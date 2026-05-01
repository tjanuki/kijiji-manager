<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SnippetsUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SnippetsController extends Controller
{
    public function edit(Request $request): Response
    {
        $stored = $request->user()->settings?->snippets ?? [];

        return Inertia::render('settings/snippets', [
            'snippets' => [
                'pickup' => $stored['pickup'] ?? '',
                'payment' => $stored['payment'] ?? '',
            ],
        ]);
    }

    public function update(SnippetsUpdateRequest $request): RedirectResponse
    {
        $request->user()->settings()->updateOrCreate(
            [],
            ['snippets' => $request->validated()],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Snippets updated.')]);

        return to_route('snippets.edit');
    }
}
