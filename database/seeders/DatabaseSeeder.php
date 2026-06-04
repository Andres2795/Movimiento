<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate([
            'email' => config('auth.default_admin.email'),
        ], [
            'name' => config('auth.default_admin.name'),
            'password' => config('auth.default_admin.password'),
            'role' => 'administrador',
            'is_active' => true,
        ]);
    }
}
