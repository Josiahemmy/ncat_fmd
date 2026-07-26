<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShipmentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The suggested shipment statuses (spec §12.6). Editing this list changes what
 * the add-event picker offers; it never changes what an event already says, and
 * it never stops a clerk typing something the list does not contain.
 */
class ShipmentStatusController extends Controller
{
    public function __construct(protected ShipmentSettings $settings)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('Admin/ShipmentStatuses/Index', [
            'settings' => $this->settings->all(),
            'defaults' => $this->settings->defaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['nullable', 'string', 'max:255'],
            'arrival_status' => ['required', 'string', 'max:255'],
        ]);

        // The list is ordered, and `validated()` assembles its result rule by
        // rule rather than copying the input array, so the client's own indexes
        // are what restore the order the admin arranged.
        ksort($data['statuses']);

        $this->settings->save($data);

        activity('app_setting')->causedBy($request->user())
            ->event('updated')->log('Updated the suggested shipment status list');

        return back()->with('success', 'Shipment statuses saved.');
    }
}
