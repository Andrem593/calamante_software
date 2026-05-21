<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin raíz
        User::updateOrCreate(
            ['email' => 'andres@amdesings.com'],
            [
                'name'     => 'Andrés Admin',
                'email'    => 'andres@amdesings.com',
                'password' => Hash::make('Admin2026!'),
                'role'     => 'superadmin',
            ]
        );

        // Admin de ejemplo
        User::updateOrCreate(
            ['email' => 'admin@calamante.com'],
            [
                'name'     => 'Administrador',
                'email'    => 'admin@calamante.com',
                'password' => Hash::make('Admin2026!'),
                'role'     => 'admin',
            ]
        );
    }
}
