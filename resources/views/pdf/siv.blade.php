@extends('pdf.layout')

@section('title', 'Store Issue Voucher '.$siv->siv_number)
@section('form-title', 'Store Issue Voucher')
@section('form-sub', '(ONE VOUCHER PER UNIT)')
@section('doc-no', 'No. '.$siv->siv_number)

@section('body')
    <table class="grid">
        <tr>
            <td style="width: 66%;"><span class="lbl">Requisition For</span><span class="val">{{ $siv->requisition_for }}</span></td>
            <td><span class="lbl">Store Issue Voucher No.</span><span class="val num">{{ $siv->siv_number }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">Ordered By (Signature)</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $siv->ordered_by }}</span></td>
            <td><span class="lbl">Date</span><span class="val">{{ $siv->ordered_by_date?->format('d/m/Y') }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">School / Section</span><span class="val">{{ $siv->school_section }}</span></td>
            <td><span class="lbl">Status</span><span class="val"><span class="badge">{{ ucfirst($siv->status ?? 'draft') }}</span></span></td>
        </tr>
        <tr>
            <td><span class="lbl">Approved By</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $siv->approved_by }}</span></td>
            <td><span class="lbl">Date</span><span class="val">{{ $siv->approved_by_date?->format('d/m/Y') }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">Entered By</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $siv->entered_by }}</span></td>
            <td><span class="lbl">Date</span><span class="val">{{ $siv->entered_by_date?->format('d/m/Y') }}</span></td>
        </tr>
    </table>

    <table class="lines" style="margin-top: 8px;">
        <thead>
            <tr>
                <th style="width: 5%;">Item No.</th>
                <th style="width: 14%;">Part No.</th>
                <th style="width: 25%;">Description</th>
                <th class="c" style="width: 8%;">Qty Required</th>
                <th style="width: 16%;">Qty Issued<br>(Figures / Words)</th>
                <th class="c" style="width: 8%;">Stores Folio</th>
                <th class="r" style="width: 8%;">Rate</th>
                <th class="r" style="width: 8%;">Amount (&#8358; / K)</th>
                <th style="width: 8%;">Charging Code</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $it)
                <tr>
                    <td class="c">{{ $it['line_no'] }}</td>
                    <td>{{ $it['part_number'] }}</td>
                    <td>{{ $it['description'] }}</td>
                    <td class="c">{{ $it['qty_required'] !== null ? rtrim(rtrim(number_format($it['qty_required'], 2), '0'), '.') : '' }}</td>
                    <td>
                        <span class="num">{{ $it['qty_issued'] !== null ? rtrim(rtrim(number_format($it['qty_issued'], 2), '0'), '.') : '' }}</span>
                        @if($it['qty_issued_words'])<br><span class="muted" style="font-size:7px;">{{ ucfirst($it['qty_issued_words']) }}</span>@endif
                    </td>
                    <td class="c">{{ $it['stores_folio'] }}</td>
                    <td class="r">{{ $it['rate'] !== null ? number_format($it['rate'], 2) : '' }}</td>
                    <td class="r">{{ $it['amount'] !== null ? number_format($it['amount'], 2) : '' }}</td>
                    <td class="c">{{ $it['charging_code'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="c muted">No items on this voucher.</td></tr>
            @endforelse
            <tr>
                <td colspan="7" class="r"><strong>TOTAL (&#8358;)</strong></td>
                <td class="r"><strong>{{ number_format($total, 2) }}</strong></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="grid" style="margin-top: 8px;">
        <tr>
            <td style="width: 50%;"><span class="lbl">Issued By</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $siv->issued_by }}</span></td>
            <td><span class="lbl">Received By</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $siv->received_by }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">Date</span><span class="val">{{ $siv->issued_by_date?->format('d/m/Y') }}</span></td>
            <td><span class="lbl">Date</span><span class="val">{{ $siv->received_by_date?->format('d/m/Y') }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="lbl">Remark</span><span class="val">{{ $siv->remark }}</span></td>
        </tr>
    </table>

    <div class="distribution">THIS COPY TO: STORES / PROGRESS / SHOP STORE / CHARGING SECTION</div>
@endsection
