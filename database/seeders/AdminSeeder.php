<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kamisetembang.org'],
            [
                'name' => 'Admin SIBAKA',
                'password' => Hash::make('P@ssw0rd!'),
                'role' => UserRole::Admin,
                'verification_status' => VerificationStatus::Approved,
                'entry_year' => 1998,
                'graduation_year' => 2002,
                'department' => 'Elektronika Komunikasi',
            ]
        );
    }
}
