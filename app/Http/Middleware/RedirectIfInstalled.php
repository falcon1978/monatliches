<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Installer::isInstalled()) {
            return $next($request);
        }

        if (Route::has('login')) {
            return redirect()->route('login');
        }

        abort(404);
    }
}
