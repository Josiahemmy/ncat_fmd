<?php

namespace App\Services\Stock;

use App\Models\AtaChapter;
use App\Models\Part;
use App\Models\PartBatch;
use App\Models\PartSerial;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Digitises the department's paper tally cards. Rows (from a CSV) are first
 * validated as a dry-run (preview), then committed atomically — either every
 * row imports or none does. Opening balances post through StockService.
 *
 * Row keys: part_number, description, ata_chapter, store, qty, unit_price,
 *           batch_no, expiry, serials (pipe-separated).
 */
class OpeningBalanceImporter
{
    public function __construct(protected StockService $stock)
    {
    }

    /**
     * Validate rows without writing anything.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{row: int, valid: bool, errors: array<int, string>, data: array<string, mixed>}>
     */
    public function preview(array $rows): array
    {
        return array_map(fn ($row, $i) => $this->validateRow($row, $i + 1), $rows, array_keys($rows));
    }

    /**
     * Import rows. Returns committed=false (writing nothing) if any row is
     * invalid; otherwise imports all inside one transaction.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{committed: bool, imported: int, preview: array<int, mixed>}
     */
    public function import(array $rows, ?User $user = null): array
    {
        $preview = $this->preview($rows);

        if (collect($preview)->contains(fn ($p) => ! $p['valid'])) {
            return ['committed' => false, 'imported' => 0, 'preview' => $preview];
        }

        DB::transaction(function () use ($preview, $user) {
            foreach ($preview as $p) {
                $this->importRow($p['data'], $user);
            }
        });

        return ['committed' => true, 'imported' => count($preview), 'preview' => $preview];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{row: int, valid: bool, errors: array<int, string>, data: array<string, mixed>}
     */
    protected function validateRow(array $row, int $number): array
    {
        $errors = [];
        $partNumber = trim((string) ($row['part_number'] ?? ''));
        $storeName = trim((string) ($row['store'] ?? ''));
        $serials = array_values(array_filter(array_map('trim', explode('|', (string) ($row['serials'] ?? '')))));
        $store = $this->resolveStore($storeName);

        if ($partNumber === '') {
            $errors[] = 'part_number is required';
        }
        if (! $store) {
            $errors[] = "store '{$storeName}' not found";
        }

        // Quantity: serial count when serials are given, else the qty column.
        $qty = $serials ? count($serials) : (is_numeric($row['qty'] ?? null) ? (float) $row['qty'] : null);
        if ($qty === null) {
            $errors[] = 'qty is required (or provide serials)';
        } elseif ($qty <= 0) {
            $errors[] = 'qty must be greater than zero';
        }

        if (($row['unit_price'] ?? '') !== '' && ! is_numeric($row['unit_price'])) {
            $errors[] = 'unit_price must be numeric';
        }

        return [
            'row' => $number,
            'valid' => $errors === [],
            'errors' => $errors,
            'data' => [
                'part_number' => $partNumber,
                'description' => trim((string) ($row['description'] ?? $partNumber)),
                'ata_chapter' => trim((string) ($row['ata_chapter'] ?? '')),
                'store_id' => $store?->id,
                'qty' => $qty,
                'unit_price' => ($row['unit_price'] ?? '') !== '' ? (float) $row['unit_price'] : null,
                'batch_no' => trim((string) ($row['batch_no'] ?? '')),
                'expiry' => trim((string) ($row['expiry'] ?? '')),
                'serials' => $serials,
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    protected function importRow(array $data, ?User $user): void
    {
        $ata = $data['ata_chapter'] !== ''
            ? AtaChapter::where('chapter_number', $data['ata_chapter'])->value('id')
            : null;

        $part = Part::firstOrCreate(
            ['part_number' => $data['part_number']],
            [
                'description' => $data['description'],
                'ata_chapter_id' => $ata,
                'unit_price' => $data['unit_price'],
                'is_serialized' => $data['serials'] !== [],
                'has_shelf_life' => $data['batch_no'] !== '' || $data['expiry'] !== '',
                'unit_of_issue' => 'EA',
            ],
        );

        $store = Store::find($data['store_id']);

        $batchId = null;
        if ($data['batch_no'] !== '') {
            $batchId = PartBatch::firstOrCreate(
                ['part_id' => $part->id, 'batch_number' => $data['batch_no']],
                ['expiry_date' => $data['expiry'] !== '' ? $data['expiry'] : null, 'qty_received' => $data['qty']],
            )->id;
        }

        if ($data['serials'] !== []) {
            foreach ($data['serials'] as $sn) {
                $serial = PartSerial::firstOrCreate(
                    ['part_id' => $part->id, 'serial_number' => $sn],
                    ['status' => 'in_store', 'part_batch_id' => $batchId],
                );
                $this->stock->openingBalance(
                    part: $part, store: $store, quantity: 1, user: $user,
                    unitPrice: $data['unit_price'], batchId: $batchId, serialId: $serial->id,
                );
            }

            return;
        }

        $this->stock->openingBalance(
            part: $part, store: $store, quantity: $data['qty'], user: $user,
            unitPrice: $data['unit_price'], batchId: $batchId,
        );
    }

    protected function resolveStore(string $name): ?Store
    {
        if ($name === '') {
            return null;
        }

        return Store::where('slug', strtolower($name))
            ->orWhereRaw('lower(name) = ?', [strtolower($name)])
            ->orWhereRaw('lower(name) like ?', ['%'.strtolower($name).'%'])
            ->first();
    }
}
