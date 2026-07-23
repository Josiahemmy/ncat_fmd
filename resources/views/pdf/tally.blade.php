@extends('pdf.layout')

@php
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $colspan = $consolidated ? 4 : 3;
@endphp

@section('title', 'Tally Card '.$part->part_number)
@section('form-title', $consolidated ? 'Tally Card AD38' : 'Store Tally Card')
@section('form-sub', $consolidated ? 'CONSOLIDATED: all stores (non-AD38)' : 'AD38')
@section('doc-no', $storeName)

@section('body')
    @if($consolidated)
        <div class="section-bar">CONSOLIDATED: all stores (non-AD38)</div>
    @endif

    <table class="grid">
        <tr>
            <td colspan="2" style="width: 50%;"><span class="lbl">Description</span><span class="val">{{ $part->description }}</span></td>
            <td><span class="lbl">Ledger Folio</span><span class="val">{{ $part->ledger_folio }}</span></td>
            <td><span class="lbl">ATA Chapter</span><span class="val">{{ $part->ataChapter?->chapter_number }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">Part No.</span><span class="val num">{{ $part->part_number }}</span></td>
            <td><span class="lbl">Location / Bin</span><span class="val">{{ $part->bin_location }}</span></td>
            <td><span class="lbl">Unit of Issue</span><span class="val">{{ $part->unit_of_issue }}</span></td>
            <td><span class="lbl">Unit Price</span><span class="val">{{ $part->unit_price !== null ? number_format((float) $part->unit_price, 2) : '' }}</span></td>
        </tr>
        <tr>
            <td><span class="lbl">Max Level</span><span class="val">{{ $num($part->max_level) }}</span></td>
            <td><span class="lbl">Min Level</span><span class="val">{{ $num($part->min_level) }}</span></td>
            <td><span class="lbl">Reorder Level</span><span class="val">{{ $num($part->reorder_level) }}</span></td>
            <td><span class="lbl">Store</span><span class="val">{{ $storeName }}</span></td>
        </tr>
        <tr>
            <td colspan="4"><span class="lbl">Period</span><span class="val">{{ $from ?: 'Opening' }} &nbsp;&mdash;&nbsp; {{ $to ?: 'To date' }}</span></td>
        </tr>
    </table>

    <table class="lines" style="margin-top: 8px;">
        <thead>
            <tr>
                <th style="width: 10%;">Date</th>
                <th style="width: 14%;">Reference</th>
                <th>Particulars</th>
                @if($consolidated)<th style="width: 12%;">Store</th>@endif
                <th class="r" style="width: 10%;">Received</th>
                <th class="r" style="width: 10%;">Issued</th>
                <th class="r" style="width: 12%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td></td>
                <td></td>
                <td><strong>Brought Forward</strong></td>
                @if($consolidated)<td></td>@endif
                <td class="r"></td>
                <td class="r"></td>
                <td class="r"><strong>{{ $num($card['brought_forward']) }}</strong></td>
            </tr>
            @forelse($card['lines'] as $line)
                <tr>
                    <td>{{ $line['date'] }}</td>
                    <td>{{ $line['reference'] }}</td>
                    <td>{{ $line['particulars'] }}@if(!empty($line['remarks']))<br><span class="muted" style="font-size:7px;">{{ $line['remarks'] }}</span>@endif</td>
                    @if($consolidated)<td>{{ $line['store'] ?? '' }}</td>@endif
                    <td class="r">{{ $num($line['received']) }}</td>
                    <td class="r">{{ $num($line['issued']) }}</td>
                    <td class="r">{{ $num($line['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $consolidated ? 7 : 6 }}" class="c muted">No movements in this period.</td></tr>
            @endforelse
            <tr>
                <td colspan="{{ $colspan }}" class="r"><strong>TOTALS</strong></td>
                <td class="r"><strong>{{ $num($card['total_received']) }}</strong></td>
                <td class="r"><strong>{{ $num($card['total_issued']) }}</strong></td>
                <td class="r"></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}" class="r"><strong>Carried Forward</strong></td>
                <td class="r"></td>
                <td class="r"></td>
                <td class="r"><strong>{{ $num($card['carried_forward']) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="foot-note">AD38 Tally Card · {{ $storeName }}@if($consolidated) · consolidated across all stores @endif</div>
@endsection
