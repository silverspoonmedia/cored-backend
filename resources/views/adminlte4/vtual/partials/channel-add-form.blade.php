@php
    // Parameters:
    //   $action       — route string
    //   $transPrefix  — translation key prefix (e.g. 'web/vtual-orphans.channels')
    //   $formKind     — string identifier for JS wiring ('orphans' | 'profiles')
    //   $helpElId     — CSS id for the dynamic help div + JS hook
    //   $platforms    — platform collection
    //   $showAlert    — (optional) control success alert, defaults true
@endphp

@php
    $showAlert = $showAlert ?? true;
    $platformKey = $transPrefix . '.platform';
    $profileLabel = $transPrefix . '.profile';
    $addButton = __($transPrefix . '.add_button');
    $youtubePlaceholder = __($transPrefix . '.profile_placeholder_youtube');
    $twitchPlaceholder = __($transPrefix . '.profile_placeholder_twitch');
    $youtubeHelp = __($transPrefix . '.profile_help_youtube');
    $twitchHelp = __($transPrefix . '.profile_help_twitch');
@endphp

<x-adminlte4::form.ajax
    :action="$action"
    method="POST"
    class="border rounded p-3 mb-3"
    data-form-kind="{{ $formKind }}"
    :showAlert="$showAlert"
    data-help-youtube="{{ $youtubeHelp }}"
    data-help-twitch="{{ $twitchHelp }}"
    data-placeholder-youtube="{{ $youtubePlaceholder }}"
    data-placeholder-twitch="{{ $twitchPlaceholder }}"
>
    <div class="row g-2 align-items-end">
        <div class="col-md-3" data-form-field="platform_key">
            <label class="form-label" for="platform_key">{{ __($platformKey) }}</label>
            <select id="platform_key" name="platform_key" class="form-select form-select-sm" required>
                @foreach ($platforms as $platform)
                    @if (!empty($platform['enabled']))
                        <option value="{{ $platform['key'] }}">{{ $platform['name'] }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="col-md-6" data-form-field="profile">
            <label class="form-label" for="profile_input">{{ __($profileLabel) }}</label>
            <input type="text" id="profile_input" name="profile" class="form-control form-control-sm font-monospace"
                   required maxlength="512" placeholder="{{ $youtubePlaceholder }}">
        </div>
        <div class="col-md-3">
            <label class="form-label invisible" aria-hidden="true">&nbsp;</label>
            <x-adminlte4::form.submit :label="$addButton" class="btn btn-primary btn-sm w-100" />
        </div>
    </div>
    <div class="row g-1">
        <div class="col-md-3">
            <x-adminlte4::form.error name="platform_key" />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.error name="profile" />
        </div>
        <div class="col-md-3"></div>
    </div>
    <div class="row g-2">
        <div class="col-12">
            <div class="form-text mb-0" id="{{ $helpElId }}">{{ $youtubeHelp }}</div>
        </div>
    </div>
</x-adminlte4::form.ajax>

<script>
(function () {
    'use strict';

    var channelForm = document.querySelector('[data-form-kind="{{ $formKind }}"]');
    if (!channelForm) {
        return;
    }

    var platformSelect = channelForm.querySelector('[name="platform_key"]');
    var profileInput = channelForm.querySelector('#profile_input');
    var helpEl = document.getElementById('{{ $helpElId }}');
    if (!platformSelect || !profileInput || !helpEl) {
        return;
    }

    platformSelect.addEventListener('change', applyPlatformHints);
    applyPlatformHints();

    function applyPlatformHints() {
        var key = platformSelect.value;
        if (key === 'twitch') {
            profileInput.placeholder = channelForm.dataset.placeholderTwitch || '';
            helpEl.textContent = channelForm.dataset.helpTwitch || '';
        } else {
            profileInput.placeholder = channelForm.dataset.placeholderYoutube || '';
            helpEl.textContent = channelForm.dataset.helpYoutube || '';
        }
    }
})();
</script>
