<?php

namespace App\Support;

/**
 * Typed catalog of environment variables declared in .env.example.
 * Used for template quoting validation and auto-generated secret metadata.
 */
final class EnvironmentVariableCatalog
{
    public const TYPE_STRING = 'string';

    public const TYPE_BOOL = 'bool';

    public const TYPE_INT = 'int';

    public const TYPE_NULL = 'null';

  /** @var list<string> */
    private const AUTO_GENERATED = [
        'APP_KEY',
        'JWT_SECRET',
        'ENCRYPTION_BLIND_INDEX_PEPPER',
    ];

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'APP_NAME' => self::TYPE_STRING,
            'APP_ENV' => self::TYPE_STRING,
            'APP_KEY' => self::TYPE_STRING,
            'APP_PREVIOUS_KEYS' => self::TYPE_STRING,
            'APP_DEBUG' => self::TYPE_BOOL,
            'APP_URL' => self::TYPE_STRING,
            'APP_LOCALE' => self::TYPE_STRING,
            'APP_FALLBACK_LOCALE' => self::TYPE_STRING,
            'APP_FAKER_LOCALE' => self::TYPE_STRING,
            'APP_MAINTENANCE_DRIVER' => self::TYPE_STRING,
            'BCRYPT_ROUNDS' => self::TYPE_INT,
            'OCI_CONFIG_FILE' => self::TYPE_STRING,
            'OCI_PROFILE' => self::TYPE_STRING,
            'OCI_KMS_KEY_ID' => self::TYPE_STRING,
            'OCI_KMS_CRYPTO_ENDPOINT' => self::TYPE_STRING,
            'OCI_HTTP_TIMEOUT' => self::TYPE_INT,
            'ENCRYPTION_KMS_ENABLED' => self::TYPE_BOOL,
            'ENCRYPTION_DECRYPTED_CACHE_MAX_TTL' => self::TYPE_INT,
            'ENCRYPTION_LOG_INVOICE_PAYLOAD_DECRYPTS' => self::TYPE_BOOL,
            'ENCRYPTION_AUDIT_DECRYPTS' => self::TYPE_STRING,
            'ENCRYPTION_BLIND_INDEX_PEPPER' => self::TYPE_STRING,
            'ENCRYPTION_SESSION_ENABLED' => self::TYPE_BOOL,
            'WEB_LAYOUT' => self::TYPE_STRING,
            'LOCALE_COOKIE' => self::TYPE_STRING,
            'LOCALE_COOKIE_MINUTES' => self::TYPE_INT,
            'JWT_ALGO' => self::TYPE_STRING,
            'JWT_SECRET' => self::TYPE_STRING,
            'JWT_TTL' => self::TYPE_INT,
            'JWT_REFRESH_TTL' => self::TYPE_INT,
            'JWT_REMEMBER_TTL' => self::TYPE_INT,
            'AUTH_GUARD' => self::TYPE_STRING,
            'AUTH_DEFAULT_ROLE' => self::TYPE_STRING,
            'AUTH_VERIFICATION_EXPIRE' => self::TYPE_INT,
            'TWO_FACTOR_WINDOW' => self::TYPE_INT,
            'TWO_FACTOR_ISSUER' => self::TYPE_STRING,
            'LOG_CHANNEL' => self::TYPE_STRING,
            'LOG_STACK' => self::TYPE_STRING,
            'LOG_DEPRECATIONS_CHANNEL' => self::TYPE_NULL,
            'LOG_LEVEL' => self::TYPE_STRING,
            'ERRORLOG_ENABLED' => self::TYPE_BOOL,
            'ERRORLOG_CLEAN_AFTER_DAYS' => self::TYPE_INT,
            'DB_CONNECTION' => self::TYPE_STRING,
            'DB_HOST' => self::TYPE_STRING,
            'DB_PORT' => self::TYPE_INT,
            'DB_DATABASE' => self::TYPE_STRING,
            'DB_USERNAME' => self::TYPE_STRING,
            'DB_PASSWORD' => self::TYPE_STRING,
            'ALLOW_DESTRUCTIVE_DB' => self::TYPE_BOOL,
            'SESSION_DRIVER' => self::TYPE_STRING,
            'SESSION_LIFETIME' => self::TYPE_INT,
            'SESSION_ENCRYPT' => self::TYPE_BOOL,
            'SESSION_PATH' => self::TYPE_STRING,
            'SESSION_DOMAIN' => self::TYPE_NULL,
            'SESSION_SECURE_COOKIE' => self::TYPE_BOOL,
            'BROADCAST_CONNECTION' => self::TYPE_STRING,
            'FILESYSTEM_DISK' => self::TYPE_STRING,
            'QUEUE_CONNECTION' => self::TYPE_STRING,
            'CACHE_STORE' => self::TYPE_STRING,
            'MEMCACHED_HOST' => self::TYPE_STRING,
            'REDIS_CLIENT' => self::TYPE_STRING,
            'REDIS_HOST' => self::TYPE_STRING,
            'REDIS_PASSWORD' => self::TYPE_NULL,
            'REDIS_PORT' => self::TYPE_INT,
            'MAIL_MAILER' => self::TYPE_STRING,
            'MAIL_SCHEME' => self::TYPE_NULL,
            'MAIL_HOST' => self::TYPE_STRING,
            'MAIL_PORT' => self::TYPE_INT,
            'MAIL_USERNAME' => self::TYPE_NULL,
            'MAIL_PASSWORD' => self::TYPE_NULL,
            'MAIL_FROM_ADDRESS' => self::TYPE_STRING,
            'MAIL_FROM_NAME' => self::TYPE_STRING,
            'AWS_ACCESS_KEY_ID' => self::TYPE_STRING,
            'AWS_SECRET_ACCESS_KEY' => self::TYPE_STRING,
            'AWS_DEFAULT_REGION' => self::TYPE_STRING,
            'AWS_BUCKET' => self::TYPE_STRING,
            'AWS_USE_PATH_STYLE_ENDPOINT' => self::TYPE_BOOL,
            'CUSTOM_S3_REGION' => self::TYPE_STRING,
            'CUSTOM_S3_BUCKET_PUBLIC' => self::TYPE_STRING,
            'CUSTOM_S3_KEY' => self::TYPE_STRING,
            'CUSTOM_S3_SECRET' => self::TYPE_STRING,
            'CUSTOM_S3_ENDPOINT' => self::TYPE_STRING,
            'CUSTOM_S3_BUCKET_PUBLIC_DOMAIN' => self::TYPE_STRING,
            'HCAPTCHA_ENABLED' => self::TYPE_BOOL,
            'HCAPTCHA_SECRET' => self::TYPE_STRING,
            'HCAPTCHA_SITEKEY' => self::TYPE_STRING,
            'APP_NOTIFICATION_TYPE' => self::TYPE_STRING,
            'APP_NOTIFICATION_POLLING_INTERVAL' => self::TYPE_INT,
            'PUSHER_APP_ID' => self::TYPE_STRING,
            'PUSHER_APP_KEY' => self::TYPE_STRING,
            'PUSHER_APP_SECRET' => self::TYPE_STRING,
            'PUSHER_PORT' => self::TYPE_STRING,
            'PUSHER_SCHEME' => self::TYPE_STRING,
            'PUSHER_APP_CLUSTER' => self::TYPE_STRING,
            'PUSHER_HOST' => self::TYPE_STRING,
            'PAYMENT_PREFER_CHANNEL_CHECKOUT' => self::TYPE_BOOL,
            'MIDTRANS_SERVER_KEY' => self::TYPE_STRING,
            'MIDTRANS_CLIENT_KEY' => self::TYPE_STRING,
            'MIDTRANS_IS_PRODUCTION' => self::TYPE_BOOL,
            'MIDTRANS_NOTIFY_URL' => self::TYPE_STRING,
            'IPAYMU_VA_KEY' => self::TYPE_STRING,
            'IPAYMU_API_KEY' => self::TYPE_STRING,
            'IPAYMU_IS_PRODUCTION' => self::TYPE_BOOL,
            'IPAYMU_NOTIFY_URL' => self::TYPE_STRING,
            'GATEWAY_ENFORCE' => self::TYPE_BOOL,
            'GATEWAY_SECRET' => self::TYPE_STRING,
            'GATEWAY_ALLOWED_IPS' => self::TYPE_STRING,
            'VITE_APP_NAME' => self::TYPE_STRING,
            'VITE_PUSHER_APP_KEY' => self::TYPE_STRING,
            'VITE_PUSHER_APP_CLUSTER' => self::TYPE_STRING,
            'VITE_PUSHER_HOST' => self::TYPE_STRING,
            'VITE_PUSHER_PORT' => self::TYPE_STRING,
            'VITE_PUSHER_SCHEME' => self::TYPE_STRING,
        ];
    }

    public static function type(string $name): ?string
    {
        return self::types()[$name] ?? null;
    }

    public static function isAutoGenerated(string $name): bool
    {
        return in_array($name, self::AUTO_GENERATED, true);
    }

    public static function valueMatchesType(string $type, string $rawValue): bool
    {
        return match ($type) {
            self::TYPE_STRING => self::isQuotedString($rawValue),
            self::TYPE_BOOL => $rawValue === 'true' || $rawValue === 'false',
            self::TYPE_INT => preg_match('/^-?\d+$/', $rawValue) === 1,
            self::TYPE_NULL => $rawValue === 'null',
            default => false,
        };
    }

    private static function isQuotedString(string $rawValue): bool
    {
        if (! str_starts_with($rawValue, '"')) {
            return false;
        }

        $length = strlen($rawValue);

        if ($length < 2 || $rawValue[$length - 1] !== '"') {
            return false;
        }

        for ($index = 1; $index < $length - 1; $index++) {
            if ($rawValue[$index] === '\\') {
                $index++;

                continue;
            }

            if ($rawValue[$index] === '"') {
                return false;
            }
        }

        return true;
    }
}
