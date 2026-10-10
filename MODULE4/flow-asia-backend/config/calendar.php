<?php

return [
    'currency' => 'PHP',
    'hr_roles' => ['hr_admin', 'hr_staff', 'super_admin'],
    'search' => ['min_length' => 2, 'max_length' => 100, 'limit' => 10],
    'read_only_paths' => ['calendar', 'calendar/*', 'hr/calendar', 'hr/calendar/*', 'hr/employees/*/calendar', 'hr/employees/*/calendar/*'],
    'event_types' => ['shoot' => 'Shoot', 'premiere' => 'Premiere', 'town_hall' => 'Town Hall', 'other' => 'Other'],
    'priority' => ['holiday', 'leave_approved', 'absent', 'late'],
    'statuses' => [
        'present' => ['label' => 'Present', 'color' => '#FFFFFF', 'code' => 'P', 'marker' => '✓'],
        'late' => ['label' => 'Late', 'color' => '#F97316', 'code' => 'L'],
        'absent' => ['label' => 'Absent', 'color' => '#DC2626', 'code' => 'A'],
        'leave_approved' => ['label' => 'Approved Leave', 'color' => '#2563EB', 'code' => 'AL'],
        'leave_pending' => ['label' => 'Pending Leave', 'color' => '#BFDBFE', 'code' => 'PL', 'pattern' => 'striped'],
        'overtime' => ['label' => 'Overtime', 'color' => '#9333EA', 'code' => 'OT'],
        'holiday' => ['label' => 'Holiday', 'color' => '#16A34A', 'code' => 'H'],
        'rest_day' => ['label' => 'Rest Day', 'color' => '#9CA3AF', 'code' => 'RD'],
        'half_day' => ['label' => 'Half-Day', 'color' => '#FACC15', 'code' => 'HD'],
    ],
    'company_event' => ['class' => 'company-event', 'marker' => '◆'],
    'demo_month' => '2026-10',
];
