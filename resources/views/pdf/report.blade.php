@extends('pdf.layout')

@section('title', $title)
@section('form-title', $title)
@section('form-sub', 'Report')

@section('body')
    @if(!empty($filters))
        <div style="font-size: 8px; color: #353A45; margin-bottom: 6px;">
            <strong>Filters:</strong>
            @foreach($filters as $k => $v)
                <span style="margin-right: 8px;">{{ ucfirst(str_replace('_', ' ', $k)) }}: {{ is_bool($v) ? ($v ? 'yes' : 'no') : $v }}</span>
            @endforeach
        </div>
    @endif

    <table class="lines">
        <thead>
            <tr>
                @foreach($columns as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $col)
                        @php($v = $row[$col] ?? '')
                        <td class="{{ is_numeric($v) ? 'r' : '' }}">{{ is_numeric($v) ? number_format((float) $v, str_contains($col, '₦') ? 2 : (floor((float)$v) == (float)$v ? 0 : 2)) : $v }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="c muted">No records match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size: 8px; color: #353A45; margin-top: 6px;">{{ count($rows) }} row(s).</div>
@endsection
