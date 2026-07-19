<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoState extends Model
{
    protected $fillable = ['active', 'counters_snapshot', 'seeded_at'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'counters_snapshot' => 'array',
            'seeded_at' => 'datetime',
        ];
    }
}
