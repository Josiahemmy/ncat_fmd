<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentCounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentCounterController extends Controller
{
    public function index(): Response
    {
        $counters = DocumentCounter::orderBy('label')->get()
            ->map(fn (DocumentCounter $c) => [
                'id' => $c->id,
                'series' => $c->series,
                'label' => $c->label,
                'prefix' => $c->prefix,
                'next_number' => $c->next_number,
                'padding' => $c->padding,
                'confirmed' => $c->confirmed,
                'notes' => $c->notes,
                'preview' => $c->peek(),
            ]);

        return Inertia::render('Admin/Counters/Index', ['counters' => $counters]);
    }

    public function update(Request $request, DocumentCounter $counter): RedirectResponse
    {
        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:20'],
            'next_number' => ['required', 'integer', 'min:1'],
            'padding' => ['required', 'integer', 'min:0', 'max:12'],
            'confirmed' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $counter->update([
            'prefix' => $data['prefix'] ?? null,
            'next_number' => $data['next_number'],
            'padding' => $data['padding'],
            'confirmed' => $request->boolean('confirmed'),
            'notes' => $data['notes'] ?? null,
        ]);

        activity('document_counter')->causedBy($request->user())->performedOn($counter)
            ->withProperties(['next_number' => $data['next_number']])
            ->event('updated')->log("Updated counter {$counter->label} → next {$counter->peek()}");

        return back()->with('success', "{$counter->label} updated.");
    }
}
