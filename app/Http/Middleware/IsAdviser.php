<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdviser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isAdviser') || ! $user->isAdviser()) {
            abort(403, 'Adviser access only.');
        }

        return $next($request);
    }
}
