<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AircraftType extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name', 'slug', 'wo_code', 'image_path', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function aircraft(): HasMany
    {
        return $this->hasMany(Aircraft::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'wo_code', 'image_path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('aircraft_type');
    }
}
