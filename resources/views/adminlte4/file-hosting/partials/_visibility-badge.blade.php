@php
    /**
     * Visibility badge.
     *
     * @var string $visibility  'private' | 'anyone_with_link'
     */
    $visLink = \App\Domain\FileHosting\Enums\Visibility::AnyoneWithLink->value;
    $isLink = ($visibility ?? 'private') === $visLink;
@endphp
@if ($isLink)
    <span class="fh-badge fh-badge--link">
        <i class="bi bi-globe"></i>
        {{ __('web/file-hosting.visibility.anyone_with_link') }}
    </span>
@else
    <span class="fh-badge fh-badge--private">
        <i class="bi bi-lock"></i>
        {{ __('web/file-hosting.visibility.private') }}
    </span>
@endif
