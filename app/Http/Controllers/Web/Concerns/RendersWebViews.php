<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Support\Web\WebLayout;
use Illuminate\Contracts\View\View;

/**
 * Resolve Blade screens against config('web.layout') instead of hardcoding a layout prefix.
 */
trait RendersWebViews
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function webView(string $view, array $data = []): View
    {
        return view(WebLayout::view($view), $data);
    }
}
