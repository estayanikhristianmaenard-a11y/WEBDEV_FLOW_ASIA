<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $request->session()->regenerate();

        return redirect()->intended('/m4');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect('/login');
    }

    public function show(Request $request)
    {
        $config = [
            'user' => $request->user()->only(['name', 'role', 'employee_id']),
            'hrRoles' => config('calendar.hr_roles'),
            'month' => now()->format('Y-m'),
        ];
        $html = file_get_contents(base_path('../flow-asia-dashboard-prototype/index.html'));
        $head = '<base href="'.e(url('/m4/assets')).'/">'
            .'<meta name="csrf-token" content="'.e(csrf_token()).'">'
            .'<script>window.flowAsiaBackend='.json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';</script>';

        return response(str_replace('<head>', '<head>'.$head, $html))->header('Cache-Control', 'no-store');
    }

    public function asset(string $path)
    {
        // Serve only the existing prototype's CSS/JS, never arbitrary workspace files.
        abort_unless(preg_match('~\A(?:css|js)/[a-zA-Z0-9_./-]+\.(?:css|js)\z~', $path), 404);
        $root = realpath(base_path('../flow-asia-dashboard-prototype'));
        $file = realpath($root.DIRECTORY_SEPARATOR.$path);
        abort_unless($file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, ['Content-Type' => str_ends_with($path, '.css') ? 'text/css' : 'text/javascript']);
    }
}
