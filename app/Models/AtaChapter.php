<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AtaChapter extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'chapter_number', 'title',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['chapter_number', 'title'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ata_chapter');
    }
}
