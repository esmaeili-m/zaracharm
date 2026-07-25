<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use Illuminate\Support\Facades\DB;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Course::truncate();
        Course::factory()
            ->count(15)
            ->create()
            ->each(function ($course) {

                CourseSection::factory()
                    ->count(rand(5,8))
                    ->create([
                        'course_id' => $course->id,
                    ])
                    ->each(function ($section) {

                        CourseLesson::factory()
                            ->count(rand(8,12))
                            ->create([
                                'course_section_id' => $section->id,
                            ]);
                    });
            });
    }
}
