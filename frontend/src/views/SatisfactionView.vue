<template>
  <div class="assessment-page">
    <v-container class="assessment-page__container py-6 py-md-10">
      <v-card
        class="assessment-shell pa-4 pa-sm-6 pa-md-8"
        elevation="0"
      >
        <div class="assessment-shell__header">
          <div>
            <div class="assessment-shell__eyebrow">
              استبيان الرضا
            </div>
            <h1 class="assessment-shell__title">
              {{ activeCourse ? activeCourse.title : 'استبيان الرضا' }}
            </h1>
          </div>
          <div class="assessment-shell__actions">
            <v-chip
              small
              color="primary"
              text-color="white"
              class="assessment-shell__chip"
            >
              استبيان الرضا
            </v-chip>
            <AppButton
              variant="plain"
              :to="{ name: 'home' }"
            >
              الرئيسية
            </AppButton>
          </div>
        </div>

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
          v-else-if="!currentUser"
          class="assessment-alert assessment-alert--error"
        >
          سجّل الدخول أولًا للوصول إلى هذه الصفحة.
        </div>
        <div
          v-else-if="!student"
          class="assessment-alert assessment-alert--error"
        >
          لم يتم العثور على بيانات الطالب المرتبطة بالحساب الحالي.
        </div>
        <div
          v-else-if="!activeCourse"
          class="assessment-empty-state"
        >
          لا توجد دورة مفعلة حاليًا.
        </div>
        <div
          v-else-if="!hasPostSubmission"
          class="assessment-alert assessment-alert--warning"
        >
          أرسل الاختبار البعدي أولًا ثم أكمل استبيان الرضا.
        </div>
        <div
          v-else-if="satisfactionQuestions.length === 0"
          class="assessment-empty-state"
        >
          لا توجد أسئلة رضا مضافة لهذه الدورة.
        </div>
        <div
          v-else-if="alreadySubmittedSatisfaction"
          class="assessment-alert assessment-alert--success"
        >
          شكرًا، تم استلام استبيان الرضا.
        </div>
        <template v-else>
          <div
            v-if="pageError"
            class="assessment-alert assessment-alert--error"
          >
            {{ pageError }}
          </div>

          <div class="assessment-section-title">
            أجب على الأسئلة التالية:
          </div>

          <article
            v-for="(question, index) in satisfactionQuestions"
            :key="question.id"
            class="assessment-question"
          >
            <div class="assessment-question__title">
              {{ index + 1 }}. {{ question.prompt }}
              <span
                v-if="question.isRequired"
                class="assessment-required"
              >*</span>
            </div>

            <div
              v-if="question.type === 'rating'"
              class="assessment-rating-bar"
            >
              <div class="assessment-rating-bar__labels">
                <span>1</span>
                <span class="assessment-rating-bar__value">
                  {{ answers[question.id]?.ratingValue ?? 'غير محدد' }}
                </span>
                <span>10</span>
              </div>
              <v-slider
                :value="answers[question.id]?.ratingValue ?? 1"
                min="1"
                max="10"
                step="1"
                ticks="always"
                tick-size="3"
                hide-details
                class="assessment-rating-bar__slider"
                :class="{ 'assessment-rating-bar__slider--unanswered': answers[question.id]?.ratingValue == null }"
                @input="setRating(question.id, $event)"
              />
            </div>

            <v-textarea
              v-else
              :value="answers[question.id]?.textValue || ''"
              outlined
              rows="4"
              hide-details
              class="assessment-textarea"
              placeholder="اكتب رأيك هنا"
              @input="setText(question.id, $event)"
            />
          </article>

          <div class="assessment-submit-row">
            <AppButton
              variant="primary"
              class="assessment-submit-button"
              :loading="submitting"
              :disabled="submitting"
              @click="handleSubmit"
            >
              إرسال الاستبيان
            </AppButton>
          </div>
        </template>
      </v-card>
    </v-container>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import { AppButton } from '../components/ui';

export default {
  name: 'SatisfactionView',
  components: {
    AppButton,
  },
  data() {
    return {
      answers: {},
      pageError: '',
      submitting: false,
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'dashboardLoading', 'dashboardError', 'currentUser']),
    student() {
      const students = this.dashboardSnapshot?.students || [];
      const loginCode = this.currentUser?.loginCode || '';

      return students.find((student) => student.loginId === loginCode) || null;
    },
    activeCourse() {
      const courses = (this.dashboardSnapshot?.courses || []).filter((course) => course.entityType !== 'task');

      return courses.find((course) => course.isActive) || null;
    },
    postSubmission() {
      if (!this.activeCourse || !this.student) {
        return null;
      }

      return [...(this.dashboardSnapshot?.submissions || [])]
        .filter((submission) => (
          submission.courseId === this.activeCourse.id
          && submission.assessmentType === 'post'
          && submission.loginId === this.student.loginId
        ))
        .sort((left, right) => new Date(right.submittedAt).getTime() - new Date(left.submittedAt).getTime())[0] || null;
    },
    hasPostSubmission() {
      return Boolean(this.postSubmission);
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
  },
  created() {
    if (!this.dashboardSnapshot) {
      this.loadDashboardSnapshot();
    }
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot', 'submitSatisfactionResponses']),
    setRating(questionId, value) {
      this.$set(this.answers, questionId, {
        ratingValue: value,
        textValue: '',
      });
      this.pageError = '';
    },
    setText(questionId, value) {
      this.$set(this.answers, questionId, {
        ratingValue: null,
        textValue: value,
      });
      this.pageError = '';
    },
    validateAnswers() {
      for (const question of this.satisfactionQuestions) {
        if (!question.isRequired) {
          continue;
        }

        if (question.type === 'rating' && this.answers[question.id]?.ratingValue == null) {
          this.pageError = 'أجب على جميع أسئلة الرضا الإلزامية.';
          return false;
        }

        if (question.type === 'text' && !(this.answers[question.id]?.textValue || '').trim()) {
          this.pageError = 'أجب على جميع أسئلة الرضا الإلزامية.';
          return false;
        }
      }

      this.pageError = '';
      return true;
    },
    async handleSubmit() {
      if (!this.activeCourse || !this.student || !this.hasPostSubmission) {
        this.pageError = 'يجب إرسال الاختبار البعدي أولًا.';
        return;
      }

      if (!this.validateAnswers()) {
        return;
      }

      this.submitting = true;

      try {
        await this.submitSatisfactionResponses(this.satisfactionQuestions.map((question) => ({
          courseId: this.activeCourse.id,
          questionId: question.id,
          loginCode: this.student.loginId,
          studentName: this.student.name,
          ratingValue: question.type === 'rating' ? (this.answers[question.id]?.ratingValue ?? null) : null,
          textValue: question.type === 'text' ? (this.answers[question.id]?.textValue || '') : '',
        })));
        this.pageError = '';
        this.answers = {};
      } catch (error) {
        this.pageError = error?.response?.data?.message || error?.message || 'تعذر إرسال الاستبيان.';
      } finally {
        this.submitting = false;
      }
    },
  },
};
</script>

<style scoped>
.assessment-page {
  min-height: 100vh;
  background:
    radial-gradient(circle at top, rgba(16, 118, 153, 0.12), transparent 36%),
    linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
}

.assessment-page__container {
  max-width: 1080px;
}

.assessment-shell {
  border: 1px solid rgba(255, 255, 255, 0.86);
  border-radius: 32px;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 24px 70px rgba(8, 65, 89, 0.12);
}

.assessment-shell__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
}

.assessment-shell__eyebrow {
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 800;
}

.assessment-shell__title {
  margin: 8px 0 0;
  color: #0f172a;
  font-size: 1.8rem;
  font-weight: 900;
}

.assessment-shell__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.assessment-shell__chip {
  font-weight: 800;
}

.assessment-alert,
.assessment-empty-state {
  margin-bottom: 18px;
  border-radius: 24px;
  padding: 18px 20px;
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

.assessment-empty-state {
  border: 1px dashed rgba(15, 23, 42, 0.14);
  background: rgba(248, 250, 252, 0.72);
  color: #475569;
}

.assessment-section-title {
  margin-bottom: 16px;
  color: #0f172a;
  font-size: 1.08rem;
  font-weight: 900;
}

.assessment-question {
  margin-bottom: 18px;
  padding: 20px;
  border: 1px solid rgba(16, 118, 153, 0.1);
  border-radius: 28px;
  background: #f6fbfd;
  box-shadow: 0 12px 30px rgba(8, 65, 89, 0.06);
}

.assessment-question__title {
  margin-bottom: 16px;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
  line-height: 1.9;
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

.assessment-textarea :deep(.v-input__slot) {
  border-radius: 22px !important;
  background: rgba(255, 255, 255, 0.96) !important;
}

.assessment-submit-row {
  display: flex;
  justify-content: flex-start;
  padding-top: 8px;
}

.assessment-submit-button {
  min-width: 170px;
  min-height: 54px !important;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 900;
  box-shadow: 0 12px 24px rgba(16, 118, 153, 0.24);
}

@media (max-width: 960px) {
  .assessment-shell__header {
    flex-direction: column;
  }

  .assessment-shell__actions {
    width: 100%;
    justify-content: flex-start;
  }
}

@media (max-width: 600px) {
  .assessment-shell {
    border-radius: 24px;
  }

  .assessment-shell__title {
    font-size: 1.45rem;
  }

  .assessment-question {
    padding: 16px;
    border-radius: 22px;
  }
}
</style>
