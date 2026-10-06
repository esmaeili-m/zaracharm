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
        Schema::create('social_links', function (Blueprint $table) {
            $table->id();

            $table->string('name');              // Instagram
            $table->string('slug')->unique();    // instagram
            $table->string('url');               // لینک شبکه اجتماعی
            $table->string('icon')->nullable();  // مسیر تصویر آیکون

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
