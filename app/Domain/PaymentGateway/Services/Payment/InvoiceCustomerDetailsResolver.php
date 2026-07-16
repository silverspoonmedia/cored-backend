<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\Identity\Models\User;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\Subscription;
use App\Domain\Service\Models\ServicePurchase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolve buyer / customer_details for gateway checkout from:
 * request override → invoiceable → authenticated user → config phone fallback.
 */
class InvoiceCustomerDetailsResolver
{
    /**
     * iPaymu setBuyer shape.
     *
     * @param  array{name?: string, email?: string, phone?: string}|null  $override
     * @return array{name: string, email: string, phone: string}
     */
    public function forIpaymu(Invoice $invoice, ?array $override = null, ?User $user = null): array
    {
        $details = $this->resolve($invoice, $override, $user);

        return [
            'name' => $details['name'],
            'email' => $details['email'],
            'phone' => $details['phone'],
        ];
    }

    /**
     * Midtrans customer_details shape.
     *
     * @param  array{first_name?: string, name?: string, email?: string, phone?: string}|null  $override
     * @return array{first_name: string, email: string, phone: string}
     */
    public function forMidtrans(Invoice $invoice, ?array $override = null, ?User $user = null): array
    {
        $normalized = null;

        if (is_array($override) && $override !== []) {
            $normalized = [
                'name' => $override['first_name'] ?? $override['name'] ?? null,
                'email' => $override['email'] ?? null,
                'phone' => $override['phone'] ?? null,
            ];
        }

        $details = $this->resolve($invoice, $normalized, $user);

        return [
            'first_name' => $details['name'],
            'email' => $details['email'],
            'phone' => $details['phone'],
        ];
    }

    /**
     * @param  array{name?: string|null, email?: string|null, phone?: string|null}|null  $override
     * @return array{name: string, email: string, phone: string}
     */
    public function resolve(Invoice $invoice, ?array $override = null, ?User $user = null): array
    {
        $fromInvoiceable = $this->fromInvoiceable($invoice);
        $fromUser = $this->fromUser($user ?? $this->authUser() ?? $this->userFromInvoiceable($invoice));

        $name = $this->firstNonEmpty(
            $override['name'] ?? null,
            $fromInvoiceable['name'] ?? null,
            $fromUser['name'] ?? null,
            'Customer',
        );

        $email = $this->firstNonEmpty(
            $override['email'] ?? null,
            $fromInvoiceable['email'] ?? null,
            $fromUser['email'] ?? null,
        );

        $phone = $this->firstNonEmpty(
            $override['phone'] ?? null,
            $fromInvoiceable['phone'] ?? null,
            $fromUser['phone'] ?? null,
            (string) config('payment.default_buyer_phone', '081000000000'),
        );

        if ($email === null || $email === '') {
            throw new HttpException(422, __('api/payments.ipaymu.buyer_required'));
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    /**
     * @return array{name?: string, email?: string, phone?: string}
     */
    private function fromInvoiceable(Invoice $invoice): array
    {
        $invoice->loadMissing('invoiceable');

        $invoiceable = $invoice->invoiceable;

        if ($invoiceable instanceof Subscription) {
            return array_filter([
                'name' => $this->stringOrNull($invoiceable->subscriber_name),
                'email' => $this->stringOrNull($invoiceable->subscriber_email),
                'phone' => $this->stringOrNull($invoiceable->subscriber_phone),
            ], fn ($v) => $v !== null);
        }

        if ($invoiceable instanceof ServicePurchase) {
            $invoiceable->loadMissing('user');

            return $this->fromUser($invoiceable->user);
        }

        return [];
    }

    private function userFromInvoiceable(Invoice $invoice): ?User
    {
        $invoice->loadMissing('invoiceable');

        if ($invoice->invoiceable instanceof ServicePurchase) {
            $invoice->invoiceable->loadMissing('user');

            return $invoice->invoiceable->user;
        }

        return null;
    }

    /**
     * @return array{name?: string, email?: string, phone?: string}
     */
    private function fromUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_filter([
            'name' => $this->stringOrNull($user->name),
            'email' => $this->stringOrNull($user->email),
        ], fn ($v) => $v !== null);
    }

    private function authUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function firstNonEmpty(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
