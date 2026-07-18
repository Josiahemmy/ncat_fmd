<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(): Response
    {
        $stores = Store::orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Store $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'type' => $s->type,
                'description' => $s->description,
                'is_active' => $s->is_active,
                // The four seeded stores have fixed types; extras are editable.
                'type_locked' => in_array($s->type, ['quarantine', 'bonded', 'dope', 'fuel'], true),
            ]);

        return Inertia::render('Admin/Stores/Index', ['stores' => $stores]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        // New stores added via the UI are always the `general` type.
        $store = Store::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'type' => 'general',
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        activity('store')->causedBy($request->user())->performedOn($store)
            ->event('created')->log("Created store {$store->name}");

        return back()->with('success', "Store {$store->name} created.");
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        // Type is fixed on update — only name/description/active change.
        $store->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        activity('store')->causedBy($request->user())->performedOn($store)
            ->event('updated')->log("Updated store {$store->name}");

        return back()->with('success', "Store {$store->name} updated.");
    }
}
