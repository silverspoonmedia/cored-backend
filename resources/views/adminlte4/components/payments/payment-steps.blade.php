@props([
    'currentStep' => 1,
    'completed' => false,
])

@php
    $steps = [
        1 => [
            'title' => __('web/payments.show.payment_steps.choose_channel'),
            'body' => __('web/payments.show.payment_steps.choose_channel_hint'),
        ],
        2 => [
            'title' => __('web/payments.show.payment_steps.pay'),
            'body' => __('web/payments.show.payment_steps.pay_hint'),
        ],
        3 => [
            'title' => __('web/payments.show.payment_steps.confirm'),
            'body' => __('web/payments.show.payment_steps.confirm_hint'),
        ],
    ];
@endphp

<div
    class="ois-steps"
    aria-label="{{ __('web/payments.show.payment_steps.aria') }}"
    data-payment-steps
    data-current-step="{{ (int) $currentStep }}"
    data-completed="{{ $completed ? '1' : '0' }}"
>
    @foreach ($steps as $number => $step)
        @php
            $stateClass = $number < $currentStep
                ? 'is-done'
                : ($number === $currentStep ? ($completed ? 'is-done' : 'is-active') : '');
        @endphp
        <div class="ois-step {{ $stateClass }}" data-payment-step="{{ $number }}">
            <span class="ois-step__num">{{ $number }}</span>
            <div>
                <p class="ois-step__title">{{ $step['title'] }}</p>
                <p class="ois-step__body">{{ $step['body'] }}</p>
            </div>
        </div>
    @endforeach
</div>
