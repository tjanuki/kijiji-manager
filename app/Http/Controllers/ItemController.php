<?php

namespace App\Http\Controllers;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Item;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = auth()->user()->items()
            ->with(['photos' => fn ($q) => $q->where('is_primary', true)])
            ->latest()
            ->get();

        return inertia('items/index', [
            'items' => $items,
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
    public function show(Item $item)
    {
        abort_unless($item->user_id === auth()->id(), 403);

        $item->load('photos');

        return inertia('items/show', [
            'item' => $item,
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
