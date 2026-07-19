<?php

namespace App\Http\Controllers\Stock;

use App\Exceptions\Stock\StockException;
use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\Part;
use App\Models\StockBalance;
use App\Models\Store;
use App\Services\Stock\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FuelController extends Controller
{
    public function __construct(
        protected StockService $stock,
        protected \App\Services\Stock\StockNotifier $notifier,
    ) {
    }

    public function index(): Response
    {
        $fuelStore = Store::where('type', 'fuel')->first();

        $fuels = Part::where('is_fuel', true)->orderBy('description')->get()->map(function (Part $p) use ($fuelStore) {
            $level = (float) StockBalance::where('part_id', $p->id)->where('store_id', $fuelStore?->id)->value('quantity');

            return [
                'id' => $p->id,
                'part_number' => $p->part_number,
                'description' => $p->description,
                'level' => $level,
                'reorder_level' => (float) $p->reorder_level,
                'unit_price' => $p->unit_price !== null ? (float) $p->unit_price : null,
            ];
        });

        // Recent fuel ledger with drill-down: receipts link to their SRV (via the
        // polymorphic source, Phase 5), issues link to the aircraft workspace.
        $movements = \App\Models\StockMovement::where('store_id', $fuelStore?->id)
            ->with(['part:id,part_number', 'aircraft:id,registration', 'user:id,name'])
            ->orderByDesc('id')->limit(25)->get()
            ->map(function (\App\Models\StockMovement $m) {
                $link = null;
                if ($m->source_type === \App\Models\Srv::class && $m->source_id) {
                    $link = route('receiving.show', $m->source_id);
                } elseif ($m->aircraft) {
                    $link = route('aircraft.show', $m->aircraft->registration);
                }

                return [
                    'id' => $m->id,
                    'date' => $m->posted_at?->toDateString(),
                    'part_number' => $m->part?->part_number,
                    'direction' => $m->direction,
                    'quantity' => (float) $m->quantity,
                    'type' => $m->movement_type,
                    'aircraft' => $m->aircraft?->registration,
                    'reference' => $m->reference,
                    'user' => $m->user?->name,
                    'link' => $link,
                ];
            });

        return Inertia::render('Stock/Fuel/Index', [
            'fuels' => $fuels,
            'aircraft' => Aircraft::where('status', 'active')->orderBy('registration')->get(['id', 'registration']),
            'movements' => $movements,
        ]);
    }

    public function receive(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'part_id' => ['required', Rule::exists('parts', 'id')],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->run(fn () => $this->stock->fuelReceive(
            part: Part::findOrFail($data['part_id']),
            quantity: (float) $data['quantity'],
            user: $request->user(),
            unitPrice: $data['unit_price'] ?? null,
            reference: $data['reference'] ?? null,
            remarks: $data['remarks'] ?? null,
        ), 'Fuel received.');
    }

    public function issue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'part_id' => ['required', Rule::exists('parts', 'id')],
            'aircraft_id' => ['required', Rule::exists('aircraft', 'id')],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->run(function () use ($data, $request) {
            $part = Part::findOrFail($data['part_id']);
            $this->stock->fuelIssue(
                part: $part,
                quantity: (float) $data['quantity'],
                aircraft: Aircraft::findOrFail($data['aircraft_id']),
                user: $request->user(),
                remarks: $data['purpose'] ?? null,
            );
            $this->notifier->checkReorder($part);
        }, 'Fuel issued.');
    }

    protected function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
