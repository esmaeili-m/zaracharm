<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Tag::truncate();
        $tags = [
            'کیف زنانه',
            'کیف مردانه',
            'کوله پشتی',
            'کیف دوشی',
            'کیف مجلسی',
            'کیف اداری',
            'کیف پول',
            'کمربند چرمی',
            'کمربند مردانه',
            'کمربند زنانه',
            'جاکارتی',
            'جاکلیدی',
            'عینک آفتابی',
            'ساعت مچی',
            'دستبند',
            'گردنبند',
            'انگشتر',
            'شال و روسری',
            'چمدان مسافرتی',
            'اکسسوری چرمی',
        ];
        foreach ($tags as $tag) {
            $slug = Tag::generateSlugFrom($tag);
            Tag::create([
                'title' => $tag,
                'slug' => $slug
            ]);
        }

    }
}
