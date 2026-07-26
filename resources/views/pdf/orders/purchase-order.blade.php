@php
    // The sample prints "30th June, 2026" with a superscript ordinal.
    $date = $order->order_date;
    $ordinal = $date ? $date->format('jS') : '';
    $dateHtml = $date
        ? preg_replace('/(\d+)(st|nd|rd|th)/', '$1<sup>$2</sup>', $ordinal).' '.$date->format('F, Y')
        : '';
@endphp

@extends('pdf.orders.layout', [
    'reference' => $order->po_number ?? 'NCAT/FMD/PO/TS/… (not yet issued)',
    'orderDate' => $dateHtml,
    'note' => $settings['po_note'],
    'preparedBy' => $settings['po_prepared_by'],
    'priority' => $order->priority,
    'isDraft' => $order->isDraft(),
])

@section('title', 'Purchase Order '.($order->po_number ?? 'draft'))
@section('form-title', 'PURCHASE ORDER')
@section('contacts-heading', 'NCAT CONTACT:')

@section('body')
    <table class="vendor">
        <tr>
            <td style="width: 58px;">TO:</td>
            <td>
                @forelse($order->vendor?->addressLines() ?? [] as $line)
                    {{ $line }}<br>
                @empty
                    {{ $order->vendor?->name }}<br>
                @endforelse
            </td>
        </tr>
    </table>

    @if($order->aircraft_type_label)
        <div class="actype">AIRCRAFT TYPE: {{ $order->aircraft_type_label }}</div>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 8%;">S/NO.</th>
                <th style="width: 30%;">DESCRIPTION</th>
                <th style="width: 20%;">PART NUMBER</th>
                <th style="width: 11%;">QTY TO<br>ORDER</th>
                <th style="width: 13%;">STATUS</th>
                <th style="width: 18%;">TIME LINE</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->lines as $line)
                <tr>
                    <td class="c">{{ $line->line_no }}.</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->part_number ?? $line->part?->part_number }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format($line->qty_to_order, 2, '.', ''), '0'), '.') }}</td>
                    <td class="c">{{ $line->line_status }}</td>
                    <td class="c">{{ $line->timelineLabel() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="c">No lines on this order.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
