<?php

namespace Tests\Feature;

use App\Models\EditorAsset;
use App\Models\Student;
use App\Models\TrainingMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoreDataApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::delete(storage_path('app/registration-request-metadata.json'));

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'login_code' => '9000',
        ]));
    }

    public function test_dashboard_account_flow_works(): void
    {
        $createResponse = $this->postJson('/api/dashboard/accounts', [
            'name' => 'Manager User',
            'loginCode' => '5001',
            'role' => 'male_manager',
        ]);

        $createResponse->assertCreated()->assertJsonPath('loginCode', '5001');

        $listResponse = $this->getJson('/api/dashboard/accounts');
        $createdAccount = collect($listResponse->json())->firstWhere('loginCode', '5001');
        $accountId = $createdAccount['id'] ?? null;

        $listResponse->assertOk()->assertJsonFragment([
            'name' => 'Manager User',
            'loginCode' => '5001',
            'role' => 'male_manager',
        ]);

        $this->assertNotNull($accountId);

        $this->deleteJson('/api/dashboard/accounts/'.$accountId)
            ->assertNoContent();

        $this->getJson('/api/dashboard/accounts')
            ->assertOk()
            ->assertJsonMissing(['loginCode' => '5001']);
    }

    public function test_editor_image_upload_works(): void
    {
        Storage::fake('public');

        $response = $this->postJson('/api/dashboard/editor-images', [
            'image' => UploadedFile::fake()->image('task-image.png', 1200, 800),
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['id', 'name', 'url']);

        $this->assertDatabaseHas('editor_assets', [
            'created_by' => auth()->id(),
        ]);

        $this->assertDatabaseHas('media', [
            'model_type' => EditorAsset::class,
            'collection_name' => 'editor-images',
            'mime_type' => 'image/png',
            'disk' => 'public',
        ]);

        $mediaPath = DB::table('media')->where('model_type', EditorAsset::class)->value('id');

        $this->assertNotNull($mediaPath);
    }

    public function test_training_materials_flow_works(): void
    {
        Storage::fake('public');

        $createResponse = $this->post('/api/dashboard/training-materials', [
            'title' => 'حقيبة المدرب',
            'description' => 'ملفات التدريب الأساسية',
            'branchId' => 'female',
            'attachments' => [
                [
                    'label' => 'دليل التدريب',
                    'file' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
                ],
                [
                    'label' => 'غلاف الحقيبة',
                    'file' => UploadedFile::fake()->image('cover.png', 800, 600),
                ],
                [
                    'label' => 'ملف مضغوط',
                    'file' => UploadedFile::fake()->create('resources.zip', 120, 'application/zip'),
                ],
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $materialId = $createResponse->json('id');

        $createResponse
            ->assertCreated()
            ->assertJsonPath('title', 'حقيبة المدرب')
            ->assertJsonPath('targetBranchId', 'female')
            ->assertJsonPath('attachments.0.displayName', 'دليل التدريب')
            ->assertJsonPath('attachments.2.displayName', 'ملف مضغوط')
            ->assertJsonCount(3, 'attachments');

        $this->assertDatabaseHas('training_materials', [
            'title' => 'حقيبة المدرب',
            'target_branch_code' => 'female',
        ]);

        $this->assertDatabaseHas('media', [
            'model_type' => TrainingMaterial::class,
            'model_id' => $materialId,
            'collection_name' => 'attachments',
            'disk' => 'public',
        ]);

        $this->getJson('/api/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('trainingMaterials.0.title', 'حقيبة المدرب')
            ->assertJsonPath('trainingMaterials.0.targetBranchId', 'female')
            ->assertJsonPath('trainingMaterials.0.attachments.1.displayName', 'غلاف الحقيبة')
            ->assertJsonPath('trainingMaterials.0.attachments.2.displayName', 'ملف مضغوط')
            ->assertJsonCount(3, 'trainingMaterials.0.attachments');

        $this->deleteJson('/api/dashboard/training-materials/'.$materialId)
            ->assertNoContent();

        $this->assertDatabaseMissing('training_materials', [
            'id' => $materialId,
        ]);
    }

    public function test_training_material_can_be_updated_with_attachment_add_remove_and_rename(): void
    {
        Storage::fake('public');

        $createResponse = $this->post('/api/dashboard/training-materials', [
            'title' => 'حقيبة قابلة للتعديل',
            'description' => 'الوصف الأول',
            'branchId' => 'male',
            'attachments' => [
                [
                    'label' => 'ملف أول',
                    'file' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
                ],
                [
                    'label' => 'ملف ثان',
                    'file' => UploadedFile::fake()->create('second.zip', 120, 'application/zip'),
                ],
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $materialId = $createResponse->json('id');
        $firstAttachmentId = $createResponse->json('attachments.0.id');

        $updateResponse = $this->post(
            '/api/dashboard/training-materials/'.$materialId,
            [
                '_method' => 'PUT',
                'title' => 'حقيبة بعد التعديل',
                'description' => 'الوصف بعد التعديل',
                'branchId' => 'female',
                'attachments' => [
                    [
                        'id' => $firstAttachmentId,
                        'label' => 'الملف الأول بعد التعديل',
                    ],
                    [
                        'label' => 'ملف جديد',
                        'file' => UploadedFile::fake()->image('new-cover.png', 800, 600),
                    ],
                ],
            ],
            [
                'Accept' => 'application/json',
            ],
        );

        $updateResponse
            ->assertOk()
            ->assertJsonPath('title', 'حقيبة بعد التعديل')
            ->assertJsonPath('description', 'الوصف بعد التعديل')
            ->assertJsonPath('targetBranchId', 'female')
            ->assertJsonPath('attachments.0.displayName', 'الملف الأول بعد التعديل')
            ->assertJsonPath('attachments.1.displayName', 'ملف جديد')
            ->assertJsonCount(2, 'attachments');

        $this->getJson('/api/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('trainingMaterials.0.title', 'حقيبة بعد التعديل')
            ->assertJsonPath('trainingMaterials.0.description', 'الوصف بعد التعديل')
            ->assertJsonPath('trainingMaterials.0.targetBranchId', 'female')
            ->assertJsonPath('trainingMaterials.0.attachments.0.displayName', 'الملف الأول بعد التعديل')
            ->assertJsonPath('trainingMaterials.0.attachments.1.displayName', 'ملف جديد')
            ->assertJsonCount(2, 'trainingMaterials.0.attachments');

        $this->assertDatabaseHas('training_materials', [
            'id' => $materialId,
            'title' => 'حقيبة بعد التعديل',
            'description' => 'الوصف بعد التعديل',
            'target_branch_code' => 'female',
        ]);

        $this->assertDatabaseMissing('media', [
            'model_type' => TrainingMaterial::class,
            'model_id' => $materialId,
            'name' => 'ملف ثان',
        ]);
    }

    public function test_student_flow_works(): void
    {
        $createResponse = $this->postJson('/api/students', [
            'name' => 'طالب جديد',
            'loginId' => '7001',
            'branchId' => 'male',
            'note' => 'ملاحظة',
        ]);

        $studentId = $createResponse->json('id');

        $createResponse
            ->assertCreated()
            ->assertJsonPath('branchId', 'male')
            ->assertJsonPath('isCertified', false);

        $this->assertDatabaseHas('users', [
            'login_code' => '7001',
            'role' => 'student',
            'full_name' => 'طالب جديد',
        ]);

        $studentUser = User::query()->where('login_code', '7001')->first();
        $this->assertNotNull($studentUser);
        $this->assertTrue(Hash::check('7001', $studentUser->password));

        $this->putJson('/api/students/'.$studentId, [
            'name' => 'طالب محدث',
            'loginCode' => '7002',
            'isCertified' => true,
            'completedParts' => [1, 5, 5, 3],
        ])
            ->assertOk()
            ->assertJsonPath('name', 'طالب محدث')
            ->assertJsonPath('loginId', '7002')
            ->assertJsonPath('isCertified', true)
            ->assertJsonPath('completedParts.0', 1)
            ->assertJsonPath('completedParts.1', 3)
            ->assertJsonPath('completedParts.2', 5);

        $this->assertDatabaseMissing('users', [
            'login_code' => '7001',
            'role' => 'student',
        ]);

        $this->assertDatabaseHas('users', [
            'login_code' => '7002',
            'role' => 'student',
            'full_name' => 'طالب محدث',
        ]);

        $this->deleteJson('/api/students/'.$studentId)->assertNoContent();

        $this->assertDatabaseMissing('students', ['id' => $studentId]);
        $this->assertDatabaseMissing('users', ['login_code' => '7002', 'role' => 'student']);
    }

    public function test_reciter_flow_and_assigned_reciter_lookup_work(): void
    {
        $student = Student::query()->create([
            'full_name' => 'طالب مرتبط',
            'login_code' => '8100',
            'branch_id' => DB::table('branches')->where('code', 'female')->value('id'),
            'note' => '',
        ]);

        $this->postJson('/api/reciters', [
            'name' => 'مقرئ جديد',
            'loginCode' => '9001',
            'branchId' => 'female',
            'linkedStudentIds' => [$student->id],
        ])
            ->assertOk()
            ->assertJsonPath('loginCode', '9001');

        $this->getJson('/api/reciters/by-login/9001')
            ->assertOk()
            ->assertJsonPath('loginCode', '9001')
            ->assertJsonPath('students.0.loginId', '8100');

        $this->getJson('/api/students/by-login/8100/assigned-reciter')
            ->assertOk()
            ->assertJsonPath('loginCode', '9001');

        $this->deleteJson('/api/reciters/by-login/9001')
            ->assertOk();

        $this->getJson('/api/reciters/by-login/9001')
            ->assertOk()
            ->assertContent('null');
    }

    public function test_female_students_are_limited_to_ten_parts(): void
    {
        $student = Student::query()->create([
            'full_name' => 'معلمة حد الأجزاء',
            'login_code' => '7010',
            'branch_id' => DB::table('branches')->where('code', 'female')->value('id'),
            'note' => '',
        ]);

        $this->putJson('/api/students/'.$student->id, [
            'completedParts' => [1, 10, 11, 30],
        ])
            ->assertOk()
            ->assertJsonPath('completedParts.0', 1)
            ->assertJsonPath('completedParts.1', 10)
            ->assertJsonCount(2, 'completedParts');

        $this->putJson('/api/students/'.$student->id.'/parts/11', [
            'shouldMarkComplete' => true,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('student_parts', [
            'student_id' => $student->id,
            'part_number' => 11,
        ]);
    }

    public function test_public_registration_uses_identity_number_for_accepted_student_and_branch_is_selected_from_gender(): void
    {
        DB::table('registration_settings')->updateOrInsert(
            ['key' => 'is_open'],
            ['value' => '1', 'updated_at' => now()],
        );

        $submitResponse = $this->postJson('/api/public/registration-requests', [
            'name' => 'طالب تجريبي',
            'loginCode' => '1020304050',
            'gender' => 'female',
            'age' => 22,
        ]);

        $requestId = $submitResponse->json('id');

        $submitResponse
            ->assertCreated()
            ->assertJsonPath('name', 'طالب تجريبي')
            ->assertJsonPath('loginCode', '1020304050')
            ->assertJsonPath('gender', 'female')
            ->assertJsonPath('age', 22)
            ->assertJsonPath('branchId', null)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('registration_requests', [
            'id' => $requestId,
            'full_name' => 'طالب تجريبي',
            'login_code' => '1020304050',
            'branch_code' => null,
            'status' => 'pending',
        ]);

        $this->getJson('/api/dashboard/registration')
            ->assertOk()
            ->assertJsonPath('requests.0.age', 22)
            ->assertJsonPath('requests.0.gender', 'female');

        $acceptResponse = $this->postJson('/api/dashboard/registration-requests/'.$requestId.'/accept');

        $acceptResponse
            ->assertOk()
            ->assertJsonPath('name', 'طالب تجريبي')
            ->assertJsonPath('loginCode', '1020304050')
            ->assertJsonPath('age', null)
            ->assertJsonPath('branchId', 'female')
            ->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('students', [
            'full_name' => 'طالب تجريبي',
            'login_code' => '1020304050',
            'branch_id' => DB::table('branches')->where('code', 'female')->value('id'),
        ]);

        $this->assertDatabaseHas('users', [
            'full_name' => 'طالب تجريبي',
            'login_code' => '1020304050',
            'role' => 'student',
        ]);
    }

    public function test_dashboard_snapshot_and_transfer_student_work(): void
    {
        $maleBranchId = DB::table('branches')->where('code', 'male')->value('id');

        $student = Student::query()->create([
            'full_name' => 'طالب تحويل',
            'login_code' => '8300',
            'branch_id' => $maleBranchId,
            'note' => 'ملاحظة تحويل',
        ]);

        $firstReciterResponse = $this->postJson('/api/reciters', [
            'name' => 'مقرئ أول',
            'loginCode' => '9101',
            'branchId' => 'male',
            'linkedStudentIds' => [$student->id],
        ])->assertOk();

        $secondReciterResponse = $this->postJson('/api/reciters', [
            'name' => 'مقرئ ثان',
            'loginCode' => '9102',
            'branchId' => 'male',
            'linkedStudentIds' => [],
        ])->assertOk();

        $snapshot = $this->getJson('/api/dashboard/snapshot');

        $snapshot
            ->assertOk()
            ->assertJsonPath('students.0.loginId', '8300')
            ->assertJsonPath('reciters.0.loginCode', '9101')
            ->assertJsonStructure([
                'roles',
                'branches',
                'students',
                'reciters',
                'courses',
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
                'rolePermissions',
            ]);

        $this->postJson('/api/dashboard/transfer-student', [
            'studentId' => $student->id,
            'targetReciterId' => $secondReciterResponse->json('id'),
        ])->assertNoContent();

        $this->getJson('/api/students/by-login/8300/assigned-reciter')
            ->assertOk()
            ->assertJsonPath('loginCode', '9102');

        $this->assertDatabaseMissing('reciter_students', [
            'reciter_id' => $firstReciterResponse->json('id'),
            'student_id' => $student->id,
        ]);

        $this->assertDatabaseHas('reciter_students', [
            'reciter_id' => $secondReciterResponse->json('id'),
            'student_id' => $student->id,
        ]);
    }

    public function test_toggle_student_part_activity_logs_notifications_and_role_permissions_work(): void
    {
        $maleBranchId = DB::table('branches')->where('code', 'male')->value('id');

        $student = Student::query()->create([
            'full_name' => 'طالب أجزاء',
            'login_code' => '8400',
            'branch_id' => $maleBranchId,
            'note' => '',
        ]);

        $reciterResponse = $this->postJson('/api/reciters', [
            'name' => 'مقرئ أجزاء',
            'loginCode' => '9201',
            'branchId' => 'male',
            'linkedStudentIds' => [$student->id],
        ])->assertOk();

        $reciterId = $reciterResponse->json('id');

        $this->putJson('/api/students/'.$student->id.'/parts/7', [
            'reciterId' => $reciterId,
            'shouldMarkComplete' => true,
        ])->assertNoContent();

        $this->assertDatabaseHas('student_parts', [
            'student_id' => $student->id,
            'part_number' => 7,
            'marked_by_reciter_id' => $reciterId,
        ]);
        $this->putJson('/api/students/'.$student->id.'/parts/7', [
            'reciterId' => $reciterId,
            'shouldMarkComplete' => false,
        ])->assertNoContent();

        $this->assertDatabaseMissing('student_parts', [
            'student_id' => $student->id,
            'part_number' => 7,
        ]);

        $this->putJson('/api/students/'.$student->id.'/parts/8', [
            'reciterId' => null,
            'shouldMarkComplete' => true,
        ])->assertNoContent();

        $this->assertDatabaseHas('student_parts', [
            'student_id' => $student->id,
            'part_number' => 8,
            'marked_by_reciter_id' => null,
        ]);

        $logResponse = $this->postJson('/api/dashboard/activity-logs', [
            'action' => 'نقل طالب',
            'target' => 'طالب أجزاء',
            'status' => 'نجحت',
            'details' => 'تم تحديث المقروء.',
            'actorName' => 'مشرف النظام',
            'actorRole' => 'admin',
        ]);

        $logResponse->assertCreated()->assertJsonPath('action', 'نقل طالب');

        $this->getJson('/api/dashboard/activity-logs')
            ->assertOk()
            ->assertJsonPath('0.target', 'طالب أجزاء');

        $notificationResponse = $this->postJson('/api/dashboard/notifications', [
            'title' => 'تنبيه',
            'message' => 'تم فتح الاختبار.',
            'targetBranchId' => 'male',
            'targetLoginIds' => ['8400'],
            'createdByName' => 'مشرف النظام',
            'createdByRole' => 'admin',
        ]);

        $notificationId = $notificationResponse->json('id');

        $notificationResponse->assertCreated();

        $this->getJson('/api/dashboard/notifications')
            ->assertOk()
            ->assertJsonPath('0.targetLoginIds.0', '8400');

        $this->deleteJson('/api/dashboard/notifications/'.$notificationId)
            ->assertNoContent();

        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);

        $this->putJson('/api/dashboard/role-permissions', [
            'role' => 'male_manager',
            'key' => 'transfer_reciter_student',
            'isEnabled' => true,
        ])->assertNoContent();

        $this->getJson('/api/dashboard/role-permissions')
            ->assertOk()
            ->assertJsonPath('male_manager.transfer_reciter_student', true);
    }

    public function test_branch_manager_snapshot_is_limited_to_the_managed_branch(): void
    {
        $maleCourseId = (string) Str::uuid();

        $this->postJson('/api/students', [
            'name' => 'متدرب فرع الرجال',
            'loginId' => '7101',
            'branchId' => 'male',
        ])->assertCreated();

        $this->postJson('/api/students', [
            'name' => 'متدربة فرع النساء',
            'loginId' => '7102',
            'branchId' => 'female',
        ])->assertCreated();

        DB::table('courses')->insert([
            'id' => $maleCourseId,
            'title' => 'دورة الفرعين',
            'entity_type' => 'course',
            'is_active' => true,
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
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        DB::table('course_submissions')->insert([
            [
                'id' => (string) Str::uuid(),
                'course_id' => $maleCourseId,
                'assessment_type' => 'pre',
                'student_name' => 'متدرب فرع الرجال',
                'login_code' => '7101',
                'manual_score' => null,
                'submitted_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'course_id' => $maleCourseId,
                'assessment_type' => 'pre',
                'student_name' => 'متدربة فرع النساء',
                'login_code' => '7102',
                'manual_score' => null,
                'submitted_at' => now(),
            ],
        ]);

        DB::table('course_attendance')->insert([
            [
                'id' => (string) Str::uuid(),
                'course_id' => $maleCourseId,
                'student_name' => 'متدرب فرع الرجال',
                'login_code' => '7101',
                'source' => 'manual',
                'created_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'course_id' => $maleCourseId,
                'student_name' => 'متدربة فرع النساء',
                'login_code' => '7102',
                'source' => 'manual',
                'created_at' => now(),
            ],
        ]);

        DB::table('role_permissions')->updateOrInsert(
            ['role' => 'male_manager', 'permission_key' => 'transfer_reciter_student'],
            ['is_enabled' => true],
        );

        Sanctum::actingAs(User::factory()->create([
            'role' => 'male_manager',
            'login_code' => '9001',
        ]));

        $this->getJson('/api/dashboard/snapshot')
            ->assertOk()
            ->assertJsonCount(1, 'branches')
            ->assertJsonPath('branches.0.id', 'male')
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.loginId', '7101')
            ->assertJsonCount(1, 'submissions')
            ->assertJsonPath('submissions.0.loginId', '7101')
            ->assertJsonCount(1, 'attendance')
            ->assertJsonPath('attendance.0.loginId', '7101');
    }

    public function test_home_page_content_can_be_updated_and_is_exposed_in_public_snapshot(): void
    {
        $response = $this->putJson('/api/dashboard/home-page-content', [
            'content' => [
                'brandTitle' => 'الرئيسية المخصصة',
                'heroTitle' => 'واجهة تعريفية جديدة',
                'heroText' => 'هذا نص تعريفي جديد للصفحة الرئيسية.',
                'heroPrimaryButtonLabel' => 'استعرض الرخص',
                'programAvailableActionLabel' => 'ابدأ الآن',
                'achievements' => [
                    'licenseCountTitle' => 'عدد الرخص',
                ],
                'programs' => [
                    [
                        'title' => 'رخصة ممارس المطورة',
                        'description' => 'وصف مخصص للرخصة الأولى.',
                        'features' => ['ميزة أولى', 'ميزة ثانية', 'ميزة ثالثة'],
                    ],
                ],
                'faqItems' => [
                    [
                        'question' => 'سؤال شائع مخصص؟',
                        'answer' => 'إجابة مخصصة من لوحة الإعدادات.',
                    ],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('brandTitle', 'الرئيسية المخصصة')
            ->assertJsonPath('heroTitle', 'واجهة تعريفية جديدة')
            ->assertJsonPath('achievements.licenseCountTitle', 'عدد الرخص')
            ->assertJsonPath('programs.0.title', 'رخصة ممارس المطورة')
            ->assertJsonPath('faqItems.0.question', 'سؤال شائع مخصص؟');

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'home_page_content',
        ]);

        $this->getJson('/api/public/snapshot')
            ->assertOk()
            ->assertJsonPath('homePageContent.brandTitle', 'الرئيسية المخصصة')
            ->assertJsonPath('homePageContent.heroPrimaryButtonLabel', 'استعرض الرخص')
            ->assertJsonPath('homePageContent.programAvailableActionLabel', 'ابدأ الآن')
            ->assertJsonPath('homePageContent.programs.0.features.2', 'ميزة ثالثة')
            ->assertJsonPath('homePageContent.faqItems.0.answer', 'إجابة مخصصة من لوحة الإعدادات.');
    }

    public function test_practitioner_page_content_can_be_updated_and_is_exposed_in_public_snapshot(): void
    {
        $response = $this->putJson('/api/dashboard/practitioner-page-content', [
            'content' => [
                'heroTitle' => 'واجهة رخصة ممارس المحدثة',
                'heroPrimaryButtonLabel' => 'ابدأ التسجيل',
                'aboutBody' => 'وصف جديد لصفحة رخصة ممارس من لوحة الإعدادات.',
                'goals' => [
                    'هدف أول مخصص',
                    'هدف ثانٍ مخصص',
                ],
                'indicatorLabels' => [
                    'tasks' => 'المهام التطبيقية',
                ],
                'startDates' => [
                    ['tag' => 'الرجال', 'text' => 'الأحد 01 / 01 / 1448هـ'],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('heroTitle', 'واجهة رخصة ممارس المحدثة')
            ->assertJsonPath('heroPrimaryButtonLabel', 'ابدأ التسجيل')
            ->assertJsonPath('aboutBody', 'وصف جديد لصفحة رخصة ممارس من لوحة الإعدادات.')
            ->assertJsonPath('goals.0', 'هدف أول مخصص')
            ->assertJsonPath('indicatorLabels.tasks', 'المهام التطبيقية')
            ->assertJsonPath('startDates.0.text', 'الأحد 01 / 01 / 1448هـ');

        $this->assertDatabaseHas('app_settings', [
            'setting_key' => 'practitioner_page_content',
        ]);

        $this->getJson('/api/public/snapshot')
            ->assertOk()
            ->assertJsonPath('practitionerPageContent.heroTitle', 'واجهة رخصة ممارس المحدثة')
            ->assertJsonPath('practitionerPageContent.heroPrimaryButtonLabel', 'ابدأ التسجيل')
            ->assertJsonPath('practitionerPageContent.aboutBody', 'وصف جديد لصفحة رخصة ممارس من لوحة الإعدادات.')
            ->assertJsonPath('practitionerPageContent.indicatorLabels.tasks', 'المهام التطبيقية')
            ->assertJsonPath('practitionerPageContent.startDates.0.text', 'الأحد 01 / 01 / 1448هـ');
    }

    public function test_archive_all_persists_full_educational_snapshot_and_detaches_reciters(): void
    {
        $maleBranchId = DB::table('branches')->where('code', 'male')->value('id');

        $studentId = (string) Str::uuid();
        $courseId = (string) Str::uuid();
        $questionId = (string) Str::uuid();
        $submissionId = (string) Str::uuid();
        $submissionAnswerId = (string) Str::uuid();
        $attendanceId = (string) Str::uuid();
        $satisfactionQuestionId = (string) Str::uuid();
        $satisfactionResponseId = (string) Str::uuid();
        $finalQuestionId = (string) Str::uuid();
        $finalSubmissionId = (string) Str::uuid();
        $finalAnswerId = (string) Str::uuid();
        $reciterUserId = (string) Str::uuid();
        $reciterId = (string) Str::uuid();

        DB::table('students')->insert([
            'id' => $studentId,
            'full_name' => 'طالب أرشيف شامل',
            'login_code' => '8610',
            'branch_id' => $maleBranchId,
            'note' => 'ملاحظة أرشيف',
            'is_certified' => true,
            'created_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => $reciterUserId,
            'full_name' => 'مقرئ أرشيف',
            'role' => 'reciter',
            'login_code' => '9301',
            'password' => Hash::make('9301'),
            'created_at' => now(),
        ]);

        DB::table('reciters')->insert([
            'id' => $reciterId,
            'full_name' => 'مقرئ أرشيف',
            'user_id' => $reciterUserId,
            'branch_id' => $maleBranchId,
            'created_at' => now(),
        ]);

        DB::table('reciter_students')->insert([
            'reciter_id' => $reciterId,
            'student_id' => $studentId,
            'created_at' => now(),
        ]);

        DB::table('student_parts')->insert([
            'student_id' => $studentId,
            'part_number' => 3,
            'marked_by_reciter_id' => $reciterId,
            'marked_at' => now(),
        ]);

        DB::table('courses')->insert([
            'id' => $courseId,
            'title' => 'دورة الأرشيف',
            'entity_type' => 'course',
            'task_mode' => null,
            'task_template_id' => null,
            'task_template_name' => '',
            'task_template_content' => null,
            'youtube_url' => '',
            'is_active' => true,
            'is_pre_enabled' => true,
            'is_post_enabled' => true,
            'is_tasks_enabled' => true,
            'male_pre_enabled' => true,
            'female_pre_enabled' => true,
            'male_post_enabled' => true,
            'female_post_enabled' => true,
            'male_tasks_enabled' => true,
            'female_tasks_enabled' => true,
            'assessment_windows' => null,
            'assessment_notification_templates' => null,
            'sort_order' => 1,
            'created_by' => null,
            'created_at' => now(),
        ]);

        DB::table('course_questions')->insert([
            'id' => $questionId,
            'course_id' => $courseId,
            'assessment_type' => 'pre',
            'question_type' => 'text',
            'prompt' => 'سؤال قبلي',
            'options' => null,
            'allow_file' => false,
            'points' => 5,
            'correct_answer' => null,
            'attachment_name' => '',
            'attachment_type' => '',
            'attachment_data_url' => null,
            'sort_order' => 1,
            'created_at' => now(),
        ]);

        DB::table('course_submissions')->insert([
            'id' => $submissionId,
            'course_id' => $courseId,
            'assessment_type' => 'pre',
            'student_id' => $studentId,
            'student_name' => 'طالب أرشيف شامل',
            'login_code' => '8610',
            'manual_score' => 88,
            'submitted_at' => now(),
        ]);

        DB::table('course_submission_answers')->insert([
            'id' => $submissionAnswerId,
            'submission_id' => $submissionId,
            'question_id' => $questionId,
            'answer_text' => 'إجابة قبلي',
            'file_name' => null,
            'file_type' => null,
            'file_data_url' => null,
            'created_at' => now(),
        ]);

        DB::table('course_attendance')->insert([
            'id' => $attendanceId,
            'course_id' => $courseId,
            'student_id' => $studentId,
            'student_name' => 'طالب أرشيف شامل',
            'login_code' => '8610',
            'source' => 'manual',
            'created_at' => now(),
        ]);

        DB::table('satisfaction_questions')->insert([
            'id' => $satisfactionQuestionId,
            'course_id' => $courseId,
            'prompt' => 'كيف كان المستوى؟',
            'type' => 'rating',
            'is_required' => true,
            'sort_order' => 1,
            'created_at' => now(),
        ]);

        DB::table('satisfaction_responses')->insert([
            'id' => $satisfactionResponseId,
            'course_id' => $courseId,
            'question_id' => $satisfactionQuestionId,
            'login_code' => '8610',
            'student_name' => 'طالب أرشيف شامل',
            'rating_value' => 9,
            'text_value' => 'ممتاز',
            'submitted_at' => now(),
        ]);

        DB::table('final_exam_questions')->insert([
            'id' => $finalQuestionId,
            'branch_code' => 'male',
            'question_type' => 'text',
            'prompt' => 'سؤال نهائي',
            'options' => null,
            'allow_file' => false,
            'points' => 10,
            'correct_answer' => null,
            'attachment_name' => '',
            'attachment_type' => '',
            'attachment_data_url' => null,
            'sort_order' => 1,
            'created_at' => now(),
        ]);

        DB::table('final_exam_submissions')->insert([
            'id' => $finalSubmissionId,
            'branch_code' => 'male',
            'student_name' => 'طالب أرشيف شامل',
            'login_code' => '8610',
            'manual_score' => 93,
            'submitted_at' => now(),
        ]);

        DB::table('final_exam_submission_answers')->insert([
            'id' => $finalAnswerId,
            'submission_id' => $finalSubmissionId,
            'question_id' => $finalQuestionId,
            'answer_text' => 'إجابة نهائية',
            'file_name' => null,
            'file_type' => null,
            'file_data_url' => null,
        ]);

        $archiveResponse = $this->postJson('/api/dashboard/archives/archive-all', [
            'name' => 'دفعة الأرشيف الشامل',
            'batch_type' => 'all',
        ]);

        $archiveId = $archiveResponse->json('archive.id');

        $archiveResponse->assertOk()->assertJsonPath('archive.name', 'دفعة الأرشيف الشامل');

        $this->assertDatabaseHas('students', ['id' => $studentId, 'archive_id' => $archiveId, 'archived_login_code' => '8610']);
        $this->assertDatabaseHas('courses', ['id' => $courseId, 'archive_id' => null]);
        $this->assertDatabaseHas('course_questions', ['id' => $questionId, 'archive_id' => null]);
        $archivedCourseId = DB::table('courses')
            ->where('archive_id', $archiveId)
            ->where('title', 'دورة الأرشيف')
            ->value('id');
        $archivedQuestionId = DB::table('course_questions')
            ->where('archive_id', $archiveId)
            ->where('prompt', 'سؤال قبلي')
            ->value('id');
        $this->assertNotNull($archivedCourseId);
        $this->assertNotNull($archivedQuestionId);
        $this->assertNotSame($courseId, $archivedCourseId);
        $this->assertNotSame($questionId, $archivedQuestionId);
        $this->assertDatabaseHas('course_submissions', ['id' => $submissionId, 'archive_id' => $archiveId]);
        $this->assertDatabaseHas('course_submission_answers', ['id' => $submissionAnswerId, 'archive_id' => $archiveId]);
        $this->assertDatabaseHas('course_attendance', ['id' => $attendanceId, 'archive_id' => $archiveId]);
        $this->assertDatabaseHas('satisfaction_questions', ['id' => $satisfactionQuestionId, 'archive_id' => null]);
        $this->assertDatabaseHas('satisfaction_responses', ['id' => $satisfactionResponseId, 'archive_id' => $archiveId]);
        $this->assertDatabaseHas('final_exam_questions', ['id' => $finalQuestionId, 'archive_id' => null]);
        $this->assertDatabaseHas('final_exam_submissions', ['id' => $finalSubmissionId, 'archive_id' => $archiveId]);
        $this->assertDatabaseHas('final_exam_submission_answers', ['id' => $finalAnswerId, 'archive_id' => $archiveId]);
        $this->assertDatabaseMissing('reciter_students', ['reciter_id' => $reciterId, 'student_id' => $studentId]);
        $this->assertDatabaseMissing('users', ['login_code' => '8610', 'role' => 'student']);

        $archivedStudentLoginCode = DB::table('students')->where('id', $studentId)->value('login_code');

        $this->assertNotSame('8610', $archivedStudentLoginCode);

        $this->getJson('/api/dashboard/snapshot')
            ->assertOk()
            ->assertJsonMissing(['loginId' => '8610'])
            ->assertJsonFragment(['title' => 'دورة الأرشيف']);

        $this->postJson('/api/students', [
            'name' => 'طالب جديد بعد الأرشفة',
            'loginId' => '8610',
            'branchId' => 'male',
            'note' => 'إعادة استخدام الرقم',
        ])
            ->assertCreated()
            ->assertJsonPath('loginId', '8610');

        $this->getJson('/api/dashboard/archives/'.$archiveId.'/students/'.$studentId)
            ->assertOk()
            ->assertJsonPath('student.name', 'طالب أرشيف شامل')
            ->assertJsonPath('summary.preTests', 1)
            ->assertJsonPath('summary.attendance', 1)
            ->assertJsonPath('courses.0.title', 'دورة الأرشيف')
            ->assertJsonPath('courses.0.pre.manualScore', 88)
            ->assertJsonPath('courses.0.attendance.isPresent', true)
            ->assertJsonPath('courses.0.satisfactionResponses.0.ratingValue', 9)
            ->assertJsonPath('finalExam.manualScore', 93);
    }

    public function test_manual_attendance_replaces_previous_course_records_and_appears_in_snapshot(): void
    {
        $maleBranchId = DB::table('branches')->where('code', 'male')->value('id');

        $student = Student::query()->create([
            'full_name' => 'طالب حضور',
            'login_code' => '8500',
            'branch_id' => $maleBranchId,
            'note' => '',
        ]);

        $courseId = (string) str()->uuid();

        DB::table('courses')->insert([
            'id' => $courseId,
            'title' => 'دورة حفظ',
            'entity_type' => 'course',
            'task_mode' => null,
            'task_template_id' => null,
            'task_template_name' => '',
            'task_template_content' => '',
            'youtube_url' => '',
            'is_active' => true,
            'is_pre_enabled' => true,
            'is_post_enabled' => true,
            'is_tasks_enabled' => true,
            'male_pre_enabled' => true,
            'female_pre_enabled' => true,
            'male_post_enabled' => true,
            'female_post_enabled' => true,
            'male_tasks_enabled' => true,
            'female_tasks_enabled' => true,
            'assessment_windows' => json_encode(['global' => [], 'male' => [], 'female' => []]),
            'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => '']),
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        DB::table('course_attendance')->insert([
            'id' => (string) str()->uuid(),
            'course_id' => $courseId,
            'student_id' => null,
            'student_name' => 'قديم',
            'login_code' => '0000',
            'source' => 'manual',
            'created_at' => now(),
        ]);

        $this->postJson('/api/dashboard/manual-attendance', [
            'courseId' => $courseId,
            'presentStudents' => [[
                'loginId' => '8500',
                'studentName' => 'طالب حضور',
                'studentId' => $student->id,
            ]],
        ])->assertNoContent();

        $this->assertDatabaseMissing('course_attendance', [
            'course_id' => $courseId,
            'login_code' => '0000',
        ]);

        $this->assertDatabaseHas('course_attendance', [
            'course_id' => $courseId,
            'login_code' => '8500',
            'student_name' => 'طالب حضور',
            'source' => 'manual',
        ]);

        $this->getJson('/api/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('attendance.0.courseId', $courseId)
            ->assertJsonPath('attendance.0.loginId', '8500')
            ->assertJsonPath('attendance.0.source', 'manual');
    }

    public function test_assessment_submission_saves_answers_and_prevents_duplicate_attempts(): void
    {
        $maleBranchId = DB::table('branches')->where('code', 'male')->value('id');

        $student = Student::query()->create([
            'full_name' => 'طالب اختبار',
            'login_code' => '8600',
            'branch_id' => $maleBranchId,
            'note' => '',
        ]);

        Student::query()->create([
            'full_name' => 'طالب اختبار متأخر',
            'login_code' => '8601',
            'branch_id' => $maleBranchId,
            'note' => '',
        ]);

        $courseId = (string) str()->uuid();
        $questionId = (string) str()->uuid();

        DB::table('courses')->insert([
            'id' => $courseId,
            'title' => 'دورة تقييم',
            'entity_type' => 'course',
            'task_mode' => null,
            'task_template_id' => null,
            'task_template_name' => '',
            'task_template_content' => '',
            'youtube_url' => '',
            'is_active' => true,
            'is_pre_enabled' => true,
            'is_post_enabled' => true,
            'is_tasks_enabled' => true,
            'male_pre_enabled' => true,
            'female_pre_enabled' => true,
            'male_post_enabled' => true,
            'female_post_enabled' => true,
            'male_tasks_enabled' => true,
            'female_tasks_enabled' => true,
            'assessment_windows' => json_encode([
                'global' => [
                    'pre' => [
                        'opensAt' => now()->subMinutes(5)->toISOString(),
                        'closesAt' => now()->addMinutes(30)->toISOString(),
                        'durationMinutes' => 30,
                    ],
                ],
                'male' => [],
                'female' => [],
            ]),
            'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => '']),
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        DB::table('course_questions')->insert([
            'id' => $questionId,
            'course_id' => $courseId,
            'assessment_type' => 'pre',
            'question_type' => 'text',
            'prompt' => 'اكتب الإجابة',
            'options' => json_encode([]),
            'allow_file' => false,
            'points' => 1,
            'correct_answer' => '',
            'attachment_name' => '',
            'attachment_type' => '',
            'attachment_data_url' => '',
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/dashboard/assessment-submissions', [
            'courseId' => $courseId,
            'assessmentType' => 'pre',
            'studentName' => 'طالب اختبار',
            'loginId' => '8600',
            'answers' => [[
                'questionId' => $questionId,
                'value' => 'إجابة تجريبية',
            ]],
        ]);

        $submissionId = $response->json('id');

        $response
            ->assertCreated()
            ->assertJsonStructure(['id', 'submittedAt']);

        $this->assertDatabaseHas('course_submissions', [
            'id' => $submissionId,
            'course_id' => $courseId,
            'assessment_type' => 'pre',
            'student_id' => $student->id,
            'login_code' => '8600',
        ]);

        $this->assertDatabaseHas('course_submission_answers', [
            'submission_id' => $submissionId,
            'question_id' => $questionId,
            'answer_text' => 'إجابة تجريبية',
        ]);

        $this->postJson('/api/dashboard/assessment-submissions', [
            'courseId' => $courseId,
            'assessmentType' => 'pre',
            'studentName' => 'طالب اختبار',
            'loginId' => '8600',
            'answers' => [[
                'questionId' => $questionId,
                'value' => 'إجابة أخرى',
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.loginId.0', 'تم إرسال هذا الاختبار مسبقًا، ولا يمكن إعادة الاختبار مرة أخرى.');

        $this->putJson('/api/dashboard/courses/'.$courseId, [
            'assessmentWindows' => [
                'global' => [
                    'pre' => [
                        'opensAt' => now()->subMinutes(30)->toISOString(),
                        'closesAt' => now()->subMinute()->toISOString(),
                        'durationMinutes' => 30,
                    ],
                ],
                'male' => [],
                'female' => [],
            ],
        ])->assertNoContent();

        $this->postJson('/api/dashboard/assessment-submissions', [
            'courseId' => $courseId,
            'assessmentType' => 'pre',
            'studentName' => 'طالب اختبار متأخر',
            'loginId' => '8601',
            'answers' => [[
                'questionId' => $questionId,
                'value' => 'إجابة متأخرة',
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.loginId.0', 'انتهى وقت الإرسال أو أن التقييم غير متاح حاليًا.');

        $this->getJson('/api/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('submissions.0.id', $submissionId)
            ->assertJsonPath('submissions.0.answers.0.questionId', $questionId);
    }

    public function test_opening_assessments_tasks_and_final_exam_creates_notifications_from_saved_templates(): void
    {
        $courseId = (string) str()->uuid();

        DB::table('courses')->insert([
            'id' => $courseId,
            'title' => 'دورة الإشعارات',
            'entity_type' => 'course',
            'task_mode' => null,
            'task_template_id' => null,
            'task_template_name' => '',
            'task_template_content' => '',
            'youtube_url' => '',
            'is_active' => false,
            'is_pre_enabled' => false,
            'is_post_enabled' => false,
            'is_tasks_enabled' => false,
            'male_pre_enabled' => true,
            'female_pre_enabled' => true,
            'male_post_enabled' => true,
            'female_post_enabled' => true,
            'male_tasks_enabled' => true,
            'female_tasks_enabled' => false,
            'assessment_windows' => json_encode([
                'global' => [],
                'male' => ['tasks' => ['closesAt' => now()->addMinutes(60)->toISOString(), 'durationMinutes' => 60]],
                'female' => [],
            ], JSON_UNESCAPED_UNICODE),
            'assessment_notification_templates' => json_encode([
                'pre' => 'تم فتح {assessmentLabel} في {courseTitle} لفرع {branchLabel} لمدة {durationMinutes} دقيقة.',
                'post' => 'تم فتح {assessmentLabel} في {courseTitle} لفرع {branchLabel} لمدة {durationMinutes} دقيقة.',
                'tasks' => 'تم فتح {assessmentLabel} في {courseTitle} لفرع {branchLabel} لمدة {durationMinutes} دقيقة.',
            ], JSON_UNESCAPED_UNICODE),
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        $this->putJson('/api/dashboard/courses/'.$courseId, [
            'assessmentWindows' => [
                'global' => [
                    'pre' => [
                        'closesAt' => now()->addMinutes(45)->toISOString(),
                        'durationMinutes' => 45,
                    ],
                ],
                'male' => ['tasks' => ['closesAt' => now()->addMinutes(60)->toISOString(), 'durationMinutes' => 60]],
                'female' => [],
            ],
        ])->assertNoContent();

        $this->postJson('/api/dashboard/courses/'.$courseId.'/activate', [
            'pre' => true,
            'post' => false,
            'tasks' => false,
        ])->assertNoContent();

        $this->assertDatabaseHas('notifications', [
            'title' => 'الاختبار القبلي - دورة الإشعارات',
            'target_branch_code' => 'male',
        ]);

        $this->assertDatabaseHas('notifications', [
            'title' => 'الاختبار القبلي - دورة الإشعارات',
            'target_branch_code' => 'female',
        ]);

        $this->putJson('/api/dashboard/courses/'.$courseId, [
            'isTasksEnabled' => true,
            'branchAvailability' => [
                'male' => ['pre' => true, 'post' => true, 'tasks' => true],
                'female' => ['pre' => true, 'post' => true, 'tasks' => false],
            ],
            'assessmentWindows' => [
                'global' => ['pre' => ['closesAt' => now()->addMinutes(45)->toISOString(), 'durationMinutes' => 45]],
                'male' => ['tasks' => ['closesAt' => now()->addMinutes(90)->toISOString(), 'durationMinutes' => 90]],
                'female' => [],
            ],
        ])->assertNoContent();

        $this->assertDatabaseHas('notifications', [
            'title' => 'المهام الأدائية - دورة الإشعارات',
            'target_branch_code' => 'male',
        ]);

        $this->putJson('/api/dashboard/final-exam/settings/male', [
            'isEnabled' => true,
            'closesAt' => now()->addMinutes(75)->format('Y-m-d H:i:s'),
            'notificationTemplate' => 'تم فتح الاختبار النهائي لفرع {branchLabel} لمدة {durationMinutes} دقيقة.',
        ])->assertNoContent();

        $this->assertDatabaseHas('notifications', [
            'title' => 'الاختبار النهائي',
            'target_branch_code' => 'male',
        ]);

        $messages = DB::table('notifications')->pluck('message', 'title');

        $this->assertStringContainsString('دورة الإشعارات', (string) $messages['الاختبار القبلي - دورة الإشعارات']);
        $this->assertStringContainsString('معلمين', (string) $messages['المهام الأدائية - دورة الإشعارات']);
        $this->assertStringContainsString('معلمين', (string) $messages['الاختبار النهائي']);
    }

    public function test_course_and_question_management_flows_work(): void
    {
        $firstCourseResponse = $this->postJson('/api/dashboard/courses', [
            'title' => 'الدورة الأولى',
            'isActive' => true,
        ])->assertCreated();

        $firstCourseId = $firstCourseResponse->json('id');

        $taskCourseResponse = $this->postJson('/api/dashboard/courses', [
            'title' => 'مهمة منزلية',
            'entityType' => 'task',
            'taskMode' => 'document',
            'taskTemplateName' => 'قالب',
            'taskTemplateContent' => 'محتوى',
        ])->assertCreated();

        $taskCourseId = $taskCourseResponse->json('id');

        $this->putJson('/api/dashboard/courses/'.$firstCourseId, [
            'title' => 'الدورة المحدثة',
            'isPostEnabled' => false,
            'branchAvailability' => [
                'male' => ['pre' => true, 'post' => false, 'tasks' => true],
                'female' => ['pre' => true, 'post' => true, 'tasks' => false],
            ],
            'assessmentWindows' => [
                'global' => ['pre' => '2026-05-01T00:00:00.000Z'],
                'male' => [],
                'female' => [],
            ],
            'assessmentNotificationTemplates' => [
                'pre' => 'قبل',
                'post' => 'بعد',
                'tasks' => 'واجب',
            ],
            'youtubeUrl' => 'https://example.com/watch',
        ])->assertNoContent();

        $questionResponse = $this->postJson('/api/dashboard/courses/'.$firstCourseId.'/questions', [
            'assessmentType' => 'pre',
            'prompt' => 'سؤال صح وخطأ',
            'type' => 'truefalse',
            'options' => [],
            'allowFile' => false,
            'points' => 2,
            'correctAnswer' => 'صح',
        ])->assertCreated();

        $questionId = $questionResponse->json('id');

        $this->putJson('/api/dashboard/courses/sort-order', [
            'orderedIds' => [$taskCourseId, $firstCourseId],
        ])->assertNoContent();

        $this->postJson('/api/dashboard/courses/'.$firstCourseId.'/activate', [
            'pre' => true,
            'post' => false,
            'tasks' => true,
        ])->assertNoContent();

        $snapshot = $this->getJson('/api/dashboard/snapshot')->assertOk();

        $snapshot
            ->assertJsonPath('courses.0.id', $taskCourseId)
            ->assertJsonPath('courses.1.id', $firstCourseId)
            ->assertJsonPath('courses.1.title', 'الدورة المحدثة')
            ->assertJsonPath('courses.1.isActive', true)
            ->assertJsonPath('courses.1.isPostEnabled', false)
            ->assertJsonPath('courses.1.branchAvailability.male.post', false)
            ->assertJsonPath('courses.1.assessmentNotificationTemplates.tasks', 'واجب')
            ->assertJsonPath('courses.1.preQuestions.0.id', $questionId)
            ->assertJsonPath('courses.1.preQuestions.0.type', 'truefalse');

        $this->postJson('/api/dashboard/courses/deactivate-all')->assertNoContent();

        $this->deleteJson('/api/dashboard/questions/'.$questionId)->assertNoContent();
        $this->deleteJson('/api/dashboard/courses/'.$taskCourseId)->assertNoContent();

        $this->assertDatabaseMissing('course_questions', ['id' => $questionId]);
        $this->assertDatabaseMissing('courses', ['id' => $taskCourseId]);
    }

    public function test_opening_a_task_closes_the_previous_open_task(): void
    {
        $firstTaskId = $this->postJson('/api/dashboard/courses', [
            'title' => 'المهمة الأولى',
            'entityType' => 'task',
            'taskMode' => 'document',
            'taskTemplateName' => 'قالب أول',
            'taskTemplateContent' => 'محتوى أول',
        ])->assertCreated()->json('id');

        $secondTaskId = $this->postJson('/api/dashboard/courses', [
            'title' => 'المهمة الثانية',
            'entityType' => 'task',
            'taskMode' => 'document',
            'taskTemplateName' => 'قالب ثان',
            'taskTemplateContent' => 'محتوى ثان',
        ])->assertCreated()->json('id');

        $this->putJson('/api/dashboard/courses/'.$firstTaskId, [
            'isTasksEnabled' => true,
            'branchAvailability' => [
                'male' => ['pre' => true, 'post' => true, 'tasks' => true],
                'female' => ['pre' => true, 'post' => true, 'tasks' => true],
            ],
            'assessmentWindows' => [
                'global' => [],
                'male' => ['tasks' => ['closesAt' => now()->addMinutes(40)->toISOString(), 'durationMinutes' => 40]],
                'female' => ['tasks' => ['closesAt' => now()->addMinutes(40)->toISOString(), 'durationMinutes' => 40]],
            ],
        ])->assertNoContent();

        $this->putJson('/api/dashboard/courses/'.$secondTaskId, [
            'isTasksEnabled' => true,
            'branchAvailability' => [
                'male' => ['pre' => true, 'post' => true, 'tasks' => true],
                'female' => ['pre' => true, 'post' => true, 'tasks' => true],
            ],
            'assessmentWindows' => [
                'global' => [],
                'male' => ['tasks' => ['closesAt' => now()->addMinutes(55)->toISOString(), 'durationMinutes' => 55]],
                'female' => ['tasks' => ['closesAt' => now()->addMinutes(55)->toISOString(), 'durationMinutes' => 55]],
            ],
        ])->assertNoContent();

        $snapshot = $this->getJson('/api/dashboard/snapshot')->assertOk();

        $snapshot
            ->assertJsonPath('courses.0.id', $firstTaskId)
            ->assertJsonPath('courses.0.isTasksEnabled', false)
            ->assertJsonPath('courses.0.branchAvailability.male.tasks', false)
            ->assertJsonPath('courses.0.branchAvailability.female.tasks', false)
            ->assertJsonPath('courses.1.id', $secondTaskId)
            ->assertJsonPath('courses.1.isTasksEnabled', true)
            ->assertJsonPath('courses.1.branchAvailability.male.tasks', true)
            ->assertJsonPath('courses.1.branchAvailability.female.tasks', true);
    }

    public function test_bulk_assessment_import_replaces_existing_rows_and_preserves_manual_scores(): void
    {
        $maleBranchId = DB::table('branches')->where('code', 'male')->value('id');

        Student::query()->create([
            'full_name' => 'طالب أول',
            'login_code' => '8700',
            'branch_id' => $maleBranchId,
            'note' => '',
        ]);

        Student::query()->create([
            'full_name' => 'طالب ثان',
            'login_code' => '8701',
            'branch_id' => $maleBranchId,
            'note' => '',
        ]);

        $courseId = (string) str()->uuid();
        $questionId = (string) str()->uuid();

        DB::table('courses')->insert([
            'id' => $courseId,
            'title' => 'استيراد نتائج',
            'entity_type' => 'course',
            'task_mode' => null,
            'task_template_id' => null,
            'task_template_name' => '',
            'task_template_content' => '',
            'youtube_url' => '',
            'is_active' => false,
            'is_pre_enabled' => true,
            'is_post_enabled' => true,
            'is_tasks_enabled' => false,
            'male_pre_enabled' => true,
            'female_pre_enabled' => true,
            'male_post_enabled' => true,
            'female_post_enabled' => true,
            'male_tasks_enabled' => true,
            'female_tasks_enabled' => true,
            'assessment_windows' => json_encode(['global' => [], 'male' => [], 'female' => []]),
            'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => '']),
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        DB::table('course_questions')->insert([
            'id' => $questionId,
            'course_id' => $courseId,
            'assessment_type' => 'post',
            'question_type' => 'text',
            'prompt' => 'اكتب',
            'options' => json_encode([]),
            'allow_file' => false,
            'points' => 1,
            'correct_answer' => '',
            'attachment_name' => '',
            'attachment_type' => '',
            'attachment_data_url' => '',
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        $existingSubmissionId = (string) str()->uuid();
        DB::table('course_submissions')->insert([
            'id' => $existingSubmissionId,
            'course_id' => $courseId,
            'assessment_type' => 'post',
            'student_id' => null,
            'student_name' => 'طالب أول',
            'login_code' => '8700',
            'manual_score' => 1,
            'submitted_at' => now(),
        ]);
        DB::table('course_submission_answers')->insert([
            'id' => (string) str()->uuid(),
            'submission_id' => $existingSubmissionId,
            'question_id' => $questionId,
            'answer_text' => 'قديم',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/dashboard/assessment-import', [
            'courseId' => $courseId,
            'assessmentType' => 'post',
            'submissions' => [
                [
                    'studentName' => 'طالب أول',
                    'loginId' => '8700',
                    'manualScore' => 8,
                    'answers' => [
                        ['questionId' => $questionId, 'value' => 'جديد'],
                        ['questionId' => '__score_override__', 'value' => '8'],
                    ],
                ],
                [
                    'studentName' => 'طالب ثان',
                    'loginId' => '8701',
                    'manualScore' => null,
                    'answers' => [
                        ['questionId' => $questionId, 'value' => 'مستورد'],
                    ],
                ],
            ],
        ])->assertOk();

        $response->assertJsonCount(2);

        $this->assertDatabaseMissing('course_submission_answers', [
            'submission_id' => $existingSubmissionId,
            'answer_text' => 'قديم',
        ]);

        $this->assertDatabaseHas('course_submissions', [
            'course_id' => $courseId,
            'assessment_type' => 'post',
            'login_code' => '8700',
            'manual_score' => 8,
        ]);

        $this->assertDatabaseHas('course_submissions', [
            'course_id' => $courseId,
            'assessment_type' => 'post',
            'login_code' => '8701',
        ]);

        $this->assertDatabaseHas('course_submission_answers', [
            'question_id' => $questionId,
            'answer_text' => 'جديد',
        ]);

        $this->getJson('/api/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('submissions.0.courseId', $courseId);
    }

    public function test_satisfaction_question_and_response_flows_work(): void
    {
        $courseId = (string) str()->uuid();

        DB::table('courses')->insert([
            'id' => $courseId,
            'title' => 'بعد الدورة',
            'entity_type' => 'course',
            'task_mode' => null,
            'task_template_id' => null,
            'task_template_name' => '',
            'task_template_content' => '',
            'youtube_url' => '',
            'is_active' => false,
            'is_pre_enabled' => true,
            'is_post_enabled' => true,
            'is_tasks_enabled' => false,
            'male_pre_enabled' => true,
            'female_pre_enabled' => true,
            'male_post_enabled' => true,
            'female_post_enabled' => true,
            'male_tasks_enabled' => true,
            'female_tasks_enabled' => true,
            'assessment_windows' => json_encode(['global' => [], 'male' => [], 'female' => []]),
            'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => '']),
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        $questionResponse = $this->postJson('/api/dashboard/satisfaction-questions', [
            'prompt' => 'كيف كانت الدورة؟',
            'type' => 'rating',
            'isRequired' => true,
        ])->assertCreated();

        $questionId = $questionResponse->json('0.id');

        $this->postJson('/api/dashboard/satisfaction-responses', [
            'responses' => [[
                'courseId' => $courseId,
                'questionId' => $questionId,
                'loginCode' => '8800',
                'studentName' => 'طالب رضا',
                'ratingValue' => 5,
                'textValue' => 'ممتاز',
            ]],
        ])->assertOk()->assertJsonPath('0.questionId', $questionId);

        $this->getJson('/api/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('satisfactionQuestions.0.id', $questionId)
            ->assertJsonPath('satisfactionResponses.0.questionId', $questionId)
            ->assertJsonPath('satisfactionResponses.0.ratingValue', 5);

        $this->deleteJson('/api/dashboard/satisfaction-questions/'.$questionId)->assertNoContent();

        $this->assertDatabaseMissing('satisfaction_questions', ['id' => $questionId]);
    }

    public function test_public_student_assessment_submission_flows_work_for_pre_post_and_tasks(): void
    {
        $maleBranchId = DB::table('branches')->where('code', 'male')->value('id');

        Student::query()->create([
            'full_name' => 'طالب المسارات',
            'login_code' => '6500',
            'branch_id' => $maleBranchId,
            'note' => '',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'student',
            'login_code' => '6500',
        ]));

        $courseId = (string) Str::uuid();
        $taskCourseId = (string) Str::uuid();
        $preQuestionId = (string) Str::uuid();
        $postQuestionId = (string) Str::uuid();
        $taskQuestionId = (string) Str::uuid();

        DB::table('courses')->insert([
            [
                'id' => $courseId,
                'title' => 'دورة الطالب',
                'entity_type' => 'course',
                'task_mode' => null,
                'task_template_id' => null,
                'task_template_name' => '',
                'task_template_content' => '',
                'youtube_url' => '',
                'is_active' => true,
                'is_pre_enabled' => true,
                'is_post_enabled' => true,
                'is_tasks_enabled' => false,
                'male_pre_enabled' => true,
                'female_pre_enabled' => true,
                'male_post_enabled' => true,
                'female_post_enabled' => true,
                'male_tasks_enabled' => false,
                'female_tasks_enabled' => false,
                'assessment_windows' => json_encode(['global' => [], 'male' => [], 'female' => []]),
                'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => '']),
                'sort_order' => 0,
                'created_at' => now(),
            ],
            [
                'id' => $taskCourseId,
                'title' => 'المهمة الأدائية',
                'entity_type' => 'task',
                'task_mode' => 'questions',
                'task_template_id' => null,
                'task_template_name' => '',
                'task_template_content' => '',
                'youtube_url' => '',
                'is_active' => false,
                'is_pre_enabled' => false,
                'is_post_enabled' => false,
                'is_tasks_enabled' => true,
                'male_pre_enabled' => false,
                'female_pre_enabled' => false,
                'male_post_enabled' => false,
                'female_post_enabled' => false,
                'male_tasks_enabled' => true,
                'female_tasks_enabled' => true,
                'assessment_windows' => json_encode(['global' => [], 'male' => [], 'female' => []]),
                'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => '']),
                'sort_order' => 1,
                'created_at' => now(),
            ],
        ]);

        DB::table('course_questions')->insert([
            [
                'id' => $preQuestionId,
                'course_id' => $courseId,
                'assessment_type' => 'pre',
                'question_type' => 'text',
                'prompt' => 'سؤال قبلي',
                'options' => json_encode([]),
                'allow_file' => false,
                'points' => 1,
                'correct_answer' => '',
                'attachment_name' => '',
                'attachment_type' => '',
                'attachment_data_url' => '',
                'sort_order' => 0,
                'created_at' => now(),
            ],
            [
                'id' => $postQuestionId,
                'course_id' => $courseId,
                'assessment_type' => 'post',
                'question_type' => 'text',
                'prompt' => 'سؤال بعدي',
                'options' => json_encode([]),
                'allow_file' => false,
                'points' => 2,
                'correct_answer' => '',
                'attachment_name' => '',
                'attachment_type' => '',
                'attachment_data_url' => '',
                'sort_order' => 0,
                'created_at' => now(),
            ],
            [
                'id' => $taskQuestionId,
                'course_id' => $taskCourseId,
                'assessment_type' => 'tasks',
                'question_type' => 'text',
                'prompt' => 'سؤال مهمة',
                'options' => json_encode([]),
                'allow_file' => false,
                'points' => 3,
                'correct_answer' => '',
                'attachment_name' => '',
                'attachment_type' => '',
                'attachment_data_url' => '',
                'sort_order' => 0,
                'created_at' => now(),
            ],
        ]);

        $this->postJson('/api/public/assessment-submissions', [
            'courseId' => $courseId,
            'assessmentType' => 'pre',
            'studentName' => 'طالب المسارات',
            'loginId' => '6500',
            'answers' => [
                ['questionId' => $preQuestionId, 'value' => 'إجابة قبلية'],
            ],
        ])->assertCreated();

        $this->postJson('/api/public/assessment-submissions', [
            'courseId' => $courseId,
            'assessmentType' => 'post',
            'studentName' => 'طالب المسارات',
            'loginId' => '6500',
            'answers' => [
                ['questionId' => $postQuestionId, 'value' => 'إجابة بعدية'],
            ],
        ])->assertCreated();

        $this->postJson('/api/public/assessment-submissions', [
            'courseId' => $taskCourseId,
            'assessmentType' => 'tasks',
            'studentName' => 'طالب المسارات',
            'loginId' => '6500',
            'answers' => [
                ['questionId' => $taskQuestionId, 'value' => 'إجابة المهمة'],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('course_submissions', [
            'course_id' => $courseId,
            'assessment_type' => 'pre',
            'login_code' => '6500',
        ]);

        $this->assertDatabaseHas('course_submissions', [
            'course_id' => $courseId,
            'assessment_type' => 'post',
            'login_code' => '6500',
        ]);

        $this->assertDatabaseHas('course_submissions', [
            'course_id' => $taskCourseId,
            'assessment_type' => 'tasks',
            'login_code' => '6500',
        ]);

        $this->assertDatabaseHas('course_submission_answers', [
            'question_id' => $preQuestionId,
            'answer_text' => 'إجابة قبلية',
        ]);

        $this->assertDatabaseHas('course_submission_answers', [
            'question_id' => $postQuestionId,
            'answer_text' => 'إجابة بعدية',
        ]);

        $this->assertDatabaseHas('course_submission_answers', [
            'question_id' => $taskQuestionId,
            'answer_text' => 'إجابة المهمة',
        ]);
    }

    public function test_public_post_assessment_and_course_scoped_satisfaction_submit_separately(): void
    {
        $maleBranchId = DB::table('branches')->where('code', 'male')->value('id');

        Student::query()->create([
            'full_name' => 'طالب الاستبيان',
            'login_code' => '6600',
            'branch_id' => $maleBranchId,
            'note' => '',
        ]);

        $targetCourseId = (string) Str::uuid();
        $otherCourseId = (string) Str::uuid();
        $postQuestionId = (string) Str::uuid();

        DB::table('courses')->insert([
            [
                'id' => $targetCourseId,
                'title' => 'الدورة المستهدفة',
                'entity_type' => 'course',
                'task_mode' => null,
                'task_template_id' => null,
                'task_template_name' => '',
                'task_template_content' => '',
                'youtube_url' => '',
                'is_active' => true,
                'is_pre_enabled' => true,
                'is_post_enabled' => true,
                'is_tasks_enabled' => false,
                'male_pre_enabled' => true,
                'female_pre_enabled' => true,
                'male_post_enabled' => true,
                'female_post_enabled' => true,
                'male_tasks_enabled' => false,
                'female_tasks_enabled' => false,
                'assessment_windows' => json_encode(['global' => [], 'male' => [], 'female' => []]),
                'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => '']),
                'sort_order' => 0,
                'created_at' => now(),
            ],
            [
                'id' => $otherCourseId,
                'title' => 'دورة أخرى',
                'entity_type' => 'course',
                'task_mode' => null,
                'task_template_id' => null,
                'task_template_name' => '',
                'task_template_content' => '',
                'youtube_url' => '',
                'is_active' => false,
                'is_pre_enabled' => true,
                'is_post_enabled' => true,
                'is_tasks_enabled' => false,
                'male_pre_enabled' => true,
                'female_pre_enabled' => true,
                'male_post_enabled' => true,
                'female_post_enabled' => true,
                'male_tasks_enabled' => false,
                'female_tasks_enabled' => false,
                'assessment_windows' => json_encode(['global' => [], 'male' => [], 'female' => []]),
                'assessment_notification_templates' => json_encode(['pre' => '', 'post' => '', 'tasks' => '']),
                'sort_order' => 1,
                'created_at' => now(),
            ],
        ]);

        DB::table('course_questions')->insert([
            'id' => $postQuestionId,
            'course_id' => $targetCourseId,
            'assessment_type' => 'post',
            'question_type' => 'text',
            'prompt' => 'سؤال بعدي للدورة',
            'options' => json_encode([]),
            'allow_file' => false,
            'points' => 4,
            'correct_answer' => '',
            'attachment_name' => '',
            'attachment_type' => '',
            'attachment_data_url' => '',
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        $questionResponse = $this->postJson('/api/dashboard/satisfaction-questions', [
            'prompt' => 'كيف كانت الدورة المستهدفة؟',
            'type' => 'rating',
            'isRequired' => true,
            'targetScope' => 'course',
            'courseId' => $targetCourseId,
        ])->assertCreated();

        $questionId = $questionResponse->json('0.id');

        $this->assertDatabaseHas('satisfaction_questions', [
            'id' => $questionId,
            'course_id' => $targetCourseId,
        ]);

        $this->assertDatabaseMissing('satisfaction_questions', [
            'course_id' => $otherCourseId,
            'prompt' => 'كيف كانت الدورة المستهدفة؟',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'student',
            'login_code' => '6600',
        ]));

        $this->postJson('/api/public/assessment-submissions', [
            'courseId' => $targetCourseId,
            'assessmentType' => 'post',
            'studentName' => 'طالب الاستبيان',
            'loginId' => '6600',
            'answers' => [
                ['questionId' => $postQuestionId, 'value' => 'إجابة الاختبار البعدي'],
            ],
        ])->assertCreated();

        $this->postJson('/api/public/satisfaction-responses', [
            'responses' => [[
                'courseId' => $targetCourseId,
                'questionId' => $questionId,
                'loginCode' => '6600',
                'studentName' => 'طالب الاستبيان',
                'ratingValue' => 9,
                'textValue' => '',
            ]],
        ])->assertOk()->assertJsonPath('0.questionId', $questionId);

        $this->assertDatabaseHas('course_submissions', [
            'course_id' => $targetCourseId,
            'assessment_type' => 'post',
            'login_code' => '6600',
            'manual_score' => null,
        ]);

        $this->assertDatabaseHas('course_submission_answers', [
            'question_id' => $postQuestionId,
            'answer_text' => 'إجابة الاختبار البعدي',
        ]);

        $this->assertDatabaseHas('satisfaction_responses', [
            'course_id' => $targetCourseId,
            'question_id' => $questionId,
            'login_code' => '6600',
            'rating_value' => 9,
        ]);
    }

    public function test_public_final_exam_submission_flow_works(): void
    {
        $questionId = (string) Str::uuid();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'student',
            'login_code' => '6700',
        ]));

        DB::table('final_exam_questions')->insert([
            'id' => $questionId,
            'branch_code' => 'male',
            'question_type' => 'multiple',
            'prompt' => 'سؤال نهائي عام',
            'options' => json_encode(['صح', 'خطأ'], JSON_UNESCAPED_UNICODE),
            'allow_file' => false,
            'points' => 3,
            'correct_answer' => 'صح',
            'attachment_name' => '',
            'attachment_type' => '',
            'attachment_data_url' => '',
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        DB::table('final_exam_settings')->updateOrInsert(
            ['branch_code' => 'male'],
            [
                'is_enabled' => true,
                'closes_at' => now()->addMinutes(45),
                'notification_template' => '',
            ],
        );

        $response = $this->postJson('/api/public/final-exam/submissions', [
            'branchCode' => 'male',
            'studentName' => 'طالب النهائي العام',
            'loginCode' => '6700',
            'answers' => [[
                'questionId' => $questionId,
                'value' => 'صح',
            ]],
        ])->assertCreated();

        $submissionId = $response->json('id');

        $this->assertDatabaseHas('final_exam_submissions', [
            'id' => $submissionId,
            'branch_code' => 'male',
            'login_code' => '6700',
        ]);

        $this->assertDatabaseHas('final_exam_submission_answers', [
            'submission_id' => $submissionId,
            'question_id' => $questionId,
            'answer_text' => 'صح',
        ]);
    }

    public function test_final_exam_management_and_submission_flows_work(): void
    {
        $questionResponse = $this->postJson('/api/dashboard/final-exam/questions', [
            'branchCode' => 'male',
            'prompt' => 'سؤال نهائي',
            'type' => 'truefalse',
            'options' => [],
            'allowFile' => false,
            'points' => 3,
            'correctAnswer' => 'صح',
        ])->assertCreated();

        $questionId = $questionResponse->json('id');

        $this->putJson('/api/dashboard/final-exam/settings/male', [
            'isEnabled' => true,
            'closesAt' => now()->addMinutes(45)->toISOString(),
        ])->assertNoContent();

        $this->putJson('/api/dashboard/final-exam/settings/male/notification-template', [
            'notificationTemplate' => 'تم فتح الاختبار النهائي',
        ])->assertNoContent();

        $submissionResponse = $this->postJson('/api/dashboard/final-exam/submissions', [
            'branchCode' => 'male',
            'studentName' => 'طالب نهائي',
            'loginCode' => '9900',
            'answers' => [[
                'questionId' => $questionId,
                'value' => 'صح',
            ]],
        ])->assertCreated();

        $submissionId = $submissionResponse->json('id');

        $this->putJson('/api/dashboard/final-exam/submissions/'.$submissionId.'/manual-score', [
            'score' => 9,
        ])->assertNoContent();

        $this->postJson('/api/dashboard/final-exam/questions/copy', [
            'from' => 'male',
            'to' => 'female',
            'move' => false,
        ])->assertNoContent();

        $snapshot = $this->getJson('/api/dashboard/snapshot')->assertOk();

        $snapshot
            ->assertJsonPath('finalExamQuestions.0.id', $questionId)
            ->assertJsonPath('finalExamSubmissions.0.id', $submissionId)
            ->assertJsonPath('finalExamSubmissions.0.manualScore', 9)
            ->assertJsonPath('finalExamSettings.male.isEnabled', true)
            ->assertJsonPath('finalExamSettings.male.notificationTemplate', 'تم فتح الاختبار النهائي');

        $this->putJson('/api/dashboard/final-exam/settings/male', [
            'isEnabled' => true,
            'closesAt' => now()->subMinute()->toISOString(),
        ])->assertNoContent();

        $this->postJson('/api/dashboard/final-exam/submissions', [
            'branchCode' => 'male',
            'studentName' => 'طالب نهائي متأخر',
            'loginCode' => '9901',
            'answers' => [[
                'questionId' => $questionId,
                'value' => 'صح',
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.branchCode.0', 'انتهى وقت الإرسال أو أن الاختبار النهائي غير متاح حاليًا.');

        $this->assertDatabaseHas('final_exam_questions', ['branch_code' => 'female']);

        $this->deleteJson('/api/dashboard/final-exam/questions/'.$questionId)->assertNoContent();

        $this->assertDatabaseMissing('final_exam_questions', ['id' => $questionId]);
    }
}
