<?php

namespace FlowAsia\Module4\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Stub storage only. Application readers must use CalendarDataSource. */
class AttendanceDaily extends Model
{
    protected $table = 'attendance_daily';

    protected $fillable = ['employee_id', 'date', 'status', 'additional_statuses', 'time_in', 'time_out', 'late_minutes', 'ot_hours', 'pending_ot_hours', 'remarks', 'calendar_details'];

    protected function casts(): array
    {
        return ['date' => 'date', 'additional_statuses' => 'array', 'calendar_details' => 'array', 'late_minutes' => 'integer', 'ot_hours' => 'decimal:2', 'pending_ot_hours' => 'decimal:2'];
    }

    protected function date(): Attribute
    {
        return Attribute::make(set: fn ($value) => CarbonImmutable::parse($value)->toDateString());
    }
}
