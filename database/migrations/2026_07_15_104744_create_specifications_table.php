<?php

use App\Enums\SpecificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specifications', function (Blueprint $table) {

            $table->id();

            // Title
            $table->string('title');

            // Unique slug
            $table->string('slug')->unique();

            // Data type
            $table->unsignedTinyInteger('type')
                ->default(SpecificationType::Text->value);

            // Can be used in product filters?
            $table->boolean('is_filterable')->default(false);

            // Show on product page?
            $table->boolean('is_visible')->default(true);

            // Display order
            $table->unsignedInteger('sort')->default(0);

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->index(['status', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specifications');
    }
};
