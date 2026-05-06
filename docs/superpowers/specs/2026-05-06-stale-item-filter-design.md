# Stale-Item Filter — Design

Phase 5 polish item from the Kijiji Manager implementation plan: surface listed items that have been up too long with no recent buyer activity, so the user can target them for re-listing or price drops.

## 1. Definition

An item is **stale** when all of the following hold:

- `status === ItemStatus::Listed` (only listed items can be stale; `reserved` is about to sell, `draft`/`ready`/`sold`/`withdrawn` are not eligible)
- `listed_at <= now()->subDays(14)`
- No inquiry on this item with `last_contact_at >= now()->subDays(7)` — covers both "never had an inquiry" and "had inquiries but they all went cold"

Thresholds live as constants on the `Item` model:

- `Item::STALE_DAYS_LISTED = 14`
- `Item::STALE_INQUIRY_WINDOW_DAYS = 7`

Hardcoded by intent. No config knob, no user setting. If the thresholds need to change, they change in code.

## 2. Backend

### Model — `app/Models/Item.php`

- `public const STALE_DAYS_LISTED = 14;`
- `public const STALE_INQUIRY_WINDOW_DAYS = 7;`
- `public function scopeStale(Builder $query): Builder` — single query that filters by status, `listed_at` cutoff, and `whereDoesntHave('inquiries', ...)` for the recent-contact window.
- `public function isStale(): bool` — same predicate evaluated in PHP on a loaded model. Used to set the per-item flag returned to the frontend. Relies on inquiries already being loaded (or queries lazily for that one item).

### Controller — `app/Http/Controllers/ItemController.php` (`index`)

- Accept `?stale=1` query param (string `"1"` or absent — keep it simple).
- Base query: `auth()->user()->items()->with(['photos' => …, 'inquiries'])->latest()`.
- When `stale=1`, chain `->stale()`.
- Compute `is_stale` per item after eager-load (via `$item->isStale()`) and append it to the response payload.
- Also pass:
  - `filters: { stale: bool }` — current filter state for the frontend
  - `stale_count: int` — total stale items regardless of current filter (so the toggle label shows "(N stale)" even when the filter is off)

### Eager loading

`with('inquiries')` so `isStale()` doesn't N+1. We only need `last_contact_at`; we can scope the relation load to that column for efficiency, but plain eager-load is fine at single-user scale.

## 3. Frontend

### `resources/js/pages/items/index.tsx`

- New filter row above the grid:
  - Checkbox / toggle: "Show only stale items"
  - Label suffix: `(N stale)` using `stale_count` from props
- Toggling calls `router.get('/items', { stale: checked ? 1 : undefined }, { preserveScroll: true, preserveState: true })`.
- Initial checked state read from `filters.stale` prop.
- When filter is on and `items.length === 0`, render a small "Nothing stale right now" empty state instead of an empty grid.

### `resources/js/components/item-card.tsx`

- Accept `is_stale?: boolean` on the item prop type.
- When true, render a small "Stale" badge next to the existing status pill. Amber/yellow tint (Tailwind: `bg-amber-100 text-amber-800` or similar) to read as "needs attention" without alarming.

### `resources/js/components/status-pill.tsx`

- No changes. The stale badge is a separate component or inline element inside the card — keeping the status pill responsibility focused on the state machine value.

## 4. Tests (Pest)

### Feature

- **`ItemIndexFilterTest`**
  - GET `/items` returns all of the user's items; each item has `is_stale` flag.
  - GET `/items?stale=1` returns only stale items.
  - `stale_count` prop reflects the total stale items regardless of filter.
  - Items belonging to other users are never returned.
- **`ItemStaleScopeTest`**
  - Listed > 14 days, no inquiries → stale.
  - Listed > 14 days, last inquiry contact > 7 days ago → stale.
  - Listed > 14 days, last inquiry contact within 7 days → NOT stale.
  - Listed < 14 days, no inquiries → NOT stale.
  - Status `draft`, `ready`, `reserved`, `sold`, `withdrawn` → NOT stale even if dates would qualify.

### Unit

- **`ItemIsStaleTest`** — same matrix as the scope test, exercising `Item::isStale()` against in-memory state.

### Factory

- `ItemFactory::stale()` state — sets `status=listed`, `listed_at = now()->subDays(15)`, no inquiries. Keeps tests readable.

## 5. Out of Scope

Deliberately not included in this spec:

- Status filter and location-in-house filter (mentioned in plan Phase 1 but never built — separate work).
- Dashboard widget showing stale count.
- Automated price-drop suggestions or notifications.
- Configurable thresholds (no config or user setting).
- Bulk actions on stale items.

These can be added later if the use case justifies them. For a one-time moving sale, the toggle + badge is enough.
