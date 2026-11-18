<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Admin pages show every user's spending: anyone else gets the "Admins only" page with a 403. */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_admin !== true) {
            return Inertia::render('AdminsOnly')->toResponse($request)->setStatusCode(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
