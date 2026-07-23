<?php

namespace App\Http\Controllers\Stock;

use App\Exceptions\Stock\StockException;
use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\Store;
use App\Services\Stock\OpeningBalanceImporter;
use App\Services\Stock\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OpeningBalanceController extends Controller
{
    public function __construct(
        protected StockService $stock,
        protected OpeningBalanceImporter $importer,
    ) {
    }

    public function index()
    {
        return Inertia::render('Stock/OpeningBalances/Index', [
            'stores' => Store::orderBy('sort_order')->get(['id', 'name', 'type']),
            'parts' => Part::orderBy('part_number')->get(['id', 'part_number', 'description', 'is_serialized', 'has_shelf_life']),
        ]);
    }

    /** Downloadable CSV template. */
    public function template()
    {
        $headers = ['part_number', 'description', 'ata_chapter', 'store', 'qty', 'unit_price', 'batch_no', 'expiry', 'serials'];
        $example = ['MS29513-014', 'O-Ring Packing', '20', 'bonded', '150', '350', '', '', ''];
        $csv = implode(',', $headers)."\n".implode(',', $example)."\n";

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="opening-balances-template.csv"',
        ]);
    }

    /** Manual single opening-balance entry for an existing part. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'part_id' => ['required', Rule::exists('parts', 'id')],
            'store_id' => ['required', Rule::exists('stores', 'id')],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->stock->openingBalance(
                part: Part::findOrFail($data['part_id']),
                store: Store::findOrFail($data['store_id']),
                quantity: (float) $data['quantity'],
                user: $request->user(),
                unitPrice: $data['unit_price'] ?? null,
                remarks: $data['remarks'] ?? null,
            );
        } catch (StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Opening balance posted.');
    }

    /** CSV dry-run: parse + validate, render the preview. */
    public function preview(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);
        $rows = $this->parseCsv($request->file('file')->getRealPath());

        return Inertia::render('Stock/OpeningBalances/Index', [
            'stores' => Store::orderBy('sort_order')->get(['id', 'name', 'type']),
            'parts' => Part::orderBy('part_number')->get(['id', 'part_number', 'description', 'is_serialized', 'has_shelf_life']),
            'preview' => $this->importer->preview($rows),
            'rows' => $rows,
        ]);
    }

    /** Commit previously-previewed rows. */
    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate(['rows' => ['required', 'array']]);

        $result = $this->importer->import($data['rows'], $request->user());

        if (! $result['committed']) {
            return back()->with('error', 'Import aborted. Some rows are invalid, fix them and retry.');
        }

        return redirect()->route('parts.index')
            ->with('success', "Imported {$result['imported']} opening balances.");
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function parseCsv(string $path): array
    {
        $lines = array_filter(array_map('trim', file($path)));
        if (! $lines) {
            return [];
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));
        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line);
            $rows[] = array_combine($header, array_pad($cells, count($header), ''));
        }

        return $rows;
    }
}
