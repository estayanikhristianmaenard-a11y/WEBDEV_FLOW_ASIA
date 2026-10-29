<?php

namespace FlowAsia\Module4\Services;

use Carbon\CarbonImmutable;
use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use FlowAsia\Module4\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use WeakMap;

class MisCalendarIdentitySource implements CalendarIdentitySource
{
    public function forUser(Authenticatable $user): array
    {
        $attributes = request()->attributes;
        $key = 'm4.mis.identities.'.config('module4.mis_connection');
        if (! $attributes->has($key)) {
            $attributes->set($key, new WeakMap);
        }
        $identities = $attributes->get($key);

        return $identities[$user] ??= $this->resolve($user);
    }

    private function resolve(Authenticatable $user): array
    {
        $preview = $user instanceof User && config('module4.mode') === 'standalone'
            && config('module4.standalone_preview') && app()->environment(['local', 'testing']);
        $query = DB::connection(config('module4.mis_connection'))->table('users as u')
            ->join('roles as r', 'r.role_id', '=', 'u.role_id')
            ->join('employees as e', 'e.employee_id', '=', 'u.employee_id');
        $record = ($preview ? $query->where('u.email', $user->email) : $query->where('u.user_id', $user->getAuthIdentifier()))
            ->first(['u.user_id', 'u.employee_id', 'u.status', 'u.locked_until', 'r.role_name', 'e.first_name', 'e.last_name']);
        if ($record && strtoupper($record->status) === 'ACTIVE'
            && (! $record->locked_until || now()->gte(CarbonImmutable::parse($record->locked_until, config('module4.timezone'))))) {
            $role = strtolower(str_replace([' ', '-'], '_', $record->role_name));

            return [
                'id' => (int) $record->user_id, 'employee_id' => (int) $record->employee_id,
                'name' => trim($record->first_name.' '.$record->last_name),
                'role' => config('module4.identity.roles')[$record->role_name] ?? $role,
            ];
        }

        $local = (new SessionCalendarIdentitySource)->forUser($user);

        return [
            'id' => 0, 'employee_id' => null, 'name' => $local['name'],
            'role' => $preview && ! $record ? $local['role'] : '',
        ];
    }
}
