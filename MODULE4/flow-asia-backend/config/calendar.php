<?php

return [
    'currency' => 'PHP',
    'hr_roles' => ['hr_admin', 'hr_staff', 'super_admin'],
    'search' => ['min_length' => 2, 'max_length' => 100, 'limit' => 10],
    'read_only_paths' => ['calendar', 'calendar/*', 'hr/calendar', 'hr/calendar/*', 'hr/employees/*/calendar', 'hr/employees/*/calendar/*'],
    'event_types' => ['shoot' => 'Shoot', 'premiere' => 'Premiere', 'town_hall' => 'Town Hall', 'other' => 'Other'],
    'priority' => ['holiday', 'leave_approved', 'absent', 'late'],
    'statuses' => [
        'present' => ['label' => 'Present', 'color' => '#2A2A2E', 'code' => 'P', 'ui_class' => 'P'],
        'late' => ['label' => 'Late', 'color' => '#F4F4F5', 'code' => 'L', 'ui_class' => 'L'],
        'absent' => ['label' => 'Absent', 'color' => '#FF4D4D', 'code' => 'A', 'ui_class' => 'A'],
        'leave_approved' => ['label' => 'Leave', 'color' => '#38BDF8', 'code' => 'AL', 'ui_class' => 'LV'],
        'leave_pending' => ['label' => 'Pending Leave', 'color' => '#38BDF8', 'code' => 'PL', 'pattern' => 'striped', 'ui_class' => 'PL'],
        'overtime' => ['label' => 'Overtime', 'color' => '#B8FF2C', 'code' => 'OT', 'ui_class' => 'OT'],
        'holiday' => ['label' => 'Holiday', 'color' => '#B8FF2C', 'code' => 'H', 'ui_class' => 'H'],
        'rest_day' => ['label' => 'Rest Day', 'color' => '#A1A1AA', 'code' => 'RD', 'ui_class' => 'RD'],
        'half_day' => ['label' => 'Half-Day', 'color' => '#FFB800', 'code' => 'HD', 'ui_class' => 'HD'],
    ],
    'invalid_status' => ['label' => 'Invalid punch', 'color' => '#FFB800', 'code' => 'INV', 'ui_class' => 'INV'],
    'company_event' => ['class' => 'company-event', 'marker' => '◆'],
    'demo_month' => '2026-10',
];
