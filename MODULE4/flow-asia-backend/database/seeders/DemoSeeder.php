<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use FlowAsia\Module4\Models\AttendanceDaily;
use FlowAsia\Module4\Models\CalendarReport;
use FlowAsia\Module4\Models\Department;
use FlowAsia\Module4\Models\Employee;
use FlowAsia\Module4\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (config('module4.mode') !== 'standalone' || ! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo accounts are only allowed in standalone local or testing environments.');
        }

        DB::transaction(function () {
            $month = CarbonImmutable::parse(config('module4_calendar.demo_month').'-01', config('module4.timezone'));
            $statuses = array_keys(config('module4_calendar.statuses'));
            $employees = [];

            // Fixed values and arithmetic provide reproducible data without randomness.
            foreach (['Creative', 'Production', 'Marketing', 'Human Resources', 'Finance'] as $departmentIndex => $name) {
                $department = Department::updateOrCreate(['name' => $name]);
                $supervisorId = null;

                for ($member = 0; $member < 8; $member++) {
                    $number = $departmentIndex * 8 + $member + 1;
                    $employee = Employee::updateOrCreate(
                        ['employee_no' => sprintf('EMP-%03d', $number)],
                        [
                            'first_name' => 'Demo',
                            'last_name' => sprintf('Employee %02d', $number),
                            'position' => $member === 0 ? 'Supervisor' : 'Team Member',
                            'department_id' => $department->id,
                            'supervisor_id' => $supervisorId,
                            'hired_on' => $number === 39 ? $month->addDays(14)->toDateString() : $month->subYear()->toDateString(),
                            'calendar_profile' => [
                                'leave_balances' => [['type' => 'Vacation', 'remaining_days' => 8], ['type' => 'Sick', 'remaining_days' => 10]],
                                'pay_periods' => [
                                    ['start' => '2026-10-01', 'end' => '2026-10-15', 'payday' => '2026-10-20'],
                                    ['start' => '2026-10-16', 'end' => '2026-10-31', 'payday' => '2026-11-05'],
                                ],
                            ],
                        ],
                    );
                    $employees[$number] = $employee;
                    $supervisorId ??= $employee->id;

                    // Employee 40 deliberately has no attendance; 39 starts mid-month.
                    if ($number === 40) {
                        continue;
                    }

                    for ($day = 1; $day <= $month->daysInMonth; $day++) {
                        $date = $month->addDays($day - 1);
                        if ($date->lt($employee->hired_on)) {
                            continue;
                        }
                        $status = $statuses[($number + $day - 2) % count($statuses)];
                        $overlap = $number === 2 && $day === 12;
                        if ($overlap) {
                            $status = 'holiday';
                        }
                        if ($number === 3 && $day === 12) {
                            $status = 'leave_approved';
                        }
                        $hasShift = in_array($status, ['present', 'late', 'overtime', 'half_day'], true);
                        $rejectedPunch = $number === 2 && $day === 13;
                        $details = [
                            'report_required' => $hasShift,
                            'shift' => $hasShift ? ['starts_at' => '09:00:00', 'ends_at' => $status === 'half_day' ? '13:00:00' : '18:00:00', 'break_minutes' => $status === 'half_day' ? 0 : 60, 'report_required' => true] : null,
                            'leave' => in_array($status, ['leave_approved', 'leave_pending'], true) || $overlap
                                ? ['type' => 'Vacation', 'approval_status' => $status === 'leave_pending' ? 'pending' : 'approved', 'days' => 1] : null,
                            'holiday' => $status === 'holiday' ? ['name' => 'Demo company holiday'] : null,
                            'punch_status' => $rejectedPunch ? 'rejected' : 'accepted',
                            'correction' => $rejectedPunch ? ['status' => 'rejected', 'reason' => 'Demo: punch could not be verified.'] : null,
                        ];

                        AttendanceDaily::updateOrCreate(
                            ['employee_id' => $employee->id, 'date' => $date->toDateString()],
                            [
                                'status' => $status,
                                'additional_statuses' => $overlap ? ['leave_approved'] : [],
                                'time_in' => in_array($status, ['present', 'late', 'overtime', 'half_day'], true) ? ($status === 'late' ? '09:15:00' : '09:00:00') : null,
                                'time_out' => match ($status) {
                                    'present', 'late' => '18:00:00', 'overtime' => '20:00:00', 'half_day' => '13:00:00', default => null
                                },
                                'late_minutes' => $status === 'late' ? 15 : 0,
                                'ot_hours' => $status === 'overtime' ? 2 : 0,
                                'pending_ot_hours' => $number === 2 && $day === 16 ? 2 : 0,
                                'remarks' => $overlap ? 'Holiday and approved leave on the same day.' : 'Demo data only.',
                                'calendar_details' => $details,
                            ],
                        );
                        if ($hasShift && $day % 2 === 0) {
                            CalendarReport::updateOrCreate(['employee_id' => $employee->id, 'date' => $date->toDateString()], [
                                'title' => 'Daily progress · '.$date->toDateString(),
                                'body' => 'Demo progress report. Completed scheduled work and shared the daily update.',
                                'submitted_at' => $date->setTime(18, 0),
                            ]);
                        }
                    }
                }
            }

            foreach (['employee' => 2, 'supervisor' => 1, 'hr' => 25] as $account => $number) {
                User::firstOrNew(['email' => $account.'@test.com'])->forceFill([
                    'name' => $employees[$number]->first_name.' '.$employees[$number]->last_name,
                    'role' => $account === 'hr' ? 'hr_admin' : $account,
                    'employee_id' => $employees[$number]->id,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ])->save();
            }
        });
    }
}
