<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AtaChapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AtaChapterController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $chapters = AtaChapter::query()
            ->when($search, fn ($q) => $q->where('chapter_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%"))
            ->orderBy('chapter_number')
            ->get(['id', 'chapter_number', 'title']);

        return Inertia::render('Admin/Ata/Index', [
            'chapters' => $chapters,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chapter_number' => ['required', 'string', 'max:10', 'unique:ata_chapters,chapter_number'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $chapter = AtaChapter::create($data);
        activity('ata_chapter')->causedBy($request->user())->performedOn($chapter)
            ->event('created')->log("Added ATA chapter {$chapter->chapter_number}");

        return back()->with('success', "ATA chapter {$chapter->chapter_number} added.");
    }

    public function update(Request $request, AtaChapter $chapter): RedirectResponse
    {
        $data = $request->validate([
            'chapter_number' => ['required', 'string', 'max:10',
                Rule::unique('ata_chapters', 'chapter_number')->ignore($chapter->id)],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $chapter->update($data);
        activity('ata_chapter')->causedBy($request->user())->performedOn($chapter)
            ->event('updated')->log("Updated ATA chapter {$chapter->chapter_number}");

        return back()->with('success', "ATA chapter {$chapter->chapter_number} updated.");
    }

    public function destroy(Request $request, AtaChapter $chapter): RedirectResponse
    {
        $number = $chapter->chapter_number;
        $chapter->delete();
        activity('ata_chapter')->causedBy($request->user())
            ->event('deleted')->log("Deleted ATA chapter {$number}");

        return back()->with('success', "ATA chapter {$number} deleted.");
    }
}
