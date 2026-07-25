<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\RequisitionApproval;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requester on every decision: approved at a level and still
 * travelling, fully approved and ready for issue, or rejected with the reason.
 */
class RequisitionDecided extends Notification
{
    public function __construct(
        public Requisition $requisition,
        public RequisitionApproval $level,
        public bool $moreLevelsRemain,
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
        $no = $this->requisition->requisition_no;
        $by = $this->level->decidedBy?->name ?? 'an approver';

        if ($this->level->decision === 'reject') {
            $title = "{$no} was rejected";
            $message = "{$by} rejected {$no} at {$this->level->level_name}. Reason: {$this->level->remarks}";
        } elseif ($this->moreLevelsRemain) {
            $title = "{$no} passed {$this->level->level_name}";
            $message = "{$by} approved {$no} at {$this->level->level_name}. It now waits on the next level.";
        } else {
            $title = "{$no} is fully approved";
            $message = "{$by} gave the final approval on {$no}. It is ready for issue.";
        }

        return [
            'type' => 'requisition_decided',
            'requisition_id' => $this->requisition->id,
            'requisition_no' => $no,
            'level_name' => $this->level->level_name,
            'decision' => $this->level->decision,
            'fully_approved' => $this->level->decision === 'approve' && ! $this->moreLevelsRemain,
            'title' => $title,
            'message' => $message,
            'href' => route('requisitions.show', $this->requisition->id),
        ];
    }
}
