<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $items = $request->user()->items()->with(['photos' => fn ($q) => $q->where('is_primary', true)]);

        $counts = collect(ItemStatus::cases())
            ->mapWithKeys(fn ($s) => [$s->value => 0])
            ->merge(
                $request->user()->items()
                    ->selectRaw('status, COUNT(*) as c')
                    ->groupBy('status')
                    ->pluck('c', 'status')
            )
            ->all();

        return inertia('dashboard', [
            'counts' => $counts,
            'recentItems' => $items->latest()->limit(8)->get(),
        ]);
    }
}
