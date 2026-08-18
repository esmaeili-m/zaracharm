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
        Schema::create('campaign_targets', function (Blueprint $table) {

            $table->id();

            $table->foreignId('campaign_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('target_type');

            $table->unsignedBigInteger('target_id')->nullable();

            $table->timestamps();

            $table->index([
                'campaign_id',
                'target_type',
            ]);

            $table->index([
                'target_type',
                'target_id',
            ]);

            $table->unique([
                'campaign_id',
                'target_type',
                'target_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_targets');
    }
};
