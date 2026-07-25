<?php

namespace Database\Factories;

use App\Models\CourseLesson;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CourseLesson>
 */
class CourseLessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    public function definition(): array
    {
        $title = fake()->randomElement([
                'آشنایی با مفاهیم اولیه',
                'بررسی ساختار بدن',
                'اصول مراقبت',
                'آمادگی قبل از زایمان',
                'تغذیه مناسب',
                'مراقبت از نوزاد',
                'پیشگیری از عوارض',
                'تمرین عملی',
                'مطالعه موردی',
                'جمع بندی جلسه',
                'بررسی خطاهای رایج',
                'نکات تخصصی',
                'آزمون پایانی',
            ]).' '.fake()->numberBetween(1,99);

        return [
            'user_id' => \App\Models\User::inRandomOrder()->value('id'),

            'title' => $title,

            'slug' => Str::slug($title . '-' . fake()->unique()->numberBetween(1, 99999)),

            'description' => fake()->paragraphs(4,true),

            'short_description' => fake()->sentence(),

            'duration' => fake()->numberBetween(5,45),

            'sort' => fake()->numberBetween(1,100),

            'is_free' => fake()->boolean(20),

            'status' => true,

            'published_at' => now(),
        ];
    }
}
