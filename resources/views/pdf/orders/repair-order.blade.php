@php
    $date = $order->order_date;
    $dateHtml = $date
        ? preg_replace('/(\d+)(st|nd|rd|th)/', '$1<sup>$2</sup>', $date->format('jS')).' '.$date->format('F, Y')
        : '';
@endphp

@extends('pdf.orders.layout', [
    'reference' => $order->ro_number ?? 'NCAT/FMD/RO/TS/… (not yet issued)',
    'orderDate' => $dateHtml,
    'note' => $settings['ro_note'],
    // The Repair Order signs off "Materials and Stores." with no "Head,", which
    // is how the sample prints it. Kept separate from the PO line rather than
    // normalised; flagged for the department.
    'preparedBy' => $settings['ro_prepared_by'],
    'priority' => $order->priority,
    'isDraft' => $order->isDraft(),
])

@section('title', 'Repair Order '.($order->ro_number ?? 'draft'))
@section('form-title', 'REPAIR ORDER')
@section('contacts-heading', 'NCAT CONTACTS:')

@section('body')
    {{-- The RO sample prints the vendor address flush left with no "TO:" label. --}}
    <div class="vendor">
        @forelse($order->vendor?->addressLines() ?? [] as $line)
            {{ $line }}<br>
        @empty
            {{ $order->vendor?->name }}<br>
        @endforelse
    </div>

    @if($order->aircraft_type_label)
        <div class="actype" style="text-align: center;">AIRCRAFT TYPE: {{ $order->aircraft_type_label }}</div>
    @endif

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 8%;">S/N.</th>
                <th style="width: 28%;">DESCRIPTION</th>
                <th style="width: 17%;">PART<br>NUMBER</th>
                <th style="width: 22%;">SERIAL NO.</th>
                <th style="width: 9%;">QTY</th>
                <th style="width: 16%;">ACTION</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->lines as $line)
                <tr>
                    <td class="c">{{ $line->line_no }}.</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->part_number ?? $line->part?->part_number }}</td>
                    <td>{{ $line->serial_no ?? $line->partSerial?->serial_number }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format($line->qty, 2, '.', ''), '0'), '.') }}</td>
                    <td class="c">{{ $line->action }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="c">No lines on this order.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
