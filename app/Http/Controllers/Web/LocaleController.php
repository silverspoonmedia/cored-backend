<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Localization\LocaleCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $supported = config('localization.supported_locales', ['en', 'id']);

        if (! in_array($locale, $supported, true)) {
            abort(404);
        }

        LocaleCookie::queue($locale);

        return redirect()->back();
    }
}
