<?php

namespace App\Actions;

use App\Enums\ItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PickupStatus;
use App\Models\Pickup;
use App\Services\ItemStatusManager;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompletePickup
{
    public function __construct(private ItemStatusManager $statusManager) {}

    public function handle(Pickup $pickup, PaymentMethod $paymentMethod): Pickup
    {
        if ($pickup->status !== PickupStatus::Scheduled) {
            throw new InvalidArgumentException(
                "Cannot complete pickup {$pickup->id} from status {$pickup->status->value}"
            );
        }

        return DB::transaction(function () use ($pickup, $paymentMethod) {
            $pickup->forceFill([
                'status' => PickupStatus::Completed->value,
                'payment_method' => $paymentMethod->value,
                'payment_status' => PaymentStatus::Received->value,
                'completed_at' => now(),
            ])->save();

            foreach ($pickup->items()->get() as $item) {
                $this->statusManager->transition($item, ItemStatus::Sold);
            }

            return $pickup->load('items');
        });
    }
}
