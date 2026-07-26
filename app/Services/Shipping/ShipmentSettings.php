<?php

namespace App\Services\Shipping;

use App\Models\AppSetting;

/**
 * The admin-editable list of suggested shipment statuses (spec §12.6).
 *
 * These are suggestions, not a vocabulary. The add-event form offers them and
 * accepts anything typed over them, because a consignment can stall somewhere
 * nobody anticipated and forcing the clerk to pick the nearest wrong label
 * would make the timeline lie.
 *
 * `arrival_status` is the one entry with meaning attached: it is what the form
 * pre-ticks the "arrived at NCAT" box for. The flag on the event itself is what
 * actually closes the shipment, so renaming this label cannot break arrivals
 * already recorded.
 */
class ShipmentSettings
{
    public const KEY = 'shipment_statuses';

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'statuses' => [
                'Shipped',
                'Arrived at local port',
                'Cleared customs',
                'Picked up by local courier',
                'In transit to NCAT',
                'Arrived at NCAT',
            ],
            'arrival_status' => 'Arrived at NCAT',
        ];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $stored = AppSetting::find(self::KEY)?->value ?? [];

        return [
            'statuses' => array_values(array_filter(
                $stored['statuses'] ?? $this->defaults()['statuses'],
                fn ($s) => is_string($s) && trim($s) !== '',
            )),
            'arrival_status' => $stored['arrival_status'] ?? $this->defaults()['arrival_status'],
        ];
    }

    /** @return array<int, string> */
    public function statuses(): array
    {
        return $this->all()['statuses'];
    }

    public function arrivalStatus(): string
    {
        return $this->all()['arrival_status'];
    }

    /** @param  array<string, mixed>  $values */
    public function save(array $values): void
    {
        AppSetting::updateOrCreate(
            ['key' => self::KEY],
            ['value' => [
                'statuses' => array_values(array_filter(
                    array_map('trim', (array) ($values['statuses'] ?? [])),
                    fn ($s) => $s !== '',
                )),
                'arrival_status' => trim((string) ($values['arrival_status'] ?? $this->defaults()['arrival_status'])),
            ]],
        );
    }
}
