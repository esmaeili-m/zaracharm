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
        Schema::create('stories', function (Blueprint $table) {
            $table->id();

            // image, video, ...
            $table->string('type');

            // عنوان یا نام نمایش داده شده روی استوری
            $table->string('user');

            // تصویر آواتار
            $table->string('avatar')->nullable();

            // آدرس تصویر یا ویدیوی استوری
            $table->string('url');

            // مدت نمایش بر حسب میلی‌ثانیه
            $table->unsignedInteger('duration')->default(5000);

            // لینک مقصد در صورت کلیک
            $table->string('link')->nullable();

            $table->boolean('status')->default(true);

            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
