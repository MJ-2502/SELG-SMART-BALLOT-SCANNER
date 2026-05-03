<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsFacilitator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isFacilitator') || ! $user->isFacilitator()) {
            abort(403, 'Facilitator access only.');
        }

        return $next($request);
    }
}