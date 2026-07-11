<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CoreDataController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('public')->group(function () {
    Route::get('snapshot', [CoreDataController::class, 'snapshot']);
    Route::get('stats', [CoreDataController::class, 'publicStats']);
    Route::get('training-material-attachments/{attachmentId}', [CoreDataController::class, 'trainingMaterialAttachment']);
    Route::get('registration', [RegistrationController::class, 'publicStatus']);
    Route::post('registration-requests', [RegistrationController::class, 'submit']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('assessment-submissions', [CoreDataController::class, 'submitAssessment']);
        Route::post('satisfaction-responses', [CoreDataController::class, 'storeSatisfactionResponses']);
        Route::post('final-exam/submissions', [CoreDataController::class, 'submitFinalExam']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('snapshot', [CoreDataController::class, 'snapshot']);
        Route::post('editor-images', [CoreDataController::class, 'storeEditorImage']);
        Route::middleware('dashboard.access')->group(function () {
            Route::get('activity-logs', [CoreDataController::class, 'activityLogs']);
            Route::post('task-templates', [CoreDataController::class, 'storeTaskTemplate']);
            Route::put('task-templates/{templateId}', [CoreDataController::class, 'updateTaskTemplate']);
            Route::post('manual-attendance', [CoreDataController::class, 'setManualAttendance']);
            Route::post('assessment-submissions', [CoreDataController::class, 'submitAssessment']);
            Route::post('assessment-import', [CoreDataController::class, 'bulkImportAssessments']);
            Route::post('satisfaction-questions', [CoreDataController::class, 'storeSatisfactionQuestion']);
            Route::delete('satisfaction-questions/{questionId}', [CoreDataController::class, 'deleteSatisfactionQuestion']);
            Route::post('satisfaction-responses', [CoreDataController::class, 'storeSatisfactionResponses']);
            Route::post('final-exam/questions', [CoreDataController::class, 'storeFinalExamQuestion']);
            Route::put('final-exam/questions/{questionId}', [CoreDataController::class, 'updateFinalExamQuestion']);
            Route::delete('final-exam/questions/{questionId}', [CoreDataController::class, 'deleteFinalExamQuestion']);
            Route::put('final-exam/settings/{branchCode}', [CoreDataController::class, 'updateFinalExamSetting']);
            Route::put('final-exam/settings/{branchCode}/notification-template', [CoreDataController::class, 'updateFinalExamNotificationTemplate']);
            Route::post('final-exam/submissions', [CoreDataController::class, 'submitFinalExam']);
            Route::post('final-exam/questions/copy', [CoreDataController::class, 'copyFinalExamQuestions']);
            Route::put('final-exam/submissions/{submissionId}/manual-score', [CoreDataController::class, 'setFinalExamManualScore']);
            Route::put('assessment-submissions/{submissionId}/manual-score', [CoreDataController::class, 'setAssessmentManualScore']);
            Route::post('courses', [CoreDataController::class, 'storeCourse']);
            Route::put('courses/sort-order', [CoreDataController::class, 'updateCourseSortOrder']);
            Route::post('courses/deactivate-all', [CoreDataController::class, 'deactivateAllCourses']);
            Route::put('courses/{courseId}', [CoreDataController::class, 'updateCourse']);
            Route::delete('courses/{courseId}', [CoreDataController::class, 'deleteCourse']);
            Route::post('courses/{courseId}/activate', [CoreDataController::class, 'activateCourse']);
            Route::post('courses/{courseId}/questions', [CoreDataController::class, 'storeCourseQuestion']);
            Route::put('questions/{questionId}', [CoreDataController::class, 'updateCourseQuestion']);
            Route::delete('questions/{questionId}', [CoreDataController::class, 'deleteCourseQuestion']);
            Route::get('accounts', [CoreDataController::class, 'listDashboardAccounts']);
            Route::post('accounts', [CoreDataController::class, 'storeDashboardAccount']);
            Route::delete('accounts/{accountId}', [CoreDataController::class, 'deleteDashboardAccount']);
            Route::get('backup/export', [CoreDataController::class, 'exportDashboardBackup']);
            Route::post('backup/restore', [CoreDataController::class, 'restoreDashboardBackup']);
            Route::post('backup/restore-file', [CoreDataController::class, 'restoreDashboardBackupFile']);
            Route::post('transfer-student', [CoreDataController::class, 'transferStudent']);
            Route::post('activity-logs', [CoreDataController::class, 'storeActivityLog']);
            Route::get('notifications', [CoreDataController::class, 'notifications']);
            Route::post('notifications', [CoreDataController::class, 'storeNotification']);
            Route::delete('notifications/{notificationId}', [CoreDataController::class, 'deleteNotification']);
            Route::get('training-materials', [CoreDataController::class, 'trainingMaterials']);
            Route::post('training-materials', [CoreDataController::class, 'storeTrainingMaterial']);
            Route::put('training-materials/{materialId}', [CoreDataController::class, 'updateTrainingMaterial']);
            Route::delete('training-materials/{materialId}', [CoreDataController::class, 'deleteTrainingMaterial']);
            Route::put('home-page-content', [CoreDataController::class, 'updateHomePageContent']);
            Route::put('practitioner-page-content', [CoreDataController::class, 'updatePractitionerPageContent']);
            Route::get('role-permissions', [CoreDataController::class, 'rolePermissions']);
            Route::put('role-permissions', [CoreDataController::class, 'setRolePermission']);
            Route::get('registration', [RegistrationController::class, 'index']);
            Route::put('registration/settings', [RegistrationController::class, 'updateSettings']);
            Route::put('registration/fields', [RegistrationController::class, 'updateFields']);
            Route::post('registration-requests/{requestId}/accept', [RegistrationController::class, 'accept']);
            Route::post('registration-requests/{requestId}/reject', [RegistrationController::class, 'reject']);
            Route::post('registration-requests/{requestId}/mark-accepted', [RegistrationController::class, 'markAccepted']);

            // Archives
            Route::get('archives', [ArchiveController::class, 'index']);
            Route::post('archives', [ArchiveController::class, 'store']);
            Route::get('archives/search/students', [ArchiveController::class, 'searchStudents']);
            Route::get('archives/{archiveId}', [ArchiveController::class, 'show']);
            Route::delete('archives/{archiveId}', [ArchiveController::class, 'destroy']);
            Route::get('archives/{archiveId}/students/{studentId}', [ArchiveController::class, 'studentDetail']);
            Route::post('archives/{archiveId}/students', [ArchiveController::class, 'assignStudent']);
            Route::post('archives/archive-all', [ArchiveController::class, 'archiveAll']);
        });
    });

    Route::prefix('students')->group(function () {
        Route::middleware('dashboard.access')->group(function () {
            Route::post('/', [CoreDataController::class, 'storeStudent']);
            Route::put('{student}', [CoreDataController::class, 'updateStudent']);
            Route::delete('{student}', [CoreDataController::class, 'deleteStudent']);
        });
        Route::get('by-login/{loginCode}/assigned-reciter', [CoreDataController::class, 'getAssignedReciter']);
        Route::put('{student}/parts/{partNumber}', [CoreDataController::class, 'toggleStudentPart']);
    });

    Route::prefix('reciters')->group(function () {
        Route::post('/', [CoreDataController::class, 'storeReciter'])->middleware('dashboard.access');
        Route::get('by-login/{loginCode}', [CoreDataController::class, 'showReciterByLoginCode']);
        Route::delete('by-login/{loginCode}', [CoreDataController::class, 'deleteReciterByLoginCode'])->middleware('dashboard.access');
    });
});
