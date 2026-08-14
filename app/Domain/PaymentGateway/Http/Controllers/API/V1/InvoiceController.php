<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Domain\PaymentGateway\Http\Requests\ExportInvoiceRequest;
use App\Domain\PaymentGateway\Http\Requests\IndexInvoiceRequest;
use App\Domain\PaymentGateway\Http\Resources\InvoiceResource;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\InvoiceCsvExportService;
use App\Domain\PaymentGateway\Services\InvoiceService;
use App\Domain\PaymentGateway\Support\DataTable\InvoiceRowActionResolver;
use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\DataTable\DataTableResponseFactory;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly InvoiceService $service,
        private readonly InvoiceCsvExportService $csvExport,
        private readonly DataTableResponseFactory $dataTables,
        private readonly InvoiceRowActionResolver $rowActions,
    ) {}

    public function index(IndexInvoiceRequest $request): JsonResponse
    {
        $invoices = $this->service->list();

        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => $this->respondToQueryList(
                $request,
                $invoices,
                fn ($list) => $this->resolveResource(InvoiceResource::collection($list)),
                __('api/payments.invoices.list'),
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $invoices,
                fn (Invoice $invoice) => $this->resolveResource(new InvoiceResource($invoice)),
                $this->rowActions,
            ),
        );
    }

    public function export(ExportInvoiceRequest $request): StreamedResponse
    {
        return $this->csvExport->stream($request->validated());
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return $this->successResponse(
            $this->resolveResource(new InvoiceResource($this->service->get($invoice->id))),
            __('api/payments.invoices.show'),
        );
    }
}
