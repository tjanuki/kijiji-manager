# Submission Feedback — Design Spec

**Date:** 2026-05-08
**Status:** Approved (pending implementation plan)

## Problem

Submitting forms in this app currently leaves the user guessing whether anything happened. Buttons disable briefly, the page reloads, and that's it — no toast, no inline confirmation, no error feedback. On `/items/1/edit`, saving succeeds silently. Photo uploads vanish into the network with no progress indicator. Validation errors fail silently because forms don't render `form.errors`. Server errors fail silently because nothing surfaces them on the client.

The infrastructure to fix this is already half-built:

- Sonner toaster mounted at root in `resources/js/app.tsx` and `resources/js/components/ui/sonner.tsx`
- `useFlashToast` hook in `resources/js/hooks/use-flash-toast.ts` listens to Inertia's `flash` event for `flash.toast = {type, message}`
- `Inertia::flash('toast', [...])` works server-side — used today only in `app/Http/Controllers/Settings/ProfileController.php:41`
- `<InputError>` component already exists for inline field errors

We need to **finish the convention** and **apply it everywhere**.

## Goals

Close four feedback gaps consistently across all mutating actions in the app:

1. **Success toasts** on every successful mutation
2. **Inline validation errors** on every form field
3. **Loading states** visible on every submit (button spinner, "Uploading…" for the photo flow)
4. **Unexpected error toasts** for 403 / 404 / 419 / 429 / 500 / network failures

## Non-goals

- Confirm dialogs on destructive actions (deferred — separate UX project)
- Optimistic UI for state changes or photo tiles
- Upload progress percentage or drag-and-drop file input
- i18n / translation files for toast strings (single-user personal app)
- Refactoring pages beyond the minimum needed for this feature

## Architecture

```
┌─ Server ─────────────────────────────────┐    ┌─ Client ─────────────────────────────┐
│ Controller mutates model                 │    │ <Toaster /> at root (Sonner)         │
│   ↓                                      │    │   ↑ toast.success / toast.error      │
│ Toast::success('Item updated.')          │ ──▶│ useFlashToast (success path)         │
│ Toast::error('Could not delete.')        │    │ useErrorToast (NEW: 403/500/network) │
│   ↓                                      │    │   ↑ router.on('httpException',       │
│ Inertia::flash('toast', [...])           │    │       'networkError')                │
│   ↓ redirect/back                        │    │ Forms render <InputError> for 422    │
└──────────────────────────────────────────┘    └──────────────────────────────────────┘
```

Three behavioral lanes:

1. **Success** → server flashes via `Toast::success()` → Sonner success toast.
2. **Validation (422)** → Inertia hands `form.errors` → `<InputError>` renders inline. No toast.
3. **Unexpected (403, 404, 419, 429, 500, network)** → new client hook `useErrorToast` listens to Inertia v3's `httpException` / `networkError` events → Sonner error toast.

Loading states piggyback on `form.processing` for `useForm`-driven forms, and on local `useState` flags for bare `router.post` / `router.delete` calls (status transitions, photo uploader).

## Components

### 1. Server-side toast helper — `app/Support/Toast.php` (NEW)

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

The wire format is identical to the existing flash payload, so `useFlashToast` continues to work unchanged. Existing `Inertia::flash('toast', ...)` calls in `ProfileController` are equivalent and may be migrated opportunistically but do not block the feature.

Messages are inline literal strings — no `lang/en/toasts.php` file, no `__()` wrapping.

### 2. Client-side error toast hook — `resources/js/hooks/use-error-toast.ts` (NEW)

```ts
import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

export function useErrorToast(): void {
    useEffect(() => {
        const offHttp = router.on('httpException', (event) => {
            const status = (event as CustomEvent).detail?.response?.status;
            // 422 = validation; render inline. Everything else = surface as toast.
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

        return () => { offHttp(); offNetwork(); };
    }, []);
}
```

Mounted alongside `useFlashToast` in `resources/js/components/ui/sonner.tsx`:

```tsx
function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();
    useFlashToast();
    useErrorToast();   // NEW
    return <Sonner ... />;
}
```

Inertia v3 event names (`httpException`, `networkError`) supersede v2's `invalid` and `exception`. The hook uses the v3 names.

`Inertia::flash` does not survive a non-2xx response, so server-side `Toast::error()` is for cases where the controller deliberately catches and recovers (returns a redirect). True exceptions and authorization failures must be handled client-side; `useErrorToast` is the catch-all.

### 3. Inline validation error rendering

Every form input gets an `<InputError>` directly below it:

```tsx
<label className="block">
    <span className="text-sm">Title</span>
    <input
        className="mt-1 w-full border rounded px-3 py-2"
        value={form.data.title}
        onChange={(e) => form.setData('title', e.target.value)}
    />
    <InputError message={form.errors.title} className="mt-1" />
</label>
```

No new wrapper component. The 3-line pattern is short and grep-able. Validation never fires a toast — inline rendering is sufficient and a toast would be redundant noise.

### 4. Loading state polish

Buttons that submit forms:

```tsx
<Button type="submit" disabled={form.processing}>
    {form.processing && <Spinner className="mr-2 size-4" />}
    Save
</Button>
```

`Spinner` already exists; `Button` from `components/ui/button.tsx` already exists. `items/edit.tsx` currently uses a raw `<button className="bg-black ...">`; we swap it for the shadcn `<Button>` while we're touching it (matches the rest of the app). No other stylistic changes.

For bare `router.post` / `router.delete` calls (status transitions, photo uploader), add local `useState` pending flags and toggle them in `onFinish`. See Section 5 for the photo uploader implementation.

### 5. Photo uploader rebuild — `resources/js/components/photo-uploader.tsx`

Today, the component fires `router.post` / `router.delete` with no callbacks. Rebuilt to track per-operation pending state:

```tsx
export function PhotoUploader({ itemId, photos }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [removingId, setRemovingId] = useState<number | null>(null);

    const upload = (file: File) => {
        setUploading(true);
        router.post(`/items/${itemId}/photos`, { photo: file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
        });
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
                    <div key={p.id} className="relative ...">
                        <img ... />
                        {p.is_primary && <span ...>Primary</span>}
                        <button
                            type="button"
                            onClick={() => remove(p.id)}
                            disabled={removingId === p.id}
                            className="absolute top-1 right-1 ..."
                        >
                            {removingId === p.id ? <Spinner className="size-3" /> : 'Remove'}
                        </button>
                    </div>
                ))}
            </div>

            <input ref={inputRef} type="file" accept="image/*" className="hidden"
                onChange={(e) => { const f = e.target.files?.[0]; if (f) upload(f); e.target.value = ''; }} />

            <button type="button" onClick={() => inputRef.current?.click()}
                disabled={uploading}
                className="border border-dashed rounded px-3 py-2 text-sm w-full">
                {uploading
                    ? <span className="inline-flex items-center gap-2"><Spinner className="size-4" /> Uploading…</span>
                    : 'Add photo'}
            </button>
        </div>
    );
}
```

`useForm` is not used here because the component owns three operations (upload, delete, reorder) and `useForm` is modeled around one form. Per-operation `useState` is the right shape.

## Convention rule

> A mutating action gets a `Toast::success()` when the user clicked something and would otherwise have no proof it worked. It gets none when the visual state of the page IS the proof.

This rule excludes:

- Photo reorder (drag visually moves the tile)
- Theme/appearance switcher (the page restyles immediately)
- Any future drag-and-drop, instant-toggle, or auto-save flow

It includes everything in the action inventory below.

## Action inventory — server-side

| Controller / Method | Toast message |
|---|---|
| `ItemController@store` | `'Item created.'` |
| `ItemController@update` | `'Item updated.'` |
| `ItemController@destroy` | `'Item deleted.'` |
| `ItemTransitionController@__invoke` | dynamic by target state: `'Item marked as sold.'` / `'Item marked on hold.'` / `'Item restored to available.'` |
| `ItemPhotoController@store` | `'Photo uploaded.'` |
| `ItemPhotoController@destroy` | `'Photo removed.'` |
| `ItemPhotoController@reorder` | (skip — drag is its own feedback) |
| `InquiryController@store` | `'Inquiry logged.'` |
| `InquiryController@update` | `'Inquiry updated.'` |
| `BuyerController@store` | `'Buyer added.'` |
| `BuyerController@update` | `'Buyer updated.'` |
| `PickupController@store` | `'Pickup scheduled.'` |
| `PickupController@update` | `'Pickup updated.'` |
| `PickupController@complete` | `'Pickup marked complete.'` |
| `PickupController@cancel` | `'Pickup cancelled.'` |
| `Settings\ProfileController@update` | already flashes — leave |
| `Settings\SecurityController@*` | audit during implementation; flash where missing |
| `Settings\SnippetsController@*` | audit during implementation; flash where missing |

## Action inventory — client-side

| File | Change |
|---|---|
| `app/Support/Toast.php` | NEW — `Toast::success` / `Toast::error` |
| `resources/js/hooks/use-error-toast.ts` | NEW — listens to `httpException` / `networkError` |
| `resources/js/components/ui/sonner.tsx` | call `useErrorToast()` next to `useFlashToast()` |
| `resources/js/pages/items/edit.tsx` | `<InputError>` on all 7 fields; swap raw button for `<Button>` + `<Spinner>` |
| `resources/js/pages/items/create.tsx` | `<InputError>` on missing fields; polish button |
| `resources/js/pages/items/show.tsx` | local `pending` state for transition buttons; `<Spinner>` on each |
| `resources/js/pages/items/index.tsx` | audit during implementation; same pattern if mutating actions exist |
| `resources/js/components/inquiry-form.tsx` | `<InputError>` everywhere; polish button |
| `resources/js/components/photo-uploader.tsx` | rebuild per Component 5 |
| `resources/js/pages/buyers/*.tsx` | audit + same form pattern |
| `resources/js/pages/pickups/*.tsx` | audit + same form pattern; local `pending` for complete/cancel |
| `resources/js/pages/settings/snippets.tsx` | audit + same form pattern |

Approximate touch count: 1 new PHP class, 1 new TS hook, ~7 controller methods updated, ~10 React files updated.

## Testing

### PHP unit

`tests/Unit/Support/ToastTest.php` (NEW):

- `Toast::success('x')` flashes `['type' => 'success', 'message' => 'x']` under the `toast` key
- `Toast::error('x')` flashes `['type' => 'error', 'message' => 'x']` under the `toast` key

### PHP feature (Pest)

For each newly-flashing controller method, extend the existing feature test (or add one if missing) to assert the flash payload after a successful mutation. Pattern:

```php
$response = $this->patch("/items/{$item->id}", $valid)->assertRedirect();
// Inertia shares flash via the session under '_flash.new' or a similar key —
// the exact assertion follows existing patterns in tests/Feature/Settings/ProfileUpdateTest.php
```

One assertion per controller action, in the test file that already exists. No new test files required beyond `ToastTest.php`.

### Frontend

No Jest/Vitest in this project. Frontend behavior is covered by browser smoke.

### Browser smoke

Add a single happy-path script (or extend the existing stale-filter smoke from commit `2bb59bd`) that walks:

- `/items/1/edit` → edit title → submit → asserts toast "Item updated." appears and button shows spinner during submit
- Validation: clear required field → submit → asserts `<InputError>` appears next to field, no toast
- Photo upload: pick file → asserts "Uploading…" appears → asserts toast "Photo uploaded." and new thumbnail
- Photo remove: click Remove → asserts spinner replaces button text → asserts toast "Photo removed."

Error path (5xx) is verified manually by temporarily throwing in a controller during development; not automated.

## Risks and considerations

- **Toast message specificity drift** — over time, similar actions could end up with inconsistent message wording. Mitigation: the action inventory above is the canonical list; add new entries when new mutations are added.
- **Forgotten flash on new actions** — there's no automatic enforcement that every mutation flashes. Mitigation: feature tests for new actions should follow the established assertion pattern, which makes a missing flash visible.
- **Inertia v3 event name drift** — if Inertia renames events again, `useErrorToast` and `useFlashToast` need updates. Mitigation: events are referenced in only two files; easy to grep and update.
- **`Inertia::flash` not surviving error responses** — by design, since we surface errors client-side. Documented inline in the hook.
