<?php

namespace App\Domain\Microstock\Contracts;

use App\Domain\Microstock\Exceptions\MarketplaceUploadException;

/**
 * Wire transport for a marketplace batch (decision:
 * docs/domains/microstock/reference-marketplace-upload.md).
 *
 * The contract is deliberately batch-shaped: a marketplace matches metadata rows
 * to files **by filename**, so a partial transfer is worse than no transfer.
 * An implementation either delivers every file plus the manifest, or throws.
 *
 * Bound in `MicrostockServiceProvider`; tests bind a fake so no suite ever opens
 * a socket.
 */
interface MarketplaceTransport
{
    /**
     * @param  array{host: string, port: int, username: string, password?: string|null, private_key?: string|null, host_key_fingerprint?: string|null}  $connection
     * @param  list<array{name: string, disk: string, path: string, size: int}>  $files
     *
     * @throws MarketplaceUploadException
     */
    public function upload(
        array $connection,
        string $remotePath,
        array $files,
        string $manifestName,
        string $manifestContents,
    ): void;
}
