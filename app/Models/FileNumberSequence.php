<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileNumberSequence extends Model
{
    protected $fillable = ['year', 'last_number'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'last_number' => 'integer'];
    }
}
