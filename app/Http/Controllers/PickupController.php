<?php

namespace App\Http\Controllers;

use App\Actions\CancelPickup;
use App\Actions\CompletePickup;
use App\Actions\SchedulePickup;
use App\Enums\PaymentMethod;
use App\Enums\PickupStatus;
use App\Http\Requests\StorePickupRequest;
use App\Http\Requests\UpdatePickupRequest;
use App\Models\Buyer;
use App\Models\Pickup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PickupController extends Controller
{
    public function index(): Response
    {
        $pickups = Pickup::query()
            ->whereHas('buyer', fn ($q) => $q->where('user_id', auth()->id()))
            ->where('status', PickupStatus::Scheduled->value)
            ->with(['buyer', 'items'])
            ->latest()
            ->get();

        return Inertia::render('pickups/index', [
            'pickups' => $pickups,
        ]);
    }

    public function show(Pickup $pickup): Response
    {
        abort_unless($pickup->buyer->user_id === auth()->id(), 403);

        $pickup->load(['buyer', 'items']);

        return Inertia::render('pickups/show', [
            'pickup' => $pickup,
            'payment_methods' => collect(PaymentMethod::cases())
                ->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])
                ->all(),
        ]);
    }

    public function store(StorePickupRequest $request, SchedulePickup $action): RedirectResponse
    {
        $data = $request->validated();
        $buyer = Buyer::query()->where('user_id', $request->user()->id)->findOrFail($data['buyer_id']);

        try {
            $pickup = $action->handle(
                buyer: $buyer,
                items: $data['items'],
                notes: $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }

        return redirect("/pickups/{$pickup->id}");
    }

    public function update(UpdatePickupRequest $request, Pickup $pickup): RedirectResponse
    {
        $pickup->update($request->validated());

        return back();
    }

    public function complete(Request $request, Pickup $pickup, CompletePickup $action): RedirectResponse
    {
        abort_unless($pickup->buyer->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ]);

        try {
            $action->handle($pickup, PaymentMethod::from($validated['payment_method']));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['payment_method' => $e->getMessage()]);
        }

        return back();
    }

    public function cancel(Request $request, Pickup $pickup, CancelPickup $action): RedirectResponse
    {
        abort_unless($pickup->buyer->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'to' => ['required', Rule::in(['cancelled', 'no_show'])],
        ]);

        try {
            $action->handle($pickup, PickupStatus::from($validated['to']));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['to' => $e->getMessage()]);
        }

        return back();
    }
}
