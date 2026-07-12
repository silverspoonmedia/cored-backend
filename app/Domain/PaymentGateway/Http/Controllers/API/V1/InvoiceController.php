<?php

namespace App\Domain\PaymentGateway\Http\Controllers\API\V1;

use App\Http\Controllers\Concerns\RespondsWithListDisplayMode;
use App\Http\Controllers\Controller;
use App\Domain\PaymentGateway\Http\Requests\IndexInvoiceRequest;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Domain\PaymentGateway\Http\Resources\InvoiceResource;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Services\InvoiceService;
use App\Support\ApiResponder;
use App\Support\DataTable\DataTableResponseFactory;
use App\Domain\PaymentGateway\Support\DataTable\InvoiceRowActionResolver;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    use ResolvesResourceArray;
    use RespondsWithListDisplayMode;

    public function __construct(
        private readonly InvoiceService $service,
        private readonly DataTableResponseFactory $dataTables,
        private readonly InvoiceRowActionResolver $rowActions,
    ) {}

    public function index(IndexInvoiceRequest $request): JsonResponse
    {
        $invoices = $this->service->list();

        return $this->respondToListDisplayMode(
            $request,
            fn (): JsonResponse => ApiResponder::success(
                $this->resolveResource(InvoiceResource::collection($invoices)),
                __('api/payments.invoices.list'),
            ),
            fn (): JsonResponse => $this->dataTables->respond(
                $invoices,
                fn (Invoice $invoice) => $this->resolveResource(new InvoiceResource($invoice)),
                $this->rowActions,
            ),
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return ApiResponder::success(
            $this->resolveResource(new InvoiceResource($this->service->get($invoice->id))),
            __('api/payments.invoices.show'),
        );
    }
}
