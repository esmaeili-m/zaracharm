<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titles = [
            'آموزش جامع بارداری',
            'آمادگی برای زایمان طبیعی',
            'شیردهی موفق از تولد تا شش ماهگی',
            'مراقبت‌های تخصصی نوزاد',
            'مامایی پیشرفته',
            'بارداری پرخطر',
            'تغذیه مادران باردار',
            'سلامت مادر و کودک',
            'آموزش احیای نوزاد',
            'اورژانس‌های مامایی',
            'بارداری دوقلو و چندقلویی',
            'بهداشت و سلامت زنان',
            'زایمان بدون درد',
            'آموزش ناباروری',
            'روانشناسی دوران بارداری',
        ];

        $title = fake()->randomElement($titles);

        $isFree = fake()->boolean(25);

        $price = $isFree
            ? 0
            : fake()->randomElement([
                490000,
                690000,
                890000,
                1200000,
                1500000,
                2000000,
            ]);

        $hasDiscount = !$isFree && fake()->boolean(60);

        return [
            'title' => $title,

            'slug' => Str::slug(
                $title.'-'.fake()->uuid()
            ),

            'description' => fake()->paragraphs(8, true),

            'short_description' => fake()->sentence(),

            'price' => $price,

            'discount_price' => $hasDiscount
                ? intval($price * fake()->randomFloat(2, 0.6, 0.9))
                : null,

            'is_free' => $isFree,

            'level' => fake()->randomElement([
                'مبتدی',
                'متوسط',
                'پیشرفته',
            ]),

            'duration' => fake()->numberBetween(300, 1800),

            'sort' => fake()->numberBetween(1, 50),

            'status' => true,

            'category_id' => Category::query()->inRandomOrder()->value('id'),

            'user_id' => User::query()->inRandomOrder()->value('id'),

        ];
    }
}
