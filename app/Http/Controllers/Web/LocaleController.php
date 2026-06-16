<?php

namespace App\Http\Controllers\Web;

use App\Models\User;
use App\Support\Localization\LocaleCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $supported = config('localization.supported_locales', ['en', 'id']);

        if (! in_array($locale, $supported, true)) {
            abort(404);
        }

        LocaleCookie::queue($locale);

        $user = Auth::guard('api')->user();

        if ($user instanceof User) {
            $this->persistLocaleForUser($user, $locale);
        }

        return redirect()->back();
    }

    private function persistLocaleForUser(User $user, string $locale): void
    {
        $column = config('localization.user_column');

        if (! is_string($column) || $column === '') {
            return;
        }

        if ($user->{$column} === $locale) {
            return;
        }

        $user->update([$column => $locale]);
    }
}
