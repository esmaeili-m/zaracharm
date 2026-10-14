<?php

namespace Tests\Feature\Sections;

use App\Models\Announcement;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageRow;
use App\Models\RowSection;
use App\Models\Section;
use App\Models\User;
use App\Support\Sections\Banners;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * سکشن بنرها و اطلاعیه‌ها (فقط جدول‌های لازم؛ برخی migrationهای پروژه مخصوص MySQL هستند)
 */
class BannersAndAnnouncementsTest extends TestCase
{
    protected const MIGRATIONS = [
        '0001_01_01_000000_create_users_table.php',
        '0001_01_01_000002_create_permission_tables.php',
        '2026_05_17_104841_create_media_table.php',
        '2026_05_21_104248_create_pages_table.php',
        '2026_05_25_052717_create_sections_table.php',
        '2026_06_16_163452_create_announcements_table.php',
        '2026_06_26_152031_create_page_rows_table.php',
        '2026_07_26_082102_create_row_sections_table.php',
        '2026_12_16_100000_register_banners_section.php',
        '2026_12_16_100001_add_link_to_announcements_and_register_section.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => array_map(fn ($f) => 'database/migrations/' . $f, self::MIGRATIONS)]);
        Storage::fake('public');
        config(['media.compress' => false]);
    }

    protected function admin(): User
    {
        $user = User::create(['mobile' => '09129999999', 'first_name' => 'مدیر', 'status' => true]);
        $user->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web', 'label' => 'مدیر کل']));

        return $user;
    }

    public function test_migrations_register_both_sections(): void
    {
        $this->assertSame('main.sections.banners', Section::where('key', 'banners')->value('component'));
        $this->assertSame('main.sections.announcements', Section::where('key', 'announcements')->value('component'));
    }

    public function test_announcement_admin_crud_and_visibility_window(): void
    {
        $this->actingAs($this->admin());

        Livewire::test('pages::dashboard.announcements.index')
            ->assertOk()
            ->set('title', 'ارسال رایگان')
            ->set('content', "سفارش‌های بالای ۲ میلیون\nرایگان ارسال می‌شوند")
            ->set('type', 'success')
            ->set('link', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['link'])
            ->set('link', '/categories/bags')
            ->set('ends_at', '1300/01/01')
            ->set('starts_at', '1405/01/01')
            ->call('save')
            ->assertHasErrors(['ends_at'])
            ->set('starts_at', '')
            ->set('ends_at', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Announcement::visible()->count());

        Announcement::create(['title' => 'آینده', 'content' => 'x', 'type' => 'info', 'is_active' => true, 'starts_at' => now()->addDay()]);
        Announcement::create(['title' => 'گذشته', 'content' => 'x', 'type' => 'info', 'is_active' => true, 'ends_at' => now()->subDay()]);
        Announcement::create(['title' => 'خاموش', 'content' => 'x', 'type' => 'info', 'is_active' => false]);

        $this->assertSame(['ارسال رایگان'], Announcement::visible()->pluck('title')->all());
    }

    public function test_announcements_section_renders_only_visible_items(): void
    {
        Announcement::create(['title' => 'اطلاعیه فعال', 'content' => 'متن <b>ایمن</b>', 'type' => 'warning', 'is_active' => true, 'link' => '/sale', 'link_text' => 'خرید']);
        Announcement::create(['title' => 'اطلاعیه خاموش', 'content' => 'x', 'type' => 'info', 'is_active' => false]);

        foreach (['list', 'cards', 'bar'] as $style) {
            Livewire::test('main.sections.announcements', ['data' => ['style' => $style, 'limit' => 5]])
                ->assertSee('اطلاعیه فعال')
                ->assertDontSee('اطلاعیه خاموش')
                ->assertDontSee('<b>ایمن</b>', false)
                ->assertSee('/sale');
        }

        // فیلتر نوع
        Livewire::test('main.sections.announcements', ['data' => ['style' => 'list', 'types' => ['danger']]])
            ->assertDontSee('اطلاعیه فعال');
    }

    public function test_banner_section_upload_save_and_render(): void
    {
        $this->actingAs($this->admin());

        $page = Page::create(['title' => 'خانه', 'slug' => 'home', 'status' => 1]);
        $row = PageRow::create(['page_id' => $page->id, 'sort' => 1]);
        $rowSection = RowSection::create(['page_row_id' => $row->id, 'section_id' => Section::where('key', 'banners')->value('id'), 'title' => 'بنرها', 'sort' => 1]);

        $component = Livewire::test('pages::dashboard.pages.sections.page-section', ['row' => $row])
            ->call('get_data', $rowSection->id)
            ->assertSet('formData.layout', 'grid');

        $first = $component->get('formData.items.0.uid');

        // بنر جدید بدون تصویر ذخیره نمی‌شود
        $component->call('save')->assertHasErrors(["formData.images.desktop_{$first}"]);

        $component->call('addBannerItem');
        $second = $component->get('formData.items.1.uid');

        $component
            ->set('formData.items.0.title', 'حراج پاییزه')
            ->set('formData.items.0.link', '/sale')
            ->set("formData.images.desktop_{$first}", UploadedFile::fake()->image('d1.jpg', 1200, 600))
            ->set("formData.images.mobile_{$first}", UploadedFile::fake()->image('m1.jpg', 600, 600))
            ->set("formData.images.desktop_{$second}", UploadedFile::fake()->image('d2.jpg', 1200, 600))
            ->set('formData.layout', 'slider')
            ->call('save')
            ->assertHasNoErrors();

        $rowSection->refresh();
        $this->assertSame('slider', $rowSection->data['layout']);
        $this->assertCount(2, $rowSection->data['items']);
        $this->assertArrayNotHasKey('images', $rowSection->data);
        $this->assertSame(3, $rowSection->media()->count());
        $this->assertSame(1, $rowSection->media()->where('collection', Banners::MOBILE)->where('purpose', $first)->count());
        // مسیر فایل بدون کلمه banner (فایروال هاست / تبلیغ‌گیر)
        $this->assertSame(3, $rowSection->media()->where('file_path', 'like', Banners::DIRECTORY . '/%')->count());

        $html = Livewire::test('main.sections.banners', ['data' => $rowSection->data, 'media' => $rowSection->media])
            ->assertSee('حراج پاییزه')
            ->assertSee('href="/sale"', false)
            ->assertSee('max-width: 767px', false)
            ->html();
        $this->assertSame(2, substr_count($html, '<picture>'));

        // حذف بنر دوم → تصویرش هنگام ذخیره پاک می‌شود
        Livewire::test('pages::dashboard.pages.sections.page-section', ['row' => $row])
            ->call('get_data', $rowSection->id)
            ->call('removeBannerItem', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, Media::where('purpose', $second)->count());
        $this->assertSame(2, $rowSection->media()->count());
    }
}
