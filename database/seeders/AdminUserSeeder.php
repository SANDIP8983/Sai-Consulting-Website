<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', config('admin.email'))->first()
            ?? User::query()->where('email', config('admin.legacy_email'))->first();

        if ($admin) {
            $admin->update([
                'name' => config('admin.name'),
                'email' => config('admin.email'),
            ]);

            return;
        }

        User::query()->create([
            'name' => config('admin.name'),
            'email' => config('admin.email'),
            'password' => Hash::make(config('admin.password')),
            'email_verified_at' => now(),
        ]);
    }
}
