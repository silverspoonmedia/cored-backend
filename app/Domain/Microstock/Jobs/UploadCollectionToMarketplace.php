<?php

namespace App\Domain\Microstock\Jobs;

use App\Domain\Microstock\Contracts\MarketplaceTransport;
use App\Domain\Microstock\Enums\MarketplaceCredentialStatus;
use App\Domain\Microstock\Enums\MarketplaceUploadStatus;
use App\Domain\Microstock\Exceptions\MarketplaceUploadException;
use App\Domain\Microstock\Models\MarketplaceCredential;
use App\Domain\Microstock\Models\MarketplaceUpload;
use App\Domain\Microstock\Services\MarketplaceUploadService;
use App\Domain\Microstock\Support\MicrostockRealtime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * One marketplace batch (decision:
 * docs/domains/microstock/reference-marketplace-upload.md).
 *
 * Explicit `tries`/`backoff`/`timeout`: the transfer is idempotent at the
 * marketplace (same filenames overwrite the same drop), so a retry is safe, but
 * an unbounded retry would keep a broken credential alive forever. A failure is
 * recorded on both the attempt row and the credential, because the credential is
 * what the owner has to fix.
 */
class UploadCollectionToMarketplace implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    public int $timeout = 900;

    public function __construct(
        private readonly string $uploadId,
    ) {}

    public function handle(MarketplaceTransport $transport, MarketplaceUploadService $uploads): void
    {
        $upload = MarketplaceUpload::query()->with(['credential', 'folder'])->find($this->uploadId);

        if ($upload === null || $upload->credential === null || $upload->folder === null) {
            return;
        }

        if ($upload->status?->isTerminal() === true) {
            return;
        }

        $credential = $upload->credential;
        $profile = $uploads->profile($upload->marketplace);
        $plan = $uploads->plan($upload->folder, $credential, $profile);

        if ($plan['files'] === []) {
            $this->markFailed($upload, $credential, __('api/microstock.upload_nothing_finalized'));

            return;
        }

        $upload->forceFill([
            'status' => MarketplaceUploadStatus::Uploading,
            'started_at' => now(),
            'file_count' => count($plan['files']),
            'byte_count' => $plan['bytes'],
        ])->save();

        try {
            $transport->upload(
                $this->connectionFor($credential, $profile),
                $plan['remote_path'],
                $plan['files'],
                $plan['manifest_name'],
                $plan['manifest_contents'],
            );
        } catch (MarketplaceUploadException $e) {
            $this->markFailed($upload, $credential, $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($upload, $credential, __('api/microstock.upload_failed'));

            return;
        }

        $upload->forceFill([
            'status' => MarketplaceUploadStatus::Uploaded,
            'finished_at' => now(),
            'error' => null,
        ])->save();

        $credential->forceFill([
            'status' => MarketplaceCredentialStatus::Verified,
            'last_error' => null,
            'last_verified_at' => now(),
            'last_used_at' => now(),
        ])->save();

        app(MicrostockRealtime::class)->collectionProgress($upload->folder->refresh(), force: true);
    }

    public function failed(Throwable $e): void
    {
        report($e);

        $upload = MarketplaceUpload::query()->with('credential')->find($this->uploadId);

        if ($upload === null || $upload->credential === null) {
            return;
        }

        $this->markFailed($upload, $upload->credential, __('api/microstock.upload_failed'));
    }

    /**
     * Connection array for the transport. The credential's secret wins over the
     * profile default, so a per-account host override needs no config change.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function connectionFor(MarketplaceCredential $credential, array $profile): array
    {
        $secret = $credential->secret_payload;
        $secret = is_array($secret) ? $secret : [];

        return [
            'host' => (string) ($secret['host'] ?? $profile['host'] ?? ''),
            'port' => (int) ($secret['port'] ?? $profile['port'] ?? 22),
            'username' => (string) ($secret['username'] ?? ''),
            'password' => $secret['password'] ?? null,
            'private_key' => $secret['private_key'] ?? null,
            'host_key_fingerprint' => (string) ($secret['host_key_fingerprint'] ?? $profile['host_key_fingerprint'] ?? ''),
        ];
    }

    private function markFailed(MarketplaceUpload $upload, MarketplaceCredential $credential, string $message): void
    {
        $upload->forceFill([
            'status' => MarketplaceUploadStatus::Failed,
            'error' => $message,
            'finished_at' => now(),
        ])->save();

        $credential->forceFill([
            'status' => MarketplaceCredentialStatus::Failed,
            'last_error' => $message,
            'last_used_at' => now(),
        ])->save();

        if ($upload->folder !== null) {
            app(MicrostockRealtime::class)->collectionProgress($upload->folder->refresh(), force: true);
        }
    }
}
