<?php

namespace App\Domain\Archivd\Console\Commands;

use App\Domain\Archivd\Services\HashBlocklistService;
use App\Domain\Archivd\Support\Sha256Checksum;
use Illuminate\Console\Command;

/**
 * Add/remove a sha256 from the DMCA hash blocklist.
 *
 * Blocked files can never be publicly shared (enabling anyone_with_link is
 * rejected and guest public access returns 404). Checksums are computed
 * post-upload by the ComputeHostedFileChecksum job.
 */
class BlocklistHash extends Command
{
    protected $signature = 'archivd:blocklist-hash {sha256 : sha256 hex of the file} {--reason= : Why it is blocked (DMCA ref, etc.)} {--remove : Remove from the blocklist}';

    protected $description = 'Add or remove a sha256 from the Archivd DMCA hash blocklist';

    public function handle(HashBlocklistService $blocklist): int
    {
        $sha256 = Sha256Checksum::normalize((string) $this->argument('sha256'));

        if (! Sha256Checksum::isValid($sha256)) {
            $this->error('Invalid sha256: expected 64 lowercase hex chars.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            $removed = $blocklist->unblock($sha256);
            $this->info($removed
                ? "Removed {$sha256} from the hash blocklist."
                : "No blocklist entry for {$sha256}.");

            return self::SUCCESS;
        }

        $blocklist->block($sha256, $this->option('reason') ?: null);
        $this->info("Blocked {$sha256}.");

        return self::SUCCESS;
    }
}
