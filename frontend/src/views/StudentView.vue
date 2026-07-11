<template>
  <div class="student-page dashboard-page-shell">
    <aside
      class="dashboard-sidebar"
      :class="{ 'dashboard-sidebar--open': mobileMenuOpen }"
    >
      <router-link
        :to="{ name: 'home' }"
        class="dashboard-sidebar__brand"
      >
        <img
          :src="$publicAsset('اللوقو-شفاف.png')"
          alt="شعار البرنامج"
          class="dashboard-sidebar__logo"
        >
        <div class="dashboard-sidebar__title">
          حسابي
        </div>
      </router-link>

      <nav class="dashboard-nav">
        <div
          v-for="item in studentMenu"
          :key="item.id"
          class="dashboard-nav__entry"
        >
          <AppRawButton
            type="button"
            class="dashboard-nav__item"
            :class="{ 'dashboard-nav__item--active': activeSection === item.id }"
            @click="selectSection(item.id)"
          >
            <span class="dashboard-nav__copy">
              <span class="dashboard-nav__icon"><v-icon small>{{ item.icon }}</v-icon></span>
              <span class="dashboard-nav__label">{{ item.label }}</span>
            </span>
            <span class="dashboard-nav__dot" />
          </AppRawButton>
        </div>
      </nav>
    </aside>

    <div
      v-if="mobileMenuOpen"
      class="dashboard-backdrop"
      @click="mobileMenuOpen = false"
    />

    <main class="dashboard-main">
      <header class="dashboard-topbar">
        <div class="dashboard-topbar__mobile">
          <AppIconButton
            variant="primary"
            class="dashboard-icon-button"
            aria-label="فتح القائمة"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <v-icon>{{ mobileMenuOpen ? 'mdi-close' : 'mdi-menu' }}</v-icon>
          </AppIconButton>
        </div>

        <div class="dashboard-topbar__welcome">
          <div class="dashboard-topbar__label">
            مرحبًا
          </div>
          <div class="dashboard-topbar__name">
            {{ studentAccount?.name || 'الطالب' }}
          </div>
        </div>
      </header>

      <div
        v-if="!hasStudentAccess"
        class="dashboard-error"
      >
        هذه الصفحة مخصصة لحسابات الطلاب فقط.
      </div>

      <section
        v-else-if="loading"
        class="dashboard-loader-shell"
      >
        <div class="dashboard-loader-shell__inner">
          <div
            class="loader"
            aria-hidden="true"
          />
        </div>
      </section>

      <div
        v-else-if="loadError"
        class="dashboard-error"
      >
        {{ loadError }}
      </div>

      <div
        v-else-if="!studentAccount"
        class="dashboard-error dashboard-error--neutral"
      >
        لا توجد بيانات مرتبطة بهذا الطالب حاليًا.
      </div>

      <template v-else>
        <section
          v-if="!['parts', 'final'].includes(activeSection)"
          class="dashboard-section"
        >
          <div
            v-if="activeSection === 'courses'"
            class="dashboard-card dashboard-card--overview-header student-course-filters-card"
          >
            <div class="student-course-filters-grid student-course-filters-grid--single">
              <div class="student-overview-filter student-overview-filter--stacked">
                <div class="student-overview-filter__label">
                  الدورات
                </div>
                <AppSelect
                  v-model="selectedCourseId"
                  :items="courseOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="dashboard-overview-filter__select"
                />
              </div>
            </div>
          </div>

          <div
            v-else-if="activeSection === 'tasks'"
            class="dashboard-card dashboard-card--overview-header student-course-filters-card"
          >
            <div class="student-course-filters-grid student-course-filters-grid--single">
              <div class="student-overview-filter student-overview-filter--stacked">
                <div class="student-overview-filter__label">
                  المهام الأدائية
                </div>
                <AppSelect
                  v-model="selectedTaskId"
                  :items="taskOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="dashboard-overview-filter__select"
                />
              </div>
            </div>
          </div>

          <div
            v-else-if="activeSection !== 'materials'"
            class="dashboard-card dashboard-card--overview-header student-summary-card"
          >
            <div class="dashboard-overview-header__top">
              <div class="dashboard-card__title-wrap">
                <h1 class="dashboard-card__title dashboard-card__title--overview">
                  {{ activeSectionTitle }}
                </h1>
                <div class="student-summary-card__subtitle">
                  {{ activeSectionDescription }}
                </div>
              </div>
            </div>

            <div class="student-summary-grid">
              <div class="student-summary-chip">
                <span>رقم الدخول</span>
                <strong>{{ currentStudentLogin || '---' }}</strong>
              </div>
              <div class="student-summary-chip">
                <span>الفرع</span>
                <strong>{{ currentBranchLabel }}</strong>
              </div>
              <div class="student-summary-chip">
                <span>المقرئ</span>
                <strong>{{ assignedReciter?.name || 'غير محدد' }}</strong>
              </div>
              <div class="student-summary-chip">
                <span>الأجزاء المقروءة</span>
                <strong>{{ completedCount }} / {{ partsLimit }}</strong>
              </div>
            </div>
          </div>
        </section>

        <section
          v-if="activeSection === 'courses'"
          class="dashboard-section"
        >
          <div class="results-board">
            <div
              v-if="!selectedCourse"
              class="results-list-shell"
            >
              <div class="results-empty-state">
                لا توجد دورات متاحة لعرض النتائج.
              </div>
            </div>

            <template v-else>
              <section class="student-course-section">
                <section class="results-list-shell">
                  <div class="results-list">
                    <article class="results-entry">
                      <div class="results-entry__actions">
                        <span
                          class="results-entry__status-pill"
                          :class="courseAttendancePresent ? 'results-entry__status-pill--present' : 'results-entry__status-pill--absent'"
                        >
                          {{ courseAttendancePresent ? 'حاضر' : 'غائب' }}
                        </span>
                      </div>

                      <div class="results-entry__identity">
                        <div class="results-entry__name">
                          التحضير
                        </div>
                      </div>
                    </article>
                  </div>
                </section>
              </section>

              <section class="student-course-section">
                <section class="results-list-shell">
                  <div class="results-list">
                    <article class="results-entry">
                      <div class="results-entry__actions">
                        <AppRawButton
                          type="button"
                          class="results-entry__preview"
                          :disabled="!preCourseSubmission"
                          @click="toggleExpandedSection('course-pre')"
                        >
                          <v-icon small>
                            {{ expandedSection === 'course-pre' ? 'mdi-eye-off-outline' : 'mdi-eye-outline' }}
                          </v-icon>
                        </AppRawButton>
                        <span
                          class="results-entry__score-pill"
                          :class="{ 'results-entry__score-pill--empty': !preCourseSubmission }"
                        >
                          {{ preCourseScoreLabel }}
                        </span>
                      </div>

                      <div class="results-entry__identity">
                        <div class="results-entry__name">
                          الاختبار القبلي
                        </div>
                      </div>
                    </article>

                    <div
                      v-if="expandedSection === 'course-pre' && preCourseSubmission"
                      class="student-detail-stack"
                    >
                      <article
                        v-for="detail in preCourseDetailCards"
                        :key="detail.key"
                        class="results-answer-card"
                      >
                        <div class="results-answer-card__question">
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
                          v-if="detail.studentAnswerHtml"
                          class="results-answer-card__document"
                        >
                          <div class="results-answer-card__answer-label results-answer-card__answer-label--student">
                            إجابتك
                          </div>
                          <RichTextDocumentView
                            :value="detail.studentAnswerHtml"
                            min-height="420px"
                          />
                        </div>
                        <div
                          v-else
                          class="results-answer-card__line results-answer-card__line--student"
                        >
                          إجابتك: {{ detail.studentAnswer }}
                        </div>
                        <div
                          v-if="detail.statusText"
                          class="results-answer-card__status"
                          :class="detail.isCorrect ? 'results-answer-card__status--correct' : 'results-answer-card__status--incorrect'"
                        >
                          {{ detail.statusText }}
                        </div>
                      </article>
                    </div>
                  </div>
                </section>
              </section>

              <section class="student-course-section">
                <section class="results-list-shell">
                  <div class="results-list">
                    <article class="results-entry">
                      <div class="results-entry__actions">
                        <AppRawButton
                          type="button"
                          class="results-entry__preview"
                          :disabled="!postCourseSubmission"
                          @click="toggleExpandedSection('course-post')"
                        >
                          <v-icon small>
                            {{ expandedSection === 'course-post' ? 'mdi-eye-off-outline' : 'mdi-eye-outline' }}
                          </v-icon>
                        </AppRawButton>
                        <span
                          class="results-entry__score-pill"
                          :class="{ 'results-entry__score-pill--empty': !postCourseSubmission }"
                        >
                          {{ postCourseScoreLabel }}
                        </span>
                      </div>

                      <div class="results-entry__identity">
                        <div class="results-entry__name">
                          الاختبار البعدي
                        </div>
                      </div>
                    </article>

                    <div
                      v-if="expandedSection === 'course-post' && postCourseSubmission"
                      class="student-detail-stack"
                    >
                      <article
                        v-for="detail in postCourseDetailCards"
                        :key="detail.key"
                        class="results-answer-card"
                      >
                        <div class="results-answer-card__question">
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
                          v-if="detail.studentAnswerHtml"
                          class="results-answer-card__document"
                        >
                          <div class="results-answer-card__answer-label results-answer-card__answer-label--student">
                            إجابتك
                          </div>
                          <RichTextDocumentView
                            :value="detail.studentAnswerHtml"
                            min-height="420px"
                          />
                        </div>
                        <div
                          v-else
                          class="results-answer-card__line results-answer-card__line--student"
                        >
                          إجابتك: {{ detail.studentAnswer }}
                        </div>
                        <div
                          v-if="detail.statusText"
                          class="results-answer-card__status"
                          :class="detail.isCorrect ? 'results-answer-card__status--correct' : 'results-answer-card__status--incorrect'"
                        >
                          {{ detail.statusText }}
                        </div>
                      </article>
                    </div>
                  </div>
                </section>
              </section>
            </template>
          </div>
        </section>

        <section
          v-else-if="activeSection === 'parts'"
          class="dashboard-section"
        >
          <div class="dashboard-card student-parts-card student-parts-card--minimal">
            <div class="student-parts-grid">
              <div
                v-for="part in allParts"
                :key="part"
                class="student-part-circle"
                :class="{ 'student-part-circle--active': completedParts.includes(part) }"
              >
                {{ part }}
              </div>
            </div>
          </div>
        </section>

        <section
          v-else-if="activeSection === 'tasks'"
          class="dashboard-section"
        >
          <div class="results-board">
            <section class="results-list-shell">
              <div
                v-if="!selectedTask"
                class="results-empty-state"
              >
                لا توجد مهام أدائية متاحة.
              </div>

              <div
                v-else
                class="results-list"
              >
                <article class="results-entry">
                  <div class="results-entry__actions">
                    <AppRawButton
                      type="button"
                      class="results-entry__preview"
                      :disabled="!selectedTaskSubmission"
                      @click="toggleExpandedSection('task')"
                    >
                      <v-icon small>
                        {{ expandedSection === 'task' ? 'mdi-eye-off-outline' : 'mdi-eye-outline' }}
                      </v-icon>
                    </AppRawButton>
                    <span
                      class="results-entry__score-pill"
                      :class="{ 'results-entry__score-pill--empty': !selectedTaskSubmission }"
                    >
                      {{ taskScoreLabel }}
                    </span>
                  </div>

                  <div class="results-entry__identity">
                    <div class="results-entry__name">
                      المهمة الأدائية
                    </div>
                  </div>
                </article>

                <div
                  v-if="expandedSection === 'task' && selectedTaskSubmission"
                  class="student-detail-stack"
                >
                  <article
                    v-for="detail in taskDetailCards"
                    :key="detail.key"
                    class="results-answer-card"
                  >
                    <div class="results-answer-card__question">
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
                      v-if="detail.studentAnswerHtml"
                      class="results-answer-card__document"
                    >
                      <div class="results-answer-card__answer-label results-answer-card__answer-label--student">
                        إجابتك
                      </div>
                      <RichTextDocumentView
                        :value="detail.studentAnswerHtml"
                        min-height="420px"
                      />
                    </div>
                    <div
                      v-else
                      class="results-answer-card__line results-answer-card__line--student"
                    >
                      إجابتك: {{ detail.studentAnswer }}
                    </div>
                    <div
                      v-if="detail.statusText"
                      class="results-answer-card__status"
                      :class="detail.isCorrect ? 'results-answer-card__status--correct' : 'results-answer-card__status--incorrect'"
                    >
                      {{ detail.statusText }}
                    </div>
                  </article>
                </div>
              </div>
            </section>
          </div>
        </section>

        <section
          v-else-if="activeSection === 'materials'"
          class="dashboard-section"
        >
          <TrainingMaterialsList
            :materials="trainingMaterials"
            preview-in-dialog
            empty-title="لا توجد حقائب تدريبية متاحة حاليًا."
            empty-description="ستظهر الملفات التدريبية هنا بمجرد إضافتها من لوحة الإدارة."
          />
        </section>

        <section
          v-else
          class="dashboard-section"
        >
          <div class="results-board">
            <section class="results-list-shell">
              <div
                v-if="finalExamQuestions.length === 0"
                class="results-empty-state"
              >
                لا توجد أسئلة نهائية متاحة لهذا الفرع.
              </div>

              <div
                v-else
                class="results-list"
              >
                <article class="results-entry">
                  <div class="results-entry__actions">
                    <AppRawButton
                      type="button"
                      class="results-entry__preview"
                      :disabled="!finalExamSubmission"
                      @click="toggleExpandedSection('final')"
                    >
                      <v-icon small>
                        {{ expandedSection === 'final' ? 'mdi-eye-off-outline' : 'mdi-eye-outline' }}
                      </v-icon>
                    </AppRawButton>
                    <span
                      class="results-entry__score-pill"
                      :class="{ 'results-entry__score-pill--empty': !finalExamSubmission }"
                    >
                      {{ finalExamScoreLabel }}
                    </span>
                  </div>

                  <div class="results-entry__identity">
                    <div class="results-entry__name">
                      الاختبار النهائي
                    </div>
                  </div>
                </article>

                <div
                  v-if="expandedSection === 'final' && finalExamSubmission"
                  class="student-detail-stack"
                >
                  <article
                    v-for="detail in finalExamDetailCards"
                    :key="detail.key"
                    class="results-answer-card"
                  >
                    <div class="results-answer-card__question">
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
                      v-if="detail.studentAnswerHtml"
                      class="results-answer-card__document"
                    >
                      <div class="results-answer-card__answer-label results-answer-card__answer-label--student">
                        إجابتك
                      </div>
                      <RichTextDocumentView
                        :value="detail.studentAnswerHtml"
                        min-height="420px"
                      />
                    </div>
                    <div
                      v-else
                      class="results-answer-card__line results-answer-card__line--student"
                    >
                      إجابتك: {{ detail.studentAnswer }}
                    </div>
                    <div
                      v-if="detail.statusText"
                      class="results-answer-card__status"
                      :class="detail.isCorrect ? 'results-answer-card__status--correct' : 'results-answer-card__status--incorrect'"
                    >
                      {{ detail.statusText }}
                    </div>
                  </article>
                </div>
              </div>
            </section>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import AppIconButton from '../components/AppIconButton.vue';
import AppSelect from '../components/AppSelect.vue';
import RichTextDocumentView from '../components/RichTextDocumentView.vue';
import TrainingMaterialsList from '../components/TrainingMaterialsList.vue';
import { AppRawButton } from '../components/ui';
import { fetchStudentAssignedReciter } from '../services/api';

const normalizeAnswer = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();

export default {
  name: 'StudentView',
  components: {
    AppIconButton,
    AppRawButton,
    AppSelect,
    RichTextDocumentView,
    TrainingMaterialsList,
  },
  data() {
    return {
      loading: false,
      loadError: '',
      assignedReciter: null,
      mobileMenuOpen: false,
      activeSection: 'courses',
      selectedCourseId: '',
      selectedTaskId: '',
      expandedSection: '',
      studentMenu: [
        { id: 'courses', label: 'الدورات', icon: 'mdi-bookshelf' },
        { id: 'parts', label: 'الإقراء', icon: 'mdi-book-open-page-variant-outline' },
        { id: 'tasks', label: 'المهام الأدائية', icon: 'mdi-clipboard-text-outline' },
        { id: 'materials', label: 'الحقائب التدريبية', icon: 'mdi-folder-multiple-outline' },
        { id: 'final', label: 'الاختبار النهائي', icon: 'mdi-school-outline' },
      ],
    };
  },
  computed: {
    ...mapState(['currentUser', 'dashboardSnapshot']),
    hasStudentAccess() {
      return this.currentUser?.role === 'student';
    },
    studentAccount() {
      const students = this.dashboardSnapshot?.students || [];

      return students.find((student) => String(student.loginId || student.loginCode || '') === String(this.currentUser?.loginCode || '')) || null;
    },
    currentStudentLogin() {
      return this.studentAccount?.loginId || this.studentAccount?.loginCode || '';
    },
    currentBranchLabel() {
      return this.studentAccount?.branchId === 'female' ? 'معلمات' : 'معلمين';
    },
    activeSectionTitle() {
      return this.studentMenu.find((item) => item.id === this.activeSection)?.label || 'حسابي';
    },
    activeSectionDescription() {
      if (this.activeSection === 'courses') {
        return 'اختر الدورة ثم راجع التحضير أو نتائج القبلي والبعدي بنفس نمط لوحة النتائج.';
      }

      if (this.activeSection === 'parts') {
        return 'عرض الأجزاء المقروءة المسجلة على حسابك فقط بدون تعديل.';
      }

      if (this.activeSection === 'tasks') {
        return 'راجع المهام الأدائية ونتيجتك بنفس أسلوب لوحة التحكم.';
      }

      if (this.activeSection === 'materials') {
        return 'حمّل الملفات التدريبية والحقائب المعتمدة المضافة لك من لوحة الإدارة.';
      }

      return 'راجع نتيجة الاختبار النهائي وتفاصيل إجاباتك.';
    },
    trainingMaterials() {
      const currentBranchId = this.studentAccount?.branchId || '';

      return (this.dashboardSnapshot?.trainingMaterials || []).filter((material) => !material.targetBranchId || material.targetBranchId === currentBranchId);
    },
    completedParts() {
      return [...(this.studentAccount?.completedParts || [])].sort((left, right) => left - right);
    },
    partsLimit() {
      return this.studentAccount?.branchId === 'female' ? 10 : 30;
    },
    allParts() {
      return Array.from({ length: this.partsLimit }, (_, index) => index + 1);
    },
    completedCount() {
      return this.completedParts.length;
    },
    courses() {
      return this.dashboardSnapshot?.courses || [];
    },
    submissions() {
      return this.dashboardSnapshot?.submissions || [];
    },
    attendance() {
      return this.dashboardSnapshot?.attendance || [];
    },
    finalExamSubmissions() {
      return this.dashboardSnapshot?.finalExamSubmissions || [];
    },
    finalExamQuestionsSource() {
      return this.dashboardSnapshot?.finalExamQuestions || [];
    },
    nonTaskCourses() {
      return this.courses.filter((course) => course.entityType !== 'task');
    },
    taskCourses() {
      return this.courses.filter((course) => course.entityType === 'task');
    },
    courseOptions() {
      return this.nonTaskCourses.map((course) => ({ label: course.title, value: course.id }));
    },
    taskOptions() {
      return this.taskCourses.map((course) => ({ label: course.title, value: course.id }));
    },
    selectedCourse() {
      return this.nonTaskCourses.find((course) => course.id === this.selectedCourseId) || null;
    },
    selectedTask() {
      return this.taskCourses.find((course) => course.id === this.selectedTaskId) || null;
    },
    preCourseQuestions() {
      return this.selectedCourse?.preQuestions || [];
    },
    postCourseQuestions() {
      return this.selectedCourse?.postQuestions || [];
    },
    preCourseSubmission() {
      if (!this.selectedCourse || !this.currentStudentLogin) {
        return null;
      }

      return [...this.submissions]
        .filter((submission) => submission.courseId === this.selectedCourse.id
          && submission.assessmentType === 'pre'
          && submission.loginId === this.currentStudentLogin)
        .sort((left, right) => new Date(right.submittedAt || 0).getTime() - new Date(left.submittedAt || 0).getTime())[0] || null;
    },
    postCourseSubmission() {
      if (!this.selectedCourse || !this.currentStudentLogin) {
        return null;
      }

      return [...this.submissions]
        .filter((submission) => submission.courseId === this.selectedCourse.id
          && submission.assessmentType === 'post'
          && submission.loginId === this.currentStudentLogin)
        .sort((left, right) => new Date(right.submittedAt || 0).getTime() - new Date(left.submittedAt || 0).getTime())[0] || null;
    },
    courseAttendancePresent() {
      if (!this.selectedCourse || !this.currentStudentLogin) {
        return false;
      }

      return this.attendance.some((record) => record.courseId === this.selectedCourse.id && record.loginId === this.currentStudentLogin);
    },
    preCourseScore() {
      return this.resolveSubmissionScore(this.preCourseQuestions, this.preCourseSubmission);
    },
    preCourseScoreLabel() {
      return this.formatResultScore(this.preCourseScore, this.preCourseSubmission, this.preCourseQuestions);
    },
    postCourseScore() {
      return this.resolveSubmissionScore(this.postCourseQuestions, this.postCourseSubmission);
    },
    postCourseScoreLabel() {
      return this.formatResultScore(this.postCourseScore, this.postCourseSubmission, this.postCourseQuestions);
    },
    preCourseDetailCards() {
      return this.buildDetailCards(this.preCourseQuestions, this.preCourseSubmission);
    },
    postCourseDetailCards() {
      return this.buildDetailCards(this.postCourseQuestions, this.postCourseSubmission);
    },
    taskQuestions() {
      return this.selectedTask?.taskQuestions || [];
    },
    selectedTaskSubmission() {
      if (!this.selectedTask || !this.currentStudentLogin) {
        return null;
      }

      return [...this.submissions]
        .filter((submission) => submission.courseId === this.selectedTask.id
          && submission.assessmentType === 'tasks'
          && submission.loginId === this.currentStudentLogin)
        .sort((left, right) => new Date(right.submittedAt || 0).getTime() - new Date(left.submittedAt || 0).getTime())[0] || null;
    },
    taskScore() {
      return this.resolveSubmissionScore(this.taskQuestions, this.selectedTaskSubmission);
    },
    taskScoreLabel() {
      return this.formatResultScore(this.taskScore, this.selectedTaskSubmission, this.taskQuestions);
    },
    taskDetailCards() {
      return this.buildDetailCards(this.taskQuestions, this.selectedTaskSubmission, {
        richTextAnswers: this.selectedTask?.taskMode === 'document',
      });
    },
    finalExamQuestions() {
      if (!this.studentAccount?.branchId) {
        return [];
      }

      return [...this.finalExamQuestionsSource]
        .filter((question) => question.branchCode === this.studentAccount.branchId)
        .sort((left, right) => (left.sortOrder || 0) - (right.sortOrder || 0));
    },
    finalExamSubmission() {
      if (!this.currentStudentLogin) {
        return null;
      }

      return this.finalExamSubmissions.find((submission) => submission.loginCode === this.currentStudentLogin) || null;
    },
    finalExamScore() {
      return this.resolveSubmissionScore(this.finalExamQuestions, this.finalExamSubmission);
    },
    finalExamScoreLabel() {
      return this.formatResultScore(this.finalExamScore, this.finalExamSubmission, this.finalExamQuestions);
    },
    finalExamDetailCards() {
      return this.buildDetailCards(this.finalExamQuestions, this.finalExamSubmission);
    },
  },
  watch: {
    'currentUser.loginCode': {
      immediate: true,
      handler() {
        this.prepareStudentView();
      },
    },
    nonTaskCourses: {
      immediate: true,
      handler(courses) {
        if (!courses.length) {
          this.selectedCourseId = '';
          return;
        }

        if (!courses.some((course) => course.id === this.selectedCourseId)) {
          this.selectedCourseId = courses.find((course) => course.isActive)?.id || courses[0].id;
        }
      },
    },
    taskCourses: {
      immediate: true,
      handler(courses) {
        if (!courses.length) {
          this.selectedTaskId = '';
          return;
        }

        if (!courses.some((course) => course.id === this.selectedTaskId)) {
          this.selectedTaskId = courses[0].id;
        }
      },
    },
    selectedCourseId() {
      if (String(this.expandedSection || '').startsWith('course-')) {
        this.expandedSection = '';
      }
    },
    selectedTaskId() {
      if (this.expandedSection === 'task') {
        this.expandedSection = '';
      }
    },
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot']),
    selectSection(sectionId) {
      this.activeSection = sectionId;
      this.mobileMenuOpen = false;
      this.expandedSection = '';
    },
    toggleExpandedSection(section) {
      this.expandedSection = this.expandedSection === section ? '' : section;
    },
    resolveSubmissionScore(questions, submission) {
      if (!submission) {
        return null;
      }

      if (submission.manualScore !== null && submission.manualScore !== undefined) {
        return Number(submission.manualScore);
      }

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
    formatScore(value) {
      if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '--';
      }

      const numeric = Number(value);
      return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(1);
    },
    formatResultScore(score, submission, questions) {
      if (!submission) {
        return 'غير مرسل';
      }

      const totalPoints = questions.reduce((sum, question) => sum + Number(question.points || 0), 0);

      if (!totalPoints) {
        return `${(submission.answers || []).length} إجابة`;
      }

      return `${this.formatScore(score)} / ${this.formatScore(totalPoints)}`;
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
    resolveCorrectAnswer(question) {
      if (!question) {
        return 'لا توجد إجابة محددة';
      }

      if (String(question.correctAnswer || '').trim()) {
        return question.correctAnswer;
      }

      return 'لا توجد إجابة محددة';
    },
    buildDetailCards(questions, submission, options = {}) {
      if (!submission) {
        return [];
      }

      const answerMap = new Map((submission.answers || []).map((answer) => [answer.questionId, answer]));

      if (questions.length > 0) {
        return questions.map((question, index) => {
          const answer = answerMap.get(question.id) || null;
          const studentAnswer = this.resolveStudentAnswer(answer);
          const studentAnswerHtml = options.richTextAnswers && !answer?.fileName && String(answer?.value || '').trim()
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
            hideCorrectAnswer: Boolean(options.richTextAnswers),
            studentAnswer,
            studentAnswerHtml,
            isCorrect,
            statusText: isCorrect === null ? '' : (isCorrect ? 'صحيحة' : 'غير صحيحة'),
          };
        });
      }

      return (submission.answers || []).map((answer, index) => ({
        key: `${answer.questionId || index}`,
        index: index + 1,
        prompt: `السؤال ${index + 1}`,
        points: '-',
        correctAnswer: 'لا توجد إجابة مرجعية',
        hideCorrectAnswer: Boolean(options.richTextAnswers),
        studentAnswer: this.resolveStudentAnswer(answer),
        studentAnswerHtml: options.richTextAnswers && !answer?.fileName && String(answer?.value || '').trim()
          ? String(answer.value)
          : '',
        isCorrect: null,
        statusText: '',
      }));
    },
    async prepareStudentView() {
      if (!this.hasStudentAccess || !this.currentUser?.loginCode) {
        this.assignedReciter = null;
        this.loadError = '';
        return;
      }

      this.loading = true;
      this.loadError = '';

      try {
        await this.loadDashboardSnapshot();
        this.assignedReciter = await fetchStudentAssignedReciter(this.currentUser.loginCode);
      } catch (error) {
        this.assignedReciter = null;
        this.loadError = error?.response?.data?.message || 'تعذر تحميل بيانات الطالب.';
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.student-page {
  min-height: 100vh;
  background: linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior-y: auto;
  touch-action: pan-y;
  -webkit-overflow-scrolling: touch;
}

.dashboard-sidebar {
  position: fixed;
  top: 0;
  right: 0;
  z-index: 20;
  display: flex;
  flex-direction: column;
  width: 336px;
  height: 100vh;
  padding: 22px 22px 28px;
  border-left: 1px solid rgba(214, 229, 233, 0.88);
  background: #fff;
  box-shadow: 10px 0 35px rgba(15, 23, 42, 0.03);
  overflow-y: auto;
}

.dashboard-sidebar__brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 6px 20px;
  color: inherit;
  text-decoration: none;
}

.dashboard-sidebar__logo {
  width: auto;
  height: 40px;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(31%) sepia(53%) saturate(1050%) hue-rotate(154deg) brightness(91%) contrast(94%) drop-shadow(0 8px 18px rgba(8, 65, 89, 0.12));
}

.dashboard-sidebar__title {
  color: #0f172a;
  font-size: 1.24rem;
  font-weight: 900;
}

.dashboard-nav {
  display: grid;
  gap: 6px;
  margin-top: 10px;
}

.dashboard-nav__entry {
  display: grid;
  gap: 8px;
}

.dashboard-nav__item {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  width: 100%;
  padding: 14px 28px 14px 54px;
  border: 0;
  border-radius: 18px;
  background: transparent;
  color: #08384a;
  text-align: right;
  cursor: pointer;
  transition: 0.2s ease;
}

.dashboard-nav__item:hover,
.dashboard-nav__item--active {
  background: rgba(16, 118, 153, 0.06);
}

.dashboard-nav__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  color: #107699;
  flex: 0 0 24px;
}

.dashboard-nav__icon :deep(.v-icon) {
  font-size: 22px !important;
  color: inherit !important;
}

.dashboard-nav__copy {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  width: 100%;
  gap: 16px;
}

.dashboard-nav__label {
  flex: 1;
  font-size: 1.05rem;
  font-weight: 500;
  text-align: right;
}

.dashboard-nav__dot {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #cfe3e8;
}

.dashboard-backdrop {
  position: fixed;
  inset: 0;
  z-index: 15;
  background: rgba(15, 23, 42, 0.24);
}

.dashboard-main {
  min-height: 100vh;
  margin-right: 336px;
  padding: 24px 28px 36px;
  overflow-x: hidden;
  overflow-y: visible;
  overscroll-behavior-y: auto;
  touch-action: pan-y;
}

.dashboard-topbar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 16px;
  margin-bottom: 24px;
  padding: 18px 20px;
  border-radius: 32px;
  border: 1px solid rgba(255, 255, 255, 0.7);
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.06);
  backdrop-filter: blur(12px);
}

.dashboard-topbar__mobile {
  display: none;
}

.dashboard-topbar__label {
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 700;
}

.dashboard-topbar__welcome {
  margin-inline-start: 0;
  margin-inline-end: auto;
  text-align: right;
}

.dashboard-topbar__name {
  margin-top: 4px;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.dashboard-topbar__timer-list {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.dashboard-topbar__timer {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 16px;
  border-radius: 16px;
  background: rgba(34, 197, 94, 0.12);
  color: #15803d;
  font-size: 0.96rem;
  font-weight: 900;
  white-space: nowrap;
}

.dashboard-topbar__timer--student {
  background: rgba(16, 118, 153, 0.08);
  color: #0f6f8d;
}

.dashboard-topbar__timer-text {
  color: inherit;
}

.dashboard-topbar__timer-label {
  direction: ltr;
  font-variant-numeric: tabular-nums;
}

.dashboard-icon-button {
  width: 42px;
  height: 42px;
}

.dashboard-error {
  margin-bottom: 16px;
  padding: 14px 18px;
  border-radius: 20px;
  border: 1px solid rgba(220, 38, 38, 0.2);
  background: rgba(220, 38, 38, 0.05);
  color: #b91c1c;
  font-size: 0.92rem;
  font-weight: 600;
}

.dashboard-error--neutral {
  border-color: rgba(214, 229, 233, 0.88);
  background: rgba(255, 255, 255, 0.92);
  color: #5b7285;
}

.dashboard-loader-shell {
  display: grid;
  place-items: center;
  min-height: calc(100vh - 180px);
}

.dashboard-loader-shell__inner {
  display: grid;
  place-items: center;
  width: 140px;
  height: 140px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.66);
  box-shadow: inset 0 0 0 1px rgba(214, 229, 233, 0.65);
}

.dashboard-section {
  margin-bottom: 24px;
  overflow: visible;
}

.dashboard-card {
  border-radius: 28px;
  border: 1px solid rgba(255, 255, 255, 0.8);
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  padding: 24px;
}

.dashboard-card--overview-header {
  display: grid;
  gap: 18px;
  padding: 24px 28px;
  direction: rtl;
}

.dashboard-overview-header__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
}

.dashboard-card__title {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.dashboard-card__title--overview {
  font-size: 1.55rem;
}

.student-summary-card__subtitle {
  margin-top: 8px;
  color: #64748b;
  font-size: 0.92rem;
  font-weight: 600;
  line-height: 1.8;
}

.student-overview-filter {
  width: min(220px, 100%);
}

.student-overview-filter--stacked {
  width: 100%;
}

.student-overview-filter__label {
  margin-bottom: 10px;
  color: #0b3f5b;
  font-size: 0.95rem;
  font-weight: 800;
}

.student-course-filters-card {
  padding: 20px 24px;
}

.student-course-filters-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.student-course-filters-grid--single {
  grid-template-columns: minmax(0, 1fr);
}

.dashboard-overview-filter__select :deep(.v-input__slot) {
  min-height: 46px !important;
  border-radius: 18px !important;
  box-shadow: none !important;
  direction: rtl;
  cursor: pointer;
}

.dashboard-overview-filter__select :deep(input),
.dashboard-overview-filter__select :deep(.v-select__selection),
.dashboard-overview-filter__select :deep(.v-select__selections) {
  text-align: right;
  justify-content: flex-end;
  cursor: pointer !important;
}

.dashboard-overview-filter__select :deep(input) {
  pointer-events: none;
  user-select: none;
  caret-color: transparent;
}

.dashboard-overview-filter__select :deep(.v-input__append-inner) {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 0 !important;
  margin-right: auto !important;
  margin-left: 0 !important;
  cursor: pointer;
}

.student-summary-grid,
.student-context-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}

.student-summary-chip,
.student-context-item {
  display: grid;
  gap: 6px;
  padding: 14px 16px;
  border: 1px solid #dfeaf1;
  border-radius: 20px;
  background: rgba(248, 251, 253, 0.92);
}

.student-summary-chip span,
.student-context-item span {
  color: #6b8091;
  font-size: 0.9rem;
  font-weight: 700;
}

.student-summary-chip strong,
.student-context-item strong {
  color: #0b3f5b;
  font-size: 1rem;
  font-weight: 900;
}

.student-mode-switch {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-start;
  gap: 10px;
}

.student-mode-switch__button {
  min-height: 50px;
  padding: 0 16px;
  border: 1px solid #cfe2eb;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.92);
  color: #557288;
  font-size: 0.98rem;
  font-weight: 900;
  transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
}

.student-mode-switch__button:hover {
  transform: translateY(-1px);
}

.student-mode-switch__button--active {
  border-color: rgba(13, 116, 144, 0.26);
  background: linear-gradient(145deg, #0d7490, #0f3f5c);
  color: #fff;
  box-shadow: 0 12px 26px rgba(8, 61, 93, 0.18);
}

.results-board {
  display: grid;
  gap: 18px;
  overflow: visible;
}

.student-course-section {
  display: grid;
}

.results-list-shell,
.results-context {
  border: 1px solid #dfeaf1;
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 253, 0.96) 100%);
  box-shadow: 0 14px 42px rgba(15, 23, 42, 0.05);
  overflow: visible;
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

.results-entry__actions {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

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

.student-detail-stack {
  display: grid;
  gap: 16px;
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

.student-parts-card {
  min-height: 220px;
}

.student-parts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(64px, 64px));
  gap: 14px;
  justify-content: start;
}

.student-part-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border: 1px solid #d5e5ee;
  border-radius: 999px;
  background: linear-gradient(180deg, #ffffff 0%, #f9fcfe 100%);
  color: #60788d;
  font-size: 1rem;
  font-weight: 900;
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
  cursor: default;
  user-select: none;
}

.student-part-circle--active {
  border-color: rgba(14, 116, 144, 0.2);
  background: linear-gradient(145deg, #0d7490, #0f3f5c);
  color: #fff;
  box-shadow: 0 12px 26px rgba(8, 61, 93, 0.22);
}

@media (max-width: 1200px) {
  .student-summary-grid,
  .student-context-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 960px) {
  .dashboard-sidebar {
    transform: translateX(100%);
    transition: transform 0.24s ease;
  }

  .dashboard-sidebar--open {
    transform: translateX(0);
  }

  .dashboard-main {
    margin-right: 0;
    padding: 18px 16px 28px;
    min-height: auto;
  }

  .dashboard-topbar__mobile {
    display: inline-flex;
  }

  .dashboard-topbar {
    flex-wrap: wrap;
  }

  .dashboard-overview-header__top {
    flex-direction: column;
    align-items: stretch;
  }

  .student-overview-filter {
    width: 100%;
  }

  .student-page,
  .dashboard-main,
  .dashboard-section,
  .results-board,
  .results-list-shell {
    touch-action: pan-y;
    overscroll-behavior-y: auto;
  }
}

@media (max-width: 700px) {
  .student-course-filters-grid {
    grid-template-columns: 1fr;
  }

  .student-summary-grid,
  .student-context-grid {
    grid-template-columns: 1fr;
  }

  .results-entry {
    flex-direction: column;
    align-items: stretch;
  }

  .results-entry__actions {
    justify-content: flex-start;
  }

  .student-parts-grid {
    grid-template-columns: repeat(auto-fit, minmax(56px, 56px));
  }

  .student-part-circle {
    width: 56px;
    height: 56px;
  }
}
</style>
