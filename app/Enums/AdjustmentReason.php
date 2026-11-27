<?php

declare(strict_types=1);

namespace App\Enums;

enum AdjustmentReason: string
{
    case OpeningStock = 'opening_stock';
    case Damaged = 'damaged';
    case Expired = 'expired';
    case Lost = 'lost';
    case Found = 'found';
    case Correction = 'correction';
    case Sample = 'sample';
    case Other = 'other';

    public function allowsGain(): bool
    {
        return in_array($this, [self::OpeningStock, self::Found, self::Correction, self::Other], true);
    }

    public function allowsLoss(): bool
    {
        return ! in_array($this, [self::OpeningStock, self::Found], true);
    }

    public function movementType(bool $isGain): StockMovementType
    {
        if ($isGain) {
            return $this === self::OpeningStock ? StockMovementType::Opening : StockMovementType::AdjustmentGain;
        }

        return match ($this) {
            self::Damaged => StockMovementType::Damage,
            self::Expired => StockMovementType::Expired,
            default => StockMovementType::AdjustmentLoss,
        };
    }

    /**
     * The account opposite to Inventory: opening stock is funded by equity, every other
     * adjustment is a gain or loss in the profit and loss account.
     */
    public function contraAccount(): SystemAccount
    {
        return $this === self::OpeningStock ? SystemAccount::OpeningBalanceEquity : SystemAccount::InventoryAdjustments;
    }

    public function label(): string
    {
        return __('inventory.reasons.' . $this->value);
    }
}
