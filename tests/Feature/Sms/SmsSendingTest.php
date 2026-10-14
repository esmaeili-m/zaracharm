<?php

namespace Tests\Feature\Sms;

use App\Models\SmsBatch;
use App\Models\SmsContact;
use App\Models\SmsContactList;
use App\Models\SmsMessage;
use App\Models\SmsSuppression;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SendResult;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * ارسال با درگاه شبیه‌سازی (هیچ درخواستی به ملی پیامک فرستاده نمی‌شود)
 */
class SmsSendingTest extends TestCase
{
    protected FakeSmsGateway $gateway;

    /**
     * فقط جدول‌های موردنیاز این ماژول (برخی migrationهای پروژه مخصوص MySQL هستند و روی sqlite تست اجرا نمی‌شوند)
     */
    public const MIGRATIONS = [
        '0001_01_01_000000_create_users_table.php',
        '0001_01_01_000002_create_permission_tables.php',
        '2026_06_08_175043_create_settings_table.php',
        '2026_07_25_071206_create_addresses_table.php',
        '2026_07_25_071221_create_orders_table.php',
        '2026_08_16_191000_create_notification_preferences_table.php',
        '2026_12_15_100000_create_sms_tables.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => array_map(fn ($file) => 'database/migrations/' . $file, self::MIGRATIONS)]);

        config(['sms.sending' => 'fake', 'sms.throttle_ms' => 0]);
        $this->gateway = new FakeSmsGateway();
        $this->app->instance(SmsGateway::class, $this->gateway);
    }

    protected function template(array $attributes = []): SmsTemplate
    {
        return SmsTemplate::create($attributes + [
            'title' => 'سفارش',
            'body' => 'سلام {0} عزیز، سفارش {1} ثبت شد.',
            'variables' => [['label' => 'نام', 'source' => 'name'], ['label' => 'شماره سفارش', 'source' => 'fixed']],
            'category' => 'service',
            'provider_body_id' => 1001,
            'provider_status' => 2,
            'status' => 'approved',
        ]);
    }

    /** UserFactory پروژه هنوز ستون قدیمی name را دارد؛ کاربر مستقیم ساخته می‌شود */
    protected function user(array $attributes): User
    {
        return User::create($attributes + ['status' => true]);
    }

    protected function sender(): SmsSender
    {
        return $this->app->make(SmsSender::class);
    }

    public function test_unapproved_template_cannot_be_used(): void
    {
        $template = $this->template(['status' => 'pending']);

        $this->expectException(RuntimeException::class);
        $this->sender()->prepare($template, ['source' => 'single', 'mobile' => '09121234567'], []);
    }

    public function test_manual_numbers_are_normalized_deduplicated_and_filtered(): void
    {
        SmsSuppression::create(['mobile' => '09350000000', 'scope' => 'all']);

        $plan = $this->sender()->prepare($this->template(), [
            'source' => 'manual',
            'numbers' => "09121234567\n+989121234567\n0912\n09350000000\n۰۹۳۶۱۲۳۴۵۶۷",
        ], [0 => ['source' => 'name', 'value' => 'مشتری'], 1 => ['source' => 'fixed', 'value' => 'A-100']]);

        $this->assertSame(['09121234567', '09361234567'], array_column($plan['ready'], 'mobile'));
        $this->assertSame(1, $plan['skipped']['duplicate']);
        $this->assertSame(1, $plan['skipped']['invalid']);
        $this->assertSame(1, $plan['skipped']['suppressed']);
        // نام خالی → مقدار جایگزین
        $this->assertSame(['مشتری', 'A-100'], $plan['ready'][0]['args']);
    }

    public function test_recipient_with_missing_variable_is_not_sent(): void
    {
        $plan = $this->sender()->prepare($this->template(), ['source' => 'manual', 'numbers' => '09121234567'], [
            0 => ['source' => 'name', 'value' => ''],
            1 => ['source' => 'fixed', 'value' => 'A-1'],
        ]);

        $this->assertSame([], $plan['ready']);
        $this->assertSame(1, $plan['skipped']['missing_vars']);
    }

    public function test_marketing_requires_consent_and_respects_opt_out(): void
    {
        $template = $this->template(['category' => 'marketing', 'body' => 'تخفیف ویژه', 'variables' => []]);
        $consenting = $this->user(['mobile' => '09120000001']);
        DB::table('notification_preferences')->insert(['user_id' => $consenting->id, 'promotions' => true, 'created_at' => now(), 'updated_at' => now()]);
        SmsContact::create(['mobile' => '09120000002', 'marketing_consent' => true]);
        SmsContact::create(['mobile' => '09120000003', 'marketing_consent' => true]);
        SmsSuppression::create(['mobile' => '09120000003', 'scope' => 'marketing']);

        $plan = $this->sender()->prepare($template, ['source' => 'manual', 'numbers' => "09120000001\n09120000002\n09120000003\n09120000004"], []);

        $this->assertSame(['09120000001', '09120000002'], array_column($plan['ready'], 'mobile'));
        $this->assertSame(1, $plan['skipped']['opted_out']);
        $this->assertSame(1, $plan['skipped']['no_consent']);
    }

    public function test_customers_all_filtered_minus_excluded(): void
    {
        $a = $this->user(['mobile' => '09121111111', 'first_name' => 'علی', 'last_name' => 'الف']);
        $b = $this->user(['mobile' => '09122222222', 'first_name' => 'رضا', 'last_name' => 'ب']);
        $this->user(['mobile' => '09123333333', 'first_name' => 'سارا', 'last_name' => 'پ']);

        $plan = $this->sender()->prepare($this->template(), [
            'source' => 'customers',
            'filters' => ['q' => ''],
            'selection' => 'all',
            'excluded_ids' => [$b->id],
        ], [1 => ['source' => 'fixed', 'value' => 'X']]);

        $this->assertCount(2, $plan['ready']);
        $this->assertNotContains('09122222222', array_column($plan['ready'], 'mobile'));
        $this->assertSame(['علی الف', 'X'], collect($plan['ready'])->firstWhere('mobile', '09121111111')['args']);
        $this->assertSame($a->id, collect($plan['ready'])->firstWhere('mobile', '09121111111')['user_id']);
    }

    public function test_dispatch_sends_once_and_is_idempotent(): void
    {
        $template = $this->template();
        $list = SmsContactList::create(['title' => 'مشتریان ویژه']);
        $list->contacts()->attach([
            SmsContact::create(['mobile' => '09121234567', 'name' => 'علی'])->id,
            SmsContact::create(['mobile' => '09351234567', 'name' => 'مریم'])->id,
        ]);
        $uuid = (string) Str::uuid();
        $vars = [1 => ['source' => 'fixed', 'value' => 'A-7']];

        $batch = $this->sender()->dispatch($template, ['source' => 'list', 'list_id' => $list->id], $vars, $uuid, null);
        $again = $this->sender()->dispatch($template, ['source' => 'list', 'list_id' => $list->id], $vars, $uuid, null);

        $this->assertSame($batch->id, $again->id);
        $this->assertSame(1, SmsBatch::count());
        $this->assertCount(2, $this->gateway->sent);
        $this->assertSame(['علی', 'A-7'], $this->gateway->sent[0]['args']);
        $this->assertSame(1001, $this->gateway->sent[0]['bodyId']);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertTrue($batch->fresh()->is_fake);
        $this->assertSame(2, SmsMessage::where('status', 'accepted')->count());
    }

    public function test_account_error_stops_batch_and_transient_failures_can_be_retried(): void
    {
        $gateway = new class implements SmsGateway {
            public int $calls = 0;
            public bool $broken = true;

            public function isLive(): bool { return false; }

            public function sendPattern(string $mobile, int $bodyId, array $args): SendResult
            {
                $this->calls++;

                return $this->broken
                    ? SendResult::failed(2, 'اعتبار کافی نیست', false, true)
                    : SendResult::accepted('1234567890123456789');
            }

            public function deliveries(array $recIds): array { return []; }
        };
        $this->app->instance(SmsGateway::class, $gateway);

        $batch = $this->sender()->dispatch($this->template(['body' => 'متن ثابت', 'variables' => []]),
            ['source' => 'manual', 'numbers' => "09121234567\n09351234567"], [], (string) Str::uuid(), null);

        $batch->refresh();
        $this->assertSame('failed', $batch->status);
        $this->assertSame(1, $gateway->calls); // پس از خطای حساب، بقیه ارسال نمی‌شوند
        $this->assertSame(1, $batch->messages()->where('status', 'queued')->count());

        $gateway->broken = false;
        $queued = $this->app->make(SmsSender::class)->retry($batch);

        $this->assertSame(2, $queued);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(2, $batch->messages()->where('status', 'accepted')->count());
        $this->assertSame(3, $gateway->calls);
    }

    public function test_cancel_marks_queued_messages_cancelled(): void
    {
        $batch = SmsBatch::create([
            'uuid' => (string) Str::uuid(), 'template_title' => 't', 'template_body' => 'b', 'provider_body_id' => 1,
            'source' => 'manual', 'status' => 'queued', 'total' => 1,
        ]);
        $batch->messages()->create(['mobile' => '09121234567', 'status' => 'queued']);

        $this->assertSame(1, $this->sender()->cancel($batch));
        $this->sender()->processBatch($batch->id);

        $this->assertSame('cancelled', $batch->messages()->first()->status);
        $this->assertCount(0, $this->gateway->sent);
    }
}
