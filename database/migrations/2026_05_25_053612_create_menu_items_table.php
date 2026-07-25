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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('menu_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('menu_items')
                ->nullOnDelete();

            $table->string('title');
            $table->boolean('status')->default(1);

            // link system
            $table->string('type');
            // page | category | external | route

            $table->string('view_type')->nullable();
            $table->string('badge')->nullable();
            // mega_tabs | mega_list | dropdown

            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('url')->nullable();

            $table->integer('sort')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
