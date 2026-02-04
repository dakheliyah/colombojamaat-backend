<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveUserFromCookie
{
    /**
     * Resolve the current user from the 'user' cookie (ITS number) and set it on the request.
     * Does not block the request if no valid user; $request->user() will be null.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->cookie('user');
        if ($raw !== null && $raw !== '') {
            $value = trim($raw);
            if ($value !== '' && $this->isValidItsNo($value)) {
                $user = User::with('sharafTypes')->where('its_no', $value)->first();
                if ($user) {
                    $request->setUserResolver(fn () => $user);
                }
            }
        }

        return $next($request);
    }

    private function isValidItsNo(string $value): bool
    {
        return preg_match('/\A[0-9]+\z/', $value) === 1;
    }
}
