<?php

namespace FlowAsia\Module4\Http\Middleware;

use Closure;
use FlowAsia\Module4\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StandaloneModulePreview
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('module4.mode') === 'standalone' && config('module4.standalone_preview') && app()->environment(['local', 'testing']) && ! $request->user()) {
            $user = User::where('email', config('module4.preview_user_email'))->first();
            abort_unless($user, 503, 'The module preview requires seeded demo data.');
            Auth::setUser($user);
        }

        return $next($request);
    }
}
