<?php

namespace FlowAsia\Module4\Http\Controllers;

use FlowAsia\Module4\Contracts\CalendarIdentitySource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $identity = app(CalendarIdentitySource::class)->forUser($request->user());
        $today = now(config('module4.timezone'));
        $navigation = [];
        foreach (config('module4.navigation.'.$identity['role'], []) as $key => $name) {
            $target = is_string($name) ? Route::getRoutes()->getByName($name) : null;
            if ($target && in_array('GET', $target->methods(), true)) {
                $navigation[$key] = route($name);
            }
        }
        $logoutRoute = config('module4.logout_route');
        $logout = is_string($logoutRoute) ? Route::getRoutes()->getByName($logoutRoute) : null;
        $config = [
            'baseUrl' => url('/'.trim(config('module4.route_prefix'), '/')),
            'user' => $identity,
            'navigation' => $navigation,
            'logoutUrl' => $logout && in_array('POST', $logout->methods(), true) ? route($logoutRoute) : null,
            'today' => $today->toDateString(),
            'todayLabel' => $today->format('l, F j, Y'),
            'timezone' => config('module4.timezone'),
            'hrRoles' => config('module4_calendar.hr_roles'),
            'eventTypes' => config('module4_calendar.event_types'),
            'month' => $today->format('Y-m'),
            'statuses' => config('module4_calendar.statuses') + ['invalid' => config('module4_calendar.invalid_status'), 'unrecorded' => config('module4_calendar.unrecorded_status')],
        ];
        $html = file_get_contents(config('module4.frontend_path').'/index.html');
        $html = str_replace('<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>', '', $html);
        $html = preg_replace('~(<span class="kpi-num"[^>]*>)[^<]*(</span>)~', '$1—$2', $html);
        $html = preg_replace('~(<span class="leave-val"[^>]*>)[^<]*(</span>)~', '$1—$2', $html);
        $html = str_replace('>Maria Santos<', '>'.e($identity['name']).'<', $html);
        $html = str_replace('>Creative Designer<', '>Loading profile…<', $html);
        $head = '<base href="'.e(str_replace('__path__', '', route('m4.assets', ['path' => '__path__']))).'">'
            .'<meta name="csrf-token" content="'.e(csrf_token()).'">'
            .'<script>window.flowAsiaBackend='.json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';</script>';

        $html = str_replace('<body>', '<body data-connected="true">', $html);

        return response(str_replace('<head>', '<head>'.$head, $html))->header('Cache-Control', 'no-store');
    }

    public function asset(string $path)
    {
        // Serve only the existing prototype's CSS/JS, never arbitrary workspace files.
        abort_unless(preg_match('~\A(?:css|js)/[a-zA-Z0-9_./-]+\.(?:css|js)\z~', $path), 404);
        $root = realpath(config('module4.frontend_path'));
        abort_unless($root, 404);
        $file = realpath($root.DIRECTORY_SEPARATOR.$path);
        abort_unless($file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, ['Content-Type' => str_ends_with($path, '.css') ? 'text/css' : 'text/javascript']);
    }
}
