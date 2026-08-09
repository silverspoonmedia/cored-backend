<x-adminlte4::layouts.app :title="__('web/users.edit_title')">
    <div class="row">
        <div class="col-lg-6">
            <x-adminlte4::card :title="__('web/users.edit_card_title', ['name' => $user->name])">
                @include(config('web.layout').'.users.partials.form', [
                    'action' => route('users.update', $user->id),
                    'method' => 'PUT',
                    'isEdit' => true,
                    'submitLabel' => __('web/users.update_submit'),
                ])
            </x-adminlte4::card>
        </div>
        <div class="col-lg-6">
            @include(config('web.layout').'.users.partials.activity-history', ['activityLogs' => $activityLogs ?? []])
        </div>
    </div>
</x-adminlte4::layouts.app>
