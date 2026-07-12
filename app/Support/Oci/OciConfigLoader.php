<?php

namespace App\Support\Oci;

use RuntimeException;

class OciConfigLoader
{
    /**
     * @return array{message: string}
     */
    public function configurationError(): ?array
    {
        $configFile = config('oci.config_file');
        $profile = config('oci.profile');
        $kmsKeyId = config('oci.kms_key_id');
        $cryptoEndpoint = config('oci.kms_crypto_endpoint');

        if (! is_string($configFile) || $configFile === '') {
            return ['message' => 'OCI_CONFIG_FILE is not set.'];
        }

        if (! is_string($profile) || $profile === '') {
            return ['message' => 'OCI_PROFILE is not set.'];
        }

        if (! is_string($kmsKeyId) || $kmsKeyId === '') {
            return ['message' => 'OCI_KMS_KEY_ID is not set.'];
        }

        if (! is_string($cryptoEndpoint) || $cryptoEndpoint === '') {
            return ['message' => 'OCI_KMS_CRYPTO_ENDPOINT is not set.'];
        }

        if (! is_file($configFile)) {
            return ['message' => 'OCI config file does not exist.'];
        }

        try {
            $ociConfig = $this->load();
        } catch (RuntimeException $exception) {
            return ['message' => $exception->getMessage()];
        }

        if (! is_readable($ociConfig->privateKeyPath)) {
            return ['message' => 'OCI API private key file is not readable.'];
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return $this->configurationError() === null;
    }

    public function load(): OciConfig
    {
        $configFile = (string) config('oci.config_file');
        $profile = (string) config('oci.profile');

        if ($configFile === '' || $profile === '') {
            throw new RuntimeException('OCI config file or profile is not set.');
        }

        if (! is_file($configFile)) {
            throw new RuntimeException('OCI config file does not exist.');
        }

        $parsed = parse_ini_file($configFile, true, INI_SCANNER_RAW);

        if ($parsed === false) {
            throw new RuntimeException('OCI config file could not be parsed.');
        }

        if (! isset($parsed[$profile]) || ! is_array($parsed[$profile])) {
            throw new RuntimeException("OCI profile [{$profile}] was not found in the config file.");
        }

        $section = $parsed[$profile];

        $tenancy = $this->requiredString($section, 'tenancy');
        $user = $this->requiredString($section, 'user');
        $fingerprint = $this->requiredString($section, 'fingerprint');
        $keyFile = $this->requiredString($section, 'key_file');
        $region = $this->requiredString($section, 'region');

        $privateKeyPath = $this->resolveKeyFilePath($configFile, $keyFile);

        if (! is_readable($privateKeyPath)) {
            throw new RuntimeException('OCI API private key file is not readable.');
        }

        return new OciConfig(
            tenancy: $tenancy,
            user: $user,
            fingerprint: $fingerprint,
            privateKeyPath: $privateKeyPath,
            region: $region,
        );
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function requiredString(array $section, string $key): string
    {
        $value = $section[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("OCI config is missing required key [{$key}].");
        }

        return trim($value);
    }

    private function resolveKeyFilePath(string $configFile, string $keyFile): string
    {
        if ($this->isAbsolutePath($keyFile)) {
            return $keyFile;
        }

        return dirname($configFile).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $keyFile);
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
