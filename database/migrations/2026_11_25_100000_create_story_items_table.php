<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هر استوری چند آیتم (تصویر یا ویدیو) با عنوان و توضیح اختیاری دارد.
 * فایل قبلی هر استوری (media با collection = url) به اولین آیتم آن منتقل می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('image');       // image | video
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('duration')->default(7000); // مدت نمایش تصویر (میلی‌ثانیه)
            $table->string('link', 500)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['story_id', 'sort']);
        });

        // ستون‌های تک‌فایلی قدیمی اختیاری می‌شوند (داده به آیتم‌ها منتقل شده است)
        Schema::table('stories', function (Blueprint $table) {
            $table->string('type')->nullable()->change();
            $table->string('url')->nullable()->change();
        });

        $now = now();

        DB::table('stories')->orderBy('id')->each(function ($story) use ($now) {
            $media = DB::table('media')
                ->where('mediable_type', 'App\\Models\\Story')
                ->where('mediable_id', $story->id)
                ->where('collection', 'url')
                ->orderBy('id')
                ->first();

            if (! $media) {
                return;
            }

            $itemId = DB::table('story_items')->insertGetId([
                'story_id' => $story->id,
                'type' => str_starts_with((string) $media->mime_type, 'video') ? 'video' : ($story->type ?: 'image'),
                'duration' => $story->duration ?: 7000,
                'link' => $story->link,
                'sort' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('media')->where('id', $media->id)->update([
                'mediable_type' => 'App\\Models\\StoryItem',
                'mediable_id' => $itemId,
                'collection' => 'story_media',
            ]);
        });
    }

    public function down(): void
    {
        DB::table('story_items')->orderBy('id')->each(function ($item) {
            DB::table('media')
                ->where('mediable_type', 'App\\Models\\StoryItem')
                ->where('mediable_id', $item->id)
                ->update(['mediable_type' => 'App\\Models\\Story', 'mediable_id' => $item->story_id, 'collection' => 'url']);
        });

        Schema::dropIfExists('story_items');
    }
};
