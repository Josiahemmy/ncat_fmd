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

        return Inertia::render('Stock/Fuel/Index', [
            'fuels' => $fuels,
            'aircraft' => Aircraft::where('status', 'active')->orderBy('registration')->get(['id', 'registration']),
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
