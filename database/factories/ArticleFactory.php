<?php

namespace Database\Factories;

use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Article::class;
    public function definition(): array
    {
        $articles = [

            'علائم اولیه بارداری که باید جدی بگیرید',
            'تغذیه مناسب در سه ماهه اول بارداری',
            'زایمان طبیعی یا سزارین؟ مقایسه کامل',
            'مراقبت از نوزاد در هفته اول تولد',
            'نکات طلایی شیردهی موفق',
            'بارداری پرخطر چیست و چگونه مدیریت می‌شود؟',
            'اهمیت مصرف اسید فولیک در دوران بارداری',
            'علائم شروع زایمان طبیعی',
            'راهکارهای کاهش درد زایمان',
            'مراقبت‌های ضروری پس از زایمان',
            'افسردگی پس از زایمان و راه‌های درمان',
            'مراقبت از بخیه بعد از زایمان',
            'چگونه وزن مناسب در بارداری داشته باشیم؟',
            'غربالگری‌های ضروری دوران بارداری',
            'علائم هشدار دهنده در بارداری',
            'فواید تماس پوست با پوست مادر و نوزاد',
            'روش صحیح حمام کردن نوزاد',
            'علت زردی نوزادان چیست؟',
            'بهترین زمان مراجعه به مامای بارداری',
            'آمادگی روانی برای مادر شدن',
            'ورزش‌های مناسب دوران بارداری',
            'رژیم غذایی مادر شیرده',
            'نکات مهم برای بارداری دوقلو',
            'آشنایی با مراقبت‌های پیش از بارداری',
            'تاثیر خواب بر سلامت مادر و جنین',
            'بررسی علل ناباروری در زنان',
            'نقش ماما در مراقبت‌های دوران بارداری',
            'راهنمای کامل مراقبت از نوزاد تازه متولد شده',
            'خطرات فشار خون بالا در بارداری',
            'همه چیز درباره رشد جنین در ۹ ماه بارداری',

        ];

        $title = fake()->randomElement($articles);

        return [
            'author_id' => User::query()->inRandomOrder()->value('id'),

            'title' => $title,

            'slug' => Str::slug($title . '-' . fake()->uuid()),

            'short_description' => fake()->sentence(20),

            'description' => collect(range(1, 10))
                ->map(fn () => fake()->paragraph())
                ->implode("\n\n"),

            'status' => true,

            'views_count' => fake()->numberBetween(0, 15000),

            'category_id' => Category::query()->inRandomOrder()->value('id'),

        ];
    }
}
