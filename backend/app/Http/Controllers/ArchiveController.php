<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\Branch;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArchiveController extends Controller
{
    public function index()
    {
        return response()->json(Archive::orderByDesc('created_at')->get());
    }

    public function searchStudents(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $term = trim($validated['name']);

        $students = Student::query()
            ->select([
                'students.id',
                'students.full_name',
                'students.login_code',
                'students.archived_login_code',
                'students.created_at',
                'students.archive_id',
                'archives.name as archive_name',
            ])
            ->leftJoin('archives', 'archives.id', '=', 'students.archive_id')
            ->with('branch')
            ->whereNotNull('students.archive_id')
            ->where('students.full_name', 'like', '%' . $term . '%')
            ->orderBy('students.full_name')
            ->orderByDesc('students.created_at')
            ->limit(100)
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'login_code' => $this->resolveStudentDisplayLoginCode($student),
                'created_at' => optional($student->created_at)?->toISOString(),
                'archive_id' => $student->archive_id,
                'archive_name' => $student->archive_name,
                'branch' => $student->branch ? [
                    'id' => $student->branch->code,
                    'name' => $student->branch->name,
                ] : null,
            ])
            ->values();

        return response()->json($students);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:archives',
            'courses_count' => 'required|integer|min:0|max:1000000',
            'batch_type' => 'required|in:male,female,all',
        ]);

        $archive = Archive::create([
            'name' => trim($validated['name']),
            'courses_count' => $validated['courses_count'],
            'batch_type' => $validated['batch_type'],
        ]);

        return response()->json($archive, 201);
    }

    public function show(string $archiveId)
    {
        $archive = Archive::findOrFail($archiveId);
        $students = Student::query()
            ->where('archive_id', $archiveId)
            ->with(['branch', 'parts'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'login_code' => $this->resolveStudentDisplayLoginCode($student),
                'created_at' => optional($student->created_at)?->toISOString(),
                'archive_id' => $student->archive_id,
                'branch' => $student->branch ? [
                    'id' => $student->branch->code,
                    'name' => $student->branch->name,
                ] : null,
            ])
            ->values();
        // optionally return courses and reciters archived in this batch
        $courses = DB::table('courses')->where('archive_id', $archiveId)->orderBy('sort_order')->get();
        
        return response()->json([
            'archive' => $archive,
            'students' => $students,
            'courses' => $courses,
        ]);
    }

    public function destroy(string $archiveId)
    {
        Archive::findOrFail($archiveId);

        DB::transaction(function () use ($archiveId) {
            $studentIds = DB::table('students')->where('archive_id', $archiveId)->pluck('id')->values();

            DB::table('course_submission_answers')->where('archive_id', $archiveId)->delete();
            DB::table('course_submissions')->where('archive_id', $archiveId)->delete();
            DB::table('course_attendance')->where('archive_id', $archiveId)->delete();
            DB::table('course_questions')->where('archive_id', $archiveId)->delete();
            DB::table('satisfaction_responses')->where('archive_id', $archiveId)->delete();
            DB::table('satisfaction_questions')->where('archive_id', $archiveId)->delete();
            DB::table('final_exam_submission_answers')->where('archive_id', $archiveId)->delete();
            DB::table('final_exam_submissions')->where('archive_id', $archiveId)->delete();
            DB::table('final_exam_questions')->where('archive_id', $archiveId)->delete();
            DB::table('courses')->where('archive_id', $archiveId)->delete();
            DB::table('notifications')->where('archive_id', $archiveId)->delete();
            DB::table('reciters')->where('archive_id', $archiveId)->delete();

            if ($studentIds->isNotEmpty()) {
                DB::table('reciter_students')->whereIn('student_id', $studentIds)->delete();
                DB::table('student_parts')->whereIn('student_id', $studentIds)->delete();
            }

            DB::table('students')->where('archive_id', $archiveId)->delete();
            DB::table('archives')->where('id', $archiveId)->delete();

            cache()->forget('dashboard:snapshot');
            cache()->forget('dashboard:notifications');
        });

        return response()->json(['message' => 'Archive deleted successfully']);
    }

    public function studentDetail(string $archiveId, string $studentId)
    {
        Archive::findOrFail($archiveId);

        $student = Student::query()
            ->where('archive_id', $archiveId)
            ->with(['branch', 'parts'])
            ->whereKey($studentId)
            ->firstOrFail();

        $branchCode = $student->branch?->code ?? 'male';
        $recordLoginCodes = collect([
            trim((string) $student->login_code),
            trim((string) $student->archived_login_code),
        ])->filter()->unique()->values();

        $courses = collect(DB::table('courses')
            ->where('archive_id', $archiveId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get());

        $courseIds = $courses->pluck('id')->filter()->values();

        $submissions = collect();
        $submissionAnswers = collect();
        $attendance = collect();
        $attendanceCount = 0;
        $satisfactionResponses = collect();
        $satisfactionQuestions = collect();

        if ($courseIds->isNotEmpty()) {
            $submissions = collect(DB::table('course_submissions')
                ->where('archive_id', $archiveId)
                ->whereIn('login_code', $recordLoginCodes)
                ->whereIn('course_id', $courseIds)
                ->orderByDesc('submitted_at')
                ->get());

            $submissionAnswers = collect(DB::table('course_submission_answers')
                ->where('archive_id', $archiveId)
                ->whereIn('submission_id', $submissions->pluck('id')->filter()->values())
                ->get())
                ->groupBy('submission_id');

            $attendanceRecords = collect(DB::table('course_attendance')
                ->where('archive_id', $archiveId)
                ->whereIn('login_code', $recordLoginCodes)
                ->whereIn('course_id', $courseIds)
                ->orderByDesc('created_at')
                ->get());
            $attendanceCount = $attendanceRecords->count();
            $attendance = $attendanceRecords->keyBy('course_id');

            $satisfactionResponses = collect(DB::table('satisfaction_responses')
                ->where('archive_id', $archiveId)
                ->whereIn('login_code', $recordLoginCodes)
                ->whereIn('course_id', $courseIds)
                ->orderByDesc('submitted_at')
                ->get());

            $satisfactionQuestions = collect(DB::table('satisfaction_questions')
                ->where('archive_id', $archiveId)
                ->whereIn('id', $satisfactionResponses->pluck('question_id')->filter()->values())
                ->get())
                ->keyBy('id');
        }

        $submissionsByCourse = $submissions->groupBy('course_id');
        $satisfactionByCourse = $satisfactionResponses->groupBy('course_id');

        $finalExamSubmission = DB::table('final_exam_submissions')
            ->where('archive_id', $archiveId)
            ->whereIn('login_code', $recordLoginCodes)
            ->first();

        $finalExamAnswers = collect();

        if ($finalExamSubmission) {
            $finalExamAnswers = collect(DB::table('final_exam_submission_answers')
                ->where('archive_id', $archiveId)
                ->where('submission_id', $finalExamSubmission->id)
                ->get())
                ->values();
        }

        $courseDetails = $courses->map(function ($course) use ($branchCode, $submissionsByCourse, $submissionAnswers, $attendance, $satisfactionByCourse, $satisfactionQuestions) {
            $courseSubmissions = collect($submissionsByCourse->get($course->id, []));
            $preSubmission = $courseSubmissions->firstWhere('assessment_type', 'pre');
            $postSubmission = $courseSubmissions->firstWhere('assessment_type', 'post');
            $taskSubmission = $courseSubmissions->firstWhere('assessment_type', 'tasks');
            $attendanceRecord = $attendance->get($course->id);
            $courseSatisfactionResponses = collect($satisfactionByCourse->get($course->id, []));

            return [
                'id' => $course->id,
                'title' => $course->title,
                'entityType' => $course->entity_type === 'task' ? 'task' : 'course',
                'branchAvailability' => [
                    'pre' => $branchCode === 'female' ? (bool) $course->female_pre_enabled : (bool) $course->male_pre_enabled,
                    'post' => $branchCode === 'female' ? (bool) $course->female_post_enabled : (bool) $course->male_post_enabled,
                    'tasks' => $branchCode === 'female' ? (bool) $course->female_tasks_enabled : (bool) $course->male_tasks_enabled,
                ],
                'attendance' => $attendanceRecord ? [
                    'isPresent' => true,
                    'source' => $attendanceRecord->source,
                    'createdAt' => (string) $attendanceRecord->created_at,
                ] : [
                    'isPresent' => false,
                    'source' => null,
                    'createdAt' => null,
                ],
                'pre' => $this->normalizeArchivedSubmission($preSubmission, $submissionAnswers),
                'post' => $this->normalizeArchivedSubmission($postSubmission, $submissionAnswers),
                'tasks' => $this->normalizeArchivedSubmission($taskSubmission, $submissionAnswers),
                'satisfactionResponses' => $courseSatisfactionResponses->map(function ($response) use ($satisfactionQuestions) {
                    $question = $satisfactionQuestions->get($response->question_id);

                    return [
                        'id' => $response->id,
                        'prompt' => $question->prompt ?? '',
                        'type' => $question->type ?? 'rating',
                        'ratingValue' => $response->rating_value !== null ? (int) $response->rating_value : null,
                        'textValue' => $response->text_value ?? '',
                        'submittedAt' => (string) $response->submitted_at,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return response()->json([
            'archive' => [
                'id' => $archiveId,
                'name' => Archive::query()->whereKey($archiveId)->value('name'),
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
                'note' => $student->note,
                'createdAt' => optional($student->created_at)?->toISOString(),
            ],
            'summary' => [
                'preTests' => $submissions->where('assessment_type', 'pre')->count(),
                'postTests' => $submissions->where('assessment_type', 'post')->count(),
                'tasks' => $submissions->where('assessment_type', 'tasks')->count(),
                'attendance' => $attendanceCount,
            ],
            'courses' => $courseDetails,
            'finalExam' => $finalExamSubmission ? [
                'branchCode' => $finalExamSubmission->branch_code,
                'manualScore' => $finalExamSubmission->manual_score !== null ? (float) $finalExamSubmission->manual_score : null,
                'submittedAt' => (string) $finalExamSubmission->submitted_at,
                'answers' => $finalExamAnswers->map(fn ($answer) => [
                    'questionId' => $answer->question_id,
                    'value' => $answer->answer_text ?? '',
                    'fileName' => $answer->file_name,
                    'fileType' => $answer->file_type,
                ])->values()->all(),
            ] : null,
        ]);
    }

    public function assignStudent(Request $request, string $archiveId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Archive::findOrFail($archiveId);

        $branch = Branch::query()
            ->where('code', 'male')
            ->first() ?? Branch::query()->orderBy('created_at')->first();

        abort_unless($branch, 422, 'لا يوجد فرع صالح لإضافة الطالب المؤرشف.');

        $loginCode = $this->generateArchivedLoginCode();

        $student = Student::query()->create([
            'full_name' => trim($validated['name']),
            'login_code' => $loginCode,
            'branch_id' => $branch->id,
            'note' => 'أضيف يدويًا داخل الأرشيف',
            'is_certified' => false,
            'created_at' => now(),
            'archive_id' => $archiveId,
        ]);

        return response()->json([
            'message' => 'Student archived successfully',
            'student' => $student,
        ]);
    }

    public function archiveAll(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:archives,name',
            'batch_type' => 'nullable|in:male,female,all',
        ]);

        $archive = Archive::create([
            'name' => trim($validated['name']),
            'batch_type' => $validated['batch_type'] ?? 'all',
        ]);
        $archiveId = $archive->id;

        DB::transaction(function () use ($archiveId) {
            $activeStudents = DB::table('students')
                ->whereNull('archive_id')
                ->select(['id', 'login_code'])
                ->orderBy('created_at')
                ->get();
            $studentIds = $activeStudents->pluck('id')->values();
            $studentLoginCodes = $activeStudents->pluck('login_code')->filter()->values();
            $activeCourses = DB::table('courses')->whereNull('archive_id')->get();
            $courseIds = $activeCourses->pluck('id')->values();
            $courseSubmissions = DB::table('course_submissions')
                ->whereNull('archive_id')
                ->whereIn('course_id', $courseIds)
                ->whereIn('login_code', $studentLoginCodes)
                ->get();
            $courseSubmissionIds = $courseSubmissions->pluck('id')->values();
            $finalExamSubmissions = DB::table('final_exam_submissions')
                ->whereNull('archive_id')
                ->whereIn('login_code', $studentLoginCodes)
                ->get();
            $finalExamSubmissionIds = $finalExamSubmissions->pluck('id')->values();

            $courseIdMap = [];
            foreach ($activeCourses as $course) {
                $newCourseId = (string) Str::uuid();
                $courseIdMap[$course->id] = $newCourseId;
                $payload = (array) $course;
                $payload['id'] = $newCourseId;
                $payload['archive_id'] = $archiveId;
                DB::table('courses')->insert($payload);
            }

            $courseQuestionIdMap = [];
            $activeCourseQuestions = DB::table('course_questions')
                ->whereNull('archive_id')
                ->whereIn('course_id', $courseIds)
                ->get();
            foreach ($activeCourseQuestions as $question) {
                $newQuestionId = (string) Str::uuid();
                $courseQuestionIdMap[$question->id] = $newQuestionId;
                $payload = (array) $question;
                $payload['id'] = $newQuestionId;
                $payload['course_id'] = $courseIdMap[$question->course_id];
                $payload['archive_id'] = $archiveId;
                DB::table('course_questions')->insert($payload);
            }

            $satisfactionQuestionIdMap = [];
            $activeSatisfactionQuestions = DB::table('satisfaction_questions')
                ->whereNull('archive_id')
                ->get();
            foreach ($activeSatisfactionQuestions as $question) {
                $newQuestionId = (string) Str::uuid();
                $satisfactionQuestionIdMap[$question->id] = $newQuestionId;
                $payload = (array) $question;
                $payload['id'] = $newQuestionId;
                $payload['course_id'] = $question->course_id
                    ? ($courseIdMap[$question->course_id] ?? $question->course_id)
                    : null;
                $payload['archive_id'] = $archiveId;
                DB::table('satisfaction_questions')->insert($payload);
            }

            $finalQuestionIdMap = [];
            $activeFinalQuestions = DB::table('final_exam_questions')
                ->whereNull('archive_id')
                ->get();
            foreach ($activeFinalQuestions as $question) {
                $newQuestionId = (string) Str::uuid();
                $finalQuestionIdMap[$question->id] = $newQuestionId;
                $payload = (array) $question;
                $payload['id'] = $newQuestionId;
                $payload['archive_id'] = $archiveId;
                DB::table('final_exam_questions')->insert($payload);
            }

            $archivedLoginCodes = [];
            foreach ($activeStudents as $student) {
                $originalLoginCode = trim((string) $student->login_code);
                $archivedLoginCode = $this->generateArchivedLoginCode();
                $archivedLoginCodes[$originalLoginCode] = $archivedLoginCode;

                DB::table('students')
                    ->where('id', $student->id)
                    ->update([
                        'archive_id' => $archiveId,
                        'archived_login_code' => $originalLoginCode !== '' ? $originalLoginCode : null,
                        'login_code' => $archivedLoginCode,
                    ]);
            }

            foreach ($courseSubmissions as $submission) {
                DB::table('course_submissions')
                    ->where('id', $submission->id)
                    ->update([
                        'course_id' => $courseIdMap[$submission->course_id] ?? $submission->course_id,
                        'login_code' => $archivedLoginCodes[$submission->login_code] ?? $submission->login_code,
                        'archive_id' => $archiveId,
                    ]);
            }

            $courseSubmissionAnswers = DB::table('course_submission_answers')
                ->whereNull('archive_id')
                ->whereIn('submission_id', $courseSubmissionIds)
                ->get();
            foreach ($courseSubmissionAnswers as $answer) {
                DB::table('course_submission_answers')
                    ->where('id', $answer->id)
                    ->update([
                        'question_id' => $courseQuestionIdMap[$answer->question_id] ?? $answer->question_id,
                        'archive_id' => $archiveId,
                    ]);
            }

            $attendanceRecords = DB::table('course_attendance')
                ->whereNull('archive_id')
                ->whereIn('login_code', $studentLoginCodes)
                ->get();
            foreach ($attendanceRecords as $attendance) {
                DB::table('course_attendance')
                    ->where('id', $attendance->id)
                    ->update([
                        'course_id' => $courseIdMap[$attendance->course_id] ?? $attendance->course_id,
                        'login_code' => $archivedLoginCodes[$attendance->login_code] ?? $attendance->login_code,
                        'archive_id' => $archiveId,
                    ]);
            }

            $satisfactionResponses = DB::table('satisfaction_responses')
                ->whereNull('archive_id')
                ->whereIn('login_code', $studentLoginCodes)
                ->get();
            foreach ($satisfactionResponses as $response) {
                DB::table('satisfaction_responses')
                    ->where('id', $response->id)
                    ->update([
                        'course_id' => $courseIdMap[$response->course_id] ?? $response->course_id,
                        'question_id' => $satisfactionQuestionIdMap[$response->question_id] ?? $response->question_id,
                        'login_code' => $archivedLoginCodes[$response->login_code] ?? $response->login_code,
                        'archive_id' => $archiveId,
                    ]);
            }

            foreach ($finalExamSubmissions as $submission) {
                DB::table('final_exam_submissions')
                    ->where('id', $submission->id)
                    ->update([
                        'login_code' => $archivedLoginCodes[$submission->login_code] ?? $submission->login_code,
                        'archive_id' => $archiveId,
                    ]);
            }

            $finalExamAnswers = DB::table('final_exam_submission_answers')
                ->whereNull('archive_id')
                ->whereIn('submission_id', $finalExamSubmissionIds)
                ->get();
            foreach ($finalExamAnswers as $answer) {
                DB::table('final_exam_submission_answers')
                    ->where('id', $answer->id)
                    ->update([
                        'question_id' => $finalQuestionIdMap[$answer->question_id] ?? $answer->question_id,
                        'archive_id' => $archiveId,
                    ]);
            }

            DB::table('reciter_students')->whereIn('student_id', $studentIds)->delete();
            DB::table('push_subscriptions')->whereIn('login_code', $studentLoginCodes)->delete();

            if ($studentLoginCodes->isNotEmpty()) {
                DB::table('users')
                    ->where('role', 'student')
                    ->whereIn('login_code', $studentLoginCodes)
                    ->delete();
            }
            
            // clear snapshot cache to force reload without the archived items
            cache()->forget('dashboard:snapshot');
            cache()->forget('dashboard:notifications');
        });

        return response()->json([
            'message' => 'All content archived successfully',
            'archive' => $archive,
        ]);
    }

    private function normalizeArchivedSubmission($submission, $submissionAnswers): ?array
    {
        if (! $submission) {
            return null;
        }

        return [
            'id' => $submission->id,
            'manualScore' => $submission->manual_score !== null ? (float) $submission->manual_score : null,
            'submittedAt' => (string) $submission->submitted_at,
            'answers' => collect($submissionAnswers->get($submission->id, []))->map(fn ($answer) => [
                'questionId' => $answer->question_id,
                'value' => $answer->answer_text ?? '',
                'fileName' => $answer->file_name,
                'fileType' => $answer->file_type,
            ])->values()->all(),
        ];
    }

    private function generateArchivedLoginCode(): string
    {
        do {
            $loginCode = 'archive-' . strtolower(substr(str_replace('-', '', (string) str()->uuid()), 0, 10));
        } while (
            Student::query()->where('login_code', $loginCode)->exists()
            || DB::table('users')->where('login_code', $loginCode)->exists()
        );

        return $loginCode;
    }

    private function resolveStudentDisplayLoginCode(object $student): string
    {
        return trim((string) ($student->archived_login_code ?: $student->login_code));
    }
}
