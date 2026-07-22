<?php

namespace App\Domain\Vtual\Integrations\Concerns;

use App\Domain\Vtual\Exceptions\PlatformApiException;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\PlatformCredential;
use App\Domain\Vtual\Services\PlatformCredentialService;

trait UsesPlatformCredential
{
    protected ?PlatformCredential $credential = null;

    public function withCredential(PlatformCredential $credential): static
    {
        $clone = clone $this;
        $clone->credential = $credential;

        return $clone;
    }

    protected function requireCredential(): PlatformCredential
    {
        if ($this->credential === null) {
            throw new VtualException(__('api/vtual.integrations.credential_required'), 422);
        }

        return $this->credential;
    }

    protected function handleAuthFailure(PlatformApiException $e, PlatformCredentialService $credentials): never
    {
        $credential = $this->credential;
        if ($credential !== null && in_array($e->upstreamStatus, [401, 403], true)) {
            // PlatformHttp already wrote ErrorLog for this HTTP failure (with credential_id).
            $credentials->recordAuthFailure($credential, $e->getMessage());
        }

        throw $e;
    }

    /**
     * Safe ops context for PlatformHttp — never secrets.
     *
     * @return array{credential_id?: string, operation?: string}
     */
    protected function httpLogContext(string $operation): array
    {
        $context = ['operation' => $operation];
        if ($this->credential !== null) {
            $context['credential_id'] = (string) $this->credential->id;
        }

        return $context;
    }
}
