<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Category::truncate();
        // دسته‌های اصلی
        $categories = [
            'کیف' => [
                'کیف زنانه' => [
                    'کیف دستی',
                    'کیف دوشی',
                    'کیف مجلسی',
                ],
                'کیف مردانه' => [
                    'کیف اداری',
                    'کیف دوشی',
                    'کیف دستی',
                ],
                'کیف پول' => [
                    'کیف پول زنانه',
                    'کیف پول مردانه',
                    'جاکارتی',
                ],
                'کوله پشتی' => [
                    'کوله پشتی زنانه',
                    'کوله پشتی مردانه',
                    'کوله پشتی دانشجویی',
                ],
                'کیف سفر' => [
                    'ساک مسافرتی',
                    'چمدان',
                    'کیف کمری',
                ],
            ],

            'کفش' => [
                'کفش زنانه' => [
                    'کفش مجلسی',
                    'کفش روزمره',
                    'کفش اسپرت',
                ],
                'کفش مردانه' => [
                    'کفش رسمی',
                    'کفش روزمره',
                    'کفش اسپرت',
                ],
                'کفش بچگانه' => [
                    'کفش دخترانه',
                    'کفش پسرانه',
                    'کفش نوزادی',
                ],
                'صندل و دمپایی' => [
                    'صندل زنانه',
                    'صندل مردانه',
                    'دمپایی',
                ],
                'کفش ورزشی' => [
                    'کفش فوتبال',
                    'کفش دویدن',
                    'کفش تمرینی',
                ],
            ],

            'اکسسوری' => [
                'اکسسوری زنانه' => [
                    'کمربند زنانه',
                    'دستبند',
                    'جاکلیدی',
                ],
                'اکسسوری مردانه' => [
                    'کمربند مردانه',
                    'جاکارتی',
                    'جاکلیدی',
                ],
                'محصولات چرمی' => [
                    'دستبند چرمی',
                    'جاکلیدی چرمی',
                    'کیف کارت چرمی',
                ],
                'ست چرمی' => [
                    'ست کیف و کمربند',
                    'ست کیف و جاکارتی',
                    'ست هدیه',
                ],
                'لوازم جانبی' => [
                    'بند کیف',
                    'بند دوشی',
                    'کاور کیف',
                ],
            ],
        ];

        $sort = 1;

        foreach ($categories as $parentTitle => $children) {

            // سطح اول
            $parent = Category::create([
                'parent_id' => null,
                'title' => $parentTitle,
                'slug' => $this->randomSlug(),

                'description' => "دسته‌بندی {$parentTitle}",
                'short_description' => "انواع {$parentTitle}",
                'status' => true,
                'sort' => $sort++,
            ]);

            $childSort = 1;

            foreach ($children as $childTitle => $grandChildren) {

                // سطح دوم
                $child = Category::create([
                    'parent_id' => $parent->id,
                    'title' => $childTitle,
                    'slug' => $this->randomSlug(),

                    'description' => "انواع {$childTitle}",
                    'short_description' => $childTitle,
                    'status' => true,
                    'sort' => $childSort++,
                ]);

                $grandChildSort = 1;

                foreach ($grandChildren as $grandChildTitle) {

                    // سطح سوم
                    Category::create([
                        'parent_id' => $child->id,
                        'title' => $grandChildTitle,
                        'slug' => $this->randomSlug(),

                        'description' => "انواع {$grandChildTitle}",
                        'short_description' => $grandChildTitle,
                        'status' => true,
                        'sort' => $grandChildSort++,
                    ]);
                }
            }
            }

    }
    private function randomSlug(): string
    {
        return Str::lower(Str::random(12));
    }
}
