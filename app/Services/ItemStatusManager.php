<?php

namespace App\Services;

use App\Enums\ItemStatus;
use App\Models\Item;
use InvalidArgumentException;

class ItemStatusManager
{
    /**
     * Move an item to a new status, validating the transition and
     * applying any timestamp / field side-effects.
     *
     * @param  array<string, mixed>  $context
     */
    public function transition(Item $item, ItemStatus $to, array $context = []): void
    {
        $from = $item->status;

        if (! $from->canTransitionTo($to)) {
            throw new InvalidArgumentException(
                "Cannot transition item {$item->id} from {$from->value} to {$to->value}"
            );
        }

        if ($to === ItemStatus::Listed && empty($context['kijiji_url'])) {
            throw new InvalidArgumentException('kijiji_url is required when transitioning to listed');
        }

        $item->status = $to;

        match ($to) {
            ItemStatus::Listed => $item->forceFill([
                'kijiji_url' => $context['kijiji_url'],
                'listed_at' => now(),
            ]),
            ItemStatus::Sold => $item->forceFill(['sold_at' => now()]),
            ItemStatus::Withdrawn => $item->forceFill(['withdrawn_at' => now()]),
            default => null,
        };

        $item->save();
    }
}
