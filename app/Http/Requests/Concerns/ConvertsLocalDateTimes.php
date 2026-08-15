<?php

namespace App\Http\Requests\Concerns;

use App\Support\IndiaDateTime;

trait ConvertsLocalDateTimes
{
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        foreach ($this->localDateTimeFields() as $field) {
            if (filled($validated[$field] ?? null)) {
                $validated[$field] = IndiaDateTime::localInputToStorage($validated[$field]);
            }
        }

        return $key === null ? $validated : data_get($validated, $key, $default);
    }

    abstract protected function localDateTimeFields(): array;
}
