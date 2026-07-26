<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vendor extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const TYPES = ['supplier', 'repair_organization', 'both'];

    protected $fillable = [
        'name', 'type', 'address', 'country', 'email', 'phone',
        'contact_person', 'notes', 'is_active', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function repairOrders(): HasMany
    {
        return $this->hasMany(RepairOrder::class);
    }

    /** Can this vendor be named on a Repair Order? */
    public function canRepair(): bool
    {
        return in_array($this->type, ['repair_organization', 'both'], true);
    }

    public function scopeRepairCapable(Builder $query): Builder
    {
        return $query->whereIn('type', ['repair_organization', 'both']);
    }

    /** Any order at all, which is what blocks a hard delete. */
    public function orderCount(): int
    {
        return $this->purchaseOrders()->withTrashed()->count()
            + $this->repairOrders()->withTrashed()->count();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'repair_organization' => 'Repair organisation',
            'both' => 'Supplier and repair organisation',
            default => 'Supplier',
        };
    }

    /** The address as printed on the order forms, one entry per line. */
    public function addressLines(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', (string) $this->address),
        ), fn ($line) => $line !== ''));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'country', 'email', 'phone', 'contact_person', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('vendor');
    }
}
