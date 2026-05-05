<?php

namespace App\Actions;

use App\Enums\ItemStatus;
use App\Enums\PickupStatus;
use App\Models\Pickup;
use App\Services\ItemStatusManager;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelPickup
{
    public function __construct(private ItemStatusManager $statusManager) {}

    public function handle(Pickup $pickup, PickupStatus $to): Pickup
    {
        if (! in_array($to, [PickupStatus::Cancelled, PickupStatus::NoShow], true)) {
            throw new InvalidArgumentException('CancelPickup only handles cancelled or no_show');
        }

        if ($pickup->status !== PickupStatus::Scheduled) {
            throw new InvalidArgumentException(
                "Cannot cancel pickup {$pickup->id} from status {$pickup->status->value}"
            );
        }

        return DB::transaction(function () use ($pickup, $to) {
            $pickup->forceFill([
                'status' => $to->value,
                'cancelled_at' => now(),
            ])->save();

            foreach ($pickup->items()->get() as $item) {
                if ($item->status === ItemStatus::Reserved) {
                    $this->statusManager->transition($item, ItemStatus::Listed);
                }
            }

            return $pickup->load('items');
        });
    }
}
