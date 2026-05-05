# Phase 3 — Inquiries & Buyers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add manual-entry buyers and inquiries — track who is asking about which item, what they said, what they offered, and the negotiation state — plus quick reply templates the user copies into Kijiji's inbox.

**Architecture:** Two new Eloquent models (`Buyer`, `Inquiry`) scoped per user (buyers belong directly to the user; inquiries inherit ownership through their `Item`). Inquiry create/update endpoints support quick-create of a buyer in the same form. Reply templates are stored as a `reply_templates` array nested inside the existing `user_settings.snippets` JSON column — no schema change needed. The item show page gains an inquiry timeline; a new buyers index/show pair gives a per-buyer history view.

**Tech Stack:** Laravel 13, Inertia v3, React 19, Pest 4, Wayfinder. Follows existing conventions (`auth()->id()` ownership checks via `abort_unless`, snake_case columns, enum casts on models, Pest top-level `it(...)` tests, factories using `fake()`).

---

## Pre-flight

- This is Phase 3 of the existing implementation plan at `.claude/output/20260429/20260429_1115_kijiji_manager/implementation-plan.md`. Phases 1 and 2 (inventory CRUD, photos, listing draft, transitions, snippets) are already shipped on `main`.
- Phase 4 (Pickups) is **not** in scope. The plan does not touch the `Pickup` model or any `reserved`/`sold` automation. The `listed → reserved` transition continues to be reachable only via `ItemTransitionController` until Phase 4 lands.
- Per `CLAUDE.md`:
  - Use `php artisan make:` commands for new files; pass `--no-interaction`.
  - Run `vendor/bin/pint --dirty --format agent` after PHP changes are complete in a task.
  - Use `php artisan test --compact --filter=…` for fast targeted runs.
  - Search Boost docs (`search-docs`) before any non-trivial framework call you are unsure of.
- After every PHP-changing task, before commit: run `vendor/bin/pint --dirty --format agent` and re-run the affected test.

---

## File Structure

**Backend (create):**
- `app/Enums/InquiryStatus.php` — string-backed enum: `new`, `replied`, `negotiating`, `ghosted`, `declined`. Has a `label()` method matching `ItemStatus`.
- `app/Models/Buyer.php` — `belongsTo(User)`, `hasMany(Inquiry)`. SoftDeletes.
- `app/Models/Inquiry.php` — `belongsTo(Item)`, `belongsTo(Buyer)`. Casts `status` → `InquiryStatus`, `received_at`/`last_contact_at` → datetime, `negotiation_log` → array.
- `database/migrations/2026_05_04_120000_create_buyers_table.php`
- `database/migrations/2026_05_04_120100_create_inquiries_table.php`
- `database/factories/BuyerFactory.php`
- `database/factories/InquiryFactory.php`
- `app/Http/Controllers/BuyerController.php` — index/store/show/update.
- `app/Http/Controllers/InquiryController.php` — store (nested under item), update.
- `app/Http/Requests/StoreBuyerRequest.php`
- `app/Http/Requests/UpdateBuyerRequest.php`
- `app/Http/Requests/StoreInquiryRequest.php`
- `app/Http/Requests/UpdateInquiryRequest.php`

**Backend (modify):**
- `app/Models/User.php:39-44` — add `buyers(): HasMany` relation alongside the existing `items()` relation.
- `app/Models/Item.php:35-38` — add `inquiries(): HasMany` relation alongside `photos()`.
- `app/Http/Controllers/ItemController.php:55-70` — `show()` eager-loads inquiries with their buyer and passes both `inquiries` and the user's `buyers` list to Inertia.
- `app/Http/Requests/Settings/SnippetsUpdateRequest.php` — accept `reply_templates` array.
- `app/Http/Controllers/Settings/SnippetsController.php` — surface `reply_templates` in the `edit()` payload.
- `routes/web.php` — register buyer and inquiry routes inside the existing `auth/verified` group.

**Frontend (create):**
- `resources/js/components/inquiry-timeline.tsx` — chronological list, status pill, copy-paste buttons for reply templates.
- `resources/js/components/inquiry-form.tsx` — new-inquiry form with quick-create buyer (used inside `items/show`).
- `resources/js/pages/buyers/index.tsx` — list of buyers with last-contact timestamp and inquiry count.
- `resources/js/pages/buyers/show.tsx` — buyer detail + their inquiries.

**Frontend (modify):**
- `resources/js/pages/items/show.tsx` — render `<InquiryTimeline />` and `<InquiryForm />` below the existing transition controls. Pass `buyers`, `inquiries`, `reply_templates` props.
- `resources/js/pages/settings/snippets.tsx` — add a "Reply templates" section with add/remove/reorder of `{label, body}` entries.
- `resources/js/components/app-sidebar.tsx` (only if a sidebar entry for `Items` already exists) — add a `Buyers` entry. If the file isn't structured to make this trivial, skip it; the buyers page is reachable via `/buyers` directly. **Verify before editing** that the sidebar already has nav entries; do not invent the structure.

---

## Task 1: Add `InquiryStatus` enum

**Files:**
- Create: `app/Enums/InquiryStatus.php`
- Test: `tests/Unit/Enums/InquiryStatusTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Enums/InquiryStatusTest.php`:

```php
<?php

use App\Enums\InquiryStatus;

it('exposes all five statuses with labels', function () {
    expect(InquiryStatus::cases())->toHaveCount(5);
    expect(InquiryStatus::New->value)->toBe('new');
    expect(InquiryStatus::Replied->value)->toBe('replied');
    expect(InquiryStatus::Negotiating->value)->toBe('negotiating');
    expect(InquiryStatus::Ghosted->value)->toBe('ghosted');
    expect(InquiryStatus::Declined->value)->toBe('declined');
});

it('returns a human label for each status', function () {
    expect(InquiryStatus::New->label())->toBe('New');
    expect(InquiryStatus::Replied->label())->toBe('Replied');
    expect(InquiryStatus::Negotiating->label())->toBe('Negotiating');
    expect(InquiryStatus::Ghosted->label())->toBe('Ghosted');
    expect(InquiryStatus::Declined->label())->toBe('Declined');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=InquiryStatusTest`
Expected: FAIL — `App\Enums\InquiryStatus` not found.

- [ ] **Step 3: Create the enum**

Create `app/Enums/InquiryStatus.php`:

```php
<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case New = 'new';
    case Replied = 'replied';
    case Negotiating = 'negotiating';
    case Ghosted = 'ghosted';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Replied => 'Replied',
            self::Negotiating => 'Negotiating',
            self::Ghosted => 'Ghosted',
            self::Declined => 'Declined',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=InquiryStatusTest`
Expected: PASS, 2 assertions across 2 tests.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/InquiryStatus.php tests/Unit/Enums/InquiryStatusTest.php
git commit -m "feat(enums): add InquiryStatus enum"
```

---

## Task 2: Buyers table migration

**Files:**
- Create: `database/migrations/2026_05_04_120000_create_buyers_table.php`

- [ ] **Step 1: Generate the migration**

Run:

```bash
php artisan make:migration create_buyers_table --no-interaction
```

Then **rename the generated file** to `2026_05_04_120000_create_buyers_table.php` so it sorts ahead of `inquiries`. (Use `git mv database/migrations/<generated_name> database/migrations/2026_05_04_120000_create_buyers_table.php`.)

- [ ] **Step 2: Fill in the migration**

Replace contents:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('kijiji_handle')->nullable();
            $table->text('trust_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: prints `Migrating: 2026_05_04_120000_create_buyers_table` then `Migrated`.

- [ ] **Step 4: Confirm schema**

Run: `php artisan db:show --counts` (sanity check) and `php artisan tinker --execute 'dump(Schema::getColumnListing("buyers"));'`
Expected: lists `id, user_id, display_name, phone, email, kijiji_handle, trust_notes, deleted_at, created_at, updated_at`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_04_120000_create_buyers_table.php
git commit -m "feat(db): create buyers table"
```

---

## Task 3: `Buyer` model + factory

**Files:**
- Create: `app/Models/Buyer.php`
- Create: `database/factories/BuyerFactory.php`
- Test: `tests/Feature/BuyerModelTest.php`

- [ ] **Step 1: Generate model and factory**

Run:

```bash
php artisan make:model Buyer --factory --no-interaction
```

This creates `app/Models/Buyer.php` and `database/factories/BuyerFactory.php`.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/BuyerModelTest.php`:

```php
<?php

use App\Models\Buyer;
use App\Models\User;

it('creates a buyer scoped to a user via the factory', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    expect($buyer->user_id)->toBe($user->id);
    expect($buyer->user->is($user))->toBeTrue();
});

it('soft-deletes a buyer', function () {
    $buyer = Buyer::factory()->create();
    $buyer->delete();

    expect(Buyer::query()->find($buyer->id))->toBeNull();
    expect(Buyer::withTrashed()->find($buyer->id))->not->toBeNull();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=BuyerModelTest`
Expected: FAIL — model has no `user()` relation / no `SoftDeletes` trait.

- [ ] **Step 4: Implement the model**

Replace `app/Models/Buyer.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Buyer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }
}
```

- [ ] **Step 5: Implement the factory**

Replace `database/factories/BuyerFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Buyer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Buyer>
 */
class BuyerFactory extends Factory
{
    protected $model = Buyer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'kijiji_handle' => fake()->optional()->userName(),
            'trust_notes' => null,
        ];
    }
}
```

> Note: `Inquiry` is referenced from `Buyer::inquiries()` but does not yet exist. The relation method is lazy (no Eloquent calls happen at model-load time), so the test passes. Task 5 creates the model.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=BuyerModelTest`
Expected: PASS.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Buyer.php database/factories/BuyerFactory.php tests/Feature/BuyerModelTest.php
git commit -m "feat(models): add Buyer model and factory"
```

---

## Task 4: Inquiries table migration

**Files:**
- Create: `database/migrations/2026_05_04_120100_create_inquiries_table.php`

- [ ] **Step 1: Generate the migration**

Run:

```bash
php artisan make:migration create_inquiries_table --no-interaction
```

Rename to `2026_05_04_120100_create_inquiries_table.php`.

- [ ] **Step 2: Fill in the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
            $table->text('message_excerpt')->nullable();
            $table->string('status')->default('new');
            $table->unsignedInteger('offered_price_cents')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->json('negotiation_log')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status']);
            $table->index(['buyer_id', 'last_contact_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: `Migrated: 2026_05_04_120100_create_inquiries_table`.

- [ ] **Step 4: Confirm schema**

Run: `php artisan tinker --execute 'dump(Schema::getColumnListing("inquiries"));'`
Expected: contains `item_id, buyer_id, message_excerpt, status, offered_price_cents, received_at, last_contact_at, negotiation_log`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_04_120100_create_inquiries_table.php
git commit -m "feat(db): create inquiries table"
```

---

## Task 5: `Inquiry` model + factory

**Files:**
- Create: `app/Models/Inquiry.php`
- Create: `database/factories/InquiryFactory.php`
- Test: `tests/Feature/InquiryModelTest.php`

- [ ] **Step 1: Generate**

Run: `php artisan make:model Inquiry --factory --no-interaction`

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/InquiryModelTest.php`:

```php
<?php

use App\Enums\InquiryStatus;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\Item;
use App\Models\User;

it('attaches an inquiry to an item and a buyer', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    $inquiry = Inquiry::factory()->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
    ]);

    expect($inquiry->item->is($item))->toBeTrue();
    expect($inquiry->buyer->is($buyer))->toBeTrue();
});

it('casts status to InquiryStatus and negotiation_log to array', function () {
    $inquiry = Inquiry::factory()->create([
        'status' => 'negotiating',
        'negotiation_log' => [['note' => 'offered $80', 'at' => now()->toIso8601String()]],
    ]);

    expect($inquiry->status)->toBe(InquiryStatus::Negotiating);
    expect($inquiry->negotiation_log)->toBeArray()->toHaveCount(1);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=InquiryModelTest`
Expected: FAIL — relations / casts not defined.

- [ ] **Step 4: Implement the model**

Replace `app/Models/Inquiry.php`:

```php
<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => InquiryStatus::class,
        'received_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'negotiation_log' => 'array',
        'offered_price_cents' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }
}
```

- [ ] **Step 5: Implement the factory**

Replace `database/factories/InquiryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\InquiryStatus;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    protected $model = Inquiry::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'buyer_id' => Buyer::factory(),
            'message_excerpt' => fake()->sentence(8),
            'status' => InquiryStatus::New->value,
            'offered_price_cents' => fake()->optional()->numberBetween(500, 30000),
            'received_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'last_contact_at' => now()->subHours(fake()->numberBetween(0, 24)),
            'negotiation_log' => null,
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=InquiryModelTest`
Expected: PASS.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Inquiry.php database/factories/InquiryFactory.php tests/Feature/InquiryModelTest.php
git commit -m "feat(models): add Inquiry model and factory"
```

---

## Task 6: Wire `User` and `Item` relations

**Files:**
- Modify: `app/Models/User.php` — add `buyers()`.
- Modify: `app/Models/Item.php` — add `inquiries()`.
- Test: `tests/Feature/InquiryModelTest.php` — extend.

- [ ] **Step 1: Add a failing relation test**

Append to `tests/Feature/InquiryModelTest.php`:

```php
it('exposes inquiries via Item->inquiries() and buyers via User->buyers()', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    Inquiry::factory()->count(2)->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
    ]);

    expect($item->inquiries()->count())->toBe(2);
    expect($user->buyers()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter='InquiryModelTest'`
Expected: FAIL — `Item::inquiries()` and `User::buyers()` undefined.

- [ ] **Step 3: Add the relations**

In `app/Models/User.php`, after the `items()` method (~line 43), add:

```php
    /**
     * @return HasMany<Buyer, $this>
     */
    public function buyers(): HasMany
    {
        return $this->hasMany(Buyer::class);
    }
```

In `app/Models/Item.php`, after the `photos()` method (~line 37), add an import at the top if needed, then:

```php
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class)->latest('received_at');
    }
```

(`HasMany` is already imported in both files; verify before editing.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=InquiryModelTest`
Expected: PASS, 3 tests.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/User.php app/Models/Item.php tests/Feature/InquiryModelTest.php
git commit -m "feat(models): wire User->buyers and Item->inquiries"
```

---

## Task 7: Buyer form requests

**Files:**
- Create: `app/Http/Requests/StoreBuyerRequest.php`
- Create: `app/Http/Requests/UpdateBuyerRequest.php`

- [ ] **Step 1: Generate**

Run:

```bash
php artisan make:request StoreBuyerRequest --no-interaction
php artisan make:request UpdateBuyerRequest --no-interaction
```

- [ ] **Step 2: Fill in `StoreBuyerRequest`**

Replace `app/Http/Requests/StoreBuyerRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'kijiji_handle' => ['nullable', 'string', 'max:128'],
            'trust_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 3: Fill in `UpdateBuyerRequest`**

Identical rules as `StoreBuyerRequest` (the buyer is editable in full from the show page):

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->route('buyer')?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'kijiji_handle' => ['nullable', 'string', 'max:128'],
            'trust_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 4: Pint and commit (no separate test — covered by controller tests in Task 8)**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/StoreBuyerRequest.php app/Http/Requests/UpdateBuyerRequest.php
git commit -m "feat(requests): add Store/UpdateBuyerRequest"
```

---

## Task 8: `BuyerController` + routes + tests

**Files:**
- Create: `app/Http/Controllers/BuyerController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BuyerCrudTest.php`

- [ ] **Step 1: Generate the controller**

Run: `php artisan make:controller BuyerController --no-interaction`

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/BuyerCrudTest.php`:

```php
<?php

use App\Models\Buyer;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('lists only the current user buyers', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Buyer::factory()->count(2)->create(['user_id' => $user->id]);
    Buyer::factory()->create(['user_id' => $other->id]);

    actingAs($user)
        ->get('/buyers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyers/index')
            ->has('buyers', 2)
        );
});

it('creates a buyer scoped to the current user', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post('/buyers', [
            'display_name' => 'Sam',
            'phone' => '555-0100',
        ])
        ->assertRedirect();

    expect(Buyer::query()->where('user_id', $user->id)->where('display_name', 'Sam')->exists())->toBeTrue();
});

it('shows a buyer with their inquiries', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->get("/buyers/{$buyer->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('buyers/show')
            ->where('buyer.id', $buyer->id)
            ->has('inquiries')
        );
});

it('forbids viewing another user buyer', function () {
    $user = User::factory()->create();
    $other = Buyer::factory()->create();

    actingAs($user)
        ->get("/buyers/{$other->id}")
        ->assertForbidden();
});

it('updates a buyer', function () {
    $user = User::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id, 'display_name' => 'Old']);

    actingAs($user)
        ->patch("/buyers/{$buyer->id}", ['display_name' => 'New'])
        ->assertRedirect();

    expect($buyer->fresh()->display_name)->toBe('New');
});

it('forbids updating another user buyer', function () {
    $user = User::factory()->create();
    $other = Buyer::factory()->create();

    actingAs($user)
        ->patch("/buyers/{$other->id}", ['display_name' => 'Hijack'])
        ->assertForbidden();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=BuyerCrudTest`
Expected: FAIL — routes do not exist.

- [ ] **Step 4: Implement the controller**

Replace `app/Http/Controllers/BuyerController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBuyerRequest;
use App\Http\Requests\UpdateBuyerRequest;
use App\Models\Buyer;
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

        return back();
    }
}
```

- [ ] **Step 5: Register routes**

Edit `routes/web.php`. Inside the existing `auth/verified` group (after the items routes):

```php
    Route::get('buyers', [BuyerController::class, 'index'])->name('buyers.index');
    Route::post('buyers', [BuyerController::class, 'store'])->name('buyers.store');
    Route::get('buyers/{buyer}', [BuyerController::class, 'show'])->name('buyers.show');
    Route::patch('buyers/{buyer}', [BuyerController::class, 'update'])->name('buyers.update');
```

Add the import at the top: `use App\Http\Controllers\BuyerController;`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=BuyerCrudTest`
Expected: PASS, 6 tests.

- [ ] **Step 7: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/BuyerController.php routes/web.php tests/Feature/BuyerCrudTest.php
git commit -m "feat(buyers): add Buyer CRUD endpoints"
```

---

## Task 9: Inquiry store endpoint (with quick-create buyer)

**Files:**
- Create: `app/Http/Controllers/InquiryController.php`
- Create: `app/Http/Requests/StoreInquiryRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InquiryStoreTest.php`

- [ ] **Step 1: Generate scaffolding**

Run:

```bash
php artisan make:controller InquiryController --no-interaction
php artisan make:request StoreInquiryRequest --no-interaction
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/InquiryStoreTest.php`:

```php
<?php

use App\Enums\InquiryStatus;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('creates an inquiry against an existing buyer', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/inquiries", [
            'buyer_id' => $buyer->id,
            'message_excerpt' => 'Is it still available?',
            'offered_price_cents' => 4000,
        ])
        ->assertRedirect();

    $inquiry = Inquiry::query()->latest('id')->first();
    expect($inquiry)->not->toBeNull();
    expect($inquiry->item_id)->toBe($item->id);
    expect($inquiry->buyer_id)->toBe($buyer->id);
    expect($inquiry->message_excerpt)->toBe('Is it still available?');
    expect($inquiry->offered_price_cents)->toBe(4000);
    expect($inquiry->status)->toBe(InquiryStatus::New);
    expect($inquiry->received_at)->not->toBeNull();
});

it('quick-creates a buyer when display_name is provided without buyer_id', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/inquiries", [
            'new_buyer' => ['display_name' => 'Jess'],
            'message_excerpt' => 'Will you take $30?',
        ])
        ->assertRedirect();

    $buyer = Buyer::query()->where('user_id', $user->id)->where('display_name', 'Jess')->first();
    expect($buyer)->not->toBeNull();
    expect(Inquiry::query()->where('buyer_id', $buyer->id)->exists())->toBeTrue();
});

it('rejects an inquiry with neither buyer_id nor new_buyer', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$item->id}/inquiries", [
            'message_excerpt' => 'hi',
        ])
        ->assertSessionHasErrors();
});

it('forbids creating an inquiry on another user item', function () {
    $user = User::factory()->create();
    $other = Item::factory()->create();
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->post("/items/{$other->id}/inquiries", [
            'buyer_id' => $buyer->id,
            'message_excerpt' => 'hi',
        ])
        ->assertForbidden();
});

it('forbids creating an inquiry against another user buyer', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $foreignBuyer = Buyer::factory()->create();

    actingAs($user)
        ->post("/items/{$item->id}/inquiries", [
            'buyer_id' => $foreignBuyer->id,
            'message_excerpt' => 'hi',
        ])
        ->assertSessionHasErrors('buyer_id');
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=InquiryStoreTest`
Expected: FAIL — route does not exist.

- [ ] **Step 4: Implement the form request**

Replace `app/Http/Requests/StoreInquiryRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $this->user() !== null
            && $item !== null
            && $item->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'buyer_id' => [
                'required_without:new_buyer',
                'nullable',
                'integer',
                Rule::exists('buyers', 'id')->where('user_id', $this->user()->id),
            ],
            'new_buyer' => ['required_without:buyer_id', 'nullable', 'array'],
            'new_buyer.display_name' => ['required_with:new_buyer', 'string', 'max:255'],
            'new_buyer.phone' => ['nullable', 'string', 'max:64'],
            'new_buyer.email' => ['nullable', 'email', 'max:255'],
            'new_buyer.kijiji_handle' => ['nullable', 'string', 'max:128'],
            'message_excerpt' => ['nullable', 'string', 'max:5000'],
            'offered_price_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

- [ ] **Step 5: Implement the controller**

Replace `app/Http/Controllers/InquiryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\InquiryStatus;
use App\Http\Requests\StoreInquiryRequest;
use App\Models\Inquiry;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;

class InquiryController extends Controller
{
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

        return back();
    }
}
```

- [ ] **Step 6: Register the route**

In `routes/web.php`, inside the `auth/verified` group (after item photo routes):

```php
    Route::post('items/{item}/inquiries', [InquiryController::class, 'store'])->name('inquiries.store');
```

Add `use App\Http\Controllers\InquiryController;` at the top.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=InquiryStoreTest`
Expected: PASS, 5 tests.

> One subtlety: `StoreInquiryRequest::authorize()` returns false when the route's `{item}` belongs to another user, which causes a 403 response. This is what the "forbids creating an inquiry on another user item" test asserts.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/InquiryController.php app/Http/Requests/StoreInquiryRequest.php routes/web.php tests/Feature/InquiryStoreTest.php
git commit -m "feat(inquiries): add inquiry store endpoint with quick-create buyer"
```

---

## Task 10: Inquiry update endpoint (status, offer, negotiation log)

**Files:**
- Modify: `app/Http/Controllers/InquiryController.php`
- Create: `app/Http/Requests/UpdateInquiryRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InquiryUpdateTest.php`

- [ ] **Step 1: Generate the request**

Run: `php artisan make:request UpdateInquiryRequest --no-interaction`

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/InquiryUpdateTest.php`:

```php
<?php

use App\Enums\InquiryStatus;
use App\Models\Buyer;
use App\Models\Inquiry;
use App\Models\Item;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('updates status, offered price, and last_contact_at', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $inquiry = Inquiry::factory()->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
        'status' => InquiryStatus::New->value,
    ]);

    $before = $inquiry->last_contact_at;

    actingAs($user)
        ->patch("/inquiries/{$inquiry->id}", [
            'status' => 'negotiating',
            'offered_price_cents' => 5500,
        ])
        ->assertRedirect();

    $fresh = $inquiry->fresh();
    expect($fresh->status)->toBe(InquiryStatus::Negotiating);
    expect($fresh->offered_price_cents)->toBe(5500);
    expect($fresh->last_contact_at->greaterThan($before))->toBeTrue();
});

it('appends a negotiation note to the log when provided', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    $inquiry = Inquiry::factory()->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
        'negotiation_log' => null,
    ]);

    actingAs($user)
        ->patch("/inquiries/{$inquiry->id}", [
            'negotiation_note' => 'Countered with $80',
        ])
        ->assertRedirect();

    $log = $inquiry->fresh()->negotiation_log;
    expect($log)->toBeArray()->toHaveCount(1);
    expect($log[0]['note'])->toBe('Countered with $80');
    expect($log[0]['at'])->not->toBeEmpty();
});

it('forbids updating another user inquiry', function () {
    $user = User::factory()->create();
    $foreign = Inquiry::factory()->create();

    actingAs($user)
        ->patch("/inquiries/{$foreign->id}", ['status' => 'replied'])
        ->assertForbidden();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=InquiryUpdateTest`
Expected: FAIL — route missing.

- [ ] **Step 4: Implement the form request**

Replace `app/Http/Requests/UpdateInquiryRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\InquiryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inquiry = $this->route('inquiry');

        return $this->user() !== null
            && $inquiry !== null
            && $inquiry->item->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(InquiryStatus::class)],
            'offered_price_cents' => ['nullable', 'integer', 'min:0'],
            'negotiation_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Step 5: Add the controller method**

Add to `app/Http/Controllers/InquiryController.php` (alongside `store`):

```php
    public function update(UpdateInquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $data = $request->validated();
        $changes = ['last_contact_at' => now()];

        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $changes['status'] = $data['status'];
        }

        if (array_key_exists('offered_price_cents', $data)) {
            $changes['offered_price_cents'] = $data['offered_price_cents'];
        }

        if (! empty($data['negotiation_note'])) {
            $log = $inquiry->negotiation_log ?? [];
            $log[] = [
                'note' => $data['negotiation_note'],
                'at' => now()->toIso8601String(),
            ];
            $changes['negotiation_log'] = $log;
        }

        $inquiry->update($changes);

        return back();
    }
```

Also add `use App\Http\Requests\UpdateInquiryRequest;` at the top of the controller.

- [ ] **Step 6: Register the route**

In `routes/web.php` (inside `auth/verified` group, near `inquiries.store`):

```php
    Route::patch('inquiries/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=InquiryUpdateTest`
Expected: PASS, 3 tests.

- [ ] **Step 8: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/InquiryController.php app/Http/Requests/UpdateInquiryRequest.php routes/web.php tests/Feature/InquiryUpdateTest.php
git commit -m "feat(inquiries): add update endpoint with negotiation log"
```

---

## Task 11: Item show controller exposes inquiries, buyers, and reply templates

**Files:**
- Modify: `app/Http/Controllers/ItemController.php` — `show()` method.
- Test: `tests/Feature/ItemShowDraftTest.php` — extend.

- [ ] **Step 1: Add a failing assertion**

Append to `tests/Feature/ItemShowDraftTest.php`:

```php
it('exposes inquiries, buyers, and reply templates on the item show page', function () {
    $user = User::factory()->create();
    $user->settings()->create(['snippets' => [
        'pickup' => '',
        'payment' => '',
        'reply_templates' => [
            ['label' => 'Still available', 'body' => 'Yes, still available!'],
        ],
    ]]);
    $item = Item::factory()->create(['user_id' => $user->id]);
    $buyer = Buyer::factory()->create(['user_id' => $user->id]);
    Inquiry::factory()->count(2)->create([
        'item_id' => $item->id,
        'buyer_id' => $buyer->id,
    ]);

    actingAs($user)
        ->get("/items/{$item->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/show')
            ->has('inquiries', 2)
            ->has('buyers', 1)
            ->has('reply_templates', 1)
        );
});
```

Add the missing `use` lines at the top of the test file: `use App\Models\Buyer;` and `use App\Models\Inquiry;` if not already present.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ItemShowDraftTest`
Expected: FAIL — keys not present in props.

- [ ] **Step 3: Update the controller**

Edit `app/Http/Controllers/ItemController.php` `show()` method:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ItemShowDraftTest`
Expected: PASS for all tests in the file.

- [ ] **Step 5: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/ItemController.php tests/Feature/ItemShowDraftTest.php
git commit -m "feat(items): expose inquiries, buyers, and reply templates on item show"
```

---

## Task 12: `<InquiryTimeline />` component

**Files:**
- Create: `resources/js/components/inquiry-timeline.tsx`

- [ ] **Step 1: Build the component**

Create `resources/js/components/inquiry-timeline.tsx`:

```tsx
import { router } from '@inertiajs/react';
import { useState } from 'react';

type Buyer = { id: number; display_name: string };
type LogEntry = { note: string; at: string };
type Inquiry = {
    id: number;
    buyer: Buyer;
    message_excerpt: string | null;
    status: 'new' | 'replied' | 'negotiating' | 'ghosted' | 'declined';
    offered_price_cents: number | null;
    received_at: string | null;
    last_contact_at: string | null;
    negotiation_log: LogEntry[] | null;
};
type ReplyTemplate = { label: string; body: string };

const STATUSES: Inquiry['status'][] = ['new', 'replied', 'negotiating', 'ghosted', 'declined'];

function CopyButton({ text, label }: { text: string; label: string }) {
    const [state, setState] = useState<'idle' | 'copied' | 'error'>('idle');
    return (
        <button
            type="button"
            onClick={async () => {
                try {
                    if (!navigator.clipboard) throw new Error('no clipboard');
                    await navigator.clipboard.writeText(text);
                    setState('copied');
                } catch {
                    setState('error');
                }
                setTimeout(() => setState('idle'), 1500);
            }}
            className="text-xs border rounded px-2 py-1 hover:bg-zinc-50"
        >
            {state === 'copied' ? 'Copied!' : state === 'error' ? 'Failed' : `Copy ${label}`}
        </button>
    );
}

function InquiryRow({ inquiry, replyTemplates }: { inquiry: Inquiry; replyTemplates: ReplyTemplate[] }) {
    const [note, setNote] = useState('');

    const update = (changes: Record<string, string | number | null>) => {
        router.patch(`/inquiries/${inquiry.id}`, changes, { preserveScroll: true });
    };

    return (
        <li className="border rounded-lg p-3 space-y-2">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <p className="font-medium text-sm">{inquiry.buyer.display_name}</p>
                    {inquiry.received_at && (
                        <p className="text-xs text-zinc-500">
                            {new Date(inquiry.received_at).toLocaleString()}
                        </p>
                    )}
                </div>
                <select
                    value={inquiry.status}
                    onChange={(e) => update({ status: e.target.value })}
                    className="text-xs border rounded px-2 py-1"
                >
                    {STATUSES.map((s) => (
                        <option key={s} value={s}>{s}</option>
                    ))}
                </select>
            </div>

            {inquiry.message_excerpt && (
                <p className="text-sm bg-zinc-50 border rounded p-2 whitespace-pre-wrap">
                    {inquiry.message_excerpt}
                </p>
            )}

            <div className="flex items-center gap-2 text-xs text-zinc-600">
                <span>Offered:</span>
                <input
                    type="number"
                    defaultValue={inquiry.offered_price_cents ?? ''}
                    placeholder="cents"
                    className="border rounded px-2 py-1 w-24"
                    onBlur={(e) => {
                        const v = e.target.value === '' ? null : Number(e.target.value);
                        update({ offered_price_cents: v });
                    }}
                />
            </div>

            {inquiry.negotiation_log && inquiry.negotiation_log.length > 0 && (
                <ul className="text-xs text-zinc-600 space-y-1">
                    {inquiry.negotiation_log.map((entry, i) => (
                        <li key={i}>
                            <span className="text-zinc-400">{new Date(entry.at).toLocaleString()}</span>
                            {' — '}
                            {entry.note}
                        </li>
                    ))}
                </ul>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    if (!note.trim()) return;
                    update({ negotiation_note: note });
                    setNote('');
                }}
                className="flex gap-2"
            >
                <input
                    type="text"
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder="Counter-offer note"
                    className="flex-1 border rounded px-2 py-1 text-xs"
                />
                <button type="submit" className="text-xs border rounded px-2 py-1">Log</button>
            </form>

            {replyTemplates.length > 0 && (
                <div className="flex flex-wrap gap-1 pt-1 border-t">
                    {replyTemplates.map((t) => (
                        <CopyButton key={t.label} text={t.body} label={t.label} />
                    ))}
                </div>
            )}
        </li>
    );
}

export function InquiryTimeline({
    inquiries,
    replyTemplates,
}: {
    inquiries: Inquiry[];
    replyTemplates: ReplyTemplate[];
}) {
    if (inquiries.length === 0) {
        return <p className="text-sm text-zinc-500">No inquiries yet.</p>;
    }

    return (
        <ul className="space-y-2">
            {inquiries.map((inq) => (
                <InquiryRow key={inq.id} inquiry={inq} replyTemplates={replyTemplates} />
            ))}
        </ul>
    );
}
```

- [ ] **Step 2: Type-check**

Run: `npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/inquiry-timeline.tsx
git commit -m "feat(ui): add InquiryTimeline component"
```

---

## Task 13: `<InquiryForm />` component + render on item show

**Files:**
- Create: `resources/js/components/inquiry-form.tsx`
- Modify: `resources/js/pages/items/show.tsx`

- [ ] **Step 1: Build the form**

Create `resources/js/components/inquiry-form.tsx`:

```tsx
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

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
        form.transform(() => payload).post(`/items/${itemId}/inquiries`, {
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
                <select
                    value={form.data.buyer_id ?? ''}
                    onChange={(e) => form.setData('buyer_id', Number(e.target.value))}
                    className="w-full border rounded px-2 py-1 text-sm"
                >
                    {buyers.map((b) => (
                        <option key={b.id} value={b.id}>{b.display_name}</option>
                    ))}
                </select>
            ) : (
                <div className="space-y-2">
                    <input
                        type="text"
                        placeholder="Display name"
                        value={form.data.new_buyer.display_name}
                        onChange={(e) =>
                            form.setData('new_buyer', { ...form.data.new_buyer, display_name: e.target.value })
                        }
                        className="w-full border rounded px-2 py-1 text-sm"
                    />
                    <input
                        type="text"
                        placeholder="Phone (optional)"
                        value={form.data.new_buyer.phone}
                        onChange={(e) =>
                            form.setData('new_buyer', { ...form.data.new_buyer, phone: e.target.value })
                        }
                        className="w-full border rounded px-2 py-1 text-sm"
                    />
                </div>
            )}

            <textarea
                value={form.data.message_excerpt}
                onChange={(e) => form.setData('message_excerpt', e.target.value)}
                placeholder="Paste their message"
                rows={3}
                className="w-full border rounded px-2 py-1 text-sm"
            />

            <input
                type="number"
                value={form.data.offered_price_cents}
                onChange={(e) => form.setData('offered_price_cents', e.target.value === '' ? '' : Number(e.target.value))}
                placeholder="Offered (cents)"
                className="w-full border rounded px-2 py-1 text-sm"
            />

            <button
                type="submit"
                disabled={form.processing}
                className="bg-black text-white px-3 py-1.5 rounded text-sm"
            >
                Log inquiry
            </button>
        </form>
    );
}
```

- [ ] **Step 2: Render on item show**

Edit `resources/js/pages/items/show.tsx`:

1. Add imports near the top:

```tsx
import { InquiryTimeline } from '@/components/inquiry-timeline';
import { InquiryForm } from '@/components/inquiry-form';
```

2. Extend the props type to include the three new fields:

```tsx
type Buyer = { id: number; display_name: string };
type ReplyTemplate = { label: string; body: string };
type Inquiry = Parameters<typeof InquiryTimeline>[0]['inquiries'][number];
```

3. Update the `ItemsShow` signature and body to receive and render the new props. Replace the existing default export's signature and the JSX after `<TransitionControls />`:

```tsx
export default function ItemsShow({
    item,
    listing_draft,
    inquiries,
    buyers,
    reply_templates,
}: {
    item: Item;
    listing_draft: ListingDraft;
    inquiries: Inquiry[];
    buyers: Buyer[];
    reply_templates: ReplyTemplate[];
}) {
```

Add this section just before the trailing `<Link href={...}>Edit item</Link>`:

```tsx
                <section className="space-y-3">
                    <h2 className="font-medium">Inquiries</h2>
                    <InquiryForm itemId={item.id} buyers={buyers} />
                    <InquiryTimeline inquiries={inquiries} replyTemplates={reply_templates} />
                </section>
```

- [ ] **Step 3: Type-check**

Run: `npx tsc --noEmit`
Expected: no errors.

- [ ] **Step 4: Smoke-test in the browser**

Run: `composer run dev` (or, if already running, just refresh).
Visit: an existing item's `/items/{id}` page. Confirm the inquiries section renders with an empty state and the form. Submit a new inquiry with a new buyer; confirm it appears.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/inquiry-form.tsx resources/js/pages/items/show.tsx
git commit -m "feat(ui): render inquiry timeline and form on item show"
```

---

## Task 14: Buyers index page

**Files:**
- Create: `resources/js/pages/buyers/index.tsx`

- [ ] **Step 1: Build the page**

Create `resources/js/pages/buyers/index.tsx`:

```tsx
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Buyer = {
    id: number;
    display_name: string;
    phone: string | null;
    email: string | null;
    kijiji_handle: string | null;
    inquiries_count: number;
    updated_at: string;
};

export default function BuyersIndex({ buyers }: { buyers: Buyer[] }) {
    const [showForm, setShowForm] = useState(false);
    const form = useForm({ display_name: '', phone: '', email: '', kijiji_handle: '' });

    return (
        <>
            <Head title="Buyers" />
            <div className="p-6 space-y-4 max-w-3xl">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Buyers</h1>
                    <button
                        type="button"
                        onClick={() => setShowForm((v) => !v)}
                        className="bg-black text-white px-3 py-1.5 rounded text-sm"
                    >
                        {showForm ? 'Cancel' : 'New buyer'}
                    </button>
                </div>

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
                        <input
                            type="text"
                            placeholder="Display name"
                            value={form.data.display_name}
                            onChange={(e) => form.setData('display_name', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <input
                            type="email"
                            placeholder="Email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Kijiji handle"
                            value={form.data.kijiji_handle}
                            onChange={(e) => form.setData('kijiji_handle', e.target.value)}
                            className="w-full border rounded px-2 py-1 text-sm"
                        />
                        <button type="submit" className="bg-black text-white px-3 py-1.5 rounded text-sm">
                            Save
                        </button>
                    </form>
                )}

                <ul className="space-y-2">
                    {buyers.map((b) => (
                        <li key={b.id} className="border rounded-lg p-3 flex items-center justify-between">
                            <div>
                                <Link href={`/buyers/${b.id}`} className="font-medium underline">
                                    {b.display_name}
                                </Link>
                                <p className="text-xs text-zinc-500">
                                    {b.kijiji_handle ?? b.email ?? b.phone ?? '—'}
                                </p>
                            </div>
                            <span className="text-xs text-zinc-600">
                                {b.inquiries_count} inquir{b.inquiries_count === 1 ? 'y' : 'ies'}
                            </span>
                        </li>
                    ))}
                </ul>

                {buyers.length === 0 && <p className="text-sm text-zinc-500">No buyers yet.</p>}
            </div>
        </>
    );
}

BuyersIndex.layout = {
    breadcrumbs: [{ title: 'Buyers', href: '/buyers' }],
};
```

- [ ] **Step 2: Verify the index test now renders the page**

Run: `php artisan test --compact --filter=BuyerCrudTest`
Expected: all 6 tests still pass (the Inertia component name `buyers/index` now resolves to a real file).

- [ ] **Step 3: Type-check + smoke**

Run: `npx tsc --noEmit`. Visit `/buyers`. Confirm the page renders.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/buyers/index.tsx
git commit -m "feat(ui): add buyers index page"
```

---

## Task 15: Buyers show page

**Files:**
- Create: `resources/js/pages/buyers/show.tsx`

- [ ] **Step 1: Build the page**

Create `resources/js/pages/buyers/show.tsx`:

```tsx
import { Head, Link, useForm } from '@inertiajs/react';

type Item = { id: number; title: string };
type Inquiry = {
    id: number;
    item: Item;
    message_excerpt: string | null;
    status: string;
    offered_price_cents: number | null;
    received_at: string | null;
};
type Buyer = {
    id: number;
    display_name: string;
    phone: string | null;
    email: string | null;
    kijiji_handle: string | null;
    trust_notes: string | null;
};

export default function BuyersShow({ buyer, inquiries }: { buyer: Buyer; inquiries: Inquiry[] }) {
    const form = useForm({
        display_name: buyer.display_name,
        phone: buyer.phone ?? '',
        email: buyer.email ?? '',
        kijiji_handle: buyer.kijiji_handle ?? '',
        trust_notes: buyer.trust_notes ?? '',
    });

    return (
        <>
            <Head title={buyer.display_name} />
            <div className="p-6 max-w-3xl space-y-6">
                <h1 className="text-2xl font-semibold">{buyer.display_name}</h1>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(`/buyers/${buyer.id}`, { preserveScroll: true });
                    }}
                    className="border rounded-lg p-4 space-y-2"
                >
                    <h2 className="font-medium text-sm">Edit details</h2>
                    <input
                        type="text"
                        value={form.data.display_name}
                        onChange={(e) => form.setData('display_name', e.target.value)}
                        className="w-full border rounded px-2 py-1 text-sm"
                    />
                    <input
                        type="text"
                        placeholder="Phone"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        className="w-full border rounded px-2 py-1 text-sm"
                    />
                    <input
                        type="email"
                        placeholder="Email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        className="w-full border rounded px-2 py-1 text-sm"
                    />
                    <input
                        type="text"
                        placeholder="Kijiji handle"
                        value={form.data.kijiji_handle}
                        onChange={(e) => form.setData('kijiji_handle', e.target.value)}
                        className="w-full border rounded px-2 py-1 text-sm"
                    />
                    <textarea
                        rows={2}
                        placeholder="Trust notes (private)"
                        value={form.data.trust_notes}
                        onChange={(e) => form.setData('trust_notes', e.target.value)}
                        className="w-full border rounded px-2 py-1 text-sm"
                    />
                    <button type="submit" className="bg-black text-white px-3 py-1.5 rounded text-sm">
                        Save
                    </button>
                </form>

                <section>
                    <h2 className="font-medium mb-2">History</h2>
                    {inquiries.length === 0 ? (
                        <p className="text-sm text-zinc-500">No inquiries yet.</p>
                    ) : (
                        <ul className="space-y-2">
                            {inquiries.map((i) => (
                                <li key={i.id} className="border rounded-lg p-3">
                                    <div className="flex justify-between items-baseline">
                                        <Link href={`/items/${i.item.id}`} className="font-medium underline">
                                            {i.item.title}
                                        </Link>
                                        <span className="text-xs uppercase tracking-wide text-zinc-500">
                                            {i.status}
                                        </span>
                                    </div>
                                    {i.message_excerpt && (
                                        <p className="text-sm text-zinc-600 mt-1 whitespace-pre-wrap">
                                            {i.message_excerpt}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

BuyersShow.layout = {
    breadcrumbs: [
        { title: 'Buyers', href: '/buyers' },
    ],
};
```

- [ ] **Step 2: Type-check + smoke**

Run: `npx tsc --noEmit`. Visit `/buyers/{id}`. Confirm rendering.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/buyers/show.tsx
git commit -m "feat(ui): add buyers show page"
```

---

## Task 16: Reply templates — backend

**Files:**
- Modify: `app/Http/Requests/Settings/SnippetsUpdateRequest.php`
- Modify: `app/Http/Controllers/Settings/SnippetsController.php`
- Test: `tests/Feature/SnippetsTest.php` — extend.

- [ ] **Step 1: Add a failing test**

Append to `tests/Feature/SnippetsTest.php`:

```php
it('saves and returns reply templates', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch('/settings/snippets', [
            'pickup' => 'Front door',
            'payment' => 'E-transfer',
            'reply_templates' => [
                ['label' => 'Still available', 'body' => 'Yes — still available!'],
                ['label' => 'Lowest', 'body' => 'Lowest is $X.'],
            ],
        ])
        ->assertRedirect('/settings/snippets');

    actingAs($user)
        ->get('/settings/snippets')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/snippets')
            ->where('snippets.reply_templates.0.label', 'Still available')
            ->where('snippets.reply_templates.1.body', 'Lowest is $X.')
        );
});
```

(Add `use App\Models\User;` and `use function Pest\Laravel\actingAs;` if not already present.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SnippetsTest`
Expected: FAIL — `reply_templates` is dropped by validation, so the GET response misses it.

- [ ] **Step 3: Update the form request**

Replace `app/Http/Requests/Settings/SnippetsUpdateRequest.php`:

```php
<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class SnippetsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'pickup' => ['present', 'string', 'max:1000'],
            'payment' => ['present', 'string', 'max:1000'],
            'reply_templates' => ['array', 'max:20'],
            'reply_templates.*.label' => ['required_with:reply_templates.*', 'string', 'max:64'],
            'reply_templates.*.body' => ['required_with:reply_templates.*', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pickup' => (string) $this->input('pickup', ''),
            'payment' => (string) $this->input('payment', ''),
            'reply_templates' => $this->input('reply_templates', []),
        ]);
    }
}
```

- [ ] **Step 4: Update the controller's `edit()` payload**

Edit `app/Http/Controllers/Settings/SnippetsController.php`:

```php
    public function edit(Request $request): Response
    {
        $stored = $request->user()->settings?->snippets ?? [];

        return Inertia::render('settings/snippets', [
            'snippets' => [
                'pickup' => $stored['pickup'] ?? '',
                'payment' => $stored['payment'] ?? '',
                'reply_templates' => $stored['reply_templates'] ?? [],
            ],
        ]);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=SnippetsTest`
Expected: PASS, including all prior tests.

- [ ] **Step 6: Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Settings/SnippetsUpdateRequest.php app/Http/Controllers/Settings/SnippetsController.php tests/Feature/SnippetsTest.php
git commit -m "feat(snippets): support reply templates in settings"
```

---

## Task 17: Reply templates — settings UI

**Files:**
- Modify: `resources/js/pages/settings/snippets.tsx`

- [ ] **Step 1: Inspect the existing file**

Open `resources/js/pages/settings/snippets.tsx` to learn its current structure. The page renders a form keyed by `pickup` and `payment`. Add a `reply_templates` section that mirrors that structure: an array of `{label, body}` rows with add/remove buttons.

- [ ] **Step 2: Update the page**

Add or update the page so the form's data shape includes:

```tsx
type ReplyTemplate = { label: string; body: string };

type SnippetsForm = {
    pickup: string;
    payment: string;
    reply_templates: ReplyTemplate[];
};
```

Render — below the existing `pickup`/`payment` textareas and above the submit button — a "Reply templates" section:

```tsx
                <section className="space-y-2 border-t pt-4">
                    <div className="flex items-center justify-between">
                        <h2 className="font-medium">Reply templates</h2>
                        <button
                            type="button"
                            onClick={() =>
                                form.setData('reply_templates', [
                                    ...form.data.reply_templates,
                                    { label: '', body: '' },
                                ])
                            }
                            className="text-xs border rounded px-2 py-1"
                        >
                            Add template
                        </button>
                    </div>

                    {form.data.reply_templates.map((tpl, i) => (
                        <div key={i} className="border rounded-lg p-3 space-y-2">
                            <input
                                type="text"
                                placeholder="Label"
                                value={tpl.label}
                                onChange={(e) => {
                                    const next = [...form.data.reply_templates];
                                    next[i] = { ...next[i], label: e.target.value };
                                    form.setData('reply_templates', next);
                                }}
                                className="w-full border rounded px-2 py-1 text-sm"
                            />
                            <textarea
                                rows={2}
                                placeholder="Body"
                                value={tpl.body}
                                onChange={(e) => {
                                    const next = [...form.data.reply_templates];
                                    next[i] = { ...next[i], body: e.target.value };
                                    form.setData('reply_templates', next);
                                }}
                                className="w-full border rounded px-2 py-1 text-sm"
                            />
                            <button
                                type="button"
                                onClick={() =>
                                    form.setData(
                                        'reply_templates',
                                        form.data.reply_templates.filter((_, j) => j !== i),
                                    )
                                }
                                className="text-xs text-rose-700 underline"
                            >
                                Remove
                            </button>
                        </div>
                    ))}
                </section>
```

When initializing `useForm`, seed `reply_templates` from the page prop (`snippets.reply_templates ?? []`).

> Don't change the file beyond this. The existing `pickup` / `payment` textareas, layout export, and submit handler stay as they are — only the form's data shape and the new section are additive.

- [ ] **Step 3: Type-check + smoke**

Run: `npx tsc --noEmit`. Visit `/settings/snippets`. Add two templates, save, reload — confirm they persist.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/settings/snippets.tsx
git commit -m "feat(ui): edit reply templates in settings"
```

---

## Task 18: End-to-end verification

**Files:**
- None (verification only).

- [ ] **Step 1: Run the full suite**

Run: `php artisan test --compact`
Expected: all tests pass; new files contributed: `InquiryStatusTest`, `BuyerModelTest`, `InquiryModelTest`, `BuyerCrudTest`, `InquiryStoreTest`, `InquiryUpdateTest`. Existing `ItemShowDraftTest` and `SnippetsTest` extended.

- [ ] **Step 2: Frontend type-check + lint**

Run: `npx tsc --noEmit && npx eslint resources/js`
Expected: clean.

- [ ] **Step 3: Manual smoke test**

With the dev server running:

1. Create an item (or reuse an existing one).
2. From the item page, click "New buyer", fill in a display name, paste a message, log the inquiry.
3. In the timeline, change the inquiry's status to "negotiating", set an offered price, log a counter-offer note.
4. Visit `/buyers` — the new buyer appears with `1 inquiry`.
5. Click into the buyer — confirm the buyer detail page lists the inquiry.
6. Visit `/settings/snippets`, add a reply template, save.
7. Back on the item page, the new template appears as a "Copy" button under the inquiry row.

- [ ] **Step 4: Confirm Pint is clean**

Run: `vendor/bin/pint --test --format agent`
Expected: "OK".

> If `pint --test` reports issues, run `vendor/bin/pint --dirty --format agent` and amend the most recent commit only if the fixes are purely formatting. Otherwise, fix and commit separately.

---

## Self-review (already applied)

- **Spec coverage:** Buyer CRUD ✓ (Task 7-8), Inquiry timeline ✓ (Task 12), Quick reply templates stored in `user_settings.snippets` ✓ (Tasks 16-17), Negotiation tracker ✓ (Task 10/12), Buyer history view ✓ (Task 15), Inquiry status enum without state-machine wiring ✓ (Task 1, plan §3 confirms it's informational only).
- **Placeholders:** none — every step has concrete code or commands.
- **Type consistency:** `Inquiry`, `Buyer`, `ReplyTemplate` shapes are stable across `inquiry-timeline.tsx`, `inquiry-form.tsx`, `buyers/show.tsx`, and the `items/show` page; column names match the migration in Task 4 (`negotiation_log`, `offered_price_cents`, `last_contact_at`); enum string values match `InquiryStatus` in Task 1.
- **Out of scope (deliberate):** No `Pickup` model, no `PickupObserver`, no `reserved/sold` automation. Phase 4 will pick those up.
