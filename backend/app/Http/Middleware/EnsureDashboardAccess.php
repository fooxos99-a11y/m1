<?php

namespace App\Http\Middleware;

use App\Services\CoreDataService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardAccess
{
    public function __construct(private readonly CoreDataService $coreDataService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $role = $user?->role;

        if ($role === 'admin') {
            return $next($request);
        }

        if (! in_array($role, ['male_manager', 'female_manager'], true)) {
            return $this->forbiddenResponse();
        }

        $permissions = $this->coreDataService->loadRolePermissions()[$role] ?? [];
        $hasAnyPermission = collect($permissions)->contains(static fn (bool $isEnabled): bool => $isEnabled === true);

        if (! $hasAnyPermission) {
            return $this->forbiddenResponse();
        }

        return $next($request);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'غير مصرح لك بالوصول إلى لوحة التحكم.',
        ], Response::HTTP_FORBIDDEN);
    }
}