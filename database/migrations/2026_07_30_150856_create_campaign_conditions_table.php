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
        Schema::create('campaign_conditions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')
                ->constrained()
                ->cascadeOnDelete();

            // نوع شرط
            // 0 => Min Purchase
            // 1 => Max Purchase
            // 2 => User Role
            // 3 => First Purchase
            // 4 => Product Count
            $table->tinyInteger('condition_type');

            // مقدار شرط
            $table->string('operator')->default('=');

            $table->string('value');
            $table->boolean('status')
                ->default(true);
            $table->timestamps();

            $table->index('condition_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_conditions');
    }
};
