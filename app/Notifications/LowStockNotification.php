<?php

namespace App\Notifications;

use App\Models\Part;
use Illuminate\Notifications\Notification;

/**
 * Written to the database channel when a posting leaves a part at or below its
 * reorder level. Consumed by the notification bell / Phase 4 dashboard.
 */
class LowStockNotification extends Notification
{
    public function __construct(
        public Part $part,
        public float $onHand,
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock',
            'part_id' => $this->part->id,
            'part_number' => $this->part->part_number,
            'description' => $this->part->description,
            'on_hand' => $this->onHand,
            'reorder_level' => (float) $this->part->reorder_level,
            'message' => "{$this->part->part_number} is at/below reorder ({$this->onHand} ≤ {$this->part->reorder_level})",
        ];
    }
}
