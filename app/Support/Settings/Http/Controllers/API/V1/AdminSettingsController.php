<?php

namespace App\Support\Settings\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponder;
use App\Support\Settings\Http\Requests\UpdateSettingsGroupRequest;
use App\Support\Settings\SettingsService;
use App\Support\Settings\Support\SettingsRealtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SettingsRealtime $realtime,
    ) {}

    public function groups(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('view settings'), 403);

        return ApiResponder::success(
            ['groups' => $this->settings->groups()],
            __('settings.groups_success'),
        );
    }

    public function show(Request $request, string $group): JsonResponse
    {
        abort_unless($request->user()?->can('view settings'), 403);

        if (! in_array($group, $this->settings->groups(), true)) {
            return ApiResponder::error(__('settings.unknown_group', ['group' => $group]), 404);
        }

        return ApiResponder::success(
            [
                'group' => $group,
                'settings' => $this->settings->presentGroup($group),
            ],
            __('settings.show_success'),
        );
    }

    public function update(UpdateSettingsGroupRequest $request, string $group): JsonResponse
    {
        if (! in_array($group, $this->settings->groups(), true)) {
            return ApiResponder::error(__('settings.unknown_group', ['group' => $group]), 404);
        }

        $updated = $this->settings->updateGroup(
            $group,
            $request->settingsPayload(),
            $request->user()?->id,
        );

        $this->realtime->groupChanged($group, 'updated');

        return ApiResponder::success(
            [
                'group' => $group,
                'settings' => $updated,
            ],
            __('settings.updated'),
        );
    }
}
