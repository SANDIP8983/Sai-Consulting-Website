<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', config('admin.username'))->first()
            ?? User::query()->where('email', config('admin.email'))->first()
            ?? User::query()->where('email', config('admin.legacy_email'))->first();

        $configuredMobile = trim((string) config('admin.mobile'));
        $mobileIsValid = static fn (?string $mobile): bool => preg_match('/\A[6-9][0-9]{9}\z/', (string) $mobile) === 1;

        if ($admin) {
            if (! $mobileIsValid($admin->mobile) && ! $mobileIsValid($configuredMobile)) {
                throw new RuntimeException('ADMIN_MOBILE is required and must be a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9 before seeding the initial admin.');
            }

            $changes = [
                'name' => config('admin.name'),
                'username' => config('admin.username'),
                'role' => 'super_admin',
                'is_active' => true,
            ];

            if (! $mobileIsValid($admin->mobile)) {
                $changes['mobile'] = $configuredMobile;
            }

            $admin->update($changes);

            return;
        }

        if (! $mobileIsValid($configuredMobile)) {
            throw new RuntimeException('ADMIN_MOBILE is required and must be a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9 before seeding the initial admin.');
        }

        User::query()->create([
            'name' => config('admin.name'),
            'username' => config('admin.username'),
            'email' => config('admin.email'),
            'mobile' => $configuredMobile,
            'role' => 'super_admin',
            'is_active' => true,
            'password' => Hash::make(config('admin.password')),
            'email_verified_at' => now(),
        ]);
    }
}
