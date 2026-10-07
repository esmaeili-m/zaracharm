<?php

namespace App\Support;

/**
 * فهرست مرجع دسترسی‌های پنل مدیریت
 *
 * نام دسترسی: <area>.<action>  (مثل products.edit)
 * RoleTableSeeder، صفحه نقش‌ها و منوی داشبورد همه از همین فهرست استفاده می‌کنند؛
 * برای بخش جدید فقط یک ردیف به AREAS اضافه و seeder را اجرا کنید.
 */
class Permissions
{
    // ورود به پنل مدیریت (middleware گروه /dashboard)
    public const PANEL = 'settings.dashboard';

    public const ACTIONS = [
        'view' => 'مشاهده',
        'create' => 'ایجاد',
        'edit' => 'ویرایش',
        'delete' => 'حذف',
    ];

    public const CRUD = ['view', 'create', 'edit', 'delete'];

    /**
     * area => [label, group, actions]
     */
    public const AREAS = [
        // داشبورد و سیستم
        'dashboard' => ['label' => 'داشبورد و گزارش‌ها', 'group' => 'سیستم', 'actions' => ['view']],
        'settings' => ['label' => 'تنظیمات سایت', 'group' => 'سیستم', 'actions' => ['view', 'edit']],
        'storage' => ['label' => 'فضای ذخیره‌سازی', 'group' => 'سیستم', 'actions' => ['view', 'create', 'delete']],
        'delivery' => ['label' => 'زمان‌بندی ارسال', 'group' => 'فروش و مالی', 'actions' => ['view', 'edit']],
        'payments' => ['label' => 'پرداخت‌ها و تراکنش‌ها', 'group' => 'فروش و مالی', 'actions' => ['view', 'edit']],
        'payment-settings' => ['label' => 'تنظیمات پرداخت (درگاه / کارت)', 'group' => 'فروش و مالی', 'actions' => ['view', 'edit']],
        'marketplaces' => ['label' => 'مارکت‌پلیس‌ها (دیجی‌کالا، باسلام، ترب)', 'group' => 'فروش و مالی', 'actions' => ['view', 'edit']],

        // کاربران
        'users' => ['label' => 'کاربران', 'group' => 'کاربران و دسترسی', 'actions' => self::CRUD],
        'roles' => ['label' => 'نقش‌ها و دسترسی‌ها', 'group' => 'کاربران و دسترسی', 'actions' => self::CRUD],

        // فروشگاه
        'products' => ['label' => 'محصولات', 'group' => 'فروشگاه', 'actions' => self::CRUD],
        'categories' => ['label' => 'دسته‌بندی‌ها', 'group' => 'فروشگاه', 'actions' => self::CRUD],
        'brands' => ['label' => 'برندها', 'group' => 'فروشگاه', 'actions' => self::CRUD],
        'options' => ['label' => 'ویژگی‌ها (رنگ، سایز...)', 'group' => 'فروشگاه', 'actions' => self::CRUD],
        'specifications' => ['label' => 'مشخصات فنی', 'group' => 'فروشگاه', 'actions' => self::CRUD],
        'inventories' => ['label' => 'انبار و موجودی', 'group' => 'فروشگاه', 'actions' => self::CRUD],

        // فروش و مالی
        'invoices' => ['label' => 'فاکتورها و فروش حضوری', 'group' => 'فروش و مالی', 'actions' => self::CRUD],
        'returns' => ['label' => 'مرجوعی‌ها', 'group' => 'فروش و مالی', 'actions' => ['view', 'edit']],
        'discounts' => ['label' => 'تخفیف‌ها', 'group' => 'فروش و مالی', 'actions' => self::CRUD],
        'coupons' => ['label' => 'کدهای تخفیف', 'group' => 'فروش و مالی', 'actions' => self::CRUD],
        'campaigns' => ['label' => 'کمپین‌ها', 'group' => 'فروش و مالی', 'actions' => self::CRUD],

        // محتوا
        'pages' => ['label' => 'صفحات', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'sections' => ['label' => 'سکشن‌های صفحه‌ساز', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'sliders' => ['label' => 'اسلایدرها', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'stories' => ['label' => 'استوری‌ها', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'articles' => ['label' => 'مقالات', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'posts' => ['label' => 'پست‌ها', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'galleries' => ['label' => 'گالری', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'tags' => ['label' => 'تگ‌ها', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'menus' => ['label' => 'منوها', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'faq' => ['label' => 'سوالات متداول', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'social-links' => ['label' => 'شبکه‌های اجتماعی', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],
        'seo' => ['label' => 'سئو', 'group' => 'محتوا و صفحه‌ساز', 'actions' => self::CRUD],

        // ارتباط با مشتری
        'comments' => ['label' => 'دیدگاه‌ها', 'group' => 'ارتباط با مشتری', 'actions' => self::CRUD],
        'product-questions' => ['label' => 'پرسش و پاسخ محصولات', 'group' => 'ارتباط با مشتری', 'actions' => self::CRUD],
        'tickets' => ['label' => 'تیکت‌ها', 'group' => 'ارتباط با مشتری', 'actions' => self::CRUD],
        'messages' => ['label' => 'پیام‌های تماس با ما', 'group' => 'ارتباط با مشتری', 'actions' => ['view', 'delete']],

        // آموزش (قدیمی)
        'courses' => ['label' => 'دوره‌ها', 'group' => 'آموزش و خدمات', 'actions' => self::CRUD],
        'services' => ['label' => 'خدمات', 'group' => 'آموزش و خدمات', 'actions' => self::CRUD],
    ];

    /**
     * دسترسی‌هایی که دیگر استفاده نمی‌شوند و seeder حذفشان می‌کند
     */
    public const DEPRECATED = ['tickets.reply', 'tickets.close'];

    /**
     * همه نام‌های دسترسی
     */
    public static function all(): array
    {
        $names = [self::PANEL];

        foreach (self::AREAS as $area => $meta) {
            foreach ($meta['actions'] as $action) {
                $names[] = "{$area}.{$action}";
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * دسترسی‌ها گروه‌بندی‌شده برای صفحه نقش‌ها
     *
     * @return array<string, array<string, array{label: string, permissions: array<string, string>}>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::AREAS as $area => $meta) {
            $groups[$meta['group']][$area] = [
                'label' => $meta['label'],
                'permissions' => collect($meta['actions'])
                    ->mapWithKeys(fn ($action) => ["{$area}.{$action}" => self::ACTIONS[$action] ?? $action])
                    ->all(),
            ];
        }

        $groups['سیستم'] = ['panel' => [
            'label' => 'ورود به پنل مدیریت',
            'permissions' => [self::PANEL => 'ورود'],
        ]] + ($groups['سیستم'] ?? []);

        return $groups;
    }

    /**
     * همگام‌سازی دیتابیس با این فهرست (غیرمخرب و idempotent)
     * - ساخت دسترسی‌های جاافتاده
     * - حذف دسترسی‌های منسوخ
     * - همه دسترسی‌ها برای admin ، هیچ دسترسی پنل برای user
     *
     * @return array{created: int, removed: int}
     */
    public static function sync(string $guard = 'web'): array
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($guard) {
            $existing = \Spatie\Permission\Models\Permission::where('guard_name', $guard)->pluck('name')->all();
            $created = 0;

            foreach (array_diff(self::all(), $existing) as $name) {
                \Spatie\Permission\Models\Permission::create(['name' => $name, 'guard_name' => $guard]);
                $created++;
            }

            $removed = 0;
            \Spatie\Permission\Models\Permission::whereIn('name', self::DEPRECATED)->where('guard_name', $guard)->get()
                ->each(function ($permission) use (&$removed) {
                    $permission->delete();
                    $removed++;
                });

            // ستون label نقش‌ها اجباری است
            $admin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard], ['label' => 'مدیر کل']);
            $user = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => $guard], ['label' => 'کاربر عادی']);

            $admin->syncPermissions(\Spatie\Permission\Models\Permission::where('guard_name', $guard)->get());
            $user->syncPermissions([]);

            return ['created' => $created, 'removed' => $removed];
        });

        $registrar->forgetCachedPermissions();

        return $result;
    }

    /**
     * دسترسی‌های فهرست که هنوز در دیتابیس ساخته نشده‌اند + منسوخ‌های باقی‌مانده
     */
    public static function outOfSync(string $guard = 'web'): int
    {
        $existing = \Spatie\Permission\Models\Permission::where('guard_name', $guard)->pluck('name')->all();

        return count(array_diff(self::all(), $existing)) + count(array_intersect(self::DEPRECATED, $existing));
    }

    public static function label(string $name): string
    {
        if ($name === self::PANEL) {
            return 'ورود به پنل مدیریت';
        }

        [$area, $action] = array_pad(explode('.', $name, 2), 2, null);

        return (self::AREAS[$area]['label'] ?? $area) . ' - ' . (self::ACTIONS[$action] ?? $action);
    }
}
