<?php

return [
    'tax_rate' => 11,
    'service_charge' => 0,
    'points_earn_per_amount' => 10000,
    'points_per_rupiah' => 1,
    'points_redeem_value' => 100,
    'currency' => 'IDR',
    'order_prefix' => 'ORD',
    'production_prefix' => 'PROD',
    'transfer_prefix' => 'TRF',
    'opname_prefix' => 'OPN',
    'waste_prefix' => 'WST',

    'partners' => [
        ['name' => 'Wahyu', 'capital' => 90_000_000],
        ['name' => 'Rizky', 'capital' => 135_000_000],
        ['name' => 'Johan', 'capital' => 72_500_000],
    ],

    'food_commission' => 2000,

    'roles' => [
        'admin' => 'Admin',
        'cashier' => 'Cashier',
    ],

    'permissions' => [
        'dashboard.view',
        'pos.access',
        'orders.view',
        'orders.manage',
        'orders.checkout',
        'orders.check',
        'orders.cancel',
        'tables.view',
        'tables.manage',
        'customers.view',
        'customers.manage',
        'marketing.view',
        'marketing.manage',
        'loyalty.view',
        'loyalty.manage',
        'products.view',
        'products.manage',
        'inventory.view',
        'inventory.manage',
        'inventory.approve',
        'production.view',
        'production.manage',
        'printers.view',
        'printers.manage',
        'reports.view',
        'reports.export',
        'users.view',
        'users.manage',
        'roles.manage',
        'outlets.view',
        'outlets.manage',
        'settings.manage',
        'audit.view',
    ],

    'role_permissions' => [
        'admin' => ['*'],
        'cashier' => [
            'dashboard.view', 'pos.access', 'orders.view', 'orders.manage', 'orders.checkout', 'orders.check', 'orders.cancel',
            'tables.view', 'tables.manage', 'customers.view', 'customers.manage',
        ],
    ],
];
