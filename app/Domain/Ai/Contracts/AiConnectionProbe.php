<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Support\AiConnectionTestResult;

interface AiConnectionProbe
{
    /**
     * Whether this probe handles the connection's driver.
     */
    public function supports(AiConnection $connection): bool;

    /**
     * Probe the live endpoint with the stored secret. Must not persist;
     * the caller writes status/error columns.
     *
     * @param  array<string, string>  $secretPayload  Decrypted secret payload
     */
    public function probe(AiConnection $connection, array $secretPayload): AiConnectionTestResult;
}
