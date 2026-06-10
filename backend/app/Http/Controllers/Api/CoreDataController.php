<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EditorAsset;
use App\Models\Student;
use App\Services\CoreDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CoreDataController extends Controller
{
    public function __construct(private readonly CoreDataService $coreDataService)
    {
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
            'image' => ['required', 'image', 'max:5120'],
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
            'url' => $request->getSchemeAndHttpHost().'/storage/'.ltrim($media->getPathRelativeToRoot(), '/'),
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

        abort_unless($media->model_type === \App\Models\TrainingMaterial::class, 404);

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
            'courseId' => ['required', 'string'],
            'assessmentType' => ['required', 'string'],
            'studentName' => ['required', 'string'],
            'loginId' => ['required', 'string'],
            'answers' => ['required', 'array'],
            'answers.*.questionId' => ['required', 'string'],
            'answers.*.value' => ['nullable', 'string'],
            'answers.*.fileName' => ['nullable', 'string'],
            'answers.*.fileType' => ['nullable', 'string'],
            'answers.*.fileDataUrl' => ['nullable', 'string'],
        ]);

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
            'courseId' => ['required', 'string'],
            'assessmentType' => ['required', 'string'],
            'submissions' => ['required', 'array'],
            'submissions.*.studentName' => ['required', 'string'],
            'submissions.*.loginId' => ['required', 'string'],
            'submissions.*.manualScore' => ['nullable', 'numeric'],
            'submissions.*.answers' => ['required', 'array'],
            'submissions.*.answers.*.questionId' => ['required', 'string'],
            'submissions.*.answers.*.value' => ['nullable', 'string'],
            'submissions.*.answers.*.fileName' => ['nullable', 'string'],
            'submissions.*.answers.*.fileType' => ['nullable', 'string'],
            'submissions.*.answers.*.fileDataUrl' => ['nullable', 'string'],
        ]);

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
            'branchCode' => ['required', 'string'],
            'studentName' => ['required', 'string'],
            'loginCode' => ['required', 'string'],
            'answers' => ['required', 'array'],
            'answers.*.questionId' => ['required', 'string'],
            'answers.*.value' => ['nullable', 'string'],
            'answers.*.fileName' => ['nullable', 'string'],
            'answers.*.fileType' => ['nullable', 'string'],
            'answers.*.fileDataUrl' => ['nullable', 'string'],
        ]);

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
            'name' => ['required', 'string'],
            'loginCode' => ['required', 'string'],
            'role' => ['required', 'string'],
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

    public function transferStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'studentId' => ['required', 'string'],
            'targetReciterId' => ['required', 'string'],
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
                'max:20480',
            ],
            'files' => ['sometimes', 'array', 'min:1'],
            'files.*' => ['file', 'max:20480'],
        ]);
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
            'role' => ['required', 'string'],
            'key' => ['required', 'string'],
            'isEnabled' => ['required', 'boolean'],
        ]);

        $this->coreDataService->setRolePermission($data['role'], $data['key'], $data['isEnabled']);

        return response()->json(status: 204);
    }

    public function storeStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'loginId' => ['required', 'string'],
            'branchId' => ['required', 'string'],
            'note' => ['nullable', 'string'],
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
            'name' => ['sometimes', 'string'],
            'loginCode' => ['sometimes', 'string'],
            'branchId' => ['sometimes', 'string'],
            'note' => ['sometimes', 'string'],
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
            'reciterId' => ['nullable', 'string'],
            'shouldMarkComplete' => ['required', 'boolean'],
        ]);

        $this->coreDataService->toggleStudentPart($student->id, $data['reciterId'] ?? null, $partNumber, $data['shouldMarkComplete']);

        return response()->json(status: 204);
    }

    public function storeReciter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentLoginCode' => ['nullable', 'string'],
            'name' => ['required', 'string'],
            'loginCode' => ['required', 'string'],
            'branchId' => ['required', 'string'],
            'linkedStudentIds' => ['sometimes', 'array'],
            'linkedStudentIds.*' => ['string'],
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

    public function showReciterByLoginCode(string $loginCode): Response
    {
        $payload = $this->coreDataService->getReciterAccountByLoginCode($loginCode);

        return $this->jsonOrNull($payload);
    }

    public function deleteReciterByLoginCode(string $loginCode): JsonResponse
    {
        $reciterId = $this->coreDataService->deleteReciterByLoginCode($loginCode);

        return response()->json(['id' => $reciterId]);
    }

    public function getAssignedReciter(string $loginCode): Response
    {
        $payload = $this->coreDataService->getStudentAssignedReciterByLoginCode($loginCode);

        return $this->jsonOrNull($payload);
    }

    private function jsonOrNull(array|null $payload): Response
    {
        if ($payload === null) {
            return response('null', 200, ['Content-Type' => 'application/json']);
        }

        return response()->json($payload);
    }
}
