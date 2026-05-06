<?php

namespace App\Http\Controllers;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;
use App\Services\ListingDraftRenderer;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $stale = $request->boolean('stale');

        $query = $user->items()
            ->with([
                'photos' => fn ($q) => $q->where('is_primary', true),
                'inquiries',
            ])
            ->latest();

        if ($stale) {
            $query->stale();
        }

        $items = $query->get()->each(function (Item $item) {
            $item->setAttribute('is_stale', $item->isStale());
            $item->unsetRelation('inquiries');
        });

        return inertia('items/index', [
            'items' => $items,
            'filters' => ['stale' => $stale],
            'stale_count' => $user->items()->stale()->count(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('items/create', [
            'conditions' => collect(ItemCondition::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
                ->all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreItemRequest $request)
    {
        $item = $request->user()->items()->create([
            ...$request->validated(),
            'status' => ItemStatus::Draft,
        ]);

        return redirect("/items/{$item->id}/edit");
    }

    /**
     * Display the specified resource.
     */
    public function show(Item $item, ListingDraftRenderer $renderer)
    {
        abort_unless($item->user_id === auth()->id(), 403);

        $item->load(['photos', 'user.settings', 'inquiries.buyer']);

        $listingDraft = $renderer->render($item);
        $snippets = $item->user?->settings?->snippets ?? [];
        $replyTemplates = $snippets['reply_templates'] ?? [];

        $inquiries = $item->inquiries;
        $buyers = auth()->user()->buyers()->orderBy('display_name')->get(['id', 'display_name']);

        $item->unsetRelation('user');
        $item->unsetRelation('inquiries');

        return inertia('items/show', [
            'item' => $item,
            'listing_draft' => $listingDraft,
            'inquiries' => $inquiries,
            'buyers' => $buyers,
            'reply_templates' => $replyTemplates,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        abort_unless($item->user_id === auth()->id(), 403);

        return inertia('items/edit', [
            'item' => $item->load('photos'),
            'conditions' => collect(ItemCondition::cases())
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])
                ->all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateItemRequest $request, Item $item)
    {
        abort_unless($item->user_id === auth()->id(), 403);

        $item->update($request->validated());

        return redirect("/items/{$item->id}/edit");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        abort_unless($item->user_id === auth()->id(), 403);

        $item->delete();

        return redirect('/items');
    }
}
