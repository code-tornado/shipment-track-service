<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every API request belongs to the company of the authenticated token's user.
 * Runs after auth:sanctum; from here on all company-owned queries are scoped.
 */
class SetCurrentCompany
{
    public function __construct(private readonly CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->company) {
            abort(401, 'Unauthenticated.');
        }

        $this->current->set($user->company);

        return $next($request);
    }
}
