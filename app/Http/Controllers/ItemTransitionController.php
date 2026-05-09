<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Services\ItemStatusManager;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ItemTransitionController extends Controller
{
    public function __invoke(Request $request, Item $item, ItemStatusManager $manager): RedirectResponse
    {
        abort_unless($item->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'to' => ['required', Rule::enum(ItemStatus::class)],
            'kijiji_url' => ['nullable', 'url', 'max:512'],
        ]);

        $to = ItemStatus::from($validated['to']);

        try {
            $manager->transition($item, $to, [
                'kijiji_url' => $validated['kijiji_url'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            $field = str_contains($e->getMessage(), 'kijiji_url') ? 'kijiji_url' : 'to';
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        Toast::success(match ($to) {
            ItemStatus::Draft => 'Item moved to draft.',
            ItemStatus::Ready => 'Item marked as ready.',
            ItemStatus::Listed => 'Item marked as listed.',
            ItemStatus::Reserved => 'Item marked as reserved.',
            ItemStatus::Sold => 'Item marked as sold.',
            ItemStatus::Withdrawn => 'Item withdrawn.',
        });

        return back();
    }
}
