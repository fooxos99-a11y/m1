<template>
  <div
    class="satisfaction-admin"
    :class="{ 'satisfaction-admin--embedded': embedded }"
  >
    <v-container class="satisfaction-admin__container py-8 py-md-10">
      <div
        v-if="dashboardError"
        class="satisfaction-admin__alert satisfaction-admin__alert--error"
      >
        {{ dashboardError }}
      </div>

      <section class="satisfaction-admin__card">
        <div class="satisfaction-admin__toolbar">
          <div class="prep-field satisfaction-admin__filter-field">
            <label class="prep-field__label">الدورة</label>
            <AppSelect
              v-model="courseSelectValue"
              :items="courseOptions"
              item-text="label"
              item-value="value"
              placeholder="اختر الدورة"
              persistent-placeholder
              hide-details
              dense
              outlined
              class="prep-select satisfaction-admin__select"
            />
          </div>
        </div>

        <div
          v-if="!selectedCourse"
          class="satisfaction-admin__empty"
        >
          {{ hasAvailableCourses ? 'اختر دورة لعرض مؤشرات الاستبيان.' : 'لا توجد دورات تحتوي على اختبار بعدي حاليًا.' }}
        </div>
        <template v-else>
          <section class="satisfaction-admin__metrics">
            <div
              v-if="!questions.length"
              class="satisfaction-admin__empty"
            >
              لا توجد أسئلة رضا مضافة لهذه الدورة بعد.
            </div>
            <div
              v-else
              class="satisfaction-admin__metrics-grid"
            >
              <article
                v-for="indicatorCard in indicatorCards"
                :key="indicatorCard.id"
                class="satisfaction-admin__metric-card assessment-score-indicator"
              >
                <AppIconButton
                  variant="plain"
                  class="satisfaction-admin__delete-indicator"
                  :disabled="deletingQuestionKey === indicatorCard.questionKey"
                  @click="deleteQuestionFromIndicator(indicatorCard)"
                >
                  <v-icon small>
                    mdi-close
                  </v-icon>
                </AppIconButton>
                <div
                  class="assessment-score-indicator__ring"
                  :style="indicatorRingStyle(indicatorCard.progress)"
                >
                  <div class="assessment-score-indicator__ring-core">
                    {{ animatedIndicatorDisplay(indicatorCard.display) }}
                  </div>
                </div>
                <div class="assessment-score-indicator__text">
                  <div class="assessment-score-indicator__subtitle satisfaction-admin__metric-title">
                    {{ indicatorCard.prompt }}
                  </div>
                  <div class="assessment-score-indicator__sublabel">
                    {{ indicatorCard.meta }}
                  </div>
                </div>
              </article>
            </div>
          </section>
        </template>
      </section>

      <AppDialog
        v-model="addDialogOpen"
        max-width="640"
        @close="closeAddDialog"
      >
        <v-card class="satisfaction-admin__dialog pa-5">
          <AppDialogHeader
            class="satisfaction-admin__dialog-header"
            title="إضافة استبيان جديد"
          />
          <div class="satisfaction-admin__dialog-note">
            اختر ما إذا كان السؤال سيُضاف لكل الدورات البعدية أو لدورة محددة فقط.
          </div>

          <AppTextField
            v-model.trim="questionDraft.prompt"
            label="نص السؤال"
            dense
            outlined
            class="satisfaction-admin__field"
          />

          <AppSelect
            v-model="questionDraft.type"
            :items="questionTypeOptions"
            item-text="label"
            item-value="value"
            label="نوع السؤال"
            dense
            outlined
            class="satisfaction-admin__field"
          />

          <AppSelect
            v-model="questionTargetScopeValue"
            :items="questionTargetOptions"
            item-text="label"
            item-value="value"
            label="الدورة"
            placeholder="اختر الدورة"
            persistent-placeholder
            dense
            outlined
            class="satisfaction-admin__field"
          />

          <AppSelect
            v-if="questionDraft.targetScope === 'course'"
            v-model="targetCourseSelectValue"
            :items="targetCourseOptions"
            item-text="label"
            item-value="value"
            label="اختر الدورة"
            placeholder="اختر الدورة"
            persistent-placeholder
            dense
            outlined
            class="satisfaction-admin__field"
          />

          <v-switch
            v-model="questionDraft.isRequired"
            inset
            hide-details
            label="سؤال إلزامي"
            class="satisfaction-admin__switch"
          />

          <AppDialogFooter class="satisfaction-admin__dialog-actions">
            <AppButton
              variant="secondary"
              @click="closeAddDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="submitting"
              @click="submitQuestion"
            >
              إضافة
            </AppButton>
          </AppDialogFooter>
        </v-card>
      </AppDialog>
    </v-container>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import {
  AppButton, AppDialog, AppDialogFooter, AppDialogHeader, AppIconButton, AppSelect, AppTextField,
} from '../components/ui';
import indicatorAnimation from '../mixins/indicatorAnimation';

export default {
  name: 'AdminSatisfactionView',
  components: {
    AppDialog,
    AppButton,
    AppSelect,
    AppDialogHeader,
    AppDialogFooter,
    AppIconButton,
    AppTextField,
  },
  mixins: [indicatorAnimation],
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      selectedCourseId: '',
      addDialogOpen: false,
      submitting: false,
      deletingQuestionKey: '',
      questionDraft: {
        prompt: '',
        type: 'rating',
        targetScope: '',
        targetCourseId: '',
        isRequired: true,
      },
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'dashboardError']),
    availableCourseOptions() {
      return [...((this.dashboardSnapshot?.courses || []).filter((course) => course.entityType !== 'task' && course.isPostEnabled))]
        .sort((left, right) => (left.sortOrder || 0) - (right.sortOrder || 0))
        .map((course) => ({
          label: course.title,
          value: course.id,
        }));
    },
    courseOptions() {
      return this.availableCourseOptions;
    },
    courseSelectValue: {
      get() {
        return this.selectedCourseId || null;
      },
      set(value) {
        this.selectedCourseId = value || '';
      },
    },
    hasAvailableCourses() {
      return this.availableCourseOptions.length > 0;
    },
    selectedCourse() {
      return (this.dashboardSnapshot?.courses || []).find((course) => course.id === this.selectedCourseId) || null;
    },
    questions() {
      if (!this.selectedCourse) {
        return [];
      }

      return [...(this.dashboardSnapshot?.satisfactionQuestions || [])]
        .filter((question) => question.courseId === this.selectedCourse.id)
        .sort((left, right) => left.sortOrder - right.sortOrder);
    },
    questionOptions() {
      const seen = new Set();

      return (this.dashboardSnapshot?.satisfactionQuestions || [])
        .filter((question) => {
          const key = this.getQuestionKey(question);

          if (seen.has(key)) {
            return false;
          }

          seen.add(key);
          return true;
        })
        .map((question) => ({
          label: `${question.prompt} - ${question.type === 'rating' ? 'تقييم' : 'نصي'}`,
          value: this.getQuestionKey(question),
        }));
    },
    responses() {
      if (!this.selectedCourse) {
        return [];
      }

      return (this.dashboardSnapshot?.satisfactionResponses || []).filter((response) => response.courseId === this.selectedCourse.id);
    },
    indicatorCards() {
      return this.questions
        .map((question) => {
          const questionResponses = this.responses.filter((response) => response.questionId === question.id);
          const values = questionResponses
            .filter((response) => response.ratingValue !== null && response.ratingValue !== undefined)
            .map((response) => Number(response.ratingValue))
            .filter((value) => Number.isFinite(value));
          const textCount = questionResponses.filter((response) => String(response.textValue || '').trim()).length;
          const average = values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : null;
          const isRating = question.type === 'rating';

          return {
            id: question.id,
            questionKey: this.getQuestionKey(question),
            prompt: question.prompt,
            meta: `${isRating ? values.length : textCount} طالب`,
            progress: isRating ? (average === null ? 0 : average * 10) : (textCount > 0 ? 100 : 0),
            display: isRating ? (average === null ? '--' : average.toFixed(1)) : String(textCount),
          };
        });
    },
    indicatorAnimationSignature() {
      return this.indicatorCards
        .map((indicator) => `${indicator.id}:${indicator.progress}:${indicator.display}`)
        .join('|');
    },
    questionTypeOptions() {
      return [
        { label: 'تقييم من 1 إلى 10', value: 'rating' },
        { label: 'إجابة نصية', value: 'text' },
      ];
    },
    questionTargetOptions() {
      return [
        { label: 'جميع الدورات', value: 'all' },
        { label: 'دورة محددة', value: 'course' },
      ];
    },
    questionTargetScopeValue: {
      get() {
        return this.questionDraft.targetScope || null;
      },
      set(value) {
        this.questionDraft.targetScope = value || '';
      },
    },
    targetCourseOptions() {
      return this.availableCourseOptions;
    },
    targetCourseSelectValue: {
      get() {
        return this.questionDraft.targetCourseId || null;
      },
      set(value) {
        this.questionDraft.targetCourseId = value || '';
      },
    },
  },
  watch: {
    dashboardSnapshot: {
      immediate: true,
      handler() {
        this.ensureSelection();
      },
    },
    indicatorAnimationSignature: {
      immediate: true,
      handler() {
        this.$nextTick(() => {
          this.restartIndicatorAnimation();
        });
      },
    },
  },
  created() {
    this.ensureSelection();
  },
  methods: {
    ...mapActions(['addSatisfactionQuestion', 'deleteSatisfactionQuestion']),
    indicatorRingStyle(progress) {
      const normalized = this.animatedIndicatorPercent(progress);
      return {
        background: `conic-gradient(#156c82 0 ${normalized}%, #e9f2f5 ${normalized}% 100%)`,
      };
    },
    getQuestionKey(question) {
      return `${question.prompt}::${question.type}`;
    },
    ensureSelection() {
      if (this.selectedCourseId && this.availableCourseOptions.some((course) => course.value === this.selectedCourseId)) {
        return;
      }

      this.selectedCourseId = '';
    },
    openAddDialog() {
      this.questionDraft = {
        prompt: '',
        type: 'rating',
        targetScope: '',
        targetCourseId: this.selectedCourseId || '',
        isRequired: true,
      };
      this.addDialogOpen = true;
    },
    closeAddDialog() {
      this.addDialogOpen = false;
      this.submitting = false;
    },
    async submitQuestion() {
      if (!this.questionDraft.prompt) {
        this.$toast.error('أدخل نص السؤال أولًا');
        return;
      }

      if (!this.questionDraft.targetScope) {
        this.$toast.error('اختر نطاق إضافة السؤال أولًا');
        return;
      }

      if (this.questionDraft.targetScope === 'course' && !this.questionDraft.targetCourseId) {
        this.$toast.error('اختر الدورة التي تريد إضافة السؤال لها');
        return;
      }

      this.submitting = true;

      try {
        await this.addSatisfactionQuestion({
          prompt: this.questionDraft.prompt,
          type: this.questionDraft.type,
          targetScope: this.questionDraft.targetScope,
          courseId: this.questionDraft.targetScope === 'course' ? this.questionDraft.targetCourseId : null,
          isRequired: this.questionDraft.isRequired,
        });

        if (this.questionDraft.targetScope === 'course' && this.questionDraft.targetCourseId) {
          this.selectedCourseId = this.questionDraft.targetCourseId;
        }

        this.$toast.success('تمت إضافة سؤال الاستبيان');
        this.closeAddDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر إضافة سؤال الاستبيان');
      } finally {
        this.submitting = false;
      }
    },
    async deleteQuestionFromIndicator(indicator) {
      if (!indicator?.id) {
        this.$toast.error('اختر سؤالًا صالحًا للحذف');
        return;
      }

      this.deletingQuestionKey = indicator.questionKey;

      try {
        await this.deleteSatisfactionQuestion(indicator.id);
        this.$toast.success('تم حذف سؤال الاستبيان');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حذف سؤال الاستبيان');
      } finally {
        this.deletingQuestionKey = '';
      }
    },
  },
};
</script>

<style scoped>
.satisfaction-admin {
  min-height: 100%;
  background: linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
}

.satisfaction-admin--embedded {
  background: transparent;
}

.satisfaction-admin--embedded .satisfaction-admin__container {
  max-width: none;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.satisfaction-admin__container {
  max-width: 1200px;
}

.satisfaction-admin__card {
  border: 1px solid rgba(255, 255, 255, 0.8);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  padding: 24px;
}

.satisfaction-admin--embedded .satisfaction-admin__card {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  backdrop-filter: blur(10px);
  padding: 28px;
}

.satisfaction-admin__toolbar {
  display: grid;
  grid-template-columns: minmax(260px, 360px);
  gap: 16px;
  margin-bottom: 22px;
}

.satisfaction-admin__select {
  width: 100%;
}

.satisfaction-admin__metrics {
  margin-top: 0;
}

.satisfaction-admin__metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 18px;
}

.satisfaction-admin__metric-title {
  line-height: 1.8;
}

.satisfaction-admin__metric-card {
  position: relative;
  padding: 24px 20px 20px;
  border: 1px solid rgba(214, 229, 238, 0.9);
  border-radius: 24px;
  background: #fff;
  box-shadow: 0 12px 34px rgba(15, 23, 42, 0.04);
}

.satisfaction-admin__delete-indicator {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #c08484;
  transition: transform 0.18s ease, color 0.18s ease;
}

.satisfaction-admin__delete-indicator:hover:not(:disabled) {
  transform: translateY(-1px);
  color: #b45353;
}

.satisfaction-admin__delete-indicator:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.satisfaction-admin__delete-indicator :deep(.v-icon) {
  font-size: 0.95rem !important;
}

.satisfaction-admin__empty,
.satisfaction-admin__alert {
  border-radius: 22px;
  padding: 18px 20px;
  font-weight: 700;
}

.satisfaction-admin__empty {
  border: 1px dashed rgba(15, 23, 42, 0.14);
  background: rgba(248, 250, 252, 0.72);
  color: #475569;
}

.satisfaction-admin__alert--error {
  margin-bottom: 18px;
  border: 1px solid rgba(220, 38, 38, 0.18);
  background: rgba(220, 38, 38, 0.06);
  color: #b91c1c;
}

.satisfaction-admin__dialog {
  border-radius: 28px;
}

.satisfaction-admin__dialog-note {
  margin: 12px 0 16px;
  color: #64748b;
  font-size: 0.92rem;
  font-weight: 600;
  line-height: 1.8;
}

.satisfaction-admin__field {
  margin-bottom: 12px;
}

.satisfaction-admin__switch {
  margin-top: -6px;
}

.satisfaction-admin__dialog {
  border-radius: 28px !important;
}

.satisfaction-admin__dialog-header {
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  gap: 16px;
  margin-bottom: 20px;
}

.satisfaction-admin__dialog-header :deep(.app-dialog-header__eyebrow) {
  color: #6b7e8f;
  font-size: 0.82rem;
  font-weight: 800;
}

.satisfaction-admin__dialog-header :deep(.app-dialog-header__title) {
  margin: 6px 0 0;
  color: #0f172a;
  font-size: 1.3rem;
  font-weight: 900;
}

.satisfaction-admin__field {
  margin-bottom: 16px;
}

.satisfaction-admin__dialog-actions {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  margin-top: 20px;
}

.prep-field {
  min-width: 0;
}

.prep-field__label {
  display: block;
  margin: 0 0 10px;
  color: #0f3554;
  font-size: 1rem;
  font-weight: 700;
}

.prep-select ::v-deep .v-input__slot {
  min-height: 44px !important;
  border-radius: 18px !important;
}

.assessment-score-indicator {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
}

.assessment-score-indicator__ring {
  position: relative;
  width: 174px;
  height: 174px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  box-shadow: 0 0 50px rgba(22, 108, 128, 0.15);
}

.assessment-score-indicator__ring-core {
  position: absolute;
  top: 15px;
  left: 15px;
  right: 15px;
  bottom: 15px;
  background: #fff;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #114258;
  font-size: 2.4rem;
  font-weight: 900;
}

.assessment-score-indicator__text {
  text-align: center;
  color: #114258;
}

.assessment-score-indicator__subtitle {
  font-weight: 900;
  font-size: 1.15rem;
  margin-bottom: 8px;
}

.assessment-score-indicator__sublabel {
  color: #6a828e;
  font-weight: 600;
  font-size: 0.95rem;
}

@media (max-width: 960px) {
  .assessment-score-indicator {
    flex-direction: column;
    align-items: stretch;
  }

  .satisfaction-admin__toolbar {
    grid-template-columns: minmax(0, 1fr);
  }

  .satisfaction-admin__metrics-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
