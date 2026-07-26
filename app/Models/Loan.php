<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A loan in either direction (spec §12.7). `direction = out` means an external
 * party is holding NCAT stock; `direction = in` means NCAT is holding someone
 * else's property.
 *
 * Overdue is derived rather than stored, so it is always true of the data
 * rather than true of the last time a job ran.
 */
class Loan extends Model
{
    use HasFactory, LogsActivity;

    protected $attributes = ['status' => 'on_loan'];

    protected $fillable = [
        'direction', 'vendor_id', 'party_name', 'party_contact',
        'part_id', 'part_serial_id', 'part_batch_id', 'item_description', 'serial_text',
        'quantity', 'from_store_id', 'started_at', 'due_date', 'status',
        'returned_at', 'return_condition',
        'written_off_at', 'written_off_by_user_id', 'write_off_reason',
        'installed_aircraft_id', 'notes', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'due_date' => 'date',
            'returned_at' => 'date',
            'written_off_at' => 'datetime',
            'quantity' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(PartSerial::class, 'part_serial_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PartBatch::class, 'part_batch_id');
    }

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function installedAircraft(): BelongsTo
    {
        return $this->belongsTo(Aircraft::class, 'installed_aircraft_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function writtenOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'written_off_by_user_id');
    }

    public function scopeOutbound(Builder $q): Builder
    {
        return $q->where('direction', 'out');
    }

    public function scopeInbound(Builder $q): Builder
    {
        return $q->where('direction', 'in');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', 'on_loan');
    }

    /** Still out and past its due date. */
    public function scopeOverdue(Builder $q): Builder
    {
        return $q->where('status', 'on_loan')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today());
    }

    public function isOpen(): bool
    {
        return $this->status === 'on_loan';
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue()
            ? (int) round($this->due_date->startOfDay()->diffInDays(today()))
            : 0;
    }

    /** on_loan | returned | written_off, with overdue layered on for display. */
    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function counterparty(): string
    {
        return $this->vendor?->name ?? $this->party_name ?? 'Unnamed party';
    }

    /** What was lent, whether or not it is in the catalogue. */
    public function itemLabel(): string
    {
        $parts = array_filter([
            $this->part?->part_number,
            $this->part?->description ?? $this->item_description,
        ]);

        return $parts === [] ? 'Unspecified item' : implode(' - ', $parts);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['direction', 'status', 'vendor_id', 'party_name', 'quantity', 'due_date', 'returned_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('loan');
    }
}
