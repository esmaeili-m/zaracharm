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
        Schema::create('page_rows', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();

            $table->foreignId('page_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('sort')->default(0);

            // فاصله بین ستون‌ها
            $table->unsignedTinyInteger('gap')->default(4);

            // فاصله بالا و پایین
            $table->unsignedTinyInteger('padding_top')->default(0);

            $table->unsignedTinyInteger('padding_bottom')->default(0);

            // Container یا Full Width
            $table->enum('container', [
                'boxed',
                'full'
            ])->default('boxed');

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_rows');
    }
};
