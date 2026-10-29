<?php

namespace FlowAsia\Module4\Services;

use Carbon\CarbonInterface;
use FlowAsia\Module4\Contracts\CalendarContextSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MisCalendarContextSource implements CalendarContextSource
{
    public function forEmployees(array $employeeIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }
        $connection = DB::connection(config('module4.mis_connection'));
        $balances = $connection->table('leave_balances as b')
            ->join('leave_types as t', 't.leave_type_id', '=', 'b.leave_type_id')
            ->whereIn('b.employee_id', $employeeIds)->where('b.leave_year', $from->year)
            ->get(['b.employee_id', 't.leave_type_name', 'b.remaining_days'])->groupBy('employee_id');
        $periods = $connection->table('payroll_periods')
            ->whereRaw('UPPER(status) NOT IN (?, ?)', ['CANCELLED', 'CANCELED'])
            ->where(fn ($query) => $query->where('pay_date', '>=', now(config('module4.timezone'))->toDateString())
                ->orWhere(fn ($overlap) => $overlap->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())))
            ->orderBy('start_date')->get(['start_date', 'end_date', 'pay_date'])
            ->map(fn (object $period): array => ['start' => $period->start_date, 'end' => $period->end_date, 'payday' => $period->pay_date])->all();

        return collect($employeeIds)->mapWithKeys(fn (int $id): array => [$id => [
            'profile' => [
                'leave_balances' => $balances->get($id, collect())->map(fn (object $balance): array => [
                    'type' => $balance->leave_type_name, 'remaining_days' => (float) $balance->remaining_days,
                ])->all(),
                'pay_periods' => $periods,
            ],
            'reports' => [],
        ]]);
    }

    public function reportForEmployee(int $employeeId, int $reportId): ?array
    {
        return null;
    }
}
