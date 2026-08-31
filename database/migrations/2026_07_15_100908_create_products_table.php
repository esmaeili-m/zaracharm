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
        Schema::create('products', function (Blueprint $table) {

            $table->id();

            // Brand owner
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Product title
            $table->string('title');
            $table->string('barcode')->nullable();

            // SEO friendly url
            $table->string('slug')->unique();

            // Short description
            $table->text('short_description')->nullable();

            // Full description
            $table->longText('description')->nullable();

            // Product type
            // Physical / Digital / Service
            $table->tinyInteger('type')->default(1);

            // Can users buy this product?
            $table->boolean('status')->default(true);

            // Display order
            $table->unsignedInteger('sort')->default(0);

            // Publish datetime
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
