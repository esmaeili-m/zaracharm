<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Service::class;

    public function definition(): array
    {
        $services = [

            'مشاوره بارداری',
            'مشاوره شیردهی',
            'مراقبت پس از زایمان',
            'آمادگی زایمان طبیعی',
            'ویزیت مامایی',
            'مشاوره ناباروری',
            'مراقبت از نوزاد',
            'مشاوره تغذیه دوران بارداری',
            'پایش رشد جنین',
            'آموزش مراقبت از مادر و کودک',
            'خدمات مامایی در منزل',
            'مشاوره بارداری پرخطر',
            'برگزاری کلاس‌های آمادگی زایمان',
            'مشاوره سلامت زنان',
            'پایش سلامت مادر باردار',
            'آموزش شیردهی موفق',
            'کنترل فشار خون بارداری',
            'مراقبت از زخم سزارین',
            'آموزش احیای نوزاد',
            'مشاوره روانشناسی دوران بارداری',

        ];

        $title = fake()->randomElement($services);

        return [

            'title' => $title,

            'slug' => Str::slug(
                $title.'-'.fake()->uuid()
            ),

            'short_description' => fake()->sentence(15),

            'description' => collect(range(1, 8))
                ->map(fn() => fake()->paragraph())
                ->implode("\n\n"),

            'status' => fake()->boolean(90),

            'views_count' => fake()->numberBetween(0, 10000),

            'category_id' => Category::query()
                ->inRandomOrder()
                ->value('id'),
        ];
    }
}
