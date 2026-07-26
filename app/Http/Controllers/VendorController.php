<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\RepairOrder;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendors (spec §12.4). The index carries its own add-new form above the list,
 * which is the layout management asked for: most vendor records are created
 * while looking at the list to check the supplier is not already there.
 */
class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'type' => $request->string('type')->toString(),
            'country' => $request->string('country')->toString(),
            'active' => $request->string('active')->toString(),
            'search' => $request->string('search')->toString(),
        ];

        $vendors = Vendor::query()
            ->withCount(['purchaseOrders', 'repairOrders'])
            ->when($filters['type'], fn ($q, $v) => $q->where('type', $v))
            ->when($filters['country'], fn ($q, $v) => $q->where('country', $v))
            ->when($filters['active'] !== '', fn ($q) => $q->where('is_active', $filters['active'] === 'active'))
            ->when($filters['search'], fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$v}%")
                ->orWhere('contact_person', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->orderBy('name')->get()
            ->map(fn (Vendor $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'type' => $v->type,
                'type_label' => $v->typeLabel(),
                'country' => $v->country,
                'email' => $v->email,
                'phone' => $v->phone,
                'contact_person' => $v->contact_person,
                'is_active' => $v->is_active,
                'order_count' => $v->purchase_orders_count + $v->repair_orders_count,
            ]);

        return Inertia::render('Vendors/Index', [
            'vendors' => $vendors,
            'filters' => $filters,
            'countries' => Vendor::query()->whereNotNull('country')
                ->distinct()->orderBy('country')->pluck('country'),
            'can' => [
                'manage' => $request->user()->can('vendors.manage'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = Vendor::create($this->validated($request));

        return redirect()->route('vendors.index')
            ->with('success', "Vendor {$vendor->name} added.");
    }

    public function show(Request $request, Vendor $vendor): Response
    {
        return Inertia::render('Vendors/Show', [
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'type' => $vendor->type,
                'type_label' => $vendor->typeLabel(),
                'address' => $vendor->address,
                'address_lines' => $vendor->addressLines(),
                'country' => $vendor->country,
                'email' => $vendor->email,
                'phone' => $vendor->phone,
                'contact_person' => $vendor->contact_person,
                'notes' => $vendor->notes,
                'is_active' => $vendor->is_active,
                'can_repair' => $vendor->canRepair(),
            ],
            'purchaseOrders' => $vendor->purchaseOrders()->orderByDesc('id')->get()
                ->map(fn (PurchaseOrder $o) => [
                    'id' => $o->id,
                    'number' => $o->po_number,
                    'order_date' => $o->order_date?->toDateString(),
                    'status' => $o->status,
                ]),
            'repairOrders' => $vendor->repairOrders()->orderByDesc('id')->get()
                ->map(fn (RepairOrder $o) => [
                    'id' => $o->id,
                    'number' => $o->ro_number,
                    'order_date' => $o->order_date?->toDateString(),
                    'status' => $o->status,
                ]),
            'can' => [
                'manage' => $request->user()->can('vendors.manage'),
            ],
        ]);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($this->validated($request, $vendor));

        return redirect()->route('vendors.show', $vendor)
            ->with('success', "Vendor {$vendor->name} updated.");
    }

    /**
     * A vendor named on any order is never hard-deleted: the orders would lose
     * the party they were addressed to. Deactivating keeps the paper trail
     * readable and takes the vendor out of every picker.
     */
    public function destroy(Vendor $vendor): RedirectResponse
    {
        if ($vendor->orderCount() > 0) {
            return back()->withErrors([
                'vendor' => 'This vendor is named on existing orders and cannot be deleted. Deactivate it instead.',
            ]);
        }

        $vendor->delete();

        return redirect()->route('vendors.index')
            ->with('success', "Vendor {$vendor->name} deleted.");
    }

    protected function validated(Request $request, ?Vendor $vendor = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('vendors', 'name')->ignore($vendor?->id)->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::in(Vendor::TYPES)],
            'address' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
    }
}
