<?php

namespace App\Actions;

use App\Enums\ItemStatus;
use App\Enums\PickupStatus;
use App\Models\Buyer;
use App\Models\Pickup;
use App\Services\ItemStatusManager;
use Illuminate\Support\Facades\DB;

class SchedulePickup
{
    public function __construct(private ItemStatusManager $statusManager) {}

    /**
     * @param  list<array{item_id: int, agreed_price_cents: int}>  $items
     */
    public function handle(Buyer $buyer, array $items, ?string $notes = null): Pickup
    {
        return DB::transaction(function () use ($buyer, $items, $notes) {
            $pickup = Pickup::create([
                'buyer_id' => $buyer->id,
                'status' => PickupStatus::Scheduled->value,
                'notes' => $notes,
            ]);

            $attach = [];
            foreach ($items as $row) {
                $attach[$row['item_id']] = ['agreed_price_cents' => $row['agreed_price_cents']];
            }
            $pickup->items()->attach($attach);

            foreach ($pickup->items()->get() as $item) {
                $this->statusManager->transition($item, ItemStatus::Reserved);
            }

            return $pickup->load('items');
        });
    }
}
