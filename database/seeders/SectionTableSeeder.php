<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Section::truncate();
        $data=[
            [
                'name'=>'هیرو',
                'key'=>'hero',
                'component'=>'main.sections.hero',
                'livewire' => 1,

            ], [
                'name'=>'دسته بندی - دوره',
                'key'=>'category-courses',
                'component'=>'components.main.sections.category-courses',
                'livewire' => 0,

            ], [
                'name'=>'درباره ما',
                'key'=>'about',
                'component'=>'components.main.sections.about',
                'livewire' => 0,

            ], [
                'name'=>'اخرین دوره ها',
                'key'=>'courses',
                'component'=>'components.main.sections.courses',
                'livewire' => 0,

            ], [
                'name'=>'تیم ما (مدرسین)',
                'key'=>'team',
                'component'=>'components.main.sections.team',
                'livewire' => 0,

            ], [
                'name'=>'خبرنامه',
                'key'=>'newsletter',
                'component'=>'main.sections.newsletter',
                'livewire' => 1,

            ], [
                'name'=>'اخرین مقالات',
                'key'=>'blog',
                'component'=>'components.main.sections.blog',
                'livewire' => 0,

            ], [
                'name'=>'سوالات متداول',
                'key'=>'faq',
                'component'=>'components.main.sections.faq',
                'livewire' => 0,

            ],[
                'name'=>'ارتباط با ما',
                'key'=>'contact',
                'component'=>'components.main.sections.contact',
                'livewire' => 0,

            ],[
                'name'=>' فرم ارتباط با ما',
                'key'=>'contact-form',
                'component'=>'main.sections.contact-form',
                'livewire' => 1,

            ],[
                'name'=>'لیست مقالات',
                'key'=>'blogs',
                'component'=>'main.sections.blogs',
                'livewire' => 1,

            ],[
                'name'=>'لیست دوره ها',
                'key'=>'courses-list',
                'component'=>'main.sections.coursesList',
                'livewire' => 1,

            ],[
                'name'=>'لیست خدمات',
                'key'=>'services',
                'component'=>'main.sections.services',
                'livewire' => 1,

            ],[
                'name'=>'دسته بندی ها',
                'key'=>'categories',
                'component'=>'main.sections.categories',
                'livewire' => 1,

            ],[
                'name' => 'گالری',
                'key'=>'gallery',
                'component'=>'components.main.sections.gallery',
                'livewire' => 0,

            ],


        ];
        Section::insert($data);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
