# Stale-Item Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Surface listed items that have been up ≥ 14 days with no buyer contact in the last 7 days, via a filter toggle on `/items` and a per-card "Stale" badge.

**Architecture:** Definition lives on the `Item` model as constants + `scopeStale()` query scope + `isStale()` predicate. Controller computes per-item flag and total count, frontend renders toggle and badge. No new tables, no new routes — `/items` accepts `?stale=1`.

**Tech Stack:** Laravel 13, Eloquent, Inertia v3, React 19, Tailwind v4, Pest 4.

**Spec:** `docs/superpowers/specs/2026-05-06-stale-item-filter-design.md`

---

## File Map

- **Modify** `app/Models/Item.php` — add staleness constants, `scopeStale()`, `isStale()`.
- **Modify** `database/factories/ItemFactory.php` — add `stale()` state.
- **Modify** `app/Http/Controllers/ItemController.php` — apply filter, compute `is_stale` per item, expose `stale_count` and `filters`.
- **Create** `tests/Feature/ItemIsStaleTest.php` — predicate matrix.
- **Create** `tests/Feature/ItemStaleScopeTest.php` — scope matrix.
- **Create** `tests/Feature/ItemIndexFilterTest.php` — controller filter behaviour.
- **Modify** `resources/js/pages/items/index.tsx` — toggle + count + empty state.
- **Modify** `resources/js/components/item-card.tsx` — stale badge.

---

## Task 1: Add `stale()` factory state

The other tasks need it to set up listed-but-old items.

**Files:**
- Modify: `database/factories/ItemFactory.php`

- [ ] **Step 1: Add the state method**

Add this method to `ItemFactory` after `listed()`:

```php
public function stale(): self
{
    return $this->state([
        'status' => ItemStatus::Listed->value,
        'kijiji_url' => 'https://www.kijiji.ca/v-'.fake()->uuid(),
        'listed_at' => now()->subDays(15),
    ]);
}
```

- [ ] **Step 2: Run pint to format**

Run: `vendor/bin/pint --dirty --format agent`
Expected: 1 file fixed (or already clean).

- [ ] **Step 3: Commit**

```bash
git add database/factories/ItemFactory.php
git commit -m "Add stale state to ItemFactory"
```

---

## Task 2: Add `Item::isStale()` predicate (TDD)

Predicate is checked per loaded item to render the badge. Returns true only for listed items past the listed-at cutoff with no recent inquiry contact.

**Files:**
- Modify: `app/Models/Item.php`
- Create: `tests/Feature/ItemIsStaleTest.php`

- [ ] **Step 1: Create the failing test file**

Create `tests/Feature/ItemIsStaleTest.php`:

```php
<?php

use App\Enums\ItemStatus;
use App\Models\Inquiry;
use App\Models\Item;

it('flags a listed item with no inquiries past the cutoff as stale', function () {
    $item = Item::factory()->stale()->create();

    expect($item->isStale())->toBeTrue();
});

it('does not flag a recently listed item as stale', function () {
    $item = Item::factory()->listed()->create([
        'listed_at' => now()->subDays(13),
    ]);

    expect($item->isStale())->toBeFalse();
});

it('does not flag a listed item with a recent inquiry as stale', function () {
    $item = Item::factory()->stale()->create();
    Inquiry::factory()->create([
        'item_id' => $item->id,
        'last_contact_at' => now()->subDays(3),
    ]);

    expect($item->fresh()->isStale())->toBeFalse();
});

it('flags a listed item whose inquiries all went cold as stale', function () {
    $item = Item::factory()->stale()->create();
    Inquiry::factory()->create([
        'item_id' => $item->id,
        'last_contact_at' => now()->subDays(8),
    ]);

    expect($item->fresh()->isStale())->toBeTrue();
});

it('does not flag non-listed items as stale even if dates qualify', function (ItemStatus $status) {
    $item = Item::factory()->create([
        'status' => $status->value,
        'listed_at' => now()->subDays(30),
    ]);

    expect($item->isStale())->toBeFalse();
})->with([
    ItemStatus::Draft,
    ItemStatus::Ready,
    ItemStatus::Reserved,
    ItemStatus::Sold,
    ItemStatus::Withdrawn,
]);
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `php artisan test --compact --filter=ItemIsStaleTest`
Expected: All 5 tests fail with "Method isStale does not exist" or similar.

- [ ] **Step 3: Add constants and `isStale()` to the Item model**

In `app/Models/Item.php`, add the constants right after the `$casts` array:

```php
public const STALE_DAYS_LISTED = 14;

public const STALE_INQUIRY_WINDOW_DAYS = 7;
```

Add this method to the model (place it after the `pickups()` relation):

```php
public function isStale(): bool
{
    if ($this->status !== ItemStatus::Listed) {
        return false;
    }

    if ($this->listed_at === null) {
        return false;
    }

    if ($this->listed_at->isAfter(now()->subDays(self::STALE_DAYS_LISTED))) {
        return false;
    }

    $cutoff = now()->subDays(self::STALE_INQUIRY_WINDOW_DAYS);

    if ($this->relationLoaded('inquiries')) {
        return ! $this->inquiries->contains(
            fn ($inquiry) => $inquiry->last_contact_at !== null
                && $inquiry->last_contact_at->greaterThanOrEqualTo($cutoff)
        );
    }

    return ! $this->inquiries()->where('last_contact_at', '>=', $cutoff)->exists();
}
```

- [ ] **Step 4: Run the tests to confirm they pass**

Run: `php artisan test --compact --filter=ItemIsStaleTest`
Expected: 5 passed.

- [ ] **Step 5: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Models/Item.php tests/Feature/ItemIsStaleTest.php
git commit -m "Add Item::isStale() predicate with staleness constants"
```

---

## Task 3: Add `Item::scopeStale()` query scope (TDD)

The scope filters the index query in a single SQL statement. Used by the controller for both the filter toggle and the `stale_count`.

**Files:**
- Modify: `app/Models/Item.php`
- Create: `tests/Feature/ItemStaleScopeTest.php`

- [ ] **Step 1: Create the failing test file**

Create `tests/Feature/ItemStaleScopeTest.php`:

```php
<?php

use App\Enums\ItemStatus;
use App\Models\Inquiry;
use App\Models\Item;

it('returns listed items past the cutoff with no recent inquiries', function () {
    $stale = Item::factory()->stale()->create();
    $fresh = Item::factory()->listed()->create(['listed_at' => now()->subDays(5)]);

    $results = Item::stale()->get();

    expect($results->pluck('id')->all())->toBe([$stale->id]);
    expect($results->pluck('id'))->not->toContain($fresh->id);
});

it('excludes listed items with a recent inquiry', function () {
    $cold = Item::factory()->stale()->create();
    $warm = Item::factory()->stale()->create();

    Inquiry::factory()->create([
        'item_id' => $warm->id,
        'last_contact_at' => now()->subDays(2),
    ]);

    $ids = Item::stale()->pluck('id')->all();

    expect($ids)->toContain($cold->id);
    expect($ids)->not->toContain($warm->id);
});

it('includes listed items whose inquiries all went cold', function () {
    $item = Item::factory()->stale()->create();

    Inquiry::factory()->create([
        'item_id' => $item->id,
        'last_contact_at' => now()->subDays(10),
    ]);

    expect(Item::stale()->pluck('id')->all())->toContain($item->id);
});

it('excludes non-listed items even if dates qualify', function (ItemStatus $status) {
    Item::factory()->create([
        'status' => $status->value,
        'listed_at' => now()->subDays(30),
    ]);

    expect(Item::stale()->count())->toBe(0);
})->with([
    ItemStatus::Draft,
    ItemStatus::Ready,
    ItemStatus::Reserved,
    ItemStatus::Sold,
    ItemStatus::Withdrawn,
]);
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `php artisan test --compact --filter=ItemStaleScopeTest`
Expected: All tests fail with "Call to undefined scope" or similar.

- [ ] **Step 3: Add `scopeStale()` to the Item model**

Add the import at the top of `app/Models/Item.php` if not already present:

```php
use Illuminate\Database\Eloquent\Builder;
```

Add this method to the model (place it after `isStale()`):

```php
public function scopeStale(Builder $query): Builder
{
    return $query
        ->where('status', ItemStatus::Listed->value)
        ->where('listed_at', '<=', now()->subDays(self::STALE_DAYS_LISTED))
        ->whereDoesntHave('inquiries', function (Builder $q) {
            $q->where('last_contact_at', '>=', now()->subDays(self::STALE_INQUIRY_WINDOW_DAYS));
        });
}
```

- [ ] **Step 4: Run the tests to confirm they pass**

Run: `php artisan test --compact --filter=ItemStaleScopeTest`
Expected: All tests pass.

- [ ] **Step 5: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit**

```bash
git add app/Models/Item.php tests/Feature/ItemStaleScopeTest.php
git commit -m "Add Item::scopeStale() query scope"
```

---

## Task 4: Wire the filter into `ItemController::index` (TDD)

The controller needs to (a) apply `scopeStale()` when `?stale=1` is set, (b) compute `is_stale` per item for the badge, (c) expose `stale_count` (always, regardless of filter), and (d) echo the current filter state.

**Files:**
- Modify: `app/Http/Controllers/ItemController.php`
- Create: `tests/Feature/ItemIndexFilterTest.php`

- [ ] **Step 1: Create the failing test file**

Create `tests/Feature/ItemIndexFilterTest.php`:

```php
<?php

use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('returns all items with is_stale flag when filter is off', function () {
    $user = User::factory()->create();

    $stale = Item::factory()->stale()->create(['user_id' => $user->id]);
    $fresh = Item::factory()->listed()->create([
        'user_id' => $user->id,
        'listed_at' => now()->subDays(2),
    ]);

    actingAs($user)
        ->get('/items')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/index')
            ->has('items', 2)
            ->where('filters.stale', false)
            ->where('stale_count', 1)
            ->has('items.0.is_stale')
            ->has('items.1.is_stale')
        );
});

it('returns only stale items when ?stale=1 is set', function () {
    $user = User::factory()->create();

    $stale = Item::factory()->stale()->create(['user_id' => $user->id]);
    Item::factory()->listed()->create([
        'user_id' => $user->id,
        'listed_at' => now()->subDays(2),
    ]);

    actingAs($user)
        ->get('/items?stale=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/index')
            ->has('items', 1)
            ->where('items.0.id', $stale->id)
            ->where('items.0.is_stale', true)
            ->where('filters.stale', true)
            ->where('stale_count', 1)
        );
});

it('stale_count counts only the auth user\'s items', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Item::factory()->stale()->create(['user_id' => $user->id]);
    Item::factory()->stale()->count(3)->create(['user_id' => $other->id]);

    actingAs($user)
        ->get('/items')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('stale_count', 1));
});
```

- [ ] **Step 2: Run the tests to confirm they fail**

Run: `php artisan test --compact --filter=ItemIndexFilterTest`
Expected: Tests fail because `filters`, `stale_count`, and `is_stale` are not yet in the response.

- [ ] **Step 3: Add `Request` import**

In `app/Http/Controllers/ItemController.php`, add this `use` statement (alphabetical order — after `App\Models\Item`):

```php
use Illuminate\Http\Request;
```

- [ ] **Step 4: Update `ItemController::index`**

Replace the `index` method in `app/Http/Controllers/ItemController.php` with:

```php
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
```

- [ ] **Step 5: Run the new tests to confirm they pass**

Run: `php artisan test --compact --filter=ItemIndexFilterTest`
Expected: 3 passed.

- [ ] **Step 6: Run the existing items index test to make sure it still passes**

Run: `php artisan test --compact --filter=ItemCrudTest`
Expected: All tests still pass (the existing "shows only the authenticated user's items on the index" test still asserts `has('items', 3)` which continues to work).

- [ ] **Step 7: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ItemController.php tests/Feature/ItemIndexFilterTest.php
git commit -m "Wire stale filter and is_stale flag into items index"
```

---

## Task 5: Render the stale badge on `ItemCard`

Add the `Stale` badge next to the status pill when `item.is_stale === true`. Use `bg-yellow-200 text-yellow-900` (amber is already used by the `reserved` status pill — don't reuse it).

**Files:**
- Modify: `resources/js/components/item-card.tsx`

- [ ] **Step 1: Update the Item type and JSX**

Replace the contents of `resources/js/components/item-card.tsx` with:

```tsx
import { Link } from '@inertiajs/react';
import { StatusPill } from '@/components/status-pill';

type Photo = { id: number; thumbnail_path: string | null; path: string };
type Item = {
    id: number;
    title: string;
    status: 'draft' | 'ready' | 'listed' | 'reserved' | 'sold' | 'withdrawn';
    asking_price_cents: number;
    is_stale?: boolean;
    photos?: Photo[];
};

export function ItemCard({ item }: { item: Item }) {
    const primary = item.photos?.[0];
    const thumb = primary?.thumbnail_path ?? primary?.path;

    return (
        <Link href={`/items/${item.id}`} className="block border rounded-lg overflow-hidden hover:shadow">
            <div className="aspect-square bg-zinc-100">
                {thumb && <img src={`/storage/${thumb}`} alt="" className="w-full h-full object-cover" />}
            </div>
            <div className="p-3 space-y-1">
                <div className="flex items-start justify-between gap-2">
                    <p className="font-medium leading-tight line-clamp-2">{item.title}</p>
                    <div className="flex flex-col items-end gap-1 shrink-0">
                        <StatusPill status={item.status} />
                        {item.is_stale && (
                            <span className="inline-block text-xs font-medium px-2 py-0.5 rounded-full bg-yellow-200 text-yellow-900">
                                Stale
                            </span>
                        )}
                    </div>
                </div>
                <p className="text-sm text-zinc-600">${(item.asking_price_cents / 100).toFixed(2)}</p>
            </div>
        </Link>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/item-card.tsx
git commit -m "Render Stale badge on item cards"
```

---

## Task 6: Add the filter toggle to `items/index`

Toggle changes `?stale=1` via `router.get()` with `preserveScroll` and `preserveState`. The label always shows the total stale count from the prop. Empty state when filter is on and no items.

**Files:**
- Modify: `resources/js/pages/items/index.tsx`

- [ ] **Step 1: Replace the index page**

Replace the contents of `resources/js/pages/items/index.tsx` with:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import { ItemCard } from '@/components/item-card';

type Photo = { id: number; thumbnail_path: string | null; path: string };
type Item = {
    id: number;
    title: string;
    status: 'draft' | 'ready' | 'listed' | 'reserved' | 'sold' | 'withdrawn';
    asking_price_cents: number;
    is_stale?: boolean;
    photos?: Photo[];
};

type Props = {
    items: Item[];
    filters: { stale: boolean };
    stale_count: number;
};

export default function ItemsIndex({ items, filters, stale_count }: Props) {
    const toggleStale = (checked: boolean) => {
        router.get(
            '/items',
            checked ? { stale: 1 } : {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Items" />
            <div className="p-6 space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Items</h1>
                    <Link href="/items/create" className="bg-black text-white px-3 py-1.5 rounded text-sm">
                        New item
                    </Link>
                </div>
                <label className="inline-flex items-center gap-2 text-sm text-zinc-700">
                    <input
                        type="checkbox"
                        checked={filters.stale}
                        onChange={(e) => toggleStale(e.target.checked)}
                        className="rounded"
                    />
                    <span>Show only stale items ({stale_count} stale)</span>
                </label>
                {items.length === 0 && filters.stale ? (
                    <p className="text-sm text-zinc-500 py-12 text-center">Nothing stale right now.</p>
                ) : (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        {items.map((item) => (
                            <ItemCard key={item.id} item={item} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ItemsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Items',
            href: '/items',
        },
    ],
};
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/pages/items/index.tsx
git commit -m "Add stale filter toggle and empty state to items index"
```

---

## Task 7: Manual smoke check + final test run

Verify the feature in a browser and run the full affected test suite.

- [ ] **Step 1: Run the full affected test suite**

Run: `php artisan test --compact --filter='ItemIsStale|ItemStaleScope|ItemIndexFilter|ItemCrud'`
Expected: All tests pass.

- [ ] **Step 2: Build the frontend**

Run: `npm run build`
Expected: Build completes with no errors.

- [ ] **Step 3: Manual browser smoke**

Ask the user to:
1. Run the dev server (`composer run dev` or whatever they use).
2. Visit `/items`. Confirm the toggle is visible with `(N stale)` count.
3. Create or seed a listed item with `listed_at = now->subDays(15)` and no inquiries — confirm "Stale" badge appears on its card.
4. Toggle the filter on — confirm only stale items remain.
5. Toggle off — full list returns.
6. Add an inquiry on a stale item with `last_contact_at = now()` and reload — confirm badge disappears.

- [ ] **Step 4: No commit needed** — feature is complete after smoke passes.

---

## Out of Scope (do not implement)

- Status filter (`?status=listed`), location filter — separate work.
- Dashboard widget showing stale count.
- Configurable thresholds via config/user settings.
- Automated price-drop suggestions.
