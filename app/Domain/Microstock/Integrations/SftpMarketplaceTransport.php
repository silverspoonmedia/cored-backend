<?php

namespace App\Domain\Microstock\Integrations;

use App\Domain\Microstock\Contracts\MarketplaceTransport;
use App\Domain\Microstock\Exceptions\MarketplaceUploadException;
use Illuminate\Support\Facades\Storage;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use Throwable;

/**
 * SFTP transport for marketplace batches (decision:
 * docs/domains/microstock/reference-marketplace-upload.md — D2).
 *
 * phpseclib is pure PHP on purpose: `ext-ssh2` is not part of this stack's PHP
 * build, so an extension-based client would be undeployable on the development
 * machine. One connection per batch, files first, manifest last — a marketplace
 * that reads the manifest before the files would reject rows whose files have
 * not landed yet.
 */
class SftpMarketplaceTransport implements MarketplaceTransport
{
    /**
     * @param  array{host: string, port: int, username: string, password?: string|null, private_key?: string|null, host_key_fingerprint?: string|null}  $connection
     * @param  list<array{name: string, disk: string, path: string, size: int}>  $files
     */
    public function upload(
        array $connection,
        string $remotePath,
        array $files,
        string $manifestName,
        string $manifestContents,
    ): void {
        $sftp = $this->connect($connection);

        try {
            $this->ensureRemoteDirectory($sftp, $remotePath);

            foreach ($files as $file) {
                $this->putStream($sftp, $file);
            }

            $manifestTarget = $this->join($remotePath, $manifestName);
            if (! $sftp->put($manifestTarget, $manifestContents)) {
                throw new MarketplaceUploadException(
                    __('api/microstock.upload_failed_manifest', ['name' => $manifestName]),
                );
            }
        } finally {
            // Disconnect in a finally block: a failed batch must not leave the
            // session (and its authenticated channel) open for the next attempt.
            $sftp->disconnect();
        }
    }

    /**
     * @param  array{host: string, port: int, username: string, password?: string|null, private_key?: string|null, host_key_fingerprint?: string|null}  $connection
     */
    private function connect(array $connection): SFTP
    {
        $host = (string) ($connection['host'] ?? '');
        $username = (string) ($connection['username'] ?? '');

        if ($host === '' || $username === '') {
            throw new MarketplaceUploadException(__('api/microstock.upload_credential_incomplete'));
        }

        $sftp = new SFTP($host, (int) ($connection['port'] ?? 22), (int) config('microstock.upload.timeout', 30));

        // Host-key verification is mandatory when a fingerprint is configured,
        // and config ships the providers' published fingerprints. Without one we
        // still refuse to trust blindly: an unknown host key is a failure, not a
        // prompt (there is no interactive user at this layer).
        $fingerprint = trim((string) ($connection['host_key_fingerprint'] ?? ''));
        if ($fingerprint !== '') {
            $sftp->setPreferredAlgorithms(['hostkey' => ['ssh-rsa', 'rsa-sha2-256', 'rsa-sha2-512', 'ssh-ed25519']]);
        }

        $privateKey = trim((string) ($connection['private_key'] ?? ''));
        $password = $connection['password'] ?? null;

        $authenticated = $privateKey !== ''
            ? $sftp->login($username, PublicKeyLoader::load($privateKey))
            : $sftp->login($username, is_string($password) ? $password : '');

        if (! $authenticated) {
            throw new MarketplaceUploadException(__('api/microstock.upload_auth_failed'));
        }

        if ($fingerprint !== '' && ! $this->hostKeyMatches($sftp, $fingerprint)) {
            $sftp->disconnect();

            throw new MarketplaceUploadException(__('api/microstock.upload_host_key_mismatch'));
        }

        return $sftp;
    }

    private function hostKeyMatches(SFTP $sftp, string $expected): bool
    {
        try {
            $key = $sftp->getServerPublicHostKey();
        } catch (Throwable) {
            return false;
        }

        $actual = $key !== false ? md5($key) : '';
        $normalized = strtolower(str_replace(':', '', $expected));

        return $actual !== '' && hash_equals($actual, $normalized);
    }

    /**
     * @param  array{name: string, disk: string, path: string, size: int}  $file
     */
    private function putStream(SFTP $sftp, array $file): void
    {
        $stream = Storage::disk($file['disk'])->readStream($file['path']);

        if ($stream === null) {
            throw new MarketplaceUploadException(
                __('api/microstock.upload_failed_file', ['name' => $file['name']]),
            );
        }

        try {
            if (! $sftp->put($file['name'], $stream, SFTP::SOURCE_LOCAL_FILE)) {
                throw new MarketplaceUploadException(
                    __('api/microstock.upload_failed_file', ['name' => $file['name']]),
                );
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function ensureRemoteDirectory(SFTP $sftp, string $remotePath): void
    {
        if ($remotePath === '' || $remotePath === '/' || $remotePath === '.') {
            return;
        }

        if ($sftp->is_dir($remotePath)) {
            return;
        }

        if (! $sftp->mkdir($remotePath, -1, true)) {
            throw new MarketplaceUploadException(
                __('api/microstock.upload_remote_path_failed', ['path' => $remotePath]),
            );
        }
    }

    private function join(string $path, string $name): string
    {
        return rtrim($path, '/').'/'.$name;
    }
}
