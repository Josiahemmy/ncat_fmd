<?php

namespace App\Services\Search;

use App\Models\Aircraft;
use App\Models\Part;
use App\Models\Requisition;
use App\Models\Siv;
use App\Models\Srv;
use App\Models\WorkOrder;

/**
 * Global typeahead (spec §7). One pass, grouped results, each group gated by
 * the viewer's permission so a user only ever sees what they may open. Per
 * group we cap the hits so the dropdown stays snappy.
 */
class SearchService
{
    /** Minimum query length before we hit the database. */
    public const MIN_LENGTH = 2;

    /** Max hits per group. */
    public const PER_GROUP = 6;

    /**
     * @return array{query: string, groups: array<int, array<string, mixed>>}
     */
    public function search(string $query, $user): array
    {
        $q = trim($query);
        if (mb_strlen($q) < self::MIN_LENGTH || ! $user) {
            return ['query' => $q, 'groups' => []];
        }

        $like = '%'.$q.'%';

        $groups = [];
        foreach ($this->builders($like) as $key => $def) {
            if (! $user->can($def['permission'])) {
                continue;
            }

            $items = $def['resolve']();
            if ($items->isNotEmpty()) {
                $groups[] = [
                    'key' => $key,
                    'label' => $def['label'],
                    'items' => $items->values()->all(),
                ];
            }
        }

        return ['query' => $q, 'groups' => $groups];
    }

    /**
     * Each group: label, the permission to view it, and a resolver returning a
     * collection of {id,title,subtitle,href} rows.
     *
     * @return array<string, array{label: string, permission: string, resolve: callable}>
     */
    protected function builders(string $like): array
    {
        return [
            'parts' => [
                'label' => 'Parts',
                'permission' => 'parts.view',
                'resolve' => fn () => Part::query()
                    ->where(fn ($w) => $w
                        ->where('part_number', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('stock_code', 'like', $like))
                    ->orderBy('part_number')
                    ->limit(self::PER_GROUP)
                    ->get()
                    ->map(fn (Part $p) => [
                        'id' => $p->id,
                        'title' => $p->part_number,
                        'subtitle' => $p->description,
                        'href' => route('parts.show', $p),
                    ]),
            ],
            'aircraft' => [
                'label' => 'Aircraft',
                'permission' => 'aircraft.view',
                'resolve' => fn () => Aircraft::query()
                    ->with('aircraftType:id,name')
                    ->where('registration', 'like', $like)
                    ->orderBy('registration')
                    ->limit(self::PER_GROUP)
                    ->get()
                    ->map(fn (Aircraft $a) => [
                        'id' => $a->id,
                        'title' => $a->registration,
                        'subtitle' => $a->aircraftType?->name,
                        'href' => route('aircraft.show', $a),
                    ]),
            ],
            'work_orders' => [
                'label' => 'Work Orders',
                'permission' => 'work_orders.view',
                'resolve' => fn () => WorkOrder::query()
                    ->where(fn ($w) => $w
                        ->where('wo_ref', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('description', 'like', $like))
                    ->orderByDesc('id')
                    ->limit(self::PER_GROUP)
                    ->get()
                    ->map(fn (WorkOrder $wo) => [
                        'id' => $wo->id,
                        'title' => $wo->wo_ref,
                        'subtitle' => $wo->title,
                        'href' => route('work-orders.show', $wo),
                    ]),
            ],
            'requisitions' => [
                'label' => 'Requisitions',
                'permission' => 'requisitions.view',
                'resolve' => fn () => Requisition::query()
                    ->where(fn ($w) => $w
                        ->where('requisition_no', 'like', $like)
                        ->orWhere('full_description', 'like', $like)
                        ->orWhere('part_no', 'like', $like))
                    ->orderByDesc('id')
                    ->limit(self::PER_GROUP)
                    ->get()
                    ->map(fn (Requisition $r) => [
                        'id' => $r->id,
                        'title' => $r->requisition_no,
                        'subtitle' => $r->full_description,
                        'href' => route('requisitions.show', $r),
                    ]),
            ],
            'receiving' => [
                'label' => 'Receiving (SRV)',
                'permission' => 'receiving.view',
                'resolve' => fn () => Srv::query()
                    ->where(fn ($w) => $w
                        ->where('srv_number', 'like', $like)
                        ->orWhere('supplier', 'like', $like))
                    ->orderByDesc('id')
                    ->limit(self::PER_GROUP)
                    ->get()
                    ->map(fn (Srv $s) => [
                        'id' => $s->id,
                        'title' => $s->srv_number,
                        'subtitle' => $s->supplier,
                        'href' => route('receiving.show', $s),
                    ]),
            ],
            'issuing' => [
                'label' => 'Issuing (SIV)',
                'permission' => 'issues.view',
                'resolve' => fn () => Siv::query()
                    ->where(fn ($w) => $w
                        ->where('siv_number', 'like', $like)
                        ->orWhere('requisition_for', 'like', $like))
                    ->orderByDesc('id')
                    ->limit(self::PER_GROUP)
                    ->get()
                    ->map(fn (Siv $s) => [
                        'id' => $s->id,
                        'title' => $s->siv_number,
                        'subtitle' => $s->requisition_for,
                        'href' => route('issuing.show', $s),
                    ]),
            ],
        ];
    }
}
