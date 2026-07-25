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
        Schema::create('shipments', function (Blueprint $table) {


            $table->id();


            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();



            $table->enum('method',[

                'post',
                'courier',
                'pickup'

            ]);



            $table->string('carrier')
                ->nullable();



            $table->string('tracking_code')
                ->nullable();



            $table->enum('status',[

                'waiting',
                'sent',
                'delivered'

            ])
                ->default('waiting');



            $table->timestamp('sent_at')
                ->nullable();


            $table->timestamp('delivered_at')
                ->nullable();



            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
