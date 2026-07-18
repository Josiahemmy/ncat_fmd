<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aircraft;
use App\Models\AircraftType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AircraftController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $aircraft = Aircraft::query()
            ->when($search, fn ($q) => $q->where('registration', 'like', "%{$search}%"))
            ->with('aircraftType:id,name')
            ->orderBy('registration')
            ->get()
            ->map(fn (Aircraft $a) => [
                'id' => $a->id,
                'registration' => $a->registration,
                'aircraft_type_id' => $a->aircraft_type_id,
                'type' => $a->aircraftType?->name,
                'status' => $a->status,
                'notes' => $a->notes,
            ]);

        $types = AircraftType::withCount('aircraft')->orderBy('sort_order')->get()
            ->map(fn (AircraftType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'wo_code' => $t->wo_code,
                'image_path' => $t->image_path,
                'aircraft_count' => $t->aircraft_count,
            ]);

        return Inertia::render('Admin/Fleet/Index', [
            'aircraft' => $aircraft,
            'types' => $types,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAircraft($request);

        $aircraft = Aircraft::create($data);
        activity('aircraft')->causedBy($request->user())->performedOn($aircraft)
            ->event('created')->log("Added aircraft {$aircraft->registration}");

        return back()->with('success', "Aircraft {$aircraft->registration} added.");
    }

    public function update(Request $request, Aircraft $aircraft): RedirectResponse
    {
        $aircraft->update($this->validateAircraft($request, $aircraft));
        activity('aircraft')->causedBy($request->user())->performedOn($aircraft)
            ->event('updated')->log("Updated aircraft {$aircraft->registration}");

        return back()->with('success', "Aircraft {$aircraft->registration} updated.");
    }

    public function destroy(Request $request, Aircraft $aircraft): RedirectResponse
    {
        $reg = $aircraft->registration;
        $aircraft->delete(); // soft delete
        activity('aircraft')->causedBy($request->user())
            ->event('deleted')->log("Removed aircraft {$reg}");

        return back()->with('success', "Aircraft {$reg} removed.");
    }

    /** @return array<string, mixed> */
    protected function validateAircraft(Request $request, ?Aircraft $aircraft = null): array
    {
        return $request->validate([
            'registration' => ['required', 'string', 'max:20',
                Rule::unique('aircraft', 'registration')->ignore($aircraft?->id)],
            'aircraft_type_id' => ['required', Rule::exists('aircraft_types', 'id')],
            'status' => ['required', Rule::in(['active', 'maintenance', 'retired'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
