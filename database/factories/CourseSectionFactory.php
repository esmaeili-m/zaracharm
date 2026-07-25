<?php

namespace Database\Factories;

use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSection>
 */
class CourseSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement([
                    'مقدمه و آشنایی',
                    'مبانی علمی',
                    'آموزش عملی',
                    'نکات تخصصی',
                    'مطالعات موردی',
                    'جمع بندی',
                    'آزمون و ارزیابی',
                    'مباحث تکمیلی',
                ]).' '.fake()->numberBetween(1,20),

            'description' => fake()->paragraph(),

            'sort' => fake()->numberBetween(1,20),

            'status' => true,
        ];
    }
}
