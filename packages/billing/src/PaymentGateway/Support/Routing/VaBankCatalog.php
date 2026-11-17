<?php

namespace App\Domain\PaymentGateway\Support\Routing;

readonly class VaBankDefinition
{
    public function __construct(
        public string $slug,
        public string $displayName,
        public ?string $midtransCode,
        public ?string $ipaymuCode,
    ) {}

    public function channelCode(): string
    {
        return 'va_'.$this->slug;
    }

    public function supportsMidtrans(): bool
    {
        return $this->midtransCode !== null;
    }

    public function supportsIpaymu(): bool
    {
        return $this->ipaymuCode !== null;
    }
}

class VaBankCatalog
{
    /**
     * @return list<VaBankDefinition>
     */
    public function all(): array
    {
        return array_map(
            fn (array $row): VaBankDefinition => new VaBankDefinition(
                slug: $row['slug'],
                displayName: $row['display_name'],
                midtransCode: $row['midtrans'] ?? null,
                ipaymuCode: $row['ipaymu'] ?? null,
            ),
            $this->definitions(),
        );
    }

    public function find(string $slug): ?VaBankDefinition
    {
        foreach ($this->all() as $bank) {
            if ($bank->slug === $slug) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * @return list<array{slug: string, display_name: string, midtrans?: string, ipaymu?: string}>
     */
    private function definitions(): array
    {
        return [
            ['slug' => 'bca', 'display_name' => 'BCA Virtual Account', 'midtrans' => 'bca', 'ipaymu' => 'bca'],
            ['slug' => 'bni', 'display_name' => 'BNI Virtual Account', 'midtrans' => 'bni', 'ipaymu' => 'bni'],
            ['slug' => 'mandiri', 'display_name' => 'Mandiri Virtual Account', 'midtrans' => 'mandiri', 'ipaymu' => 'mandiri'],
            ['slug' => 'bri', 'display_name' => 'BRI Virtual Account', 'midtrans' => 'bri', 'ipaymu' => 'bri'],
            ['slug' => 'permata', 'display_name' => 'Permata Virtual Account', 'midtrans' => 'permata', 'ipaymu' => 'permata'],
            ['slug' => 'cimb', 'display_name' => 'CIMB Niaga Virtual Account', 'midtrans' => 'cimb', 'ipaymu' => 'cimb'],
            ['slug' => 'danamon', 'display_name' => 'Danamon Virtual Account', 'midtrans' => 'danamon', 'ipaymu' => 'danamon'],
            ['slug' => 'bsi', 'display_name' => 'BSI Virtual Account', 'midtrans' => 'bsi', 'ipaymu' => 'bsi'],
            ['slug' => 'seabank', 'display_name' => 'SeaBank Virtual Account', 'midtrans' => 'seabank'],
            ['slug' => 'saqu', 'display_name' => 'Bank Saqu Virtual Account', 'midtrans' => 'saqu'],
            ['slug' => 'artha_graha', 'display_name' => 'Bank Artha Graha Virtual Account', 'ipaymu' => 'bag'],
            ['slug' => 'muamalat', 'display_name' => 'Bank Muamalat Virtual Account', 'ipaymu' => 'bmi'],
            ['slug' => 'bpdbali', 'display_name' => 'BPD Bali Virtual Account', 'ipaymu' => 'bpdbali'],
        ];
    }
}
