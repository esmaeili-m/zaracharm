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
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();

            $table->morphs('seoable');

            // SEO اصلی
            $table->string('title', 70)->nullable();
            $table->string('description', 160)->nullable();
            $table->text('keywords')->nullable();

            // Canonical
            $table->string('canonical_url')->nullable();

            // Robots
            $table->boolean('no_index')->default(false);
            $table->boolean('no_follow')->default(false);
            $table->boolean('no_archive')->default(false);

            // Open Graph
            $table->string('og_title')->nullable();
            $table->string('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('og_type')->default('website');

            // Twitter
            $table->string('twitter_title')->nullable();
            $table->string('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();
            $table->string('twitter_card')->default('summary_large_image');

            // Structured Data
            $table->json('schema')->nullable();

            // Sitemap
            $table->decimal('priority', 2, 1)->default(0.5);

            $table->enum('changefreq', [
                'always',
                'hourly',
                'daily',
                'weekly',
                'monthly',
                'yearly',
                'never',
            ])->default('weekly');

            $table->timestamps();

            $table->unique([
                'seoable_type',
                'seoable_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_metas');
    }
};
