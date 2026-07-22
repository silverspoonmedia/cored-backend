<?php

namespace App\Domain\Vtual\Enums;

enum PlatformCredentialType: string
{
    case ApiKey = 'api_key';
    case OauthApp = 'oauth_app';
    case Bearer = 'bearer';

    /**
     * Required plaintext payload keys for this type.
     *
     * @return list<string>
     */
    public function requiredPayloadKeys(): array
    {
        return match ($this) {
            self::ApiKey => ['api_key'],
            self::OauthApp => ['client_id', 'client_secret'],
            self::Bearer => ['access_token'],
        };
    }

    /**
     * Optional plaintext payload keys.
     *
     * @return list<string>
     */
    public function optionalPayloadKeys(): array
    {
        return match ($this) {
            self::ApiKey => [],
            self::OauthApp => [],
            self::Bearer => ['refresh_token', 'expires_at'],
        };
    }

    /**
     * Field used for secret_hint (last 4 chars).
     */
    public function primarySecretKey(): string
    {
        return match ($this) {
            self::ApiKey => 'api_key',
            self::OauthApp => 'client_secret',
            self::Bearer => 'access_token',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
