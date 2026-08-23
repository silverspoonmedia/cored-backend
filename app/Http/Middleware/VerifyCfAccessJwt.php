<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Optional in-app verification of a Cloudflare Zero Trust Access JWT
 * (`Cf-Access-Jwt-Assertion`). OFF by default — when
 * `services.cloudflare_access.enabled` is false the middleware is a pure
 * pass-through, so behaviour is unchanged until explicitly enabled.
 *
 * Zero Trust already enforces access at the Cloudflare edge; this is a
 * defence-in-depth layer for hosts that are not behind the edge, or to prove
 * end-to-end that a request really came through Access. It does NOT replace
 * the SPA's JWT auth (`__Host-spa_jwt`) or RBAC.
 *
 * Registered manually on the routes that need it — not applied globally.
 */
class VerifyCfAccessJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('services.cloudflare_access', []);

        if (! ($config['enabled'] ?? false)) {
            return $next($request);
        }

        $teamDomain = (string) ($config['team_domain'] ?? '');
        $aud = (string) ($config['aud'] ?? '');
        $token = (string) $request->headers->get('Cf-Access-Jwt-Assertion', '');

        if ($teamDomain === '' || $aud === '' || $token === '' || ! $this->verify($token, $teamDomain, $aud)) {
            throw new AccessDeniedHttpException('Cloudflare Access verification failed.');
        }

        return $next($request);
    }

    private function verify(string $jwt, string $teamDomain, string $aud): bool
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        [$header64, $payload64, $sig64] = $parts;

        $header = json_decode((string) $this->b64urlDecode($header64), true);
        $payload = json_decode((string) $this->b64urlDecode($payload64), true);
        if (! is_array($header) || ! is_array($payload)) {
            return false;
        }

        // Expiry (60s leeway for clock skew).
        $exp = $payload['exp'] ?? 0;
        if (! is_numeric($exp) || time() - 60 >= (int) $exp) {
            return false;
        }

        // Issuer + audience.
        if (($payload['iss'] ?? '') !== $teamDomain) {
            return false;
        }
        $audiences = $payload['aud'] ?? [];
        $audiences = is_array($audiences) ? $audiences : [$audiences];
        if (! in_array($aud, $audiences, true)) {
            return false;
        }

        // Signature (RS256 against the team JWKS, cached by kid).
        if (($header['alg'] ?? '') !== 'RS256') {
            return false;
        }
        $publicKey = $this->publicKeyForKid($teamDomain, (string) ($header['kid'] ?? ''));
        if ($publicKey === null) {
            return false;
        }

        $signature = $this->b64urlDecode($sig64);
        if ($signature === null) {
            return false;
        }

        return openssl_verify("$header64.$payload64", $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Fetch + cache the Access JWKS for the team and return the PEM public key
     * matching `kid`. Cached forever per kid and rebuilt on a cache miss (the
     * key set is tiny and rotates rarely); see docs/platform/cache-policy.md.
     */
    private function publicKeyForKid(string $teamDomain, string $kid): ?string
    {
        if ($kid === '') {
            return null;
        }

        $jwk = Cache::rememberForever("cf_access_jwk:{$teamDomain}:{$kid}", function () use ($teamDomain, $kid) {
            $certs = Http::timeout(5)->get("{$teamDomain}/cdn-cgi/access/certs")->json('keys');
            if (! is_array($certs)) {
                return null;
            }
            foreach ($certs as $jwkCert) {
                if (($jwkCert['kid'] ?? '') === $kid) {
                    return $jwkCert;
                }
            }

            return null;
        });

        if (! is_array($jwk) || ($jwk['kty'] ?? '') !== 'RSA') {
            // Do not cache a miss forever — forget so a rotated key can retry.
            Cache::forget("cf_access_jwk:{$teamDomain}:{$kid}");

            return null;
        }

        return $this->jwkToPem($jwk);
    }

    private function jwkToPem(array $jwk): ?string
    {
        $n = $this->b64urlDecode((string) ($jwk['n'] ?? ''));
        $e = $this->b64urlDecode((string) ($jwk['e'] ?? ''));
        if ($n === null || $e === null) {
            return null;
        }

        $modulus = $this->derSequence($this->derInteger($n));
        $exponent = $this->derInteger($e);
        $rsaPublicKey = $this->derSequence($modulus.$exponent);

        // Wrap in SubjectPublicKeyInfo: rsaEncryption OID + NULL params.
        $algorithm = $this->derSequence(hex2bin('06092a864886f70d010101')."\x05\x00");
        $bitString = "\x03".$this->derLength(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;
        $spki = $this->derSequence($algorithm.$bitString);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spki), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function derSequence(string $content): string
    {
        return "\x30".$this->derLength(strlen($content)).$content;
    }

    private function derInteger(string $bytes): string
    {
        // Ensure positive (prepend 0x00 if the high bit is set).
        if (ord($bytes[0]) > 0x7F) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->derLength(strlen($bytes)).$bytes;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $encoded = '';
        while ($length > 0) {
            $encoded = chr($length & 0xFF).$encoded;
            $length >>= 8;
        }

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    private function b64urlDecode(string $data): ?string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/').str_repeat('=', 3 - (3 + strlen($data)) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
