<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Aircraft extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    // "aircraft" is both singular and plural — pin the table name explicitly.
    protected $table = 'aircraft';

    protected $fillable = [
        'registration', 'aircraft_type_id', 'status', 'notes',
    ];

    public function aircraftType(): BelongsTo
    {
        return $this->belongsTo(AircraftType::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['registration', 'aircraft_type_id', 'status', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('aircraft');
    }
}
