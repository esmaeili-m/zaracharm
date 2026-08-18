<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        $admin = User::create([
            'first_name' => 'مهدی',
            'last_name' => 'اسماعیلی',
            'email' => 'admin@zaracharm.ir',
            'mobile' => '09193544391',
            'password' => Hash::make('password123'),
        ]);
        $admin->assignRole('admin');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    }
}
