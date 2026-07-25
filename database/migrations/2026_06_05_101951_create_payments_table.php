<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('gateway');

            $table->string('authority')->nullable();

            $table->string('ref_id')->nullable();

            $table->unsignedBigInteger('amount');

            $table->enum('status', [
                'pending',
                'success',
                'failed'
            ])->default('pending');

            $table->json('response')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
