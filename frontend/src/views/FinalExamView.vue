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
            الاختبار النهائي
          </div>
          <div class="assessment-hero__badge">
            الاختبار النهائي
          </div>
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
            class="assessment-alert assessment-alert--error"
          >
            {{ publicError }}
          </div>
          <div
            v-else-if="!studentResolved"
            class="assessment-empty-state"
          >
            جارٍ التحقق من بيانات الطالب...
          </div>
          <template v-else>
            <div
              v-if="pageError"
              class="assessment-alert assessment-alert--error"
            >
              {{ pageError }}
            </div>

            <div
              v-if="student && !isEnabled"
              class="assessment-empty-state"
            >
              لا توجد بيانات لهذا الاختبار حاليًا.
            </div>

            <div
              v-if="student && existingSubmission"
              class="assessment-state assessment-state--success"
            >
              <div class="assessment-state__title">
                تم الإرسال
              </div>
            </div>

            <div
              v-if="student && isEnabled && !existingSubmission && questions.length === 0"
              class="assessment-empty-state"
            >
              لا توجد أسئلة مضافة لهذا الفرع بعد.
            </div>

            <template v-if="canInteract && questions.length">
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
                      :for="`final-question-file-${question.id}`"
                      class="assessment-pill-button"
                    >
                      إرفاق ملف
                    </label>
                    <input
                      v-if="question.allowFile"
                      :id="`final-question-file-${question.id}`"
                      type="file"
                      class="assessment-file-input"
                      @change="handleFileSelect(question.id, $event)"
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
                  v-if="question.type === 'multiple' || question.type === 'truefalse'"
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
              v-if="canInteract && questions.length"
              class="assessment-submit-row"
            >
              <v-btn
                color="primary"
                depressed
                class="assessment-submit-button"
                :loading="submitting"
                :disabled="submitting || !isEnabled"
                @click="handleSubmit"
              >
                إرسال
              </v-btn>
            </div>

            <div
              v-if="existingSubmission && questions.length"
              class="assessment-review"
            >
              <div class="assessment-section-title">
                مراجعة الإجابات
              </div>

              <article
                v-for="(question, index) in questions"
                :key="`${question.id}-review`"
                class="assessment-question"
              >
                <div class="assessment-question__title">
                  {{ index + 1 }}. {{ question.prompt }}
                </div>
                <div class="assessment-review__answer">
                  {{ submittedAnswer(question.id) || '—' }}
                </div>
                <div
                  v-if="question.correctAnswer"
                  class="assessment-review__meta"
                  :class="reviewAnswerClass(question)"
                >
                  {{ reviewAnswerLabel(question) }}
                </div>
              </article>
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
  submitPublicFinalExam,
} from '@/services/api';

const MAX_ATTACHMENT_SIZE = 5 * 1024 * 1024;

const normalizeAnswer = (value) => String(value || '').trim().toLowerCase();

export default {
  name: 'FinalExamView',
  components: {
    AppDialog,
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
      answers: {},
      files: {},
      pageError: '',
      submitting: false,
      previewDialogOpen: false,
      previewAttachment: null,
      resetKey: 0,
      currentTimestamp: Date.now(),
      clockIntervalId: null,
      publicLoadingGuardTimer: null,
      publicAutoRefreshTimer: null,
      publicRefreshInFlight: false,
    };
  },
  computed: {
    currentUser() {
      return this.$store?.state?.currentUser || null;
    },
    authenticatedStudentLogin() {
      return ['student', 'trainee'].includes(this.currentUser?.role)
        ? String(this.currentUser.loginCode || '').trim()
        : '';
    },
    student() {
      return (this.publicSnapshot?.students || []).find((student) => student.loginId === this.studentLoginId) || null;
    },
    branchCode() {
      return this.student?.branchId || 'male';
    },
    branchSetting() {
      return this.publicSnapshot?.finalExamSettings?.[this.branchCode] || { isEnabled: false, closesAt: null };
    },
    questions() {
      return [...(this.publicSnapshot?.finalExamQuestions || [])]
        .filter((question) => question.branchCode === this.branchCode)
        .sort((left, right) => (left.sortOrder || 0) - (right.sortOrder || 0));
    },
    isEnabled() {
      if (!this.student || !this.branchSetting?.isEnabled) {
        return false;
      }

      if (!this.branchSetting?.closesAt) {
        return true;
      }

      const closesAt = new Date(this.branchSetting.closesAt).getTime();

      return Number.isFinite(closesAt) && closesAt > this.currentTimestamp;
    },
    existingSubmission() {
      if (!this.student) {
        return null;
      }

      return (this.publicSnapshot?.finalExamSubmissions || []).find((submission) => submission.loginCode === this.student.loginId) || null;
    },
    canInteract() {
      return Boolean(this.student && this.isEnabled && !this.existingSubmission);
    },
    gradedSubmission() {
      const total = this.questions.reduce((sum, question) => sum + Number(question.points || 0), 0);

      if (!this.existingSubmission) {
        return { score: 0, total };
      }

      if (typeof this.existingSubmission.manualScore === 'number' && Number.isFinite(this.existingSubmission.manualScore)) {
        return {
          score: this.existingSubmission.manualScore,
          total: Math.max(total, this.existingSubmission.manualScore),
        };
      }

      const answersByQuestion = new Map((this.existingSubmission.answers || []).map((answer) => [answer.questionId, answer.value]));
      const score = this.questions.reduce((sum, question) => {
        if (!question.correctAnswer) {
          return sum;
        }

        return this.isAnswerCorrect(question.correctAnswer, answersByQuestion.get(question.id) || '')
          ? sum + Number(question.points || 0)
          : sum;
      }, 0);

      return { score, total };
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
    this.clockIntervalId = window.setInterval(() => {
      this.currentTimestamp = Date.now();
    }, 30000);

    this.loadPublicData();
    this.publicAutoRefreshTimer = window.setInterval(() => {
      this.refreshPublicSnapshotSilently();
    }, 2000);
  },
  beforeDestroy() {
    if (this.clockIntervalId) {
      window.clearInterval(this.clockIntervalId);
      this.clockIntervalId = null;
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
          this.publicError = error?.response?.data?.message || error?.message || 'تعذر تحميل بيانات الاختبار النهائي.';
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

        if (this.isEnabled || this.existingSubmission) {
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

        if (this.isEnabled || this.existingSubmission) {
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
    openAttachmentPreview(attachment) {
      this.previewAttachment = attachment;
      this.previewDialogOpen = true;
    },
    closePreview() {
      this.previewDialogOpen = false;
      this.previewAttachment = null;
    },
    isAnswerCorrect(correctAnswer, answer) {
      return Boolean(String(correctAnswer || '').trim()) && normalizeAnswer(answer) === normalizeAnswer(correctAnswer);
    },
    submittedAnswer(questionId) {
      return (this.existingSubmission?.answers || []).find((answer) => answer.questionId === questionId)?.value || '';
    },
    reviewAnswerClass(question) {
      const answer = this.submittedAnswer(question.id);

      if (!question.correctAnswer) {
        return 'assessment-review__meta--neutral';
      }

      return this.isAnswerCorrect(question.correctAnswer, answer)
        ? 'assessment-review__meta--success'
        : 'assessment-review__meta--danger';
    },
    reviewAnswerLabel(question) {
      const answer = this.submittedAnswer(question.id);

      if (!question.correctAnswer) {
        return 'تم استلام الإجابة.';
      }

      if (this.isAnswerCorrect(question.correctAnswer, answer)) {
        return 'إجابة صحيحة';
      }

      return `الإجابة الصحيحة: ${question.correctAnswer}`;
    },
    async handleFileSelect(questionId, event) {
      const file = event?.target?.files?.[0];

      if (!file) {
        return;
      }

      if (file.size > MAX_ATTACHMENT_SIZE) {
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
    async handleSubmit() {
      if (!this.student || !this.isEnabled) {
        this.pageError = 'الاختبار النهائي غير متاح حاليًا.';
        return;
      }

      if (this.existingSubmission) {
        this.pageError = 'تم إرسال الاختبار النهائي مسبقًا.';
        return;
      }

      for (const question of this.questions) {
        if (!(this.answers[question.id] || '').trim()) {
          this.pageError = 'الرجاء إكمال جميع الأسئلة';
          return;
        }
      }

      this.submitting = true;

      try {
        await submitPublicFinalExam({
          branchCode: this.branchCode,
          studentName: this.student.name,
          loginCode: this.student.loginId,
          answers: this.questions.map((question) => ({
            questionId: question.id,
            value: this.answers[question.id] || '',
            fileName: this.files[question.id]?.name || null,
            fileType: this.files[question.id]?.type || null,
            fileDataUrl: this.files[question.id]?.dataUrl || null,
          })),
        });

        this.pageError = '';
        this.answers = {};
        this.files = {};
        this.resetKey += 1;
        this.publicSnapshot = await this.fetchPublicSnapshotWithTimeout();
      } catch (error) {
        this.pageError = error?.response?.data?.message || error?.message || 'تعذر إرسال الاختبار النهائي.';
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
  filter: brightness(0) saturate(100%) invert(31%) sepia(53%) saturate(1050%) hue-rotate(154deg) brightness(91%) contrast(94%) drop-shadow(0 12px 24px rgba(3, 27, 36, 0.28));
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
  border-radius: 36px;
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
  border-radius: 24px;
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

.assessment-state__title {
  font-size: 1.05rem;
  font-weight: 900;
}

.assessment-state__text {
  margin-top: 8px;
  font-weight: 600;
}

.assessment-section-title {
  margin-bottom: 18px;
  color: #0f172a;
  font-size: 1.15rem;
  font-weight: 900;
}

.assessment-question {
  margin-bottom: 18px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 28px;
  background: rgba(246, 251, 253, 0.98);
  padding: 18px;
}

.assessment-question__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.assessment-question__title {
  color: #0f172a;
  font-size: 1rem;
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 6px 16px;
  border: 1px solid rgba(27, 31, 35, 0.15);
  border-radius: 6px;
  background: #107699;
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  line-height: 22px;
  box-shadow: rgba(27, 31, 35, 0.1) 0 1px 0;
  cursor: pointer;
}

.assessment-pill-button--ghost {
  border: 1px solid rgba(16, 118, 153, 0.18);
  background: #ffffff;
  color: #107699;
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
  min-height: 54px;
  border: 1px solid rgba(20, 109, 136, 0.16);
  border-radius: 20px;
  background: #fff;
  color: #0f172a;
  font-size: 0.95rem;
  font-weight: 800;
  cursor: pointer;
}

.assessment-option--active {
  border-color: rgba(20, 109, 136, 0.9);
  background: linear-gradient(180deg, #1e809b 0%, #146d88 100%);
  color: #fff;
  box-shadow: 0 14px 28px rgba(20, 109, 136, 0.22);
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
  margin-top: 10px;
}

.assessment-submit-button {
  min-width: 160px;
  height: 50px !important;
  border-radius: 18px;
  font-size: 1rem;
  font-weight: 900;
}

.assessment-review__answer {
  margin-top: 12px;
  color: #0f172a;
  font-size: 0.96rem;
  font-weight: 700;
}

.assessment-review__meta {
  margin-top: 10px;
  font-size: 0.84rem;
  font-weight: 800;
}

.assessment-review__meta--success {
  color: #047857;
}

.assessment-review__meta--danger {
  color: #b91c1c;
}

.assessment-review__meta--neutral {
  color: #64748b;
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
  .assessment-shell__header,
  .assessment-question__header {
    flex-direction: column;
  }

  .assessment-shell__logos {
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
</style>
