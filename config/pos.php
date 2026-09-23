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
        ['name' => 'Wahyu', 'capital' => 100_000_000],
        ['name' => 'Rizky', 'capital' => 150_000_000],
        ['name' => 'Johan', 'capital' => 74_000_000],
    ],

    'food_commission' => 2000,

    // Fixed operating costs. Target omzet minuman = monthly total (cover BOP; HPP tuned later).
    'bop' => [
        'items' => [
            ['name' => 'Sewa Ruko', 'category' => 'sewa', 'amount' => 45_000_000, 'period' => 'year'],
            ['name' => 'Listrik', 'category' => 'listrik', 'amount' => 1_000_000, 'period' => 'month'],
            ['name' => 'Wifi / Internet', 'category' => 'wifi', 'amount' => 325_000, 'period' => 'month'],
            ['name' => 'Gaji Karyawan', 'category' => 'gaji', 'amount' => 8_900_000, 'period' => 'month'],
            ['name' => 'Iuran', 'category' => 'iuran', 'amount' => 125_000, 'period' => 'month'],
        ],
        'drink_categories' => ['Coffee', 'Non Coffee', 'Fit Tea', 'Xiway Main'],
    ],

    'roles' => [
        'admin' => 'Admin',
        'cashier' => 'Cashier',
        'karyawan' => 'Karyawan',
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
        'products.options',
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
        'attendance.clock',
        'attendance.manage',
        'bop.manage',
    ],

    'role_permissions' => [
        'admin' => ['*'],
        'cashier' => [
            'dashboard.view', 'pos.access', 'orders.view', 'orders.manage', 'orders.checkout', 'orders.check', 'orders.cancel',
            'tables.view', 'tables.manage', 'customers.view', 'customers.manage',
            'attendance.clock',
            'bop.manage',
            'products.view', 'products.options',
        ],
        'karyawan' => [
            'attendance.clock',
        ],
    ],

    'attendance' => [
        'late_after' => '08:30',
        'geo_radius_m' => 150,
    ],
];
