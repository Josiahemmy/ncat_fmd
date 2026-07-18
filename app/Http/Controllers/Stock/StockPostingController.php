<?php

namespace App\Http\Controllers\Stock;

use App\Exceptions\Stock\StockException;
use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\Store;
use App\Services\Stock\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Thin HTTP layer over StockService. Every posting is permission-gated at the
 * route and executed by the engine — this controller never touches the ledger
 * directly. StockExceptions surface as a flash error (422-equivalent for UX).
 */
class StockPostingController extends Controller
{
    public function __construct(protected StockService $stock)
    {
    }

    public function certify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'part_id' => ['required', Rule::exists('parts', 'id')],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'decision' => ['required', Rule::in(['release_to_bonded', 'release_to_dope', 'reject'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->run(fn () => $this->stock->certify(
            part: Part::findOrFail($data['part_id']),
            quantity: (float) $data['quantity'],
            decision: $data['decision'],
            user: $request->user(),
            remarks: $data['remarks'] ?? null,
        ), 'Certification posted.');
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'part_id' => ['required', Rule::exists('parts', 'id')],
            'from_store_id' => ['required', Rule::exists('stores', 'id')],
            'to_store_id' => ['required', 'different:from_store_id', Rule::exists('stores', 'id')],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->run(fn () => $this->stock->transfer(
            part: Part::findOrFail($data['part_id']),
            from: Store::findOrFail($data['from_store_id']),
            to: Store::findOrFail($data['to_store_id']),
            quantity: (float) $data['quantity'],
            user: $request->user(),
            remarks: $data['remarks'] ?? null,
        ), 'Transfer posted.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'part_id' => ['required', Rule::exists('parts', 'id')],
            'store_id' => ['required', Rule::exists('stores', 'id')],
            'delta' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return $this->run(fn () => $this->stock->adjust(
            part: Part::findOrFail($data['part_id']),
            store: Store::findOrFail($data['store_id']),
            delta: (float) $data['delta'],
            reason: $data['reason'],
            user: $request->user(),
        ), 'Adjustment posted.');
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
