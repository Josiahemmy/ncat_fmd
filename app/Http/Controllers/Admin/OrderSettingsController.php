<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Documents\OrderSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The blocks printed on the Purchase and Repair Order letterheads: the address,
 * the two named contacts, the prepared-by line, and the NOTE text. Editable
 * here so the department can correct a name or an email without a deploy.
 */
class OrderSettingsController extends Controller
{
    public function __construct(protected OrderSettings $settings)
    {
    }

    public function edit(): Response
    {
        return Inertia::render('Admin/OrderDocuments/Index', [
            'settings' => $this->settings->all(),
            'defaults' => $this->settings->defaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'email_line_1' => ['nullable', 'string', 'max:255'],
            'email_line_2' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'contact_1_name' => ['nullable', 'string', 'max:255'],
            'contact_1_email' => ['nullable', 'string', 'max:255'],
            'contact_2_name' => ['nullable', 'string', 'max:255'],
            'contact_2_email' => ['nullable', 'string', 'max:255'],
            'po_prepared_by' => ['nullable', 'string', 'max:255'],
            'ro_prepared_by' => ['nullable', 'string', 'max:255'],
            'po_note' => ['nullable', 'string', 'max:2000'],
            'ro_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->settings->save($data);

        activity('app_setting')->causedBy($request->user())
            ->event('updated')->log('Updated the order document letterhead and contacts');

        return back()->with('success', 'Order document settings saved.');
    }
}
