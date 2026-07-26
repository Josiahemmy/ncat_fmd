<?php

namespace App\Http\Controllers;

use App\Models\AircraftType;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\Reports\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports module (spec §7 module 10). Filterable screens with matching PDF and
 * streamed-CSV exports. Screen tables are capped for responsiveness; CSV export
 * streams the full filtered set row-by-row (no memory blow-up on the ledger).
 */
class ReportsController extends Controller
{
    /** On-screen row cap; exports are not capped for CSV. */
    protected const SCREEN_LIMIT = 500;

    /** PDF is capped — very large sets should be pulled as CSV. */
    protected const PDF_LIMIT = 2000;

    public function __construct(protected ReportService $reports)
    {
    }

    public function index(Request $request)
    {
        return Inertia::render('Reports/Index', [
            'reports' => collect(ReportService::REPORTS)
                ->filter(fn ($title, $key) => $this->permitted($request, $key))
                ->map(fn ($title, $key) => [
                    'key' => $key,
                    'title' => $title,
                ])->values(),
        ]);
    }

    public function show(Request $request, string $report)
    {
        abort_unless($this->reports->exists($report), 404);
        abort_unless($this->permitted($request, $report), 403);

        $filters = $this->filters($request);
        $rows = $this->reports->rows($report, $filters);

        return Inertia::render('Reports/Show', [
            'report' => $report,
            'title' => $this->reports->titleFor($report),
            'columns' => $this->reports->columns($report),
            'rows' => $rows->take(self::SCREEN_LIMIT)->values()->all(),
            'truncatedAt' => self::SCREEN_LIMIT,
            'filters' => $filters,
            'options' => $this->options(),
        ]);
    }

    public function export(Request $request, string $report)
    {
        abort_unless($this->reports->exists($report), 404);
        abort_unless($this->permitted($request, $report), 403);

        $filters = $this->filters($request);
        $format = $request->string('format')->toString() ?: 'csv';
        $columns = $this->reports->columns($report);
        $title = $this->reports->titleFor($report);

        if ($format === 'pdf') {
            $rows = $this->reports->rows($report, $filters)->take(self::PDF_LIMIT)->values()->all();

            return Pdf::loadView('pdf.report', [
                'crest' => public_path('brand/ncat-logo.png'),
                'title' => $title,
                'columns' => $columns,
                'rows' => $rows,
                'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            ])->setPaper('a4', 'landscape')->stream(str($title)->slug().'.pdf');
        }

        return $this->streamCsv($report, $title, $columns, $filters);
    }

    protected function streamCsv(string $report, string $title, array $columns, array $filters): StreamedResponse
    {
        $rows = $this->reports->rows($report, $filters);
        $filename = str($title)->slug().'-'.now()->format('Ymd-His').'.csv';

        return Response::stream(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders ₦ correctly
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => $row[$c] ?? '', $columns));
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /** @return array<string, mixed> */
    protected function filters(Request $request): array
    {
        return [
            'store' => $request->integer('store') ?: null,
            'part' => $request->integer('part') ?: null,
            'type' => $request->string('type')->toString() ?: null,
            'user' => $request->integer('user') ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'state' => $request->string('state')->toString() ?: null,
            'scope' => $request->string('scope')->toString() ?: null,
            'days' => $request->integer('days') ?: null,
            'search' => $request->string('search')->toString() ?: null,
            'direction' => $request->string('direction')->toString() ?: null,
            'vendor' => $request->integer('vendor') ?: null,
        ];
    }

    /** `reports.view` plus, for some reports, the module's own view permission. */
    protected function permitted(Request $request, string $report): bool
    {
        $extra = $this->reports->permissionFor($report);

        return $extra === null || (bool) $request->user()?->can($extra);
    }

    /** Filter dropdown sources for the report screens. */
    protected function options(): array
    {
        return [
            'stores' => Store::orderBy('sort_order')->get(['id', 'name']),
            'types' => AircraftType::orderBy('sort_order')->get(['id', 'name']),
            'vendors' => Vendor::orderBy('name')->get(['id', 'name']),
            'movementTypes' => ['opening_balance', 'receiving', 'certification_transfer', 'transfer', 'issue', 'fuel_receive', 'fuel_issue', 'adjustment', 'return', 'loan_out', 'loan_return', 'loan_in', 'loan_in_return'],
        ];
    }
}
