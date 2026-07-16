<?php

namespace App\Domain\Identity\Http\Controllers\Web;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Web\Controller;
use App\Support\Localization\LocalePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (! LocalePreference::isSupported($locale)) {
            abort(404);
        }

        $user = Auth::guard('api')->user();
        LocalePreference::apply($locale, $user instanceof User ? $user : null);

        return redirect()->back();
    }
}
