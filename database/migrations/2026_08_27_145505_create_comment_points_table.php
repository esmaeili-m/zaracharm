<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('comment_id')
                ->constrained('comments')
                ->cascadeOnDelete();

            $table->enum('type', [
                'positive',
                'negative',
            ]);

            $table->string('body');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_points');
    }
};
