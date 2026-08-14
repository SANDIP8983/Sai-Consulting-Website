<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentBlock extends Model
{
    protected $fillable = ['block_date', 'starts_at', 'ends_at', 'full_day', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['block_date' => 'date', 'full_day' => 'boolean'];
    }
}
