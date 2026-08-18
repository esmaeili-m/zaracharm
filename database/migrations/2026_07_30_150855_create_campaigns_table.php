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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            // discount, free_shipping, gift, cashback, ...
            $table->tinyInteger('type');

            // draft, active, expired
            $table->tinyInteger('status')->default(0);

            $table->integer('priority')->default(0);

            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index(['start_at', 'end_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
