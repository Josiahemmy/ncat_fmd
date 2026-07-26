<?php

namespace App\Services\Documents;

use App\Models\AircraftType;
use App\Models\DocumentCounter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Single entry point for reserving document numbers. Every series draws from
 * the row-locked {@see DocumentCounter::reserve()} so numbers are unique and
 * gapless under concurrency (real row locking engages on MySQL; see CI).
 *
 * Plain series (requisition / srv / siv) return their counter's padded serial
 * directly. Work Orders wrap the global running serial in the department's
 * canonical ref: FMD/{wo_code}/{MM}/{YY}/{serial}. Purchase and Repair Orders
 * wrap theirs in the NCAT/FMD/{PO|RO}/TS/... refs printed on those forms.
 */
class DocumentNumberService
{
    /** Reserve the next number for a plain series (requisition|srv|siv). */
    public function reserve(string $series): string
    {
        return $this->counter($series)->reserve();
    }

    /**
     * Reserve a Work Order reference: FMD/{wo_code}/{MM}/{YY}/{serial}.
     *
     * The serial is the global `work_order` counter (one running sequence for
     * the whole fleet); the wo_code comes from the aircraft *type* so the token
     * is always canonical (no TB-9/TB-09 drift). Reserved at creation, never at
     * draft-preview, so abandoned forms cannot leave gaps.
     */
    public function reserveWorkOrder(AircraftType $type, ?CarbonInterface $at = null): string
    {
        $at ??= Carbon::now();
        $serial = $this->counter('work_order')->reserve();

        return sprintf(
            'FMD/%s/%s/%s/%s',
            $type->wo_code,
            $at->format('m'),
            $at->format('y'),
            $serial,
        );
    }

    /**
     * Reserve a Purchase Order reference: NCAT/FMD/PO/TS/{D}/{M}/{serial}.
     *
     * Day and month come from the order's issue date and are NOT zero-padded:
     * the sample reads NCAT/FMD/PO/TS/30/6/307 for 30th June. Reserved when the
     * order leaves draft, so an abandoned draft cannot leave a gap.
     */
    public function reservePurchaseOrder(?CarbonInterface $at = null): string
    {
        $at ??= Carbon::now();

        return sprintf(
            'NCAT/FMD/PO/TS/%d/%d/%s',
            $at->day,
            $at->month,
            $this->counter('purchase_order')->reserve(),
        );
    }

    /**
     * Reserve a Repair Order reference: NCAT/FMD/RO/TS/{MM}/{serial}.
     *
     * Month only, and zero-padded here: the sample reads NCAT/FMD/RO/TS/03/298
     * for 4th March. The two order series pad differently on the paper, so they
     * are formatted separately rather than sharing one helper.
     */
    public function reserveRepairOrder(?CarbonInterface $at = null): string
    {
        $at ??= Carbon::now();

        return sprintf(
            'NCAT/FMD/RO/TS/%s/%s',
            $at->format('m'),
            $this->counter('repair_order')->reserve(),
        );
    }

    /** Non-consuming preview of a plain series' next number. */
    public function peek(string $series): string
    {
        return $this->counter($series)->peek();
    }

    protected function counter(string $series): DocumentCounter
    {
        return DocumentCounter::where('series', $series)->firstOrFail();
    }
}
