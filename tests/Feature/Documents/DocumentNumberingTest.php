<?php

namespace Tests\Feature\Documents;

use App\Models\AircraftType;
use App\Models\DocumentCounter;
use App\Services\Documents\DocumentNumberService;
use Database\Seeders\DocumentCounterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DocumentCounterSeeder::class);
    }

    protected function numbers(): DocumentNumberService
    {
        return app(DocumentNumberService::class);
    }

    public function test_work_order_number_uses_wo_code_month_year_and_global_serial(): void
    {
        $type = AircraftType::factory()->create(['wo_code' => 'DA40NG']);
        $at = Carbon::create(2026, 5, 15);

        $ref = $this->numbers()->reserveWorkOrder($type, $at);

        // FMD/{wo_code}/{MM}/{YY}/{serial} — 2-digit month + year, global serial.
        $this->assertSame('FMD/DA40NG/05/26/1344', $ref);
        $this->assertSame(1345, DocumentCounter::where('series', 'work_order')->value('next_number'));
    }

    public function test_work_order_serial_is_global_across_aircraft_types(): void
    {
        $da40 = AircraftType::factory()->create(['wo_code' => 'DA40NG']);
        $da42 = AircraftType::factory()->create(['wo_code' => 'DA42NG']);
        $at = Carbon::create(2026, 6, 1);

        $first = $this->numbers()->reserveWorkOrder($da40, $at);
        $second = $this->numbers()->reserveWorkOrder($da42, $at);

        // One running serial shared by all aircraft types — no per-type sequence.
        $this->assertSame('FMD/DA40NG/06/26/1344', $first);
        $this->assertSame('FMD/DA42NG/06/26/1345', $second);
    }

    public function test_plain_series_use_padded_serials(): void
    {
        // SRV/SIV print 4-digit padded; requisition is unpadded per its counter.
        $this->assertSame('0202', $this->numbers()->reserve('srv'));
        $this->assertSame('0294', $this->numbers()->reserve('siv'));
        $this->assertSame('1002', $this->numbers()->reserve('requisition'));
    }

    public function test_reservations_are_unique_and_gapless_under_repetition(): void
    {
        // Sequential proof of the reserve() invariant. On MySQL CI the underlying
        // lockForUpdate row lock actually engages under concurrency; on sqlite
        // :memory: this proves the arithmetic — no duplicates, no skipped serials.
        $refs = [];
        for ($i = 0; $i < 50; $i++) {
            $refs[] = $this->numbers()->reserve('srv');
        }

        $this->assertCount(50, array_unique($refs), 'reserved numbers must be unique');
        $this->assertSame('0202', $refs[0]);
        $this->assertSame('0251', $refs[49], 'serials must be gapless');
    }

    public function test_counter_continues_after_admin_edit(): void
    {
        // Admin bumps the counter (e.g. to reconcile with the paper pad).
        DocumentCounter::where('series', 'srv')->update(['next_number' => 500]);

        $this->assertSame('0500', $this->numbers()->reserve('srv'));
        $this->assertSame('0501', $this->numbers()->reserve('srv'));
    }

    public function test_reserving_an_unknown_series_fails_loudly(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->numbers()->reserve('does_not_exist');
    }
}
