<?php

namespace Database\Seeders;

use App\Models\RewardLevel;
use Illuminate\Database\Seeder;

class RewardLevelSeeder extends Seeder
{
    public function run(): void
    {
        RewardLevel::query()->delete();

        RewardLevel::create([
            'name' => 'برنزی',
            'min_points' => 0,
            'discount_percent' => 0,
            'color' => 'amber',
            'icon' => 'bronze',
            'description' => 'سطح پایه مانا کلاب',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        RewardLevel::create([
            'name' => 'نقره‌ای',
            'min_points' => 1000,
            'discount_percent' => 5,
            'color' => 'gray',
            'icon' => 'silver',
            'description' => 'ویژه مشتریان فعال مانا کلاب',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        RewardLevel::create([
            'name' => 'طلایی',
            'min_points' => 5000,
            'discount_percent' => 10,
            'color' => 'yellow',
            'icon' => 'gold',
            'description' => 'ویژه مشتریان وفادار',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        RewardLevel::create([
            'name' => 'VIP',
            'min_points' => 8000,
            'discount_percent' => 15,
            'color' => 'purple',
            'icon' => 'vip',
            'description' => 'ویژه مشتریان VIP مانا کلاب',
            'is_active' => true,
            'sort_order' => 4,
        ]);
    }
}
