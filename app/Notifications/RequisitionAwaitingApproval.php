<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\RequisitionApproval;
use Illuminate\Notifications\Notification;

/**
 * Sent to the holders of a level's bound role or permission when a requisition
 * arrives at that level and is waiting on them.
 */
class RequisitionAwaitingApproval extends Notification
{
    public function __construct(
        public Requisition $requisition,
        public RequisitionApproval $level,
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
            'type' => 'requisition_awaiting_approval',
            'requisition_id' => $this->requisition->id,
            'requisition_no' => $this->requisition->requisition_no,
            'level_name' => $this->level->level_name,
            'title' => "{$this->requisition->requisition_no} awaits your approval",
            'message' => "{$this->level->level_name} is pending on {$this->requisition->requisition_no} ({$this->requisition->full_description}).",
            'href' => route('requisitions.show', $this->requisition->id),
        ];
    }
}
