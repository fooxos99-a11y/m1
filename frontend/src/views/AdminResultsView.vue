<template>
  <div
    class="results-view"
    :class="{ 'results-view--embedded': embedded }"
  >
    <div
      v-if="isAttendanceMode"
      class="prep-layout"
    >
      <section class="prep-card">
        <div class="prep-card__head">
          <div>
            <h1 class="prep-card__title">
              التحضير
            </h1>
            <div
              v-if="saveStatusText"
              class="prep-card__status"
              :class="{ 'prep-card__status--saving': isSavingAttendance }"
            >
              {{ saveStatusText }}
            </div>
          </div>
          <AppButton
            v-if="!embedded"
            variant="plain"
            :to="{ name: 'dashboard' }"
          >
            رجوع للوحة
          </AppButton>
        </div>

        <div class="prep-filters">
          <div
            v-if="!managedBranchId"
            class="prep-field"
          >
            <label class="prep-field__label">الفرع</label>
            <AppSelect
              v-model="attendanceBranchId"
              :items="branchOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="prep-select"
            />
          </div>

          <div class="prep-field prep-field--wide">
            <label class="prep-field__label">الدورة / المهام</label>
            <AppSelect
              v-model="attendanceCourseId"
              :items="courseOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="prep-select"
            >
              <template
                v-if="canManageCourses"
                #item="{ item, on, attrs }"
              >
                <div
                  class="results-course-option"
                  v-bind="attrs"
                  v-on="on"
                >
                  <span class="results-course-option__label">{{ item.label }}</span>
                  <AppRawButton
                    type="button"
                    class="results-course-option__delete"
                    :disabled="deletingCourseId === item.value"
                    :aria-label="`حذف ${item.course?.entityType === 'task' ? 'المهمة' : 'الدورة'} ${item.label}`"
                    @mousedown.stop.prevent
                    @click.stop.prevent="requestCourseDelete(item.course)"
                  >
                    <i
                      class="fa-solid fa-trash-can"
                      aria-hidden="true"
                    />
                  </AppRawButton>
                </div>
              </template>
            </AppSelect>
          </div>

          <AppRawButton
            type="button"
            class="attendance-toggle prep-filters__toggle"
            :class="{
              'attendance-toggle--active': allVisibleChecked,
              'attendance-toggle--partial': visibleCheckedCount > 0 && !allVisibleChecked,
            }"
            :disabled="!attendanceStudents.length || isSavingAttendance"
            @click="toggleVisibleAttendance"
          >
            <span class="attendance-toggle__dot" />
          </AppRawButton>
        </div>

        <div class="prep-table-wrap">
          <table class="prep-table">
            <thead>
              <tr>
                <th>الاسم</th>
                <th>رقم الدخول</th>
                <th>حاضر</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!displayedAttendanceStudents.length">
                <td
                  colspan="3"
                  class="prep-table__empty"
                >
                  لا يوجد معلمون في هذا الفرع.
                </td>
              </tr>
              <tr
                v-for="student in displayedAttendanceStudents"
                :key="student.id"
              >
                <td class="prep-table__name">
                  {{ student.name }}
                </td>
                <td class="prep-table__login">
                  {{ student.loginId || '---' }}
                </td>
                <td class="prep-table__status">
                  <AppRawButton
                    type="button"
                    class="attendance-toggle"
                    :class="{ 'attendance-toggle--active': attendanceChecked.includes(student.id) }"
                    :disabled="isSavingAttendance"
                    @click="toggleAttendance(student.id)"
                  >
                    <span class="attendance-toggle__dot" />
                  </AppRawButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <section
      v-else
      class="results-board"
    >
      <section class="results-shell">
        <div class="results-shell__header">
          <h1 class="results-shell__title">
            لوحة النتائج
          </h1>
        </div>

        <div class="results-filters">
          <div class="results-filter-field results-filter-field--wide">
            <label class="results-filter-field__label">القسم</label>
            <AppSelect
              v-model="resultsCourseId"
              :items="resultsCourseOptions"
              item-text="label"
              item-value="value"
              placeholder="اختر"
              persistent-placeholder
              dense
              outlined
              hide-details
              class="results-select"
            />
          </div>

          <div
            v-if="!managedBranchId"
            class="results-filter-field"
          >
            <label class="results-filter-field__label">الفرع</label>
            <AppSelect
              v-model="resultsBranchId"
              :items="branchOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="results-select"
            />
          </div>

          <div
            v-if="isCourseResultsSection"
            class="results-filter-field"
          >
            <label class="results-filter-field__label">نوع البيانات</label>
            <AppSelect
              v-model="resultsType"
              :items="resultsTypeOptions"
              item-text="label"
              item-value="value"
              placeholder="اختر"
              persistent-placeholder
              dense
              outlined
              hide-details
              class="results-select"
            />
          </div>

          <div
            v-if="showStudentFilter"
            class="results-filter-field"
          >
            <label class="results-filter-field__label">{{ studentFilterLabel }}</label>
            <AppSelect
              v-model="studentFilter"
              :items="studentFilterOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="results-select"
            />
          </div>
        </div>
      </section>

      <section class="results-list-shell">
        <div
          v-if="isAttendanceResultsType && hasSelectedResultsSection"
          class="results-attendance-summary"
        >
          <span>عدد الحضور: {{ attendancePresentCount }}</span>
          <span>عدد الغياب: {{ attendanceAbsentCount }}</span>
        </div>

        <div
          v-if="dashboardSnapshot === null"
          class="results-empty-state"
        >
          جارٍ تحميل البيانات...
        </div>
        <div
          v-else-if="!hasSelectedResultsSection"
          class="results-empty-state"
        >
          {{ isCourseResultsSection && !resultsType ? 'اختر نوع البيانات لعرض النتائج.' : 'لا توجد عناصر متاحة لهذا النوع من البيانات.' }}
        </div>
        <div
          v-else-if="displayedRows.length === 0"
          class="results-empty-state"
        >
          لا توجد بيانات مطابقة للفلاتر الحالية.
        </div>
        <div
          v-else
          class="results-list"
        >
          <article
            v-for="row in displayedRows"
            :key="row.key"
            class="results-entry"
          >
            <div class="results-entry__actions">
              <template v-if="isAttendanceResultsType">
                <span
                  class="results-entry__status-pill"
                  :class="row.present ? 'results-entry__status-pill--present' : 'results-entry__status-pill--absent'"
                >
                  {{ row.present ? 'حاضر' : 'غائب' }}
                </span>
              </template>

              <template v-else>
                <AppRawButton
                  v-if="row.attachment"
                  type="button"
                  class="results-entry__attachment"
                  :title="row.attachment.fileName"
                  @click="openAttachmentPreview(row.attachment)"
                >
                  <v-icon small>
                    mdi-paperclip
                  </v-icon>
                </AppRawButton>
                <AppRawButton
                  type="button"
                  class="results-entry__preview"
                  :disabled="!row.submission"
                  @click="openResultDialog(row)"
                >
                  <v-icon small>
                    mdi-eye-outline
                  </v-icon>
                </AppRawButton>
                <span
                  class="results-entry__score-pill"
                  :class="{ 'results-entry__score-pill--empty': !row.submission }"
                >
                  {{ row.scoreLabel }}
                </span>
              </template>
            </div>

            <div class="results-entry__identity">
              <div class="results-entry__name">
                {{ row.name }}
              </div>
              <div class="results-entry__login">
                {{ row.loginId || '---' }}
              </div>
              <div
                v-if="row.attachment"
                class="results-entry__attachment-name"
              >
                {{ row.attachment.fileName }}
              </div>
            </div>
          </article>
        </div>
      </section>

      <AppDialog
        v-model="resultDialogOpen"
        max-width="980"
        scrollable
      >
        <div class="results-detail-dialog">
          <div class="results-detail-dialog__header">
            <div v-if="!isTaskResultsSection">
              <div class="results-detail-dialog__eyebrow">
                {{ selectedResultsSectionLabel }}
              </div>
              <div class="results-detail-dialog__title">
                {{ activeResultsTypeLabel }}: الإجابة
              </div>
            </div>
            <div class="results-detail-dialog__actions">
              <AppRawButton
                v-if="isTaskResultsSection && selectedResultRow && selectedResultRow.submission"
                type="button"
                class="results-detail-dialog__download-btn"
                title="تحميل PDF"
                @click="downloadResultAsPdf"
              >
                <v-icon small>
                  mdi-file-pdf-box
                </v-icon>
                تحميل PDF
              </AppRawButton>
              <AppRawButton
                type="button"
                class="results-detail-dialog__close"
                @click="closeResultDialog"
              >
                <v-icon small>
                  mdi-close
                </v-icon>
              </AppRawButton>
            </div>
          </div>

          <div
            v-if="(isTaskResultsSection || isFinalExamResultsSection) && selectedResultRow && selectedResultRow.submission"
            class="results-score-editor"
          >
            <span class="results-score-editor__label">الدرجة</span>
            <div class="results-score-editor__controls">
              <input
                v-model.number="scoreEditValue"
                type="number"
                min="0"
                class="results-score-editor__input"
                :placeholder="selectedResultsTotalPoints ? String(selectedResultsTotalPoints) : '0'"
                @keydown.enter="handleSaveScore"
              >
              <span
                v-if="selectedResultsTotalPoints"
                class="results-score-editor__total"
              >/ {{ selectedResultsTotalPoints }}</span>
              <AppRawButton
                type="button"
                class="results-score-editor__save-btn"
                :disabled="isSavingScore"
                @click="handleSaveScore"
              >
                {{ isSavingScore ? 'جارٍ الحفظ...' : 'حفظ الدرجة' }}
              </AppRawButton>
            </div>
          </div>

          <div
            v-if="!selectedResultRow"
            class="results-empty-state results-empty-state--dialog"
          >
            لا توجد تفاصيل متاحة لهذه النتيجة.
          </div>
          <div
            v-else
            class="results-detail-dialog__body"
            :class="{ 'results-detail-dialog__body--tasks': isTaskResultsSection }"
          >
            <article
              v-for="detail in resultDetailCards"
              :key="detail.key"
              class="results-answer-card"
            >
              <div
                v-if="!isTaskResultsSection"
                class="results-answer-card__question"
              >
                {{ detail.index }}. {{ detail.prompt }}
                <span class="results-answer-card__points">الدرجة: {{ detail.points }}</span>
              </div>

              <div
                v-if="!detail.hideCorrectAnswer"
                class="results-answer-card__line results-answer-card__line--correct"
              >
                الإجابة الصحيحة: {{ detail.correctAnswer }}
              </div>

              <div
                v-if="detail.statusText"
                class="results-answer-card__status"
                :class="detail.isCorrect ? 'results-answer-card__status--correct' : 'results-answer-card__status--incorrect'"
              >
                {{ detail.statusText }}
              </div>

              <div
                v-if="detail.studentAnswerHtml"
                class="results-answer-card__document"
              >
                <div
                  v-if="!isTaskResultsSection"
                  class="results-answer-card__answer-label results-answer-card__answer-label--student"
                >
                  إجابة المعلم
                </div>
                <RichTextDocumentView
                  :value="detail.studentAnswerHtml"
                  :min-height="isTaskResultsSection ? '0' : '420px'"
                />
              </div>

              <div
                v-else
                class="results-answer-card__line results-answer-card__line--student"
              >
                إجابة المعلم: {{ detail.studentAnswer }}
              </div>

              <AppRawButton
                v-if="detail.attachment"
                type="button"
                class="results-answer-card__attachment"
                @click="openAttachmentPreview(detail.attachment)"
              >
                <v-icon small>
                  mdi-paperclip
                </v-icon>
                {{ detail.attachment.fileName }}
              </AppRawButton>
            </article>
          </div>
        </div>
      </AppDialog>

      <AppDialog
        v-model="courseDeleteDialogOpen"
        max-width="520"
      >
        <div class="results-delete-dialog">
          <h2 class="results-delete-dialog__title">
            {{ courseDeleteEntityLabel === 'مهمة' ? 'تأكيد حذف المهمة' : 'تأكيد حذف الدورة' }}
          </h2>
          <p class="results-delete-dialog__text">
            هل أنت متأكد من حذف {{ courseDeleteEntityLabel }}
            <strong>{{ courseDeleteTitle || 'المحددة' }}</strong>
            ؟
          </p>
          <div class="results-delete-dialog__actions">
            <AppButton
              variant="secondary"
              @click="closeCourseDeleteDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="danger"
              :loading="isDeletingCourse"
              @click="confirmCourseDelete"
            >
              {{ isDeletingCourse ? 'جارٍ الحذف...' : 'حذف' }}
            </AppButton>
          </div>
        </div>
      </AppDialog>
    </section>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import RichTextDocumentView from '../components/RichTextDocumentView.vue';
import {
  AppButton, AppDialog, AppRawButton, AppSelect,
} from '../components/ui';
import { setAssessmentManualScore, setFinalExamManualScore } from '../services/api';
import { sanitizeRichTextHtml } from '../utils/documentContent';

const RESULTS_TASKS_VALUE = '__tasks__';
const RESULTS_FINAL_EXAM_VALUE = '__final_exam__';

const normalizeAnswer = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();

export default {
  name: 'AdminResultsView',
  components: {
    AppButton,
    AppDialog,
    AppRawButton,
    RichTextDocumentView,
    AppSelect,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
    panelMode: {
      type: String,
      default: '',
    },
  },
  data() {
    return {
      attendanceCourseId: '',
      attendanceBranchId: 'male',
      attendanceChecked: [],
      attendanceSaveTimer: null,
      isSavingAttendance: false,
      saveStatusText: '',
      resultsCourseId: '',
      resultsBranchId: 'male',
      resultsType: '',
      studentFilter: '',
      resultDialogOpen: false,
      selectedResultLoginId: '',
      scoreEditValue: null,
      isSavingScore: false,
      courseDeleteDialogOpen: false,
      courseDeleteId: '',
      courseDeleteTitle: '',
      courseDeleteEntityType: 'course',
      deletingCourseId: '',
      branchOptions: [
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
      ],
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'currentUser']),
    managedBranchId() {
      if (this.currentUser?.role === 'male_manager') {
        return 'male';
      }

      if (this.currentUser?.role === 'female_manager') {
        return 'female';
      }

      return '';
    },
    canManageCourses() {
      return this.currentUser?.role === 'admin';
    },
    isDeletingCourse() {
      return Boolean(this.deletingCourseId);
    },
    effectiveAttendanceBranchId() {
      return this.managedBranchId || this.attendanceBranchId;
    },
    effectiveResultsBranchId() {
      return this.managedBranchId || this.resultsBranchId;
    },
    courses() {
      return this.dashboardSnapshot?.courses || [];
    },
    students() {
      return this.dashboardSnapshot?.students || [];
    },
    attendance() {
      return this.dashboardSnapshot?.attendance || [];
    },
    submissions() {
      return this.dashboardSnapshot?.submissions || [];
    },
    finalExamQuestions() {
      return this.dashboardSnapshot?.finalExamQuestions || [];
    },
    finalExamSubmissions() {
      return this.dashboardSnapshot?.finalExamSubmissions || [];
    },
    courseOptions() {
      return this.courses.map((course) => ({
        label: course.title,
        value: course.id,
        course,
      }));
    },
    attendanceEligibleCourses() {
      return this.courses.filter((course) => course.entityType !== 'task');
    },
    assessmentEligibleCourses() {
      return this.courses.filter((course) => course.entityType !== 'task');
    },
    taskEligibleCourses() {
      return this.courses.filter((course) => course.entityType === 'task');
    },
    resultsTypeOptions() {
      return [
        { label: 'التحضير', value: 'attendance' },
        { label: 'الاختبار القبلي', value: 'pre' },
        { label: 'الاختبار البعدي', value: 'post' },
      ];
    },
    resultsCourseOptions() {
      const courseOptions = this.assessmentEligibleCourses.map((course) => ({
        label: course.title,
        value: course.id,
        course,
      }));
      const taskOptions = this.taskEligibleCourses.map((course) => ({
        label: course.title || 'مهمة أدائية',
        value: `task:${course.id}`,
        course,
      }));
      const specialOptions = [
        ...taskOptions,
        { label: 'الاختبار النهائي', value: RESULTS_FINAL_EXAM_VALUE },
      ];

      return courseOptions.concat(specialOptions);
    },
    selectedResultsCourse() {
      return this.courses.find((course) => course.id === this.resultsCourseId) || null;
    },
    selectedTaskResultCourse() {
      if (!this.isTaskResultsSection) {
        return null;
      }

      const selectedTaskId = this.resultsCourseId.startsWith('task:') ? this.resultsCourseId.slice(5) : '';
      if (selectedTaskId) {
        return this.taskEligibleCourses.find((course) => course.id === selectedTaskId) || null;
      }

      const courseId = this.selectedResultRow?.submission?.courseId || '';
      return this.taskEligibleCourses.find((course) => course.id === courseId) || null;
    },
    isTaskResultsSection() {
      return this.resultsCourseId === RESULTS_TASKS_VALUE || this.resultsCourseId.startsWith('task:');
    },
    isFinalExamResultsSection() {
      return this.resultsCourseId === RESULTS_FINAL_EXAM_VALUE;
    },
    isCourseResultsSection() {
      return Boolean(this.resultsCourseId) && !this.isTaskResultsSection && !this.isFinalExamResultsSection;
    },
    hasSelectedResultsSection() {
      return this.isTaskResultsSection
        || this.isFinalExamResultsSection
        || (Boolean(this.selectedResultsCourse) && Boolean(this.resultsType));
    },
    selectedResultsSectionLabel() {
      if (this.isTaskResultsSection) {
        return this.selectedTaskResultCourse?.title || 'المهام الأدائية';
      }

      if (this.isFinalExamResultsSection) {
        return 'الاختبار النهائي';
      }

      return this.selectedResultsCourse?.title || 'النتائج';
    },
    courseDeleteEntityLabel() {
      return this.courseDeleteEntityType === 'task' ? 'مهمة' : 'دورة';
    },
    selectedResultsQuestions() {
      if (this.isFinalExamResultsSection) {
        return this.finalExamQuestions.filter((question) => question.branchCode === this.effectiveResultsBranchId);
      }

      if (this.isTaskResultsSection) {
        const submissionCourseId = this.selectedResultRow?.submission?.courseId || '';
        const taskCourse = this.taskEligibleCourses.find((course) => course.id === submissionCourseId) || null;

        if (taskCourse) {
          return taskCourse.taskQuestions || [];
        }

        return this.taskEligibleCourses.flatMap((course) => course.taskQuestions || []);
      }

      if (!this.selectedResultsCourse) {
        return [];
      }

      if (this.resultsType === 'pre') {
        return this.selectedResultsCourse.preQuestions || [];
      }

      if (this.resultsType === 'post') {
        return this.selectedResultsCourse.postQuestions || [];
      }

      return [];
    },
    selectedResultsTotalPoints() {
      return this.selectedResultsQuestions.reduce((sum, question) => sum + Number(question.points || 0), 0);
    },
    isAttendanceMode() {
      return this.panelMode === 'attendance';
    },
    isAttendanceResultsType() {
      return this.isCourseResultsSection && this.resultsType === 'attendance';
    },
    showStudentFilter() {
      return this.isAttendanceResultsType;
    },
    studentFilterLabel() {
      return 'حالة المعلمين';
    },
    attendanceStudents() {
      return this.students.filter((student) => student.branchId === this.effectiveAttendanceBranchId);
    },
    displayedAttendanceStudents() {
      return [...this.attendanceStudents]
        .sort((left, right) => {
          const leftChecked = this.attendanceChecked.includes(left.id) ? 1 : 0;
          const rightChecked = this.attendanceChecked.includes(right.id) ? 1 : 0;

          if (leftChecked !== rightChecked) {
            return rightChecked - leftChecked;
          }

          return (left.name || '').localeCompare(right.name || '', 'ar');
        });
    },
    visibleCheckedCount() {
      return this.attendanceStudents.filter((student) => this.attendanceChecked.includes(student.id)).length;
    },
    allVisibleChecked() {
      return this.attendanceStudents.length > 0 && this.visibleCheckedCount === this.attendanceStudents.length;
    },
    selectedAttendanceRecords() {
      return this.attendance.filter((record) => record.courseId === this.attendanceCourseId);
    },
    activeResultsTypeLabel() {
      if (this.isTaskResultsSection) {
        return this.selectedTaskResultCourse?.title || 'المهام الأدائية';
      }

      if (this.isFinalExamResultsSection) {
        return 'الاختبار النهائي';
      }

      return this.resultsTypeOptions.find((option) => option.value === this.resultsType)?.label || 'النتائج';
    },
    activeBranchLabel() {
      return this.branchOptions.find((option) => option.value === this.effectiveResultsBranchId)?.label || 'الكل';
    },
    studentFilterOptions() {
      if (this.isAttendanceResultsType) {
        return [
          { label: 'جميع المعلمين', value: 'all' },
          { label: 'الحاضرين', value: 'present' },
          { label: 'الغائبين', value: 'absent' },
          { label: 'غائبين 3 مرات فأكثر', value: 'absent3plus' },
        ];
      }

      return this.resultsBranchStudents.map((student) => ({
        label: student.name,
        value: student.loginId,
      }));
    },
    resultsBranchStudents() {
      return this.students
        .filter((student) => student.branchId === this.effectiveResultsBranchId)
        .sort((left, right) => (left.name || '').localeCompare(right.name || '', 'ar'));
    },
    attendanceRows() {
      const presentLogins = new Set(this.attendance.filter((record) => record.courseId === this.resultsCourseId).map((record) => record.loginId).filter(Boolean));

      return this.resultsBranchStudents.map((student) => ({
        key: `attendance-${student.id}`,
        name: student.name,
        loginId: student.loginId,
        present: presentLogins.has(student.loginId),
        absenceCount: this.countStudentAbsences(student.loginId),
      }));
    },
    attendancePresentCount() {
      return this.attendanceRows.filter((row) => row.present).length;
    },
    attendanceAbsentCount() {
      return this.attendanceRows.filter((row) => !row.present).length;
    },
    assessmentRows() {
      return this.resultsBranchStudents.map((student) => {
        const submission = this.submissions.find((item) => (
          item.courseId === this.resultsCourseId
          && item.assessmentType === this.resultsType
          && item.loginId === student.loginId
        )) || null;

        const score = submission ? this.resolveSubmissionScore(submission) : null;

        return {
          key: `submission-${student.id}`,
          name: student.name,
          loginId: student.loginId,
          submission,
          attachment: this.resolveSubmissionAttachment(submission),
          score,
          scoreLabel: this.formatRowScore(score, submission),
        };
      });
    },
    taskRows() {
      const taskCourses = this.selectedTaskResultCourse ? [this.selectedTaskResultCourse] : this.taskEligibleCourses;

      return taskCourses.flatMap((course) => this.resultsBranchStudents.map((student) => {
        const submission = this.submissions.find((item) => (
          item.courseId === course.id
          && item.assessmentType === 'tasks'
          && item.loginId === student.loginId
        )) || null;
        const score = this.resolveSubmissionScore(submission);

        return {
          key: `task-${course.id}-${student.id}`,
          name: student.name,
          loginId: student.loginId,
          sectionLabel: course.title,
          submission,
          attachment: this.resolveSubmissionAttachment(submission),
          score,
          scoreLabel: submission ? `${course.title}: ${this.formatRowScore(score, submission)}` : `${course.title}: غير مرسل`,
        };
      }));
    },
    finalExamRows() {
      return this.resultsBranchStudents.map((student) => {
        const submission = this.finalExamSubmissions.find((item) => (
          item.branchCode === this.effectiveResultsBranchId
          && item.loginCode === student.loginId
        )) || null;
        const score = this.resolveSubmissionScore(submission);

        return {
          key: `final-${student.id}`,
          name: student.name,
          loginId: student.loginId,
          submission,
          attachment: this.resolveSubmissionAttachment(submission),
          score,
          scoreLabel: this.formatRowScore(score, submission),
        };
      });
    },
    displayedRows() {
      const source = this.isFinalExamResultsSection
        ? this.finalExamRows
        : (this.isTaskResultsSection
          ? this.taskRows
          : (this.isAttendanceResultsType ? this.attendanceRows : this.assessmentRows));

      return source.filter((row) => {
        if (!this.studentFilter || this.studentFilter === 'all') {
          return true;
        }

        if (this.isAttendanceResultsType) {
          if (this.studentFilter === 'present') {
            return row.present;
          }

          if (this.studentFilter === 'absent') {
            return !row.present;
          }

          if (this.studentFilter === 'absent3plus') {
            return row.absenceCount >= 3;
          }

          return true;
        }

        return row.loginId === this.studentFilter;
      });
    },
    selectedResultRow() {
      return this.displayedRows.find((row) => row.key === this.selectedResultLoginId) || null;
    },
    resultDetailCards() {
      if (!this.selectedResultRow?.submission) {
        return [];
      }

      const answerMap = new Map((this.selectedResultRow.submission.answers || []).map((answer) => [answer.questionId, answer]));

      if (this.selectedResultsQuestions.length > 0) {
        return this.selectedResultsQuestions.map((question, index) => {
          const answer = answerMap.get(question.id) || null;
          const studentAnswer = this.resolveStudentAnswer(answer);
          const taskMode = this.selectedTaskResultCourse?.taskMode || this.selectedResultsCourse?.taskMode || '';
          const studentAnswerHtml = this.isTaskResultsSection
            && taskMode === 'document'
            && !answer?.fileName
            && String(answer?.value || '').trim()
            ? String(answer.value)
            : '';
          const correctAnswer = this.resolveCorrectAnswer(question);
          const isComparable = Boolean(question.correctAnswer);
          const isCorrect = isComparable ? normalizeAnswer(studentAnswer) === normalizeAnswer(correctAnswer) : null;

          return {
            key: `${question.id}-${index}`,
            index: index + 1,
            prompt: question.prompt,
            points: question.points || 0,
            correctAnswer,
            hideCorrectAnswer: this.isTaskResultsSection && taskMode === 'document',
            studentAnswer,
            studentAnswerHtml,
            attachment: this.resolveAnswerAttachment(answer),
            isCorrect,
            statusText: isCorrect === null ? '' : (isCorrect ? 'صحيحة' : 'غير صحيحة'),
          };
        });
      }

      return (this.selectedResultRow.submission.answers || []).map((answer, index) => ({
        key: `${answer.questionId || index}`,
        index: index + 1,
        prompt: `السؤال ${index + 1}`,
        points: '-',
        correctAnswer: 'لا توجد إجابة مرجعية',
        hideCorrectAnswer: this.isTaskResultsSection && this.selectedTaskResultCourse?.taskMode === 'document',
        studentAnswer: this.resolveStudentAnswer(answer),
        studentAnswerHtml: this.isTaskResultsSection
          && this.selectedTaskResultCourse?.taskMode === 'document'
          && !answer?.fileName
          && String(answer?.value || '').trim()
          ? String(answer.value)
          : '',
        attachment: this.resolveAnswerAttachment(answer),
        isCorrect: null,
        statusText: '',
      }));
    },
  },
  watch: {
    courseOptions: {
      immediate: true,
      handler(options) {
        if (!this.attendanceCourseId || !options.some((option) => option.value === this.attendanceCourseId)) {
          this.attendanceCourseId = options[0]?.value || '';
        }
      },
    },
    resultsCourseOptions: {
      immediate: true,
      handler(options) {
        if (this.resultsCourseId && !options.some((option) => option.value === this.resultsCourseId)) {
          this.resultsCourseId = '';
        }
      },
    },
    resultsType() {
      if (!this.studentFilterOptions.some((option) => option.value === this.studentFilter)) {
        this.studentFilter = this.studentFilterOptions[0]?.value || '';
      }

      this.closeResultDialog();
    },
    studentFilterOptions: {
      immediate: true,
      handler(options) {
        if (!options.some((option) => option.value === this.studentFilter)) {
          this.studentFilter = options[0]?.value || '';
        }
      },
    },
    selectedAttendanceRecords: {
      immediate: true,
      handler(records) {
        this.attendanceChecked = records.map((record) => {
          const student = this.students.find((item) => item.loginId === record.loginId);

          return student?.id || null;
        }).filter(Boolean);
      },
    },
    resultsCourseId() {
      if (!this.studentFilterOptions.some((option) => option.value === this.studentFilter)) {
        this.studentFilter = this.studentFilterOptions[0]?.value || '';
      }

      this.closeResultDialog();
    },
    resultsBranchId() {
      this.studentFilter = this.studentFilterOptions[0]?.value || '';
      this.closeResultDialog();
    },
    resultDialogOpen(opened) {
      if (opened && this.selectedResultRow?.submission) {
        const current = this.selectedResultRow.submission.manualScore;
        this.scoreEditValue = current !== null && current !== undefined ? Number(current) : null;
      }
    },
  },
  created() {
    if (this.managedBranchId) {
      this.attendanceBranchId = this.managedBranchId;
      this.resultsBranchId = this.managedBranchId;
    }

    this.loadDashboardSnapshot();

  },
  beforeDestroy() {
    if (this.attendanceSaveTimer) {
      clearTimeout(this.attendanceSaveTimer);
    }
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot', 'setManualAttendance', 'deleteCourse']),
    requestCourseDelete(course) {
      if (!this.canManageCourses || !course?.id) {
        return;
      }

      this.courseDeleteId = course.id;
      this.courseDeleteTitle = course.title || '';
      this.courseDeleteEntityType = course.entityType === 'task' ? 'task' : 'course';
      this.courseDeleteDialogOpen = true;
    },
    closeCourseDeleteDialog() {
      this.courseDeleteDialogOpen = false;
      this.courseDeleteId = '';
      this.courseDeleteTitle = '';
      this.courseDeleteEntityType = 'course';
      this.deletingCourseId = '';
    },
    async confirmCourseDelete() {
      if (!this.courseDeleteId) {
        return;
      }

      const courseId = this.courseDeleteId;
      this.deletingCourseId = courseId;

      try {
        await this.deleteCourse(courseId);
        this.$toast.success(this.courseDeleteEntityType === 'task' ? 'تم حذف المهمة' : 'تم حذف الدورة');
        this.closeCourseDeleteDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || (this.courseDeleteEntityType === 'task' ? 'تعذر حذف المهمة' : 'تعذر حذف الدورة'));
      } finally {
        this.deletingCourseId = '';
      }
    },
    countStudentAbsences(loginId) {
      if (!loginId) {
        return 0;
      }

      const eligibleCourseIds = this.attendanceEligibleCourses.map((course) => course.id);
      const presentCourseIds = new Set(this.attendance.filter((record) => record.loginId === loginId).map((record) => record.courseId));

      return eligibleCourseIds.filter((courseId) => !presentCourseIds.has(courseId)).length;
    },
    toggleAttendance(studentId) {
      this.attendanceChecked = this.attendanceChecked.includes(studentId)
        ? this.attendanceChecked.filter((id) => id !== studentId)
        : [...this.attendanceChecked, studentId];

      this.queueAttendanceSave();
    },
    toggleVisibleAttendance() {
      const visibleStudentIds = this.attendanceStudents.map((student) => student.id);

      if (this.allVisibleChecked) {
        this.attendanceChecked = this.attendanceChecked.filter((id) => !visibleStudentIds.includes(id));
      } else {
        const checkedIds = new Set(this.attendanceChecked);

        visibleStudentIds.forEach((studentId) => checkedIds.add(studentId));
        this.attendanceChecked = Array.from(checkedIds);
      }

      this.queueAttendanceSave();
    },
    queueAttendanceSave() {
      if (!this.attendanceCourseId) {
        return;
      }

      this.saveStatusText = 'سيتم الحفظ تلقائيًا...';

      if (this.attendanceSaveTimer) {
        clearTimeout(this.attendanceSaveTimer);
      }

      this.attendanceSaveTimer = setTimeout(() => {
        this.attendanceSaveTimer = null;
        this.saveAttendance();
      }, 250);
    },
    async saveAttendance() {
      try {
        this.isSavingAttendance = true;
        this.saveStatusText = 'جارٍ حفظ التحضير...';
        const presentStudents = this.students.filter((student) => this.attendanceChecked.includes(student.id));
        await this.setManualAttendance({
          courseId: this.attendanceCourseId,
          presentStudents: presentStudents.map((student) => ({
            loginId: student.loginId,
            studentName: student.name,
            studentId: student.id,
          })),
        });
        this.saveStatusText = 'تم حفظ التحضير';
      } catch (error) {
        this.saveStatusText = 'تعذر حفظ التحضير';
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ الحضور');
      } finally {
        this.isSavingAttendance = false;
      }
    },
    resolveSubmissionScore(submission) {
      if (!submission) {
        return null;
      }

      if (submission.manualScore !== null && submission.manualScore !== undefined) {
        return Number(submission.manualScore);
      }

      if (submission.assessmentType === 'tasks') {
        return null;
      }

      const questions = this.resolveSubmissionQuestions(submission);

      if (!questions.length) {
        return null;
      }

      const answerMap = new Map((submission.answers || []).map((answer) => [answer.questionId, answer]));

      return questions.reduce((sum, question) => {
        if (!question.correctAnswer) {
          return sum;
        }

        const answer = answerMap.get(question.id);
        const studentAnswer = this.resolveStudentAnswer(answer);

        if (normalizeAnswer(studentAnswer) === normalizeAnswer(question.correctAnswer)) {
          return sum + Number(question.points || 0);
        }

        return sum;
      }, 0);
    },
    resolveSubmissionQuestions(submission) {
      if (this.isFinalExamResultsSection) {
        return this.finalExamQuestions.filter((question) => question.branchCode === this.effectiveResultsBranchId);
      }

      if (this.isTaskResultsSection) {
        const taskCourse = this.taskEligibleCourses.find((course) => course.id === submission?.courseId) || null;
        return taskCourse?.taskQuestions || [];
      }

      return this.selectedResultsQuestions;
    },
    formatScore(value) {
      if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '--';
      }

      const numeric = Number(value);
      return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(1);
    },
    formatRowScore(score, submission) {
      if (!submission) {
        return 'غير مرسل';
      }

      if (submission.assessmentType === 'tasks' && (submission.manualScore === null || submission.manualScore === undefined)) {
        return 'قيد المراجعة';
      }

      if (!this.selectedResultsTotalPoints) {
        return `${(submission.answers || []).length} إجابة`;
      }

      return `${this.formatScore(score)} / ${this.formatScore(this.selectedResultsTotalPoints)}`;
    },
    resolveStudentAnswer(answer) {
      if (!answer) {
        return 'لا توجد إجابة';
      }

      if (answer.fileName) {
        return `ملف مرفق: ${answer.fileName}`;
      }

      if (String(answer.value || '').trim()) {
        return answer.value;
      }

      return 'لا توجد إجابة';
    },
    resolveAnswerAttachment(answer) {
      if (!answer?.fileName || !answer?.fileDataUrl) {
        return null;
      }

      return {
        fileName: answer.fileName,
        fileType: answer.fileType || '',
        fileDataUrl: answer.fileDataUrl,
      };
    },
    resolveSubmissionAttachment(submission) {
      return (submission?.answers || [])
        .map((answer) => this.resolveAnswerAttachment(answer))
        .find(Boolean) || null;
    },
    openAttachmentPreview(attachment) {
      if (!attachment?.fileDataUrl) {
        return;
      }

      const previewWindow = window.open('', '_blank', 'noopener,noreferrer');
      if (!previewWindow) {
        return;
      }

      previewWindow.document.write(`
        <!doctype html>
        <html lang="ar" dir="rtl">
          <head>
            <meta charset="utf-8">
            <title>${this.escapeHtml(attachment.fileName || 'المرفق')}</title>
            <style>
              body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; font-family: sans-serif; }
              iframe, img, video { width: 100%; height: 100vh; border: 0; object-fit: contain; background: #fff; }
              a { color: #006c67; font-weight: 800; font-size: 18px; }
            </style>
          </head>
          <body>
            ${this.renderAttachmentPreview(attachment)}
          </body>
        </html>
      `);
      previewWindow.document.close();
    },
    escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },
    escapeAttribute(value) {
      return this.escapeHtml(value).replace(/`/g, '&#96;');
    },
    renderAttachmentPreview(attachment) {
      const source = this.escapeAttribute(attachment.fileDataUrl);
      const fileType = attachment.fileType || '';
      const fileName = this.escapeAttribute(attachment.fileName || 'المرفق');
      const fileLabel = this.escapeHtml(attachment.fileName || 'المرفق');

      if (fileType.startsWith('image/')) {
        return `<img src="${source}" alt="${fileName}">`;
      }

      if (fileType === 'application/pdf') {
        return `<iframe src="${source}" title="${fileName}"></iframe>`;
      }

      if (fileType.startsWith('video/')) {
        return `<video src="${source}" controls></video>`;
      }

      return `<a href="${source}" download="${fileName}">تحميل / فتح ${fileLabel}</a>`;
    },
    resolveCorrectAnswer(question) {
      if (!question) {
        return 'لا توجد إجابة محددة';
      }

      if (String(question.correctAnswer || '').trim()) {
        return question.correctAnswer;
      }

      return 'لا توجد إجابة محددة';
    },
    openResultDialog(row) {
      if (!row?.submission) {
        return;
      }

      this.selectedResultLoginId = row.key;
      this.resultDialogOpen = true;
    },
    closeResultDialog() {
      this.resultDialogOpen = false;
      this.selectedResultLoginId = '';
    },
    async handleSaveScore() {
      const submission = this.selectedResultRow?.submission;
      if (!submission?.id) { return; }
      this.isSavingScore = true;
      try {
        const score = this.scoreEditValue === null || this.scoreEditValue === '' ? null : Number(this.scoreEditValue);
        if (this.isFinalExamResultsSection) {
          await setFinalExamManualScore(submission.id, score);
        } else {
          await setAssessmentManualScore(submission.id, score);
        }
        await this.loadDashboardSnapshot();
      } catch (err) {
        this.$toast.error(err?.response?.data?.message || 'تعذر حفظ الدرجة');
      } finally {
        this.isSavingScore = false;
      }
    },
    downloadResultAsPdf() {
      const courseName = this.escapeHtml(this.selectedTaskResultCourse?.title || this.selectedResultsSectionLabel || 'النتيجة');
      const studentName = this.escapeHtml(this.selectedResultRow?.name || 'المعلم');
      const docTitle = `${courseName}:${studentName}`;

      const cardsHtml = this.resultDetailCards.map((card, i) => {
        const answerContent = card.studentAnswerHtml
          ? `<div class="student-doc-answer ql-editor">${sanitizeRichTextHtml(card.studentAnswerHtml)}</div>`
          : `<p class="student-text-answer">${this.escapeHtml(card.studentAnswer || 'لا توجد إجابة')}</p>`;
        return `
          <div class="q-card">
            <div class="q-prompt">${i + 1}. ${this.escapeHtml(card.prompt)} <span class="q-pts">(${this.escapeHtml(card.points)} درجة)</span></div>
            <div class="q-answer-label">إجابة المعلم:</div>
            ${answerContent}
          </div>`;
      }).join('');

      const html = `<!DOCTYPE html>
<html dir="rtl" lang="ar"><head>
  <meta charset="UTF-8">
  <title>${docTitle}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; direction: rtl; color: #1a1a2e; background: #f0f4f8; padding: 24px; }
    .pdf-header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #3a5a8a; }
    .pdf-header h1 { font-size: 22px; color: #1a3a5c; }
    .q-card { background: #fff; border: 1px solid #d0d8e8; border-radius: 10px; padding: 18px 20px; margin-bottom: 20px; page-break-inside: avoid; }
    .q-prompt { font-weight: 700; font-size: 15px; color: #1a3a5c; margin-bottom: 10px; }
    .q-pts { font-weight: 400; color: #777; font-size: 13px; }
    .q-answer-label { font-size: 12px; color: #888; margin-bottom: 8px; }
    .student-doc-answer { direction: rtl; text-align: right; font-size: 14px; line-height: 1.8; padding: 10px; background: #fafafa; border-radius: 6px; }
    .student-doc-answer img { position: static !important; display: block; max-width: 100%; height: auto; margin: 10px auto; border-radius: 8px; }
    .student-text-answer { font-size: 14px; color: #333; padding: 8px 12px; background: #fafafa; border-right: 3px solid #4e7fc5; border-radius: 4px; margin-top: 4px; }
    @media print { body { background: white; padding: 10px; } .q-card { border: 1px solid #ccc; } }
  </style>
</head>
<body>
  <div class="pdf-header"><h1>${docTitle}</h1></div>
  ${cardsHtml}
  <script>window.onload = function() { setTimeout(function() { window.print(); }, 400); };</` + `script>
</body></html>`;

      const win = window.open('', '_blank', 'width=900,height=700');
      if (!win) {
        this.$toast.error('يرجى السماح بفتح النوافذ المنبثقة لتحميل الملف');
        return;
      }
      win.document.write(html);
      win.document.close();
    },
  },
};
</script>

<style scoped>
.results-view {
  width: 100%;
}

.results-view--embedded {
  padding: 0;
}

.prep-layout {
  width: 100%;
}

.prep-card {
  background: #fff;
  border: 1px solid #e6edf3;
  border-radius: 30px;
  padding: 28px 28px 18px;
  box-shadow: 0 12px 34px rgba(15, 23, 42, 0.04);
}

.prep-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.prep-card__title {
  margin: 0;
  color: #0f3554;
  font-size: 2rem;
  font-weight: 800;
  line-height: 1.2;
}

.prep-card__status {
  margin-top: 8px;
  color: #6c8193;
  font-size: 0.95rem;
}

.prep-card__status--saving {
  color: #1787a6;
}

.prep-filters {
  display: grid;
  grid-template-columns: minmax(220px, 0.95fr) minmax(260px, 1.15fr) 54px;
  align-items: end;
  gap: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid #e8eef4;
}

.prep-field {
  min-width: 0;
}

.prep-field--wide {
  min-width: 0;
}

.prep-field__label {
  display: block;
  margin: 0 0 10px;
  color: #0f3554;
  font-size: 1rem;
  font-weight: 700;
}

.results-course-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
}

.results-course-option__label {
  min-width: 0;
  flex: 1;
}

.results-course-option__delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: none;
  border-radius: 999px;
  background: rgba(193, 54, 54, 0.1);
  color: #c13636;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.results-course-option__delete:hover:not(:disabled) {
  background: rgba(193, 54, 54, 0.18);
}

.results-course-option__delete:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.results-delete-dialog {
  padding: 28px;
  background: #fff;
  border-radius: 24px;
}

.results-delete-dialog__title {
  margin: 0;
  color: #0f3554;
  font-size: 1.35rem;
  font-weight: 800;
}

.results-delete-dialog__text {
  margin: 16px 0 0;
  color: #35556f;
  font-size: 1rem;
  line-height: 1.8;
}

.results-delete-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}

.prep-table-wrap {
  overflow: hidden;
}

.prep-table {
  width: 100%;
  border-collapse: collapse;
}

.prep-table thead th {
  padding: 18px 12px 14px;
  color: #5f7790;
  font-size: 1rem;
  font-weight: 700;
  text-align: right;
  border-bottom: 1px solid #dfe8f0;
}

.prep-table tbody td {
  padding: 18px 12px;
  color: #244764;
  font-size: 1rem;
  border-bottom: 1px solid #e7eef4;
}

.prep-table tbody tr:last-child td {
  border-bottom: none;
}

.prep-table__name {
  font-weight: 700;
}

.prep-table__login {
  width: 160px;
  color: #577089;
  white-space: nowrap;
}

.prep-table__status {
  width: 88px;
  text-align: left;
}

.prep-table__empty {
  padding: 34px 12px !important;
  color: #6c8193 !important;
  text-align: center !important;
}

.attendance-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  padding: 0;
  border: 2px solid #6aaecd;
  border-radius: 50%;
  background: transparent;
  transition: background-color 0.2s ease, border-color 0.2s ease, opacity 0.2s ease;
}

.attendance-toggle:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.attendance-toggle__dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: transparent;
  transition: background-color 0.2s ease;
}

.attendance-toggle--active {
  border-color: #1484a7;
}

.attendance-toggle--active .attendance-toggle__dot,
.attendance-toggle--partial .attendance-toggle__dot {
  background: #1484a7;
}

.results-board {
  display: grid;
  gap: 18px;
}

.results-shell,
.results-list-shell,
.results-context {
  border: 1px solid #dfeaf1;
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 253, 0.96) 100%);
  box-shadow: 0 14px 42px rgba(15, 23, 42, 0.05);
}

.results-shell {
  padding: 26px 34px 28px;
}

.results-shell__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18px;
}

.results-shell__title {
  margin: 0;
  color: #0b3f5b;
  font-size: 2rem;
  font-weight: 900;
}

.results-filters {
  display: grid;
  grid-template-columns: minmax(220px, 1.45fr) repeat(3, minmax(150px, 1fr));
  gap: 18px;
}

.results-filter-field {
  min-width: 0;
}

.results-filter-field--wide {
  min-width: 0;
}

.results-filter-field__label {
  display: block;
  margin-bottom: 10px;
  color: #0b3f5b;
  font-size: 1rem;
  font-weight: 800;
}

.results-select :deep(input::placeholder) {
  color: #8a98a5 !important;
  opacity: 1;
}

.results-context {
  padding: 24px 34px;
}

.results-context__eyebrow {
  color: #688094;
  font-size: 1rem;
  font-weight: 600;
}

.results-context__title {
  margin-top: 8px;
  color: #0b3f5b;
  font-size: 1.9rem;
  font-weight: 900;
}

.results-list-shell {
  padding: 16px 24px 22px;
}

.results-attendance-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 14px;
  color: #0b3f5b;
  font-size: 1rem;
  font-weight: 900;
}

.results-attendance-summary span {
  padding: 8px 12px;
  border-radius: 14px;
  background: rgba(20, 132, 167, 0.1);
}

.results-list {
  display: grid;
  gap: 14px;
}

.results-entry {
  display: flex;
  direction: ltr;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  min-height: 82px;
  padding: 18px 22px;
  border: 1px solid #d9e8f0;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.98);
}

.results-entry__identity {
  min-width: 0;
  text-align: right;
}

.results-entry__name {
  color: #0b3f5b;
  font-size: 1.05rem;
  font-weight: 900;
}

.results-entry__login {
  margin-top: 2px;
  color: #6a8396;
  font-size: 0.95rem;
}

.results-entry__attachment-name {
  margin-top: 4px;
  color: #006c67;
  font-size: 0.78rem;
  font-weight: 800;
  word-break: break-word;
}

.results-entry__actions {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.results-entry__attachment,
.results-entry__preview {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border: 2px solid #c8e0ec;
  border-radius: 14px;
  background: #fff;
  color: #0d4f69;
}

.results-entry__attachment {
  border-color: rgba(0, 108, 103, 0.22);
  border-radius: 50%;
  background: rgba(0, 108, 103, 0.08);
  color: #006c67;
}

.results-entry__preview:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.results-entry__score-pill,
.results-entry__status-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 66px;
  min-height: 34px;
  padding: 0 14px;
  border-radius: 999px;
  font-size: 0.98rem;
  font-weight: 900;
  white-space: nowrap;
}

.results-entry__score-pill {
  border: 1px solid #c8e0ec;
  background: #fff;
  color: #0b7aad;
}

.results-entry__score-pill--empty {
  color: #6c8193;
}

.results-entry__status-pill--present {
  background: rgba(34, 197, 94, 0.1);
  color: #16a34a;
}

.results-entry__status-pill--absent {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.results-empty-state {
  padding: 42px 24px;
  color: #6a8396;
  font-size: 1rem;
  font-weight: 700;
  text-align: center;
}

.results-empty-state--dialog {
  padding: 34px 18px;
}

.results-detail-dialog__content {
  box-shadow: none;
}

.results-detail-dialog {
  border-radius: 34px;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
  overflow: hidden;
}

.results-detail-dialog__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 26px 32px 20px;
  border-bottom: 1px solid #dbe8f0;
}

.results-detail-dialog__eyebrow {
  color: #7a8fa0;
  font-size: 0.98rem;
}

.results-detail-dialog__title {
  margin-top: 6px;
  color: #0b3f5b;
  font-size: 2rem;
  font-weight: 900;
}

.results-detail-dialog__close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border: 1px solid #d5e5ee;
  border-radius: 14px;
  background: #fff;
  color: #5a7388;
}

.results-detail-dialog__body {
  max-height: 70vh;
  overflow-y: auto;
  padding: 26px 24px 28px;
  display: grid;
  gap: 16px;
  background: #fbfdfe;
}

.results-answer-card {
  padding: 18px 22px;
  border: 1px solid #e2edf3;
  border-radius: 24px;
  background: #fff;
}

.results-answer-card__question {
  color: #0b3f5b;
  font-size: 1.05rem;
  font-weight: 900;
  line-height: 1.9;
}

.results-answer-card__points {
  color: #6b8091;
  font-size: 0.98rem;
  font-weight: 700;
}

.results-answer-card__line {
  margin-top: 10px;
  font-size: 1rem;
  line-height: 1.9;
}

.results-answer-card__line--correct {
  color: #1f9f63;
  font-weight: 700;
}

.results-answer-card__line--student {
  color: #7b8f9d;
  font-weight: 600;
}

.results-answer-card__attachment {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
  border: 1px solid rgba(0, 108, 103, 0.18);
  border-radius: 999px;
  padding: 8px 12px;
  background: rgba(0, 108, 103, 0.08);
  color: #006c67;
  font-weight: 800;
}

.results-answer-card__document {
  display: grid;
  gap: 10px;
  margin-top: 12px;
}

.results-answer-card__answer-label {
  font-size: 0.98rem;
  font-weight: 800;
}

.results-answer-card__answer-label--student {
  color: #7b8f9d;
}

.results-answer-card__status {
  margin-top: 4px;
  font-size: 1rem;
  font-weight: 800;
}

.results-answer-card__status--correct {
  color: #16a34a;
}

.results-answer-card__status--incorrect {
  color: #dc2626;
}

@media (max-width: 960px) {
  .prep-card {
    padding: 22px 18px 12px;
    border-radius: 24px;
  }

  .prep-card__title,
  .results-shell__title,
  .results-context__title,
  .results-detail-dialog__title {
    font-size: 1.65rem;
  }

  .prep-filters,
  .results-filters {
    grid-template-columns: 1fr;
  }

  .prep-filters > :last-child {
    justify-self: start;
  }

  .results-shell,
  .results-context,
  .results-list-shell {
    padding-inline: 18px;
  }
}

@media (max-width: 600px) {
  .prep-card__head,
  .results-entry,
  .results-detail-dialog__header {
    flex-direction: column;
    align-items: stretch;
  }

  .prep-table,
  .prep-table thead,
  .prep-table tbody,
  .prep-table tr,
  .prep-table th,
  .prep-table td {
    display: block;
    width: 100%;
  }

  .prep-table thead {
    display: none;
  }

  .prep-table tbody tr {
    padding: 14px 0;
    border-bottom: 1px solid #e7eef4;
  }

  .prep-table tbody tr:last-child {
    border-bottom: none;
  }

  .prep-table tbody td {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 0;
    border-bottom: none;
  }

  .prep-table tbody td::before {
    color: #5f7790;
    font-weight: 700;
  }

  .prep-table__name::before {
    content: 'الاسم';
  }

  .prep-table__login::before {
    content: 'رقم الدخول';
  }

  .prep-table__status::before {
    content: 'حاضر';
  }

  .results-entry__actions {
    justify-content: flex-start;
  }
}

.results-score-editor {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  flex-wrap: wrap;
}

.results-score-editor__label {
  font-weight: 600;
  font-size: 14px;
  color: #444;
  min-width: fit-content;
}

.results-score-editor__controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.results-score-editor__input {
  width: 80px;
  padding: 6px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 15px;
  text-align: center;
  outline: none;
  transition: border-color 0.2s;
}
.results-score-editor__input:focus {
  border-color: #4e7fc5;
}

.results-score-editor__total {
  font-size: 14px;
  color: #666;
}

.results-score-editor__save-btn {
  padding: 6px 16px;
  background: #4e7fc5;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s, opacity 0.2s;
}
.results-score-editor__save-btn:hover:not(:disabled) {
  background: #3a6aad;
}
.results-score-editor__save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.results-detail-dialog__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.results-detail-dialog__download-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: #e53935;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  cursor: pointer;
  transition: background 0.2s;
}
.results-detail-dialog__download-btn:hover {
  background: #c62828;
}

.results-detail-dialog__body--tasks {
  padding: 0;
}

.results-detail-dialog__body--tasks .results-answer-card {
  border: none;
  border-radius: 0;
  padding: 0;
  margin: 0;
  box-shadow: none;
}

.results-detail-dialog__body--tasks :deep(.rich-text-document-view__surface) {
  padding: 8px;
  border-radius: 0;
  border: none;
  background: #f4f7fa;
}

.results-detail-dialog__body--tasks :deep(.rich-text-document-view__page) {
  min-height: 0 !important;
  padding: 16px 20px;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
}
</style>
