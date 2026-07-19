<?php

namespace Tests\Feature\Stock;

use App\Exceptions\Stock\InvalidSerialTransitionException;
use App\Models\Aircraft;
use App\Models\PartSerial;
use App\Services\Stock\SerialStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerialStateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function service(): SerialStateService
    {
        return app(SerialStateService::class);
    }

    public function test_install_puts_a_serial_onto_an_aircraft(): void
    {
        $serial = PartSerial::factory()->create(['status' => 'in_store']);
        $aircraft = Aircraft::factory()->create();

        $this->service()->install($serial, $aircraft, position: 'LH MLG');

        $serial->refresh();
        $this->assertSame('installed', $serial->status);
        $this->assertSame($aircraft->id, $serial->current_aircraft_id);
        $this->assertNull($serial->current_store_id);
        $this->assertSame('LH MLG', $serial->position);
    }

    public function test_remove_sends_an_installed_serial_to_repair_and_clears_aircraft(): void
    {
        $aircraft = Aircraft::factory()->create();
        $serial = PartSerial::factory()->installed($aircraft)->create();

        $this->service()->remove($serial, 'at_repair', reason: 'Tyre burst');

        $serial->refresh();
        $this->assertSame('at_repair', $serial->status);
        $this->assertNull($serial->current_aircraft_id);
        $this->assertNull($serial->current_store_id);
    }

    public function test_remove_can_mark_a_serial_unserviceable(): void
    {
        $serial = PartSerial::factory()->installed()->create();

        $this->service()->remove($serial, 'removed_unserviceable');

        $this->assertSame('removed_unserviceable', $serial->fresh()->status);
    }

    public function test_removal_target_must_be_a_valid_removal_state(): void
    {
        $serial = PartSerial::factory()->installed()->create();

        $this->expectException(InvalidSerialTransitionException::class);
        $this->service()->remove($serial, 'installed');
    }

    public function test_an_illegal_transition_is_rejected(): void
    {
        $serial = PartSerial::factory()->create(['status' => 'scrapped']);
        $aircraft = Aircraft::factory()->create();

        $this->expectException(InvalidSerialTransitionException::class);
        $this->service()->install($serial, $aircraft);
    }

    public function test_a_serial_cannot_be_installed_twice(): void
    {
        $serial = PartSerial::factory()->installed()->create();

        $this->expectException(InvalidSerialTransitionException::class);
        $this->service()->install($serial, Aircraft::factory()->create());
    }
}
