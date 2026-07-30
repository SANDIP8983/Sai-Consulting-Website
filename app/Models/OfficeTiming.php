<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeTiming extends Model
{
    protected $fillable = [
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_closed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }
}
