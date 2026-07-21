<?php

namespace App\Support\Product\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponder;
use App\Support\Product\Http\Resources\ProductActivationResource;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductActivationController extends Controller
{
    public function __construct(private readonly ProductActivationService $activations) {}

    public function index(Request $request): JsonResponse
    {
        $activations = $this->activations->forUser($request->user());

        return ApiResponder::success(
            ProductActivationResource::collection($activations),
            __('product-activations.list_success'),
        );
    }

    public function show(Request $request, string $productKey): JsonResponse
    {
        $activation = $this->activations->findOrFail($request->user()->id, $productKey);

        return ApiResponder::success(
            new ProductActivationResource($activation),
            __('product-activations.show_success'),
        );
    }
}
