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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();

            // فیلدهای اصلی
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('short_description', 500)->nullable();
            // فیلدهای وضعیت و ترتیب
            $table->boolean('status')->default(true);
            $table->integer('sort')->default(1);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort','title']);
            $table->index('parent_id');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
