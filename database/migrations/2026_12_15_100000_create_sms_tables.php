<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مدیریت پیامک (ملی پیامک)
 *
 * - sms_templates            : الگوهای خط خدماتی اشتراکی (BodyID ملی پیامک + وضعیت خام و داخلی)
 * - sms_contacts             : مخاطبان دستی (جدا از کاربران سایت؛ اتصال اختیاری با user_id)
 * - sms_contact_lists(+members)
 * - sms_suppressions         : شماره‌های مسدود / انصراف‌داده
 * - sms_batches              : هر عملیات ارسال (کلید یکتای جلوگیری از ارسال تکراری + نسخه ثابت الگو)
 * - sms_messages             : هر گیرنده (وضعیت ارسال، recId، وضعیت تحویل، خطا)
 * - sms_audit_logs           : رویدادهای مهم
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');                                   // متغیرها با {0} {1} ... (قالب ملی پیامک)
            $table->json('variables')->nullable();                  // [{label, source}] به ترتیب اندیس
            $table->string('category', 20)->default('service');     // service | marketing
            $table->text('description')->nullable();

            $table->unsignedBigInteger('provider_body_id')->nullable()->unique();
            $table->unsignedTinyInteger('provider_status')->nullable(); // BodyStatus خام
            $table->string('provider_description')->nullable();
            $table->string('provider_created_at', 50)->nullable();       // InsertDate (رشته‌ای از سرویس)
            $table->string('status', 20)->default('draft');             // draft | pending | approved | rejected | unknown | failed | missing
            $table->string('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('sms_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mobile', 11)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->timestamps();
        });

        Schema::create('sms_contact_lists', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_contact_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sms_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sms_contact_list_id', 'sms_contact_id'], 'sms_list_member_unique');
        });

        Schema::create('sms_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 11)->unique();
            $table->string('scope', 20)->default('all');            // all | marketing
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sms_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                        // کلید یکتای فرم ارسال (ضد ارسال تکراری)
            $table->foreignId('sms_template_id')->nullable()->constrained()->nullOnDelete();
            // نسخه ثابت الگو در لحظه ارسال (تغییر بعدی الگو گزارش را خراب نمی‌کند)
            $table->string('template_title');
            $table->text('template_body');
            $table->unsignedBigInteger('provider_body_id');
            $table->string('category', 20)->default('service');

            $table->string('source', 20);                          // single | manual | customers | list | import
            $table->json('source_meta')->nullable();
            $table->string('status', 20)->default('queued');       // queued | sending | completed | failed | cancelled
            $table->boolean('is_fake')->default(false);            // ارسال شبیه‌سازی‌شده (SMS_SENDING=fake)

            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('skipped')->default(0);        // حذف‌شده پیش از ارسال (نامعتبر/تکراری/مسدود/بدون رضایت)
            $table->json('skipped_summary')->nullable();
            $table->unsignedBigInteger('estimated_cost')->nullable();
            $table->string('last_error')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_batch_id')->constrained()->cascadeOnDelete();
            $table->string('mobile', 11);
            $table->string('recipient_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sms_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->json('args')->nullable();                      // مقادیر متغیرها (پس از مدت نگهداری پاک می‌شود)

            // queued | sending | accepted | delivered | undelivered | failed | unknown | cancelled
            $table->string('status', 20)->default('queued');
            $table->string('provider_rec_id', 40)->nullable();
            $table->integer('provider_code')->nullable();          // کد خروجی ارسال (در صورت خطا)
            $table->integer('delivery_code')->nullable();          // کد خام وضعیت تحویل
            $table->string('error')->nullable();
            $table->boolean('error_permanent')->default(false);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('cost')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('delivery_checked_at')->nullable();
            $table->timestamps();

            $table->index(['sms_batch_id', 'status']);
            $table->index(['status', 'sent_at']);
            $table->index('mobile');
            $table->index('provider_rec_id');
            $table->index('created_at');
        });

        Schema::create('sms_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_audit_logs');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_batches');
        Schema::dropIfExists('sms_suppressions');
        Schema::dropIfExists('sms_contact_list_members');
        Schema::dropIfExists('sms_contact_lists');
        Schema::dropIfExists('sms_contacts');
        Schema::dropIfExists('sms_templates');
    }
};
