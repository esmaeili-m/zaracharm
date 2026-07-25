<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {

            $table->id();

            // Product
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            // Specification
            $table->foreignId('specification_id')
                ->constrained()
                ->cascadeOnDelete();

            // Text Value
            $table->text('text_value')->nullable();

            // Integer Value
            $table->bigInteger('number_value')->nullable();

            // Decimal Value
            $table->decimal('decimal_value',12,2)->nullable();

            // Boolean Value
            $table->boolean('boolean_value')->nullable();

            // Date Value
            $table->date('date_value')->nullable();

            // Active / Inactive
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->unique([
                'product_id',
                'specification_id'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
