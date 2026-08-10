<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $configuredUsername = trim((string) config('admin.username'));
        $configuredMobile = trim((string) config('admin.mobile'));
        $configuredPassword = (string) config('admin.password');
        $mobileIsValid = static fn (?string $mobile): bool => preg_match('/\A[6-9][0-9]{9}\z/', (string) $mobile) === 1;

        if ($configuredUsername === '') {
            throw new RuntimeException('ADMIN_USERNAME is required before seeding the initial admin.');
        }

        DB::transaction(function () use ($configuredUsername, $configuredMobile, $configuredPassword, $mobileIsValid): void {
            $admin = User::query()->where('username', $configuredUsername)->lockForUpdate()->first();
            $otherSuperAdmin = User::query()
                ->where('role', 'super_admin')
                ->when($admin, fn ($query) => $query->whereKeyNot($admin->id))
                ->lockForUpdate()
                ->first(['id']);

            if ($otherSuperAdmin) {
                throw new RuntimeException('A conflicting Super Admin already exists. The production bootstrap will not create or replace another Super Admin.');
            }

            if ($admin && $admin->role !== 'super_admin') {
                throw new RuntimeException('ADMIN_USERNAME belongs to an existing non-Super-Admin account. The production bootstrap will not change its role.');
            }

            if ($admin) {
                if (! $mobileIsValid($admin->mobile) && ! $mobileIsValid($configuredMobile)) {
                    throw new RuntimeException('ADMIN_MOBILE is required and must be a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9 before seeding the initial admin.');
                }

                $changes = ['name' => config('admin.name'), 'is_active' => true];
                if (! $mobileIsValid($admin->mobile)) {
                    $changes['mobile'] = $configuredMobile;
                }
                $admin->update($changes);

                return;
            }

            if (! $mobileIsValid($configuredMobile)) {
                throw new RuntimeException('ADMIN_MOBILE is required and must be a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9 before seeding the initial admin.');
            }

            if (trim($configuredPassword) === '') {
                throw new RuntimeException('ADMIN_PASSWORD is required before seeding the initial admin. Configure it securely in the deployment environment; no default password is provided.');
            }

            User::query()->create([
                'name' => config('admin.name'),
                'username' => $configuredUsername,
                'email' => config('admin.email'),
                'mobile' => $configuredMobile,
                'role' => 'super_admin',
                'is_active' => true,
                'password' => Hash::make($configuredPassword),
                'email_verified_at' => now(),
            ]);
        });
    }
}
