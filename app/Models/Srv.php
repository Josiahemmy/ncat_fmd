<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Srv extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'srv_number', 'srv_date', 'destination_store_id', 'supplier', 'lpo_or_petty_cash_ref',
        'head_of_receiving_dept', 'storekeeper', 'status', 'posted_at', 'posted_by_user_id',
        'created_by_user_id', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'srv_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SrvItem::class);
    }

    public function destinationStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
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
            ->logOnly(['srv_number', 'status', 'destination_store_id', 'supplier'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('srv');
    }
}
