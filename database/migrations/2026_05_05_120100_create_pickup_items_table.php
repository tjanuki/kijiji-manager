<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('agreed_price_cents');
            $table->timestamps();

            $table->unique(['pickup_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_items');
    }
};
