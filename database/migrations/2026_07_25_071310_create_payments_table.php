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
        Schema::create('payments', function (Blueprint $table) {


            $table->id();


            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();



            $table->enum('method',[

                'wallet',
                'gateway',
                'installment'

            ]);



            // مثلا zarinpal یا digipay

            $table->string('gateway')
                ->nullable();



            $table->string('transaction_id')
                ->nullable();



            $table->unsignedBigInteger('amount');



            $table->enum('status',[

                'pending',
                'success',
                'failed'

            ])
                ->default('pending');



            // پاسخ درگاه
            $table->json('gateway_response')
                ->nullable();



            $table->timestamp('paid_at')
                ->nullable();



            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
