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
                'title'=>'شگفت انگیزها',
                'slug'=>'special-offers',
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
                'title'=>'مقالات ما',
                'slug'=>'articles',
            ],[
                'title'=>'محصولات',
                'slug'=>'products',
            ],

        ];
        Page::insert($data);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    }
}
