<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBuyerRequest;
use App\Http\Requests\UpdateBuyerRequest;
use App\Models\Buyer;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BuyerController extends Controller
{
    public function index(): Response
    {
        $buyers = auth()->user()->buyers()
            ->withCount('inquiries')
            ->orderBy('display_name')
            ->get();

        return Inertia::render('buyers/index', [
            'buyers' => $buyers,
        ]);
    }

    public function store(StoreBuyerRequest $request): RedirectResponse
    {
        $buyer = $request->user()->buyers()->create($request->validated());

        Toast::success('Buyer added.');

        return redirect("/buyers/{$buyer->id}");
    }

    public function show(Buyer $buyer): Response
    {
        abort_unless($buyer->user_id === auth()->id(), 403);

        $buyer->load(['inquiries.item']);

        return Inertia::render('buyers/show', [
            'buyer' => $buyer,
            'inquiries' => $buyer->inquiries,
        ]);
    }

    public function update(UpdateBuyerRequest $request, Buyer $buyer): RedirectResponse
    {
        $buyer->update($request->validated());

        Toast::success('Buyer updated.');

        return back();
    }
}
