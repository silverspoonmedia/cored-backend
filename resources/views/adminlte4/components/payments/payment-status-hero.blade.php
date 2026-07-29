@props([
    'eyebrow' => '',
    'title' => '',
    'amount' => null,
    'currency' => 'IDR',
    'badges' => [],
])

<div class="ois-hero" role="status" aria-live="polite" aria-atomic="true" data-payment-hero>
    @if ($eyebrow !== '')
        <div class="ois-hero__eyebrow">{{ $eyebrow }}</div>
    @endif

    <h1 class="ois-hero__title">{{ $title }}</h1>

    @if ($amount !== null && $amount !== '')
        {{-- Prefer Resource *_formatted (currency included). Raw numeric + currency is legacy only. --}}
        <p class="ois-hero__amount" data-payment-hero-amount>
            @if (is_numeric($amount))
                {{ trim($currency.' '.$amount) }}
            @else
                {{ $amount }}
            @endif
        </p>
    @endif

    @if (is_array($badges) && $badges !== [])
        <div class="ois-hero__badges">
            @foreach ($badges as $badge)
                @php
                    $label = data_get($badge, 'label', '');
                    $variant = data_get($badge, 'variant', 'secondary');
                    $key = data_get($badge, 'key', '');
                    $class = match ($variant) {
                        'warning' => 'ois-status-warning',
                        'success' => 'ois-status-success',
                        'danger' => 'ois-status-danger',
                        default => 'ois-status-secondary',
                    };
                @endphp
                @if ($label !== '')
                    <span
                        class="badge {{ $class }}"
                        @if ($key !== '') data-payment-badge="{{ $key }}" @endif
                    >{{ $label }}</span>
                @endif
            @endforeach
        </div>
    @endif
</div>
