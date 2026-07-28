<?php

namespace App\Domain\Microstock\Http\Controllers\Web;

use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Services\CsvExportService;
use App\Domain\Microstock\Services\MicrostockService;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Microstock web — thin shell over the File Hosting browse SPA, pinned to the
 * owner's built-in Microstock system workspace.
 */
class MicrostockController extends Controller
{
    public function __construct(
        private readonly MicrostockService $microstock,
        private readonly CsvExportService $csv,
    ) {}

    /**
     * Browse/manage assets: reuse the File Hosting SPA in the Microstock
     * system workspace context.
     *
     * Not activated yet → send the user to /services to register the free
     * Microstock workspace (or buy a slot addon).
     */
    public function index(Request $request): RedirectResponse
    {
        try {
            $workspace = $this->microstock->workspaceFor($request->user());
        } catch (MicrostockException $e) {
            return redirect()->route('services.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('file-hosting.index', [
            'workspace_id' => $workspace->id,
        ]);
    }

    /**
     * Download a CSV of finalized metadata for the owner's Microstock workspace.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $owner = $request->user();
        $result = $this->csv->build($owner);

        $filename = 'microstock-metadata-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($result): void {
            $out = fopen('php://output', 'w');
            foreach ($result['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
