<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Models\Srv;
use App\Models\Store;
use App\Services\Documents\DocumentNumberService;
use App\Services\Documents\SrvService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SrvController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = Srv::query()
            ->with('destinationStore:id,name')
            ->when($request->string('status')->toString(), fn ($q, $v) => $q->where('status', $v))
            ->when($request->string('search')->toString(), fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('srv_number', 'like', "%{$v}%")->orWhere('supplier', 'like', "%{$v}%")))
            ->orderByDesc('id')->get()
            ->map(fn (Srv $s) => [
                'id' => $s->id,
                'srv_number' => $s->srv_number,
                'srv_date' => $s->srv_date?->toDateString(),
                'supplier' => $s->supplier,
                'destination' => $s->destinationStore?->name,
                'status' => $s->status,
            ]);

        return Inertia::render('Srv/Index', [
            'srvs' => $rows,
            'filters' => ['status' => $request->string('status')->toString(), 'search' => $request->string('search')->toString()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Srv/Create', [
            'parts' => $this->partList(),
            'stores' => Store::orderBy('sort_order')->get(['id', 'name', 'slug', 'type']),
            'quarantineId' => Store::where('type', 'quarantine')->value('id'),
            'fuelStoreId' => Store::where('type', 'fuel')->value('id'),
            'nextNumber' => app(DocumentNumberService::class)->peek('srv'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $srv = Srv::create([
            'srv_number' => app(DocumentNumberService::class)->reserve('srv'),
            'srv_date' => $data['srv_date'],
            'destination_store_id' => $data['destination_store_id'],
            'supplier' => $data['supplier'] ?? null,
            'lpo_or_petty_cash_ref' => $data['lpo_or_petty_cash_ref'] ?? null,
            'head_of_receiving_dept' => $data['head_of_receiving_dept'] ?? null,
            'storekeeper' => $data['storekeeper'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ]);

        $this->syncItems($srv, $data['items']);

        return redirect()->route('receiving.show', $srv)->with('success', "SRV {$srv->srv_number} saved as draft.");
    }

    public function show(Srv $srv): Response
    {
        $srv->load(['items.part:id,part_number,description,is_serialized,has_shelf_life,is_fuel', 'destinationStore:id,name,type', 'postedBy:id,name']);

        return Inertia::render('Srv/Show', [
            'srv' => [
                'id' => $srv->id,
                'srv_number' => $srv->srv_number,
                'srv_date' => $srv->srv_date?->toDateString(),
                'destination' => $srv->destinationStore?->name,
                'destination_type' => $srv->destinationStore?->type,
                'supplier' => $srv->supplier,
                'lpo_or_petty_cash_ref' => $srv->lpo_or_petty_cash_ref,
                'head_of_receiving_dept' => $srv->head_of_receiving_dept,
                'storekeeper' => $srv->storekeeper,
                'status' => $srv->status,
                'posted_at' => $srv->posted_at?->toDayDateTimeString(),
                'posted_by' => $srv->postedBy?->name,
                'remarks' => $srv->remarks,
                'items' => $srv->items->map(fn ($i) => [
                    'id' => $i->id, 'line_no' => $i->line_no,
                    'part_number' => $i->part->part_number, 'description' => $i->part->description,
                    'quantity' => (float) $i->quantity, 'supplier_details' => $i->supplier_details,
                    'fol_no' => $i->fol_no, 'rate' => $i->rate, 'amount' => $i->amount,
                    'invoice_no' => $i->invoice_no, 'acct_code' => $i->acct_code,
                    'batch_no' => $i->batch_no, 'batch_year' => $i->batch_year,
                    'expiry_date' => $i->expiry_date?->toDateString(), 'serials' => $i->serials,
                ]),
            ],
        ]);
    }

    public function update(Request $request, Srv $srv): RedirectResponse
    {
        abort_if($srv->isPosted(), 422, 'A posted SRV is read-only.');
        $data = $this->validated($request);

        $srv->update($data);
        $srv->items()->delete();
        $this->syncItems($srv, $data['items']);

        return redirect()->route('receiving.show', $srv)->with('success', "SRV {$srv->srv_number} updated.");
    }

    public function post(Request $request, Srv $srv, SrvService $service): RedirectResponse
    {
        abort_if($srv->isPosted(), 422, 'This SRV has already been posted.');

        $srv->load('items.part');
        if ($errors = $service->validationErrors($srv)) {
            throw ValidationException::withMessages($errors);
        }

        $service->post($srv, $request->user());

        return redirect()->route('receiving.show', $srv)
            ->with('success', "SRV {$srv->srv_number} posted — stock received into {$srv->destinationStore->name}.");
    }

    protected function syncItems(Srv $srv, array $items): void
    {
        foreach (array_values($items) as $n => $item) {
            $srv->items()->create([
                'line_no' => $n + 1,
                'part_id' => $item['part_id'],
                'quantity' => $item['quantity'],
                'supplier_details' => $item['supplier_details'] ?? null,
                'fol_no' => $item['fol_no'] ?? null,
                'rate' => $item['rate'] ?? null,
                'amount' => $item['amount'] ?? null,
                'invoice_no' => $item['invoice_no'] ?? null,
                'acct_code' => $item['acct_code'] ?? null,
                'batch_no' => $item['batch_no'] ?? null,
                'batch_year' => $item['batch_year'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
                'serials' => array_values(array_filter((array) ($item['serials'] ?? []))),
            ]);
        }
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'srv_date' => ['required', 'date'],
            'destination_store_id' => ['required', 'exists:stores,id'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'lpo_or_petty_cash_ref' => ['nullable', 'string', 'max:255'],
            'head_of_receiving_dept' => ['nullable', 'string', 'max:255'],
            'storekeeper' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'exists:parts,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.supplier_details' => ['nullable', 'string', 'max:500'],
            'items.*.fol_no' => ['nullable', 'string', 'max:100'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.invoice_no' => ['nullable', 'string', 'max:100'],
            'items.*.acct_code' => ['nullable', 'string', 'max:100'],
            'items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'items.*.batch_year' => ['nullable', 'string', 'max:10'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*' => ['nullable', 'string', 'max:100'],
        ]);
    }

    protected function partList()
    {
        return Part::orderBy('part_number')->get([
            'id', 'part_number', 'description', 'is_serialized', 'has_shelf_life', 'is_fuel',
        ]);
    }
}
