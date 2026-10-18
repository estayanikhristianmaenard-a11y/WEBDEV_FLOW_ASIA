<?php

namespace FlowAsia\Module4\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReadOnlyCalendar
{
    public function handle(Request $request, Closure $next): Response
    {
        // Restrict M4 calendar paths only. HR company-event CRUD stays editable.
        $prefix = trim(config('module4.route_prefix'), '/');
        $paths = array_map(fn (string $path): string => ($prefix ? $prefix.'/' : '').$path, config('module4_calendar.read_only_paths'));
        if ($request->is(...$paths)
            && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(403, 'Attendance, leave and overtime are read-only in calendar views.');
        }

        return $next($request);
    }
}
