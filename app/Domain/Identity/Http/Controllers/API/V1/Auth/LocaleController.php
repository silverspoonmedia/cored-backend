<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Domain\Identity\Models\User;
use App\Support\Localization\LocaleCookie;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    use ApiResponse;

    public function update(Request $request): JsonResponse
    {
        $supported = config('localization.supported_locales', ['en', 'id', 'ja']);

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $supported)],
        ]);

        $locale = $validated['locale'];
        LocaleCookie::queue($locale);

        $user = Auth::guard('api')->user();

        if ($user instanceof User) {
            $column = config('localization.user_column');

            if (is_string($column) && $column !== '' && $user->{$column} !== $locale) {
                $user->forceFill([$column => $locale])->save();
            }
        }

        return $this->successResponse(['locale' => $locale], __('api/common.updated'));
    }
}
