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
        Schema::create('withdrawal_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('iban', 26);

            $table->string('account_holder_name');

            $table->boolean('is_verified')
                ->default(false);

            $table->boolean('is_default')
                ->default(false);

            $table->timestamp('verified_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'is_verified',
            ]);

            $table->index([
                'user_id',
                'is_default',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_accounts');
    }
};
