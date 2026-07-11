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
              :src="previewAttachmentSource"
              alt="معاينة المرفق"
              class="assessment-dialog__image"
            >
            <iframe
              v-else-if="previewKind === 'pdf'"
              :src="previewAttachmentSource"
              title="معاينة PDF"
              class="assessment-dialog__frame"
            />
            <video
              v-else-if="previewKind === 'video'"
              :src="previewAttachmentSource"
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
            <AppButton
              variant="secondary"
              @click="closePreview"
            >
              إغلاق
            </AppButton>
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
            <AppButton
              variant="primary"
              @click="handleLogin"
            >
              دخول
            </AppButton>
          </div>
        </v-card>
      </AppDialog>

      <div class="assessment-stage">
        <div class="assessment-hero">
          <img
            :src="$publicAsset('اللوقو-شفاف.png')"
            alt="شعار برنامج رخصة ممارس"
            class="assessment-hero__logo"
          >
          <div class="assessment-hero__divider" />
          <div class="assessment-hero__eyebrow">
            المهمة الأدائية
          </div>
          <div class="assessment-hero__badge">
            {{ selectedTask ? selectedTask.title : 'المهمة الأدائية' }}
          </div>
        </div>

        <div
          v-if="selectedTaskSummary"
          class="assessment-hero__summary"
        >
          {{ selectedTaskSummary }}
        </div>

        <v-card
          class="assessment-shell pa-4 pa-sm-6 pa-md-8"
          elevation="0"
        >
          <div
            v-if="publicLoading && !publicSnapshot"
            class="assessment-empty-state"
          >
            جارٍ تحميل البيانات...
          </div>
          <div
            v-else-if="publicError"
            class="assessment-error-state"
          >
            <div class="assessment-alert assessment-alert--error">
              {{ publicError }}
            </div>
            <AppButton
              variant="secondary"
              class="mt-4"
              @click="loadPublicData"
            >
              إعادة المحاولة
            </AppButton>
          </div>
          <div
            v-else-if="!studentResolved"
            class="assessment-empty-state"
          >
            جارٍ التحقق من بيانات الطالب...
          </div>
          <div
            v-else-if="tasks.length === 0"
            class="assessment-empty-state"
          >
            لا توجد مهام مفعلة حاليًا.
          </div>
          <div
            v-else-if="!student"
            class="assessment-empty-state"
          >
            أدخل رقم الدخول للمتابعة إلى المهمة الأدائية.
          </div>
          <template v-else>
            <div
              v-if="pageError"
              class="assessment-alert assessment-alert--error"
            >
              {{ pageError }}
            </div>

            <div
              v-if="selectedTask && student && !taskIsEnabled"
              class="assessment-empty-state"
            >
              لا توجد بيانات لهذه المهمة حاليًا.
            </div>

            <div
              v-if="existingSubmission"
              class="assessment-state assessment-state--success"
            >
              <div class="assessment-state__title">
                تم الإرسال
              </div>
            </div>

            <template v-else-if="selectedTask">
              <div
                v-if="currentTaskVideo"
                class="task-video-card"
              >
                <div class="task-video-card__title">
                  الفيديو
                </div>
                <iframe
                  v-if="currentTaskVideo.kind === 'embed'"
                  :src="currentTaskVideo.src"
                  class="task-video-card__frame"
                  title="فيديو المهمة الأدائية"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                  allowfullscreen
                />
                <video
                  v-else
                  :src="currentTaskVideo.src"
                  controls
                  playsinline
                  class="task-video-card__frame"
                />
              </div>

              <div
                v-if="selectedTask.taskMode === 'document'"
                class="task-document-layout"
              >
                <div class="task-editor-shell">
                  <RichTextEditor
                    :value="documentAnswer"
                    :protected-content="selectedTaskTemplateContent"
                    :lock-images="true"
                    :allow-protected-editing="true"
                    :disabled="Boolean(existingSubmission || !taskIsEnabled)"
                    min-height="420px"
                    @input="setDocumentAnswer"
                  />
                </div>
              </div>

              <template v-else>
                <div class="assessment-section-title">
                  أجب على الأسئلة التالية:
                </div>
                <article
                  v-for="(question, index) in taskQuestions"
                  :key="`${question.id}-${selectedTaskId}`"
                  class="assessment-question"
                >
                  <div class="assessment-question__header">
                    <div class="assessment-question__title">
                      {{ index + 1 }}. {{ question.prompt }}
                    </div>
                    <div class="assessment-question__tools">
                      <label
                        v-if="question.allowFile"
                        :for="`task-file-${question.id}`"
                        class="assessment-pill-button"
                      >
                        إرفاق ملف
                      </label>
                      <input
                        v-if="question.allowFile"
                        :id="`task-file-${question.id}`"
                        type="file"
                        class="assessment-file-input"
                        @change="handleFileSelect(question.id, $event)"
                      >
                      <AppButton
                        v-if="question.attachmentDataUrl"
                        variant="secondary"
                        class="assessment-pill-button assessment-pill-button--ghost"
                        @click="openAttachmentPreview({
                          name: question.attachmentName,
                          type: question.attachmentType,
                          dataUrl: question.attachmentDataUrl,
                        })"
                      >
                        عرض المحتوى
                      </AppButton>
                      <AppButton
                        v-if="files[question.id]?.previewUrl"
                        variant="secondary"
                        class="assessment-pill-button assessment-pill-button--ghost"
                        @click="openAttachmentPreview(files[question.id])"
                      >
                        معاينة المرفق
                      </AppButton>
                    </div>
                  </div>

                  <div
                    v-if="question.type === 'multiple' || question.type === 'truefalse'"
                    class="assessment-options-grid"
                  >
                    <AppChoiceButton
                      v-for="option in question.options"
                      :key="option"
                      block
                      class="assessment-option"
                      :class="{ 'assessment-option--active': answers[question.id] === option }"
                      :active="answers[question.id] === option"
                      @click="setAnswer(question.id, option)"
                    >
                      {{ option }}
                    </AppChoiceButton>
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
                v-if="canSubmit"
                class="assessment-submit-row"
              >
                <AppButton
                  variant="primary"
                  class="assessment-submit-button"
                  :loading="submitting"
                  :disabled="submitting || !taskIsEnabled"
                  @click="handleSubmit"
                >
                  إرسال
                </AppButton>
              </div>
            </template>
          </template>
        </v-card>
      </div>
    </v-container>
  </div>
</template>

<script>
import { AppButton, AppChoiceButton, AppDialog } from '@/components/ui';
import RichTextEditor from '@/components/RichTextEditor.vue';
import {
  fetchPublicSnapshot,
  loadAccessSession,
  saveAccessSession,
  clearAccessSession,
  submitPublicAssessment,
} from '@/services/api';
import { hasMeaningfulDocumentContent } from '@/utils/documentContent';

const readTaskFileAsDataUrl = (file) => new Promise((resolve, reject) => {
  const reader = new FileReader();

  reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
  reader.onerror = () => reject(new Error('task-file-read-failed'));
  reader.readAsDataURL(file);
});

export default {
  name: 'TasksView',
  components: {
    AppDialog,
    AppButton,
    AppChoiceButton,
    RichTextEditor,
  },
  data() {
    return {
      publicSnapshot: null,
      publicLoading: false,
      publicError: '',
      loginId: '',
      loginError: '',
      studentLoginId: '',
      studentResolved: false,
      loginDialogOpen: false,
      selectedTaskId: '',
      answers: {},
      files: {},
      pageError: '',
      submitting: false,
      previewDialogOpen: false,
      previewAttachment: null,
      currentTimestamp: Date.now(),
      taskClockTimer: null,
      publicLoadingGuardTimer: null,
    };
  },
  computed: {
    currentUser() {
      return this.$store?.state?.currentUser || null;
    },
    isAuthenticatedNonStudent() {
      return Boolean(this.currentUser && this.currentUser.role && !['student', 'trainee'].includes(this.currentUser.role));
    },
    authenticatedStudentLogin() {
      return ['student', 'trainee'].includes(this.currentUser?.role)
        ? String(this.currentUser.loginCode || '').trim()
        : '';
    },
    tasks() {
      return [...(this.publicSnapshot?.courses || [])]
        .filter((course) => course.entityType === 'task')
        .sort((left, right) => (left.sortOrder || 0) - (right.sortOrder || 0));
    },
    selectedTask() {
      return this.tasks.find((task) => task.id === this.selectedTaskId) || this.tasks[0] || null;
    },
    taskQuestions() {
      return this.selectedTask?.taskQuestions || [];
    },
    documentQuestion() {
      return this.taskQuestions[0] || null;
    },
    selectedTaskSummary() {
      return typeof this.selectedTask?.taskDescription === 'string'
        ? this.selectedTask.taskDescription.trim()
        : '';
    },
    selectedTaskTemplateContent() {
      return typeof this.selectedTask?.taskTemplateContent === 'string'
        ? this.selectedTask.taskTemplateContent
        : '';
    },
    documentInitialContent() {
      if (!this.documentQuestion) {
        return '';
      }

      const submittedAnswer = (this.existingSubmission?.answers || []).find(
        (answer) => answer.questionId === this.documentQuestion.id,
      );

      if (typeof submittedAnswer?.value === 'string' && submittedAnswer.value.trim()) {
        return submittedAnswer.value;
      }

      return this.selectedTaskTemplateContent;
    },
    documentAnswer() {
      if (!this.documentQuestion) {
        return '';
      }

      return Object.prototype.hasOwnProperty.call(this.answers, this.documentQuestion.id)
        ? this.answers[this.documentQuestion.id]
        : this.documentInitialContent;
    },
    currentTaskVideo() {
      return this.resolveTaskVideo(this.selectedTask?.youtubeUrl || '');
    },
    student() {
      return (this.publicSnapshot?.students || []).find((student) => student.loginId === this.studentLoginId) || null;
    },
    taskIsEnabled() {
      if (!this.selectedTask || !this.student) {
        return false;
      }

      const branchAvailability = this.selectedTask.branchAvailability?.[this.student.branchId] || {};

      if (!(this.selectedTask.isTasksEnabled && branchAvailability.tasks !== false)) {
        return false;
      }

      const branchWindow = this.getWindowMeta(this.selectedTask.assessmentWindows?.[this.student.branchId]?.tasks);
      const globalWindow = this.getWindowMeta(this.selectedTask.assessmentWindows?.global?.tasks);
      const hasWindowConfig = Boolean(branchWindow.closesAt || globalWindow.closesAt);

      if (!hasWindowConfig) {
        return true;
      }

      return this.isWindowActive(branchWindow) || this.isWindowActive(globalWindow);
    },
    existingSubmission() {
      if (!this.selectedTask || !this.student) {
        return null;
      }

      return [...(this.publicSnapshot?.submissions || [])]
        .filter((submission) => submission.courseId === this.selectedTask.id && submission.assessmentType === 'tasks' && submission.loginId === this.student.loginId)
        .sort((left, right) => new Date(right.submittedAt).getTime() - new Date(left.submittedAt).getTime())[0] || null;
    },
    canSubmit() {
      return Boolean(this.selectedTask && this.student && this.taskIsEnabled && !this.existingSubmission);
    },
    previewKind() {
      const type = this.previewAttachment?.type || '';
      const source = this.previewAttachmentSource;

      if (type.startsWith('image/') || source.startsWith('data:image/')) {
        return 'image';
      }

      if (type === 'application/pdf' || source.startsWith('data:application/pdf')) {
        return 'pdf';
      }

      if (type.startsWith('video/') || source.startsWith('data:video/')) {
        return 'video';
      }

      return 'other';
    },
    previewAttachmentSource() {
      return this.previewAttachment?.previewUrl || this.previewAttachment?.dataUrl || '';
    },
  },
  watch: {
    currentUser() {
      if (this.redirectAuthenticatedNonStudent()) {
        return;
      }

      if (this.publicSnapshot) {
        this.restoreStudentSession();
      }
    },
    tasks: {
      immediate: true,
      handler(tasks) {
        if (!tasks.length) {
          this.selectedTaskId = '';
          return;
        }

        const requestedTaskId = typeof this.$route.query.taskId === 'string' ? this.$route.query.taskId.trim() : '';

        if (requestedTaskId && tasks.some((task) => task.id === requestedTaskId)) {
          this.selectedTaskId = requestedTaskId;
          return;
        }

        if (!tasks.some((task) => task.id === this.selectedTaskId)) {
          this.selectedTaskId = tasks[0].id;
        }
      },
    },
    selectedTaskId: {
      immediate: true,
      handler(taskId) {
        this.clearSelectedFiles();

        if (!taskId) {
          this.answers = {};
          this.files = {};
          return;
        }

        this.answers = {};

        this.files = {};
      },
    },
  },
  created() {
    if (this.redirectAuthenticatedNonStudent()) {
      return;
    }

    this.taskClockTimer = window.setInterval(() => {
      this.currentTimestamp = Date.now();
    }, 1000);

    this.loadPublicData();
  },
  beforeDestroy() {
    if (this.taskClockTimer) {
      window.clearInterval(this.taskClockTimer);
      this.taskClockTimer = null;
    }

    if (this.publicLoadingGuardTimer) {
      window.clearTimeout(this.publicLoadingGuardTimer);
      this.publicLoadingGuardTimer = null;
    }

    this.clearSelectedFiles();
  },
  methods: {
    resolveAuthenticatedFallbackRoute() {
      if (this.currentUser?.role === 'trainee') {
        return { name: 'trainee' };
      }

      if (this.currentUser?.role === 'reciter') {
        return { name: 'reciter' };
      }

      if (['admin', 'male_manager', 'female_manager'].includes(this.currentUser?.role)) {
        return { name: 'dashboard' };
      }

      return { name: 'home' };
    },
    redirectAuthenticatedNonStudent() {
      if (!this.isAuthenticatedNonStudent) {
        return false;
      }

      const targetRoute = this.resolveAuthenticatedFallbackRoute();

      if (targetRoute?.name && this.$route.name !== targetRoute.name) {
        this.$router.replace(targetRoute);
      }

      return true;
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
        this.publicSnapshot = Object.freeze(await this.fetchPublicSnapshotWithTimeout());
        this.restoreStudentSession();
      } catch (error) {
        if (error?.message === 'snapshot-timeout') {
          this.publicError = 'انتهت مهلة التحميل. تأكد من تشغيل السيرفر ثم أعد المحاولة.';
        } else {
          this.publicError = error?.response?.data?.message || error?.message || 'تعذر تحميل بيانات المهام الأدائية.';
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
    selectTask(taskId) {
      this.selectedTaskId = taskId;
      this.pageError = '';
    },
    setAnswer(questionId, value) {
      this.$set(this.answers, questionId, value);
      this.pageError = '';
    },
    setDocumentAnswer(value) {
      if (!this.documentQuestion) {
        return;
      }

      this.$set(this.answers, this.documentQuestion.id, value);
      this.pageError = '';
    },
    resolveTaskVideo(rawUrl) {
      const value = String(rawUrl || '').trim();

      if (!value) {
        return null;
      }

      const directVideoPattern = /\.(mp4|webm|ogg)(\?.*)?$/i;

      if (directVideoPattern.test(value)) {
        return {
          kind: 'video',
          src: value,
        };
      }

      try {
        const parsed = new URL(value);
        const host = parsed.hostname.replace(/^www\./i, '').toLowerCase();
        let videoId = '';

        if (host === 'youtu.be') {
          videoId = parsed.pathname.replace(/^\//, '').split('/')[0] || '';
        } else if (host.endsWith('youtube.com')) {
          if (parsed.pathname === '/watch') {
            videoId = parsed.searchParams.get('v') || '';
          } else if (parsed.pathname.startsWith('/embed/')) {
            videoId = parsed.pathname.split('/embed/')[1]?.split('/')[0] || '';
          } else if (parsed.pathname.startsWith('/shorts/')) {
            videoId = parsed.pathname.split('/shorts/')[1]?.split('/')[0] || '';
          }
        }

        if (videoId) {
          return {
            kind: 'embed',
            src: `https://www.youtube-nocookie.com/embed/${videoId}?rel=0`,
          };
        }
      } catch (error) {
        return null;
      }

      return null;
    },
    openAttachmentPreview(attachment) {
      this.previewAttachment = attachment;
      this.previewDialogOpen = true;
    },
    closePreview() {
      this.previewDialogOpen = false;
      this.previewAttachment = null;
    },
    handleFileSelect(questionId, event) {
      const file = event?.target?.files?.[0];

      if (!file) {
        return;
      }

      this.clearSelectedFile(questionId);

      let previewUrl = '';
      try {
        previewUrl = URL.createObjectURL(file);
      } catch (error) {
        previewUrl = '';
      }

      this.$set(this.files, questionId, {
        file,
        name: file.name,
        type: file.type,
        previewUrl,
      });
      this.pageError = '';
      event.target.value = '';
    },
    clearSelectedFile(questionId) {
      const currentFile = this.files[questionId];

      if (currentFile?.previewUrl) {
        URL.revokeObjectURL(currentFile.previewUrl);
      }
    },
    clearSelectedFiles() {
      Object.keys(this.files || {}).forEach((questionId) => {
        this.clearSelectedFile(questionId);
      });
    },
    async buildSubmissionAnswers(questionList) {
      return Promise.all(questionList.map(async (question) => {
        const selectedFile = this.files[question.id] || null;
        let fileDataUrl = null;

        if (selectedFile?.file) {
          fileDataUrl = await readTaskFileAsDataUrl(selectedFile.file);

          if (!fileDataUrl) {
            throw new Error('تعذر قراءة المرفق. جرب ملفا أصغر أو أغلقه من البرامج الأخرى ثم أعد اختياره.');
          }
        }

        return {
          questionId: question.id,
          value: this.selectedTask.taskMode === 'document'
            ? this.documentAnswer
            : (this.answers[question.id] || ''),
          fileName: selectedFile?.name || null,
          fileType: selectedFile?.type || null,
          fileDataUrl,
        };
      }));
    },
    async handleSubmit() {
      if (!this.selectedTask || !this.student) {
        this.pageError = 'سجّل الدخول أولًا.';
        return;
      }

      if (this.existingSubmission) {
        this.pageError = 'تم إرسال هذه المهمة الأدائية مسبقًا.';
        return;
      }

      if (!this.taskIsEnabled) {
        this.pageError = 'هذه المهمة الأدائية غير متاحة لك الآن.';
        return;
      }

      if (this.selectedTask.taskMode === 'document') {
        if (!this.documentQuestion || !hasMeaningfulDocumentContent(this.documentAnswer)) {
          this.pageError = 'اكتب محتوى المهمة الأدائية أولًا.';
          return;
        }
      } else {
        for (const question of this.taskQuestions) {
          if (!(this.answers[question.id] || '').trim()) {
            this.pageError = 'الرجاء إكمال جميع الأسئلة';
            return;
          }
        }
      }

      this.submitting = true;

      try {
        const questionList = this.selectedTask.taskMode === 'document'
          ? [this.documentQuestion].filter(Boolean)
          : this.taskQuestions;

        const answers = await this.buildSubmissionAnswers(questionList);

        await submitPublicAssessment({
          courseId: this.selectedTask.id,
          assessmentType: 'tasks',
          studentName: this.student.name,
          loginId: this.student.loginId,
          answers,
        });

        this.pageError = '';
        this.publicSnapshot = Object.freeze(await this.fetchPublicSnapshotWithTimeout());
      } catch (error) {
        this.pageError = error?.response?.data?.message || error?.message || 'تعذر إرسال المهمة الأدائية.';
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>

<style scoped>
.assessment-page {
  position: relative;
  min-height: 100vh;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior-y: auto;
  touch-action: pan-y;
  -webkit-overflow-scrolling: touch;
  background: #08384a;
}

.assessment-page__container {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 1120px;
  overflow: visible;
}

.assessment-stage {
  width: 100%;
  max-width: 900px;
  margin: 0 auto;
  overflow: visible;
}

.assessment-hero {
  display: grid;
  justify-items: center;
  gap: 14px;
  margin-bottom: 14px;
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
  max-width: 100%;
  overflow-wrap: anywhere;
  text-align: center;
}

.assessment-hero__summary {
  margin: 0 auto 18px;
  max-width: 720px;
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.96rem;
  font-weight: 600;
  line-height: 1.9;
  text-align: center;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.assessment-shell {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  border: 1px solid rgba(205, 228, 235, 0.42);
  border-radius: 36px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(244, 250, 252, 0.95) 100%);
  box-shadow: 0 34px 84px rgba(3, 27, 36, 0.28);
  box-sizing: border-box;
  overflow-x: hidden;
  overflow-y: visible;
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
  border-radius: 24px;
  padding: 18px 20px;
}

.assessment-error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 0;
}

.assessment-error-state .assessment-alert {
  width: 100%;
  margin-bottom: 0;
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

.assessment-empty-state,
.assessment-state {
  margin-bottom: 20px;
  border: 1px dashed rgba(15, 23, 42, 0.14);
  background: rgba(248, 250, 252, 0.78);
  color: #475569;
}

.assessment-state--success {
  border-style: solid;
  border-color: rgba(14, 165, 233, 0.18);
  background: rgba(14, 165, 233, 0.08);
  color: #075985;
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
}

.assessment-section-title--secondary {
  margin-top: 4px;
}

.task-document-layout {
  display: grid;
  gap: 18px;
  width: 100%;
  min-width: 0;
  overflow: visible;
}

.task-video-card {
  border: 1px solid rgba(166, 210, 226, 0.4);
  border-radius: 30px;
  background: linear-gradient(180deg, #f7fcff 0%, #eef7fa 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75), 0 18px 36px rgba(8, 65, 89, 0.08);
  padding: 22px;
}

.task-video-card__title {
  margin-bottom: 14px;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.task-video-card__frame {
  width: 100%;
  min-height: 340px;
  border: 0;
  border-radius: 24px;
  background: #0f172a;
}

.task-video-card video.task-video-card__frame {
  object-fit: contain;
}

.task-editor-shell {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  border: 1px solid rgba(166, 210, 226, 0.4);
  border-radius: 30px;
  background: linear-gradient(180deg, #f7fcff 0%, #eef7fa 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75), 0 18px 36px rgba(8, 65, 89, 0.08);
  padding: 18px;
  overflow-x: hidden;
  overflow-y: visible;
}

.task-template-shell {
  display: grid;
  gap: 14px;
}

.assessment-question {
  margin-bottom: 22px;
  border: 1px solid rgba(166, 210, 226, 0.4);
  border-radius: 30px;
  background: linear-gradient(180deg, #f7fcff 0%, #eef7fa 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75), 0 18px 36px rgba(8, 65, 89, 0.08);
  padding: 22px;
}

.assessment-question__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.assessment-question__title {
  color: #0f172a;
  flex: 1 1 100%;
  text-align: center;
  font-size: 1.06rem;
  font-weight: 900;
  line-height: 1.9;
}

.assessment-question__tools {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.assessment-pill-button {
  min-height: 42px;
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
  min-width: 0;
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

.assessment-option {
  min-height: 56px;
  border: 1px solid rgba(166, 210, 226, 0.5);
  border-radius: 20px;
  color: #123f56;
  font-size: 0.95rem;
  font-weight: 800;
}

.assessment-option--active {
  border-color: transparent;
  background: var(--app-primary-gradient, linear-gradient(135deg, #2a94b2 0%, #11657e 100%));
  box-shadow: 0 18px 30px rgba(17, 101, 126, 0.28);
}

.assessment-textarea :deep(textarea) {
  line-height: 1.9;
}

.assessment-question__file-name {
  margin-top: 12px;
  color: #64748b;
  font-size: 0.84rem;
  font-weight: 700;
}

.assessment-submit-row {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}

.assessment-submit-button {
  min-width: 170px;
  min-height: 54px;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 900;
  box-shadow: 0 18px 30px rgba(17, 101, 126, 0.28);
}

.assessment-dialog__body {
  margin-top: 18px;
}

.assessment-dialog__body--stack {
  display: grid;
  gap: 14px;
}

.assessment-dialog__actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 18px;
}

.assessment-dialog__image,
.assessment-dialog__frame,
.assessment-dialog__video {
  width: 100%;
  min-height: 420px;
  border: 0;
  border-radius: 22px;
  background: #0f172a;
  object-fit: contain;
}

@media (max-width: 960px) {
  .assessment-question__header {
    flex-direction: column;
  }

  .assessment-question__tools {
    justify-content: center;
  }

  .assessment-options-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .assessment-submit-row {
    justify-content: stretch;
  }

  .assessment-submit-button {
    width: 100%;
  }
}

@media (max-width: 600px) {
  .assessment-page__container,
  .assessment-stage,
  .assessment-shell,
  .assessment-hero,
  .assessment-hero__summary,
  .task-document-layout,
  .task-editor-shell {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
  }

  .assessment-hero__badge {
    width: 100%;
    padding-inline: 18px;
    font-size: 1rem;
    box-sizing: border-box;
  }

  .assessment-hero__summary {
    padding-inline: 6px;
    font-size: 0.9rem;
  }

  .assessment-question,
  .task-editor-shell,
  .assessment-shell {
    border-radius: 24px;
  }

  .assessment-question,
  .task-editor-shell {
    padding: 16px;
  }

  .task-video-card {
    padding: 16px;
    border-radius: 24px;
  }

  .task-video-card__frame {
    min-height: 220px;
    border-radius: 18px;
  }
}

@media (max-width: 420px) {
  .assessment-page__container {
    padding-left: 6px !important;
    padding-right: 6px !important;
  }

  .task-editor-shell {
    padding: 10px;
  }

  .assessment-shell {
    padding-left: 8px !important;
    padding-right: 8px !important;
  }
}
</style>
