<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverages', function (Blueprint $table) {
            $table->id();
            // the rep being covered (keeps ownership) and the stand-in
            $table->foreignId('representative_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('covering_representative_id')->constrained('users')->cascadeOnDelete();
            // null = the whole book; set = just this one customer
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // the visibility scope filters on the covering rep + the window
            $table->index('covering_representative_id');
            $table->index('representative_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverages');
    }
};
