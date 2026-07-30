<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\OfficeTiming;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SettingsService
{
    /**
     * Return a form-friendly value array for a configuration group.
     *
     * @return array<string, string|null>
     */
    public function websiteSettings(): array
    {
        return $this->settingsForGroup('website');
    }

    /**
     * @return array<string, string|null>
     */
    public function officeSettings(): array
    {
        return $this->settingsForGroup('office');
    }

    /**
     * @return array<string, string|null>
     */
    public function contactSettings(): array
    {
        return $this->settingsForGroup('contact');
    }

    /**
     * @param array<string, string|null> $values
     */
    public function updateWebsiteSettings(array $values): void
    {
        $this->updateGroup('website', $values);
    }

    /**
     * @param array<string, string|null> $values
     */
    public function updateOfficeSettings(array $values): void
    {
        $this->updateGroup('office', $values);
    }

    /**
     * @param array<string, string|null> $values
     */
    public function updateContactSettings(array $values): void
    {
        $this->updateGroup('contact', $values);
    }

    /**
     * Return each day in the weekly schedule, including days not configured yet.
     *
     * @return Collection<int, array{day_of_week: int, opens_at: string|null, closes_at: string|null, is_closed: bool, notes: string|null}>
     */
    public function officeTimings(): Collection
    {
        $existingTimings = OfficeTiming::query()
            ->orderBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        return collect(range(0, 6))->map(function (int $dayOfWeek) use ($existingTimings): array {
            /** @var OfficeTiming|null $timing */
            $timing = $existingTimings->get($dayOfWeek);

            return [
                'day_of_week' => $dayOfWeek,
                'opens_at' => $timing?->opens_at,
                'closes_at' => $timing?->closes_at,
                'is_closed' => $timing?->is_closed ?? false,
                'notes' => $timing?->notes,
            ];
        });
    }

    /**
     * @param array<int, array{day_of_week: int, opens_at: string|null, closes_at: string|null, is_closed: bool, notes: string|null}> $timings
     */
    public function updateOfficeTimings(array $timings): void
    {
        DB::transaction(function () use ($timings): void {
            foreach ($timings as $timing) {
                OfficeTiming::query()->updateOrCreate(
                    ['day_of_week' => $timing['day_of_week']],
                    [
                        'opens_at' => $timing['is_closed'] ? null : $timing['opens_at'],
                        'closes_at' => $timing['is_closed'] ? null : $timing['closes_at'],
                        'is_closed' => $timing['is_closed'],
                        'notes' => $timing['notes'],
                    ],
                );
            }
        });
    }

    /**
     * @return Collection<int, Holiday>
     */
    public function holidays(): Collection
    {
        return Holiday::query()
            ->orderBy('holiday_date')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function createHoliday(array $attributes): Holiday
    {
        return Holiday::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateHoliday(Holiday $holiday, array $attributes): void
    {
        $holiday->update($attributes);
    }

    public function deleteHoliday(Holiday $holiday): void
    {
        $holiday->delete();
    }

    /**
     * @return array<string, string|null>
     */
    private function settingsForGroup(string $group): array
    {
        $definitions = $this->definitionsForGroup($group);
        $storedValues = Setting::query()
            ->where('setting_group', $group)
            ->pluck('setting_value', 'setting_key');

        $values = [];

        foreach ($definitions as $field => $definition) {
            $values[$field] = $storedValues->get($definition['key']);
        }

        return $values;
    }

    /**
     * @param array<string, string|null> $values
     */
    private function updateGroup(string $group, array $values): void
    {
        $definitions = $this->definitionsForGroup($group);

        DB::transaction(function () use ($group, $values, $definitions): void {
            foreach ($definitions as $field => $definition) {
                Setting::query()->updateOrCreate(
                    ['setting_key' => $definition['key']],
                    [
                        'setting_value' => $values[$field] ?? null,
                        'value_type' => $definition['type'],
                        'setting_group' => $group,
                        'is_public' => $definition['is_public'],
                    ],
                );
            }
        });
    }

    /**
     * @return array<string, array{key: string, type: string, is_public: bool}>
     */
    private function definitionsForGroup(string $group): array
    {
        return match ($group) {
            'website' => [
                'website_name' => ['key' => 'website.name', 'type' => 'string', 'is_public' => true],
                'website_status' => ['key' => 'website.status', 'type' => 'string', 'is_public' => true],
                'maintenance_message' => ['key' => 'website.maintenance_message', 'type' => 'string', 'is_public' => true],
            ],
            'office' => [
                'office_name' => ['key' => 'office.name', 'type' => 'string', 'is_public' => true],
                'address_line_1' => ['key' => 'office.address_line_1', 'type' => 'string', 'is_public' => true],
                'address_line_2' => ['key' => 'office.address_line_2', 'type' => 'string', 'is_public' => true],
                'city' => ['key' => 'office.city', 'type' => 'string', 'is_public' => true],
                'state' => ['key' => 'office.state', 'type' => 'string', 'is_public' => true],
                'postal_code' => ['key' => 'office.postal_code', 'type' => 'string', 'is_public' => true],
                'timezone' => ['key' => 'office.timezone', 'type' => 'string', 'is_public' => false],
            ],
            'contact' => [
                'public_email' => ['key' => 'contact.email', 'type' => 'string', 'is_public' => true],
                'public_phone' => ['key' => 'contact.phone', 'type' => 'string', 'is_public' => true],
                'whatsapp_number' => ['key' => 'contact.whatsapp_number', 'type' => 'string', 'is_public' => true],
            ],
            default => throw new \InvalidArgumentException("Unsupported settings group [{$group}]."),
        };
    }
}
