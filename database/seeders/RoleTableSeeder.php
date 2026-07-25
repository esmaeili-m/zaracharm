<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Role::truncate();
        $admin = Role::create(['name' => 'admin','guard_name' => 'web', 'label' => 'مدیر']);
        $student = Role::create(['name' => 'user','guard_name' => 'web', 'label' => 'کاربر عادی']);

        $permissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'articles.view', 'articles.create', 'articles.edit', 'articles.delete',
            'pages.view', 'pages.create', 'pages.edit', 'pages.delete',
            'sections.view', 'sections.create', 'sections.edit', 'sections.delete',
            'galleries.view', 'galleries.create', 'galleries.edit', 'galleries.delete',

            // آموزش و خدمات
            'courses.view', 'courses.create', 'courses.edit', 'courses.delete',
            'services.view', 'services.create', 'services.edit', 'services.delete',

            // سازمان‌دهی
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'tags.view', 'tags.create', 'tags.edit', 'tags.delete',
            'menus.view', 'menus.create', 'menus.edit', 'menus.delete',

            // ارتباطات
            'messages.view', 'messages.delete',
            'comments.view', 'comments.edit', 'comments.delete',
            'tickets.view', 'tickets.reply', 'tickets.close', 'tickets.delete',
            'faq.view', 'faq.create', 'faq.edit', 'faq.delete',

            // مالی و سئو
            'invoices.view', 'invoices.create', 'invoices.delete',
            'seo.view','seo.create', 'seo.edit',

            // سیستم
            'settings.view', 'settings.edit','settings.dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin->givePermissionTo(Permission::all());
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    }
}
