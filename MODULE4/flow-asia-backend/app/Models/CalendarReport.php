<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class CalendarReport extends Model
{
    protected $fillable = ['employee_id', 'date', 'title', 'body', 'submitted_at'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'submitted_at' => 'immutable_datetime'];
    }

    protected function date(): Attribute
    {
        return Attribute::make(set: fn ($value): string => CarbonImmutable::parse($value)->toDateString());
    }
}
