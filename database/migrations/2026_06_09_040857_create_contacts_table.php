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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // اطلاعات فرستنده
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();

            // موضوع و پیام
            $table->string('subject')->nullable();
            $table->text('message');

            // نوع درخواست
            $table->string('type')->default('contact');
            // contact, support, consultation, complaint, cooperation

            // وضعیت رسیدگی
            $table->enum('status', [
                'new',
                'in_progress',
                'answered',
                'closed'
            ])->default('new');

            // پاسخ ادمین
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();

            // اطلاعات تکمیلی
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
