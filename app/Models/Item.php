<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => ItemStatus::class,
        'condition' => ItemCondition::class,
        'listed_at' => 'datetime',
        'sold_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'asking_price_cents' => 'integer',
        'floor_price_cents' => 'integer',
    ];

    public const STALE_DAYS_LISTED = 14;

    public const STALE_INQUIRY_WINDOW_DAYS = 7;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ItemPhoto::class)->orderBy('position');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class)->latest('received_at');
    }

    public function pickups(): BelongsToMany
    {
        return $this->belongsToMany(Pickup::class, 'pickup_items')
            ->withPivot('agreed_price_cents')
            ->withTimestamps();
    }

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

    public function scopeStale(Builder $query): Builder
    {
        return $query
            ->where('status', ItemStatus::Listed->value)
            ->where('listed_at', '<=', now()->subDays(self::STALE_DAYS_LISTED))
            ->whereDoesntHave('inquiries', function (Builder $q) {
                $q->where('last_contact_at', '>=', now()->subDays(self::STALE_INQUIRY_WINDOW_DAYS));
            });
    }
}
