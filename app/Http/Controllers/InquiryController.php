<?php

namespace App\Http\Controllers;

use App\Enums\InquiryStatus;
use App\Http\Requests\StoreInquiryRequest;
use App\Http\Requests\UpdateInquiryRequest;
use App\Models\Inquiry;
use App\Models\Item;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();

        $buyerId = $data['buyer_id'] ?? null;

        if (! $buyerId) {
            $buyer = $request->user()->buyers()->create($data['new_buyer']);
            $buyerId = $buyer->id;
        }

        Inquiry::create([
            'item_id' => $item->id,
            'buyer_id' => $buyerId,
            'message_excerpt' => $data['message_excerpt'] ?? null,
            'offered_price_cents' => $data['offered_price_cents'] ?? null,
            'status' => InquiryStatus::New->value,
            'received_at' => now(),
            'last_contact_at' => now(),
        ]);

        Toast::success('Inquiry logged.');

        return back();
    }

    public function update(UpdateInquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $data = $request->validated();
        $changes = ['last_contact_at' => now()];

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $changes['status'] = $data['status'];
        }

        if (array_key_exists('offered_price_cents', $data)) {
            $changes['offered_price_cents'] = $data['offered_price_cents'];
        }

        if (! empty($data['negotiation_note'])) {
            $log = $inquiry->negotiation_log ?? [];
            $log[] = [
                'note' => $data['negotiation_note'],
                'at' => now()->toIso8601String(),
            ];
            $changes['negotiation_log'] = $log;
        }

        $inquiry->update($changes);

        Toast::success('Inquiry updated.');

        return back();
    }
}
