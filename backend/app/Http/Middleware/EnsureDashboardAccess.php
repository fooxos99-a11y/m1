<?php

namespace App\Http\Middleware;

use App\Services\CoreDataService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardAccess
{
    public function __construct(private readonly CoreDataService $coreDataService) {}

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

        $requiredPermissions = $this->requiredPermissions($request);

        if ($requiredPermissions === []) {
            return $this->forbiddenResponse();
        }

        $permissions = $this->coreDataService->loadRolePermissions()[$role] ?? [];
        $hasRequiredPermission = collect($requiredPermissions)->contains(
            static fn (string $permission): bool => ($permissions[$permission] ?? false) === true,
        );

        if (! $hasRequiredPermission) {
            return $this->forbiddenResponse();
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function requiredPermissions(Request $request): array
    {
        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());

        if ($path === 'api/dashboard/snapshot') {
            return [
                'add_student',
                'delete_student',
                'edit_student',
                'add_reciter',
                'delete_reciter',
                'edit_reciter',
                'transfer_reciter_student',
                'edit_pre_questions',
                'open_pre_exam',
                'edit_post_questions',
                'open_post_exam',
                'edit_tasks',
                'page_notifications',
                'page_activity_log',
                'page_results',
                'backup_export',
                'backup_import',
                'backup_restore',
            ];
        }

        if (
            Str::startsWith($path, 'api/dashboard/accounts')
            || Str::contains($path, 'role-permissions')
            || Str::contains($path, 'home-page-content')
            || Str::contains($path, 'practitioner-page-content')
            || Str::contains($path, 'final-exam')
            || Str::contains($path, 'satisfaction')
        ) {
            return [];
        }

        if (Str::contains($path, 'backup/restore')) {
            return ['backup_restore'];
        }

        if (Str::contains($path, 'backup/export')) {
            return ['backup_export'];
        }

        if (Str::startsWith($path, 'api/students')) {
            return match ($method) {
                'POST' => ['add_student'],
                'PUT' => ['edit_student'],
                'DELETE' => ['delete_student'],
                default => [],
            };
        }

        if (Str::startsWith($path, 'api/reciters')) {
            if ($method === 'POST') {
                return $request->filled('currentLoginCode') ? ['edit_reciter'] : ['add_reciter'];
            }

            return $method === 'DELETE' ? ['delete_reciter'] : [];
        }

        if (Str::contains($path, 'transfer-student')) {
            return ['transfer_reciter_student'];
        }

        if (Str::contains($path, 'activity-logs')) {
            return ['page_activity_log'];
        }

        if (Str::contains($path, 'notifications') || Str::contains($path, 'training-materials')) {
            return ['page_notifications'];
        }

        if (Str::contains($path, 'assessment-import')) {
            return ['edit_pre_questions', 'edit_post_questions', 'edit_tasks'];
        }

        if (Str::contains($path, 'task-templates')) {
            return ['edit_tasks'];
        }

        if (Str::contains($path, 'manual-attendance')) {
            return ['edit_student'];
        }

        if (Str::contains($path, 'assessment-submissions')) {
            return ['page_results'];
        }

        if (Str::contains($path, 'courses')) {
            return ['edit_pre_questions', 'open_pre_exam', 'edit_post_questions', 'open_post_exam', 'edit_tasks'];
        }

        if (Str::contains($path, 'questions')) {
            return ['edit_pre_questions', 'edit_post_questions', 'edit_tasks'];
        }

        if (Str::contains($path, 'results')) {
            return ['page_results'];
        }

        if (Str::contains($path, 'archives')) {
            return ['backup_export', 'backup_import', 'backup_restore'];
        }

        if (Str::contains($path, 'registration')) {
            return ['add_student', 'edit_student'];
        }

        return ['edit_student', 'edit_reciter', 'page_notifications', 'page_activity_log', 'page_results'];
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'غير مصرح لك بالوصول إلى لوحة التحكم.',
        ], Response::HTTP_FORBIDDEN);
    }
}
