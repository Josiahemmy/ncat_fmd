<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\RepairOrder;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\Srv;
use App\Models\Store;
use App\Services\Documents\OrderSettings;
use App\Services\Stock\TallyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Number;

/**
 * Server-rendered, paper-exact voucher PDFs (dompdf). Each mirrors the
 * department's real form field-for-field with NCAT branding; signature areas
 * render as blank lines for wet-ink signing of the printout. Data mirrors the
 * matching Show controller so the print files alongside the on-screen record.
 */
class PdfController extends Controller
{
    /**
     * Absolute path to the crest for dompdf (local file, no HTTP fetch).
     *
     * The print crest, not the 1024px web logo. dompdf embeds the source
     * bitmap at full resolution regardless of the height it is drawn at, so the
     * 1024px file put roughly 1.2 MB into every voucher for a mark that prints
     * 46px tall. These are emailed to vendors in Austria and England, so the
     * weight lands on someone else's mailbox. 300px square is about 626 dpi at
     * the printed size, comfortably past what A4 printing resolves.
     */
    protected function crest(): string
    {
        return public_path('brand/ncat-logo-print.png');
    }

    protected function render(string $view, array $data, string $filename)
    {
        $pdf = Pdf::loadView($view, $data + ['crest' => $this->crest()])
            ->setPaper('a4', 'portrait');

        return $pdf->stream($filename); // inline: opens for print/save
    }

    public function requisition(Requisition $requisition)
    {
        $requisition->load(['aircraft.aircraftType', 'part', 'workOrder', 'requestedBy', 'approvedBy']);

        return $this->render('pdf.requisition', [
            'r' => $requisition,
        ], "Requisition-{$requisition->requisition_no}.pdf");
    }

    public function siv(Siv $siv)
    {
        $siv->load(['items.part:id,part_number,description', 'items.sourceStore:id,name', 'items.requisition:id,requisition_no', 'postedBy']);

        $items = $siv->items->map(fn ($it) => [
            'line_no' => $it->line_no,
            'part_number' => $it->part?->part_number,
            'description' => $it->description ?: $it->part?->description,
            'qty_required' => (float) $it->qty_required,
            'qty_issued' => $it->qty_issued !== null ? (float) $it->qty_issued : null,
            'qty_issued_words' => $it->qty_issued !== null ? Number::spell((int) $it->qty_issued) : null,
            'store' => $it->sourceStore?->name,
            'stores_folio' => $it->stores_folio,
            'rate' => $it->rate !== null ? (float) $it->rate : null,
            'amount' => $it->amount !== null ? (float) $it->amount : null,
            'charging_code' => $it->charging_code,
            'requisition_no' => $it->requisition?->requisition_no,
        ]);

        return $this->render('pdf.siv', [
            'siv' => $siv,
            'items' => $items,
            'total' => (float) $siv->items->sum('amount'),
        ], "SIV-{$siv->siv_number}.pdf");
    }

    public function srv(Srv $srv)
    {
        $srv->load(['items.part:id,part_number,description', 'destinationStore:id,name,type', 'postedBy']);

        $items = $srv->items->map(fn ($it) => [
            'line_no' => $it->line_no,
            'quantity' => (float) $it->quantity,
            'quantity_words' => Number::spell((int) $it->quantity),
            'supplier_details' => $it->supplier_details,
            'part_number' => $it->part?->part_number,
            'description' => $it->part?->description,
            'fol_no' => $it->fol_no,
            'rate' => $it->rate !== null ? (float) $it->rate : null,
            'amount' => $it->amount !== null ? (float) $it->amount : null,
            'invoice_no' => $it->invoice_no,
            'acct_code' => $it->acct_code,
            'batch_no' => $it->batch_no,
            'batch_year' => $it->batch_year,
            'expiry_date' => $it->expiry_date?->toDateString(),
        ]);

        return $this->render('pdf.srv', [
            'srv' => $srv,
            'items' => $items,
            'total' => (float) $srv->items->sum('amount'),
        ], "SRV-{$srv->srv_number}.pdf");
    }

    /**
     * The two order forms print on their own letterhead, not the internal
     * voucher house style: NCAT crest and aerodrome address block, a boxed
     * title, a ref-and-date line, the vendor address, a NOTE box, the contacts,
     * and the priority checkboxes. They share `pdf.orders.layout` for that
     * frame and differ only in their table and NOTE text.
     */
    public function purchaseOrder(PurchaseOrder $purchaseOrder, OrderSettings $settings)
    {
        $purchaseOrder->load(['vendor', 'lines.part:id,part_number,description']);

        return $this->render('pdf.orders.purchase-order', [
            'order' => $purchaseOrder,
            'settings' => $settings->all(),
        ], 'PurchaseOrder-'.$this->slugRef($purchaseOrder->po_number, $purchaseOrder->id).'.pdf');
    }

    public function repairOrder(RepairOrder $repairOrder, OrderSettings $settings)
    {
        $repairOrder->load(['vendor', 'lines.part:id,part_number,description', 'lines.partSerial:id,serial_number']);

        return $this->render('pdf.orders.repair-order', [
            'order' => $repairOrder,
            'settings' => $settings->all(),
        ], 'RepairOrder-'.$this->slugRef($repairOrder->ro_number, $repairOrder->id).'.pdf');
    }

    /** Order refs contain slashes, which cannot go in a download filename. */
    protected function slugRef(?string $reference, int $id): string
    {
        return $reference ? str_replace('/', '-', $reference) : "draft-{$id}";
    }

    public function tally(Request $request, Part $part, TallyService $tally)
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;
        $part->load('ataChapter:id,chapter_number');

        $consolidated = $request->string('store')->toString() === 'all';
        if ($consolidated) {
            $store = null;
            $card = $tally->consolidated($part, $from, $to);
            $storeName = 'All stores (consolidated)';
        } else {
            $storeId = $request->integer('store')
                ?: \App\Models\StockMovement::where('part_id', $part->id)->value('store_id');
            $store = $storeId ? Store::find($storeId) : null;
            $card = $store ? $tally->card($part, $store, $from, $to) : ['brought_forward' => 0, 'lines' => collect(), 'total_received' => 0, 'total_issued' => 0, 'carried_forward' => 0];
            $storeName = $store?->name ?? '—';
        }

        return $this->render('pdf.tally', [
            'part' => $part,
            'card' => $card,
            'storeName' => $storeName,
            'consolidated' => $consolidated,
            'from' => $from,
            'to' => $to,
        ], "TallyCard-{$part->part_number}.pdf");
    }
}
