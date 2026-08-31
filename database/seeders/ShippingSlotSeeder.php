<?php

namespace Database\Seeders;

use App\Models\ShippingSlot;
use Illuminate\Database\Seeder;

class ShippingSlotSeeder extends Seeder
{
    public function run(): void
    {
        $days = [
            ['offset' => 1, 'label' => 'فردا', 'cost' => 0,     'is_holiday' => false],
            ['offset' => 2, 'label' => null,    'cost' => 49000, 'is_holiday' => false],
            ['offset' => 3, 'label' => null,    'cost' => 0,     'is_holiday' => false],
            ['offset' => 4, 'label' => null,    'cost' => 35000, 'is_holiday' => false],
            ['offset' => 5, 'label' => null,    'cost' => 0,     'is_holiday' => true],
            ['offset' => 6, 'label' => null,    'cost' => 40000, 'is_holiday' => false],
            ['offset' => 7, 'label' => null,    'cost' => 40000, 'is_holiday' => false],
        ];

        foreach ($days as $day) {
            ShippingSlot::create([
                'date'       => now()->addDays($day['offset'])->toDateString(),
                'label'      => $day['label'],
                'cost'       => $day['cost'],
                'is_holiday' => $day['is_holiday'],
                'is_active'  => true,
            ]);
        }
    }
}
