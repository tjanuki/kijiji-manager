<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Enums\ItemStatus;
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
}
