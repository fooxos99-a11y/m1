<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EditorAsset;
use App\Models\Reciter;
use App\Models\Student;
use App\Models\TrainingMaterial;
use App\Services\CoreDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CoreDataController extends Controller
{
    private const EDITOR_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private const TRAINING_ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'application/zip',
        'application/x-zip-compressed',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'audio/mpeg',
        'audio/wav',
    ];

    private const ANSWER_ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    private const MAX_ANSWER_DATA_URL_LENGTH = 28000000;

    public function __construct(private readonly CoreDataService $coreDataService) {}

    private function publicPathPrefix(): string
    {
        $basePath = trim((string) config('app.public_base_path', ''), '/');

        return $basePath === '' ? '' : '/'.$basePath;
    }

    public function listDashboardAccounts(): JsonResponse
    {
        return response()->json($this->coreDataService->listDashboardAccounts());
    }

    public function storeTaskTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'content' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->coreDataService->createTaskTemplate($data['name'], (string) ($data['content'] ?? '')),
            201,
        );
    }

    public function updateTaskTemplate(Request $request, string $templateId): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'content' => ['sometimes', 'nullable', 'string'],
        ]);

        $this->coreDataService->updateTaskTemplate($templateId, $data);

        return response()->json(status: 204);
    }

    public function storeEditorImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'mimetypes:'.implode(',', self::EDITOR_IMAGE_MIME_TYPES), 'max:5120'],
        ]);

        $editorAsset = EditorAsset::query()->create([
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);

        $media = $editorAsset
            ->addMedia($data['image'])
            ->usingName(pathinfo($data['image']->getClientOriginalName(), PATHINFO_FILENAME))
            ->usingFileName($data['image']->hashName())
            ->toMediaCollection('editor-images', 'public');

        return response()->json([
            'id' => $media->uuid ?? (string) $media->id,
            'name' => $media->name,
            'url' => $request->getSchemeAndHttpHost().$this->publicPathPrefix().'/storage/'.ltrim($media->getPathRelativeToRoot(), '/'),
        ], Response::HTTP_CREATED);
    }

    public function snapshot(): JsonResponse
    {
        return response()->json($this->coreDataService->loadDashboardSnapshot());
    }

    public function publicStats(): JsonResponse
    {
        return response()->json($this->coreDataService->loadPublicStats());
    }

    public function trainingMaterialAttachment(string $attachmentId): BinaryFileResponse
    {
        $media = Media::query()
            ->where('collection_name', 'attachments')
            ->where(function ($query) use ($attachmentId) {
                $query->where('uuid', $attachmentId);

                if (ctype_digit($attachmentId)) {
                    $query->orWhereKey((int) $attachmentId);
                }
            })
            ->firstOrFail();

        abort_unless($media->model_type === TrainingMaterial::class, 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateHomePageContent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'array'],
        ]);

        return response()->json($this->coreDataService->updateHomePageContent($data['content']));
    }

    public function updatePractitionerPageContent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'array'],
        ]);

        return response()->json($this->coreDataService->updatePractitionerPageContent($data['content']));
    }

    public function activityLogs(): JsonResponse
    {
        return response()->json($this->coreDataService->loadActivityLogs());
    }

    public function setManualAttendance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'courseId' => ['required', 'string'],
            'presentStudents' => ['required', 'array'],
            'presentStudents.*.loginId' => ['required', 'string'],
            'presentStudents.*.studentName' => ['required', 'string'],
            'presentStudents.*.studentId' => ['nullable', 'string'],
        ]);

        $this->coreDataService->setManualAttendance($data['courseId'], $data['presentStudents']);

        return response()->json(status: 204);
    }

    public function submitAssessment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'courseId' => ['required', 'string', 'max:100'],
            'assessmentType' => ['required', 'string', 'in:pre,post,tasks'],
            'studentName' => ['required', 'string', 'max:255'],
            'loginId' => ['required', 'string', 'max:255'],
            'answers' => ['required', 'array', 'max:500'],
            'answers.*.questionId' => ['required', 'string', 'max:100'],
            'answers.*.value' => ['nullable', 'string', 'max:20000'],
            'answers.*.fileName' => ['nullable', 'string', 'max:255'],
            'answers.*.fileType' => ['nullable', 'string', 'max:100'],
            'answers.*.fileDataUrl' => ['nullable', 'string'],
            'answers.*.files' => ['prohibited'],
        ]);

        $this->validateAnswerFilePayloads($data['answers']);

        if ($request->is('api/public/*')) {
            $this->assertAuthenticatedSubmitter($request, $data['loginId']);
        }

        return response()->json(
            $this->coreDataService->submitAssessment($data['courseId'], $data['assessmentType'], $data),
            201,
        );
    }

    public function bulkImportAssessments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'courseId' => ['required', 'string', 'max:100'],
            'assessmentType' => ['required', 'string', 'in:pre,post,tasks'],
            'submissions' => ['required', 'array', 'max:1000'],
            'submissions.*.studentName' => ['required', 'string', 'max:255'],
            'submissions.*.loginId' => ['required', 'string', 'max:255'],
            'submissions.*.manualScore' => ['nullable', 'numeric'],
            'submissions.*.answers' => ['required', 'array', 'max:500'],
            'submissions.*.answers.*.questionId' => ['required', 'string', 'max:100'],
            'submissions.*.answers.*.value' => ['nullable', 'string', 'max:20000'],
            'submissions.*.answers.*.fileName' => ['nullable', 'string', 'max:255'],
            'submissions.*.answers.*.fileType' => ['nullable', 'string', 'max:100'],
            'submissions.*.answers.*.fileDataUrl' => ['nullable', 'string'],
            'submissions.*.answers.*.files' => ['prohibited'],
        ]);

        foreach ($data['submissions'] as $submissionIndex => $submission) {
            $this->validateAnswerFilePayloads($submission['answers'] ?? [], "submissions.$submissionIndex.answers");
        }

        return response()->json(
            $this->coreDataService->bulkImportAssessments($data['courseId'], $data['assessmentType'], $data['submissions']),
        );
    }

    public function storeCourse(Request $request): JsonResponse
    {
        $this->normalizeTaskTemplateContentInput($request);

        $data = $request->validate([
            'title' => ['required', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'entityType' => ['nullable', 'string'],
            'taskMode' => ['nullable', 'string'],
            'taskTemplateId' => ['nullable', 'string'],
            'taskTemplateName' => ['nullable', 'string'],
            'taskTemplateContent' => ['nullable', 'string'],
            'youtubeUrl' => ['nullable', 'string'],
            'taskDescription' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->coreDataService->createCourse($data['title'], (bool) ($data['isActive'] ?? false), $data),
            201,
        );
    }

    public function updateCourse(Request $request, string $courseId): JsonResponse
    {
        $this->normalizeTaskTemplateContentInput($request);

        $data = $request->validate([
            'title' => ['sometimes', 'string'],
            'entityType' => ['sometimes', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'isPreEnabled' => ['sometimes', 'boolean'],
            'isPostEnabled' => ['sometimes', 'boolean'],
            'isTasksEnabled' => ['sometimes', 'boolean'],
            'branchAvailability' => ['sometimes', 'array'],
            'assessmentWindows' => ['sometimes', 'array'],
            'assessmentNotificationTemplates' => ['sometimes', 'array'],
            'taskMode' => ['sometimes', 'nullable', 'string'],
            'taskTemplateId' => ['sometimes', 'nullable', 'string'],
            'taskTemplateName' => ['sometimes', 'string'],
            'taskTemplateContent' => ['sometimes', 'nullable', 'string'],
            'youtubeUrl' => ['sometimes', 'nullable', 'string'],
            'taskDescription' => ['sometimes', 'nullable', 'string'],
        ]);

        $this->coreDataService->updateCourse($courseId, $data);

        return response()->json(status: 204);
    }

    private function normalizeTaskTemplateContentInput(Request $request): void
    {
        if (! $request->exists('taskTemplateContent')) {
            return;
        }

        $value = $request->input('taskTemplateContent');

        if ($value === null || is_string($value)) {
            return;
        }

        $request->merge([
            'taskTemplateContent' => is_scalar($value)
                ? (string) $value
                : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        ]);
    }

    public function deleteCourse(string $courseId): JsonResponse
    {
        $this->coreDataService->deleteCourse($courseId);

        return response()->json(status: 204);
    }

    public function updateCourseSortOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orderedIds' => ['required', 'array'],
            'orderedIds.*' => ['string'],
        ]);

        $this->coreDataService->updateCoursesSortOrder($data['orderedIds']);

        return response()->json(status: 204);
    }

    public function activateCourse(Request $request, string $courseId): JsonResponse
    {
        $data = $request->validate([
            'pre' => ['sometimes', 'boolean'],
            'post' => ['sometimes', 'boolean'],
            'tasks' => ['sometimes', 'boolean'],
        ]);

        $this->coreDataService->activateCourse($courseId, $data === [] ? null : $data);

        return response()->json(status: 204);
    }

    public function deactivateAllCourses(): JsonResponse
    {
        $this->coreDataService->deactivateAllCourses();

        return response()->json(status: 204);
    }

    public function storeCourseQuestion(Request $request, string $courseId): JsonResponse
    {
        $data = $request->validate([
            'assessmentType' => ['required', 'string'],
            'prompt' => ['required', 'string'],
            'type' => ['required', 'string'],
            'options' => ['sometimes', 'array'],
            'options.*' => ['string'],
            'allowFile' => ['required', 'boolean'],
            'points' => ['required', 'integer'],
            'correctAnswer' => ['nullable', 'string'],
            'attachmentName' => ['nullable', 'string'],
            'attachmentType' => ['nullable', 'string'],
            'attachmentDataUrl' => ['nullable', 'string'],
        ]);

        return response()->json([
            'id' => $this->coreDataService->addCourseQuestion($courseId, $data['assessmentType'], $data),
        ], 201);
    }

    public function deleteCourseQuestion(string $questionId): JsonResponse
    {
        $this->coreDataService->deleteCourseQuestion($questionId);

        return response()->json(status: 204);
    }

    public function updateCourseQuestion(Request $request, string $questionId): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string'],
            'type' => ['required', 'string'],
            'options' => ['sometimes', 'array'],
            'options.*' => ['string'],
            'allowFile' => ['required', 'boolean'],
            'points' => ['required', 'integer'],
            'correctAnswer' => ['nullable', 'string'],
            'attachmentName' => ['nullable', 'string'],
            'attachmentType' => ['nullable', 'string'],
            'attachmentDataUrl' => ['nullable', 'string'],
        ]);

        $this->coreDataService->updateCourseQuestion($questionId, $data);

        return response()->json(status: 204);
    }

    public function storeSatisfactionQuestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string'],
            'type' => ['required', 'string'],
            'isRequired' => ['required', 'boolean'],
            'targetScope' => ['sometimes', 'nullable', 'string', 'in:all,course'],
            'courseId' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->coreDataService->addSatisfactionQuestion(
                $data['prompt'],
                $data['type'],
                $data['isRequired'],
                (string) ($data['targetScope'] ?? 'all'),
                $data['courseId'] ?? null,
            ),
            201,
        );
    }

    public function deleteSatisfactionQuestion(string $questionId): JsonResponse
    {
        $this->coreDataService->deleteSatisfactionQuestion($questionId);

        return response()->json(status: 204);
    }

    public function storeSatisfactionResponses(Request $request): JsonResponse
    {
        $data = $request->validate([
            'responses' => ['required', 'array'],
            'responses.*.courseId' => ['required', 'string'],
            'responses.*.questionId' => ['required', 'string'],
            'responses.*.loginCode' => ['required', 'string'],
            'responses.*.studentName' => ['required', 'string'],
            'responses.*.ratingValue' => ['nullable', 'integer'],
            'responses.*.textValue' => ['nullable', 'string'],
        ]);

        if ($request->is('api/public/*')) {
            $loginCodes = collect($data['responses'])
                ->pluck('loginCode')
                ->map(fn ($loginCode) => trim((string) $loginCode))
                ->filter()
                ->unique()
                ->values();

            if ($loginCodes->count() !== 1) {
                throw ValidationException::withMessages([
                    'responses' => ['لا يمكن إرسال رضا لأكثر من حساب في الطلب نفسه.'],
                ]);
            }

            $this->assertAuthenticatedSubmitter($request, $loginCodes->first());
        }

        return response()->json($this->coreDataService->submitSatisfactionResponses($data['responses']));
    }

    public function storeFinalExamQuestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branchCode' => ['required', 'string'],
            'prompt' => ['required', 'string'],
            'type' => ['required', 'string'],
            'options' => ['sometimes', 'array'],
            'options.*' => ['string'],
            'allowFile' => ['required', 'boolean'],
            'points' => ['required', 'integer'],
            'correctAnswer' => ['required', 'string'],
        ]);

        return response()->json($this->coreDataService->addFinalExamQuestion($data['branchCode'], $data), 201);
    }

    public function deleteFinalExamQuestion(string $questionId): JsonResponse
    {
        $this->coreDataService->deleteFinalExamQuestion($questionId);

        return response()->json(status: 204);
    }

    public function updateFinalExamQuestion(Request $request, string $questionId): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string'],
            'type' => ['required', 'string'],
            'options' => ['sometimes', 'array'],
            'options.*' => ['string'],
            'allowFile' => ['required', 'boolean'],
            'points' => ['required', 'integer'],
            'correctAnswer' => ['required', 'string'],
        ]);

        $this->coreDataService->updateFinalExamQuestion($questionId, $data);

        return response()->json(status: 204);
    }

    public function updateFinalExamSetting(Request $request, string $branchCode): JsonResponse
    {
        $data = $request->validate([
            'isEnabled' => ['required', 'boolean'],
            'closesAt' => ['nullable', 'string'],
            'notificationTemplate' => ['sometimes', 'nullable', 'string'],
        ]);

        $this->coreDataService->updateFinalExamSetting($branchCode, $data['isEnabled'], $data['closesAt'] ?? null, $data['notificationTemplate'] ?? null);

        return response()->json(status: 204);
    }

    public function updateFinalExamNotificationTemplate(Request $request, string $branchCode): JsonResponse
    {
        $data = $request->validate([
            'notificationTemplate' => ['required', 'string'],
        ]);

        $this->coreDataService->updateFinalExamNotificationTemplate($branchCode, $data['notificationTemplate']);

        return response()->json(status: 204);
    }

    public function submitFinalExam(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branchCode' => ['required', 'string', 'in:male,female'],
            'studentName' => ['required', 'string', 'max:255'],
            'loginCode' => ['required', 'string', 'max:255'],
            'answers' => ['required', 'array', 'max:500'],
            'answers.*.questionId' => ['required', 'string', 'max:100'],
            'answers.*.value' => ['nullable', 'string', 'max:20000'],
            'answers.*.fileName' => ['nullable', 'string', 'max:255'],
            'answers.*.fileType' => ['nullable', 'string', 'max:100'],
            'answers.*.fileDataUrl' => ['nullable', 'string'],
            'answers.*.files' => ['prohibited'],
        ]);

        $this->validateAnswerFilePayloads($data['answers']);

        if ($request->is('api/public/*')) {
            $this->assertAuthenticatedSubmitter($request, $data['loginCode']);
        }

        return response()->json($this->coreDataService->submitFinalExam($data), 201);
    }

    private function assertAuthenticatedSubmitter(Request $request, string $loginCode): void
    {
        $user = $request->user();
        $allowedRoles = ['student', 'trainee'];
        $userLoginCode = trim((string) ($user?->login_code ?? ''));
        $targetLoginCode = trim($loginCode);

        if (! $user || ! in_array((string) $user->role, $allowedRoles, true) || $userLoginCode === '' || $userLoginCode !== $targetLoginCode) {
            throw ValidationException::withMessages([
                'loginCode' => ['هذا الإرسال متاح فقط لصاحب الحساب المسجل.'],
            ]);
        }
    }

    public function copyFinalExamQuestions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'move' => ['required', 'boolean'],
        ]);

        $this->coreDataService->copyFinalExamQuestions($data['from'], $data['to'], $data['move']);

        return response()->json(status: 204);
    }

    public function setFinalExamManualScore(Request $request, string $submissionId): JsonResponse
    {
        $data = $request->validate([
            'score' => ['nullable', 'numeric'],
        ]);

        $this->coreDataService->setFinalExamManualScore($submissionId, $data['score'] ?? null);

        return response()->json(status: 204);
    }

    public function setAssessmentManualScore(Request $request, string $submissionId): JsonResponse
    {
        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->coreDataService->setAssessmentManualScore($submissionId, isset($data['score']) ? (float) $data['score'] : null);

        return response()->json(status: 204);
    }

    public function storeDashboardAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'loginCode' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:admin,male_manager,female_manager,student,reciter,trainee'],
        ]);

        $user = $this->coreDataService->createDashboardAccount($data['name'], $data['loginCode'], $data['role']);

        return response()->json([
            'id' => $user->id,
            'name' => $user->full_name,
            'loginCode' => $user->login_code,
            'role' => $user->role,
        ], 201);
    }

    public function deleteDashboardAccount(string $accountId): JsonResponse
    {
        $this->coreDataService->deleteDashboardAccount($accountId);

        return response()->json(status: 204);
    }

    public function restoreDashboardBackup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'snapshot' => ['required', 'array'],
            'confirm' => ['accepted'],
        ]);

        return response()->json($this->coreDataService->restoreDashboardSnapshot($data['snapshot']));
    }

    public function exportDashboardBackup(): BinaryFileResponse
    {
        $path = storage_path('app/momars-backup-'.now()->format('Y-m-d-H-i-s').'.zip');

        $this->coreDataService->createDashboardBackupZip($path);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function restoreDashboardBackupFile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'backup' => ['required', 'file', 'mimes:zip', 'max:512000'],
            'confirm' => ['accepted'],
        ]);

        return response()->json($this->coreDataService->restoreDashboardBackupZip($data['backup']->getRealPath()));
    }

    public function transferStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'studentId' => ['required', 'string', 'max:100'],
            'targetReciterId' => ['required', 'string', 'max:100'],
        ]);

        $this->coreDataService->transferStudentToReciter($data['studentId'], $data['targetReciterId']);

        return response()->json(status: 204);
    }

    public function storeActivityLog(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string'],
            'target' => ['required', 'string'],
            'status' => ['required', 'string'],
            'details' => ['nullable', 'string'],
            'actorName' => ['nullable', 'string'],
            'actorRole' => ['nullable', 'string'],
        ]);

        return response()->json($this->coreDataService->addActivityLog($data), 201);
    }

    public function notifications(): JsonResponse
    {
        return response()->json($this->coreDataService->loadNotifications());
    }

    public function storeNotification(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'message' => ['required', 'string'],
            'targetBranchId' => ['nullable', 'string'],
            'targetLoginIds' => ['sometimes', 'array'],
            'targetLoginIds.*' => ['string'],
            'createdByName' => ['nullable', 'string'],
            'createdByRole' => ['nullable', 'string'],
        ]);

        return response()->json($this->coreDataService->addNotification($data), 201);
    }

    public function deleteNotification(string $notificationId): JsonResponse
    {
        $this->coreDataService->deleteNotification($notificationId);

        return response()->json(status: 204);
    }

    public function trainingMaterials(): JsonResponse
    {
        return response()->json($this->coreDataService->loadTrainingMaterials());
    }

    public function storeTrainingMaterial(Request $request): JsonResponse
    {
        $data = $this->validateTrainingMaterialPayload($request);
        $attachments = $this->extractTrainingMaterialAttachments($request);

        return response()->json(
            $this->coreDataService->createTrainingMaterial(
                $data['title'],
                (string) ($data['description'] ?? ''),
                $data['branchId'] ?? null,
                $attachments,
            ),
            201,
        );
    }

    public function updateTrainingMaterial(Request $request, string $materialId): JsonResponse
    {
        $data = $this->validateTrainingMaterialPayload($request, true);
        $attachments = $this->extractTrainingMaterialAttachments($request, true);

        return response()->json(
            $this->coreDataService->updateTrainingMaterial(
                $materialId,
                $data['title'],
                (string) ($data['description'] ?? ''),
                $data['branchId'] ?? null,
                $attachments,
            ),
        );
    }

    public function deleteTrainingMaterial(string $materialId): JsonResponse
    {
        $this->coreDataService->deleteTrainingMaterial($materialId);

        return response()->json(status: 204);
    }

    private function validateTrainingMaterialPayload(Request $request, bool $allowExistingAttachments = false): array
    {
        return $request->validate([
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'branchId' => ['nullable', 'string'],
            'attachments' => ['sometimes', 'array', 'min:1'],
            'attachments.*.id' => $allowExistingAttachments ? ['sometimes', 'string'] : ['prohibited'],
            'attachments.*.label' => ['nullable', 'string'],
            'attachments.*.url' => ['nullable', 'url', 'max:2048'],
            'attachments.*.file' => [
                'nullable',
                'file',
                'mimetypes:'.implode(',', self::TRAINING_ATTACHMENT_MIME_TYPES),
                'max:20480',
            ],
            'files' => ['sometimes', 'array', 'min:1'],
            'files.*' => ['file', 'mimetypes:'.implode(',', self::TRAINING_ATTACHMENT_MIME_TYPES), 'max:20480'],
        ]);
    }

    private function validateAnswerFilePayloads(array $answers, string $rootKey = 'answers'): void
    {
        $errors = [];

        foreach ($answers as $index => $answer) {
            $fileDataUrl = trim((string) ($answer['fileDataUrl'] ?? ''));

            if ($fileDataUrl === '') {
                continue;
            }

            $fileType = strtolower(trim((string) ($answer['fileType'] ?? '')));
            $field = "$rootKey.$index.fileDataUrl";

            if (! in_array($fileType, self::ANSWER_ATTACHMENT_MIME_TYPES, true)) {
                $errors[$field] = ['نوع المرفق غير مسموح.'];
                continue;
            }

            if (strlen($fileDataUrl) > self::MAX_ANSWER_DATA_URL_LENGTH) {
                $errors[$field] = ['حجم المرفق أكبر من الحد المسموح.'];
                continue;
            }

            $expectedPrefix = 'data:'.$fileType.';base64,';

            if (! str_starts_with(strtolower($fileDataUrl), $expectedPrefix)) {
                $errors[$field] = ['صيغة المرفق غير صالحة.'];
                continue;
            }

            $payload = substr($fileDataUrl, strlen($expectedPrefix));

            if ($payload === '' || ! preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $payload) || base64_decode($payload, true) === false) {
                $errors[$field] = ['بيانات المرفق غير صالحة.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function extractTrainingMaterialAttachments(Request $request, bool $allowExistingAttachments = false): array
    {
        $attachments = [];

        foreach (($request->input('attachments', []) ?: []) as $index => $attachment) {
            $file = data_get($request->file('attachments', []), $index.'.file');
            $attachmentId = trim((string) ($attachment['id'] ?? ''));
            $url = trim((string) ($attachment['url'] ?? ''));

            if (! $file && $url === '' && (! $allowExistingAttachments || $attachmentId === '')) {
                continue;
            }

            $attachments[] = [
                'id' => $attachmentId,
                'label' => (string) ($attachment['label'] ?? ''),
                'file' => $file,
                'url' => $url,
            ];
        }

        if ($attachments === []) {
            $attachments = collect($request->file('files', []))
                ->filter()
                ->map(fn ($file) => [
                    'id' => '',
                    'label' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'file' => $file,
                ])
                ->values()
                ->all();
        }

        return $attachments;
    }

    public function rolePermissions(): JsonResponse
    {
        return response()->json($this->coreDataService->loadRolePermissions());
    }

    public function setRolePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:male_manager,female_manager'],
            'key' => ['required', 'string', 'max:100'],
            'isEnabled' => ['required', 'boolean'],
        ]);

        $this->coreDataService->setRolePermission($data['role'], $data['key'], $data['isEnabled']);

        return response()->json(status: 204);
    }

    public function storeStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'loginId' => ['required', 'string', 'max:255'],
            'branchId' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $student = $this->coreDataService->createStudent(
            $data['name'],
            $data['loginId'],
            $data['branchId'],
            $data['note'] ?? '',
        );

        return response()->json([
            'id' => $student->id,
            'name' => $student->full_name,
            'loginId' => $student->login_code,
            'branchId' => $student->branch?->code,
            'note' => $student->note,
            'isCertified' => $student->is_certified,
        ], 201);
    }

    public function updateStudent(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'loginCode' => ['sometimes', 'string', 'max:255'],
            'branchId' => ['sometimes', 'string', 'max:100'],
            'note' => ['sometimes', 'string', 'max:2000'],
            'isCertified' => ['sometimes', 'boolean'],
            'completedParts' => ['sometimes', 'array'],
            'completedParts.*' => ['integer', 'min:1', 'max:30'],
        ]);

        $student = $this->coreDataService->updateStudent($student, $data);

        return response()->json([
            'id' => $student->id,
            'name' => $student->full_name,
            'loginId' => $student->login_code,
            'branchId' => $student->branch?->code,
            'note' => $student->note,
            'isCertified' => $student->is_certified,
            'completedParts' => $student->parts->pluck('part_number')->sort()->values()->all(),
        ]);
    }

    public function deleteStudent(Student $student): JsonResponse
    {
        $this->coreDataService->deleteStudent($student);

        return response()->json(status: 204);
    }

    public function toggleStudentPart(Request $request, Student $student, int $partNumber): JsonResponse
    {
        $data = $request->validate([
            'reciterId' => ['nullable', 'string', 'max:100'],
            'shouldMarkComplete' => ['required', 'boolean'],
        ]);

        $this->assertCanToggleStudentPart($request, $student, $data['reciterId'] ?? null);

        $this->coreDataService->toggleStudentPart($student->id, $data['reciterId'] ?? null, $partNumber, $data['shouldMarkComplete']);

        return response()->json(status: 204);
    }

    public function storeReciter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentLoginCode' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'loginCode' => ['required', 'string', 'max:255'],
            'branchId' => ['required', 'string', 'max:100'],
            'linkedStudentIds' => ['sometimes', 'array'],
            'linkedStudentIds.*' => ['string', 'max:100'],
        ]);

        $reciter = $this->coreDataService->saveReciter(
            $data['currentLoginCode'] ?? null,
            $data['name'],
            $data['loginCode'],
            $data['branchId'],
            $data['linkedStudentIds'] ?? [],
        );

        return response()->json([
            'id' => $reciter->id,
            'name' => $reciter->full_name,
            'loginCode' => $reciter->user?->login_code,
            'branchId' => $reciter->branch?->code,
            'studentIds' => $reciter->students->pluck('id')->all(),
        ]);
    }

    public function showReciterByLoginCode(Request $request, string $loginCode): Response
    {
        $this->assertCanViewReciterAccount($request, $loginCode);

        $payload = $this->coreDataService->getReciterAccountByLoginCode($loginCode);

        return $this->jsonOrNull($payload);
    }

    public function deleteReciterByLoginCode(string $loginCode): JsonResponse
    {
        $reciterId = $this->coreDataService->deleteReciterByLoginCode($loginCode);

        return response()->json(['id' => $reciterId]);
    }

    public function getAssignedReciter(Request $request, string $loginCode): Response
    {
        $this->assertCanViewAssignedReciter($request, $loginCode);

        $payload = $this->coreDataService->getStudentAssignedReciterByLoginCode($loginCode);

        return $this->jsonOrNull($payload);
    }

    private function assertCanViewReciterAccount(Request $request, string $loginCode): void
    {
        $user = $request->user();
        $role = (string) ($user?->role ?? '');

        if (in_array($role, ['admin', 'male_manager', 'female_manager'], true)) {
            return;
        }

        if ($role === 'reciter' && trim((string) $user?->login_code) === trim($loginCode)) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN, 'غير مصرح لك بعرض بيانات هذا المقرئ.');
    }

    private function assertCanViewAssignedReciter(Request $request, string $loginCode): void
    {
        $user = $request->user();
        $role = (string) ($user?->role ?? '');
        $targetLoginCode = trim($loginCode);

        if (in_array($role, ['admin', 'male_manager', 'female_manager'], true)) {
            return;
        }

        if (in_array($role, ['student', 'trainee'], true) && trim((string) $user?->login_code) === $targetLoginCode) {
            return;
        }

        if ($role === 'reciter') {
            $reciter = Reciter::query()->where('user_id', $user?->getAuthIdentifier())->first();
            $isLinked = $reciter && $reciter->students()->where('students.login_code', $targetLoginCode)->exists();

            if ($isLinked) {
                return;
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'غير مصرح لك بعرض بيانات ربط هذا الطالب.');
    }

    private function assertCanToggleStudentPart(Request $request, Student $student, ?string $reciterId): void
    {
        $user = $request->user();
        $role = (string) ($user?->role ?? '');

        if ($role === 'admin') {
            return;
        }

        if (in_array($role, ['male_manager', 'female_manager'], true)) {
            $permissions = $this->coreDataService->loadRolePermissions()[$role] ?? [];

            if (($permissions['edit_student'] ?? false) === true) {
                return;
            }
        }

        if ($role === 'reciter') {
            $reciter = Reciter::query()->where('user_id', $user?->getAuthIdentifier())->first();
            $isSameReciter = $reciter && (! $reciterId || $reciter->id === $reciterId);
            $isLinkedStudent = $reciter && $reciter->students()->whereKey($student->getKey())->exists();

            if ($isSameReciter && $isLinkedStudent) {
                return;
            }
        }

        abort(Response::HTTP_FORBIDDEN, 'غير مصرح لك بتعديل أجزاء هذا الطالب.');
    }

    private function jsonOrNull(?array $payload): Response
    {
        if ($payload === null) {
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        return response()->json($payload);
    }
}
