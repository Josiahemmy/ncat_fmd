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
 * canonical ref: FMD/{wo_code}/{MM}/{YY}/{serial}.
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
