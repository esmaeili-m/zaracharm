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
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->morphs('mediable');

            $table->string('collection')->nullable();

            $table->string('name')->nullable();

            $table->string('file_path')->nullable();

            $table->string('external_url')->nullable();


            $table->string('disk')->default('public');

            $table->string('mime_type')->nullable();

            $table->string('extension')->nullable();

            $table->unsignedBigInteger('size')->default(0);

            $table->string('type')->default('other');

            $table->boolean('is_private')->default(false);

            $table->integer('sort')->default(0);

            $table->string('purpose')->nullable()->index();

            $table->longText('meta')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
