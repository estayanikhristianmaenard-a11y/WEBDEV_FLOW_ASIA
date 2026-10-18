<?php

namespace FlowAsia\Module4\Models;

use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = ['employee_no', 'first_name', 'last_name', 'position', 'department_id', 'supervisor_id', 'hired_on', 'calendar_profile'];

    protected function casts(): array
    {
        return ['hired_on' => 'date', 'calendar_profile' => 'array'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeVisibleTo(Builder $query, Authenticatable $user): Builder
    {
        $identity = app(CalendarIdentitySource::class)->forUser($user);
        if (in_array($identity['role'], config('module4_calendar.hr_roles'), true)) {
            return $query;
        }

        if ($identity['employee_id'] === null || ! in_array($identity['role'], ['employee', 'supervisor'], true)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($identity) {
            $scope->where('id', $identity['employee_id']);
            if ($identity['role'] === 'supervisor') {
                $scope->orWhere('supervisor_id', $identity['employee_id']);
            }
        });
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }
}
