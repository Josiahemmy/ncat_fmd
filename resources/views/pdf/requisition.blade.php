@extends('pdf.layout')

@section('title', 'Requisition '.$r->requisition_no)
@section('form-title', 'Aircraft Spare Parts Requisition Sheet')
@section('form-sub', '(ONE VOUCHER PER UNIT)')
@section('doc-no', 'No. '.$r->requisition_no)

@section('body')
    <table class="grid">
        <tr>
            <td style="width: 33%;"><span class="lbl">(1) Aircraft Reg</span><span class="val">{{ $r->aircraft?->registration ?? $r->aircraft_reg }}</span></td>
            <td style="width: 33%;"><span class="lbl">(2) Engine Serial No.</span><span class="val">{{ $r->engine_serial_no }}</span></td>
            <td><span class="lbl">(3) Position</span><span class="val">{{ $r->position }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">(4) Required For</span><span class="val">{{ $r->workOrder?->wo_ref ?? $r->supply_source }}</span></td>
            <td><span class="lbl">Date</span><span class="val">{{ $r->requisition_date?->format('d/m/Y') }}</span></td>
            <td><span class="lbl">Authorised By</span><span class="val">{{ $r->authorised_by }}</span></td>
        </tr>
        <tr>
            <td colspan="3"><span class="lbl">(5) Full Description</span><span class="val">{{ $r->full_description }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">(6) Part No.</span><span class="val">{{ $r->part_no ?? $r->part?->part_number }}</span></td>
            <td><span class="lbl">(7) Stock Code</span><span class="val">{{ $r->stock_code }}</span></td>
            <td><span class="lbl">(8) Serial Number</span><span class="val">{{ $r->serial_number }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">(9) Batch No.</span><span class="val">{{ $r->batch_no }} @if($r->batch_year)/ {{ $r->batch_year }}@endif</span></td>
            <td><span class="lbl">(10) Bin / Bal Line No.</span><span class="val">{{ $r->bin_bal_line_no }}</span></td>
            <td><span class="lbl">Status</span><span class="val"><span class="badge">{{ ucfirst($r->status) }}</span></span></td>
        </tr>
        <tr>
            <td colspan="3"><span class="lbl">(11) Serviceable Unit Issued By (Storeman)</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $r->issued_by }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="lbl">(12) Unit Serviced By</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $r->unit_serviced_by }}</span></td>
            <td><span class="lbl">Date</span><span class="val">{{ $r->unit_serviced_date?->format('d/m/Y') }}</span></td>
        </tr>
    </table>

    <div class="section-bar">Removal Information (to be completed by aircraft technician)</div>
    <table class="grid" style="border-top: none;">
        <tr>
            <td style="width: 50%;"><span class="lbl">(13) Serial No. Removed</span><span class="val">{{ $r->serial_no_removed }}</span></td>
            <td><span class="lbl">(14) Zone</span><span class="val">{{ $r->removal_zone }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">(15) Unit Changed By</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $r->unit_changed_by }}</span></td>
            <td><span class="lbl">Date</span><span class="val">{{ $r->removal_date?->format('d/m/Y') }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="lbl">(16) Reason For Removal</span><span class="val">{{ $r->reason_for_removal }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">(17) Signature</span><div class="sign-line"></div></td>
            <td><span class="lbl">Date</span><span class="val">{{ $r->removal_date?->format('d/m/Y') }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">(18) Repair Facility / Workshop</span><span class="val">{{ $r->repair_facility }}</span></td>
            <td><span class="lbl">(19) Date Sent</span><span class="val">{{ $r->date_sent?->format('d/m/Y') }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="lbl">(20) Repair Order Ref. (if applicable)</span><span class="val">{{ $r->repair_order_ref }}</span></td>
        </tr>
    </table>

    <div class="distribution">THIS COPY TO: STORES / PROGRESS / SHOP STORE / CONP. PLANNING AND CHIEF INSPECTOR</div>
@endsection
