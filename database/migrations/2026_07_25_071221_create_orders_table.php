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
        Schema::create('orders', function (Blueprint $table) {


            $table->id();


            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();



            $table->string('order_number')
                ->unique();



            $table->foreignId('address_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();



            $table->enum('status',[

                'pending',
                'processing',
                'shipped',
                'completed',
                'cancelled'

            ])
                ->default('pending');



            $table->enum('payment_status',[

                'unpaid',
                'pending',
                'paid',
                'failed',
                'refunded'

            ])
                ->default('unpaid');




            $table->unsignedBigInteger('subtotal');



            $table->unsignedBigInteger('discount_amount')
                ->default(0);



            $table->unsignedBigInteger('shipping_amount')
                ->default(0);



            $table->unsignedBigInteger('total_amount');



            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
