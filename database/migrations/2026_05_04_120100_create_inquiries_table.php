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
