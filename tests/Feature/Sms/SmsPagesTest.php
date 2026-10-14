<?php

namespace Tests\Feature\Sms;

use App\Models\SmsBatch;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGateway;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmsPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => array_map(fn ($file) => 'database/migrations/' . $file, SmsSendingTest::MIGRATIONS)]);
        config(['sms.sending' => 'fake', 'sms.username' => null, 'sms.password' => null]);
        $this->app->instance(SmsGateway::class, new FakeSmsGateway());
    }

    protected function admin(): User
    {
        $user = User::create(['mobile' => '09129999999', 'first_name' => 'مدیر', 'status' => true]);
        $user->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web', 'label' => 'مدیر کل']));

        return $user;
    }

    public function test_pages_render_for_admin(): void
    {
        $this->actingAs($this->admin());
        SmsTemplate::create(['title' => 'سفارش', 'body' => 'سلام {0}', 'provider_body_id' => 5, 'provider_status' => 2, 'status' => 'approved']);

        foreach (['index', 'templates', 'send', 'contacts', 'reports'] as $page) {
            Livewire::test('pages::dashboard.sms.' . $page)->assertOk();
        }
    }

    public function test_pages_forbidden_without_permission(): void
    {
        $this->actingAs(User::create(['mobile' => '09128888888', 'status' => true]));

        Livewire::test('pages::dashboard.sms.index')->assertForbidden();
        Livewire::test('pages::dashboard.sms.send')->assertForbidden();
    }

    public function test_single_send_through_wizard_is_not_repeated_on_double_submit(): void
    {
        $this->actingAs($this->admin());
        $template = SmsTemplate::create([
            'title' => 'سفارش', 'body' => 'سلام {0}', 'variables' => [['label' => 'نام', 'source' => 'fixed']],
            'provider_body_id' => 5, 'provider_status' => 2, 'status' => 'approved',
        ]);

        $component = Livewire::test('pages::dashboard.sms.send')
            ->set('source', 'single')
            ->set('singleMobile', '۰۹۱۲۱۲۳۴۵۶۷')
            ->call('goTo', 2)
            ->call('chooseTemplate', $template->id)
            ->call('goTo', 3)
            ->set('vars.0.value', 'علی')
            ->call('goTo', 4)
            ->assertSet('step', 4)
            ->assertSet('plan.count', 1)
            ->set('confirmed', true)
            ->call('send')
            ->assertSet('step', 5)
            ->call('send');

        $this->assertSame(1, SmsBatch::count());
        $this->assertSame('09121234567', SmsBatch::first()->messages()->first()->mobile);
        $this->assertSame(['علی'], SmsBatch::first()->messages()->first()->args);
        $component->assertSee('عملیات ارسال');
    }

    public function test_unapproved_template_cannot_be_chosen(): void
    {
        $this->actingAs($this->admin());
        $pending = SmsTemplate::create(['title' => 'x', 'body' => 'y', 'provider_body_id' => 6, 'status' => 'pending']);

        Livewire::test('pages::dashboard.sms.send')
            ->call('chooseTemplate', $pending->id)
            ->assertSet('templateId', null);
    }
}
