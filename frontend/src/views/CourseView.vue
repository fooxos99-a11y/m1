<template>
  <div class="assessment-page">
    <div class="assessment-orbit assessment-orbit--large" />
    <div class="assessment-orbit assessment-orbit--medium" />
    <div class="assessment-orbit assessment-orbit--small" />
    <div class="assessment-glow" />
    <div class="assessment-grid" />
    <div class="assessment-top-shade" />
    <div class="assessment-radial assessment-radial--one" />
    <div class="assessment-radial assessment-radial--two" />

    <v-container class="assessment-page__container py-8 py-md-12">
      <AppDialog
        v-model="previewDialogOpen"
        max-width="920"
      >
        <v-card class="assessment-dialog pa-4 pa-sm-6">
          <div class="assessment-dialog__title">
            معاينة المرفق
          </div>
          <div
            v-if="previewAttachment"
            class="assessment-dialog__body"
          >
            <img
              v-if="previewKind === 'image'"
              :src="previewAttachment.dataUrl"
              alt="معاينة المرفق"
              class="assessment-dialog__image"
            >
            <iframe
              v-else-if="previewKind === 'pdf'"
              :src="previewAttachment.dataUrl"
              title="معاينة PDF"
              class="assessment-dialog__frame"
            />
            <video
              v-else-if="previewKind === 'video'"
              :src="previewAttachment.dataUrl"
              controls
              class="assessment-dialog__video"
            />
            <div
              v-else
              class="assessment-empty-state"
            >
              هذا النوع لا يدعم المعاينة المباشرة داخل الصفحة.
            </div>
          </div>
          <div class="assessment-dialog__actions">
            <v-btn
              outlined
              color="primary"
              @click="closePreview"
            >
              إغلاق
            </v-btn>
          </div>
        </v-card>
      </AppDialog>

      <AppDialog
        v-model="loginDialogOpen"
        max-width="420"
        persistent
        :close-on-backdrop="false"
      >
        <v-card class="assessment-dialog pa-5">
          <div class="assessment-dialog__title">
            رقم الدخول
          </div>
          <div class="assessment-dialog__body assessment-dialog__body--stack">
            <v-text-field
              v-model.trim="loginId"
              outlined
              dense
              hide-details
              label="رقم الدخول"
              @keydown.enter="handleLogin"
            />
            <div
              v-if="loginError"
              class="assessment-alert assessment-alert--error mb-0"
            >
              {{ loginError }}
            </div>
          </div>
          <div class="assessment-dialog__actions">
            <v-btn
              color="primary"
              depressed
              @click="handleLogin"
            >
              دخول
            </v-btn>
          </div>
        </v-card>
      </AppDialog>

      <div class="assessment-stage">
        <div class="assessment-hero">
          <img
            src="/اللوقو-شفاف.png"
            alt="شعار برنامج رخصة ممارس"
            class="assessment-hero__logo"
          >
          <div class="assessment-hero__divider" />
          <div class="assessment-hero__eyebrow">
            {{ assessmentLabel }}
          </div>
          <div class="assessment-hero__badge">
            {{ activeCourse ? activeCourse.title : assessmentLabel }}
          </div>
        </div>

        <v-card
          class="assessment-shell pa-4 pa-sm-6 pa-md-8"
          elevation="0"
        >
          <div
            v-if="dashboardLoading && !dashboardSnapshot"
            class="assessment-empty-state"
          >
            جارٍ تحميل البيانات...
          </div>
          <div
            v-else-if="dashboardError"
            class="assessment-alert assessment-alert--error"
          >
            {{ dashboardError }}
          </div>
          <div
            v-else-if="!studentResolved"
            class="assessment-empty-state"
          >
            جارٍ التحقق من بيانات الطالب...
          </div>
          <div
            v-else-if="!activeCourse"
            class="assessment-empty-state"
          >
            لا توجد دورة مفعلة حاليًا، لذلك لا يمكن عرض الاختبار الآن.
          </div>
          <template v-else>
            <div
              v-if="pageError"
              class="assessment-alert assessment-alert--error"
            >
              {{ pageError }}
            </div>

            <div
              v-if="student && !isAssessmentEnabled"
              class="assessment-empty-state"
            >
              لا توجد بيانات لهذا الاختبار حاليًا.
            </div>

            <div
              v-if="existingSubmission && !hasPendingPostSatisfaction"
              class="assessment-state assessment-state--success"
            >
              <div class="assessment-state__title">
                تم الإرسال
              </div>
            </div>

            <div
              v-else-if="existingSubmission && hasPendingPostSatisfaction"
              class="assessment-state assessment-state--info"
            >
              <div class="assessment-state__title">
                تم الإرسال
              </div>
            </div>

            <template v-if="canInteractWithAssessment && questions.length">
              <div class="assessment-section-title">
                أجب على الأسئلة التالية:
              </div>

              <article
                v-for="(question, index) in questions"
                :key="`${question.id}-${resetKey}`"
                class="assessment-question"
              >
                <div class="assessment-question__header">
                  <div class="assessment-question__title">
                    {{ index + 1 }}. {{ question.prompt }}
                  </div>
                  <div class="assessment-question__tools">
                    <label
                      v-if="question.allowFile"
                      :for="`question-file-${question.id}`"
                      class="assessment-pill-button"
                    >
                      إرفاق ملف
                    </label>
                    <input
                      v-if="question.allowFile"
                      :id="`question-file-${question.id}`"
                      type="file"
                      class="assessment-file-input"
                      @change="handleStudentFileSelect(question.id, $event)"
                    >
                    <button
                      v-if="question.attachmentDataUrl"
                      type="button"
                      class="assessment-pill-button assessment-pill-button--ghost"
                      @click="openAttachmentPreview({
                        name: question.attachmentName,
                        type: question.attachmentType,
                        dataUrl: question.attachmentDataUrl,
                      })"
                    >
                      عرض المحتوى
                    </button>
                    <button
                      v-if="files[question.id]?.dataUrl"
                      type="button"
                      class="assessment-pill-button assessment-pill-button--ghost"
                      @click="openAttachmentPreview(files[question.id])"
                    >
                      معاينة المرفق
                    </button>
                  </div>
                </div>

                <div
                  v-if="question.type === 'multiple'"
                  class="assessment-options-grid"
                >
                  <button
                    v-for="option in question.options"
                    :key="option"
                    type="button"
                    class="assessment-option"
                    :class="{ 'assessment-option--active': answers[question.id] === option }"
                    @click="setAnswer(question.id, option)"
                  >
                    {{ option }}
                  </button>
                </div>

                <v-textarea
                  v-else
                  :value="answers[question.id] || ''"
                  outlined
                  rows="5"
                  hide-details
                  class="assessment-textarea"
                  placeholder="اكتب إجابتك هنا"
                  @input="setAnswer(question.id, $event)"
                />

                <div
                  v-if="files[question.id]?.name"
                  class="assessment-question__file-name"
                >
                  تم اختيار: {{ files[question.id].name }}
                </div>
              </article>
            </template>

            <div
              v-if="resolvedAssessmentType === 'post' && student && satisfactionQuestions.length && !alreadySubmittedSatisfaction && (isAssessmentEnabled || existingSubmission)"
              class="assessment-satisfaction"
            >
              <div class="assessment-divider" />
              <div class="assessment-section-title">
                استبيان الرضا
              </div>

              <article
                v-for="(question, index) in satisfactionQuestions"
                :key="question.id"
                class="assessment-question"
              >
                <div class="assessment-question__header">
                  <div class="assessment-question__title">
                    {{ index + 1 }}. {{ question.prompt }}
                    <span
                      v-if="question.isRequired"
                      class="assessment-required"
                    >*</span>
                  </div>
                </div>

                <div
                  v-if="question.type === 'rating'"
                  class="assessment-rating-bar"
                >
                  <div class="assessment-rating-bar__labels">
                    <span>1</span>
                    <span class="assessment-rating-bar__value">
                      {{ satisfactionAnswers[question.id]?.ratingValue ?? 'غير محدد' }}
                    </span>
                    <span>10</span>
                  </div>
                  <v-slider
                    :value="satisfactionAnswers[question.id]?.ratingValue ?? 1"
                    min="1"
                    max="10"
                    step="1"
                    ticks="always"
                    tick-size="3"
                    hide-details
                    class="assessment-rating-bar__slider"
                    :class="{ 'assessment-rating-bar__slider--unanswered': satisfactionAnswers[question.id]?.ratingValue == null }"
                    @input="setSatisfactionRating(question.id, $event)"
                  />
                </div>

                <v-textarea
                  v-else
                  :value="satisfactionAnswers[question.id]?.textValue || ''"
                  outlined
                  rows="4"
                  hide-details
                  class="assessment-textarea"
                  placeholder="اكتب رأيك هنا"
                  @input="setSatisfactionText(question.id, $event)"
                />
              </article>
            </div>

            <div
              v-if="satisfactionError && (isAssessmentEnabled || existingSubmission)"
              class="assessment-alert assessment-alert--error"
            >
              {{ satisfactionError }}
            </div>

            <div
              v-if="canSubmitFlow && (questions.length || hasPendingPostSatisfaction)"
              class="assessment-submit-row"
            >
              <v-btn
                color="primary"
                depressed
                class="assessment-submit-button"
                :loading="submitting"
                :disabled="submitting || !isAssessmentEnabled"
                @click="handleSubmit"
              >
                إرسال
              </v-btn>
            </div>

            <div
              v-if="resolvedAssessmentType === 'post' && student && satisfactionQuestions.length && alreadySubmittedSatisfaction && (isAssessmentEnabled || existingSubmission)"
              class="assessment-alert assessment-alert--success"
            >
              شكرًا، تم استلام استبيان الرضا.
            </div>
          </template>
        </v-card>
      </div>
    </v-container>
  </div>
</template>

<script>
import AppDialog from '@/components/AppDialog.vue';
import {
  fetchPublicSnapshot,
  loadAccessSession,
  saveAccessSession,
  clearAccessSession,
  submitPublicAssessment,
  submitPublicSatisfactionResponses,
} from '@/services/api';

const MAX_STUDENT_ATTACHMENT_SIZE = 5 * 1024 * 1024;
const ASSESSMENT_LABELS = {
  pre: 'الاختبار القبلي',
  post: 'الاختبار البعدي',
  tasks: 'المهام الأدائية',
};

export default {
  name: 'CourseView',
  components: {
    AppDialog,
  },
  props: {
    assessmentType: {
      type: String,
      default: 'post',
    },
  },
  data() {
    return {
      publicSnapshot: null,
      publicLoading: false,
      publicError: '',
      answers: {},
      files: {},
      satisfactionAnswers: {},
      satisfactionError: '',
      pageError: '',
      loginId: '',
      loginError: '',
      studentLoginId: '',
      studentResolved: false,
      loginDialogOpen: false,
      submitting: false,
      previewDialogOpen: false,
      previewAttachment: null,
      resetKey: 0,
      currentTimestamp: Date.now(),
      assessmentClockTimer: null,
      publicLoadingGuardTimer: null,
      publicAutoRefreshTimer: null,
      publicRefreshInFlight: false,
    };
  },
  computed: {
    currentUser() {
      return this.$store?.state?.currentUser || null;
    },
    dashboardSnapshot() {
      return this.publicSnapshot;
    },
    dashboardLoading() {
      return this.publicLoading;
    },
    dashboardError() {
      return this.publicError;
    },
    resolvedAssessmentType() {
      return ['pre', 'post', 'tasks'].includes(this.assessmentType) ? this.assessmentType : 'post';
    },
    assessmentLabel() {
      return ASSESSMENT_LABELS[this.resolvedAssessmentType] || ASSESSMENT_LABELS.post;
    },
    authenticatedStudentLogin() {
      return ['student', 'trainee'].includes(this.currentUser?.role)
        ? String(this.currentUser.loginCode || '').trim()
        : '';
    },
    student() {
      const students = this.dashboardSnapshot?.students || [];
      const loginCode = this.studentLoginId || '';

      return students.find((student) => student.loginId === loginCode) || null;
    },
    activeCourse() {
      const courses = (this.dashboardSnapshot?.courses || []).filter((course) => course.entityType !== 'task');

      return courses.find((course) => course.isActive) || null;
    },
    questions() {
      if (!this.activeCourse) {
        return [];
      }

      if (this.resolvedAssessmentType === 'pre') {
        return this.activeCourse.preQuestions || [];
      }

      if (this.resolvedAssessmentType === 'tasks') {
        return this.activeCourse.taskQuestions || [];
      }

      return this.activeCourse.postQuestions || [];
    },
    isAssessmentEnabled() {
      if (!this.activeCourse) {
        return false;
      }

      const branchId = this.student?.branchId;
      const branchAvailability = branchId ? this.activeCourse.branchAvailability?.[branchId] || {} : {};
      const isEnabledBySettings = this.resolvedAssessmentType === 'pre'
        ? Boolean(this.activeCourse.isPreEnabled && (branchId ? branchAvailability.pre !== false : true))
        : this.resolvedAssessmentType === 'tasks'
          ? Boolean(this.activeCourse.isTasksEnabled && (branchId ? branchAvailability.tasks !== false : true))
          : Boolean(this.activeCourse.isPostEnabled && (branchId ? branchAvailability.post !== false : true));

      if (!isEnabledBySettings) {
        return false;
      }

      const branchWindow = branchId ? this.getWindowMeta(this.activeCourse.assessmentWindows?.[branchId]?.[this.resolvedAssessmentType]) : null;
      const globalWindow = this.getWindowMeta(this.activeCourse.assessmentWindows?.global?.[this.resolvedAssessmentType]);
      const hasWindowConfig = Boolean(branchWindow?.closesAt || globalWindow.closesAt);

      if (!hasWindowConfig) {
        return true;
      }

      return this.isWindowActive(branchWindow) || this.isWindowActive(globalWindow);
    },
    existingSubmission() {
      if (!this.activeCourse || !this.student) {
        return null;
      }

      return [...(this.dashboardSnapshot?.submissions || [])]
        .filter((submission) => (
          submission.courseId === this.activeCourse.id
          && submission.assessmentType === this.resolvedAssessmentType
          && submission.loginId === this.student.loginId
        ))
        .sort((left, right) => new Date(right.submittedAt).getTime() - new Date(left.submittedAt).getTime())[0] || null;
    },
    satisfactionQuestions() {
      if (!this.activeCourse) {
        return [];
      }

      return [...(this.dashboardSnapshot?.satisfactionQuestions || [])]
        .filter((question) => question.courseId === this.activeCourse.id)
        .sort((left, right) => left.sortOrder - right.sortOrder);
    },
    alreadySubmittedSatisfaction() {
      if (!this.activeCourse || !this.student || this.satisfactionQuestions.length === 0) {
        return false;
      }

      const responses = this.dashboardSnapshot?.satisfactionResponses || [];

      return this.satisfactionQuestions.every((question) => responses.some((response) => (
        response.courseId === this.activeCourse.id
        && response.questionId === question.id
        && response.loginCode === this.student.loginId
      )));
    },
    hasPendingPostSatisfaction() {
      return this.resolvedAssessmentType === 'post'
        && this.satisfactionQuestions.length > 0
        && !this.alreadySubmittedSatisfaction;
    },
    canInteractWithAssessment() {
      return Boolean(this.student && this.isAssessmentEnabled && !this.existingSubmission);
    },
    canSubmitFlow() {
      return Boolean(this.student && this.isAssessmentEnabled && (!this.existingSubmission || this.hasPendingPostSatisfaction));
    },
    previewKind() {
      const type = this.previewAttachment?.type || '';
      const dataUrl = this.previewAttachment?.dataUrl || '';

      if (type.startsWith('image/') || dataUrl.startsWith('data:image/')) {
        return 'image';
      }

      if (type === 'application/pdf' || dataUrl.startsWith('data:application/pdf')) {
        return 'pdf';
      }

      if (type.startsWith('video/') || dataUrl.startsWith('data:video/')) {
        return 'video';
      }

      return 'other';
    },
  },
  watch: {
    currentUser() {
      if (this.publicSnapshot) {
        this.restoreStudentSession();
      }
    },
  },
  created() {
    this.assessmentClockTimer = window.setInterval(() => {
      this.currentTimestamp = Date.now();
    }, 1000);

    this.loadPublicData();
    this.publicAutoRefreshTimer = window.setInterval(() => {
      this.refreshPublicSnapshotSilently();
    }, 2000);
  },
  beforeDestroy() {
    if (this.assessmentClockTimer) {
      window.clearInterval(this.assessmentClockTimer);
      this.assessmentClockTimer = null;
    }

    if (this.publicLoadingGuardTimer) {
      window.clearTimeout(this.publicLoadingGuardTimer);
      this.publicLoadingGuardTimer = null;
    }

    if (this.publicAutoRefreshTimer) {
      window.clearInterval(this.publicAutoRefreshTimer);
      this.publicAutoRefreshTimer = null;
    }
  },
  methods: {
    stopPublicAutoRefresh() {
      if (this.publicAutoRefreshTimer) {
        window.clearInterval(this.publicAutoRefreshTimer);
        this.publicAutoRefreshTimer = null;
      }
    },
    fetchPublicSnapshotWithTimeout(timeoutMs = 15000) {
      return new Promise((resolve, reject) => {
        const timeoutId = window.setTimeout(() => {
          reject(new Error('snapshot-timeout'));
        }, timeoutMs);

        fetchPublicSnapshot()
          .then((payload) => {
            window.clearTimeout(timeoutId);
            resolve(payload);
          })
          .catch((error) => {
            window.clearTimeout(timeoutId);
            reject(error);
          });
      });
    },
    getWindowMeta(value) {
      if (!value) {
        return { opensAt: '', closesAt: '' };
      }

      if (typeof value === 'string') {
        return { opensAt: '', closesAt: value };
      }

      return {
        opensAt: value.opensAt || '',
        closesAt: value.closesAt || '',
      };
    },
    isWindowActive(windowValue) {
      const windowMeta = this.getWindowMeta(windowValue);

      if (!windowMeta.closesAt) {
        return false;
      }

      const closesAt = new Date(windowMeta.closesAt).getTime();

      if (!Number.isFinite(closesAt) || closesAt <= this.currentTimestamp) {
        return false;
      }

      if (!windowMeta.opensAt) {
        return true;
      }

      const opensAt = new Date(windowMeta.opensAt).getTime();
      return !Number.isFinite(opensAt) || opensAt <= this.currentTimestamp;
    },
    async loadPublicData() {
      if (this.publicLoadingGuardTimer) {
        window.clearTimeout(this.publicLoadingGuardTimer);
        this.publicLoadingGuardTimer = null;
      }

      this.publicLoading = true;
      this.publicError = '';
      this.publicLoadingGuardTimer = window.setTimeout(() => {
        if (!this.publicLoading || this.publicSnapshot) {
          return;
        }

        this.publicLoading = false;
        this.studentResolved = true;
        this.publicError = 'انتهت مهلة التحميل. تأكد من تشغيل السيرفر ثم أعد المحاولة.';

        if (!this.student) {
          this.loginDialogOpen = true;
        }
      }, 12000);

      try {
        this.publicSnapshot = await this.fetchPublicSnapshotWithTimeout();
        this.restoreStudentSession();
      } catch (error) {
        if (error?.message === 'snapshot-timeout') {
          this.publicError = 'انتهت مهلة التحميل. تأكد من تشغيل السيرفر ثم أعد المحاولة.';
        } else {
          this.publicError = error?.response?.data?.message || error?.message || 'تعذر تحميل بيانات الاختبار.';
        }
      } finally {
        if (this.publicLoadingGuardTimer) {
          window.clearTimeout(this.publicLoadingGuardTimer);
          this.publicLoadingGuardTimer = null;
        }

        this.publicLoading = false;
        this.studentResolved = true;

        if (!this.student) {
          this.loginDialogOpen = true;
        }

        if (this.isAssessmentEnabled || this.existingSubmission) {
          this.stopPublicAutoRefresh();
        }
      }
    },
    async refreshPublicSnapshotSilently() {
      if (this.publicLoading || this.submitting || this.publicRefreshInFlight) {
        return;
      }

      this.publicRefreshInFlight = true;

      try {
        const payload = await this.fetchPublicSnapshotWithTimeout(8000);
        this.publicSnapshot = payload;

        if (!this.studentLoginId) {
          this.restoreStudentSession();
        }

        if (this.isAssessmentEnabled || this.existingSubmission) {
          this.stopPublicAutoRefresh();
        }
      } catch {
        // Ignore transient polling failures; foreground loading handles user feedback.
      } finally {
        this.publicRefreshInFlight = false;
      }
    },
    restoreStudentSession() {
      const queryLogin = typeof this.$route.query.login === 'string' ? this.$route.query.login.trim() : '';
      const session = loadAccessSession();
      const sessionLogin = session?.role === 'student' ? String(session.loginCode || '').trim() : '';
      const preferredLogin = this.authenticatedStudentLogin || sessionLogin || queryLogin;

      this.loginId = queryLogin || this.authenticatedStudentLogin || sessionLogin || '';
      this.studentLoginId = '';
      this.loginError = '';

      if (!preferredLogin) {
        return;
      }

      const foundStudent = (this.publicSnapshot?.students || []).find((student) => student.loginId === preferredLogin);

      if (!foundStudent) {
        if (sessionLogin) {
          clearAccessSession();
        }

        this.loginDialogOpen = true;
        return;
      }

      this.studentLoginId = foundStudent.loginId;
      this.loginId = foundStudent.loginId;
      this.loginDialogOpen = false;
      this.syncStudentSession(foundStudent);
    },
    syncStudentSession(student) {
      saveAccessSession({
        role: 'student',
        loginCode: student.loginId,
        name: student.name,
        redirectPath: this.$route.fullPath,
        branchId: student.branchId,
      });
    },
    handleLogin() {
      const trimmed = this.loginId.trim();

      if (!trimmed) {
        this.loginError = 'أدخل رقم الدخول.';
        return;
      }

      const foundStudent = (this.publicSnapshot?.students || []).find((student) => student.loginId === trimmed);

      if (!foundStudent) {
        this.loginError = 'رقم الدخول غير موجود.';
        return;
      }

      this.studentLoginId = foundStudent.loginId;
      this.loginId = foundStudent.loginId;
      this.loginError = '';
      this.pageError = '';
      this.loginDialogOpen = false;
      this.syncStudentSession(foundStudent);
    },
    setAnswer(questionId, value) {
      this.$set(this.answers, questionId, value);
      this.pageError = '';
    },
    setSatisfactionRating(questionId, value) {
      this.$set(this.satisfactionAnswers, questionId, {
        ratingValue: value,
        textValue: '',
      });
      this.satisfactionError = '';
    },
    setSatisfactionText(questionId, value) {
      this.$set(this.satisfactionAnswers, questionId, {
        ratingValue: null,
        textValue: value,
      });
      this.satisfactionError = '';
    },
    openAttachmentPreview(attachment) {
      this.previewAttachment = attachment;
      this.previewDialogOpen = true;
    },
    closePreview() {
      this.previewDialogOpen = false;
      this.previewAttachment = null;
    },
    async handleStudentFileSelect(questionId, event) {
      const file = event?.target?.files?.[0];

      if (!file) {
        return;
      }

      if (file.size > MAX_STUDENT_ATTACHMENT_SIZE) {
        this.pageError = 'حجم الملف المرفوع كبير جدًا. الحد الأقصى 5 ميجابايت.';
        event.target.value = '';
        return;
      }

      const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
        reader.onerror = () => reject(new Error('file-read-failed'));
        reader.readAsDataURL(file);
      }).catch(() => '');

      if (!dataUrl) {
        this.pageError = 'تعذر قراءة الملف المرفوع.';
        event.target.value = '';
        return;
      }

      this.$set(this.files, questionId, {
        name: file.name,
        type: file.type,
        dataUrl,
      });
      this.pageError = '';
      event.target.value = '';
    },
    validateSatisfactionAnswers() {
      for (const question of this.satisfactionQuestions) {
        if (!question.isRequired) {
          continue;
        }

        if (question.type === 'rating' && this.satisfactionAnswers[question.id]?.ratingValue == null) {
          this.satisfactionError = 'أجب على جميع أسئلة الرضا الإلزامية.';
          return false;
        }

        if (question.type === 'text' && !(this.satisfactionAnswers[question.id]?.textValue || '').trim()) {
          this.satisfactionError = 'أجب على جميع أسئلة الرضا الإلزامية.';
          return false;
        }
      }

      this.satisfactionError = '';
      return true;
    },
    async handleSubmit() {
      if (!this.activeCourse) {
        this.pageError = 'لا توجد دورة مفعلة حاليًا.';
        return;
      }

      if (!this.isAssessmentEnabled) {
        this.pageError = 'هذا الاختبار غير متاح لك الآن.';
        return;
      }

      if (!this.student) {
        this.pageError = 'تعذر تحديد الطالب الحالي.';
        return;
      }

      if (this.existingSubmission && !this.hasPendingPostSatisfaction) {
        this.pageError = 'تم إرسال النتيجة مسبقًا، ولا يمكن إعادة الإرسال مرة أخرى.';
        return;
      }

      if (!this.existingSubmission) {
        for (const question of this.questions) {
          if (!(this.answers[question.id] || '').trim()) {
            this.pageError = 'الرجاء إكمال جميع الأسئلة';
            return;
          }
        }
      }

      if (this.resolvedAssessmentType === 'post' && this.satisfactionQuestions.length > 0 && !this.alreadySubmittedSatisfaction && !this.validateSatisfactionAnswers()) {
        return;
      }


      this.submitting = true;

      try {
        if (!this.existingSubmission) {
          await submitPublicAssessment({
            courseId: this.activeCourse.id,
            assessmentType: this.resolvedAssessmentType,
            studentName: this.student.name,
            loginId: this.student.loginId,
            answers: this.questions.map((question) => ({
              questionId: question.id,
              value: this.answers[question.id] || '',
              fileName: this.files[question.id]?.name || null,
              fileType: this.files[question.id]?.type || null,
              fileDataUrl: this.files[question.id]?.dataUrl || null,
            })),
          });
        }

        if (this.resolvedAssessmentType === 'post' && this.satisfactionQuestions.length > 0 && !this.alreadySubmittedSatisfaction) {
          await submitPublicSatisfactionResponses(this.satisfactionQuestions.map((question) => ({
            courseId: this.activeCourse.id,
            questionId: question.id,
            loginCode: this.student.loginId,
            studentName: this.student.name,
            ratingValue: question.type === 'rating' ? (this.satisfactionAnswers[question.id]?.ratingValue ?? null) : null,
            textValue: question.type === 'text' ? (this.satisfactionAnswers[question.id]?.textValue || '') : '',
          })));
        }

        this.pageError = '';
        this.satisfactionError = '';
        this.answers = {};
        this.files = {};
        this.satisfactionAnswers = {};
        this.resetKey += 1;
        this.publicSnapshot = await this.fetchPublicSnapshotWithTimeout();
      } catch (error) {
        this.pageError = error?.response?.data?.message || error?.message || 'تعذر إرسال الاختبار.';
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>

<style scoped>
.assessment-page {
  --assessment-surface-radius: 64px;
  --assessment-deep-color: #08384a;
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background: #08384a;
}

.assessment-page__container {
  position: relative;
  z-index: 2;
  max-width: 1120px;
}

.assessment-stage {
  max-width: 900px;
  margin: 0 auto;
}

.assessment-hero {
  display: grid;
  justify-items: center;
  gap: 14px;
  margin-bottom: 26px;
  text-align: center;
}

.assessment-hero__logo {
  width: 92px;
  height: 92px;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(100%) drop-shadow(0 12px 24px rgba(3, 27, 36, 0.28));
}

.assessment-hero__divider {
  width: 62px;
  height: 5px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
}

.assessment-hero__eyebrow {
  color: rgba(255, 255, 255, 0.82);
  font-size: 0.98rem;
  font-weight: 800;
}

.assessment-hero__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 58px;
  padding: 12px 30px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
  color: #ffffff;
  font-size: clamp(1.4rem, 3vw, 2.15rem);
  font-weight: 900;
  line-height: 1.3;
  backdrop-filter: blur(10px);
}

.assessment-shell {
  border: 1px solid rgba(205, 228, 235, 0.42);
  border-radius: var(--assessment-surface-radius);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(244, 250, 252, 0.95) 100%);
  box-shadow: 0 34px 84px rgba(3, 27, 36, 0.28);
}

.assessment-orbit,
.assessment-glow,
.assessment-grid,
.assessment-top-shade,
.assessment-radial {
  position: absolute;
  pointer-events: none;
}

.assessment-orbit {
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  border: 1px dashed rgba(255, 255, 255, 0.08);
  border-radius: 50%;
}

.assessment-orbit--large {
  width: 1400px;
  height: 1400px;
}

.assessment-orbit--medium {
  width: 920px;
  height: 920px;
}

.assessment-orbit--small {
  width: 540px;
  height: 540px;
}

.assessment-glow {
  top: -18%;
  right: -12%;
  width: 860px;
  height: 860px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(42, 148, 178, 0.18) 0%, rgba(8, 56, 74, 0) 70%);
  filter: blur(40px);
}

.assessment-grid {
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
  background-size: 40px 40px;
}

.assessment-top-shade {
  top: 0;
  right: 0;
  left: 0;
  height: 180px;
  background: linear-gradient(180deg, rgba(3, 27, 36, 0.72) 0%, rgba(8, 56, 74, 0) 100%);
}

.assessment-radial {
  border-radius: 50%;
  filter: blur(34px);
}

.assessment-radial--one {
  width: 280px;
  height: 280px;
  right: 5%;
  top: 18%;
  background: rgba(56, 189, 248, 0.16);
}

.assessment-radial--two {
  width: 240px;
  height: 240px;
  left: 8%;
  bottom: 14%;
  background: rgba(110, 231, 183, 0.12);
}

.assessment-alert,
.assessment-state,
.assessment-empty-state {
  border-radius: 32px;
  padding: 18px 20px;
}

.assessment-alert {
  margin-bottom: 18px;
  font-weight: 700;
}

.assessment-alert--error {
  border: 1px solid rgba(220, 38, 38, 0.18);
  background: rgba(220, 38, 38, 0.06);
  color: #b91c1c;
}

.assessment-alert--warning {
  border: 1px solid rgba(217, 119, 6, 0.18);
  background: rgba(245, 158, 11, 0.08);
  color: #a16207;
}

.assessment-alert--success {
  border: 1px solid rgba(5, 150, 105, 0.2);
  background: rgba(16, 185, 129, 0.08);
  color: #047857;
}

.assessment-empty-state,
.assessment-state {
  margin-bottom: 20px;
  border: 1px dashed rgba(15, 23, 42, 0.14);
  background: rgba(248, 250, 252, 0.72);
  color: #475569;
}

.assessment-state--success {
  border-style: solid;
  border-color: rgba(14, 165, 233, 0.18);
  background: rgba(14, 165, 233, 0.08);
  color: #075985;
}

.assessment-state--info {
  border-style: solid;
  border-color: rgba(14, 165, 233, 0.18);
  background: rgba(14, 165, 233, 0.08);
  color: #0f766e;
}

.assessment-state__title {
  font-size: 1.05rem;
  font-weight: 900;
}

.assessment-state__text {
  margin-top: 8px;
  font-weight: 600;
}

.assessment-section-title {
  margin-bottom: 20px;
  color: #0f172a;
  font-size: 1.18rem;
  font-weight: 900;
  text-align: right;
}

.assessment-question {
  margin: 0;
  padding: 22px 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
}

.assessment-question + .assessment-question {
  border-top: 1px solid rgba(166, 210, 226, 0.38);
}

.assessment-question__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 16px;
}

.assessment-question__title {
  color: #0f172a;
  flex: 1 1 100%;
  text-align: right;
  font-size: 1.06rem;
  font-weight: 900;
  line-height: 1.9;
}

.assessment-question__tools {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

.assessment-pill-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: 6px 16px;
  border: 1px solid rgba(27, 31, 35, 0.15);
  border-radius: 6px;
  background: #ffffff;
  color: #107699;
  font-size: 16px;
  font-weight: 700;
  line-height: 22px;
  box-shadow: rgba(27, 31, 35, 0.06) 0 1px 0;
  cursor: pointer;
}

.assessment-pill-button--ghost {
  background: #f6fbfd;
}

.assessment-file-input {
  display: none;
}

.assessment-options-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.assessment-option,
.assessment-rating {
  min-height: 56px;
  border: 1px solid rgba(166, 210, 226, 0.5);
  border-radius: 28px;
  background: #fff;
  color: #123f56;
  font-size: 0.95rem;
  font-weight: 800;
  transition: 0.2s ease;
}

.assessment-option--active,
.assessment-rating--active {
  border-color: transparent;
  background: var(--assessment-deep-color);
  color: #fff;
  box-shadow: 0 18px 30px rgba(8, 56, 74, 0.26);
}

.assessment-textarea :deep(.v-input__slot) {
  border-radius: 30px !important;
  background: rgba(255, 255, 255, 0.96) !important;
}

.assessment-question__file-name {
  margin-top: 12px;
  color: #64748b;
  font-size: 0.84rem;
  font-weight: 700;
  word-break: break-word;
}

.assessment-satisfaction {
  margin-top: 28px;
}

.assessment-divider {
  height: 1px;
  margin: 10px 0 26px;
  background: linear-gradient(90deg, transparent, rgba(16, 118, 153, 0.45), transparent);
}

.assessment-required {
  color: #dc2626;
}

.assessment-rating-bar {
  display: grid;
  gap: 8px;
}

.assessment-rating-bar__labels {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: #406174;
  font-size: 0.86rem;
  font-weight: 800;
}

.assessment-rating-bar__value {
  min-width: 78px;
  padding: 3px 10px;
  border-radius: 999px;
  background: rgba(16, 118, 153, 0.08);
  color: #107699;
  text-align: center;
}

.assessment-rating-bar__slider {
  margin-top: -4px;
}

.assessment-rating-bar__slider:deep(.v-slider__track-background),
.assessment-rating-bar__slider:deep(.v-slider__track-fill) {
  height: 8px;
  border-radius: 999px;
}

.assessment-rating-bar__slider:deep(.v-slider__thumb) {
  width: 18px;
  height: 18px;
}

.assessment-rating-bar__slider:deep(.v-slider__tick) {
  width: 2px;
  height: 8px;
  border-radius: 999px;
}

.assessment-rating-bar__slider--unanswered {
  opacity: 0.72;
}

.assessment-submit-row {
  display: flex;
  justify-content: flex-start;
  padding-top: 8px;
}

.assessment-submit-button {
  min-width: 170px;
  min-height: 54px !important;
  border-radius: 28px;
  font-size: 1rem;
  font-weight: 900;
  box-shadow: 0 18px 30px rgba(17, 101, 126, 0.28);
}

.assessment-dialog__title {
  margin-bottom: 16px;
}

.assessment-dialog__image,
.assessment-dialog__video,
.assessment-dialog__frame {
  width: 100%;
  border: 0;
  border-radius: 20px;
}

.assessment-dialog__image,
.assessment-dialog__video {
  max-height: 72vh;
  object-fit: contain;
}

.assessment-dialog__frame {
  height: 72vh;
}

.assessment-dialog__actions {
  display: flex;
  justify-content: flex-start;
  margin-top: 18px;
}

@media (max-width: 960px) {
  .assessment-options-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 600px) {
  .assessment-shell {
    border-radius: 40px;
  }

  .assessment-hero__badge {
    width: 100%;
    padding-inline: 18px;
    font-size: 1.22rem;
  }

  .assessment-question {
    padding: 18px 0;
  }
}
</style>
