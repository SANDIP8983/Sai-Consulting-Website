<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffAccountService
{
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->protectLastActiveSuperAdmin($locked, $attributes);
            $locked->update($attributes);

            return $locked->fresh();
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    public function canDelete(User $actor, User $target): bool
    {
        return $actor->is_active && $actor->isSuperAdmin() && ! $actor->is($target)
            && ! ($target->isSuperAdmin() && $target->is_active && $this->activeSuperAdminCount() <= 1)
            && ! $this->hasHistoricalReferences($target);
    }

    public function delete(User $actor, User $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            $lockedActor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $lockedTarget = User::query()->lockForUpdate()->findOrFail($target->id);

            if (! $lockedActor->is_active || ! $lockedActor->isSuperAdmin()) {
                abort(403);
            }
            if ($lockedActor->is($lockedTarget)) {
                throw ValidationException::withMessages(['delete' => 'You cannot permanently delete your own account.']);
            }
            if ($lockedTarget->isSuperAdmin() && $lockedTarget->is_active && $this->activeSuperAdminCount(true) <= 1) {
                throw ValidationException::withMessages(['delete' => 'The last active Super Admin cannot be deleted.']);
            }
            if ($this->hasHistoricalReferences($lockedTarget)) {
                throw ValidationException::withMessages([
                    'delete' => 'આ User સાથે અગાઉના રેકોર્ડ જોડાયેલા હોવાથી તેને Delete કરી શકાતો નથી. Userને Inactive કરો.',
                ]);
            }

            $lockedTarget->delete();
        });
    }

    public function hasHistoricalReferences(User $user): bool
    {
        foreach (config('permissions.user_reference_columns') as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column) && DB::table($table)->where($column, $user->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function protectLastActiveSuperAdmin(User $user, array $attributes): void
    {
        $willRemainActiveSuperAdmin = ($attributes['role'] ?? $user->role) === 'super_admin'
            && (bool) ($attributes['is_active'] ?? $user->is_active);

        $activeSuperAdmins = User::query()->where('role', 'super_admin')->where('is_active', true)->lockForUpdate()->get();

        if ($user->isSuperAdmin() && $user->is_active && ! $willRemainActiveSuperAdmin
            && $activeSuperAdmins->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'The last active Super Admin cannot be demoted or deactivated.',
            ]);
        }
    }

    private function activeSuperAdminCount(bool $lock = false): int
    {
        $query = User::query()->where('role', 'super_admin')->where('is_active', true);

        return ($lock ? $query->lockForUpdate()->get() : $query->get())->count();
    }
}
