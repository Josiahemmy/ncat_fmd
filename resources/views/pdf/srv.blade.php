@extends('pdf.layout')

@section('title', 'Store Receipt Voucher '.$srv->srv_number)
@section('form-title', 'Store Receipt Voucher')
@section('doc-no', 'No. '.$srv->srv_number)

@section('body')
    <table class="grid">
        <tr>
            <td colspan="2"><span class="lbl">To The Storekeeper</span>
                <span class="val">Received the following items into the <strong>{{ $srv->destinationStore->name }}</strong> store</span>
            </td>
            <td style="width: 20%;"><span class="lbl">Date</span><span class="val">{{ $srv->srv_date?->format('d/m/Y') }}</span></td>
        </tr>
        <tr>
            <td style="width: 40%;"><span class="lbl">Supplier</span><span class="val">{{ $srv->supplier }}</span></td>
            <td colspan="2"><span class="lbl">Cross reference the above number with the Local Purchase Order / Petty Cash Voucher No.</span><span class="val">{{ $srv->lpo_or_petty_cash_ref }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">Head of Receiving Dept.</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $srv->head_of_receiving_dept }}</span></td>
            <td><span class="lbl">Storekeeper</span><div class="sign-line"></div><span class="muted" style="font-size:7px;">{{ $srv->storekeeper }}</span></td>
            <td><span class="lbl">Status</span><span class="val"><span class="badge">{{ ucfirst($srv->status ?? 'draft') }}</span></span></td>
        </tr>
    </table>

    <table class="lines" style="margin-top: 8px;">
        <thead>
            <tr>
                <th style="width: 4%;">Item No.</th>
                <th style="width: 13%;">Qty<br>(Figures / Words)</th>
                <th style="width: 22%;">Suppliers &amp; Details of Materials</th>
                <th style="width: 12%;">Part No.</th>
                <th class="c" style="width: 6%;">Fol No.</th>
                <th class="r" style="width: 7%;">Rate</th>
                <th class="r" style="width: 9%;">Amount (&#8358; / K)</th>
                <th style="width: 9%;">Invoice No.</th>
                <th style="width: 8%;">Acct Code</th>
                <th style="width: 10%;">Batch / Expiry</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $it)
                <tr>
                    <td class="c">{{ $it['line_no'] }}</td>
                    <td>
                        <span class="num">{{ $it['quantity'] !== null ? rtrim(rtrim(number_format($it['quantity'], 2), '0'), '.') : '' }}</span>
                        @if($it['quantity_words'])<br><span class="muted" style="font-size:7px;">{{ ucfirst($it['quantity_words']) }}</span>@endif
                    </td>
                    <td>{{ $it['supplier_details'] }}{{ $it['description'] ? ($it['supplier_details'] ? ' — ' : '').$it['description'] : '' }}</td>
                    <td>{{ $it['part_number'] }}</td>
                    <td class="c">{{ $it['fol_no'] }}</td>
                    <td class="r">{{ $it['rate'] !== null ? number_format($it['rate'], 2) : '' }}</td>
                    <td class="r">{{ $it['amount'] !== null ? number_format($it['amount'], 2) : '' }}</td>
                    <td>{{ $it['invoice_no'] }}</td>
                    <td class="c">{{ $it['acct_code'] }}</td>
                    <td>
                        {{ $it['batch_no'] }}@if($it['batch_year']) / {{ $it['batch_year'] }}@endif
                        @if($it['expiry_date'])<br><span class="muted" style="font-size:7px;">Exp: {{ $it['expiry_date'] }}</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="c muted">No items on this voucher.</td></tr>
            @endforelse
            <tr>
                <td colspan="6" class="r"><strong>TOTAL (&#8358;)</strong></td>
                <td class="r"><strong>{{ number_format($total, 2) }}</strong></td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>

    <div class="foot-note" style="font-size:8.5px; color:#101318; margin-top:8px;">
        I certify that the above mentioned goods were received in good order and condition and are taken on stores charge.
    </div>

    <table class="grid" style="margin-top: 6px;">
        <tr>
            <td style="width: 50%;"><span class="lbl">Head of Receiving Dept. (Signature)</span><div class="sign-line"></div></td>
            <td><span class="lbl">Storekeeper (Signature)</span><div class="sign-line"></div></td>
        </tr>
        <tr>
            <td colspan="2"><span class="lbl">Remarks</span><span class="val">{{ $srv->remarks }}</span></td>
        </tr>
    </table>

    <div class="distribution">THIS COPY TO: STORES / ACCOUNTS / RECEIVING DEPT.</div>
@endsection
