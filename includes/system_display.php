<?php
/**
 * Shared display metadata for connected_systems rows — used by the
 * dashboard's department cards/stat tiles and the Connected Systems
 * management page, so both stay visually and structurally in sync.
 */

const SYSTEM_THEME_COLORS = ['blue', 'orange', 'purple', 'rose', 'teal', 'amber'];

const SYSTEM_ICON_CHOICES = [
    'fa-server' => 'Server (generic)',
    'fa-hard-hat' => 'Hard hat (infrastructure)',
    'fa-road' => 'Road',
    'fa-calendar-check' => 'Calendar (reservations)',
    'fa-tools' => 'Tools (maintenance)',
    'fa-leaf' => 'Leaf (energy/environment)',
    'fa-bolt' => 'Bolt (utilities/power)',
    'fa-water' => 'Water',
    'fa-building' => 'Building',
    'fa-file-invoice-dollar' => 'Invoice (billing)',
    'fa-shield-halved' => 'Shield (safety/security)',
    'fa-chart-line' => 'Chart (analytics)',
    'fa-warehouse' => 'Warehouse',
    'fa-truck' => 'Truck (logistics)',
    'fa-hospital' => 'Hospital (health)',
    'fa-graduation-cap' => 'Graduation cap (education)',
    'fa-map' => 'Map (urban/zoning planning)',
];

function systemThemeGradientHex(string $themeColor): array
{
    $map = [
        'blue' => ['#3b82f6', '#1d4ed8'],
        'orange' => ['#f59e0b', '#d97706'],
        'purple' => ['#8b5cf6', '#6d28d9'],
        'rose' => ['#fb7185', '#c8185a'],
        'teal' => ['#14b8a6', '#0f766e'],
        'amber' => ['#d4920a', '#a05a00'],
    ];

    return $map[$themeColor] ?? $map['blue'];
}
