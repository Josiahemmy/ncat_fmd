<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Siv extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'siv_number', 'requisition_for', 'ordered_by', 'ordered_by_date', 'school_section',
        'approved_by', 'approved_by_date', 'entered_by', 'entered_by_date',
        'issued_by', 'issued_by_date', 'received_by', 'received_by_date', 'remark',
        'status', 'posted_at', 'posted_by_user_id', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'ordered_by_date' => 'date',
            'approved_by_date' => 'date',
            'entered_by_date' => 'date',
            'issued_by_date' => 'date',
            'received_by_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SivItem::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['siv_number', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('siv');
    }
}
