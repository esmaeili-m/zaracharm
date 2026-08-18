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
                'name'=>'اسلایدر',
                'key'=>'sliders',
                'component'=>'main.sections.sliders',
                'is_livewire' => 0,

            ],
            [
                'name'=>'دسته بندی ها',
                'key'=>'categories',
                'component'=>'main.sections.categories',
                'is_livewire' => 1,

            ],
            [
                'name' => 'پیشنهادهای لحظه‌ای',
                'key' => 'instantOffers',
                'component' => 'main.sections.instant-offers',
                'is_livewire' => 1,
            ],
            [
                'name' => 'محصولات',
                'key' => 'products',
                'component' => 'main.sections.products',
                'is_livewire' => 1,
            ],
            [
                'name' => 'کمپین',
                'key' => 'campaigns',
                'component' => 'main.sections.campaigns',
                'is_livewire' => 1,
            ],
            [
                'name' => 'خبرنامه',
                'key' => 'newsletter',
                'component' => 'main.sections.newsletter',
                'is_livewire' => 1,
            ],
            [
                'name' => 'راه‌های ارتباطی',
                'key' => 'contact_channels',
                'component' => 'main.sections.contact-channels',
                'is_livewire' => 1,
            ],
            [
                'name' => 'مقالات',
                'key' => 'articles',
                'component' => 'main.sections.articles',
                'is_livewire' => 1,
            ],
            [
                'name' => 'درباره ما',
                'key' => 'about',
                'component' => 'main.sections.about',
                'is_livewire' => 1,
            ],
            [
                'name' => 'ارتباط با ما',
                'key' => 'contact',
                'component' => 'main.sections.contact',
                'is_livewire' => 1,
            ],
            [
                'name' => 'نقشه',
                'key' => 'map',
                'component' => 'main.sections.map',
                'is_livewire' => 1,
            ],
            [
                'name' => 'استوری',
                'key' => 'stories',
                'component' => 'main.sections.stories',
                'is_livewire' => 1,
            ],


        ];
        Section::insert($data);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
