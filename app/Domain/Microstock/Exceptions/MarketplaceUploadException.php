<?php

namespace App\Domain\Microstock\Exceptions;

/**
 * Transport-level failure of a marketplace transfer. Thrown by a
 * `MarketplaceTransport` implementation and caught by
 * `MarketplaceUploadService`, which records it on the attempt row and marks the
 * credential `failed`.
 *
 * The message is owner-facing, so it must never embed a secret (the connection
 * array carries the password). The transport is responsible for stripping
 * credentials out of a library message before wrapping it.
 */
class MarketplaceUploadException extends \RuntimeException {}
