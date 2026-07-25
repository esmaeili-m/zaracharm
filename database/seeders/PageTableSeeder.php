<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PageTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Page::truncate();
        $data=[
            [
                'title'=>'صفحه اصلی',
                'slug'=>'home',
            ],
            [
                'title'=>'دسته بندی ها',
                'slug'=>'categories',
            ],
            [
                'title'=>'دوره ها',
                'slug'=>'courses',
            ],
            [
                'title'=>'درباره ما',
                'slug'=>'about-us',
            ],
            [
                'title'=>'ارتباط با ما',
                'slug'=>'contact-us',
            ],
            [
                'title'=>'خدمات ما',
                'slug'=>'services',
            ],
            [
                'title'=>'مقالات ما',
                'slug'=>'articles',
            ],

        ];
        Page::insert($data);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    }
}
