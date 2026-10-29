<?php

namespace FlowAsia\Module4\Console\Commands;

use FlowAsia\Module4\Providers\Module4ServiceProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('m4:check')]
#[Description('Check M4 adapter wiring, routes, assets and demo isolation without reading business records')]
class CheckModule4Integration extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $failed = false;
        $mode = config('module4.mode');
        if (! in_array($mode, ['standalone', 'shared'], true)) {
            $this->error('M4_MODE must be standalone or shared.');
            $failed = true;
        }
        if (! in_array(config('module4.data_schema'), ['demo', 'mis'], true)) {
            $this->error('M4_DATA_SCHEMA must be demo or mis.');
            $failed = true;
        }
        if (config('module4.data_schema') === 'mis') {
            try {
                $schema = Schema::connection(config('module4.mis_connection'));
                foreach ([
                    'employees' => ['employee_id', 'employee_number', 'department_id', 'position_id', 'hire_date'],
                    'attendance_daily' => ['attendance_daily_id', 'attendance_date', 'clock_in', 'overtime_minutes'],
                    'company_events' => ['company_event_id', 'event_title', 'start_datetime', 'end_datetime'],
                    'leave_requests' => ['employee_id', 'start_date', 'end_date', 'status'],
                    'overtime_requests' => ['employee_id', 'overtime_date', 'approved_minutes'],
                    'users' => ['user_id', 'employee_id', 'role_id'],
                    'roles' => ['role_id', 'role_name'],
                    'departments' => ['department_id', 'department_name'],
                    'positions' => ['position_id', 'position_name'],
                    'schedules' => ['schedule_id', 'schedule_date', 'shift_id'],
                    'shifts' => ['shift_id', 'start_time', 'end_time'],
                    'holidays' => ['holiday_date', 'holiday_name'],
                    'correction_requests' => ['correction_request_id', 'attendance_daily_id'],
                    'leave_balances' => ['employee_id', 'remaining_days', 'leave_year'],
                    'leave_types' => ['leave_type_id', 'leave_type_name'],
                    'payroll_periods' => ['start_date', 'end_date', 'pay_date'],
                ] as $table => $columns) {
                    if (! $schema->hasColumns($table, $columns)) {
                        throw new \LogicException('Missing or incompatible MIS table: '.$table);
                    }
                }
                $this->info('MIS schema passed; reports remain unavailable because the supplied schema has no report source.');
            } catch (Throwable $exception) {
                $this->error('MIS schema: '.$exception->getMessage());
                $failed = true;
            }
        }
        foreach (Module4ServiceProvider::SOURCES as $key => $contract) {
            try {
                $source = app($contract);
                if (! $source instanceof $contract || ($mode === 'shared' && in_array($source::class, Module4ServiceProvider::LOCAL_SOURCES, true))) {
                    throw new \LogicException('A shared adapter is required.');
                }
                $this->info($key.': '.$source::class);
            } catch (Throwable $exception) {
                $this->error($key.': '.$exception->getMessage());
                $failed = true;
            }
        }
        if (! is_file(config('module4.frontend_path').'/index.html')) {
            $this->error('M4_FRONTEND_PATH must point to flow-asia-dashboard-prototype.');
            $failed = true;
        }
        $prefix = trim(config('module4.route_prefix'), '/');
        $events = Route::getRoutes()->getByName(config('module4.route_name_prefix').'calendar.events');
        if (! Route::has('m4.dashboard') || ! Route::has('m4.assets')
            || $events?->uri() !== trim($prefix.'/calendar/events', '/')) {
            $this->error('M4 routes do not match configuration. Clear the host route/config cache and register Module4ServiceProvider.');
            $failed = true;
        }
        if ($mode === 'shared' && (config('module4.standalone_preview') || ! config('module4.route_prefix') || ! config('module4.route_name_prefix'))) {
            $this->error('Shared mode requires preview=false and nonempty route and route-name prefixes.');
            $failed = true;
        }
        if ($failed) {
            return self::FAILURE;
        }
        $this->info('M4 wiring passed ('.$mode.'). This does not verify upstream business data or live authentication.');

        return self::SUCCESS;
    }
}
