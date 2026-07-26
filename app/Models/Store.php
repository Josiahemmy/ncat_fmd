<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Store extends Model
{
    use HasFactory, LogsActivity;

    /** Store types the loan engine owns. Neither is picked by hand in the UI. */
    public const LOAN_OUT = 'loan_out';

    public const LOAN_IN = 'loan_in';

    protected $fillable = [
        'name', 'slug', 'type', 'owned', 'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'owned' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Ids of the stores holding stock NCAT actually owns. Every value,
     * stock-summary and reorder-alert query scopes to these, which is what
     * keeps borrowed stock from inflating NCAT's own numbers (spec §12.7).
     *
     * @return array<int, int>
     */
    public static function ownedIds(): array
    {
        return static::query()->where('owned', true)->pluck('id')->all();
    }

    /** The holding location for NCAT stock currently lent out. */
    public static function loanOut(): ?self
    {
        return static::where('type', self::LOAN_OUT)->first();
    }

    /** The holding location for stock borrowed from another organisation. */
    public static function loanIn(): ?self
    {
        return static::where('type', self::LOAN_IN)->first();
    }

    /** A loan-engine store: created by the seeder, never edited by hand. */
    public function isLoanStore(): bool
    {
        return in_array($this->type, [self::LOAN_OUT, self::LOAN_IN], true);
    }

    /**
     * A location the engine posts through, not a room a clerk works in.
     *
     * Quarantine moves only on certification; the two loan stores move only on
     * a loan or a return. Raising a requisition, an issue or a hand transfer
     * against any of them would shift stock without the record that explains
     * why it moved, so the store page offers none of those actions.
     */
    public function isSystemLocation(): bool
    {
        return $this->type === 'quarantine' || $this->isLoanStore();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'type', 'owned', 'description', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('store');
    }
}
