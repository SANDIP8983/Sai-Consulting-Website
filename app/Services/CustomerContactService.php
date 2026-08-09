<?php

namespace App\Services;

use App\Models\CustomerRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerContactService
{
    public function update(CustomerRequest $request, array $attributes, User $actor): void
    {
        DB::transaction(function () use ($request, $attributes, $actor): void {
            $locked = CustomerRequest::query()->lockForUpdate()->findOrFail($request->id);
            $values = ['mobile' => $attributes['mobile'], 'whatsapp' => $attributes['whatsapp'] ?: null, 'email' => $attributes['email'] ? strtolower($attributes['email']) : null];
            $changed = collect($values)->filter(fn ($value, $field) => $locked->{$field} !== $value)->keys()->values()->all();
            if ($changed === []) {
                return;
            }
            $old = collect($changed)->mapWithKeys(fn ($field) => [$field => $this->mask($locked->{$field}, $field)])->all();
            $new = collect($changed)->mapWithKeys(fn ($field) => [$field => $this->mask($values[$field], $field)])->all();
            $locked->update($values);
            $locked->contactChangeHistory()->create(['changed_by' => $actor->id, 'changed_fields' => $changed, 'masked_old_values' => $old, 'masked_new_values' => $new, 'changed_at' => now()]);
        });
    }

    private function mask(?string $value, string $field): ?string
    {
        if (blank($value)) {
            return null;
        }
        if ($field === 'email') {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 1).'***@'.$domain;
        }

        return '******'.substr($value, -4);
    }
}
