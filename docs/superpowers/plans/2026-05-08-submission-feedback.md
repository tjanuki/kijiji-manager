# Submission Feedback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close four submission-feedback gaps across the app: success toasts on every mutation, inline validation errors on every form field, visible loading states, and unexpected-error toasts for 4xx/5xx/network failures.

**Architecture:** Server-side controllers flash toasts via a new `App\Support\Toast` static helper (which delegates to `Inertia::flash`); the existing `useFlashToast` hook surfaces them via Sonner. A new `useErrorToast` hook listens to Inertia v3's `httpException` and `networkError` events for non-422 failures. Forms render `<InputError>` next to every field for 422 (no toast). Loading states use `form.processing` for `useForm` flows and local `useState` for bare `router.*` calls.

**Tech Stack:** Laravel 13, Inertia.js v3 (Laravel + React), React 19, Sonner v2, Pest v4, Tailwind v4.

**Spec:** `docs/superpowers/specs/2026-05-08-submission-feedback-design.md` (commit `2263175`)

---

## Phase 0 — Foundation

### Task 1: Toast helper class + unit tests

**Files:**
- Create: `app/Support/Toast.php`
- Create: `tests/Unit/Support/ToastTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Support/ToastTest.php`:

```php
<?php

use App\Support\Toast;
use Inertia\Inertia;

it('flashes a success toast through Inertia::flash', function () {
    Inertia::shouldReceive('flash')
        ->once()
        ->with('toast', ['type' => 'success', 'message' => 'Saved.']);

    Toast::success('Saved.');
});

it('flashes an error toast through Inertia::flash', function () {
    Inertia::shouldReceive('flash')
        ->once()
        ->with('toast', ['type' => 'error', 'message' => 'Failed.']);

    Toast::error('Failed.');
});
```

- [ ] **Step 2: Run tests to confirm they fail**

Run: `php artisan test --compact --filter=ToastTest`
Expected: FAIL — `Class "App\Support\Toast" not found`.

- [ ] **Step 3: Create the Toast class**

Create `app/Support/Toast.php`:

```php
<?php

namespace App\Support;

use Inertia\Inertia;

class Toast
{
    public static function success(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    public static function error(string $message): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

Run: `php artisan test --compact --filter=ToastTest`
Expected: PASS — both tests green.

- [ ] **Step 5: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Support/Toast.php tests/Unit/Support/ToastTest.php
git commit -m "Add Toast helper for Inertia flash toasts"
```

---

### Task 2: useErrorToast hook + wire into Toaster

**Files:**
- Create: `resources/js/hooks/use-error-toast.ts`
- Modify: `resources/js/components/ui/sonner.tsx`

(No automated test — frontend behavior is covered by browser smoke in Task 17.)

- [ ] **Step 1: Create the hook**

Create `resources/js/hooks/use-error-toast.ts`:

```ts
import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export function useErrorToast(): void {
    useEffect(() => {
        const offHttp = router.on('httpException', (event) => {
            const status = (event as CustomEvent).detail?.response?.status;
            if (status === 422) return;

            const messageByStatus: Record<number, string> = {
                403: 'You don’t have permission to do that.',
                404: 'That item no longer exists.',
                419: 'Your session expired. Please refresh and try again.',
                429: 'Too many requests. Please wait a moment.',
            };
            toast.error(messageByStatus[status] ?? 'Something went wrong. Please try again.');
        });

        const offNetwork = router.on('networkError', () => {
            toast.error('Network error. Check your connection and try again.');
        });

        return () => {
            offHttp();
            offNetwork();
        };
    }, []);
}
```

- [ ] **Step 2: Wire it into the Toaster**

Modify `resources/js/components/ui/sonner.tsx`:

```tsx
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useErrorToast } from '@/hooks/use-error-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();
    useErrorToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
```

- [ ] **Step 3: Verify the build still compiles**

Run: `npm run build`
Expected: build completes without TS errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/hooks/use-error-toast.ts resources/js/components/ui/sonner.tsx
git commit -m "Add useErrorToast hook for non-422 Inertia failures"
```

---

## Phase 1 — Items

### Task 3: ItemController flash + extend ItemCrudTest

**Files:**
- Modify: `app/Http/Controllers/ItemController.php`
- Modify: `tests/Feature/ItemCrudTest.php`

- [ ] **Step 1: Add a failing flash assertion to the create test**

In `tests/Feature/ItemCrudTest.php`, locate the existing test at line 46 (`it('creates an item in draft status', ...`). After the existing `assertRedirect` line, add the flash assertion. The full updated test should read:

```php
it('creates an item in draft status', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->post('/items', [
        'title' => 'Old couch',
        'description' => 'Comfy',
        'condition' => 'good',
        'asking_price_cents' => 12500,
    ]);

    $item = Item::where('title', 'Old couch')->firstOrFail();
    expect($item->status->value)->toBe('draft');
    expect($item->user_id)->toBe($user->id);
    $response->assertRedirect("/items/{$item->id}/edit");

    actingAs($user)->get("/items/{$item->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.message', 'Item created.')
            ->etc()
        );
});
```

(`->etc()` allows other props to be present; we only assert what we care about.)

- [ ] **Step 2: Run the test to confirm it fails**

Run: `php artisan test --compact --filter="creates an item in draft status"`
Expected: FAIL — `flash.toast` is missing.

- [ ] **Step 3: Add Toast::success to ItemController::store**

In `app/Http/Controllers/ItemController.php`, modify `store()` (lines 61-69):

```php
public function store(StoreItemRequest $request)
{
    $item = $request->user()->items()->create([
        ...$request->validated(),
        'status' => ItemStatus::Draft,
    ]);

    Toast::success('Item created.');

    return redirect("/items/{$item->id}/edit");
}
```

Add `use App\Support\Toast;` to the imports at the top of the file.

- [ ] **Step 4: Run the test to confirm it passes**

Run: `php artisan test --compact --filter="creates an item in draft status"`
Expected: PASS.

- [ ] **Step 5: Repeat for `update` test**

In `tests/Feature/ItemCrudTest.php`, locate the test at line 94 (`it('updates an owned item', ...`). After the existing assertions, append:

```php
$response->assertRedirect("/items/{$item->id}/edit");

actingAs($user)->get("/items/{$item->id}/edit")
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Item updated.')
        ->etc()
    );
```

(Replace `$response` with whatever variable holds the patch response; if there is none, capture it as `$response = actingAs(...)->patch(...);`.)

Run: `php artisan test --compact --filter="updates an owned item"` — should FAIL.

Then add to `ItemController::update` (lines 117-124):

```php
public function update(UpdateItemRequest $request, Item $item)
{
    abort_unless($item->user_id === auth()->id(), 403);

    $item->update($request->validated());

    Toast::success('Item updated.');

    return redirect("/items/{$item->id}/edit");
}
```

Re-run — should PASS.

- [ ] **Step 6: Repeat for `destroy` test**

In `tests/Feature/ItemCrudTest.php`, locate the test at line 123 (`it('soft-deletes an owned item', ...`). After the existing assertions, capture the response and add:

```php
$response->assertRedirect('/items');

actingAs($user)->get('/items')
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Item deleted.')
        ->etc()
    );
```

Run — should FAIL.

Then add to `ItemController::destroy` (lines 129-136):

```php
public function destroy(Item $item)
{
    abort_unless($item->user_id === auth()->id(), 403);

    $item->delete();

    Toast::success('Item deleted.');

    return redirect('/items');
}
```

Re-run — should PASS.

- [ ] **Step 7: Run all ItemCrudTest tests to confirm no regressions**

Run: `php artisan test --compact --filter=ItemCrudTest`
Expected: all green.

- [ ] **Step 8: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/ItemController.php tests/Feature/ItemCrudTest.php
git commit -m "Flash toasts for item create/update/delete"
```

---

### Task 4: items/edit.tsx — InputError + Button/Spinner

**Files:**
- Modify: `resources/js/pages/items/edit.tsx`

- [ ] **Step 1: Replace the entire form body to add InputError + Button + Spinner**

Replace the contents of `resources/js/pages/items/edit.tsx` with:

```tsx
import { Head, useForm } from '@inertiajs/react';
import { PhotoUploader } from '@/components/photo-uploader';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';

type Condition = { value: string; label: string };
type Photo = { id: number; path: string; thumbnail_path: string | null; position: number; is_primary: boolean };
type Item = {
    id: number;
    title: string;
    description: string | null;
    category: string | null;
    condition: string;
    asking_price_cents: number;
    floor_price_cents: number | null;
    location_in_house: string | null;
    notes: string | null;
    status: string;
    photos: Photo[];
};

export default function ItemsEdit({ item, conditions }: { item: Item; conditions: Condition[] }) {
    const form = useForm({
        title: item.title,
        description: item.description ?? '',
        category: item.category ?? '',
        condition: item.condition,
        asking_price_cents: item.asking_price_cents,
        floor_price_cents: item.floor_price_cents ?? '',
        location_in_house: item.location_in_house ?? '',
        notes: item.notes ?? '',
    });

    return (
        <>
            <Head title={`Edit ${item.title}`} />
            <form
                className="max-w-xl p-6 space-y-4"
                onSubmit={(e) => { e.preventDefault(); form.patch(`/items/${item.id}`); }}
            >
                <h1 className="text-2xl font-semibold">Edit item</h1>

                <label className="block">
                    <span className="text-sm">Title</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                    <InputError message={form.errors.title} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Description</span>
                    <textarea
                        className="mt-1 w-full border rounded px-3 py-2"
                        rows={4}
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                    />
                    <InputError message={form.errors.description} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Category</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.category}
                        onChange={(e) => form.setData('category', e.target.value)}
                    />
                    <InputError message={form.errors.category} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Condition</span>
                    <select
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.condition}
                        onChange={(e) => form.setData('condition', e.target.value)}
                    >
                        {conditions.map((c) => (
                            <option key={c.value} value={c.value}>{c.label}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.condition} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Asking price (cents)</span>
                    <input
                        type="number"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.asking_price_cents}
                        onChange={(e) => form.setData('asking_price_cents', Number(e.target.value))}
                    />
                    <InputError message={form.errors.asking_price_cents} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Floor price (cents)</span>
                    <input
                        type="number"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.floor_price_cents}
                        onChange={(e) => form.setData('floor_price_cents', Number(e.target.value) || '')}
                    />
                    <InputError message={form.errors.floor_price_cents} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Location in house</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.location_in_house}
                        onChange={(e) => form.setData('location_in_house', e.target.value)}
                    />
                    <InputError message={form.errors.location_in_house} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Private notes</span>
                    <textarea
                        className="mt-1 w-full border rounded px-3 py-2"
                        rows={2}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                    <InputError message={form.errors.notes} className="mt-1" />
                </label>

                <section className="border-t pt-4">
                    <h2 className="text-lg font-medium mb-2">Photos</h2>
                    <PhotoUploader itemId={item.id} photos={item.photos} />
                </section>

                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner className="mr-2 size-4" />}
                    Save
                </Button>
            </form>
        </>
    );
}

ItemsEdit.layout = {
    breadcrumbs: [
        { title: 'Items', href: '/items' },
        { title: 'Edit', href: '#' },
    ],
};
```

- [ ] **Step 2: Verify the import paths exist**

Run these checks (in this order):

```bash
ls resources/js/components/ui/button.tsx
ls resources/js/components/input-error.tsx
ls resources/js/components/ui/spinner.tsx
```

Expected: all three files exist. (If `ui/spinner.tsx` is at a different path, search with `find resources/js -name "spinner*"` and adjust the import.)

- [ ] **Step 3: Run the build**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/items/edit.tsx
git commit -m "Render InputError on every field and use Button+Spinner on items/edit"
```

---

### Task 5: items/create.tsx — InputError on missing fields + Button/Spinner

**Files:**
- Modify: `resources/js/pages/items/create.tsx`

The current file renders raw `<p>` error tags only on `title` and `asking_price_cents` (lines 37, 65). We replace those with `<InputError>` and add it to the missing fields, plus polish the button.

- [ ] **Step 1: Replace the entire file**

Replace the contents of `resources/js/pages/items/create.tsx` with:

```tsx
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';

type Condition = { value: string; label: string };

export default function ItemsCreate({ conditions }: { conditions: Condition[] }) {
    const form = useForm({
        title: '',
        description: '',
        category: '',
        condition: conditions[0]?.value ?? '',
        asking_price_cents: 0,
        floor_price_cents: '' as number | '',
        location_in_house: '',
        notes: '',
    });

    return (
        <>
            <Head title="New item" />
            <form
                className="max-w-xl p-6 space-y-4"
                onSubmit={(e) => { e.preventDefault(); form.post('/items'); }}
            >
                <h1 className="text-2xl font-semibold">New item</h1>

                <label className="block">
                    <span className="text-sm">Title</span>
                    <input
                        name="title"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                    <InputError message={form.errors.title} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Description</span>
                    <textarea
                        rows={4}
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                    />
                    <InputError message={form.errors.description} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Category</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.category}
                        onChange={(e) => form.setData('category', e.target.value)}
                    />
                    <InputError message={form.errors.category} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Condition</span>
                    <select
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.condition}
                        onChange={(e) => form.setData('condition', e.target.value)}
                    >
                        {conditions.map((c) => (
                            <option key={c.value} value={c.value}>{c.label}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.condition} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Asking price (cents)</span>
                    <input
                        type="number"
                        name="asking_price_cents"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.asking_price_cents || ''}
                        onChange={(e) => form.setData('asking_price_cents', Number(e.target.value))}
                    />
                    <InputError message={form.errors.asking_price_cents} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Floor price (cents)</span>
                    <input
                        type="number"
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.floor_price_cents}
                        onChange={(e) => form.setData('floor_price_cents', e.target.value === '' ? '' : Number(e.target.value))}
                    />
                    <InputError message={form.errors.floor_price_cents} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Location in house</span>
                    <input
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.location_in_house}
                        onChange={(e) => form.setData('location_in_house', e.target.value)}
                    />
                    <InputError message={form.errors.location_in_house} className="mt-1" />
                </label>

                <label className="block">
                    <span className="text-sm">Private notes</span>
                    <textarea
                        rows={2}
                        className="mt-1 w-full border rounded px-3 py-2"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                    <InputError message={form.errors.notes} className="mt-1" />
                </label>

                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner className="mr-2 size-4" />}
                    Create
                </Button>
            </form>
        </>
    );
}
```

(If the original file had a `ItemsCreate.layout = { ... }` export, preserve it — copy it from the existing file.)

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/items/create.tsx
git commit -m "Render InputError on every field and use Button+Spinner on items/create"
```

---

## Phase 2 — Photos

### Task 6: ItemPhotoController flash + extend ItemPhotoTest

**Files:**
- Modify: `app/Http/Controllers/ItemPhotoController.php`
- Modify: `tests/Feature/ItemPhotoTest.php`

`reorder()` is intentionally NOT flashed (per spec: drag is its own feedback).

- [ ] **Step 1: Add failing flash assertion to the upload test**

In `tests/Feature/ItemPhotoTest.php`, locate the test at line 15 (`it('stores a photo and generates a thumbnail', ...`). At the end of the test, append a follow-up assertion. The exact added lines depend on what URL the controller redirects back to — ItemPhotoController::store returns `back()`, so the previous request URL was likely `/items/{item}/edit`. Add:

```php
actingAs($user)->get("/items/{$item->id}/edit")
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Photo uploaded.')
        ->etc()
    );
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `php artisan test --compact --filter="stores a photo and generates a thumbnail"`
Expected: FAIL — flash missing.

- [ ] **Step 3: Add Toast::success to ItemPhotoController::store**

In `app/Http/Controllers/ItemPhotoController.php`, modify `store()` (lines 15-38). Add `use App\Support\Toast;` at the top and add the flash before the return:

```php
public function store(Request $request, Item $item): RedirectResponse
{
    abort_unless($item->user_id === $request->user()->id, 403);

    $validated = $request->validate([
        'photo' => ['required', 'image', 'max:15360'],
    ]);

    $path = $validated['photo']->store("items/{$item->id}", 'public');
    $thumbnailPath = $this->createThumbnail($path);

    $position = (int) $item->photos()->max('position');
    $isFirst = $item->photos()->count() === 0;

    ItemPhoto::create([
        'item_id' => $item->id,
        'path' => $path,
        'thumbnail_path' => $thumbnailPath,
        'position' => $isFirst ? 0 : $position + 1,
        'is_primary' => $isFirst,
    ]);

    Toast::success('Photo uploaded.');

    return back();
}
```

Run the test — should PASS.

- [ ] **Step 4: Repeat for delete**

In `tests/Feature/ItemPhotoTest.php`, locate the test at line 58 (`it('deletes a photo and reassigns primary', ...`). Append:

```php
actingAs($user)->get("/items/{$item->id}/edit")
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Photo removed.')
        ->etc()
    );
```

Run — FAIL. Then add to `ItemPhotoController::destroy` (lines 61-77), before the `return back();`:

```php
Toast::success('Photo removed.');

return back();
```

Run — PASS.

- [ ] **Step 5: Run the full ItemPhotoTest suite**

Run: `php artisan test --compact --filter=ItemPhotoTest`
Expected: all green. The reorder test should still pass — we didn't add flash there.

- [ ] **Step 6: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/ItemPhotoController.php tests/Feature/ItemPhotoTest.php
git commit -m "Flash toasts for photo upload and delete"
```

---

### Task 7: Rebuild photo-uploader.tsx with per-operation pending state

**Files:**
- Modify: `resources/js/components/photo-uploader.tsx`

- [ ] **Step 1: Replace the file**

Replace the contents of `resources/js/components/photo-uploader.tsx` with:

```tsx
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';

type Photo = {
    id: number;
    path: string;
    thumbnail_path: string | null;
    position: number;
    is_primary: boolean;
};

export function PhotoUploader({ itemId, photos }: { itemId: number; photos: Photo[] }) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [removingId, setRemovingId] = useState<number | null>(null);

    const upload = (file: File) => {
        setUploading(true);
        router.post(
            `/items/${itemId}/photos`,
            { photo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setUploading(false),
            }
        );
    };

    const remove = (photoId: number) => {
        setRemovingId(photoId);
        router.delete(`/items/${itemId}/photos/${photoId}`, {
            preserveScroll: true,
            onFinish: () => setRemovingId(null),
        });
    };

    return (
        <div className="space-y-2">
            <div className="grid grid-cols-3 gap-2">
                {photos.map((p) => (
                    <div key={p.id} className="relative border rounded overflow-hidden">
                        <img
                            src={`/storage/${p.thumbnail_path ?? p.path}`}
                            alt=""
                            className="w-full h-32 object-cover"
                        />
                        {p.is_primary && (
                            <span className="absolute top-1 left-1 bg-black/70 text-white text-xs px-1.5 py-0.5 rounded">
                                Primary
                            </span>
                        )}
                        <button
                            type="button"
                            onClick={() => remove(p.id)}
                            disabled={removingId === p.id}
                            className="absolute top-1 right-1 bg-white/90 text-xs px-1.5 py-0.5 rounded inline-flex items-center"
                        >
                            {removingId === p.id ? <Spinner className="size-3" /> : 'Remove'}
                        </button>
                    </div>
                ))}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) upload(file);
                    e.target.value = '';
                }}
            />
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                disabled={uploading}
                className="border border-dashed rounded px-3 py-2 text-sm w-full"
            >
                {uploading
                    ? <span className="inline-flex items-center gap-2"><Spinner className="size-4" /> Uploading…</span>
                    : 'Add photo'}
            </button>
        </div>
    );
}
```

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/photo-uploader.tsx
git commit -m "Add pending state and spinners to photo uploader"
```

---

## Phase 3 — Item status transitions

### Task 8: ItemTransitionController flash + extend ItemTransitionTest

**Files:**
- Modify: `app/Http/Controllers/ItemTransitionController.php`
- Modify: `tests/Feature/ItemTransitionTest.php`

The toast message is dynamic per target state (per spec): "Item marked as sold." / "Item marked on hold." / "Item restored to available." We pick a single, clear message map covering the six target states.

- [ ] **Step 1: Add failing flash assertion**

In `tests/Feature/ItemTransitionTest.php`, locate the first test at line 8 (`it('transitions an owned item along an allowed edge', ...`). Capture the response and append:

```php
$response->assertRedirect();

actingAs($user)->get("/items/{$item->id}/show")  // adjust path if different
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Item marked as ready.')
        ->etc()
    );
```

(Look at the test body to confirm the target state — the message must match the state the test transitions to. If the test transitions `draft -> ready`, use `'Item marked as ready.'`. If the test goes to `listed`, use `'Item marked as listed.'`.)

If the redirect target isn't an inertia page (e.g., `back()` from a non-inertia request), instead assert via the manual session bag:

```php
expect(session('_inertia_flash.toast'))->toBe(['type' => 'success', 'message' => 'Item marked as ready.']);
```

The first form is preferred when feasible.

- [ ] **Step 2: Run the test to confirm it fails**

Run: `php artisan test --compact --filter="transitions an owned item"`
Expected: FAIL.

- [ ] **Step 3: Add a target-state-aware Toast::success to the controller**

In `app/Http/Controllers/ItemTransitionController.php`, add `use App\Support\Toast;` at the top, then modify `__invoke()`:

```php
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
```

(`ItemStatus` cases per the audit: Draft, Ready, Listed, Reserved, Sold, Withdrawn.)

- [ ] **Step 4: Run the test to confirm it passes**

Run: `php artisan test --compact --filter="transitions an owned item"`
Expected: PASS.

- [ ] **Step 5: Run the full ItemTransitionTest suite**

Run: `php artisan test --compact --filter=ItemTransitionTest`
Expected: all green.

- [ ] **Step 6: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/ItemTransitionController.php tests/Feature/ItemTransitionTest.php
git commit -m "Flash a state-aware toast on item transition"
```

---

### Task 9: items/show.tsx — pending state for transition + schedule pickup buttons

**Files:**
- Modify: `resources/js/pages/items/show.tsx`

The audit located bare `router.post` calls in the `TransitionControls` component (lines 130-228) and `SchedulePickupForm` component (lines 49-128). We add local pending state to disable + spinner the buttons during submit.

- [ ] **Step 1: Open the file and apply two changes**

Open `resources/js/pages/items/show.tsx`. We make two patches.

**Patch A — TransitionControls (around lines 130-228):** find the `router.post(...)` call (line ~136) and the buttons that invoke it. Add a local pending state and disable buttons while pending.

Locate the existing handler. It looks like:

```tsx
const post = (to: string) => {
    router.post(`/items/${item.id}/transition`, { to }, {
        preserveScroll: true,
        onError: (errors) => setError(errors.to ?? errors.kijiji_url ?? ''),
    });
};
```

Replace it with:

```tsx
const [pending, setPending] = useState(false);
const post = (to: string) => {
    setPending(true);
    router.post(`/items/${item.id}/transition`, { to }, {
        preserveScroll: true,
        onError: (errors) => setError(errors.to ?? errors.kijiji_url ?? ''),
        onFinish: () => setPending(false),
    });
};
```

For each transition button (originally at lines ~154, 168, 206, 221), wrap the label and add `disabled={pending}`. Example for one button:

```tsx
<Button onClick={() => post('ready')} disabled={pending}>
    {pending && <Spinner className="mr-2 size-4" />}
    Mark ready
</Button>
```

Apply the same shape (`disabled={pending}` + spinner inside) to all four transition buttons. Add the imports at the top of the file if missing:

```tsx
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
```

**Patch B — SchedulePickupForm (around lines 49-128):** the existing submit handler (around line 76) is:

```tsx
const submit = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/pickups', { ... }, { preserveScroll: true });
};
```

Add local pending state. Replace with:

```tsx
const [pending, setPending] = useState(false);
const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setPending(true);
    router.post('/pickups', { ... }, {
        preserveScroll: true,
        onFinish: () => setPending(false),
    });
};
```

(Keep the `{ ... }` payload from the existing code intact — only add `setPending(true)` and `onFinish`.)

Replace the submit button (around line 123) with:

```tsx
<Button type="submit" disabled={pending}>
    {pending && <Spinner className="mr-2 size-4" />}
    Schedule pickup
</Button>
```

If the original button text is different ("Save", "Schedule", etc.), keep the original text.

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: completes without TS errors. If it fails because `useState` is already imported in a different group, deduplicate.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/items/show.tsx
git commit -m "Track pending state on transition and schedule-pickup buttons"
```

---

## Phase 4 — Inquiries

### Task 10: InquiryController flash + extend InquiryStoreTest / InquiryUpdateTest

**Files:**
- Modify: `app/Http/Controllers/InquiryController.php`
- Modify: `tests/Feature/InquiryStoreTest.php`
- Modify: `tests/Feature/InquiryUpdateTest.php`

- [ ] **Step 1: Add failing flash assertion to InquiryStoreTest**

In `tests/Feature/InquiryStoreTest.php`, locate the test at line 11 (`it('creates an inquiry against an existing buyer', ...`). Append:

```php
actingAs($user)->get("/items/{$item->id}/show")  // or whatever route the user lands on
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Inquiry logged.')
        ->etc()
    );
```

(The store action returns `back()`, so the previous URL drives where the assertion fetches from. If the test originally navigated from `/items/{id}` or similar, use that.)

Run: `php artisan test --compact --filter="creates an inquiry against an existing buyer"`
Expected: FAIL.

- [ ] **Step 2: Add Toast::success to InquiryController::store**

In `app/Http/Controllers/InquiryController.php`, add `use App\Support\Toast;` at the top. Modify `store()` (lines 14-36), add `Toast::success('Inquiry logged.');` before the `return back();`:

```php
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
```

Run the test — PASS.

- [ ] **Step 3: Repeat for InquiryUpdateTest**

In `tests/Feature/InquiryUpdateTest.php`, at the end of the test at line 11 (`it('updates status, offered price, and last_contact_at', ...`), append the matching flash assertion with message `'Inquiry updated.'`.

Run — FAIL.

In `app/Http/Controllers/InquiryController.php`, add to `update()` (lines 38-63), before the `return back();`:

```php
Toast::success('Inquiry updated.');

return back();
```

Run — PASS.

- [ ] **Step 4: Run the full Inquiry test suites**

Run: `php artisan test --compact --filter=InquiryStoreTest`
Run: `php artisan test --compact --filter=InquiryUpdateTest`
Expected: all green.

- [ ] **Step 5: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/InquiryController.php tests/Feature/InquiryStoreTest.php tests/Feature/InquiryUpdateTest.php
git commit -m "Flash toasts for inquiry create and update"
```

---

### Task 11: inquiry-form.tsx — InputError + Button/Spinner

**Files:**
- Modify: `resources/js/components/inquiry-form.tsx`

- [ ] **Step 1: Replace the file**

Replace the contents of `resources/js/components/inquiry-form.tsx` with:

```tsx
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';

type Buyer = { id: number; display_name: string };

export function InquiryForm({ itemId, buyers }: { itemId: number; buyers: Buyer[] }) {
    const [mode, setMode] = useState<'existing' | 'new'>(buyers.length > 0 ? 'existing' : 'new');

    const form = useForm<{
        buyer_id: number | null;
        new_buyer: { display_name: string; phone: string; email: string };
        message_excerpt: string;
        offered_price_cents: number | '';
    }>({
        buyer_id: buyers[0]?.id ?? null,
        new_buyer: { display_name: '', phone: '', email: '' },
        message_excerpt: '',
        offered_price_cents: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...(mode === 'existing'
                ? { buyer_id: form.data.buyer_id }
                : { new_buyer: form.data.new_buyer }),
            message_excerpt: form.data.message_excerpt,
            offered_price_cents: form.data.offered_price_cents === '' ? null : form.data.offered_price_cents,
        };
        form.transform(() => payload);
        form.post(`/items/${itemId}/inquiries`, {
            preserveScroll: true,
            onSuccess: () => form.reset('message_excerpt', 'offered_price_cents'),
        });
    };

    return (
        <form onSubmit={submit} className="border rounded-lg p-4 space-y-3">
            <h3 className="font-medium text-sm">Log an inquiry</h3>

            <div className="flex gap-2 text-xs">
                <button
                    type="button"
                    onClick={() => setMode('existing')}
                    disabled={buyers.length === 0}
                    className={`border rounded px-2 py-1 ${mode === 'existing' ? 'bg-black text-white' : ''}`}
                >
                    Existing buyer
                </button>
                <button
                    type="button"
                    onClick={() => setMode('new')}
                    className={`border rounded px-2 py-1 ${mode === 'new' ? 'bg-black text-white' : ''}`}
                >
                    New buyer
                </button>
            </div>

            {mode === 'existing' ? (
                <div>
                    <select
                        value={form.data.buyer_id ?? ''}
                        onChange={(e) => form.setData('buyer_id', Number(e.target.value))}
                        className="w-full border rounded px-2 py-1 text-sm"
                    >
                        {buyers.map((b) => (
                            <option key={b.id} value={b.id}>{b.display_name}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.buyer_id} className="mt-1" />
                </div>
            ) : (
                <div className="space-y-2">
                    <div>
                        <input
                            type="text"
                            placeholder="Display name"
                            value={form.data.new_buyer.display_name}
                            onChange={(e) =>
                                form.setData('new_buyer', { ...form.data.new_buyer, display_name: e.target.value })
                            }
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors['new_buyer.display_name']} className="mt-1" />
                    </div>
                    <div>
                        <input
                            type="text"
                            placeholder="Phone (optional)"
                            value={form.data.new_buyer.phone}
                            onChange={(e) =>
                                form.setData('new_buyer', { ...form.data.new_buyer, phone: e.target.value })
                            }
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <InputError message={form.errors['new_buyer.phone']} className="mt-1" />
                    </div>
                </div>
            )}

            <div>
                <textarea
                    value={form.data.message_excerpt}
                    onChange={(e) => form.setData('message_excerpt', e.target.value)}
                    placeholder="Paste their message"
                    rows={3}
                    className="w-full border rounded px-2 py-1 text-sm"
                />
                <InputError message={form.errors.message_excerpt} className="mt-1" />
            </div>

            <div>
                <input
                    type="number"
                    value={form.data.offered_price_cents}
                    onChange={(e) => form.setData('offered_price_cents', e.target.value === '' ? '' : Number(e.target.value))}
                    placeholder="Offered (cents)"
                    className="w-full border rounded px-2 py-1 text-sm"
                />
                <InputError message={form.errors.offered_price_cents} className="mt-1" />
            </div>

            <Button type="submit" disabled={form.processing}>
                {form.processing && <Spinner className="mr-2 size-4" />}
                Log inquiry
            </Button>
        </form>
    );
}
```

- [ ] **Step 2: Run the build**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/inquiry-form.tsx
git commit -m "Render InputError and use Button+Spinner on inquiry form"
```

---

### Task 12: inquiry-timeline.tsx — pending state on inline edits

**Files:**
- Modify: `resources/js/components/inquiry-timeline.tsx`

The audit found 3 mutating `router.patch` calls in `InquiryRow` (lines ~51, 89-92, 116). They have no loading state. After Task 10, the server flashes "Inquiry updated." on each — but the user can't see disabled feedback while the request is in flight. We add per-row pending state.

- [ ] **Step 1: Read the existing file to understand the structure**

Read `resources/js/components/inquiry-timeline.tsx` end-to-end. The component renders one `InquiryRow` per inquiry. Each row has:

- A status `<select>` that fires `router.patch(...)` on change
- An offered-price `<input>` that fires `router.patch(...)` on blur
- A negotiation-note form

We add a single `pending` flag per row and disable each control while pending.

- [ ] **Step 2: Apply the changes inside InquiryRow**

At the top of the `InquiryRow` component (before the existing `useState` calls), add:

```tsx
const [pending, setPending] = useState(false);
```

Replace each `router.patch(...)` call to include `onFinish`. For example, the status-change call:

```tsx
// Before:
router.patch(`/inquiries/${inquiry.id}`, { status: newStatus }, { preserveScroll: true });

// After:
setPending(true);
router.patch(`/inquiries/${inquiry.id}`, { status: newStatus }, {
    preserveScroll: true,
    onFinish: () => setPending(false),
});
```

Apply the same shape (the `setPending(true)` line before the call + the `onFinish` callback) to all three `router.patch` calls in the file.

On each interactive control, add `disabled={pending}`:

```tsx
<select
    value={...}
    onChange={...}
    disabled={pending}
    className="..."
>
```

```tsx
<input
    type="number"
    onBlur={...}
    disabled={pending}
    className="..."
/>
```

For the negotiation-note submit button (find the `<button type="submit">` near line 116), wrap the label and add `disabled={pending}`. If the button is currently a raw `<button>`, leave it as raw — just add the disabled prop:

```tsx
<button type="submit" disabled={pending} className="...">
    {pending ? 'Saving…' : 'Add note'}
</button>
```

- [ ] **Step 3: Run the build**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/inquiry-timeline.tsx
git commit -m "Track pending state on inline inquiry edits"
```

---

## Phase 5 — Buyers

### Task 13: BuyerController flash + extend BuyerCrudTest

**Files:**
- Modify: `app/Http/Controllers/BuyerController.php`
- Modify: `tests/Feature/BuyerCrudTest.php`

- [ ] **Step 1: Add failing flash assertions**

In `tests/Feature/BuyerCrudTest.php`:

For `it('creates a buyer scoped to the current user', ...)` at line 27, append:

```php
actingAs($user)->get("/buyers/{$buyer->id}")
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Buyer added.')
        ->etc()
    );
```

(`$buyer` should be retrievable as `Buyer::latest()->first()` if not already in scope.)

For `it('updates a buyer', ...)` at line 63, append:

```php
actingAs($user)->get("/buyers/{$buyer->id}")
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Buyer updated.')
        ->etc()
    );
```

Run: `php artisan test --compact --filter=BuyerCrudTest`
Expected: 2 FAIL.

- [ ] **Step 2: Add Toast::success in BuyerController**

In `app/Http/Controllers/BuyerController.php`, add `use App\Support\Toast;` at the top. Modify `store()` and `update()`:

```php
public function store(StoreBuyerRequest $request): RedirectResponse
{
    $buyer = $request->user()->buyers()->create($request->validated());

    Toast::success('Buyer added.');

    return redirect("/buyers/{$buyer->id}");
}

public function update(UpdateBuyerRequest $request, Buyer $buyer): RedirectResponse
{
    $buyer->update($request->validated());

    Toast::success('Buyer updated.');

    return back();
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=BuyerCrudTest`
Expected: all green.

- [ ] **Step 4: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/BuyerController.php tests/Feature/BuyerCrudTest.php
git commit -m "Flash toasts for buyer create and update"
```

---

### Task 14: buyers/index.tsx + buyers/show.tsx — InputError + Button/Spinner

**Files:**
- Modify: `resources/js/pages/buyers/index.tsx`
- Modify: `resources/js/pages/buyers/show.tsx`

- [ ] **Step 1: Update buyers/index.tsx**

Find the form block (lines 14-78 per the audit). Replace the form section with:

```tsx
{showForm && (
    <form
        onSubmit={(e) => {
            e.preventDefault();
            form.post('/buyers', {
                onSuccess: () => {
                    form.reset();
                    setShowForm(false);
                },
            });
        }}
        className="border rounded-lg p-4 space-y-2"
    >
        <div>
            <input
                type="text"
                placeholder="Display name"
                value={form.data.display_name}
                onChange={(e) => form.setData('display_name', e.target.value)}
                className="w-full border rounded px-2 py-1 text-sm"
            />
            <InputError message={form.errors.display_name} className="mt-1" />
        </div>
        <div>
            <input
                type="text"
                placeholder="Phone"
                value={form.data.phone}
                onChange={(e) => form.setData('phone', e.target.value)}
                className="w-full border rounded px-2 py-1 text-sm"
            />
            <InputError message={form.errors.phone} className="mt-1" />
        </div>
        <div>
            <input
                type="email"
                placeholder="Email"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
                className="w-full border rounded px-2 py-1 text-sm"
            />
            <InputError message={form.errors.email} className="mt-1" />
        </div>
        <div>
            <input
                type="text"
                placeholder="Kijiji handle"
                value={form.data.kijiji_handle}
                onChange={(e) => form.setData('kijiji_handle', e.target.value)}
                className="w-full border rounded px-2 py-1 text-sm"
            />
            <InputError message={form.errors.kijiji_handle} className="mt-1" />
        </div>
        <Button type="submit" disabled={form.processing}>
            {form.processing && <Spinner className="mr-2 size-4" />}
            Save
        </Button>
    </form>
)}
```

Add the imports at the top of the file if not already present:

```tsx
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
```

- [ ] **Step 2: Update buyers/show.tsx**

Replace the form (lines 21-81 per the audit) with:

```tsx
<form
    onSubmit={(e) => {
        e.preventDefault();
        form.patch(`/buyers/${buyer.id}`, { preserveScroll: true });
    }}
    className="border rounded-lg p-4 space-y-2"
>
    <h2 className="font-medium text-sm">Edit details</h2>
    <div>
        <input
            type="text"
            value={form.data.display_name}
            onChange={(e) => form.setData('display_name', e.target.value)}
            className="w-full border rounded px-2 py-1 text-sm"
        />
        <InputError message={form.errors.display_name} className="mt-1" />
    </div>
    <div>
        <input
            type="text"
            placeholder="Phone"
            value={form.data.phone}
            onChange={(e) => form.setData('phone', e.target.value)}
            className="w-full border rounded px-2 py-1 text-sm"
        />
        <InputError message={form.errors.phone} className="mt-1" />
    </div>
    <div>
        <input
            type="email"
            placeholder="Email"
            value={form.data.email}
            onChange={(e) => form.setData('email', e.target.value)}
            className="w-full border rounded px-2 py-1 text-sm"
        />
        <InputError message={form.errors.email} className="mt-1" />
    </div>
    <div>
        <input
            type="text"
            placeholder="Kijiji handle"
            value={form.data.kijiji_handle}
            onChange={(e) => form.setData('kijiji_handle', e.target.value)}
            className="w-full border rounded px-2 py-1 text-sm"
        />
        <InputError message={form.errors.kijiji_handle} className="mt-1" />
    </div>
    <div>
        <textarea
            rows={2}
            placeholder="Trust notes (private)"
            value={form.data.trust_notes}
            onChange={(e) => form.setData('trust_notes', e.target.value)}
            className="w-full border rounded px-2 py-1 text-sm"
        />
        <InputError message={form.errors.trust_notes} className="mt-1" />
    </div>
    <Button type="submit" disabled={form.processing}>
        {form.processing && <Spinner className="mr-2 size-4" />}
        Save
    </Button>
</form>
```

Add the same three imports at the top if not already present.

- [ ] **Step 3: Run the build**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/buyers/index.tsx resources/js/pages/buyers/show.tsx
git commit -m "Render InputError and use Button+Spinner on buyer forms"
```

---

## Phase 6 — Pickups

### Task 15: PickupController flash + extend Pickup test files

**Files:**
- Modify: `app/Http/Controllers/PickupController.php`
- Modify: `tests/Feature/PickupCreateTest.php`
- Modify: `tests/Feature/PickupUpdateTest.php`
- Modify: `tests/Feature/PickupCompleteTest.php`
- Modify: `tests/Feature/PickupCancelTest.php`

- [ ] **Step 1: Add failing flash assertions to each pickup test file**

Append a flash assertion to one happy-path test in each file. Pick the simplest passing test in each.

In `tests/Feature/PickupCreateTest.php`, line 12 (`it('schedules a pickup and reserves attached items', ...`), append:

```php
actingAs($user)->get("/pickups/{$pickup->id}")
    ->assertInertia(fn ($page) => $page
        ->where('flash.toast.type', 'success')
        ->where('flash.toast.message', 'Pickup scheduled.')
        ->etc()
    );
```

In `tests/Feature/PickupUpdateTest.php`, line 10 (`it('updates pickup notes and payment_method', ...`), append a similar assertion with message `'Pickup updated.'`.

In `tests/Feature/PickupCompleteTest.php`, line 14 (`it('completes a pickup, marks payment received, and sells all items', ...`), append with message `'Pickup marked complete.'`.

In `tests/Feature/PickupCancelTest.php`, line 12 (`it('cancels a pickup and returns items to listed', ...`), append with message `'Pickup cancelled.'`. Note: the cancel test that uses `to: 'no_show'` should also assert flash, but with the same message `'Pickup cancelled.'` (we don't differentiate cancellation reasons in the toast).

Run: `php artisan test --compact --filter=Pickup` — expect 4 FAIL.

- [ ] **Step 2: Add Toast::success in PickupController**

In `app/Http/Controllers/PickupController.php`, add `use App\Support\Toast;` at the top. Modify each method:

```php
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

    Toast::success('Pickup scheduled.');

    return redirect("/pickups/{$pickup->id}");
}

public function update(UpdatePickupRequest $request, Pickup $pickup): RedirectResponse
{
    $pickup->update($request->validated());

    Toast::success('Pickup updated.');

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

    Toast::success('Pickup marked complete.');

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

    Toast::success('Pickup cancelled.');

    return back();
}
```

- [ ] **Step 3: Run all pickup tests**

Run: `php artisan test --compact --filter=Pickup`
Expected: all green.

- [ ] **Step 4: Format and commit**

Run: `vendor/bin/pint --dirty --format agent`

```bash
git add app/Http/Controllers/PickupController.php tests/Feature/PickupCreateTest.php tests/Feature/PickupUpdateTest.php tests/Feature/PickupCompleteTest.php tests/Feature/PickupCancelTest.php
git commit -m "Flash toasts for pickup schedule/update/complete/cancel"
```

---

### Task 16: pickups/show.tsx — InputError + pending for complete/cancel

**Files:**
- Modify: `resources/js/pages/pickups/show.tsx`

The audit found three areas:
1. The notes/payment edit form (lines 35-101) — needs InputError + Button/Spinner
2. The Complete button (lines 119-131) — needs local pending state
3. Cancel and No-Show buttons (lines 140-165) — need local pending state

- [ ] **Step 1: Update the edit form**

Replace the edit form block with:

```tsx
<form
    onSubmit={(e) => {
        e.preventDefault();
        editForm.patch(`/pickups/${pickup.id}`, { preserveScroll: true });
    }}
    className="border rounded-lg p-4 space-y-2"
>
    <h2 className="font-medium text-sm">Notes & payment method</h2>
    <div>
        <textarea
            rows={3}
            value={editForm.data.notes}
            onChange={(e) => editForm.setData('notes', e.target.value)}
            placeholder="Pickup time, location, anything else"
            className="w-full border rounded px-2 py-1 text-sm"
        />
        <InputError message={editForm.errors.notes} className="mt-1" />
    </div>
    <div>
        <select
            value={editForm.data.payment_method}
            onChange={(e) => editForm.setData('payment_method', e.target.value)}
            className="w-full border rounded px-2 py-1 text-sm"
        >
            <option value="">Select payment method…</option>
            {payment_methods.map((m) => (
                <option key={m.value} value={m.value}>{m.label}</option>
            ))}
        </select>
        <InputError message={editForm.errors.payment_method} className="mt-1" />
    </div>
    <Button type="submit" disabled={editForm.processing}>
        {editForm.processing && <Spinner className="mr-2 size-4" />}
        Save
    </Button>
</form>
```

- [ ] **Step 2: Add pending state for the action buttons**

Near the top of the component body (alongside the existing `useState` for `completePayment`), add:

```tsx
const [completePending, setCompletePending] = useState(false);
const [cancelPending, setCancelPending] = useState<'cancelled' | 'no_show' | null>(null);
```

Replace the Complete button (around lines 119-131):

```tsx
<Button
    type="button"
    onClick={() => {
        setCompletePending(true);
        router.post(
            `/pickups/${pickup.id}/complete`,
            { payment_method: completePayment },
            {
                preserveScroll: true,
                onFinish: () => setCompletePending(false),
            },
        );
    }}
    disabled={completePending}
    className="bg-emerald-700 text-white"
>
    {completePending && <Spinner className="mr-2 size-4" />}
    Complete & mark sold
</Button>
```

Replace the Cancel and No-Show buttons (around lines 140-165):

```tsx
<button
    type="button"
    disabled={cancelPending !== null}
    onClick={() => {
        setCancelPending('cancelled');
        router.post(
            `/pickups/${pickup.id}/cancel`,
            { to: 'cancelled' },
            {
                preserveScroll: true,
                onFinish: () => setCancelPending(null),
            },
        );
    }}
    className="text-sm border rounded px-3 py-1.5 inline-flex items-center"
>
    {cancelPending === 'cancelled' ? <Spinner className="mr-2 size-4" /> : null}
    Cancel pickup
</button>
<button
    type="button"
    disabled={cancelPending !== null}
    onClick={() => {
        setCancelPending('no_show');
        router.post(
            `/pickups/${pickup.id}/cancel`,
            { to: 'no_show' },
            {
                preserveScroll: true,
                onFinish: () => setCancelPending(null),
            },
        );
    }}
    className="text-sm border rounded px-3 py-1.5 inline-flex items-center"
>
    {cancelPending === 'no_show' ? <Spinner className="mr-2 size-4" /> : null}
    Mark no-show
</button>
```

Add the imports at the top if not already present:

```tsx
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
```

- [ ] **Step 3: Run the build**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/pickups/show.tsx
git commit -m "Render InputError and pending state on pickup actions"
```

---

## Phase 7 — Settings polish

### Task 17: Add spinner to settings/snippets.tsx button

**Files:**
- Modify: `resources/js/pages/settings/snippets.tsx`

The audit found this file already renders `<InputError>` on every field and `<Button disabled={processing}>` on the submit. The only missing piece is the spinner inside the button.

- [ ] **Step 1: Add the import**

At the top of the file, alongside the existing imports, add:

```tsx
import { Spinner } from '@/components/ui/spinner';
```

- [ ] **Step 2: Add the spinner inside the button**

The Button is around line 153. Locate:

```tsx
<Button
    disabled={processing}
    data-test="update-snippets-button"
>
    Save
</Button>
```

Replace with:

```tsx
<Button
    disabled={processing}
    data-test="update-snippets-button"
>
    {processing && <Spinner className="mr-2 size-4" />}
    Save
</Button>
```

- [ ] **Step 3: Run the build**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/settings/snippets.tsx
git commit -m "Show spinner on snippets save button while processing"
```

---

## Phase 8 — Browser smoke

### Task 18: Browser smoke for the items/edit happy path + photo flow

**Files:**
- Create or modify: a smoke script following the existing pattern from commit `2bb59bd` (browser smoke test for happy-path on stale-item filter)

- [ ] **Step 1: Locate the existing smoke script pattern**

Run: `git show --stat 2bb59bd` to see what file the previous smoke test added. Then read that file to understand the structure.

Run: `find tests -name "*smoke*" -o -name "*browser*"` — see if there's an existing smoke directory.

Open the file referenced by `2bb59bd` and copy its setup boilerplate (login, fixture creation, MCP browser tool calls).

- [ ] **Step 2: Write the smoke script**

Create or extend the smoke test file with a single happy-path script that:

1. Logs in as a test user.
2. Creates an item via `Item::factory()->create()`.
3. Navigates to `/items/{id}/edit` using the chrome MCP tools (`mcp__claude-in-chrome__navigate`).
4. Edits the title field, clicks Save.
5. Asserts:
   - The button shows a spinner during submit (use `mcp__claude-in-chrome__find` or `read_page`).
   - A toast with text "Item updated." appears (read the page text and grep for the message).
6. Triggers a validation failure (clear title, click Save).
7. Asserts:
   - Inline error appears under the title field.
   - No toast fires.
8. Uploads a photo (file_upload MCP tool, target the hidden file input).
9. Asserts:
   - The Add photo button shows "Uploading…" briefly.
   - A toast "Photo uploaded." appears.
   - A new thumbnail appears in the grid.
10. Removes the photo. Asserts a toast "Photo removed.".

If the existing smoke script uses Pest browser-test syntax, mirror that. If it uses raw Bash + curl + chrome MCP, mirror that.

- [ ] **Step 3: Run the smoke**

Follow whatever invocation the existing smoke script uses (likely `php artisan test --compact --filter=...` or a custom shell script). The smoke should print PASS for each assertion.

- [ ] **Step 4: Commit**

```bash
git add <smoke files>
git commit -m "Add browser smoke for submission feedback happy path"
```

---

## Final verification

### Task 19: Run full test suite

- [ ] **Step 1: Run all tests**

Run: `php artisan test --compact`
Expected: all green. No regressions.

- [ ] **Step 2: Run pint final check**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no changes (everything formatted as we went).

- [ ] **Step 3: Run the build one more time**

Run: `npm run build`
Expected: completes without TS errors.

- [ ] **Step 4: Manual sanity sweep at /items/1/edit**

If a dev server is running (`composer run dev` or `npm run dev`), open `/items/1/edit` and:

- Edit a field, click Save → toast appears, button spins during submit.
- Clear a required field, click Save → inline `<InputError>` appears, no toast.
- Upload a photo → "Uploading…" appears, then a toast.
- Remove a photo → spinner replaces "Remove", then a toast.

If anything is missing, file a follow-up (don't squeeze fixes into this plan retroactively).

The plan is complete when all 18 tasks are committed and Task 19 verifications pass.
