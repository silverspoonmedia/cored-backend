<?php

/**
 * Product domain stub — catalog Items CRUD retired after FileHosting became
 * the primary product-domain example. Routes intentionally empty.
 *
 * See docs/domains/product/README.md and docs/domains/file-hosting/README.md.
 */

use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('product')->name('product.')->group(function (): void {
    // Stub: no public product catalog endpoints.
});
