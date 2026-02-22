<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveUserFromCookie
{
    /**
     * Resolve the current user from the 'user' cookie (ITS number) or Authorization: Bearer <its_no>.
     * Does not block the request if no valid user; $request->user() will be null.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $itsNo = $this->resolveItsNo($request);
        if ($itsNo !== null) {
            $user = User::with(['sharafTypes', 'roles'])->where('its_no', $itsNo)->first();
            if ($user) {
                $request->setUserResolver(fn () => $user);
            }
        }

        return $next($request);
    }

    /**
     * Get ITS number from cookie or Authorization Bearer header (for API clients that don't send cookies).
     */
    private function resolveItsNo(Request $request): ?string
    {
        $raw = $request->cookie('user');
        if ($raw !== null && $raw !== '') {
            $value = trim((string) $raw);
            if ($value !== '' && $this->isValidItsNo($value)) {
                return $value;
            }
        }

        $header = $request->header('Authorization');
        if ($header && str_starts_with($header, 'Bearer ')) {
            $value = trim(substr($header, 7));
            if ($value !== '' && $this->isValidItsNo($value)) {
                return $value;
            }
        }

        return null;
    }

    private function isValidItsNo(string $value): bool
    {
        return preg_match('/\A[0-9]+\z/', $value) === 1;
    }
}
