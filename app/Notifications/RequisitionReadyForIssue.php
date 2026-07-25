<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Notifications\Notification;

/**
 * Sent to `issues.post` holders when a requisition clears its final approval
 * level. This is the "for issue" alert management asked for: the store officer
 * who raises the SIV learns about the requisition without polling the list.
 */
class RequisitionReadyForIssue extends Notification
{
    public function __construct(public Requisition $requisition)
    {
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
            'type' => 'requisition_ready_for_issue',
            'requisition_id' => $this->requisition->id,
            'requisition_no' => $this->requisition->requisition_no,
            'title' => "{$this->requisition->requisition_no} is ready for issue",
            'message' => "{$this->requisition->requisition_no} ({$this->requisition->full_description}) is fully approved and waiting on a store issue voucher.",
            'href' => route('requisitions.show', $this->requisition->id),
        ];
    }
}
