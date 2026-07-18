<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AircraftType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AircraftTypeController extends Controller
{
    /** Types are the fixed fleet set — name & WO code editable, no delete. */
    public function update(Request $request, AircraftType $type): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'wo_code' => ['nullable', 'string', 'max:20'],
        ]);

        $type->update($data);
        activity('aircraft_type')->causedBy($request->user())->performedOn($type)
            ->event('updated')->log("Updated aircraft type {$type->name}");

        return back()->with('success', "Aircraft type {$type->name} updated.");
    }
}
