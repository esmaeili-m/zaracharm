<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ویژگی‌های هر دسته‌بندی => فیلترهای صفحه دسته‌بندی
 *
 * category_attributes : ویژگی‌های تعریف‌شده برای دسته (به زیر‌دسته‌ها ارث می‌رسد)
 *    attribute_type = spec  (مشخصه فنی: جنس، حافظه، ...)  | option (ویژگی قیمت‌ساز: رنگ، سایز، ...)
 * specifications.filter_type : نحوه نمایش فیلتر (null = خودکار بر اساس نوع داده)
 * options.display_type       : color | buttons | select (null = خودکار)
 * option_values.color_code   : کد رنگ برای انتخابگر رنگ (#RRGGBB)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('attribute_type', 10);            // spec | option
            $table->unsignedBigInteger('attribute_id');
            $table->boolean('is_filter')->default(true);       // نمایش به‌عنوان فیلتر
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'attribute_type', 'attribute_id'], 'category_attribute_unique');
            $table->index(['attribute_type', 'attribute_id']);
        });

        Schema::table('specifications', function (Blueprint $table) {
            $table->string('filter_type', 20)->nullable()->after('is_filterable');
        });

        Schema::table('options', function (Blueprint $table) {
            $table->string('display_type', 20)->nullable()->after('slug');
        });

        Schema::table('option_values', function (Blueprint $table) {
            $table->string('color_code', 9)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('option_values', fn (Blueprint $table) => $table->dropColumn('color_code'));
        Schema::table('options', fn (Blueprint $table) => $table->dropColumn('display_type'));
        Schema::table('specifications', fn (Blueprint $table) => $table->dropColumn('filter_type'));
        Schema::dropIfExists('category_attributes');
    }
};
