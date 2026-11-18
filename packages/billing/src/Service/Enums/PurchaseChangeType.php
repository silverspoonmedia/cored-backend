<?php

namespace App\Domain\Service\Enums;

enum PurchaseChangeType: string
{
    case New = 'new';
    case Renew = 'renew';
    case Upgrade = 'upgrade';
    case Downgrade = 'downgrade';
    case AddonUnit = 'addon_unit';
    case AddonRenewal = 'addon_renewal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isMainSwitch(): bool
    {
        return in_array($this, [self::Upgrade, self::Downgrade], true);
    }
}
