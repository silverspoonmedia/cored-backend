<x-adminlte4::layouts.app :title="__('web/notification-audiences.edit_title')">
    <x-adminlte4::card :title="__('web/notification-audiences.edit_title')">
        <x-adminlte4::form.ajax :action="route('notification-audiences.update', $audience['type'])" method="PUT">
            <p class="text-muted">{{ $audience['label'] ?? $audience['type'] }} <code>{{ $audience['type'] }}</code></p>

            <div class="mb-3">
                <label class="form-label">{{ __('web/notification-audiences.form.roles') }}</label>
                <select name="roles[]" class="form-select" multiple size="6">
                    @foreach ($assignableRoles as $role)
                        <option
                            value="{{ $role['name'] }}"
                            @selected(in_array($role['name'], $audience['roles'] ?? [], true))
                        >{{ $role['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-check form-switch mb-3">
                <input type="hidden" name="notify_subject_user" value="0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="notify-subject-user"
                    name="notify_subject_user"
                    value="1"
                    @checked($audience['notify_subject_user'] ?? false)
                >
                <label class="form-check-label" for="notify-subject-user">
                    {{ __('web/notification-audiences.form.notify_subject_user') }}
                </label>
            </div>

            <x-adminlte4::form.submit :label="__('ui.actions.save')" />
        </x-adminlte4::form.ajax>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
