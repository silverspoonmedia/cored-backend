<?php

namespace App\Domain\FileHosting\Http\Controllers\Web;

use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Services\FileService;
use App\Domain\FileHosting\Services\FolderService;
use App\Http\Controllers\Web\Controller;
use App\Support\Presentation\BytePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Guest "anyone with the link" pages.
 * Download hop requires a Laravel temporary signed URL, then 302 to S3 (no byte proxy).
 * Dual JSON responses power the public soft SPA shell (`Accept: application/json`).
 */
class PublicShareController extends Controller
{
    public function __construct(
        private readonly FileService $files,
        private readonly FolderService $folders,
    ) {}

    public function showFile(Request $request, string $file): View|RedirectResponse|JsonResponse
    {
        try {
            $model = $this->files->publicShow($file);
        } catch (FileHostingException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->statusCode());
            }

            abort($e->statusCode(), $e->getMessage());
        }

        $payload = [
            'file' => [
                'id' => $model->id,
                'name' => $model->name,
                'mime' => $model->mime,
                'size' => $model->size,
                'size_human' => BytePresenter::human((int) $model->size),
            ],
            'downloadUrl' => $this->temporaryPublicDownloadUrl($file),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $payload]);
        }

        return $this->webView('file-hosting.public-file', $payload);
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

    public function showFolder(Request $request, string $folder): View|RedirectResponse|JsonResponse
    {
        try {
            $data = $this->folders->publicBrowse($folder);
        } catch (FileHostingException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->statusCode());
            }

            abort($e->statusCode(), $e->getMessage());
        }

        $browse = [
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
                'size_human' => BytePresenter::human((int) $f->size),
            ])->values()->all(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => ['browse' => $browse]]);
        }

        return $this->webView('file-hosting.public-folder', [
            'browse' => $browse,
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
