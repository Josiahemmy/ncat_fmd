<?php

/**
 * Single source of truth for the permission catalogue (spec §6, v1.1).
 * Consumed by PermissionSeeder, RoleSeeder, and the Administration
 * roles/permissions matrix UI. Grouped by module for the grouped toggles.
 *
 * Permissions for Phase 2–5 modules are defined now so roles can be shaped
 * up-front; the modules that enforce them arrive in later phases.
 */
return [
    'groups' => [
        'stores' => [
            'label' => 'Stores & Stock',
            'permissions' => [
                'stores.view' => 'View stores',
                'stores.manage' => 'Manage stores',
                'stock.view' => 'View stock balances',
                'stock.adjust' => 'Adjust stock (stock-take corrections)',
                'stock.transfer' => 'Transfer stock between stores',
                'quarantine.certify' => 'Certify quarantined stock',
                'fuel.post' => 'Post fuel receipts / issues',
            ],
        ],
        'documents' => [
            'label' => 'Documents',
            'permissions' => [
                'work_orders.view' => 'View work orders',
                'work_orders.create' => 'Create work orders',
                'work_orders.edit' => 'Edit work orders',
                'requisitions.view' => 'View requisitions',
                'requisitions.create' => 'Create requisitions',
                'requisitions.approve' => 'Approve requisitions',
                'receiving.view' => 'View receiving (SRV)',
                'receiving.post' => 'Post receiving (SRV)',
                'issues.view' => 'View issuing (SIV)',
                'issues.post' => 'Post issuing (SIV)',
            ],
        ],
        'catalogue' => [
            'label' => 'Catalogue',
            'permissions' => [
                'parts.view' => 'View parts catalogue',
                'parts.manage' => 'Manage parts',
                'tally.view' => 'View tally cards',
            ],
        ],
        'reports' => [
            'label' => 'Reports & Audit',
            'permissions' => [
                'reports.view' => 'View reports',
                'audit.view' => 'View activity log',
            ],
        ],
        'administration' => [
            'label' => 'Administration',
            'permissions' => [
                'users.view' => 'View users',
                'users.manage' => 'Manage users',
                'roles.view' => 'View roles & permissions',
                'roles.manage' => 'Manage roles & permissions',
                'aircraft.view' => 'View aircraft & types',
                'aircraft.manage' => 'Manage aircraft & types',
                'ata.view' => 'View ATA chapters',
                'ata.manage' => 'Manage ATA chapters',
                'counters.view' => 'View document counters',
                'counters.manage' => 'Manage document counters',
            ],
        ],
    ],

    /**
     * Starter roles → default permissions (spec §6).
     * Super Admin is handled separately (Gate::before bypass, immutable).
     * Segregation of duties: `quarantine.certify` is granted to Stores Officer,
     * NOT Storekeeper (receiver ≠ certifier).
     */
    'roles' => [
        'Stores Officer' => [
            'stores.view', 'stock.view', 'stock.adjust', 'stock.transfer',
            'quarantine.certify', 'fuel.post',
            'work_orders.view', 'requisitions.view', 'requisitions.approve',
            'receiving.view', 'issues.view', 'issues.post',
            'parts.view', 'parts.manage', 'tally.view',
            'reports.view', 'aircraft.view', 'ata.view',
        ],
        'Storekeeper' => [
            'stores.view', 'stock.view',
            'receiving.view', 'receiving.post', 'issues.view', 'issues.post',
            'fuel.post', 'requisitions.view', 'work_orders.view',
            'parts.view', 'tally.view', 'aircraft.view', 'ata.view',
        ],
        'Engineer/Technician' => [
            'work_orders.view', 'work_orders.create',
            'requisitions.view', 'requisitions.create',
            'stock.view', 'parts.view', 'tally.view', 'aircraft.view', 'ata.view',
        ],
        'Viewer' => [
            'stores.view', 'stock.view', 'work_orders.view', 'requisitions.view',
            'receiving.view', 'issues.view', 'parts.view', 'tally.view',
            'reports.view', 'aircraft.view', 'ata.view',
        ],
    ],
];
