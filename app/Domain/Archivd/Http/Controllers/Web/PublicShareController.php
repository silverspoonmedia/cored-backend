<?php

namespace App\Domain\Archivd\Http\Controllers\Web;

use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Archivd\Services\FileService;
use App\Domain\Archivd\Services\FolderService;
use App\Http\Controllers\Controller;
use App\Support\Presentation\BytePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Guest "anyone with the link" pages.
 * Download hop requires a Laravel temporary signed URL, then 302 to S3 (no byte proxy).
 * JSON responses power the SPA shell (`Accept: application/json`); non-JSON
 * requests are redirected to the SPA origin so the SPA router can take over.
 */
class PublicShareController extends Controller
{
    public function __construct(
        private readonly FileService $files,
        private readonly FolderService $folders,
    ) {}

    public function showFile(Request $request, string $file): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return $this->redirectToSpa("/s/f/{$file}");
        }

        try {
            $model = $this->files->publicShow($file);
        } catch (ArchivdException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statusCode());
        }

        return response()->json(['data' => [
            'file' => [
                'id' => $model->id,
                'name' => $model->name,
                'mime' => $model->mime,
                'size' => $model->size,
                'size_human' => BytePresenter::human((int) $model->size),
            ],
            'downloadUrl' => $this->temporaryPublicDownloadUrl($file),
        ]]);
    }

    public function downloadFile(Request $request, string $file): RedirectResponse
    {
        try {
            $payload = $this->files->publicDownload($file, $request->boolean('preview'));
        } catch (ArchivdException $e) {
            abort($e->statusCode(), $e->getMessage());
        }

        return redirect()->away($payload['url']);
    }

    public function showFolder(Request $request, string $folder): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return $this->redirectToSpa("/s/d/{$folder}");
        }

        try {
            $data = $this->folders->publicBrowse($folder);
        } catch (ArchivdException $e) {
            return response()->json(['message' => $e->getMessage()], $e->statusCode());
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

        return response()->json(['data' => ['browse' => $browse]]);
    }

    private function redirectToSpa(string $path): RedirectResponse
    {
        $base = rtrim((string) config('services.spa.url'), '/');

        return redirect()->away($base.$path);
    }

    private function temporaryPublicDownloadUrl(string $fileId): string
    {
        $ttl = (int) setting_config('archivd.public_download_signature_ttl_minutes', 5);

        return URL::temporarySignedRoute(
            'archivd.public.files.download',
            now()->addMinutes(max(1, $ttl)),
            ['file' => $fileId],
        );
    }
}
