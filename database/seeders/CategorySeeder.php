<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Category::truncate();
        // دسته‌های اصلی
        $parents = Category::factory()->count(5)->create();

        // زیر دسته‌ها
        foreach ($parents as $parent) {
            Category::factory()
                ->count(1)
                ->create([
                    'parent_id' => $parent->id,
                ]);
        }
    }
}
