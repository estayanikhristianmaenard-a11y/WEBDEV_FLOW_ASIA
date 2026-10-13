<?php

namespace App\Models;

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

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (in_array($user->role, config('calendar.hr_roles'), true)) {
            return $query;
        }

        if ($user->employee_id === null || ! in_array($user->role, ['employee', 'supervisor'], true)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($user) {
            $scope->where('id', $user->employee_id);
            if ($user->role === 'supervisor') {
                $scope->orWhere('supervisor_id', $user->employee_id);
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
