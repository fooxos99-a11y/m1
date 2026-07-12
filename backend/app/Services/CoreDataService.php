<?php

namespace App\Services;

use App\Events\DashboardActivityLogged;
use App\Events\DashboardNotificationCreated;
use App\Events\DashboardNotificationDeleted;
use App\Models\Branch;
use App\Models\Reciter;
use App\Models\RegistrationRequest;
use App\Models\Student;
use App\Models\TrainingMaterial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use ZipArchive;

class CoreDataService
{
    private const ACTIVITY_LOGS_CACHE_KEY = 'dashboard:activity_logs';

    private const NOTIFICATIONS_CACHE_KEY = 'dashboard:notifications';

    public function loadDashboardSnapshot(): array
    {
        $managedBranchId = $this->resolveManagedDashboardBranchId(auth()->user()?->role);
        $branches = Branch::query()->orderBy('created_at')->get();
        $students = Student::query()->whereNull('archive_id')->with(['branch', 'parts'])->orderBy('created_at')->get();
        $reciters = Reciter::query()->whereNull('archive_id')->with(['user', 'branch', 'students'])->orderBy('created_at')->get();
        $courses = DB::table('courses')->whereNull('archive_id')->orderBy('sort_order')->orderBy('created_at')->get();
        $questions = DB::table('course_questions')->whereNull('archive_id')->orderBy('sort_order')->get()->groupBy('course_id');
        $submissions = DB::table('course_submissions')->whereNull('archive_id')->orderByDesc('submitted_at')->get();
        $submissionAnswers = DB::table('course_submission_answers')->whereNull('archive_id')->get()->groupBy('submission_id');
        $attendance = DB::table('course_attendance')->whereNull('archive_id')->orderByDesc('created_at')->get();
        $taskTemplates = DB::table('task_templates')->orderByDesc('created_at')->get();
        $notifications = collect($this->loadNotifications());
        $activityLogs = collect($this->loadActivityLogs());
        $satisfactionQuestions = DB::table('satisfaction_questions')->whereNull('archive_id')->orderBy('sort_order')->get();
        $satisfactionResponses = DB::table('satisfaction_responses')->whereNull('archive_id')->orderByDesc('submitted_at')->get();
        $finalExamQuestions = DB::table('final_exam_questions')->whereNull('archive_id')->orderBy('sort_order')->get();
        $finalExamSubmissions = DB::table('final_exam_submissions')->whereNull('archive_id')->orderByDesc('submitted_at')->get();
        $finalExamAnswers = DB::table('final_exam_submission_answers')->whereNull('archive_id')->get()->groupBy('submission_id');
        $finalExamSettings = DB::table('final_exam_settings')->get()->keyBy('branch_code');
        $rolePermissions = DB::table('role_permissions')->get();
        $trainingMaterials = TrainingMaterial::query()->with('media')->orderByDesc('created_at')->get();

        $currentUser = auth('sanctum')->user() ?: auth()->user();
        $currentRole = (string) ($currentUser?->role ?? '');
        $currentLoginCode = trim((string) ($currentUser?->login_code ?? ''));

        if ($managedBranchId !== '') {
            $students = $students->filter(fn (Student $student) => ($student->branch?->code ?? 'male') === $managedBranchId)->values();
            $reciters = $reciters->filter(fn (Reciter $reciter) => ($reciter->branch?->code ?? 'male') === $managedBranchId)->values();

            $allowedStudentLogins = $students
                ->map(fn (Student $student) => (string) $student->login_code)
                ->filter(fn (string $loginCode) => $loginCode !== '')
                ->flip();

            $submissions = $submissions->filter(fn ($submission) => $allowedStudentLogins->has((string) $submission->login_code))->values();
            $attendance = $attendance->filter(fn ($item) => $allowedStudentLogins->has((string) $item->login_code))->values();
            $satisfactionResponses = $satisfactionResponses->filter(fn ($item) => $allowedStudentLogins->has((string) $item->login_code))->values();
            $finalExamQuestions = $finalExamQuestions->filter(fn ($item) => (string) $item->branch_code === $managedBranchId)->values();
            $finalExamSubmissions = $finalExamSubmissions->filter(fn ($item) => (string) $item->branch_code === $managedBranchId)->values();
            $trainingMaterials = $trainingMaterials
                ->filter(fn (TrainingMaterial $material) => ! $material->target_branch_code
                    || $material->target_branch_code === $managedBranchId
                    || $material->target_branch_code === 'supervision')
                ->values();
            $notifications = $notifications
                ->filter(fn (array $item) => ! ($item['targetBranchId'] ?? null) || ($item['targetBranchId'] ?? null) === $managedBranchId)
                ->values();
        }

        if (! in_array($currentRole, ['admin', 'male_manager', 'female_manager'], true)) {
            if (in_array($currentRole, ['student', 'trainee'], true)) {
                $students = $students
                    ->filter(fn (Student $student) => (string) $student->login_code === $currentLoginCode)
                    ->values();
                $reciters = collect();
            } elseif ($currentRole === 'reciter') {
                $currentReciter = $reciters->first(
                    fn (Reciter $reciter) => (string) ($reciter->user?->login_code ?? '') === $currentLoginCode,
                );
                $students = $currentReciter
                    ? $currentReciter->students->loadMissing(['branch', 'parts'])->values()
                    : collect();
                $reciters = $currentReciter ? collect([$currentReciter]) : collect();
            } else {
                $students = collect();
                $reciters = collect();
            }

            $allowedStudentLogins = $students
                ->map(fn (Student $student) => (string) $student->login_code)
                ->filter(fn (string $loginCode) => $loginCode !== '')
                ->flip();
            $allowedBranchCodes = $students
                ->map(fn (Student $student) => (string) ($student->branch?->code ?? ''))
                ->filter()
                ->unique()
                ->flip();

            $submissions = $submissions->filter(fn ($submission) => $allowedStudentLogins->has((string) $submission->login_code))->values();
            $attendance = $attendance->filter(fn ($item) => $allowedStudentLogins->has((string) $item->login_code))->values();
            $satisfactionResponses = $satisfactionResponses->filter(fn ($item) => $allowedStudentLogins->has((string) $item->login_code))->values();
            $finalExamQuestions = $finalExamQuestions->filter(fn ($item) => $allowedBranchCodes->has((string) $item->branch_code))->values();
            $finalExamSubmissions = $finalExamSubmissions->filter(fn ($item) => $allowedStudentLogins->has((string) $item->login_code))->values();
            $notifications = collect();
            $activityLogs = collect();
            $rolePermissions = collect();

            $studentBranchCode = $students->first()?->branch?->code;

            $trainingMaterials = $trainingMaterials
                ->filter(fn (TrainingMaterial $material) => $material->target_branch_code !== 'supervision'
                    && (! $material->target_branch_code || $material->target_branch_code === $studentBranchCode))
                ->values();
        }

        $questionsByCourse = $questions->map(fn (Collection $items) => [
            'pre' => $this->normalizeCourseQuestions($items->where('assessment_type', 'pre')),
            'post' => $this->normalizeCourseQuestions($items->where('assessment_type', 'post')),
            'tasks' => $this->normalizeCourseQuestions($items->where('assessment_type', 'tasks')),
        ]);

        return [
            'roles' => [
                ['id' => 'admin', 'label' => 'مدير النمو المهني'],
                ['id' => 'male_manager', 'label' => 'مشرف'],
                ['id' => 'female_manager', 'label' => 'مشرفة'],
                ['id' => 'student', 'label' => 'معلم/ة'],
                ['id' => 'reciter', 'label' => 'مقرئ'],
                ['id' => 'trainee', 'label' => 'معلم'],
            ],
            'branches' => $branches
                ->filter(fn (Branch $branch) => $managedBranchId === '' || $branch->code === $managedBranchId)
                ->map(fn (Branch $branch) => [
                    'id' => $branch->code,
                    'label' => $branch->name,
                ])->values()->all(),
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->full_name,
                'loginId' => $student->login_code,
                'branchId' => $student->branch?->code ?? 'male',
                'note' => $student->note,
                'isCertified' => $student->is_certified,
                'completedParts' => $student->parts->pluck('part_number')->sort()->values()->all(),
                'createdAt' => optional($student->created_at)->toISOString() ?? now()->toISOString(),
            ])->values()->all(),
            'reciters' => $reciters->map(fn (Reciter $reciter) => [
                'id' => $reciter->id,
                'name' => $reciter->full_name ?: ($reciter->user?->full_name ?? ''),
                'loginCode' => $reciter->user?->login_code ?? '',
                'branchId' => $reciter->branch?->code ?? 'male',
                'studentIds' => $reciter->students->pluck('id')->values()->all(),
            ])->values()->all(),
            'courses' => collect($courses)->map(function ($course) use ($questionsByCourse) {
                $courseQuestions = $questionsByCourse->get($course->id, ['pre' => [], 'post' => [], 'tasks' => []]);

                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'entityType' => $course->entity_type === 'task' ? 'task' : 'course',
                    'isActive' => (bool) $course->is_active,
                    'isPreEnabled' => (bool) $course->is_pre_enabled,
                    'isPostEnabled' => (bool) $course->is_post_enabled,
                    'isTasksEnabled' => (bool) $course->is_tasks_enabled,
                    'branchAvailability' => [
                        'male' => [
                            'pre' => (bool) $course->male_pre_enabled,
                            'post' => (bool) $course->male_post_enabled,
                            'tasks' => (bool) $course->male_tasks_enabled,
                        ],
                        'female' => [
                            'pre' => (bool) $course->female_pre_enabled,
                            'post' => (bool) $course->female_post_enabled,
                            'tasks' => (bool) $course->female_tasks_enabled,
                        ],
                    ],
                    'assessmentWindows' => $this->decodeJsonObject($course->assessment_windows, ['global' => [], 'male' => [], 'female' => []]),
                    'assessmentNotificationTemplates' => $this->decodeJsonObject($course->assessment_notification_templates, ['pre' => '', 'post' => '', 'tasks' => '']),
                    'taskMode' => $course->task_mode,
                    'taskTemplateId' => $course->task_template_id ?? '',
                    'taskTemplateName' => $course->task_template_name ?? '',
                    'taskTemplateContent' => $course->task_template_content ?? '',
                    'youtubeUrl' => $course->youtube_url ?? '',
                    'taskDescription' => $course->task_description ?? '',
                    'sortOrder' => (int) $course->sort_order,
                    'preQuestions' => $courseQuestions['pre'],
                    'postQuestions' => $courseQuestions['post'],
                    'taskQuestions' => $courseQuestions['tasks'],
                    'createdAt' => (string) $course->created_at,
                ];
            })->values()->all(),
            'taskTemplates' => collect($taskTemplates)->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'content' => $template->content ?? '',
                'createdAt' => (string) $template->created_at,
            ])->values()->all(),
            'submissions' => collect($submissions)->map(function ($submission) use ($submissionAnswers) {
                return [
                    'id' => $submission->id,
                    'courseId' => $submission->course_id,
                    'assessmentType' => $submission->assessment_type,
                    'studentName' => $submission->student_name,
                    'loginId' => $submission->login_code,
                    'manualScore' => $submission->manual_score !== null ? (float) $submission->manual_score : null,
                    'answers' => collect($submissionAnswers->get($submission->id, []))->map(fn ($answer) => [
                        'questionId' => $answer->question_id,
                        'value' => $answer->answer_text ?? '',
                        'fileName' => $answer->file_name,
                        'fileType' => $answer->file_type,
                        'fileDataUrl' => $answer->file_data_url,
                    ])->values()->all(),
                    'submittedAt' => (string) $submission->submitted_at,
                ];
            })->values()->all(),
            'attendance' => collect($attendance)->map(fn ($item) => [
                'id' => $item->id,
                'courseId' => $item->course_id,
                'studentName' => $item->student_name,
                'loginId' => $item->login_code,
                'source' => $item->source === 'manual' ? 'manual' : 'post-test',
                'createdAt' => (string) $item->created_at,
            ])->values()->all(),
            'notifications' => $notifications->values()->all(),
            'activityLogs' => $activityLogs->values()->all(),
            'satisfactionQuestions' => collect($satisfactionQuestions)->map(fn ($item) => [
                'id' => $item->id,
                'courseId' => $item->course_id ?? '',
                'prompt' => $item->prompt,
                'type' => $item->type === 'text' ? 'text' : 'rating',
                'isRequired' => (bool) $item->is_required,
                'sortOrder' => (int) $item->sort_order,
                'createdAt' => (string) $item->created_at,
            ])->values()->all(),
            'satisfactionResponses' => collect($satisfactionResponses)->map(fn ($item) => [
                'id' => $item->id,
                'courseId' => $item->course_id,
                'questionId' => $item->question_id,
                'loginCode' => $item->login_code,
                'studentName' => $item->student_name,
                'ratingValue' => $item->rating_value !== null ? (int) $item->rating_value : null,
                'textValue' => $item->text_value ?? '',
                'submittedAt' => (string) $item->submitted_at,
            ])->values()->all(),
            'finalExamQuestions' => collect($finalExamQuestions)->map(fn ($item) => [
                'id' => $item->id,
                'branchCode' => $item->branch_code,
                'type' => $this->mapQuestionType($item->question_type, $item->options),
                'prompt' => $item->prompt,
                'options' => $this->decodeJsonArray($item->options),
                'allowFile' => (bool) $item->allow_file,
                'points' => (int) $item->points,
                'correctAnswer' => $item->correct_answer ?? '',
                'attachmentName' => $item->attachment_name ?? '',
                'attachmentType' => $item->attachment_type ?? '',
                'attachmentDataUrl' => $item->attachment_data_url ?? '',
                'sortOrder' => (int) $item->sort_order,
                'createdAt' => (string) $item->created_at,
            ])->values()->all(),
            'finalExamSubmissions' => collect($finalExamSubmissions)->map(function ($item) use ($finalExamAnswers) {
                return [
                    'id' => $item->id,
                    'branchCode' => $item->branch_code,
                    'studentName' => $item->student_name,
                    'loginCode' => $item->login_code,
                    'manualScore' => $item->manual_score !== null ? (float) $item->manual_score : null,
                    'answers' => collect($finalExamAnswers->get($item->id, []))->map(fn ($answer) => [
                        'questionId' => $answer->question_id,
                        'value' => $answer->answer_text ?? '',
                        'fileName' => $answer->file_name,
                        'fileType' => $answer->file_type,
                        'fileDataUrl' => $answer->file_data_url,
                    ])->values()->all(),
                    'submittedAt' => (string) $item->submitted_at,
                ];
            })->values()->all(),
            'finalExamSettings' => [
                'male' => $managedBranchId !== '' && $managedBranchId !== 'male'
                    ? ['isEnabled' => false, 'closesAt' => null, 'notificationTemplate' => '']
                    : $this->normalizeFinalExamSetting($finalExamSettings->get('male')),
                'female' => $managedBranchId !== '' && $managedBranchId !== 'female'
                    ? ['isEnabled' => false, 'closesAt' => null, 'notificationTemplate' => '']
                    : $this->normalizeFinalExamSetting($finalExamSettings->get('female')),
            ],
            'trainingMaterials' => $trainingMaterials
                ->map(fn (TrainingMaterial $material) => $this->serializeTrainingMaterial($material))
                ->values()
                ->all(),
            'homePageContent' => $this->loadHomePageContent(),
            'practitionerPageContent' => $this->loadPractitionerPageContent(),
            'rolePermissions' => $rolePermissions
                ->groupBy('role')
                ->map(fn (Collection $items) => $items->mapWithKeys(fn ($item) => [$item->permission_key => (bool) $item->is_enabled])->all())
                ->all(),
        ];
    }

    private function resolveManagedDashboardBranchId(?string $role): string
    {
        return match ($role) {
            'male_manager' => 'male',
            'female_manager' => 'female',
            default => '',
        };
    }

    public function listDashboardAccounts(): array
    {
        return User::query()
            ->whereIn('role', ['admin', 'male_manager', 'female_manager'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->full_name,
                'loginCode' => $user->login_code ?? '',
                'role' => $user->role,
            ])
            ->all();
    }

    public function createDashboardAccount(string $name, string $loginCode, string $role): User
    {
        $name = trim($name);
        $loginCode = trim($loginCode);

        if ($name === '' || $loginCode === '') {
            throw ValidationException::withMessages(['login_code' => 'أدخل الاسم ورقم الدخول.']);
        }

        if (! in_array($role, ['admin', 'male_manager', 'female_manager'], true)) {
            throw ValidationException::withMessages(['role' => 'نوع الحساب الإشرافي غير صالح.']);
        }

        if (User::query()->where('login_code', $loginCode)->exists()) {
            throw ValidationException::withMessages(['login_code' => 'رقم الدخول مستخدم مسبقًا.']);
        }

        return User::query()->create([
            'full_name' => $name,
            'role' => $role,
            'login_code' => $loginCode,
            'password' => Hash::make($loginCode),
        ]);
    }

    public function loadRegistrationPublicStatus(): array
    {
        return [
            'isOpen' => $this->isRegistrationOpen(),
            'fields' => $this->loadRegistrationFormFields(),
            'branches' => Branch::query()
                ->orderBy('created_at')
                ->get()
                ->map(fn (Branch $branch) => [
                    'id' => $branch->code,
                    'label' => $branch->name,
                ])
                ->values()
                ->all(),
        ];
    }

    public function loadPublicStats(): array
    {
        $baseStats = [
            'batches' => 32,
            'courses' => 274,
            'managerGraduates' => 24,
            'supervisorGraduates' => 35,
            'secretaryGraduates' => 27,
            'practitionerGraduates' => 1466,
            'licenseDetails' => [
                'manager' => ['graduates' => 24, 'batches' => 1, 'courses' => 7],
                'supervisor' => ['graduates' => 35, 'batches' => 1, 'courses' => 6],
                'secretary' => ['graduates' => 27, 'batches' => 1, 'courses' => 3],
                'practitioner' => ['graduates' => 1466, 'batches' => 29, 'courses' => 258],
            ],
        ];

        $graduatesCount = $baseStats['managerGraduates']
            + $baseStats['supervisorGraduates']
            + $baseStats['secretaryGraduates']
            + $baseStats['practitionerGraduates'];

        $satisfactionRate = 0;
        if (Schema::hasTable('satisfaction_responses')) {
            $row = DB::table('satisfaction_responses')
                ->whereNotNull('rating_value')
                ->selectRaw('SUM(rating_value) as total, COUNT(*) as cnt')
                ->first();
            if ($row && $row->cnt > 0) {
                $satisfactionRate = (int) round(($row->total / ($row->cnt * 10)) * 100);
            }
        }

        return [
            'graduates' => $graduatesCount,
            'satisfactionRate' => $satisfactionRate,
            'courses' => $baseStats['courses'],
            'batches' => $baseStats['batches'],
            'licenseDetails' => $baseStats['licenseDetails'],
            'graduateDetails' => [
                'manager' => $baseStats['managerGraduates'],
                'supervisor' => $baseStats['supervisorGraduates'],
                'secretary' => $baseStats['secretaryGraduates'],
                'practitioner' => $baseStats['practitionerGraduates'],
            ],
        ];
    }

    public function loadRegistrationDashboardData(): array
    {
        $origin = rtrim((string) config('app.frontend_url', config('app.url', 'http://127.0.0.1:8080')), '/');

        return [
            'isOpen' => $this->isRegistrationOpen(),
            'registrationUrl' => $origin.'/registration',
            'fields' => $this->loadRegistrationFormFields(),
            'requests' => RegistrationRequest::query()
                ->orderByRaw("case when status = 'pending' then 0 when status = 'accepted' then 1 else 2 end")
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (RegistrationRequest $request) => $this->serializeRegistrationRequest($request))
                ->all(),
        ];
    }

    public function updateRegistrationSettings(bool $isOpen): void
    {
        DB::table('registration_settings')->updateOrInsert(
            ['key' => 'is_open'],
            ['value' => $isOpen ? '1' : '0', 'updated_at' => now()],
        );
    }

    public function loadRegistrationFormFields(): array
    {
        return $this->normalizeRegistrationFormFields(
            $this->loadJsonAppSetting('registration_form_fields', $this->defaultRegistrationFormFields()),
        );
    }

    public function updateRegistrationFormFields(array $fields): array
    {
        $normalized = $this->normalizeRegistrationFormFields($fields);

        $this->storeJsonAppSetting('registration_form_fields', $normalized);

        return $normalized;
    }

    public function loadHomePageContent(): array
    {
        return $this->normalizeHomePageContent(
            $this->loadJsonAppSetting('home_page_content', $this->defaultHomePageContent()),
        );
    }

    public function updateHomePageContent(array $content): array
    {
        $normalized = $this->normalizeHomePageContent($content);

        $this->storeJsonAppSetting('home_page_content', $normalized);

        return $normalized;
    }

    public function loadPractitionerPageContent(): array
    {
        return $this->normalizePractitionerPageContent(
            $this->loadJsonAppSetting('practitioner_page_content', $this->defaultPractitionerPageContent()),
        );
    }

    public function updatePractitionerPageContent(array $content): array
    {
        $normalized = $this->normalizePractitionerPageContent($content);

        $this->storeJsonAppSetting('practitioner_page_content', $normalized);

        return $normalized;
    }

    public function createDashboardBackupZip(string $zipPath): void
    {
        $snapshot = $this->loadDashboardSnapshot();
        $snapshot['backupMeta'] = [
            'version' => 1,
            'createdAt' => now()->toISOString(),
            'includesMediaFiles' => true,
        ];

        $mediaIndex = $this->attachTrainingMaterialMediaBackupPaths($snapshot);
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ValidationException::withMessages(['backup' => 'تعذر إنشاء ملف النسخة الاحتياطية.']);
        }

        $zip->addFromString('backup.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        foreach ($mediaIndex as $entry) {
            if (is_file($entry['sourcePath'])) {
                $zip->addFile($entry['sourcePath'], $entry['backupPath']);
            }
        }

        $zip->close();
    }

    public function restoreDashboardBackupZip(string $zipPath): array
    {
        $extractPath = storage_path('app/backup-restore/'.Str::uuid());
        File::ensureDirectoryExists($extractPath);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            File::deleteDirectory($extractPath);
            throw ValidationException::withMessages(['backup' => 'تعذر فتح ملف النسخة الاحتياطية.']);
        }

        try {
            $this->extractDashboardBackupZipSafely($zip, $extractPath);
        } catch (\Throwable $exception) {
            $zip->close();
            File::deleteDirectory($extractPath);

            throw $exception;
        }

        $zip->close();

        $backupJsonPath = $extractPath.DIRECTORY_SEPARATOR.'backup.json';

        if (! is_file($backupJsonPath)) {
            File::deleteDirectory($extractPath);
            throw ValidationException::withMessages(['backup' => 'ملف ZIP لا يحتوي backup.json.']);
        }

        $snapshot = json_decode((string) file_get_contents($backupJsonPath), true);

        if (! is_array($snapshot)) {
            File::deleteDirectory($extractPath);
            throw ValidationException::withMessages(['backup' => 'ملف backup.json غير صالح.']);
        }

        try {
            return $this->restoreDashboardSnapshot($snapshot, $extractPath);
        } finally {
            File::deleteDirectory($extractPath);
        }
    }

    private function extractDashboardBackupZipSafely(ZipArchive $zip, string $extractPath): void
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);

            if (! is_string($entryName)) {
                throw ValidationException::withMessages(['backup' => 'ملف ZIP يحتوي مسارًا غير آمن.']);
            }

            $normalizedName = $this->normalizeBackupZipEntryName($entryName);

            if ($normalizedName === null) {
                throw ValidationException::withMessages(['backup' => 'ملف ZIP يحتوي مسارًا غير آمن.']);
            }

            $entries[$entryName] = $normalizedName;
        }

        foreach ($entries as $entryName => $normalizedName) {
            $isDirectory = str_ends_with(str_replace('\\', '/', $entryName), '/');
            $targetPath = $this->resolveBackupExtractionPath($extractPath, $normalizedName, $isDirectory);

            if ($isDirectory) {
                continue;
            }

            $source = $zip->getStream($entryName);

            if ($source === false) {
                throw ValidationException::withMessages(['backup' => 'تعذر قراءة ملف داخل النسخة الاحتياطية.']);
            }

            $target = fopen($targetPath, 'wb');

            if ($target === false) {
                fclose($source);
                throw ValidationException::withMessages(['backup' => 'تعذر استخراج ملف النسخة الاحتياطية.']);
            }

            stream_copy_to_stream($source, $target);
            fclose($target);
            fclose($source);
        }
    }

    private function normalizeBackupZipEntryName(string $entryName): ?string
    {
        if ($entryName === '' || str_contains($entryName, "\0")) {
            return null;
        }

        $normalizedName = str_replace('\\', '/', $entryName);

        if (
            str_starts_with($normalizedName, '/')
            || preg_match('/^[A-Za-z]:/', $normalizedName) === 1
        ) {
            return null;
        }

        $normalizedName = trim($normalizedName, '/');

        if ($normalizedName === '') {
            return null;
        }

        foreach (explode('/', $normalizedName) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return null;
            }
        }

        return $normalizedName;
    }

    private function resolveBackupExtractionPath(string $extractPath, string $normalizedName, bool $isDirectory = false): string
    {
        $targetPath = $extractPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedName);
        $directoryPath = $isDirectory ? $targetPath : dirname($targetPath);

        File::ensureDirectoryExists($directoryPath);

        $basePath = realpath($extractPath) ?: $extractPath;
        $resolvedDirectory = realpath($directoryPath);

        if (
            $resolvedDirectory === false
            || ! str_starts_with(
                rtrim($resolvedDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
                rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
            )
        ) {
            throw ValidationException::withMessages(['backup' => 'ملف ZIP يحتوي مسارًا غير آمن.']);
        }

        return $targetPath;
    }

    public function restoreDashboardSnapshot(array $snapshot, ?string $mediaRoot = null): array
    {
        $this->validateDashboardSnapshotForRestore($snapshot);

        DB::transaction(function () use ($snapshot, $mediaRoot): void {
            $this->purgeActiveDashboardData();
            $this->restoreBranchesFromSnapshot($snapshot['branches'] ?? []);

            $studentIdMap = $this->restoreStudentsFromSnapshot($snapshot['students'] ?? []);
            $reciterIdMap = $this->restoreRecitersFromSnapshot($snapshot['reciters'] ?? [], $studentIdMap);
            $courseQuestionIds = $this->restoreCoursesFromSnapshot($snapshot['courses'] ?? []);

            $this->restoreTaskTemplatesFromSnapshot($snapshot['taskTemplates'] ?? []);
            $this->restoreCourseSubmissionsFromSnapshot($snapshot['submissions'] ?? [], $courseQuestionIds);
            $this->restoreAttendanceFromSnapshot($snapshot['attendance'] ?? []);
            $this->restoreNotificationsFromSnapshot($snapshot['notifications'] ?? []);
            $this->restoreSatisfactionFromSnapshot($snapshot['satisfactionQuestions'] ?? [], $snapshot['satisfactionResponses'] ?? []);
            $this->restoreFinalExamFromSnapshot($snapshot['finalExamQuestions'] ?? [], $snapshot['finalExamSubmissions'] ?? [], $snapshot['finalExamSettings'] ?? []);
            $this->restoreTrainingMaterialsFromSnapshot($snapshot['trainingMaterials'] ?? [], $mediaRoot);
            $this->restoreRolePermissionsFromSnapshot($snapshot['rolePermissions'] ?? []);

            if (array_key_exists('homePageContent', $snapshot)) {
                $this->storeJsonAppSetting('home_page_content', $this->normalizeHomePageContent((array) $snapshot['homePageContent']));
            }

            if (array_key_exists('practitionerPageContent', $snapshot)) {
                $this->storeJsonAppSetting('practitioner_page_content', $this->normalizePractitionerPageContent((array) $snapshot['practitionerPageContent']));
            }
        });

        $this->flushDashboardCaches();

        return $this->loadDashboardSnapshot();
    }

    private function validateDashboardSnapshotForRestore(array $snapshot): void
    {
        $requiredArrayKeys = ['students', 'courses'];
        $optionalArrayKeys = [
            'roles',
            'branches',
            'reciters',
            'taskTemplates',
            'submissions',
            'attendance',
            'notifications',
            'activityLogs',
            'satisfactionQuestions',
            'satisfactionResponses',
            'finalExamQuestions',
            'finalExamSubmissions',
            'finalExamSettings',
            'trainingMaterials',
            'homePageContent',
            'practitionerPageContent',
            'rolePermissions',
            'backupMeta',
        ];

        foreach ($requiredArrayKeys as $key) {
            if (! array_key_exists($key, $snapshot) || ! is_array($snapshot[$key])) {
                throw ValidationException::withMessages(['snapshot' => 'ملف النسخة الاحتياطية غير صالح أو ناقص.']);
            }
        }

        foreach ($optionalArrayKeys as $key) {
            if (array_key_exists($key, $snapshot) && ! is_array($snapshot[$key])) {
                throw ValidationException::withMessages(['snapshot' => 'ملف النسخة الاحتياطية غير صالح أو ناقص.']);
            }
        }
    }

    public function createRegistrationRequest(string $name, string $loginCode, string $phone, string $gender, array $answers = [], ?int $legacyAge = null): array
    {
        if (! $this->isRegistrationOpen()) {
            throw ValidationException::withMessages(['registration' => 'التسجيل مغلق حاليًا.']);
        }

        $name = trim($name);
        $loginCode = trim($loginCode);
        $phone = trim($phone);

        if ($name === '' || ! preg_match('/^\d{10}$/', $loginCode)) {
            throw ValidationException::withMessages(['loginCode' => 'رقم الهوية يجب أن يتكون من 10 أرقام.']);
        }

        if (! preg_match('/^\d{10}$/', $phone)) {
            throw ValidationException::withMessages(['phone' => 'رقم الجوال يجب أن يتكون من 10 أرقام.']);
        }

        if (! in_array($gender, ['male', 'female'], true)) {
            throw ValidationException::withMessages(['gender' => 'اختر الجنس.']);
        }

        if ($legacyAge !== null && ! array_key_exists('age', $answers)) {
            $answers['age'] = (string) $legacyAge;
        }

        $answers = $this->normalizeRegistrationAnswers($answers);

        $this->assertRegistrationLoginCodeAvailable($loginCode);

        $request = RegistrationRequest::query()->create([
            'full_name' => $name,
            'login_code' => $loginCode,
            'branch_code' => null,
            'note' => null,
            'status' => 'pending',
        ]);

        $this->storeRegistrationRequestMetadata($request->id, $phone, $gender, $answers, $legacyAge);

        return $this->serializeRegistrationRequest($request);
    }

    public function acceptRegistrationRequest(string $requestId, ?string $branchCode = null): array
    {
        $registrationRequest = RegistrationRequest::query()->find($requestId);

        if (! $registrationRequest) {
            throw ValidationException::withMessages(['requestId' => 'طلب التسجيل غير موجود.']);
        }

        if ($registrationRequest->status !== 'pending') {
            throw ValidationException::withMessages(['requestId' => 'تمت معالجة هذا الطلب مسبقًا.']);
        }

        $this->assertRegistrationLoginCodeAvailable($registrationRequest->login_code, $registrationRequest->id);
        $branchCode = $branchCode ?: $this->registrationRequestBranchCode($registrationRequest->id);
        $branch = $this->resolveBranchByCode($branchCode);

        DB::transaction(function () use ($registrationRequest, $branch): void {
            $student = $this->createStudent(
                $registrationRequest->full_name,
                $registrationRequest->login_code,
                $branch->code,
            );

            $registrationRequest->forceFill([
                'full_name' => $registrationRequest->full_name,
                'branch_code' => $branch->code,
                'status' => 'accepted',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'decision_reason' => 'تم القبول وإنشاء سجل المعلم/ة وحساب الدخول.',
                'note' => $student->note,
            ])->save();
        });

        $this->forgetRegistrationRequestAge($registrationRequest->id);

        return $this->serializeRegistrationRequest($registrationRequest->fresh());
    }

    public function rejectRegistrationRequest(string $requestId, string $reason = ''): array
    {
        $registrationRequest = RegistrationRequest::query()->find($requestId);

        if (! $registrationRequest) {
            throw ValidationException::withMessages(['requestId' => 'طلب التسجيل غير موجود.']);
        }

        if ($registrationRequest->status !== 'pending') {
            throw ValidationException::withMessages(['requestId' => 'تمت معالجة هذا الطلب مسبقًا.']);
        }

        $registrationRequest->forceFill([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'decision_reason' => trim($reason),
        ])->save();

        $this->forgetRegistrationRequestAge($registrationRequest->id);

        return $this->serializeRegistrationRequest($registrationRequest);
    }

    public function markRegistrationRequestAccepted(string $requestId): array
    {
        $registrationRequest = RegistrationRequest::query()->find($requestId);

        if (! $registrationRequest) {
            throw ValidationException::withMessages(['requestId' => 'طلب التسجيل غير موجود.']);
        }

        if ($registrationRequest->status !== 'pending') {
            throw ValidationException::withMessages(['requestId' => 'تمت معالجة هذا الطلب مسبقًا.']);
        }

        $registrationRequest->forceFill([
            'status' => 'accepted',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'decision_reason' => 'تم اعتماد الطلب يدويًا دون إنشاء حساب جديد.',
        ])->save();

        $this->forgetRegistrationRequestAge($registrationRequest->id);

        return $this->serializeRegistrationRequest($registrationRequest);
    }

    public function deleteDashboardAccount(string $accountId): void
    {
        User::query()
            ->whereKey($accountId)
            ->whereIn('role', ['admin', 'male_manager', 'female_manager'])
            ->delete();
    }

    public function createTaskTemplate(string $name, string $content = ''): array
    {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'اسم القالب مطلوب.']);
        }

        $templateId = (string) str()->uuid();
        $createdAt = now();

        DB::table('task_templates')->insert([
            'id' => $templateId,
            'name' => $name,
            'content' => $content,
            'created_at' => $createdAt,
        ]);

        return [
            'id' => $templateId,
            'name' => $name,
            'content' => $content,
            'createdAt' => $createdAt->toISOString(),
        ];
    }

    public function updateTaskTemplate(string $templateId, array $updates): void
    {
        $template = DB::table('task_templates')->where('id', $templateId)->first();

        if (! $template) {
            throw ValidationException::withMessages(['templateId' => 'القالب المحدد غير موجود.']);
        }

        $payload = [];

        if (array_key_exists('name', $updates)) {
            $name = trim((string) $updates['name']);

            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'اسم القالب مطلوب.']);
            }

            $payload['name'] = $name;
        }

        if (array_key_exists('content', $updates)) {
            $payload['content'] = (string) ($updates['content'] ?? '');
        }

        if ($payload !== []) {
            DB::table('task_templates')->where('id', $templateId)->update($payload);
        }
    }

    public function createStudent(string $name, string $loginCode, string $branchCode, string $note = ''): Student
    {
        $name = trim($name);
        $loginCode = trim($loginCode);
        $branch = $this->resolveBranchByCode($branchCode);

        if ($name === '' || $loginCode === '') {
            throw ValidationException::withMessages(['login_code' => 'أدخل اسم المعلم/ة والفرع ورقم الدخول.']);
        }

        if (Student::query()->where('login_code', $loginCode)->exists()) {
            throw ValidationException::withMessages(['login_code' => 'رقم الدخول مستخدم مسبقًا.']);
        }

        if (User::query()->where('login_code', $loginCode)->exists()) {
            throw ValidationException::withMessages(['login_code' => 'رقم الدخول مستخدم مسبقًا.']);
        }

        return DB::transaction(function () use ($name, $loginCode, $branch, $note): Student {
            $student = Student::query()->create([
                'full_name' => $name,
                'login_code' => $loginCode,
                'branch_id' => $branch->id,
                'note' => $note,
                'is_certified' => false,
            ]);

            User::query()->create([
                'full_name' => $name,
                'role' => 'student',
                'login_code' => $loginCode,
                'password' => Hash::make($loginCode),
            ]);

            return $student;
        });
    }

    private function isRegistrationOpen(): bool
    {
        return (string) DB::table('registration_settings')->where('key', 'is_open')->value('value') === '1';
    }

    private function assertRegistrationLoginCodeAvailable(string $loginCode, ?string $exceptRequestId = null): void
    {
        if (Student::query()->where('login_code', $loginCode)->exists() || User::query()->where('login_code', $loginCode)->exists()) {
            throw ValidationException::withMessages(['loginCode' => 'رقم الدخول مستخدم مسبقًا.']);
        }

        $requestQuery = RegistrationRequest::query()
            ->where('login_code', $loginCode)
            ->whereIn('status', ['pending', 'accepted']);

        if ($exceptRequestId) {
            $requestQuery->where('id', '!=', $exceptRequestId);
        }

        if ($requestQuery->exists()) {
            throw ValidationException::withMessages(['loginCode' => 'يوجد طلب مسجل بهذا الرقم بالفعل.']);
        }
    }

    private function serializeRegistrationRequest(RegistrationRequest $request): array
    {
        return [
            'id' => $request->id,
            'name' => $request->full_name,
            'loginCode' => $request->login_code,
            'phone' => $request->status === 'pending' ? $this->registrationRequestPhone($request->id) : null,
            'age' => $request->status === 'pending' ? $this->registrationRequestAge($request->id) : null,
            'gender' => $request->status === 'pending' ? $this->registrationRequestGender($request->id) : null,
            'answers' => $this->registrationRequestAnswers($request->id),
            'branchId' => $request->branch_code ?: null,
            'note' => $request->note ?? '',
            'status' => $request->status,
            'decisionReason' => $request->decision_reason ?? '',
            'reviewedAt' => optional($request->reviewed_at)?->toISOString(),
            'createdAt' => optional($request->created_at)?->toISOString() ?? now()->toISOString(),
        ];
    }

    private function registrationRequestAge(string $requestId): ?int
    {
        $metadata = $this->loadRegistrationRequestMetadata();
        $age = $metadata[$requestId]['age'] ?? null;

        return is_numeric($age) ? (int) $age : null;
    }

    private function registrationRequestGender(string $requestId): ?string
    {
        $metadata = $this->loadRegistrationRequestMetadata();
        $gender = $metadata[$requestId]['gender'] ?? null;

        return in_array($gender, ['male', 'female'], true) ? $gender : null;
    }

    private function registrationRequestPhone(string $requestId): ?string
    {
        $metadata = $this->loadRegistrationRequestMetadata();
        $phone = (string) ($metadata[$requestId]['phone'] ?? '');

        return preg_match('/^\d{10}$/', $phone) ? $phone : null;
    }

    private function registrationRequestBranchCode(string $requestId): string
    {
        return $this->registrationRequestGender($requestId) === 'female' ? 'female' : 'male';
    }

    private function registrationRequestAnswers(string $requestId): array
    {
        $metadata = $this->loadRegistrationRequestMetadata();
        $answers = $metadata[$requestId]['answers'] ?? [];

        return is_array($answers) ? $answers : [];
    }

    private function storeRegistrationRequestAge(string $requestId, int $age): void
    {
        $metadata = $this->loadRegistrationRequestMetadata();
        $metadata[$requestId] = ['age' => $age];

        $this->writeRegistrationRequestMetadata($metadata);
    }

    private function storeRegistrationRequestMetadata(string $requestId, string $phone, string $gender, array $answers, ?int $legacyAge = null): void
    {
        $metadata = $this->loadRegistrationRequestMetadata();
        $metadata[$requestId] = [
            'age' => $legacyAge,
            'phone' => $phone,
            'gender' => $gender,
            'answers' => $answers,
        ];

        $this->writeRegistrationRequestMetadata($metadata);
    }

    private function defaultRegistrationFormFields(): array
    {
        return [[
            'id' => 'age',
            'label' => 'العمر',
            'type' => 'number',
            'required' => true,
            'options' => [],
        ]];
    }

    private function normalizeRegistrationFormFields(array $fields): array
    {
        $hasLegacyPhoneField = collect($fields)->contains(
            fn ($field): bool => trim((string) ($field['label'] ?? '')) === 'رقم الجوال',
        );

        $normalized = collect($fields)
            ->map(function ($field): array {
                $type = in_array(($field['type'] ?? 'text'), ['text', 'number', 'select'], true) ? $field['type'] : 'text';
                $options = collect($field['options'] ?? [])
                    ->map(fn ($option) => trim((string) $option))
                    ->filter()
                    ->values()
                    ->all();

                if ($type === 'select' && $options === []) {
                    $options = ['خيار 1'];
                }

                return [
                    'id' => trim((string) ($field['id'] ?? '')) ?: (string) str()->uuid(),
                    'label' => trim((string) ($field['label'] ?? '')),
                    'type' => $type,
                    'required' => (bool) ($field['required'] ?? true),
                    'options' => $type === 'select' ? $options : [],
                ];
            })
            ->filter(fn (array $field): bool => $field['label'] !== '' && $field['label'] !== 'رقم الجوال')
            ->values()
            ->all();

        if ($hasLegacyPhoneField && ! collect($normalized)->contains('id', 'age')) {
            array_unshift($normalized, $this->defaultRegistrationFormFields()[0]);
        }

        return $normalized;
    }

    private function normalizeRegistrationAnswers(array $answers): array
    {
        $fields = $this->loadRegistrationFormFields();
        $normalized = [];
        $errors = [];

        foreach ($fields as $field) {
            $value = trim((string) ($answers[$field['id']] ?? ''));

            if ($field['required'] && $value === '') {
                $errors['answers'] = 'أكمل بيانات التسجيل المطلوبة.';
                break;
            }

            if ($value !== '' && $field['type'] === 'select' && ! in_array($value, $field['options'], true)) {
                $errors['answers'] = 'اختر قيمة صحيحة من القائمة.';
                break;
            }

            if ($value !== '' && $field['type'] === 'number' && ! preg_match('/^\d+$/', $value)) {
                $errors['answers'] = 'أدخل رقمًا صحيحًا في الحقول الرقمية.';
                break;
            }

            $normalized[$field['id']] = [
                'label' => $field['label'],
                'value' => $value,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    private function forgetRegistrationRequestAge(string $requestId): void
    {
        $metadata = $this->loadRegistrationRequestMetadata();

        if (! array_key_exists($requestId, $metadata)) {
            return;
        }

        unset($metadata[$requestId]);
        $this->writeRegistrationRequestMetadata($metadata);
    }

    private function loadRegistrationRequestMetadata(): array
    {
        $path = $this->registrationRequestMetadataPath();

        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeRegistrationRequestMetadata(array $metadata): void
    {
        $path = $this->registrationRequestMetadataPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function registrationRequestMetadataPath(): string
    {
        return storage_path('app/registration-request-metadata.json');
    }

    public function loadTrainingMaterials(): array
    {
        return TrainingMaterial::query()
            ->with(['media', 'branch'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TrainingMaterial $material) => $this->serializeTrainingMaterial($material))
            ->values()
            ->all();
    }

    public function createTrainingMaterial(string $title, string $description, ?string $branchCode = null, array $attachments = []): array
    {
        $title = trim($title);
        $description = trim($description);
        $branchCode = $branchCode !== null ? trim($branchCode) : null;

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'عنوان المادة التدريبية مطلوب.']);
        }

        if ($attachments === []) {
            throw ValidationException::withMessages(['attachments' => 'أرفق ملفًا واحدًا على الأقل.']);
        }

        $targetBranchCode = null;

        if ($branchCode === 'supervision') {
            $targetBranchCode = 'supervision';
        } elseif ($branchCode !== null && $branchCode !== '' && $branchCode !== 'all') {
            $targetBranchCode = $this->resolveBranchByCode($branchCode)->code;
        }

        $material = DB::transaction(function () use ($title, $description, $targetBranchCode, $attachments): TrainingMaterial {
            $material = TrainingMaterial::query()->create([
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'target_branch_code' => $targetBranchCode,
                'external_attachments' => $this->normalizeExternalTrainingMaterialAttachments($attachments),
                'created_by' => auth()->id(),
            ]);

            foreach ($attachments as $index => $attachment) {
                $file = $attachment['file'] ?? null;
                $url = trim((string) ($attachment['url'] ?? ''));
                $label = trim((string) ($attachment['label'] ?? ''));

                if (! $file && $url === '') {
                    throw ValidationException::withMessages([
                        "attachments.$index.file" => 'اختر ملفًا أو أضف رابط يوتيوب.',
                    ]);
                }

                if ($label === '' && $url === '') {
                    throw ValidationException::withMessages([
                        "attachments.$index.label" => 'اسم الملف أو رابط المقطع مطلوب.',
                    ]);
                }

                if (! $file) {
                    continue;
                }

                $material
                    ->addMedia($file)
                    ->usingName($label)
                    ->usingFileName($file->hashName())
                    ->withCustomProperties([
                        'display_name' => $label,
                        'original_client_name' => $file->getClientOriginalName(),
                    ])
                    ->toMediaCollection('attachments', 'public');
            }

            return $material->fresh(['media', 'branch']);
        });

        $this->flushDashboardCaches();

        return $this->serializeTrainingMaterial($material);
    }

    public function updateTrainingMaterial(string $materialId, string $title, string $description, ?string $branchCode = null, array $attachments = []): array
    {
        $material = TrainingMaterial::query()->with(['media', 'branch'])->find($materialId);

        if (! $material) {
            throw ValidationException::withMessages(['materialId' => 'المادة التدريبية المحددة غير موجودة.']);
        }

        $title = trim($title);
        $description = trim($description);
        $branchCode = $branchCode !== null ? trim($branchCode) : null;

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'عنوان المادة التدريبية مطلوب.']);
        }

        if ($attachments === []) {
            throw ValidationException::withMessages(['attachments' => 'أرفق ملفًا واحدًا على الأقل.']);
        }

        $targetBranchCode = null;

        if ($branchCode === 'supervision') {
            $targetBranchCode = 'supervision';
        } elseif ($branchCode !== null && $branchCode !== '' && $branchCode !== 'all') {
            $targetBranchCode = $this->resolveBranchByCode($branchCode)->code;
        }

        $updatedMaterial = DB::transaction(function () use ($material, $title, $description, $targetBranchCode, $attachments): TrainingMaterial {
            $material->forceFill([
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'target_branch_code' => $targetBranchCode,
                'external_attachments' => $this->normalizeExternalTrainingMaterialAttachments($attachments),
            ])->save();

            $existingMedia = $material->getMedia('attachments');
            $mediaIndex = collect();

            foreach ($existingMedia as $media) {
                $mediaIndex->put((string) $media->id, $media);

                if ($media->uuid) {
                    $mediaIndex->put((string) $media->uuid, $media);
                }
            }

            $keepMediaIds = [];

            foreach ($attachments as $index => $attachment) {
                $label = trim((string) ($attachment['label'] ?? ''));
                $attachmentId = trim((string) ($attachment['id'] ?? ''));
                $file = $attachment['file'] ?? null;
                $url = trim((string) ($attachment['url'] ?? ''));

                if ($label === '' && $url === '') {
                    throw ValidationException::withMessages([
                        "attachments.$index.label" => 'اسم الملف أو رابط المقطع مطلوب.',
                    ]);
                }

                if ($url !== '') {
                    continue;
                }

                if ($attachmentId !== '') {
                    $media = $mediaIndex->get($attachmentId);

                    if (! $media) {
                        throw ValidationException::withMessages([
                            "attachments.$index.id" => 'الملف المحدد غير موجود ضمن المادة التدريبية.',
                        ]);
                    }

                    $customProperties = $media->custom_properties ?? [];
                    $customProperties['display_name'] = $label;

                    $media->name = $label;
                    $media->custom_properties = $customProperties;
                    $media->save();

                    $keepMediaIds[] = (string) $media->id;

                    continue;
                }

                if (! $file) {
                    throw ValidationException::withMessages([
                        "attachments.$index.file" => 'اختر ملفًا أو أضف رابط يوتيوب.',
                    ]);
                }

                $newMedia = $material
                    ->addMedia($file)
                    ->usingName($label)
                    ->usingFileName($file->hashName())
                    ->withCustomProperties([
                        'display_name' => $label,
                        'original_client_name' => $file->getClientOriginalName(),
                    ])
                    ->toMediaCollection('attachments', 'public');

                $keepMediaIds[] = (string) $newMedia->id;
            }

            $existingMedia
                ->filter(fn ($media) => ! in_array((string) $media->id, $keepMediaIds, true))
                ->each(fn ($media) => $media->delete());

            return $material->fresh(['media', 'branch']);
        });

        $this->flushDashboardCaches();

        return $this->serializeTrainingMaterial($updatedMaterial);
    }

    public function deleteTrainingMaterial(string $materialId): void
    {
        $material = TrainingMaterial::query()->with('media')->find($materialId);

        if (! $material) {
            throw ValidationException::withMessages(['materialId' => 'المادة التدريبية المحددة غير موجودة.']);
        }

        $material->clearMediaCollection('attachments');
        $material->delete();

        $this->flushDashboardCaches();
    }

    public function createCourse(string $title, bool $isActive, array $options = []): array
    {
        $title = trim($title);
        $entityType = ($options['entityType'] ?? 'course') === 'task' ? 'task' : 'course';
        $taskMode = $entityType === 'task' ? (($options['taskMode'] ?? 'questions') === 'document' ? 'document' : 'questions') : null;

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'اسم الدورة مطلوب.']);
        }

        $courseId = (string) str()->uuid();
        $createdAt = now();

        DB::transaction(function () use ($courseId, $createdAt, $title, $isActive, $entityType, $taskMode, $options): void {
            if ($isActive && $entityType !== 'task') {
                DB::table('courses')->update(['is_active' => false]);
            }

            DB::table('courses')->insert([
                'id' => $courseId,
                'title' => $title,
                'entity_type' => $entityType,
                'task_mode' => $taskMode,
                'task_template_id' => ($options['taskTemplateId'] ?? '') !== '' ? $options['taskTemplateId'] : null,
                'task_template_name' => $options['taskTemplateName'] ?? '',
                'task_template_content' => $options['taskTemplateContent'] ?? '',
                'youtube_url' => $options['youtubeUrl'] ?? '',
                'task_description' => $options['taskDescription'] ?? '',
                'is_active' => $entityType === 'task' ? false : $isActive,
                'is_pre_enabled' => true,
                'is_post_enabled' => true,
                'is_tasks_enabled' => false,
                'male_pre_enabled' => true,
                'female_pre_enabled' => true,
                'male_post_enabled' => true,
                'female_post_enabled' => true,
                'male_tasks_enabled' => true,
                'female_tasks_enabled' => true,
                'assessment_windows' => json_encode(['global' => [], 'male' => [], 'female' => []], JSON_UNESCAPED_UNICODE),
                'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => ''], JSON_UNESCAPED_UNICODE),
                'sort_order' => (int) DB::table('courses')->count(),
                'created_at' => $createdAt,
            ]);
        });

        $course = DB::table('courses')->where('id', $courseId)->first();

        return $this->mapCourseRow($course, ['pre' => [], 'post' => [], 'tasks' => []]);
    }

    public function updateCourse(string $courseId, array $updates): void
    {
        $course = DB::table('courses')->where('id', $courseId)->first();

        if (! $course) {
            throw ValidationException::withMessages(['courseId' => 'الدورة المحددة غير موجودة.']);
        }

        $payload = [];
        $wasTasksEnabled = (bool) $course->is_tasks_enabled;
        $previousWindows = $this->decodeJsonObject($course->assessment_windows, ['global' => [], 'male' => [], 'female' => []]);

        if (array_key_exists('title', $updates)) {
            $payload['title'] = trim((string) $updates['title']);
        }

        if (array_key_exists('entityType', $updates)) {
            $payload['entity_type'] = $updates['entityType'] === 'task' ? 'task' : 'course';
        }

        if (array_key_exists('isActive', $updates)) {
            $payload['is_active'] = (bool) $updates['isActive'];
        }

        if (array_key_exists('isPreEnabled', $updates)) {
            $payload['is_pre_enabled'] = (bool) $updates['isPreEnabled'];
        }

        if (array_key_exists('isPostEnabled', $updates)) {
            $payload['is_post_enabled'] = (bool) $updates['isPostEnabled'];
        }

        if (array_key_exists('isTasksEnabled', $updates)) {
            $payload['is_tasks_enabled'] = (bool) $updates['isTasksEnabled'];
        }

        if (isset($updates['branchAvailability']) && is_array($updates['branchAvailability'])) {
            $payload['male_pre_enabled'] = (bool) data_get($updates, 'branchAvailability.male.pre', true);
            $payload['female_pre_enabled'] = (bool) data_get($updates, 'branchAvailability.female.pre', true);
            $payload['male_post_enabled'] = (bool) data_get($updates, 'branchAvailability.male.post', true);
            $payload['female_post_enabled'] = (bool) data_get($updates, 'branchAvailability.female.post', true);
            $payload['male_tasks_enabled'] = (bool) data_get($updates, 'branchAvailability.male.tasks', true);
            $payload['female_tasks_enabled'] = (bool) data_get($updates, 'branchAvailability.female.tasks', true);
        }

        if (array_key_exists('assessmentWindows', $updates)) {
            $payload['assessment_windows'] = json_encode($updates['assessmentWindows'], JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('assessmentNotificationTemplates', $updates)) {
            $payload['assessment_notification_templates'] = json_encode($updates['assessmentNotificationTemplates'], JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('taskMode', $updates)) {
            $payload['task_mode'] = $updates['taskMode'];
        }

        if (array_key_exists('taskTemplateId', $updates)) {
            $payload['task_template_id'] = (($updates['taskTemplateId'] ?? '') !== '') ? $updates['taskTemplateId'] : null;
        }

        if (array_key_exists('taskTemplateName', $updates)) {
            $payload['task_template_name'] = (string) $updates['taskTemplateName'];
        }

        if (array_key_exists('taskTemplateContent', $updates)) {
            $payload['task_template_content'] = (string) $updates['taskTemplateContent'];
        }

        if (array_key_exists('youtubeUrl', $updates)) {
            $payload['youtube_url'] = (string) $updates['youtubeUrl'];
        }

        if (array_key_exists('taskDescription', $updates)) {
            $payload['task_description'] = (string) $updates['taskDescription'];
        }

        $nextCourse = null;
        $nextWindows = ['global' => [], 'male' => [], 'female' => []];
        $templates = ['pre' => '', 'post' => '', 'tasks' => ''];
        $tasksJustOpened = false;
        $isTaskCourse = ($payload['entity_type'] ?? $course->entity_type ?? 'course') === 'task';

        DB::transaction(function () use (
            $courseId,
            $payload,
            $updates,
            $wasTasksEnabled,
            $previousWindows,
            &$nextCourse,
            &$nextWindows,
            &$templates,
            &$tasksJustOpened,
        ): void {
            if ($payload !== []) {
                DB::table('courses')->where('id', $courseId)->update($payload);
            }

            if (! (array_key_exists('isTasksEnabled', $updates) || array_key_exists('assessmentWindows', $updates))) {
                return;
            }

            $nextCourse = DB::table('courses')->where('id', $courseId)->first();

            if (! $nextCourse) {
                return;
            }

            $nextWindows = $this->decodeJsonObject($nextCourse->assessment_windows, ['global' => [], 'male' => [], 'female' => []]);
            $templates = $this->decodeJsonObject($nextCourse->assessment_notification_templates, ['pre' => '', 'post' => '', 'tasks' => '']);
            $tasksJustOpened = (bool) ($nextCourse->is_tasks_enabled ?? false)
                && (! $wasTasksEnabled || $this->hasAssessmentWindowOpened($previousWindows, $nextWindows, 'tasks'));

            if ($tasksJustOpened) {
                $this->closeOtherOpenedTaskCourses($courseId);
            }
        });

        if ($tasksJustOpened) {
            $nextCourse = DB::table('courses')->where('id', $courseId)->first();
            $nextWindows = $this->decodeJsonObject($nextCourse?->assessment_windows, ['global' => [], 'male' => [], 'female' => []]);
            $templates = $this->decodeJsonObject($nextCourse?->assessment_notification_templates, ['pre' => '', 'post' => '', 'tasks' => '']);
            $this->dispatchAssessmentOpenNotification($nextCourse, 'tasks', $templates, $nextWindows);
        }
    }

    private function closeOtherOpenedTaskCourses(string $activeTaskId): void
    {
        DB::table('courses')
            ->where('entity_type', 'task')
            ->where('id', '!=', $activeTaskId)
            ->orderBy('sort_order')
            ->get()
            ->each(function (object $task): void {
                $windows = $this->decodeJsonObject($task->assessment_windows, ['global' => [], 'male' => [], 'female' => []]);

                $windows['global'] = [
                    ...((array) ($windows['global'] ?? [])),
                    'tasks' => null,
                ];
                $windows['male'] = [
                    ...((array) ($windows['male'] ?? [])),
                    'tasks' => null,
                ];
                $windows['female'] = [
                    ...((array) ($windows['female'] ?? [])),
                    'tasks' => null,
                ];

                DB::table('courses')->where('id', $task->id)->update([
                    'is_tasks_enabled' => false,
                    'male_tasks_enabled' => false,
                    'female_tasks_enabled' => false,
                    'assessment_windows' => json_encode($windows, JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    public function deleteCourse(string $courseId): void
    {
        DB::table('courses')->where('id', $courseId)->delete();
    }

    public function updateCoursesSortOrder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                DB::table('courses')->where('id', $id)->update(['sort_order' => $index]);
            }
        });
    }

    public function activateCourse(string $courseId, ?array $settings = null): void
    {
        $course = DB::table('courses')->where('id', $courseId)->first();

        if (! $course) {
            throw ValidationException::withMessages(['courseId' => 'الدورة المحددة غير موجودة.']);
        }

        $wasPreEnabled = (bool) $course->is_pre_enabled;
        $wasPostEnabled = (bool) $course->is_post_enabled;
        $entityType = (string) ($course->entity_type ?? 'course');

        DB::transaction(function () use ($courseId, $settings, $entityType): void {
            DB::table('courses')
                ->where('id', '!=', $courseId)
                ->where('entity_type', $entityType)
                ->update(['is_active' => false]);

            $payload = ['is_active' => true];

            if ($settings !== null) {
                $payload['is_pre_enabled'] = (bool) ($settings['pre'] ?? false);
                $payload['is_post_enabled'] = (bool) ($settings['post'] ?? false);
                $payload['is_tasks_enabled'] = (bool) ($settings['tasks'] ?? false);
            }

            DB::table('courses')->where('id', $courseId)->update($payload);
        });

        if ($settings !== null) {
            $freshCourse = DB::table('courses')->where('id', $courseId)->first();
            $templates = $this->decodeJsonObject($freshCourse?->assessment_notification_templates, ['pre' => '', 'post' => '', 'tasks' => '']);
            $windows = $this->decodeJsonObject($freshCourse?->assessment_windows, ['global' => [], 'male' => [], 'female' => []]);

            if ((bool) ($settings['pre'] ?? false) && ! $wasPreEnabled) {
                $this->dispatchAssessmentOpenNotification($freshCourse, 'pre', $templates, $windows);
            }

            if ((bool) ($settings['post'] ?? false) && ! $wasPostEnabled) {
                $this->dispatchAssessmentOpenNotification($freshCourse, 'post', $templates, $windows);
            }
        }
    }

    public function deactivateAllCourses(): void
    {
        DB::table('courses')->update(['is_active' => false]);
    }

    public function addCourseQuestion(string $courseId, string $assessmentType, array $question): string
    {
        if (! in_array($assessmentType, ['pre', 'post', 'tasks'], true)) {
            throw ValidationException::withMessages(['assessmentType' => 'نوع التقييم غير صالح.']);
        }

        if (! DB::table('courses')->where('id', $courseId)->exists()) {
            throw ValidationException::withMessages(['courseId' => 'الدورة المحددة غير موجودة.']);
        }

        $questionId = (string) str()->uuid();
        $type = ($question['type'] ?? 'multiple') === 'truefalse' ? 'multiple' : ($question['type'] ?? 'multiple');
        $options = ($question['type'] ?? 'multiple') === 'truefalse' ? ['صح', 'خطأ'] : ($question['options'] ?? []);
        $sortOrder = (int) DB::table('course_questions')
            ->where('course_id', $courseId)
            ->where('assessment_type', $assessmentType)
            ->count();

        DB::table('course_questions')->insert([
            'id' => $questionId,
            'course_id' => $courseId,
            'assessment_type' => $assessmentType,
            'question_type' => $type,
            'prompt' => $question['prompt'],
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'allow_file' => (bool) ($question['allowFile'] ?? false),
            'points' => (int) ($question['points'] ?? 1),
            'correct_answer' => $question['correctAnswer'] ?? '',
            'attachment_name' => $question['attachmentName'] ?? '',
            'attachment_type' => $question['attachmentType'] ?? '',
            'attachment_data_url' => $question['attachmentDataUrl'] ?? '',
            'sort_order' => $sortOrder,
            'created_at' => now(),
        ]);

        return $questionId;
    }

    public function deleteCourseQuestion(string $questionId): void
    {
        DB::table('course_questions')->where('id', $questionId)->delete();
    }

    public function updateCourseQuestion(string $questionId, array $question): void
    {
        $existingQuestion = DB::table('course_questions')->where('id', $questionId)->first();

        if (! $existingQuestion) {
            throw ValidationException::withMessages(['questionId' => 'السؤال المحدد غير موجود.']);
        }

        $type = ($question['type'] ?? 'multiple') === 'truefalse' ? 'multiple' : ($question['type'] ?? 'multiple');
        $options = ($question['type'] ?? 'multiple') === 'truefalse' ? ['صح', 'خطأ'] : ($question['options'] ?? []);

        DB::table('course_questions')->where('id', $questionId)->update([
            'question_type' => $type,
            'prompt' => trim((string) $question['prompt']),
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'allow_file' => (bool) ($question['allowFile'] ?? false),
            'points' => (int) ($question['points'] ?? 1),
            'correct_answer' => $question['correctAnswer'] ?? '',
            'attachment_name' => $question['attachmentName'] ?? '',
            'attachment_type' => $question['attachmentType'] ?? '',
            'attachment_data_url' => $question['attachmentDataUrl'] ?? '',
        ]);
    }

    public function bulkImportAssessments(string $courseId, string $assessmentType, array $submissions): array
    {
        if (! in_array($assessmentType, ['pre', 'post', 'tasks'], true)) {
            throw ValidationException::withMessages(['assessmentType' => 'نوع التقييم غير صالح.']);
        }

        if (! DB::table('courses')->where('id', $courseId)->exists()) {
            throw ValidationException::withMessages(['courseId' => 'الدورة المحددة غير موجودة.']);
        }

        if ($submissions === []) {
            return [];
        }

        $dedupedByLogin = [];
        foreach ($submissions as $submission) {
            $dedupedByLogin[$submission['loginId']] = $submission;
        }

        $normalizedSubmissions = array_values($dedupedByLogin);
        $loginIds = array_values(array_map(fn (array $submission) => $submission['loginId'], $normalizedSubmissions));
        $actionableSubmissions = array_values(array_filter($normalizedSubmissions, function (array $submission): bool {
            $hasManualScore = isset($submission['manualScore']) && is_numeric($submission['manualScore']) && (float) $submission['manualScore'] >= 0;
            $hasAnswers = collect($submission['answers'] ?? [])->contains(function (array $answer): bool {
                return ($answer['questionId'] ?? '') !== '__score_override__'
                    && (trim((string) ($answer['value'] ?? '')) !== '' || ! empty($answer['fileDataUrl']));
            });

            return $hasManualScore || $hasAnswers;
        }));

        return DB::transaction(function () use ($courseId, $assessmentType, $loginIds, $actionableSubmissions, $normalizedSubmissions): array {
            $existingIds = DB::table('course_submissions')
                ->where('course_id', $courseId)
                ->where('assessment_type', $assessmentType)
                ->whereIn('login_code', $loginIds)
                ->pluck('id')
                ->all();

            if ($existingIds !== []) {
                DB::table('course_submission_answers')->whereIn('submission_id', $existingIds)->delete();
                DB::table('course_submissions')
                    ->where('course_id', $courseId)
                    ->where('assessment_type', $assessmentType)
                    ->whereIn('login_code', $loginIds)
                    ->delete();
            }

            if ($actionableSubmissions === []) {
                return [];
            }

            $studentIdByLogin = Student::query()
                ->whereIn('login_code', array_map(fn (array $submission) => $submission['loginId'], $actionableSubmissions))
                ->pluck('id', 'login_code')
                ->all();

            $inserted = [];
            $answersToInsert = [];

            foreach ($actionableSubmissions as $submission) {
                $submissionId = (string) str()->uuid();
                $submittedAt = now();

                DB::table('course_submissions')->insert([
                    'id' => $submissionId,
                    'course_id' => $courseId,
                    'assessment_type' => $assessmentType,
                    'student_id' => $studentIdByLogin[$submission['loginId']] ?? null,
                    'student_name' => $submission['studentName'],
                    'login_code' => $submission['loginId'],
                    'manual_score' => isset($submission['manualScore']) && is_numeric($submission['manualScore']) ? (float) $submission['manualScore'] : null,
                    'submitted_at' => $submittedAt,
                ]);

                foreach (($submission['answers'] ?? []) as $answer) {
                    if (($answer['questionId'] ?? '') === '__score_override__') {
                        continue;
                    }

                    $answersToInsert[] = [
                        'id' => (string) str()->uuid(),
                        'submission_id' => $submissionId,
                        'question_id' => $answer['questionId'],
                        'answer_text' => $answer['value'] ?? null,
                        'file_name' => $answer['fileName'] ?? null,
                        'file_type' => $answer['fileType'] ?? null,
                        'file_data_url' => $answer['fileDataUrl'] ?? null,
                        'created_at' => now(),
                    ];
                }

                $inserted[] = [
                    'id' => $submissionId,
                    'submittedAt' => $submittedAt->toISOString(),
                    'loginId' => $submission['loginId'],
                ];
            }

            if ($answersToInsert !== []) {
                DB::table('course_submission_answers')->insert($answersToInsert);
            }

            return array_values(array_filter($inserted, function (array $row) use ($normalizedSubmissions): bool {
                return collect($normalizedSubmissions)->contains(fn (array $submission) => $submission['loginId'] === $row['loginId']);
            }));
        });
    }

    public function addSatisfactionQuestion(
        string $prompt,
        string $type,
        bool $isRequired,
        string $targetScope = 'all',
        ?string $courseId = null,
    ): array {
        $prompt = trim($prompt);
        $type = $type === 'text' ? 'text' : 'rating';
        $targetScope = $targetScope === 'course' ? 'course' : 'all';
        $courseId = $courseId !== null ? trim($courseId) : null;

        if ($prompt === '') {
            throw ValidationException::withMessages(['prompt' => 'نص السؤال مطلوب.']);
        }

        if ($targetScope === 'course') {
            if ($courseId === null || $courseId === '') {
                throw ValidationException::withMessages(['courseId' => 'اختر دورة صالحة.']);
            }

            $targetCourses = DB::table('courses')
                ->where('id', $courseId)
                ->where('entity_type', '!=', 'task')
                ->where('is_post_enabled', true)
                ->orderBy('sort_order')
                ->get();

            if ($targetCourses->isEmpty()) {
                throw ValidationException::withMessages(['courseId' => 'تعذر العثور على الدورة المحددة.']);
            }
        } else {
            $targetCourses = DB::table('courses')
                ->where('entity_type', '!=', 'task')
                ->where('is_post_enabled', true)
                ->orderBy('sort_order')
                ->get();
        }

        if ($targetCourses->isEmpty()) {
            return [];
        }

        $createdAt = now();
        $rows = $targetCourses->map(function ($course) use ($prompt, $type, $isRequired, $createdAt) {
            return [
                'id' => (string) str()->uuid(),
                'course_id' => $course->id,
                'prompt' => $prompt,
                'type' => $type,
                'is_required' => $isRequired,
                'sort_order' => (int) DB::table('satisfaction_questions')->where('course_id', $course->id)->count(),
                'created_at' => $createdAt,
            ];
        })->all();

        DB::table('satisfaction_questions')->insert($rows);

        return array_map(fn (array $row) => [
            'id' => $row['id'],
            'courseId' => $row['course_id'],
            'createdAt' => $createdAt->toISOString(),
        ], $rows);
    }

    public function deleteSatisfactionQuestion(string $questionId): void
    {
        DB::table('satisfaction_questions')->where('id', $questionId)->delete();
    }

    public function submitSatisfactionResponses(array $responses): array
    {
        $rows = array_map(fn (array $response) => [
            'id' => (string) str()->uuid(),
            'course_id' => $response['courseId'],
            'question_id' => $response['questionId'],
            'login_code' => $response['loginCode'],
            'student_name' => $response['studentName'],
            'rating_value' => $response['ratingValue'],
            'text_value' => ($response['textValue'] ?? '') !== '' ? $response['textValue'] : null,
            'submitted_at' => now(),
        ], $responses);

        DB::table('satisfaction_responses')->upsert(
            $rows,
            ['course_id', 'question_id', 'login_code'],
            ['student_name', 'rating_value', 'text_value', 'submitted_at'],
        );

        return array_map(fn (array $row) => [
            'id' => (string) DB::table('satisfaction_responses')
                ->where('course_id', $row['course_id'])
                ->where('question_id', $row['question_id'])
                ->where('login_code', $row['login_code'])
                ->value('id'),
            'courseId' => $row['course_id'],
            'questionId' => $row['question_id'],
            'loginCode' => $row['login_code'],
        ], $rows);
    }

    public function addFinalExamQuestion(string $branchCode, array $question): array
    {
        $branchCode = $this->normalizeBranchCode($branchCode);
        $id = (string) str()->uuid();
        $createdAt = now();
        $type = ($question['type'] ?? 'multiple') === 'text' ? 'text' : 'multiple';
        $isTrueFalse = ($question['type'] ?? 'multiple') === 'truefalse';
        $sortOrder = (int) DB::table('final_exam_questions')->where('branch_code', $branchCode)->count();

        DB::table('final_exam_questions')->insert([
            'id' => $id,
            'branch_code' => $branchCode,
            'question_type' => $type,
            'prompt' => trim((string) $question['prompt']),
            'options' => json_encode($isTrueFalse ? ['صح', 'خطأ'] : ($question['options'] ?? []), JSON_UNESCAPED_UNICODE),
            'allow_file' => (bool) ($question['allowFile'] ?? false),
            'points' => (int) ($question['points'] ?? 1),
            'correct_answer' => $question['correctAnswer'] ?? '',
            'attachment_name' => '',
            'attachment_type' => '',
            'attachment_data_url' => '',
            'sort_order' => $sortOrder,
            'created_at' => $createdAt,
        ]);

        return ['id' => $id, 'createdAt' => $createdAt->toISOString()];
    }

    public function deleteFinalExamQuestion(string $questionId): void
    {
        DB::table('final_exam_questions')->where('id', $questionId)->delete();
    }

    public function updateFinalExamQuestion(string $questionId, array $question): void
    {
        $existingQuestion = DB::table('final_exam_questions')->where('id', $questionId)->first();

        if (! $existingQuestion) {
            throw ValidationException::withMessages(['questionId' => 'السؤال المحدد غير موجود.']);
        }

        $type = ($question['type'] ?? 'multiple') === 'text' ? 'text' : 'multiple';
        $isTrueFalse = ($question['type'] ?? 'multiple') === 'truefalse';

        DB::table('final_exam_questions')->where('id', $questionId)->update([
            'question_type' => $type,
            'prompt' => trim((string) $question['prompt']),
            'options' => json_encode($isTrueFalse ? ['صح', 'خطأ'] : ($question['options'] ?? []), JSON_UNESCAPED_UNICODE),
            'allow_file' => (bool) ($question['allowFile'] ?? false),
            'points' => (int) ($question['points'] ?? 1),
            'correct_answer' => $question['correctAnswer'] ?? '',
        ]);
    }

    public function updateFinalExamSetting(string $branchCode, bool $isEnabled, ?string $closesAt, ?string $notificationTemplate = null): void
    {
        $branchCode = $this->normalizeBranchCode($branchCode);
        $existingSetting = DB::table('final_exam_settings')->where('branch_code', $branchCode)->first();
        $wasEnabled = (bool) ($existingSetting->is_enabled ?? false);
        $resolvedClosesAt = null;

        if ($closesAt) {
            $resolvedClosesAt = Carbon::parse($closesAt)->format('Y-m-d H:i:s');
        }

        $resolvedTemplate = $notificationTemplate ?? (string) ($existingSetting->notification_template ?? '');

        DB::table('final_exam_settings')->updateOrInsert(
            ['branch_code' => $branchCode],
            [
                'is_enabled' => $isEnabled,
                'closes_at' => $resolvedClosesAt,
                'notification_template' => $resolvedTemplate,
            ],
        );

        if ($isEnabled && ! $wasEnabled) {
            $this->dispatchFinalExamOpenNotification($branchCode, $resolvedClosesAt, $resolvedTemplate);
        }
    }

    public function updateFinalExamNotificationTemplate(string $branchCode, string $notificationTemplate): void
    {
        $branchCode = $this->normalizeBranchCode($branchCode);

        DB::table('final_exam_settings')->updateOrInsert(
            ['branch_code' => $branchCode],
            [
                'notification_template' => $notificationTemplate,
                'is_enabled' => (bool) DB::table('final_exam_settings')->where('branch_code', $branchCode)->value('is_enabled'),
                'closes_at' => DB::table('final_exam_settings')->where('branch_code', $branchCode)->value('closes_at'),
            ],
        );
    }

    public function submitFinalExam(array $submission): array
    {
        $branchCode = $this->normalizeBranchCode($submission['branchCode']);
        $loginCode = trim((string) $submission['loginCode']);

        $this->assertFinalExamSubmissionIsOpen($branchCode);

        if (DB::table('final_exam_submissions')->where('login_code', $loginCode)->exists()) {
            throw ValidationException::withMessages(['loginCode' => 'تم إرسال الاختبار النهائي مسبقًا.']);
        }

        $submissionId = (string) str()->uuid();
        $submittedAt = now();

        DB::transaction(function () use ($submissionId, $submittedAt, $branchCode, $submission, $loginCode): void {
            DB::table('final_exam_submissions')->insert([
                'id' => $submissionId,
                'branch_code' => $branchCode,
                'student_name' => $submission['studentName'],
                'login_code' => $loginCode,
                'submitted_at' => $submittedAt,
            ]);

            $answers = collect($submission['answers'] ?? [])
                ->filter(fn (array $answer) => ($answer['questionId'] ?? '') !== '__score_override__')
                ->map(fn (array $answer) => [
                    'id' => (string) str()->uuid(),
                    'submission_id' => $submissionId,
                    'question_id' => $answer['questionId'],
                    'answer_text' => $answer['value'] ?? null,
                    'file_name' => $answer['fileName'] ?? null,
                    'file_type' => $answer['fileType'] ?? null,
                    'file_data_url' => $answer['fileDataUrl'] ?? null,
                ])
                ->all();

            if ($answers !== []) {
                DB::table('final_exam_submission_answers')->insert($answers);
            }
        });

        return ['id' => $submissionId, 'submittedAt' => $submittedAt->toISOString()];
    }

    public function copyFinalExamQuestions(string $from, string $to, bool $move): void
    {
        $from = $this->normalizeBranchCode($from);
        $to = $this->normalizeBranchCode($to);

        $sourceQuestions = DB::table('final_exam_questions')
            ->where('branch_code', $from)
            ->orderBy('sort_order')
            ->get();

        if ($sourceQuestions->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($sourceQuestions, $from, $to, $move): void {
            DB::table('final_exam_questions')->where('branch_code', $to)->delete();

            DB::table('final_exam_questions')->insert($sourceQuestions->map(fn ($question) => [
                'id' => (string) str()->uuid(),
                'branch_code' => $to,
                'question_type' => $question->question_type,
                'prompt' => $question->prompt,
                'options' => $question->options,
                'allow_file' => $question->allow_file,
                'points' => $question->points,
                'correct_answer' => $question->correct_answer,
                'attachment_name' => $question->attachment_name,
                'attachment_type' => $question->attachment_type,
                'attachment_data_url' => $question->attachment_data_url,
                'sort_order' => $question->sort_order,
                'created_at' => now(),
            ])->all());

            if ($move) {
                DB::table('final_exam_questions')->where('branch_code', $from)->delete();
            }
        });
    }

    public function setFinalExamManualScore(string $submissionId, float|int|null $score): void
    {
        DB::table('final_exam_submissions')->where('id', $submissionId)->update(['manual_score' => $score]);
    }

    public function setAssessmentManualScore(string $submissionId, float|int|null $score): void
    {
        DB::table('course_submissions')->where('id', $submissionId)->update(['manual_score' => $score]);
        $this->flushDashboardCaches();
    }

    public function updateStudent(Student $student, array $updates): Student
    {
        $payload = [];
        $originalLoginCode = $student->login_code;
        $originalName = $student->full_name;

        if (array_key_exists('name', $updates)) {
            $payload['full_name'] = trim((string) $updates['name']);
        }

        if (array_key_exists('loginCode', $updates)) {
            $nextLoginCode = trim((string) $updates['loginCode']);
            $exists = Student::query()
                ->where('login_code', $nextLoginCode)
                ->whereKeyNot($student->getKey())
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages(['loginCode' => 'رقم الدخول مستخدم مسبقًا.']);
            }

            $userExists = User::query()
                ->where('login_code', $nextLoginCode)
                ->where(function ($query) use ($originalLoginCode): void {
                    $query->where('role', '!=', 'student')
                        ->orWhere('login_code', '!=', $originalLoginCode);
                })
                ->exists();

            if ($userExists) {
                throw ValidationException::withMessages(['loginCode' => 'رقم الدخول مستخدم مسبقًا.']);
            }

            $payload['login_code'] = $nextLoginCode;
        }

        if (array_key_exists('note', $updates)) {
            $payload['note'] = (string) $updates['note'];
        }

        if (array_key_exists('isCertified', $updates)) {
            $payload['is_certified'] = (bool) $updates['isCertified'];
        }

        if (array_key_exists('branchId', $updates)) {
            $payload['branch_id'] = $this->resolveBranchByCode((string) $updates['branchId'])->id;
        }

        DB::transaction(function () use ($student, $payload, $updates, $originalLoginCode, $originalName): void {
            if ($payload !== []) {
                $student->fill($payload);
                $student->save();
            }

            $student->loadMissing('branch');
            $partsLimit = $student->branch?->code === 'female' ? 10 : 30;

            $targetLoginCode = $payload['login_code'] ?? $originalLoginCode;
            $targetName = $payload['full_name'] ?? $originalName;

            User::query()->updateOrCreate(
                ['login_code' => $originalLoginCode],
                [
                    'full_name' => $targetName,
                    'role' => 'student',
                    'login_code' => $targetLoginCode,
                    'password' => User::query()->where('login_code', $originalLoginCode)->value('password') ?: Hash::make($targetLoginCode),
                ],
            );

            if (array_key_exists('completedParts', $updates) && is_array($updates['completedParts'])) {
                $student->parts()->delete();

                $parts = collect($updates['completedParts'])
                    ->map(fn ($value) => (int) $value)
                    ->filter(fn (int $partNumber) => $partNumber >= 1 && $partNumber <= $partsLimit)
                    ->unique()
                    ->sort()
                    ->values();

                foreach ($parts as $partNumber) {
                    DB::table('student_parts')->insert([
                        'student_id' => $student->id,
                        'part_number' => $partNumber,
                        'marked_by_reciter_id' => null,
                        'marked_at' => now(),
                    ]);
                }
            } elseif ($partsLimit === 10) {
                $student->parts()->where('part_number', '>', $partsLimit)->delete();
            }
        });

        return $student->fresh(['branch', 'parts']);
    }

    public function deleteStudent(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            User::query()->where('login_code', $student->login_code)->where('role', 'student')->delete();
            $student->delete();
        });
    }

    public function saveReciter(?string $currentLoginCode, string $name, string $loginCode, string $branchCode, array $linkedStudentIds = []): Reciter
    {
        $name = trim($name);
        $loginCode = trim($loginCode);
        $currentLoginCode = trim((string) $currentLoginCode);
        $branch = $this->resolveBranchByCode($branchCode);

        if ($name === '' || $loginCode === '') {
            throw ValidationException::withMessages(['login_code' => 'أدخل اسم المقرئ والفرع ورقم الدخول.']);
        }

        return DB::transaction(function () use ($currentLoginCode, $name, $loginCode, $branch, $linkedStudentIds): Reciter {
            $currentUser = $currentLoginCode === ''
                ? null
                : User::query()->where('login_code', $currentLoginCode)->where('role', 'reciter')->first();

            $targetUser = User::query()->where('login_code', $loginCode)->where('role', 'reciter')->first();

            if ($targetUser && (! $currentUser || $targetUser->id !== $currentUser->id)) {
                throw ValidationException::withMessages(['login_code' => 'رقم دخول المقرئ مستخدم مسبقًا.']);
            }

            $user = $currentUser ?? $targetUser;

            if ($user) {
                $user->update([
                    'full_name' => $name,
                    'login_code' => $loginCode,
                    'role' => 'reciter',
                ]);
            } else {
                $user = User::query()->create([
                    'full_name' => $name,
                    'role' => 'reciter',
                    'login_code' => $loginCode,
                    'password' => Hash::make($loginCode),
                ]);
            }

            $reciter = Reciter::query()->firstOrNew(['user_id' => $user->id]);
            $reciter->fill([
                'full_name' => $name,
                'user_id' => $user->id,
                'branch_id' => $branch->id,
            ]);
            $reciter->save();

            $validatedStudentIds = Student::query()
                ->whereIn('id', $linkedStudentIds)
                ->pluck('id')
                ->all();

            $reciter->students()->sync($validatedStudentIds);

            return $reciter->fresh(['user', 'branch', 'students.branch', 'students.parts']);
        });
    }

    public function deleteReciterByLoginCode(string $loginCode): ?string
    {
        $loginCode = trim($loginCode);

        if ($loginCode === '') {
            return null;
        }

        return DB::transaction(function () use ($loginCode): ?string {
            $user = User::query()->where('login_code', $loginCode)->where('role', 'reciter')->first();

            if (! $user) {
                return null;
            }

            $reciter = Reciter::query()->where('user_id', $user->id)->first();
            $reciterId = $reciter?->id;

            if ($reciter) {
                $reciter->students()->detach();
                $reciter->delete();
            }

            $user->delete();

            return $reciterId;
        });
    }

    public function getReciterAccountByLoginCode(string $loginCode): ?array
    {
        $loginCode = trim($loginCode);

        if ($loginCode === '') {
            return null;
        }

        $user = User::query()->where('login_code', $loginCode)->where('role', 'reciter')->first();

        if (! $user) {
            return null;
        }

        $reciter = Reciter::query()
            ->with(['students.branch', 'students.parts'])
            ->where('user_id', $user->id)
            ->first();

        if (! $reciter) {
            return [
                'id' => $user->id,
                'name' => $user->full_name,
                'loginCode' => $user->login_code,
                'students' => [],
            ];
        }

        return [
            'id' => $reciter->id,
            'name' => $reciter->full_name ?: $user->full_name,
            'loginCode' => $user->login_code,
            'branchId' => $reciter->branch?->code,
            'students' => $reciter->students
                ->map(fn (Student $student) => [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'loginId' => $student->login_code,
                    'branchId' => $student->branch?->code ?? 'male',
                    'note' => $student->note,
                    'completedParts' => $student->parts->pluck('part_number')->sort()->values()->all(),
                ])
                ->sortBy([
                    fn (array $student) => -count($student['completedParts']),
                    fn (array $student) => $student['name'],
                ], options: SORT_REGULAR)
                ->values()
                ->all(),
        ];
    }

    public function getStudentAssignedReciterByLoginCode(string $loginCode): ?array
    {
        $loginCode = trim($loginCode);

        if ($loginCode === '') {
            return null;
        }

        $student = Student::query()
            ->with(['reciters.user'])
            ->where('login_code', $loginCode)
            ->first();

        if (! $student) {
            return null;
        }

        $reciter = $student->reciters->first();

        if (! $reciter) {
            return null;
        }

        return [
            'id' => $reciter->id,
            'name' => $reciter->full_name ?: ($reciter->user?->full_name ?? ''),
            'loginCode' => $reciter->user?->login_code ?? '',
        ];
    }

    public function transferStudentToReciter(string $studentId, string $targetReciterId): void
    {
        $studentId = trim($studentId);
        $targetReciterId = trim($targetReciterId);

        if ($studentId === '' || $targetReciterId === '') {
            throw ValidationException::withMessages(['studentId' => 'بيانات النقل غير مكتملة.']);
        }

        $student = Student::query()->find($studentId);
        $reciter = Reciter::query()->find($targetReciterId);

        if (! $student || ! $reciter) {
            throw ValidationException::withMessages(['studentId' => 'تعذر العثور على المعلم/ة أو المقرئ المحدد.']);
        }

        DB::transaction(function () use ($student, $reciter): void {
            DB::table('reciter_students')->where('student_id', $student->id)->delete();

            DB::table('reciter_students')->insert([
                'reciter_id' => $reciter->id,
                'student_id' => $student->id,
                'created_at' => now(),
            ]);
        });
    }

    public function toggleStudentPart(string $studentId, ?string $reciterId, int $partNumber, bool $shouldMarkComplete): void
    {
        $student = Student::query()->with('branch')->find($studentId);
        $reciter = $reciterId ? Reciter::query()->find($reciterId) : null;

        if (! $student) {
            throw ValidationException::withMessages(['studentId' => 'تعذر العثور على المعلم/ة المحدد.']);
        }

        $partsLimit = $student->branch?->code === 'female' ? 10 : 30;

        if ($partNumber < 1 || $partNumber > $partsLimit) {
            throw ValidationException::withMessages(['partNumber' => 'رقم الجزء غير صالح لهذا الفرع.']);
        }

        if ($reciterId !== null && ! $reciter) {
            throw ValidationException::withMessages(['reciterId' => 'تعذر العثور على المقرئ المحدد.']);
        }

        if ($shouldMarkComplete) {
            DB::table('student_parts')->updateOrInsert(
                ['student_id' => $student->id, 'part_number' => $partNumber],
                ['marked_by_reciter_id' => $reciter?->id, 'marked_at' => now()],
            );

            return;
        }

        DB::table('student_parts')
            ->where('student_id', $student->id)
            ->where('part_number', $partNumber)
            ->delete();
    }

    public function loadActivityLogs(): array
    {
        if (! Schema::hasTable('activity_log')) {
            return [];
        }

        return $this->rememberCache(self::ACTIVITY_LOGS_CACHE_KEY, now()->addMinutes(10), fn (): array => Activity::query()
            ->where('log_name', 'dashboard')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Activity $item) => [
                'id' => $item->id,
                'action' => $item->description,
                'target' => (string) ($item->properties['target'] ?? ''),
                'status' => (string) ($item->event ?? ''),
                'details' => (string) ($item->properties['details'] ?? ''),
                'actorName' => (string) ($item->properties['actorName'] ?? ''),
                'actorRole' => (string) ($item->properties['actorRole'] ?? ''),
                'createdAt' => optional($item->created_at)->toISOString() ?? now()->toISOString(),
            ])
            ->all());
    }

    public function setManualAttendance(string $courseId, array $presentStudents): void
    {
        if (! DB::table('courses')->where('id', $courseId)->exists()) {
            throw ValidationException::withMessages(['courseId' => 'الدورة المحددة غير موجودة.']);
        }

        DB::transaction(function () use ($courseId, $presentStudents) {
            DB::table('course_attendance')
                ->where('course_id', $courseId)
                ->delete();

            if ($presentStudents === []) {
                return;
            }

            $rows = collect($presentStudents)
                ->map(fn (array $student) => [
                    'id' => (string) str()->uuid(),
                    'course_id' => $courseId,
                    'student_id' => $student['studentId'] ?: null,
                    'student_name' => $student['studentName'],
                    'login_code' => $student['loginId'],
                    'source' => 'manual',
                    'created_at' => now(),
                ])
                ->all();

            DB::table('course_attendance')->insert($rows);
        });
    }

    public function submitAssessment(string $courseId, string $assessmentType, array $submission): array
    {
        if (! in_array($assessmentType, ['pre', 'post', 'tasks'], true)) {
            throw ValidationException::withMessages(['assessmentType' => 'نوع التقييم غير صالح.']);
        }

        $course = DB::table('courses')->where('id', $courseId)->first();

        if (! $course) {
            throw ValidationException::withMessages(['courseId' => 'الدورة المحددة غير موجودة.']);
        }

        $loginId = trim($submission['loginId']);
        $studentName = trim($submission['studentName']);

        if ($loginId === '' || $studentName === '') {
            throw ValidationException::withMessages(['loginId' => 'بيانات المعلم/ة غير مكتملة.']);
        }

        $student = Student::query()->with('branch')->where('login_code', $loginId)->first();

        $this->assertAssessmentSubmissionIsOpen($course, $assessmentType, $student);

        $alreadySubmitted = DB::table('course_submissions')
            ->where('course_id', $courseId)
            ->where('assessment_type', $assessmentType)
            ->where('login_code', $loginId)
            ->exists();

        if ($alreadySubmitted) {
            throw ValidationException::withMessages(['loginId' => 'تم إرسال هذا الاختبار مسبقًا، ولا يمكن إعادة الاختبار مرة أخرى.']);
        }

        $studentId = Student::query()
            ->where('login_code', $loginId)
            ->value('id');

        $submissionId = (string) str()->uuid();
        $submittedAt = now();

        $defaultManualScore = null;

        DB::transaction(function () use ($submissionId, $submittedAt, $courseId, $assessmentType, $studentId, $studentName, $loginId, $submission, $defaultManualScore) {
            $alreadySubmitted = DB::table('course_submissions')
                ->where('course_id', $courseId)
                ->where('assessment_type', $assessmentType)
                ->where('login_code', $loginId)
                ->lockForUpdate()
                ->exists();

            if ($alreadySubmitted) {
                throw ValidationException::withMessages(['loginId' => 'تم إرسال هذا الاختبار مسبقًا، ولا يمكن إعادة الاختبار مرة أخرى.']);
            }

            DB::table('course_submissions')->insert([
                'id' => $submissionId,
                'course_id' => $courseId,
                'assessment_type' => $assessmentType,
                'student_id' => $studentId,
                'student_name' => $studentName,
                'login_code' => $loginId,
                'manual_score' => $defaultManualScore,
                'submitted_at' => $submittedAt,
            ]);

            $answers = collect($submission['answers'] ?? [])
                ->filter(fn (array $answer) => ($answer['questionId'] ?? '') !== '__score_override__')
                ->map(fn (array $answer) => [
                    'id' => (string) str()->uuid(),
                    'submission_id' => $submissionId,
                    'question_id' => $answer['questionId'],
                    'answer_text' => $answer['value'] ?? null,
                    'file_name' => $answer['fileName'] ?? null,
                    'file_type' => $answer['fileType'] ?? null,
                    'file_data_url' => $answer['fileDataUrl'] ?? null,
                    'created_at' => now(),
                ])
                ->all();

            if ($answers !== []) {
                DB::table('course_submission_answers')->insert($answers);
            }
        });

        if ($assessmentType === 'tasks') {
            $this->addNotification([
                'title' => 'مهمة تحتاج مراجعة',
                'message' => sprintf('%s انتهى من مهمة %s وتحتاج مراجعة للإتمام.', $studentName, $course->title ?? 'المهمة'),
                'targetBranchId' => $student?->branch?->code,
                'targetLoginIds' => [],
                'createdByName' => 'النظام',
                'createdByRole' => 'system',
            ]);
        }

        return [
            'id' => $submissionId,
            'submittedAt' => $submittedAt->toISOString(),
        ];
    }

    public function addActivityLog(array $input): array
    {
        $activity = activity('dashboard')
            ->withProperties([
                'target' => $input['target'],
                'details' => $input['details'] ?? '',
                'actorName' => $input['actorName'] ?? '',
                'actorRole' => $input['actorRole'] ?? '',
            ])
            ->event($input['status'])
            ->log($input['action']);

        $this->flushDashboardCaches();

        $payload = [
            'id' => $activity->id,
            'action' => $activity->description,
            'target' => (string) ($activity->properties['target'] ?? ''),
            'status' => (string) ($activity->event ?? ''),
            'details' => (string) ($activity->properties['details'] ?? ''),
            'actorName' => (string) ($activity->properties['actorName'] ?? ''),
            'actorRole' => (string) ($activity->properties['actorRole'] ?? ''),
            'createdAt' => optional($activity->created_at)->toISOString() ?? now()->toISOString(),
        ];

        event(new DashboardActivityLogged($payload));

        return $payload;
    }

    public function loadNotifications(): array
    {
        return $this->rememberCache(self::NOTIFICATIONS_CACHE_KEY, now()->addMinutes(10), fn (): array => DB::table('notifications')
            ->whereNull('archive_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'message' => $item->message,
                'targetBranchId' => $item->target_branch_code,
                'targetLoginIds' => $this->decodeJsonArray($item->target_login_ids),
                'createdAt' => (string) $item->created_at,
                'createdByRole' => $item->created_by_role,
                'createdByName' => $item->created_by_name,
            ])
            ->all());
    }

    public function addNotification(array $input): array
    {
        $id = (string) str()->uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'title' => $input['title'],
            'message' => $input['message'],
            'target_branch_code' => $input['targetBranchId'] ?? null,
            'target_login_ids' => json_encode($input['targetLoginIds'] ?? [], JSON_UNESCAPED_UNICODE),
            'created_by_name' => $input['createdByName'] ?? null,
            'created_by_role' => $input['createdByRole'] ?? null,
            'created_at' => now(),
        ]);

        $this->flushDashboardCaches();

        $payload = [
            'id' => $id,
            'title' => $input['title'],
            'message' => $input['message'],
            'targetBranchId' => $input['targetBranchId'] ?? null,
            'targetLoginIds' => $input['targetLoginIds'] ?? [],
            'createdByName' => $input['createdByName'] ?? null,
            'createdByRole' => $input['createdByRole'] ?? null,
            'createdAt' => now()->toISOString(),
        ];

        event(new DashboardNotificationCreated($payload));

        return $payload;
    }

    public function deleteNotification(string $notificationId): void
    {
        DB::table('notifications')->where('id', $notificationId)->delete();

        $this->flushDashboardCaches();

        event(new DashboardNotificationDeleted($notificationId));
    }

    private function purgeActiveDashboardData(): void
    {
        $activeStudentIds = DB::table('students')
            ->when(Schema::hasColumn('students', 'archive_id'), fn ($query) => $query->whereNull('archive_id'))
            ->pluck('id')
            ->all();
        $activeStudentLoginCodes = DB::table('students')
            ->when(Schema::hasColumn('students', 'archive_id'), fn ($query) => $query->whereNull('archive_id'))
            ->pluck('login_code')
            ->all();
        $activeReciterUserIds = DB::table('reciters')
            ->when(Schema::hasColumn('reciters', 'archive_id'), fn ($query) => $query->whereNull('archive_id'))
            ->pluck('user_id')
            ->filter()
            ->all();

        foreach (TrainingMaterial::query()->with('media')->get() as $material) {
            $material->clearMediaCollection('attachments');
            $material->delete();
        }

        DB::table('reciter_students')->whereIn('student_id', $activeStudentIds)->delete();
        DB::table('student_parts')->whereIn('student_id', $activeStudentIds)->delete();

        foreach ([
            'course_submission_answers',
            'course_attendance',
            'course_submissions',
            'satisfaction_responses',
            'final_exam_submission_answers',
            'final_exam_submissions',
            'course_questions',
            'satisfaction_questions',
            'final_exam_questions',
            'courses',
            'reciters',
            'students',
            'notifications',
            'task_templates',
            'role_permissions',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);

            if (Schema::hasColumn($table, 'archive_id')) {
                $query->whereNull('archive_id');
            }

            $query->delete();
        }

        User::query()
            ->where(function ($query) use ($activeStudentLoginCodes, $activeReciterUserIds): void {
                $query->where(function ($studentQuery) use ($activeStudentLoginCodes): void {
                    $studentQuery->whereIn('role', ['student', 'trainee'])
                        ->whereIn('login_code', $activeStudentLoginCodes);
                })->orWhere(function ($reciterQuery) use ($activeReciterUserIds): void {
                    $reciterQuery->where('role', 'reciter')
                        ->whereIn('id', $activeReciterUserIds);
                });
            })
            ->delete();
    }

    private function restoreBranchesFromSnapshot(array $branches): void
    {
        $branches = $branches !== [] ? $branches : [
            ['id' => 'male', 'label' => 'معلمين'],
            ['id' => 'female', 'label' => 'معلمات'],
        ];

        foreach ($branches as $branch) {
            $code = trim((string) ($branch['id'] ?? $branch['code'] ?? ''));
            $label = trim((string) ($branch['label'] ?? $branch['name'] ?? $code));

            if ($code === '') {
                continue;
            }

            DB::table('branches')->updateOrInsert(
                ['code' => $code],
                [
                    'id' => DB::table('branches')->where('code', $code)->value('id') ?: (string) Str::uuid(),
                    'name' => $label !== '' ? $label : $code,
                    'created_at' => DB::table('branches')->where('code', $code)->value('created_at') ?: now(),
                ],
            );
        }
    }

    private function restoreStudentsFromSnapshot(array $students): array
    {
        $idMap = [];

        foreach ($students as $student) {
            $id = trim((string) ($student['id'] ?? '')) ?: (string) Str::uuid();
            $loginCode = trim((string) ($student['loginId'] ?? $student['loginCode'] ?? ''));
            $name = trim((string) ($student['name'] ?? ''));

            if ($loginCode === '' || $name === '') {
                continue;
            }

            $branch = $this->resolveBranchByCode((string) ($student['branchId'] ?? 'male'));
            $createdAt = $this->restoreTimestamp($student['createdAt'] ?? null);

            $user = User::query()->create([
                'full_name' => $name,
                'role' => 'student',
                'login_code' => $loginCode,
                'password' => Hash::make($loginCode),
            ]);

            DB::table('students')->insert([
                'id' => $id,
                'full_name' => $name,
                'login_code' => $loginCode,
                'branch_id' => $branch->id,
                'note' => (string) ($student['note'] ?? ''),
                'is_certified' => (bool) ($student['isCertified'] ?? false),
                'created_by' => $user->id,
                'created_at' => $createdAt,
            ]);

            foreach (collect($student['completedParts'] ?? [])->map(fn ($part) => (int) $part)->unique()->sort()->values() as $partNumber) {
                if ($partNumber < 1 || $partNumber > ($branch->code === 'female' ? 10 : 30)) {
                    continue;
                }

                DB::table('student_parts')->insert([
                    'student_id' => $id,
                    'part_number' => $partNumber,
                    'marked_by_reciter_id' => null,
                    'marked_at' => $createdAt,
                ]);
            }

            $idMap[$student['id'] ?? $id] = $id;
        }

        return $idMap;
    }

    private function restoreRecitersFromSnapshot(array $reciters, array $studentIdMap): array
    {
        $idMap = [];

        foreach ($reciters as $reciter) {
            $id = trim((string) ($reciter['id'] ?? '')) ?: (string) Str::uuid();
            $loginCode = trim((string) ($reciter['loginCode'] ?? ''));
            $name = trim((string) ($reciter['name'] ?? ''));

            if ($loginCode === '' || $name === '') {
                continue;
            }

            $branch = $this->resolveBranchByCode((string) ($reciter['branchId'] ?? 'male'));
            $user = User::query()->create([
                'full_name' => $name,
                'role' => 'reciter',
                'login_code' => $loginCode,
                'password' => Hash::make($loginCode),
            ]);

            DB::table('reciters')->insert([
                'id' => $id,
                'full_name' => $name,
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'created_at' => now(),
            ]);

            foreach ($reciter['studentIds'] ?? [] as $studentId) {
                $mappedStudentId = $studentIdMap[$studentId] ?? $studentId;

                if (! DB::table('students')->where('id', $mappedStudentId)->exists()) {
                    continue;
                }

                DB::table('reciter_students')->updateOrInsert([
                    'reciter_id' => $id,
                    'student_id' => $mappedStudentId,
                ], [
                    'created_at' => now(),
                ]);
            }

            $idMap[$reciter['id'] ?? $id] = $id;
        }

        return $idMap;
    }

    private function restoreCoursesFromSnapshot(array $courses): array
    {
        $questionIds = [];

        foreach ($courses as $course) {
            $courseId = trim((string) ($course['id'] ?? '')) ?: (string) Str::uuid();
            $createdAt = $this->restoreTimestamp($course['createdAt'] ?? null);

            DB::table('courses')->insert([
                'id' => $courseId,
                'title' => trim((string) ($course['title'] ?? 'بدون عنوان')),
                'entity_type' => ($course['entityType'] ?? 'course') === 'task' ? 'task' : 'course',
                'task_mode' => $course['taskMode'] ?? null,
                'task_template_id' => ($course['taskTemplateId'] ?? '') ?: null,
                'task_template_name' => (string) ($course['taskTemplateName'] ?? ''),
                'task_template_content' => (string) ($course['taskTemplateContent'] ?? ''),
                'youtube_url' => (string) ($course['youtubeUrl'] ?? ''),
                'task_description' => (string) ($course['taskDescription'] ?? ''),
                'is_active' => (bool) ($course['isActive'] ?? false),
                'is_pre_enabled' => (bool) ($course['isPreEnabled'] ?? true),
                'is_post_enabled' => (bool) ($course['isPostEnabled'] ?? true),
                'is_tasks_enabled' => (bool) ($course['isTasksEnabled'] ?? true),
                'male_pre_enabled' => (bool) ($course['branchAvailability']['male']['pre'] ?? true),
                'female_pre_enabled' => (bool) ($course['branchAvailability']['female']['pre'] ?? true),
                'male_post_enabled' => (bool) ($course['branchAvailability']['male']['post'] ?? true),
                'female_post_enabled' => (bool) ($course['branchAvailability']['female']['post'] ?? true),
                'male_tasks_enabled' => (bool) ($course['branchAvailability']['male']['tasks'] ?? true),
                'female_tasks_enabled' => (bool) ($course['branchAvailability']['female']['tasks'] ?? true),
                'assessment_windows' => json_encode($course['assessmentWindows'] ?? ['global' => [], 'male' => [], 'female' => []], JSON_UNESCAPED_UNICODE),
                'assessment_notification_templates' => json_encode($course['assessmentNotificationTemplates'] ?? ['pre' => '', 'post' => '', 'tasks' => ''], JSON_UNESCAPED_UNICODE),
                'sort_order' => (int) ($course['sortOrder'] ?? 0),
                'created_by' => auth()->id(),
                'created_at' => $createdAt,
            ]);

            foreach (['pre' => 'preQuestions', 'post' => 'postQuestions', 'tasks' => 'taskQuestions'] as $assessmentType => $key) {
                foreach (($course[$key] ?? []) as $index => $question) {
                    $questionId = $this->restoreCourseQuestion($courseId, $assessmentType, $question, $index);
                    $questionIds[$question['id'] ?? $questionId] = $questionId;
                }
            }
        }

        return $questionIds;
    }

    private function restoreCourseQuestion(string $courseId, string $assessmentType, array $question, int $index): string
    {
        $questionId = trim((string) ($question['id'] ?? '')) ?: (string) Str::uuid();
        $type = ($question['type'] ?? 'multiple') === 'text' ? 'text' : 'multiple';
        $options = ($question['type'] ?? 'multiple') === 'truefalse' ? ['صح', 'خطأ'] : ($question['options'] ?? []);

        DB::table('course_questions')->insert([
            'id' => $questionId,
            'course_id' => $courseId,
            'assessment_type' => $assessmentType,
            'question_type' => $type,
            'prompt' => trim((string) ($question['prompt'] ?? '')),
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'allow_file' => (bool) ($question['allowFile'] ?? false),
            'points' => (int) ($question['points'] ?? 1),
            'correct_answer' => (string) ($question['correctAnswer'] ?? ''),
            'attachment_name' => (string) ($question['attachmentName'] ?? ''),
            'attachment_type' => (string) ($question['attachmentType'] ?? ''),
            'attachment_data_url' => (string) ($question['attachmentDataUrl'] ?? ''),
            'sort_order' => (int) ($question['sortOrder'] ?? $index),
            'created_at' => $this->restoreTimestamp($question['createdAt'] ?? null),
        ]);

        return $questionId;
    }

    private function restoreTaskTemplatesFromSnapshot(array $templates): void
    {
        foreach ($templates as $template) {
            DB::table('task_templates')->insert([
                'id' => trim((string) ($template['id'] ?? '')) ?: (string) Str::uuid(),
                'name' => trim((string) ($template['name'] ?? 'قالب مهمة')),
                'content' => (string) ($template['content'] ?? ''),
                'created_at' => $this->restoreTimestamp($template['createdAt'] ?? null),
            ]);
        }
    }

    private function restoreCourseSubmissionsFromSnapshot(array $submissions, array $questionIds): void
    {
        foreach ($submissions as $submission) {
            $courseId = (string) ($submission['courseId'] ?? '');

            if (! DB::table('courses')->where('id', $courseId)->exists()) {
                continue;
            }

            $submissionId = trim((string) ($submission['id'] ?? '')) ?: (string) Str::uuid();
            $loginCode = trim((string) ($submission['loginId'] ?? $submission['loginCode'] ?? ''));
            $studentId = DB::table('students')->where('login_code', $loginCode)->value('id');

            DB::table('course_submissions')->insert([
                'id' => $submissionId,
                'course_id' => $courseId,
                'assessment_type' => (string) ($submission['assessmentType'] ?? 'pre'),
                'student_id' => $studentId,
                'student_name' => (string) ($submission['studentName'] ?? ''),
                'login_code' => $loginCode,
                'manual_score' => $submission['manualScore'] ?? null,
                'submitted_at' => $this->restoreTimestamp($submission['submittedAt'] ?? null),
            ]);

            foreach ($submission['answers'] ?? [] as $answer) {
                $questionId = $questionIds[$answer['questionId'] ?? ''] ?? ($answer['questionId'] ?? '');

                if (! DB::table('course_questions')->where('id', $questionId)->exists()) {
                    continue;
                }

                DB::table('course_submission_answers')->insert([
                    'id' => (string) Str::uuid(),
                    'submission_id' => $submissionId,
                    'question_id' => $questionId,
                    'answer_text' => (string) ($answer['value'] ?? ''),
                    'file_name' => $answer['fileName'] ?? null,
                    'file_type' => $answer['fileType'] ?? null,
                    'file_data_url' => $answer['fileDataUrl'] ?? null,
                    'created_at' => $this->restoreTimestamp($submission['submittedAt'] ?? null),
                ]);
            }
        }
    }

    private function restoreAttendanceFromSnapshot(array $attendanceRows): void
    {
        foreach ($attendanceRows as $row) {
            $courseId = (string) ($row['courseId'] ?? '');

            if (! DB::table('courses')->where('id', $courseId)->exists()) {
                continue;
            }

            $loginCode = trim((string) ($row['loginId'] ?? $row['loginCode'] ?? ''));

            DB::table('course_attendance')->updateOrInsert([
                'course_id' => $courseId,
                'login_code' => $loginCode,
                'source' => (string) ($row['source'] ?? 'post-test'),
            ], [
                'id' => trim((string) ($row['id'] ?? '')) ?: (string) Str::uuid(),
                'student_id' => DB::table('students')->where('login_code', $loginCode)->value('id'),
                'student_name' => (string) ($row['studentName'] ?? ''),
                'created_at' => $this->restoreTimestamp($row['createdAt'] ?? null),
            ]);
        }
    }

    private function restoreNotificationsFromSnapshot(array $notifications): void
    {
        foreach ($notifications as $notification) {
            DB::table('notifications')->insert([
                'id' => trim((string) ($notification['id'] ?? '')) ?: (string) Str::uuid(),
                'title' => trim((string) ($notification['title'] ?? 'إشعار')),
                'message' => (string) ($notification['message'] ?? ''),
                'target_branch_code' => $notification['targetBranchId'] ?? null,
                'target_login_ids' => json_encode($notification['targetLoginIds'] ?? [], JSON_UNESCAPED_UNICODE),
                'created_by_name' => $notification['createdByName'] ?? null,
                'created_by_role' => $notification['createdByRole'] ?? null,
                'created_at' => $this->restoreTimestamp($notification['createdAt'] ?? null),
            ]);
        }
    }

    private function restoreSatisfactionFromSnapshot(array $questions, array $responses): void
    {
        foreach ($questions as $index => $question) {
            $courseId = $question['courseId'] ?? null;

            DB::table('satisfaction_questions')->insert([
                'id' => trim((string) ($question['id'] ?? '')) ?: (string) Str::uuid(),
                'course_id' => $courseId !== '' ? $courseId : null,
                'prompt' => trim((string) ($question['prompt'] ?? '')),
                'type' => ($question['type'] ?? 'rating') === 'text' ? 'text' : 'rating',
                'is_required' => (bool) ($question['isRequired'] ?? true),
                'sort_order' => (int) ($question['sortOrder'] ?? $index),
                'created_at' => $this->restoreTimestamp($question['createdAt'] ?? null),
            ]);
        }

        foreach ($responses as $response) {
            $courseId = (string) ($response['courseId'] ?? '');
            $questionId = (string) ($response['questionId'] ?? '');

            if (! DB::table('courses')->where('id', $courseId)->exists() || ! DB::table('satisfaction_questions')->where('id', $questionId)->exists()) {
                continue;
            }

            DB::table('satisfaction_responses')->updateOrInsert([
                'course_id' => $courseId,
                'question_id' => $questionId,
                'login_code' => (string) ($response['loginCode'] ?? ''),
            ], [
                'id' => trim((string) ($response['id'] ?? '')) ?: (string) Str::uuid(),
                'student_name' => (string) ($response['studentName'] ?? ''),
                'rating_value' => $response['ratingValue'] ?? null,
                'text_value' => (string) ($response['textValue'] ?? ''),
                'submitted_at' => $this->restoreTimestamp($response['submittedAt'] ?? null),
            ]);
        }
    }

    private function restoreFinalExamFromSnapshot(array $questions, array $submissions, array $settings): void
    {
        foreach (['male', 'female'] as $branchCode) {
            $setting = $settings[$branchCode] ?? [];

            DB::table('final_exam_settings')->updateOrInsert(
                ['branch_code' => $branchCode],
                [
                    'is_enabled' => (bool) ($setting['isEnabled'] ?? false),
                    'closes_at' => $setting['closesAt'] ?? null,
                    'notification_template' => (string) ($setting['notificationTemplate'] ?? ''),
                ],
            );
        }

        foreach ($questions as $index => $question) {
            $type = ($question['type'] ?? 'multiple') === 'text' ? 'text' : 'multiple';
            $options = ($question['type'] ?? 'multiple') === 'truefalse' ? ['صح', 'خطأ'] : ($question['options'] ?? []);

            DB::table('final_exam_questions')->insert([
                'id' => trim((string) ($question['id'] ?? '')) ?: (string) Str::uuid(),
                'branch_code' => $this->normalizeBranchCode((string) ($question['branchCode'] ?? 'male')),
                'question_type' => $type,
                'prompt' => trim((string) ($question['prompt'] ?? '')),
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                'allow_file' => (bool) ($question['allowFile'] ?? false),
                'points' => (int) ($question['points'] ?? 1),
                'correct_answer' => (string) ($question['correctAnswer'] ?? ''),
                'attachment_name' => (string) ($question['attachmentName'] ?? ''),
                'attachment_type' => (string) ($question['attachmentType'] ?? ''),
                'attachment_data_url' => (string) ($question['attachmentDataUrl'] ?? ''),
                'sort_order' => (int) ($question['sortOrder'] ?? $index),
                'created_at' => $this->restoreTimestamp($question['createdAt'] ?? null),
            ]);
        }

        foreach ($submissions as $submission) {
            $submissionId = trim((string) ($submission['id'] ?? '')) ?: (string) Str::uuid();

            DB::table('final_exam_submissions')->insert([
                'id' => $submissionId,
                'branch_code' => $this->normalizeBranchCode((string) ($submission['branchCode'] ?? 'male')),
                'student_name' => (string) ($submission['studentName'] ?? ''),
                'login_code' => (string) ($submission['loginCode'] ?? ''),
                'manual_score' => $submission['manualScore'] ?? null,
                'submitted_at' => $this->restoreTimestamp($submission['submittedAt'] ?? null),
            ]);

            foreach ($submission['answers'] ?? [] as $answer) {
                $questionId = (string) ($answer['questionId'] ?? '');

                if (! DB::table('final_exam_questions')->where('id', $questionId)->exists()) {
                    continue;
                }

                DB::table('final_exam_submission_answers')->insert([
                    'id' => (string) Str::uuid(),
                    'submission_id' => $submissionId,
                    'question_id' => $questionId,
                    'answer_text' => (string) ($answer['value'] ?? ''),
                    'file_name' => $answer['fileName'] ?? null,
                    'file_type' => $answer['fileType'] ?? null,
                    'file_data_url' => $answer['fileDataUrl'] ?? null,
                ]);
            }
        }
    }

    private function restoreTrainingMaterialsFromSnapshot(array $materials, ?string $mediaRoot = null): void
    {
        foreach ($materials as $material) {
            $externalAttachments = collect($material['attachments'] ?? [])
                ->filter(fn (array $attachment) => ($attachment['type'] ?? '') === 'youtube' && trim((string) ($attachment['url'] ?? '')) !== '')
                ->map(fn (array $attachment) => [
                    'id' => (string) ($attachment['id'] ?? Str::uuid()),
                    'label' => (string) ($attachment['displayName'] ?? $attachment['name'] ?? 'مقطع يوتيوب'),
                    'url' => (string) ($attachment['url'] ?? ''),
                ])
                ->values()
                ->all();

            $materialModel = TrainingMaterial::query()->create([
                'title' => trim((string) ($material['title'] ?? 'مادة تدريبية')),
                'description' => (string) ($material['description'] ?? ''),
                'target_branch_code' => $material['targetBranchId'] ?? null,
                'external_attachments' => $externalAttachments,
                'created_by' => auth()->id(),
            ]);

            $materialModel->forceFill([
                'created_at' => now(),
                'updated_at' => now(),
            ])->save();

            if ($mediaRoot === null) {
                continue;
            }

            foreach ($material['attachments'] ?? [] as $attachment) {
                if (($attachment['type'] ?? '') !== 'file') {
                    continue;
                }

                $backupPath = trim((string) ($attachment['backupPath'] ?? ''));

                if ($backupPath === '' || str_contains($backupPath, '..')) {
                    continue;
                }

                $sourcePath = $mediaRoot.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $backupPath);

                if (! is_file($sourcePath)) {
                    continue;
                }

                $displayName = (string) ($attachment['displayName'] ?? $attachment['name'] ?? basename($sourcePath));
                $originalName = (string) ($attachment['originalName'] ?? basename($sourcePath));

                $materialModel
                    ->addMedia($sourcePath)
                    ->preservingOriginal()
                    ->usingName($displayName)
                    ->usingFileName((string) ($attachment['name'] ?? basename($sourcePath)))
                    ->withCustomProperties([
                        'display_name' => $displayName,
                        'original_client_name' => $originalName,
                    ])
                    ->toMediaCollection('attachments', 'public');
            }
        }
    }

    private function restoreRolePermissionsFromSnapshot(array $rolePermissions): void
    {
        foreach ($rolePermissions as $role => $permissions) {
            if (! in_array($role, ['male_manager', 'female_manager'], true) || ! is_array($permissions)) {
                continue;
            }

            foreach ($permissions as $permissionKey => $enabled) {
                DB::table('role_permissions')->insert([
                    'role' => $role,
                    'permission_key' => (string) $permissionKey,
                    'is_enabled' => (bool) $enabled,
                ]);
            }
        }
    }

    private function attachTrainingMaterialMediaBackupPaths(array &$snapshot): array
    {
        $mediaIndex = [];
        $materials = TrainingMaterial::query()->with('media')->get()->keyBy(fn (TrainingMaterial $material) => (string) $material->id);

        foreach ($snapshot['trainingMaterials'] ?? [] as $materialIndex => $material) {
            $materialModel = $materials->get((string) ($material['id'] ?? ''));

            if (! $materialModel) {
                continue;
            }

            $materialMedia = $materialModel->getMedia('attachments')
                ->keyBy(fn ($media) => (string) ($media->uuid ?? $media->id));

            foreach ($material['attachments'] ?? [] as $attachmentIndex => $attachment) {
                if (($attachment['type'] ?? '') !== 'file') {
                    continue;
                }

                $media = $materialMedia->get((string) ($attachment['id'] ?? ''));

                if (! $media || ! is_file($media->getPath())) {
                    continue;
                }

                $backupPath = 'media/training-materials/'
                    .Str::slug((string) ($material['id'] ?? $materialIndex), '-')
                    .'/'.Str::slug((string) ($attachment['id'] ?? $attachmentIndex), '-')
                    .'/'.$media->file_name;

                $snapshot['trainingMaterials'][$materialIndex]['attachments'][$attachmentIndex]['backupPath'] = $backupPath;
                $mediaIndex[] = [
                    'sourcePath' => $media->getPath(),
                    'backupPath' => $backupPath,
                ];
            }
        }

        return $mediaIndex;
    }

    private function restoreTimestamp(mixed $value): Carbon
    {
        try {
            return $value ? Carbon::parse((string) $value) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    private function serializeTrainingMaterial(TrainingMaterial $material): array
    {
        $basePath = trim((string) config('app.public_base_path', ''), '/');
        $pathPrefix = $basePath === '' ? '' : '/'.$basePath;
        $fileAttachments = $material->getMedia('attachments')
            ->map(fn ($media) => [
                'id' => $media->uuid ?? (string) $media->id,
                'name' => $media->file_name,
                'displayName' => $media->getCustomProperty('display_name') ?: $media->name,
                'originalName' => $media->getCustomProperty('original_client_name') ?: $media->file_name,
                'mimeType' => $media->mime_type,
                'size' => (int) $media->size,
                'type' => 'file',
                'url' => request()->getSchemeAndHttpHost().$pathPrefix.'/api/public/training-material-attachments/'.($media->uuid ?? $media->id),
            ]);
        $externalAttachments = collect($material->external_attachments ?? [])
            ->map(fn (array $attachment) => [
                'id' => (string) ($attachment['id'] ?? Str::uuid()),
                'name' => (string) ($attachment['label'] ?? 'مقطع يوتيوب'),
                'displayName' => (string) ($attachment['label'] ?? 'مقطع يوتيوب'),
                'originalName' => (string) ($attachment['url'] ?? ''),
                'mimeType' => 'text/html',
                'size' => 0,
                'type' => 'youtube',
                'url' => (string) ($attachment['url'] ?? ''),
                'embedUrl' => $this->youtubeEmbedUrl((string) ($attachment['url'] ?? '')),
            ])
            ->filter(fn (array $attachment) => $attachment['embedUrl'] !== '');

        return [
            'id' => $material->id,
            'title' => $material->title,
            'description' => $material->description ?? '',
            'targetBranchId' => $material->target_branch_code ?: null,
            'targetBranchLabel' => $material->target_branch_code === 'supervision'
                ? 'الإشراف'
                : ($material->branch?->name ?? 'كل الفروع'),
            'attachments' => $fileAttachments
                ->concat($externalAttachments)
                ->values()
                ->all(),
            'createdAt' => optional($material->created_at)?->toISOString() ?? now()->toISOString(),
        ];
    }

    private function normalizeExternalTrainingMaterialAttachments(array $attachments): array
    {
        return collect($attachments)
            ->map(function (array $attachment): ?array {
                $url = trim((string) ($attachment['url'] ?? ''));

                if ($url === '' || $this->youtubeEmbedUrl($url) === '') {
                    return null;
                }

                return [
                    'id' => trim((string) ($attachment['id'] ?? '')) ?: (string) Str::uuid(),
                    'label' => trim((string) ($attachment['label'] ?? '')) ?: 'مقطع يوتيوب',
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function youtubeEmbedUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $videoId = '';

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $videoId = trim((string) ($parts['path'] ?? ''), '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $videoId = (string) ($query['v'] ?? '');

            if ($videoId === '' && preg_match('~^/(?:embed|shorts)/([^/?]+)~', (string) ($parts['path'] ?? ''), $matches)) {
                $videoId = $matches[1];
            }
        }

        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $videoId)
            ? 'https://www.youtube.com/embed/'.$videoId
            : '';
    }

    private function dispatchAssessmentOpenNotification(?object $course, string $assessmentType, array $templates, array $windows): void
    {
        if (! $course || ! in_array($assessmentType, ['pre', 'post', 'tasks'], true)) {
            return;
        }

        $template = trim((string) ($templates[$assessmentType] ?? ''));

        if ($template === '') {
            return;
        }

        $targetBranches = $this->resolveAssessmentTargetBranches($course, $assessmentType, $windows);

        foreach ($targetBranches as $branchCode) {
            $message = $this->fillTemplatePlaceholders($template, [
                'courseTitle' => (string) ($course->title ?? ''),
                'assessmentLabel' => $this->assessmentLabel($assessmentType),
                'branchLabel' => $this->branchLabel($branchCode),
                'durationMinutes' => (string) $this->resolveAssessmentDurationMinutes($windows, $assessmentType, $branchCode),
            ]);

            if (trim($message) === '') {
                continue;
            }

            $this->addNotification([
                'title' => sprintf('%s - %s', $this->assessmentLabel($assessmentType), (string) ($course->title ?? '')),
                'message' => $message,
                'targetBranchId' => $branchCode,
                'createdByName' => 'النظام',
                'createdByRole' => 'system',
            ]);
        }
    }

    private function dispatchFinalExamOpenNotification(string $branchCode, ?string $closesAt, string $notificationTemplate): void
    {
        $template = trim($notificationTemplate);

        if ($template === '') {
            return;
        }

        $message = $this->fillTemplatePlaceholders($template, [
            'branchLabel' => $this->branchLabel($branchCode),
            'durationMinutes' => (string) $this->resolveMinutesUntil($closesAt),
        ]);

        if (trim($message) === '') {
            return;
        }

        $this->addNotification([
            'title' => 'الاختبار النهائي',
            'message' => $message,
            'targetBranchId' => $branchCode,
            'createdByName' => 'النظام',
            'createdByRole' => 'system',
        ]);
    }

    private function resolveAssessmentTargetBranches(object $course, string $assessmentType, array $windows): array
    {
        $globalWindow = data_get($windows, "global.$assessmentType");

        if ($this->assessmentWindowHasValue($globalWindow)) {
            return ['male', 'female'];
        }

        $branches = [];

        foreach (['male', 'female'] as $branchCode) {
            $enabledColumn = sprintf('%s_%s_enabled', $branchCode, $assessmentType);
            $branchWindow = data_get($windows, "$branchCode.$assessmentType");

            if ((bool) ($course->{$enabledColumn} ?? false) && $this->assessmentWindowHasValue($branchWindow)) {
                $branches[] = $branchCode;
            }
        }

        return $branches;
    }

    private function assessmentWindowHasValue(mixed $window): bool
    {
        if (is_array($window)) {
            return trim((string) ($window['closesAt'] ?? '')) !== '';
        }

        return trim((string) $window) !== '';
    }

    private function resolveAssessmentDurationMinutes(array $windows, string $assessmentType, string $branchCode): int
    {
        $globalWindow = data_get($windows, "global.$assessmentType");
        $branchWindow = data_get($windows, "$branchCode.$assessmentType");
        $window = $this->assessmentWindowHasValue($globalWindow) ? $globalWindow : $branchWindow;

        if (is_array($window) && isset($window['durationMinutes'])) {
            return max(0, (int) $window['durationMinutes']);
        }

        $closesAt = is_array($window) ? ($window['closesAt'] ?? null) : $window;

        return $this->resolveMinutesUntil(is_string($closesAt) ? $closesAt : null);
    }

    private function resolveMinutesUntil(?string $closesAt): int
    {
        if (! $closesAt) {
            return 0;
        }

        try {
            return max(1, (int) ceil(now()->diffInSeconds(Carbon::parse($closesAt), false) / 60));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function fillTemplatePlaceholders(string $template, array $replacements): string
    {
        $pairs = [];

        foreach ($replacements as $key => $value) {
            $pairs['{'.$key.'}'] = (string) $value;
        }

        return strtr($template, $pairs);
    }

    private function assessmentLabel(string $assessmentType): string
    {
        return [
            'pre' => 'الاختبار القبلي',
            'post' => 'الاختبار البعدي',
            'tasks' => 'المهام الأدائية',
        ][$assessmentType] ?? $assessmentType;
    }

    private function branchLabel(string $branchCode): string
    {
        return [
            'male' => 'معلمين',
            'female' => 'معلمات',
        ][$this->normalizeBranchCode($branchCode)] ?? $branchCode;
    }

    private function hasAssessmentWindowOpened(array $previousWindows, array $nextWindows, string $assessmentType): bool
    {
        foreach (['global', 'male', 'female'] as $scope) {
            $previous = data_get($previousWindows, "$scope.$assessmentType");
            $next = data_get($nextWindows, "$scope.$assessmentType");

            if (! $this->assessmentWindowHasValue($previous) && $this->assessmentWindowHasValue($next)) {
                return true;
            }
        }

        return false;
    }

    private function flushDashboardCaches(): void
    {
        $this->forgetCache(self::ACTIVITY_LOGS_CACHE_KEY);
        $this->forgetCache(self::NOTIFICATIONS_CACHE_KEY);
    }

    private function rememberCache(string $key, \DateTimeInterface|\DateInterval|int $ttl, callable $resolver): mixed
    {
        try {
            return Cache::store('redis')->remember($key, $ttl, $resolver);
        } catch (\Throwable) {
            return Cache::remember($key, $ttl, $resolver);
        }
    }

    private function forgetCache(string $key): void
    {
        try {
            Cache::store('redis')->forget($key);
        } catch (\Throwable) {
            // Fall back to the default store when Redis is unavailable locally.
        }

        Cache::forget($key);
    }

    public function loadRolePermissions(): array
    {
        $result = ['male_manager' => [], 'female_manager' => []];

        foreach (DB::table('role_permissions')->get() as $row) {
            if (! isset($result[$row->role])) {
                $result[$row->role] = [];
            }

            $result[$row->role][$row->permission_key] = (bool) $row->is_enabled;
        }

        return $result;
    }

    public function setRolePermission(string $role, string $key, bool $isEnabled): void
    {
        DB::table('role_permissions')->updateOrInsert(
            ['role' => $role, 'permission_key' => $key],
            ['is_enabled' => $isEnabled],
        );
    }

    private function resolveBranchByCode(string $branchCode): Branch
    {
        $branchCode = trim($branchCode);

        if ($branchCode === '') {
            throw ValidationException::withMessages(['branchId' => 'أدخل رمز الفرع.']);
        }

        $branch = Branch::query()->where('code', $branchCode)->first();

        if (! $branch) {
            throw ValidationException::withMessages(['branchId' => sprintf('الفرع %s غير موجود.', $branchCode)]);
        }

        return $branch;
    }

    private function normalizeBranchCode(string $branchCode): string
    {
        $branchCode = trim($branchCode);

        if (! in_array($branchCode, ['male', 'female'], true)) {
            throw ValidationException::withMessages(['branchCode' => 'رمز الفرع غير صالح.']);
        }

        return $branchCode;
    }

    private function mapCourseRow(object $course, array $courseQuestions): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'entityType' => $course->entity_type === 'task' ? 'task' : 'course',
            'isActive' => (bool) $course->is_active,
            'isPreEnabled' => (bool) $course->is_pre_enabled,
            'isPostEnabled' => (bool) $course->is_post_enabled,
            'isTasksEnabled' => (bool) $course->is_tasks_enabled,
            'branchAvailability' => [
                'male' => [
                    'pre' => (bool) $course->male_pre_enabled,
                    'post' => (bool) $course->male_post_enabled,
                    'tasks' => (bool) $course->male_tasks_enabled,
                ],
                'female' => [
                    'pre' => (bool) $course->female_pre_enabled,
                    'post' => (bool) $course->female_post_enabled,
                    'tasks' => (bool) $course->female_tasks_enabled,
                ],
            ],
            'assessmentWindows' => $this->decodeJsonObject($course->assessment_windows, ['global' => [], 'male' => [], 'female' => []]),
            'assessmentNotificationTemplates' => $this->decodeJsonObject($course->assessment_notification_templates, ['pre' => '', 'post' => '', 'tasks' => '']),
            'taskMode' => $course->task_mode,
            'taskTemplateId' => $course->task_template_id ?? '',
            'taskTemplateName' => $course->task_template_name ?? '',
            'taskTemplateContent' => $course->task_template_content ?? '',
            'youtubeUrl' => $course->youtube_url ?? '',
            'taskDescription' => $course->task_description ?? '',
            'sortOrder' => (int) $course->sort_order,
            'preQuestions' => $courseQuestions['pre'],
            'postQuestions' => $courseQuestions['post'],
            'taskQuestions' => $courseQuestions['tasks'],
            'createdAt' => (string) $course->created_at,
        ];
    }

    private function normalizeCourseQuestions(Collection $items): array
    {
        return $items->map(fn ($item) => [
            'id' => $item->id,
            'prompt' => $item->prompt,
            'type' => $this->mapQuestionType($item->question_type, $item->options),
            'options' => $this->decodeJsonArray($item->options),
            'allowFile' => (bool) $item->allow_file,
            'points' => (int) $item->points,
            'correctAnswer' => $item->correct_answer ?? '',
            'attachmentName' => $item->attachment_name ?? '',
            'attachmentType' => $item->attachment_type ?? '',
            'attachmentDataUrl' => $item->attachment_data_url ?? '',
        ])->values()->all();
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function decodeJsonObject(mixed $value, array $default): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    private function loadJsonAppSetting(string $settingKey, array $default): array
    {
        if (! Schema::hasTable('app_settings')) {
            return $default;
        }

        $storedValue = DB::table('app_settings')
            ->where('setting_key', $settingKey)
            ->value('value');

        return $this->decodeJsonObject($storedValue, $default);
    }

    private function storeJsonAppSetting(string $settingKey, array $value): void
    {
        if (! Schema::hasTable('app_settings')) {
            throw ValidationException::withMessages([
                'settings' => 'جدول الإعدادات غير متاح. نفذ الترحيلات أولاً.',
            ]);
        }

        $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now();

        if (DB::table('app_settings')->where('setting_key', $settingKey)->exists()) {
            DB::table('app_settings')
                ->where('setting_key', $settingKey)
                ->update([
                    'value' => $encodedValue,
                    'updated_at' => $timestamp,
                ]);

            return;
        }

        DB::table('app_settings')->insert([
            'setting_key' => $settingKey,
            'value' => $encodedValue,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function normalizeHomePageContent(array $content): array
    {
        $defaults = $this->defaultHomePageContent();
        $achievementsInput = is_array($content['achievements'] ?? null) ? $content['achievements'] : [];
        $programsInput = is_array($content['programs'] ?? null) ? $content['programs'] : [];
        $faqItemsInput = is_array($content['faqItems'] ?? null) ? $content['faqItems'] : [];

        $programs = [];

        foreach ($defaults['programs'] as $index => $defaultProgram) {
            $programInput = is_array($programsInput[$index] ?? null) ? $programsInput[$index] : [];
            $featuresInput = is_array($programInput['features'] ?? null) ? $programInput['features'] : [];
            $features = [];

            foreach ($defaultProgram['features'] as $featureIndex => $defaultFeature) {
                $features[] = $this->normalizeHomePageText($featuresInput[$featureIndex] ?? null, $defaultFeature);
            }

            $programs[] = [
                'title' => $this->normalizeHomePageText($programInput['title'] ?? null, $defaultProgram['title']),
                'menuSubtitle' => $this->normalizeHomePageText($programInput['menuSubtitle'] ?? null, $defaultProgram['menuSubtitle']),
                'description' => $this->normalizeHomePageText($programInput['description'] ?? null, $defaultProgram['description']),
                'audience' => $this->normalizeHomePageText($programInput['audience'] ?? null, $defaultProgram['audience']),
                'features' => $features,
            ];
        }

        $faqItems = [];

        foreach ($defaults['faqItems'] as $index => $defaultItem) {
            $itemInput = is_array($faqItemsInput[$index] ?? null) ? $faqItemsInput[$index] : [];

            $faqItems[] = [
                'question' => $this->normalizeHomePageText($itemInput['question'] ?? null, $defaultItem['question']),
                'answer' => $this->normalizeHomePageText($itemInput['answer'] ?? null, $defaultItem['answer']),
            ];
        }

        return [
            'brandTitle' => $this->normalizeHomePageText($content['brandTitle'] ?? null, $defaults['brandTitle']),
            'licensesMenuTitle' => $this->normalizeHomePageText($content['licensesMenuTitle'] ?? null, $defaults['licensesMenuTitle']),
            'heroTitle' => $this->normalizeHomePageText($content['heroTitle'] ?? null, $defaults['heroTitle']),
            'heroText' => $this->normalizeHomePageText($content['heroText'] ?? null, $defaults['heroText']),
            'heroPrimaryButtonLabel' => $this->normalizeHomePageText($content['heroPrimaryButtonLabel'] ?? null, $defaults['heroPrimaryButtonLabel']),
            'heroSecondaryButtonLabel' => $this->normalizeHomePageText($content['heroSecondaryButtonLabel'] ?? null, $defaults['heroSecondaryButtonLabel']),
            'programsSectionTitle' => $this->normalizeHomePageText($content['programsSectionTitle'] ?? null, $defaults['programsSectionTitle']),
            'programAvailableActionLabel' => $this->normalizeHomePageText($content['programAvailableActionLabel'] ?? null, $defaults['programAvailableActionLabel']),
            'programUpcomingActionLabel' => $this->normalizeHomePageText($content['programUpcomingActionLabel'] ?? null, $defaults['programUpcomingActionLabel']),
            'faqEyebrow' => $this->normalizeHomePageText($content['faqEyebrow'] ?? null, $defaults['faqEyebrow']),
            'faqTitle' => $this->normalizeHomePageText($content['faqTitle'] ?? null, $defaults['faqTitle']),
            'faqText' => $this->normalizeHomePageText($content['faqText'] ?? null, $defaults['faqText']),
            'footerBrandTitle' => $this->normalizeHomePageText($content['footerBrandTitle'] ?? null, $defaults['footerBrandTitle']),
            'footerDescription' => $this->normalizeHomePageText($content['footerDescription'] ?? null, $defaults['footerDescription']),
            'footerAboutTitle' => $this->normalizeHomePageText($content['footerAboutTitle'] ?? null, $defaults['footerAboutTitle']),
            'footerHomeLabel' => $this->normalizeHomePageText($content['footerHomeLabel'] ?? null, $defaults['footerHomeLabel']),
            'footerLicensesLabel' => $this->normalizeHomePageText($content['footerLicensesLabel'] ?? null, $defaults['footerLicensesLabel']),
            'footerContactTitle' => $this->normalizeHomePageText($content['footerContactTitle'] ?? null, $defaults['footerContactTitle']),
            'footerAddress' => $this->normalizeHomePageText($content['footerAddress'] ?? null, $defaults['footerAddress']),
            'footerPhone' => $this->normalizeHomePageText($content['footerPhone'] ?? null, $defaults['footerPhone']),
            'footerPoliciesTitle' => $this->normalizeHomePageText($content['footerPoliciesTitle'] ?? null, $defaults['footerPoliciesTitle']),
            'footerPrivacyLabel' => $this->normalizeHomePageText($content['footerPrivacyLabel'] ?? null, $defaults['footerPrivacyLabel']),
            'footerTermsLabel' => $this->normalizeHomePageText($content['footerTermsLabel'] ?? null, $defaults['footerTermsLabel']),
            'footerCopyright' => $this->normalizeHomePageText($content['footerCopyright'] ?? null, $defaults['footerCopyright']),
            'footerDevelopedBy' => $this->normalizeHomePageText($content['footerDevelopedBy'] ?? null, $defaults['footerDevelopedBy']),
            'achievements' => [
                'maleTraineesTitle' => $this->normalizeHomePageText($achievementsInput['maleTraineesTitle'] ?? null, $defaults['achievements']['maleTraineesTitle']),
                'femaleTraineesTitle' => $this->normalizeHomePageText($achievementsInput['femaleTraineesTitle'] ?? null, $defaults['achievements']['femaleTraineesTitle']),
                'satisfactionRateTitle' => $this->normalizeHomePageText($achievementsInput['satisfactionRateTitle'] ?? null, $defaults['achievements']['satisfactionRateTitle']),
                'licenseCountTitle' => $this->normalizeHomePageText($achievementsInput['licenseCountTitle'] ?? null, $defaults['achievements']['licenseCountTitle']),
            ],
            'programs' => $programs,
            'faqItems' => $faqItems,
        ];
    }

    private function normalizeHomePageText(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        return str_replace(
            [
                'متدربي ومتدربات',
                'المتدربون والمتدربات',
                'المتدربين والمتدربات',
                'المتدربين',
                'المتدربات',
                'متدربين',
                'متدربات',
                'المتدرب',
                'المتدربة',
                'متدرب',
                'متدربة',
            ],
            [
                'معلمي ومعلمات',
                'المعلمون والمعلمات',
                'المعلمين والمعلمات',
                'المعلمين',
                'المعلمات',
                'معلمين',
                'معلمات',
                'المعلم',
                'المعلمة',
                'معلم',
                'معلمة',
            ],
            trim($value),
        );
    }

    private function normalizePractitionerPageContent(array $content): array
    {
        $defaults = $this->defaultPractitionerPageContent();
        $navItemsInput = is_array($content['navItems'] ?? null) ? $content['navItems'] : $defaults['navItems'];
        $indicatorLabelsInput = is_array($content['indicatorLabels'] ?? null) ? $content['indicatorLabels'] : [];
        $goalsInput = is_array($content['goals'] ?? null) ? $content['goals'] : $defaults['goals'];
        $domainsInput = is_array($content['domains'] ?? null) ? $content['domains'] : $defaults['domains'];
        $includesItemsInput = is_array($content['includesItems'] ?? null) ? $content['includesItems'] : $defaults['includesItems'];
        $requirementsInput = is_array($content['requirements'] ?? null) ? $content['requirements'] : $defaults['requirements'];
        $recitationInput = is_array($content['recitation'] ?? null) ? $content['recitation'] : $defaults['recitation'];
        $recitationMechanismItemsInput = is_array($content['recitationMechanismItems'] ?? null) ? $content['recitationMechanismItems'] : $defaults['recitationMechanismItems'];
        $durationQuickInfoInput = is_array($content['durationQuickInfo'] ?? null) ? $content['durationQuickInfo'] : $defaults['durationQuickInfo'];
        $startDatesInput = is_array($content['startDates'] ?? null) ? $content['startDates'] : $defaults['startDates'];

        $indicatorLabels = [];

        foreach ($defaults['indicatorLabels'] as $key => $defaultLabel) {
            $indicatorLabels[$key] = $this->normalizeHomePageText($indicatorLabelsInput[$key] ?? null, $defaultLabel);
        }

        $domains = [];

        foreach ($domainsInput as $index => $domainInput) {
            $defaultDomain = $defaults['domains'][$index] ?? ['title' => '', 'items' => []];
            $domainInput = is_array($domainInput) ? $domainInput : [];
            $itemsInput = is_array($domainInput['items'] ?? null) ? $domainInput['items'] : $defaultDomain['items'];
            $items = [];

            foreach ($itemsInput as $itemIndex => $itemInput) {
                $defaultItem = $defaultDomain['items'][$itemIndex] ?? '';
                $items[] = $this->normalizeHomePageText($itemsInput[$itemIndex] ?? null, $defaultItem);
            }

            $domains[] = [
                'title' => $this->normalizeHomePageText($domainInput['title'] ?? null, $defaultDomain['title']),
                'items' => $items,
            ];
        }

        $includesItems = [];

        foreach ($includesItemsInput as $index => $itemInput) {
            $defaultItem = $defaults['includesItems'][$index] ?? ['num' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'title' => ''];
            $itemInput = is_array($itemInput) ? $itemInput : [];
            $includesItems[] = [
                'num' => $this->normalizeHomePageText($itemInput['num'] ?? null, $defaultItem['num']),
                'title' => $this->normalizeHomePageText($itemInput['title'] ?? null, $defaultItem['title']),
            ];
        }

        $recitation = [];

        foreach ($recitationInput as $index => $itemInput) {
            $defaultItem = $defaults['recitation'][$index] ?? ['tag' => '', 'text' => ''];
            $itemInput = is_array($itemInput) ? $itemInput : [];
            $recitation[] = [
                'tag' => $this->normalizeHomePageText($itemInput['tag'] ?? null, $defaultItem['tag']),
                'text' => $this->normalizeHomePageText($itemInput['text'] ?? null, $defaultItem['text']),
            ];
        }

        $durationQuickInfo = [];

        foreach ($durationQuickInfoInput as $index => $itemInput) {
            $defaultItem = $defaults['durationQuickInfo'][$index] ?? ['label' => '', 'value' => ''];
            $itemInput = is_array($itemInput) ? $itemInput : [];
            $durationQuickInfo[] = [
                'label' => $this->normalizeHomePageText($itemInput['label'] ?? null, $defaultItem['label']),
                'value' => $this->normalizeHomePageText($itemInput['value'] ?? null, $defaultItem['value']),
            ];
        }

        $startDates = [];

        foreach ($startDatesInput as $index => $itemInput) {
            $defaultItem = $defaults['startDates'][$index] ?? ['tag' => '', 'text' => ''];
            $itemInput = is_array($itemInput) ? $itemInput : [];
            $startDates[] = [
                'tag' => $this->normalizeHomePageText($itemInput['tag'] ?? null, $defaultItem['tag']),
                'text' => $this->normalizeHomePageText($itemInput['text'] ?? null, $defaultItem['text']),
            ];
        }

        return [
            'brandTitle' => $this->normalizeHomePageText($content['brandTitle'] ?? null, $defaults['brandTitle']),
            'heroTitle' => $this->normalizeHomePageText($content['heroTitle'] ?? null, $defaults['heroTitle']),
            'heroText' => $this->normalizeHomePageText($content['heroText'] ?? null, $defaults['heroText']),
            'heroPrimaryButtonLabel' => $this->normalizeHomePageText($content['heroPrimaryButtonLabel'] ?? null, $defaults['heroPrimaryButtonLabel']),
            'heroSecondaryButtonLabel' => $this->normalizeHomePageText($content['heroSecondaryButtonLabel'] ?? null, $defaults['heroSecondaryButtonLabel']),
            'navItems' => collect($navItemsInput)->map(function ($itemInput, int $index) use ($defaults) {
                $defaultItem = $defaults['navItems'][$index] ?? ['label' => ''];
                $itemInput = is_array($itemInput) ? $itemInput : [];

                return [
                    'label' => $this->normalizeHomePageText($itemInput['label'] ?? null, $defaultItem['label']),
                ];
            })->values()->all(),
            'aboutEyebrow' => $this->normalizeHomePageText($content['aboutEyebrow'] ?? null, $defaults['aboutEyebrow']),
            'aboutTitlePrefix' => $this->normalizeHomePageText($content['aboutTitlePrefix'] ?? null, $defaults['aboutTitlePrefix']),
            'aboutTitleHighlight' => $this->normalizeHomePageText($content['aboutTitleHighlight'] ?? null, $defaults['aboutTitleHighlight']),
            'aboutLead' => $this->normalizeHomePageText($content['aboutLead'] ?? null, $defaults['aboutLead']),
            'aboutBody' => $this->normalizeHomePageText($content['aboutBody'] ?? null, $defaults['aboutBody']),
            'goalsHeadingPrefix' => $this->normalizeHomePageText($content['goalsHeadingPrefix'] ?? null, $defaults['goalsHeadingPrefix']),
            'goalsHeadingHighlight' => $this->normalizeHomePageText($content['goalsHeadingHighlight'] ?? null, $defaults['goalsHeadingHighlight']),
            'goals' => collect($goalsInput)->map(fn ($item, int $index) => $this->normalizeHomePageText($item, $defaults['goals'][$index] ?? ''))->values()->all(),
            'statsEyebrow' => $this->normalizeHomePageText($content['statsEyebrow'] ?? null, $defaults['statsEyebrow']),
            'statsTitlePrefix' => $this->normalizeHomePageText($content['statsTitlePrefix'] ?? null, $defaults['statsTitlePrefix']),
            'statsTitleHighlight' => $this->normalizeHomePageText($content['statsTitleHighlight'] ?? null, $defaults['statsTitleHighlight']),
            'indicatorLabels' => $indicatorLabels,
            'competenciesTitle' => $this->normalizeHomePageText($content['competenciesTitle'] ?? null, $defaults['competenciesTitle']),
            'domains' => $domains,
            'includesTitle' => $this->normalizeHomePageText($content['includesTitle'] ?? null, $defaults['includesTitle']),
            'includesItems' => $includesItems,
            'requirementsTitle' => $this->normalizeHomePageText($content['requirementsTitle'] ?? null, $defaults['requirementsTitle']),
            'requirements' => collect($requirementsInput)->map(fn ($item, int $index) => $this->normalizeHomePageText($item, $defaults['requirements'][$index] ?? ''))->values()->all(),
            'recitationTitle' => $this->normalizeHomePageText($content['recitationTitle'] ?? null, $defaults['recitationTitle']),
            'recitation' => $recitation,
            'recitationMechanismTitle' => $this->normalizeHomePageText($content['recitationMechanismTitle'] ?? null, $defaults['recitationMechanismTitle']),
            'recitationMechanismItems' => collect($recitationMechanismItemsInput)->map(fn ($item, int $index) => $this->normalizeHomePageText($item, $defaults['recitationMechanismItems'][$index] ?? ''))->values()->all(),
            'durationTitle' => $this->normalizeHomePageText($content['durationTitle'] ?? null, $defaults['durationTitle']),
            'durationQuickInfo' => $durationQuickInfo,
            'durationDescriptionPrimary' => $this->normalizeHomePageText($content['durationDescriptionPrimary'] ?? null, $defaults['durationDescriptionPrimary']),
            'durationDescriptionSecondary' => $this->normalizeHomePageText($content['durationDescriptionSecondary'] ?? null, $defaults['durationDescriptionSecondary']),
            'startDatesTitle' => $this->normalizeHomePageText($content['startDatesTitle'] ?? null, $defaults['startDatesTitle']),
            'startDates' => $startDates,
            'footerBrandTitle' => $this->normalizeHomePageText($content['footerBrandTitle'] ?? null, $defaults['footerBrandTitle']),
            'footerDescription' => $this->normalizeHomePageText($content['footerDescription'] ?? null, $defaults['footerDescription']),
            'footerQuickLinksTitle' => $this->normalizeHomePageText($content['footerQuickLinksTitle'] ?? null, $defaults['footerQuickLinksTitle']),
            'footerContactTitle' => $this->normalizeHomePageText($content['footerContactTitle'] ?? null, $defaults['footerContactTitle']),
            'footerAddress' => $this->normalizeHomePageText($content['footerAddress'] ?? null, $defaults['footerAddress']),
            'footerPhone' => $this->normalizeHomePageText($content['footerPhone'] ?? null, $defaults['footerPhone']),
            'footerPoliciesTitle' => $this->normalizeHomePageText($content['footerPoliciesTitle'] ?? null, $defaults['footerPoliciesTitle']),
            'footerPrivacyLabel' => $this->normalizeHomePageText($content['footerPrivacyLabel'] ?? null, $defaults['footerPrivacyLabel']),
            'footerTermsLabel' => $this->normalizeHomePageText($content['footerTermsLabel'] ?? null, $defaults['footerTermsLabel']),
            'footerCopyright' => $this->normalizeHomePageText($content['footerCopyright'] ?? null, $defaults['footerCopyright']),
            'footerDevelopedBy' => $this->normalizeHomePageText($content['footerDevelopedBy'] ?? null, $defaults['footerDevelopedBy']),
            'loginDialogTitle' => $this->normalizeHomePageText($content['loginDialogTitle'] ?? null, $defaults['loginDialogTitle']),
            'loginCodeLabel' => $this->normalizeHomePageText($content['loginCodeLabel'] ?? null, $defaults['loginCodeLabel']),
            'loginPasswordLabel' => $this->normalizeHomePageText($content['loginPasswordLabel'] ?? null, $defaults['loginPasswordLabel']),
            'loginSubmitLabel' => $this->normalizeHomePageText($content['loginSubmitLabel'] ?? null, $defaults['loginSubmitLabel']),
        ];
    }

    private function defaultHomePageContent(): array
    {
        return [
            'brandTitle' => 'منصة الرخص المهنية',
            'licensesMenuTitle' => 'اختر الرخصة المهنية',
            'heroTitle' => 'منصة الرخص المهنية',
            'heroText' => 'بيئة موحدة لإدارة الرخص المهنية، متابعة التقييمات، ورفع جاهزية المعلمين والمعلمات عبر برامج تأهيلية واضحة ومسارات مرنة وتجربة رقمية حديثة.',
            'heroPrimaryButtonLabel' => 'الرخص المهنية',
            'heroSecondaryButtonLabel' => 'إنجازاتنا',
            'programsSectionTitle' => 'الرخص المهنية',
            'programAvailableActionLabel' => 'الدخول إلى الرخصة',
            'programUpcomingActionLabel' => 'سيتم الإطلاق قريباً',
            'faqEyebrow' => 'الأسئلة الشائعة',
            'faqTitle' => 'إجابات سريعة لأكثر الأسئلة تداولاً',
            'faqText' => 'توضيحات مختصرة حول مفهوم الرخصة المهنية، شروطها، وآلية التقدم عليها للمعلمين والمعلمات.',
            'footerBrandTitle' => 'منصة الرخص المهنية',
            'footerDescription' => 'برنامج تأهيلي يُعنى بإعداد معلمي ومعلمات القرآن عبر أربع مجالات رئيسة (الشرعي، التعليمي، التربوي، المهاري)، بهدف تأهيلهم لقيادة الحلقة القرآنية بكفاءة وفاعلية.',
            'footerAboutTitle' => 'عن المنصة',
            'footerHomeLabel' => 'الرئيسية',
            'footerLicensesLabel' => 'الرخص المتاحة',
            'footerContactTitle' => 'تواصل معنا',
            'footerAddress' => 'القصيم، المملكة العربية السعودية',
            'footerPhone' => '+966 50 000 0000',
            'footerPoliciesTitle' => 'الأنظمة والسياسات',
            'footerPrivacyLabel' => 'سياسة الخصوصية',
            'footerTermsLabel' => 'الشروط والأحكام',
            'footerCopyright' => 'جمعية تحفيظ القرآن الكريم. جميع الحقوق محفوظة.',
            'footerDevelopedBy' => 'تم التطوير بواسطة',
            'achievements' => [
                'maleTraineesTitle' => 'أعداد المعلمين',
                'femaleTraineesTitle' => 'أعداد المعلمات',
                'satisfactionRateTitle' => 'نسبة الرضا',
                'licenseCountTitle' => 'رخص متعدد',
            ],
            'programs' => [
                [
                    'title' => 'رخصة ممارس',
                    'menuSubtitle' => '',
                    'description' => 'المسار الأساسي لإعداد الممارسين عبر المحتوى التأهيلي والاختبارات وإدارة التقدم.',
                    'audience' => 'الفئة المستهدفة: المعلمون والمعلمات',
                    'features' => ['مسار تأهيلي متكامل', 'اختبارات قبلية وبعدية', 'متابعة نتائج وتقارير'],
                ],
                [
                    'title' => 'رخصة مدير',
                    'menuSubtitle' => '',
                    'description' => 'مسار متخصص للقيادة الإدارية، الإشراف على البرامج، وقياس الجاهزية التشغيلية.',
                    'audience' => 'الفئة المستهدفة: مدراء البرامج والقيادات التنفيذية',
                    'features' => ['لوحات مؤشرات تنفيذية', 'إشراف على الدورات', 'متابعة حالة الفروع'],
                ],
                [
                    'title' => 'رخصة مشرف',
                    'menuSubtitle' => '',
                    'description' => 'مسار إشرافي لمتابعة الأداء الميداني وجودة التنفيذ والتواصل مع الفرق التدريبية.',
                    'audience' => 'الفئة المستهدفة: المشرفون والمشرفات',
                    'features' => ['متابعة ميدانية', 'مؤشرات أداء فرعية', 'تنسيق مباشر مع المقيمين'],
                ],
                [
                    'title' => 'رخصة سكرتير',
                    'menuSubtitle' => '',
                    'description' => 'مسار تشغيلي يركز على التنظيم الإداري وإدارة المهام اليومية والملفات والمتابعة.',
                    'audience' => 'الفئة المستهدفة: فرق السكرتارية والإسناد الإداري',
                    'features' => ['تنظيم المهام', 'إدارة السجلات', 'متابعة الجداول والتنبيهات'],
                ],
            ],
            'faqItems' => [
                [
                    'question' => 'ما هي الرخصة المهنية؟',
                    'answer' => 'الرخصة المهنية وثيقة تنظيمية تمنح شاغلي الوظائف التعليمية بعد استيفاء المتطلبات والمعايير المعتمدة، وتؤكد الجاهزية المهنية والمعرفية لممارسة التعليم بكفاءة.',
                ],
                [
                    'question' => 'ما شروط الحصول على الرخصة المهنية؟',
                    'answer' => 'تشمل الشروط عادةً استيفاء المؤهل المطلوب، والتسجيل في الاختبارات المهنية المعتمدة، وتحقيق الدرجة المطلوبة، والالتزام بالضوابط والإجراءات التي تعلنها الجهة المختصة.',
                ],
                [
                    'question' => 'كيف أتقدم للحصول على رخصة مهنية كمعلم؟',
                    'answer' => 'تتقدم عبر التسجيل في المنصة أو الجهة المعتمدة، ثم حجز الاختبار المهني المناسب، واستكمال البيانات المطلوبة، وأداء الاختبار، وبعد ظهور النتيجة واستيفاء الشروط يتم إصدار الرخصة أو استكمال ما يلزم لإتمامها.',
                ],
            ],
        ];
    }

    private function defaultPractitionerPageContent(): array
    {
        return [
            'brandTitle' => 'برنامج رخصة ممارس',
            'heroTitle' => 'برنامج رخصة ممارس',
            'heroText' => 'برنامج تأهيلي يُعنى بإعداد معلمي ومعلمات القرآن عبر أربع مجالات رئيسة (الشرعي، التعليمي، التربوي، المهاري)، بهدف تأهيلهم لقيادة الحلقة القرآنية بكفاءة وفاعلية.',
            'heroPrimaryButtonLabel' => 'سجل الآن',
            'heroSecondaryButtonLabel' => 'تعرّف على البرنامج',
            'navItems' => [
                ['label' => 'عن البرنامج'],
                ['label' => 'مجالات وكفايات البرنامج'],
                ['label' => 'المتطلبات'],
            ],
            'aboutEyebrow' => 'لمحة عن البرنامج',
            'aboutTitlePrefix' => 'برنامج',
            'aboutTitleHighlight' => 'رخصة ممارس',
            'aboutLead' => 'برنامج تأهيلي يُعنى بإعداد معلمي ومعلمات القرآن عبر أربع مجالات رئيسة (الشرعي، التعليمي، التربوي، المهاري)، بهدف تأهيلهم لقيادة الحلقة القرآنية بكفاءة وفاعلية.',
            'aboutBody' => 'يتضمن البرنامج لقاءات تدريبية حضورية ومهام أدائية إضافة إلى عرض القرآن، بما يعزز كفاءة المعلم والمعلمة في تعليم القرآن.',
            'goalsHeadingPrefix' => 'أهداف',
            'goalsHeadingHighlight' => 'البرنامج',
            'goals' => [
                'التعرّف على أهمية العلم الشرعي وأهم مسائل العقيدة والطهارة والصلاة',
                'إتقان أساسيات تعليم القرآن الكريم ومبادئ علم التجويد',
                'توظيف الأساليب التربوية المناسبة في التعامل مع المعلمين',
                'استحضار أهمية الرسالة التعليمية والالتزام بها',
                'تطبيق مهارات التواصل والتخطيط في البيئة التعليمية',
                'إدارة الحلقة القرآنية وتنظيمها بكفاءة',
            ],
            'statsEyebrow' => 'نظرة سريعة',
            'statsTitlePrefix' => 'مؤشرات',
            'statsTitleHighlight' => 'البرنامج',
            'indicatorLabels' => [
                'memorization' => 'مجموع الأجزاء المقروءة',
                'attendance' => 'الحضور',
                'pre' => 'الاختبار القبلي',
                'post' => 'الاختبار البعدي',
                'tasks' => 'المهام الأدائية',
                'completed30' => 'المعلمون الذين أنهوا 30 جزءًا',
            ],
            'competenciesTitle' => 'مجالات وكفايات البرنامج',
            'domains' => [
                [
                    'title' => 'كفايات المجال الشرعي',
                    'items' => [
                        'أهمية العلم الشرعي وأثره في حياة المعلم/ة',
                        'أهم مسائل التوحيد والإيمان',
                        'أهم مسائل الطهارة',
                        'الأحكام العامة للصلاة',
                    ],
                ],
                [
                    'title' => 'كفايات المجال التعليمي',
                    'items' => [
                        'مبادئ أحكام التجويد نظريًا وتطبيقيًا',
                        'استراتيجيات تعليم القرآن الكريم',
                        'مباحث وآداب قرآنية',
                    ],
                ],
                [
                    'title' => 'كفايات المجال التربوي',
                    'items' => [
                        'مدخل في التربية وأهميتها وخصائصها',
                        'خصائص المراحل العمرية واحتياجاتها',
                        'الأساليب التربوية',
                        'بناء القيم وتعزيز السلوك',
                        'الصحة النفسية في البيئة التعليمية',
                    ],
                ],
                [
                    'title' => 'كفايات المجال المهاري',
                    'items' => [
                        'مهارات التواصل الفعال',
                        'إدارة الحلقة القرآنية',
                        'تكامل شخصية المعلم',
                        'مهارات التخطيط',
                        'التعامل مع النظام التقني (ناظم)',
                        'الدور الاستراتيجي للمعلم والمعلمة',
                    ],
                ],
            ],
            'includesTitle' => 'ماذا يتضمن البرنامج؟',
            'includesItems' => [
                ['num' => '01', 'title' => 'لقاءات تدريبية حضورية'],
                ['num' => '02', 'title' => 'مهام أدائية تطبيقية'],
                ['num' => '03', 'title' => 'عرض القرآن'],
                ['num' => '04', 'title' => 'اختبارات قبلية وبعدية'],
                ['num' => '05', 'title' => 'اختبار نهائي'],
            ],
            'requirementsTitle' => 'متطلبات الحصول على الرخصة',
            'requirements' => [
                'حضور ما لا يقل عن (10) لقاءات من اللقاءات التدريبية',
                'تنفيذ (80%) من المهام الأدائية',
                'اجتياز الاختبار النهائي بنسبة لا تقل عن (70%)',
                'الالتزام بآداب وأخلاقيات تعليم القرآن الكريم',
            ],
            'recitationTitle' => 'إتمام عرض القرآن وفق الآتي:',
            'recitation' => [
                ['tag' => 'الرجال', 'text' => 'المعلمون: عرض كامل القرآن'],
                ['tag' => 'النساء', 'text' => 'المعلمات: عرض (15) جزءًا'],
            ],
            'recitationMechanismTitle' => 'آلية عرض القرآن وختمة التلاوة',
            'recitationMechanismItems' => [
                'أن يكون العرض على مقرئ معتمد من معهد الإمام عاصم أو من إدارة الشؤون التعليمية.',
                'يكون مقدار العرض: المعلمون: (27) جزءًا قراءةً مرسلةً واضحةً خاليةً من اللحون الجلية ثم (3) أجزاءٍ بالتجويد.',
                'يكون مقدار العرض: المعلمات: (14) جزءًا قراءةً مرسلةً واضحةً خاليةً من اللحون الجلية ثم جزء واحد بالتجويد.',
                'يُسمح بخمسة تنبيهات لكل جزء.',
                'عند التنبيه السادس يوقف المشارك ويستمع إلى المصحف المعلم لضبط الموضع ثم يعيد قراءة الجزء، ويكرر ذلك حتى يتم الإتقان.',
                'تُطبق في الأجزاء المقروءة بالتجويد أحكام التجويد الأساسية: أحكام النون الساكنة والتنوين، الميم الساكنة، النون والميم المشددتين، المدود.',
            ],
            'durationTitle' => 'مدة البرنامج وآلية التنفيذ',
            'durationQuickInfo' => [
                ['label' => 'المدة', 'value' => 'ستة أسابيع'],
                ['label' => 'التكرار', 'value' => 'دورتان أسبوعيًا'],
                ['label' => 'آلية التنفيذ', 'value' => 'حضوريًا'],
            ],
            'durationDescriptionPrimary' => 'مدة البرنامج ستة أسابيع، بواقع دورتين تدريبيتين أسبوعيًا، يتخللها تنفيذ مهام أدائية واختبارات قبلية وبعدية، إضافة إلى عرض القرآن واختبار نهائي.',
            'durationDescriptionSecondary' => 'يُنفّذ البرنامج حضوريًا، وفق الجدول التدريبي المعتمد لكل من المعلمين والمعلمات.',
            'startDatesTitle' => 'بداية البرنامج',
            'startDates' => [
                ['tag' => 'الرجال', 'text' => 'يوم الإثنين 18 / 10 / 1447هـ'],
                ['tag' => 'النساء', 'text' => 'يوم السبت 23 / 10 / 1447هـ'],
            ],
            'footerBrandTitle' => 'برنامج رخصة ممارس',
            'footerDescription' => 'برنامج تأهيلي يُعنى بإعداد معلمي ومعلمات القرآن عبر أربع مجالات رئيسة (الشرعي، التعليمي، التربوي، المهاري)، بهدف تأهيلهم لقيادة الحلقة القرآنية بكفاءة وفاعلية.',
            'footerQuickLinksTitle' => 'روابط سريعة',
            'footerContactTitle' => 'تواصل معنا',
            'footerAddress' => 'القصيم، المملكة العربية السعودية',
            'footerPhone' => '+966 50 000 0000',
            'footerPoliciesTitle' => 'الأنظمة والسياسات',
            'footerPrivacyLabel' => 'سياسة الخصوصية',
            'footerTermsLabel' => 'الشروط والأحكام',
            'footerCopyright' => 'برنامج رخصة ممارس. جميع الحقوق محفوظة.',
            'footerDevelopedBy' => 'تم التطوير بواسطة',
            'loginDialogTitle' => 'تسجيل الدخول',
            'loginCodeLabel' => 'رقم الدخول',
            'loginPasswordLabel' => 'كلمة المرور',
            'loginSubmitLabel' => 'دخول',
        ];
    }

    private function normalizeFinalExamSetting(mixed $setting): array
    {
        if (! $setting) {
            return [
                'isEnabled' => false,
                'closesAt' => null,
                'notificationTemplate' => 'تم فتح الاختبار النهائي لفرع {branchLabel} لمدة {durationMinutes} دقيقة.',
            ];
        }

        return [
            'isEnabled' => (bool) $setting->is_enabled,
            'closesAt' => $setting->closes_at,
            'notificationTemplate' => $setting->notification_template !== ''
                ? $setting->notification_template
                : 'تم فتح الاختبار النهائي لفرع {branchLabel} لمدة {durationMinutes} دقيقة.',
        ];
    }

    private function assertFinalExamSubmissionIsOpen(string $branchCode): void
    {
        $setting = DB::table('final_exam_settings')->where('branch_code', $branchCode)->first();

        if (! $setting || ! (bool) ($setting->is_enabled ?? false)) {
            throw ValidationException::withMessages([
                'branchCode' => 'انتهى وقت الإرسال أو أن الاختبار النهائي غير متاح حاليًا.',
            ]);
        }

        if ($setting->closes_at && ! $this->isWindowCurrentlyOpen(['closesAt' => $setting->closes_at])) {
            throw ValidationException::withMessages([
                'branchCode' => 'انتهى وقت الإرسال أو أن الاختبار النهائي غير متاح حاليًا.',
            ]);
        }
    }

    private function assertAssessmentSubmissionIsOpen(object $course, string $assessmentType, ?Student $student): void
    {
        $enabledColumn = $this->assessmentEnabledColumn($assessmentType);

        if (! (bool) ($course->{$enabledColumn} ?? false)) {
            throw ValidationException::withMessages([
                'loginId' => 'انتهى وقت الإرسال أو أن التقييم غير متاح حاليًا.',
            ]);
        }

        $windows = $this->decodeJsonObject($course->assessment_windows, ['global' => [], 'male' => [], 'female' => []]);
        $branchCode = $student?->branch?->code;

        if ($branchCode && ! $this->isAssessmentBranchEnabled($course, $assessmentType, $branchCode)) {
            throw ValidationException::withMessages([
                'loginId' => 'انتهى وقت الإرسال أو أن التقييم غير متاح حاليًا.',
            ]);
        }

        $globalWindow = data_get($windows, "global.$assessmentType");

        if (! $branchCode) {
            if ($this->assessmentWindowHasValue($globalWindow) && ! $this->isWindowCurrentlyOpen($globalWindow)) {
                throw ValidationException::withMessages([
                    'loginId' => 'انتهى وقت الإرسال أو أن التقييم غير متاح حاليًا.',
                ]);
            }

            return;
        }

        $branchWindow = data_get($windows, "$branchCode.$assessmentType");
        $hasWindowConfig = $this->assessmentWindowHasValue($branchWindow) || $this->assessmentWindowHasValue($globalWindow);

        if ($hasWindowConfig && ! $this->isWindowCurrentlyOpen($branchWindow) && ! $this->isWindowCurrentlyOpen($globalWindow)) {
            throw ValidationException::withMessages([
                'loginId' => 'انتهى وقت الإرسال أو أن التقييم غير متاح حاليًا.',
            ]);
        }
    }

    private function assessmentEnabledColumn(string $assessmentType): string
    {
        return [
            'pre' => 'is_pre_enabled',
            'post' => 'is_post_enabled',
            'tasks' => 'is_tasks_enabled',
        ][$assessmentType] ?? 'is_pre_enabled';
    }

    private function isAssessmentBranchEnabled(object $course, string $assessmentType, string $branchCode): bool
    {
        $column = [
            'male' => [
                'pre' => 'male_pre_enabled',
                'post' => 'male_post_enabled',
                'tasks' => 'male_tasks_enabled',
            ],
            'female' => [
                'pre' => 'female_pre_enabled',
                'post' => 'female_post_enabled',
                'tasks' => 'female_tasks_enabled',
            ],
        ][$branchCode][$assessmentType] ?? null;

        return $column ? (bool) ($course->{$column} ?? false) : false;
    }

    private function isWindowCurrentlyOpen(mixed $window): bool
    {
        if (! $this->assessmentWindowHasValue($window)) {
            return false;
        }

        $opensAt = null;
        $closesAt = null;

        if (is_array($window)) {
            $opensAt = trim((string) ($window['opensAt'] ?? ''));
            $closesAt = trim((string) ($window['closesAt'] ?? ''));
        } else {
            $closesAt = trim((string) $window);
        }

        try {
            if ($opensAt !== '' && Carbon::parse($opensAt)->isFuture()) {
                return false;
            }

            return $closesAt !== '' && Carbon::parse($closesAt)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    private function mapQuestionType(?string $questionType, mixed $options): string
    {
        if ($questionType === 'text') {
            return 'text';
        }

        $normalizedOptions = array_map(
            static fn ($option) => mb_strtolower(trim((string) $option)),
            $this->decodeJsonArray($options),
        );

        if (count($normalizedOptions) === 2 && in_array('صح', $normalizedOptions, true) && in_array('خطأ', $normalizedOptions, true)) {
            return 'truefalse';
        }

        return 'multiple';
    }
}
