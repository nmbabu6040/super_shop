<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case PurchaseReceipt = 'purchase_receipt';
    case PurchaseReturn = 'purchase_return';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case AdjustmentGain = 'adjustment_gain';
    case AdjustmentLoss = 'adjustment_loss';
    case CountGain = 'count_gain';
    case CountLoss = 'count_loss';
    case Damage = 'damage';
    case Expired = 'expired';

    /**
     * +1 for movements that add stock, -1 for movements that remove stock.
     */
    public function direction(): int
    {
        return match ($this) {
            self::Opening,
            self::PurchaseReceipt,
            self::SaleReturn,
            self::TransferIn,
            self::AdjustmentGain,
            self::CountGain => 1,
            default => -1,
        };
    }

    /**
     * Expired stock must never be sold or sent to another warehouse. Writing it off is still allowed.
     */
    public function blocksExpiredStock(): bool
    {
        return in_array($this, [self::Sale, self::TransferOut], true);
    }

    public function label(): string
    {
        return __('inventory.movement_types.' . $this->value);
    }
}
