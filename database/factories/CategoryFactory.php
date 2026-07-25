<?php

namespace Database\Factories;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'بارداری',
            'زایمان طبیعی',
            'شیردهی',
            'مراقبت نوزاد',
            'بهداشت زنان',
            'مامایی پیشرفته',
            'بارداری پرخطر',
            'تغذیه در بارداری',
            'آمادگی زایمان',
            'سلامت مادر و کودک',
            'ناباروری',
            'سونوگرافی بارداری',
            'مراقبت‌های پس از زایمان',
            'بارداری دوقلو',
            'احیای نوزاد',
            'سلامت جنین',
            'زایمان بدون درد',
            'روانشناسی بارداری',
            'اورژانس‌های مامایی',
            'آموزش مادران باردار',
        ]);

        return [
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name . '-' . fake()->unique()->numberBetween(1, 999)),
            'description' => fake()->paragraph(4),
            'short_description' => fake()->sentence(),
            'image' => 'categories/default.jpg',
            'banner_image' => 'categories/banner.jpg',
            'status' => true,
            'order' => fake()->numberBetween(1, 20),
        ];
    }
}
