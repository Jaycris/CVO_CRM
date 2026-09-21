<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RedirectIfMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenanceModeEnabled()) {
            return $next($request);
        }

        if ($this->routeIsAllowedDuringMaintenance($request)) {
            return $next($request);
        }

        if ($request->user()?->role?->name === 'Admin') {
            return $next($request);
        }

        return redirect()->route('maintenance');
    }

    private function maintenanceModeEnabled(): bool
    {
        try {
            return AppSetting::maintenanceModeEnabled();
        } catch (Throwable) {
            return false;
        }
    }

    private function routeIsAllowedDuringMaintenance(Request $request): bool
    {
        return $request->routeIs(
            'maintenance',
            'login',
            'logout',
            'password.*',
            'verification.*'
        );
    }
}
