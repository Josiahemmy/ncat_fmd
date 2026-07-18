<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DocumentCounter extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'series', 'label', 'prefix', 'next_number', 'padding', 'confirmed', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'padding' => 'integer',
            'confirmed' => 'boolean',
        ];
    }

    /** Format an arbitrary number with this series' prefix + zero-padding. */
    public function format(int $number): string
    {
        $body = $this->padding > 0
            ? str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT)
            : (string) $number;

        return ($this->prefix ?? '').$body;
    }

    /** The next number as it would be printed, without consuming it. */
    public function peek(): string
    {
        return $this->format($this->next_number);
    }

    /**
     * Atomically consume the next number and return it formatted.
     * Used from Phase 3 when a document is created. Row-locked to avoid
     * duplicate serials under concurrency.
     */
    public function reserve(): string
    {
        return DB::transaction(function () {
            $fresh = static::whereKey($this->getKey())->lockForUpdate()->first();
            $number = $fresh->next_number;
            $fresh->increment('next_number');
            $this->next_number = $number + 1;

            return $this->format($number);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['series', 'label', 'prefix', 'next_number', 'padding', 'confirmed'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('document_counter');
    }
}
