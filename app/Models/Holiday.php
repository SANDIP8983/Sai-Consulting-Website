<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'holiday_date',
        'title',
        'description',
        'is_recurring',
        'is_closed',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'is_recurring' => 'boolean',
            'is_closed' => 'boolean',
        ];
    }
}
