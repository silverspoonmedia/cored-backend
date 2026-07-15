<?php

namespace App\Domain\FileHosting\Http\Controllers\Web;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Services\FileService;
use App\Domain\FileHosting\Services\FolderService;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Guest "anyone with the link" pages.
 * Download hop requires a Laravel temporary signed URL, then 302 to S3 (no byte proxy).
 */
class PublicShareController extends Controller
{
    public function __construct(
        private readonly FileService $files,
        private readonly FolderService $folders,
    ) {}

    public function showFile(string $file): View|RedirectResponse
    {
        try {
            $model = $this->files->publicShow($file);
        } catch (FileHostingException $e) {
            abort($e->statusCode(), $e->getMessage());
        }

        return $this->webView('file-hosting.public-file', [
            'file' => [
                'id' => $model->id,
                'name' => $model->name,
                'mime' => $model->mime,
                'size' => $model->size,
            ],
            'downloadUrl' => $this->temporaryPublicDownloadUrl($file),
        ]);
    }

    public function downloadFile(string $file): RedirectResponse
    {
        try {
            $payload = $this->files->publicDownload($file);
        } catch (FileHostingException $e) {
            abort($e->statusCode(), $e->getMessage());
        }

        return redirect()->away($payload['url']);
    }

    public function showFolder(string $folder): View|RedirectResponse
    {
        try {
            $data = $this->folders->publicBrowse($folder);
        } catch (FileHostingException $e) {
            abort($e->statusCode(), $e->getMessage());
        }

        return $this->webView('file-hosting.public-folder', [
            'browse' => [
                'owner_id' => $data['owner_id'],
                'folder' => [
                    'id' => $data['folder']->id,
                    'name' => $data['folder']->name,
                ],
                'breadcrumb' => $data['breadcrumb'],
                'folders' => $data['folders']->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                ])->values()->all(),
                'files' => $data['files']->map(fn ($f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'mime' => $f->mime,
                    'size' => $f->size,
                ])->values()->all(),
            ],
        ]);
    }

    private function temporaryPublicDownloadUrl(string $fileId): string
    {
        $ttl = (int) config('file-hosting.public_download_signature_ttl_minutes', 5);

        return URL::temporarySignedRoute(
            'file-hosting.public.files.download',
            now()->addMinutes(max(1, $ttl)),
            ['file' => $fileId],
        );
    }
}
