@props([
    'eyebrow' => '',
    'title' => '',
    'amount' => null,
    'currency' => 'IDR',
    'badges' => [],
])

<div class="ois-hero" role="status" aria-live="polite" aria-atomic="true">
    @if ($eyebrow !== '')
        <div class="ois-hero__eyebrow">{{ $eyebrow }}</div>
    @endif

    <h1 class="ois-hero__title">{{ $title }}</h1>

    @if ($amount !== null)
        <p class="ois-hero__amount">{{ $currency }} {{ is_numeric($amount) ? number_format((float) $amount, 2) : $amount }}</p>
    @endif

    @if (is_array($badges) && $badges !== [])
        <div class="ois-hero__badges">
            @foreach ($badges as $badge)
                @php
                    $label = data_get($badge, 'label', '');
                    $variant = data_get($badge, 'variant', 'secondary');
                    $class = match ($variant) {
                        'warning' => 'ois-status-warning',
                        'success' => 'ois-status-success',
                        'danger' => 'ois-status-danger',
                        default => 'ois-status-secondary',
                    };
                @endphp
                @if ($label !== '')
                    <span class="badge {{ $class }}">{{ $label }}</span>
                @endif
            @endforeach
        </div>
    @endif
</div>
