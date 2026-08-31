<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_slot_id')
                ->nullable()
                ->after('address_id')
                ->constrained('shipping_slots')
                ->nullOnDelete();

            $table->enum('payment_method', ['gateway', 'cod', 'transfer', 'wallet'])
                ->default('gateway')
                ->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_slot_id');
            $table->dropColumn('payment_method');
        });
    }
};
