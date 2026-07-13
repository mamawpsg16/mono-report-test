<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prospects = leads a rep is chasing before they're a real Customer. On
     * "convert" we create a Customer and stamp converted_customer_id here,
     * keeping the prospect row (and its future visit history) intact -- the
     * Salesforce Lead->Contact pattern, not a destructive rename.
     */
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            // The owning rep; drives Prospect::scopeVisibleTo. nullOnDelete so
            // deleting a user doesn't cascade-delete their prospects.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Set once, on convert. nullOnDelete: deleting the customer doesn't
            // erase the fact that this prospect existed.
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
