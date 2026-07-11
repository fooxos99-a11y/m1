<template>
  <div
    class="final-exam-page"
    :class="{ 'final-exam-page--embedded': embedded }"
  >
    <v-container class="final-exam-page__container py-8 py-md-10">
      <v-alert
        v-if="dashboardError"
        type="error"
        outlined
        class="mb-6"
      >
        {{ dashboardError }}
      </v-alert>

      <section
        v-if="showBranchCards"
        class="assessment-cards-shell"
      >
        <article
          v-for="branch in branchOptions"
          :key="branch.value"
          class="assessment-course-card"
        >
          <div class="assessment-course-card__top">
            <div class="assessment-course-card__copy">
              <h2 class="assessment-course-card__title">
                {{ branch.label }}
              </h2>
            </div>
          </div>

          <div class="assessment-course-card__buttons">
            <AppChoiceButton
              block
              class="assessment-course-card__button"
              :active="selectedBranch === branch.value"
              @click="openBranchWorkspace(branch.value)"
            >
              تعديل الأسئلة
            </AppChoiceButton>
            <AppChoiceButton
              block
              class="assessment-course-card__button"
              :active="isBranchActive(branch.value)"
              @click="toggleBranchActivation(branch.value)"
            >
              {{ isBranchActive(branch.value) ? 'إيقاف الاختبار' : 'بدء الاختبار' }}
            </AppChoiceButton>
          </div>
        </article>
      </section>

      <section
        v-if="showBranchCards"
        class="assessment-indicators-card"
      >
        <div class="assessment-indicators-card__header">
          <h2 class="assessment-indicators-card__title">
            مؤشرات الاختبار النهائي
          </h2>
        </div>

        <div class="assessment-indicators-card__controls">
          <div
            v-if="!managedBranchId"
            class="assessment-indicators-card__filter-group"
          >
            <label class="assessment-indicators-card__label">الفرع</label>
            <AppSelect
              v-model="indicatorBranch"
              :items="branchOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="assessment-select"
            />
          </div>
        </div>

        <div
          v-if="!finalExamIndicator.totalStudents"
          class="assessment-empty-state"
        >
          لا يوجد معلمون في هذا الفرع لعرض المؤشرات.
        </div>

        <div
          v-else
          class="assessment-indicators-panel assessment-indicators-panel--single"
        >
          <article class="assessment-score-indicator">
            <div
              class="assessment-score-indicator__ring"
              :style="finalExamIndicatorStyle"
            >
              <div class="assessment-score-indicator__ring-core">
                {{ animatedIndicatorDisplay(`${finalExamIndicator.percent}%`) }}
              </div>
            </div>
            <div class="assessment-score-indicator__text">
              <div class="assessment-score-indicator__subtitle">
                نسبة تقديم الاختبار النهائي
              </div>
              <div class="assessment-score-indicator__sublabel">
                {{ finalExamIndicator.submitted }} من {{ finalExamIndicator.totalStudents }} معلم
              </div>
            </div>
          </article>
        </div>
      </section>

      <section
        v-else
        class="final-exam-list-shell"
      >
        <div
          v-if="!managedBranchId"
          class="assessment-toolbar assessment-toolbar--embedded"
        >
          <div class="assessment-toolbar__field">
            <label class="assessment-toolbar__label">الفرع</label>
            <AppSelect
              v-model="selectedBranch"
              :items="branchOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="assessment-select"
            />
          </div>
        </div>

        <div
          v-if="visibleBranchQuestions.length === 0"
          class="assessment-empty-state"
        >
          لا توجد أسئلة بعد.
        </div>

        <div
          v-else
          class="assessment-inline-list"
        >
          <article
            v-for="(question, index) in visibleBranchQuestions"
            :key="question.id"
            class="assessment-form-card assessment-form-card--inline assessment-inline-list__item"
          >
            <div class="assessment-form-card__topline">
              <div class="assessment-form-card__meta">
                <AppRawButton
                  type="button"
                  class="assessment-form-card__trash"
                  aria-label="حذف السؤال"
                  :disabled="isSaving"
                  @click="removeQuestion(question.id)"
                >
                  <i
                    class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                    aria-hidden="true"
                  />
                </AppRawButton>
              </div>
            </div>

            <div class="assessment-form-card__prompt-row">
              <div class="assessment-form-card__field-group assessment-form-card__field-group--compact assessment-form-card__field-group--prompt">
                <label class="assessment-form-card__label">{{ index + 1 }}.السؤال</label>
                <input
                  :value="questionDrafts[question.id]?.prompt || ''"
                  type="text"
                  class="assessment-input"
                  placeholder="اكتب السؤال"
                  :disabled="isSaving"
                  @input="updateQuestionDraft(question.id, { prompt: $event.target.value }); clearQuestionDraftError(question.id)"
                >
              </div>

              <div class="assessment-form-card__field-group assessment-form-card__field-group--compact assessment-form-card__field-group--points-inline">
                <label class="assessment-form-card__label">الدرجة</label>
                <input
                  :value="questionDrafts[question.id]?.points || '1'"
                  type="number"
                  min="0"
                  class="assessment-input assessment-input--points"
                  placeholder="1"
                  :disabled="isSaving"
                  @input="updateQuestionDraft(question.id, { points: $event.target.value })"
                >
              </div>
            </div>

            <div
              v-if="questionDrafts[question.id]?.type === 'multiple'"
              class="assessment-form-card__field-group"
            >
              <div class="assessment-options-grid">
                <div
                  v-for="(option, optionIndex) in (questionDrafts[question.id]?.options || [])"
                  :key="`${question.id}-${optionIndex}`"
                  class="assessment-options-grid__item"
                  :class="{ 'assessment-options-grid__item--correct': isExistingCorrectOption(question.id, option) }"
                >
                  <AppRawButton
                    v-if="option.trim()"
                    type="button"
                    class="assessment-options-grid__check"
                    :class="{ 'assessment-options-grid__check--active': isExistingCorrectOption(question.id, option) }"
                    :disabled="isSaving"
                    @click="selectExistingCorrectOption(question.id, optionIndex)"
                  >
                    <v-icon small>
                      mdi-check
                    </v-icon>
                  </AppRawButton>
                  <input
                    :value="option"
                    type="text"
                    class="assessment-input"
                    :placeholder="`الخيار ${optionIndex + 1}`"
                    :disabled="isSaving"
                    @input="handleExistingOptionChange(question.id, optionIndex, $event.target.value)"
                  >
                  <AppRawButton
                    v-if="optionIndex === (questionDrafts[question.id]?.options || []).length - 1"
                    type="button"
                    class="assessment-options-grid__append"
                    :disabled="isSaving"
                    @click="handleExistingAddOptionField(question.id)"
                  >
                    <v-icon small>
                      mdi-plus
                    </v-icon>
                  </AppRawButton>
                </div>
              </div>
            </div>

            <div class="assessment-inline-list__actions">
              <p
                v-if="questionDraftErrors[question.id]"
                class="assessment-form-card__error"
              >
                {{ questionDraftErrors[question.id] }}
              </p>
            </div>
          </article>
        </div>

        <div class="assessment-inline-builder">
          <div
            v-if="questionForms.length"
            class="assessment-inline-builder__list"
          >
            <article
              v-for="(form, formIndex) in questionForms"
              :key="`final-question-form-inline-${formIndex}`"
              class="assessment-form-card assessment-form-card--inline"
            >
              <div class="assessment-form-card__topline">
                <div class="assessment-form-card__meta">
                  <AppRawButton
                    type="button"
                    class="assessment-form-card__trash"
                    aria-label="حذف السؤال"
                    @click="handleRemoveQuestionSlot(formIndex)"
                  >
                    <i
                      class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                      aria-hidden="true"
                    />
                  </AppRawButton>
                </div>
              </div>

              <div class="assessment-form-card__prompt-row">
                <div class="assessment-form-card__field-group assessment-form-card__field-group--compact assessment-form-card__field-group--prompt">
                  <label class="assessment-form-card__label">{{ visibleBranchQuestions.length + formIndex + 1 }}.السؤال</label>
                  <input
                    :value="form.prompt"
                    type="text"
                    class="assessment-input"
                    placeholder="اكتب السؤال"
                    @input="updateQuestionForm(formIndex, { prompt: $event.target.value }); clearQuestionError(formIndex)"
                    @paste="handleBulkPaste"
                  >
                </div>

                <div class="assessment-form-card__field-group assessment-form-card__field-group--compact assessment-form-card__field-group--points-inline">
                  <label class="assessment-form-card__label">الدرجة</label>
                  <input
                    :value="form.points"
                    type="number"
                    min="0"
                    class="assessment-input assessment-input--points"
                    placeholder="1"
                    @input="updateQuestionForm(formIndex, { points: $event.target.value })"
                  >
                </div>
              </div>

              <div
                v-if="form.type === 'multiple'"
                class="assessment-form-card__field-group"
              >
                <div class="assessment-options-grid">
                  <div
                    v-for="(option, optionIndex) in form.options"
                    :key="`form-${formIndex}-option-${optionIndex}`"
                    class="assessment-options-grid__item"
                    :class="{ 'assessment-options-grid__item--correct': normalizeAnswer(option) === normalizeAnswer(form.correctAnswer) && form.correctAnswerTouched }"
                  >
                    <AppRawButton
                      v-if="option.trim()"
                      type="button"
                      class="assessment-options-grid__check"
                      :class="{ 'assessment-options-grid__check--active': normalizeAnswer(option) === normalizeAnswer(form.correctAnswer) && form.correctAnswerTouched }"
                      @click="updateQuestionForm(formIndex, { correctAnswer: option.trim(), correctAnswerTouched: true })"
                    >
                      <v-icon small>
                        mdi-check
                      </v-icon>
                    </AppRawButton>
                    <input
                      :value="option"
                      type="text"
                      class="assessment-input"
                      :placeholder="`الخيار ${optionIndex + 1}`"
                      @input="handleOptionChange(formIndex, optionIndex, $event.target.value)"
                      @paste="handleOptionPaste(formIndex, optionIndex, $event)"
                    >
                    <AppRawButton
                      v-if="optionIndex === form.options.length - 1"
                      type="button"
                      class="assessment-options-grid__append"
                      @click="handleAddOptionField(formIndex)"
                    >
                      <v-icon small>
                        mdi-plus
                      </v-icon>
                    </AppRawButton>
                  </div>
                </div>
              </div>

              <p
                v-if="questionErrors[formIndex]"
                class="assessment-form-card__error"
              >
                {{ questionErrors[formIndex] }}
              </p>
            </article>
          </div>

          <div class="assessment-inline-builder__actions">
            <v-menu
              offset-y
              left
              content-class="final-exam-inline-builder-menu__content"
            >
              <template #activator="{ on, attrs }">
                <AppRawButton
                  type="button"
                  class="assessment-inline-builder__add"
                  aria-label="إضافة سؤال"
                  v-bind="attrs"
                  v-on="on"
                >
                  <v-icon small>
                    mdi-plus
                  </v-icon>
                </AppRawButton>
              </template>

              <div class="assessment-inline-builder__menu">
                <AppRawButton
                  type="button"
                  class="assessment-inline-builder__menu-item"
                  @click="handleAddQuestionSlot('multiple')"
                >
                  خيارات
                </AppRawButton>
                <AppRawButton
                  type="button"
                  class="assessment-inline-builder__menu-item"
                  @click="handleAddQuestionSlot('text')"
                >
                  نصي
                </AppRawButton>
              </div>
            </v-menu>

            <AppButton
              variant="primary"
              :loading="isSaving"
              @click="handleSaveAllQuestions"
            >
              {{ isSaving ? 'جارٍ الحفظ...' : 'حفظ' }}
            </AppButton>
          </div>
        </div>
      </section>

      <AppDialog
        v-model="copyDialogOpen"
        max-width="560"
      >
        <div class="final-exam-activation-dialog">
          <AppDialogHeader :title="`نسخ الأسئلة إلى ${targetBranchLabel}`" />

          <AppDialogBody class="final-exam-activation-dialog__body">
            <p class="final-exam-copy-dialog__text">
              سيتم نسخ جميع أسئلة {{ currentBranchLabel }} إلى {{ targetBranchLabel }}.
            </p>
          </AppDialogBody>

          <AppDialogFooter class="final-exam-activation-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeCopyDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="copySubmitting"
              @click="confirmCopyQuestions"
            >
              {{ copySubmitting ? 'جارٍ النسخ...' : 'موافق' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="activationDialogOpen"
        max-width="560"
      >
        <div class="final-exam-activation-dialog">
          <AppDialogHeader title="فتح الاختبار النهائي" />

          <AppDialogBody class="final-exam-activation-dialog__body">
            <div class="final-exam-duration-field final-exam-duration-field--dialog">
              <span class="final-exam-duration-field__label">اختر الفرع</span>
              <AppSelect
                v-model="activationBranch"
                :items="activationBranchOptions"
                item-text="label"
                item-value="value"
                dense
                outlined
                hide-details
                class="assessment-select"
              />
            </div>

            <div class="final-exam-duration-field final-exam-duration-field--dialog">
              <span class="final-exam-duration-field__label">مدة الفتح بالدقائق</span>
              <input
                v-model.number="openDurationMinutes"
                type="number"
                min="1"
                class="final-exam-duration-field__input"
                placeholder="60"
              >
            </div>

            <div
              v-if="activationError"
              class="final-exam-activation-dialog__error"
            >
              {{ activationError }}
            </div>
          </AppDialogBody>

          <AppDialogFooter class="final-exam-activation-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeActivationDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="activationSubmitting"
              @click="confirmActivation"
            >
              {{ activationSubmitting ? 'جارٍ البدء...' : 'بدء' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="manageDialogOpen"
        max-width="560"
      >
        <div class="final-exam-activation-dialog">
          <AppDialogHeader title="إدارة الاختبار النهائي" />

          <AppDialogBody class="final-exam-activation-dialog__body">
            <div class="final-exam-duration-field final-exam-duration-field--dialog">
              <span class="final-exam-duration-field__label">الإجراء</span>
              <AppSelect
                v-model="manageChoice"
                :items="manageOptions"
                item-text="label"
                item-value="value"
                dense
                outlined
                hide-details
                class="assessment-select"
              />
            </div>
          </AppDialogBody>

          <AppDialogFooter class="final-exam-activation-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeManageDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="manageSubmitting"
              @click="confirmManageAction"
            >
              {{ manageSubmitting ? 'جارٍ التنفيذ...' : 'تأكيد' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>
    </v-container>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import {
  AppButton, AppChoiceButton, AppDialog, AppDialogBody, AppDialogFooter,
  AppDialogHeader, AppRawButton, AppSelect,
} from '../components/ui';
import indicatorAnimation from '../mixins/indicatorAnimation';
import { parseImportedQuestionsFromText, splitPastedQuestionOptions } from '../utils/questionImportParser';

const emptyQuestionForm = (type = 'multiple') => ({
  prompt: '',
  type,
  options: type === 'multiple' ? ['', ''] : [],
  allowFile: 'no',
  points: '1',
  correctAnswer: '',
  correctAnswerTouched: false,
});

export default {
  name: 'AdminFinalExamView',
  components: {
    AppDialog,
    AppButton,
    AppChoiceButton,
    AppRawButton,
    AppSelect,
    AppDialogHeader,
    AppDialogBody,
    AppDialogFooter,
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
      selectedBranch: '',
      indicatorBranch: 'male',
      isEnabled: false,
      openDurationMinutes: 60,
      questionDialogOpen: false,
      copyDialogOpen: false,
      activationDialogOpen: false,
      copySubmitting: false,
      activationSubmitting: false,
      activationError: '',
      activationBranch: 'male',
      activationPreserveOtherBranch: false,
      manageDialogOpen: false,
      manageChoice: '',
      manageSubmitting: false,
      isSaving: false,
      questionForms: [],
      questionErrors: [],
      questionDrafts: {},
      questionDraftErrors: {},
      pendingDeletedQuestionIds: [],
      pasteText: '',
      currentTimestamp: Date.now(),
      countdownTimer: null,
      branchOptions: [
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
      ],
      questionTypeOptions: [
        { label: 'اختيارات', value: 'multiple' },
        { label: 'نصي', value: 'text' },
      ],
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'dashboardError', 'currentUser']),
    managedBranchId() {
      if (this.currentUser?.role === 'male_manager') {
        return 'male';
      }

      if (this.currentUser?.role === 'female_manager') {
        return 'female';
      }

      return '';
    },
    settings() {
      return this.dashboardSnapshot?.finalExamSettings || {
        male: { isEnabled: false, closesAt: null },
        female: { isEnabled: false, closesAt: null },
      };
    },
    students() {
      return this.dashboardSnapshot?.students || [];
    },
    finalExamSubmissions() {
      return this.dashboardSnapshot?.finalExamSubmissions || [];
    },
    showBranchCards() {
      return false;
    },
    branchQuestions() {
      const targetBranch = this.managedBranchId || this.selectedBranch;

      return (this.dashboardSnapshot?.finalExamQuestions || []).filter((question) => question.branchCode === targetBranch);
    },
    visibleBranchQuestions() {
      return this.branchQuestions.filter((question) => !this.pendingDeletedQuestionIds.includes(question.id));
    },
    currentBranchLabel() {
      return this.branchOptions.find((branch) => branch.value === (this.managedBranchId || this.selectedBranch))?.label || 'هذا الفرع';
    },
    targetBranchLabel() {
      return this.branchOptions.find((branch) => branch.value !== this.selectedBranch)?.label || 'الفرع الآخر';
    },
    activationBranchOptions() {
      return [
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
        { label: 'الكل', value: 'all' },
      ];
    },
    activeBranches() {
      return ['male', 'female'].filter((branchCode) => this.isBranchActive(branchCode));
    },
    indicatorStudents() {
      return this.students.filter((student) => student.branchId === (this.managedBranchId || this.indicatorBranch));
    },
    finalExamIndicator() {
      const totalStudents = this.indicatorStudents.length;

      if (!totalStudents) {
        return {
          submitted: 0,
          totalStudents: 0,
          percent: 0,
        };
      }

      const submittedLogins = new Set(
        this.finalExamSubmissions
          .filter((submission) => submission.branchCode === (this.managedBranchId || this.indicatorBranch))
          .map((submission) => submission.loginCode)
          .filter(Boolean),
      );
      const submitted = this.indicatorStudents.reduce(
        (sum, student) => sum + (submittedLogins.has(student.loginId) ? 1 : 0),
        0,
      );

      return {
        submitted,
        totalStudents,
        percent: Math.round((submitted / totalStudents) * 100),
      };
    },
    finalExamIndicatorStyle() {
      return this.buildIndicatorRingStyle(this.finalExamIndicator.percent);
    },
    indicatorAnimationSignature() {
      return [
        this.indicatorBranch,
        this.finalExamIndicator.percent,
        this.finalExamIndicator.submitted,
        this.finalExamIndicator.totalStudents,
      ].join('|');
    },
    hasAnyActiveBranch() {
      return this.activeBranches.length > 0;
    },
    manageOptions() {
      if (this.activeBranches.length === 2) {
        return [
          { value: 'close_all', label: 'إغلاق الكل' },
          { value: 'close_male', label: 'إغلاق معلمين' },
          { value: 'close_female', label: 'إغلاق معلمات' },
        ];
      }

      if (this.activeBranches.length === 1) {
        const activeBranch = this.activeBranches[0];
        const inactiveBranch = activeBranch === 'male' ? 'female' : 'male';

        return [
          { value: `close_${activeBranch}`, label: `إغلاق ${this.branchLabel(activeBranch)}` },
          { value: `open_${inactiveBranch}`, label: `فتح ${this.branchLabel(inactiveBranch)}` },
          { value: 'open_all', label: 'فتح الكل' },
        ];
      }

      return [];
    },
  },
  watch: {
    managedBranchId: {
      immediate: true,
      handler(value) {
        if (!value) {
          return;
        }

        this.selectedBranch = value;
        this.indicatorBranch = value;
        this.activationBranch = value;
      },
    },
    selectedBranch: {
      immediate: true,
      handler() {
        this.syncBranchState();
        this.questionForms = [];
        this.questionErrors = [];
        this.pendingDeletedQuestionIds = [];
      },
    },
    branchQuestions: {
      immediate: true,
      handler(questions) {
        this.syncQuestionDrafts(questions);
      },
    },
    dashboardSnapshot: {
      deep: true,
      handler() {
        this.syncBranchState();
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
    this.countdownTimer = window.setInterval(() => {
      this.currentTimestamp = Date.now();
    }, 1000);

    this.loadDashboardSnapshot();
  },
  beforeDestroy() {
    if (this.countdownTimer) {
      window.clearInterval(this.countdownTimer);
      this.countdownTimer = null;
    }
  },
  methods: {
    ...mapActions([
      'loadDashboardSnapshot',
      'addFinalExamQuestion',
      'updateFinalExamQuestion',
      'deleteFinalExamQuestion',
      'toggleFinalExamEnabled',
      'copyFinalExamQuestions',
    ]),
    buildIndicatorRingStyle(percent) {
      const safePercent = this.animatedIndicatorPercent(percent);

      return {
        background: `conic-gradient(#156c82 0 ${safePercent}%, #e9f2f5 ${safePercent}% 100%)`,
      };
    },
    syncBranchState(branchCode = '') {
      const requestedBranchCode = branchCode || this.selectedBranch;
      const activeBranchCode = this.branchOptions.some((branch) => branch.value === requestedBranchCode)
        ? requestedBranchCode
        : this.defaultBranchCode();

      if (this.selectedBranch !== activeBranchCode) {
        this.selectedBranch = activeBranchCode;
      }

      const setting = this.settings[activeBranchCode] || { isEnabled: false, closesAt: null };
      this.isEnabled = this.isBranchActive(activeBranchCode);

      if (setting.closesAt) {
        const remainingMinutes = this.minutesUntil(setting.closesAt);

        if (remainingMinutes > 0) {
          this.openDurationMinutes = remainingMinutes;
        }
      }

      this.emitTopbarState(setting, activeBranchCode);
    },
    defaultBranchCode() {
      return this.branchOptions[this.branchOptions.length - 1]?.value || 'male';
    },
    emitTopbarState(setting, branchCode) {
      this.$emit('final-exam-topbar-state', {
        branchCode,
        branchLabel: this.branchLabel(branchCode),
        isEnabled: this.isBranchActive(branchCode),
        closesAt: setting?.closesAt || null,
        timers: this.activeBranches
          .map((activeBranchCode) => {
            const activeSetting = this.settings[activeBranchCode] || { closesAt: null };

            if (!this.isBranchActive(activeBranchCode) || !activeSetting.closesAt) {
              return null;
            }

            return {
              branchCode: activeBranchCode,
              branchLabel: this.branchLabel(activeBranchCode),
              closesAt: activeSetting.closesAt,
            };
          })
          .filter(Boolean),
      });
    },
    openBranchWorkspace(branchCode) {
      this.selectedBranch = branchCode;
    },
    async toggleBranchActivation(branchCode) {
      if (this.isBranchActive(branchCode)) {
        try {
          await this.closeBranches([branchCode]);
          this.$toast.success('تم تحديث حالة الاختبار النهائي');
        } catch (error) {
          this.$toast.error(error?.response?.data?.message || 'تعذر تحديث حالة الاختبار النهائي');
        }

        return;
      }

      this.activationBranch = branchCode;
      this.activationPreserveOtherBranch = false;
      this.activationError = '';
      this.activationSubmitting = false;
      this.activationDialogOpen = true;
    },
    branchLabel(branchCode) {
      return this.branchOptions.find((branch) => branch.value === branchCode)?.label || 'هذا الفرع';
    },
    minutesUntil(value) {
      const parsed = new Date(value);

      if (Number.isNaN(parsed.getTime())) {
        return 0;
      }

      const diffMs = parsed.getTime() - Date.now();

      if (diffMs <= 0) {
        return 0;
      }

      return Math.max(1, Math.ceil(diffMs / 60000));
    },
    isBranchActive(branchCode) {
      const setting = this.settings[branchCode] || { isEnabled: false, closesAt: null };

      if (!setting.isEnabled || !setting.closesAt) {
        return false;
      }

      const closesAt = new Date(setting.closesAt).getTime();
      return Number.isFinite(closesAt) && closesAt > this.currentTimestamp;
    },
    questionTypeLabel(type) {
      return this.questionTypeOptions.find((option) => option.value === type)?.label || type;
    },
    normalizeAnswer(value) {
      return String(value || '').trim().toLowerCase();
    },
    normalizeQuestionType(question) {
      if (question?.type === 'text') {
        return 'text';
      }

      return 'multiple';
    },
    createQuestionDraft(question = null) {
      const type = this.normalizeQuestionType(question);

      return {
        prompt: question?.prompt || '',
        type,
        options: type === 'multiple'
          ? ((Array.isArray(question?.options) && question.options.length >= 2) ? [...question.options] : ['', ''])
          : [],
        allowFile: 'no',
        points: String(question?.points ?? 1),
        correctAnswer: question?.correctAnswer || '',
        correctAnswerTouched: Boolean(question?.correctAnswer),
      };
    },
    syncQuestionDrafts(questions) {
      const nextDrafts = {};
      const nextErrors = {};

      questions.forEach((question) => {
        nextDrafts[question.id] = this.questionDrafts[question.id]
          ? {
            ...this.questionDrafts[question.id],
            type: this.questionDrafts[question.id].type || this.normalizeQuestionType(question),
          }
          : this.createQuestionDraft(question);
        nextErrors[question.id] = this.questionDraftErrors[question.id] || '';
      });

      this.questionDrafts = nextDrafts;
      this.questionDraftErrors = nextErrors;
    },
    updateQuestionDraft(questionId, patch) {
      this.questionDrafts = {
        ...this.questionDrafts,
        [questionId]: {
          ...(this.questionDrafts[questionId] || this.createQuestionDraft()),
          ...patch,
        },
      };
    },
    clearQuestionDraftError(questionId) {
      this.questionDraftErrors = {
        ...this.questionDraftErrors,
        [questionId]: '',
      };
    },
    openQuestionDialog() {
      this.handleCreateQuestionDialogChange(true);
    },
    openActivationDialog() {
      if (this.hasAnyActiveBranch) {
        this.openManageDialog();
        return;
      }

      this.activationBranch = this.selectedBranch || this.branchOptions[0]?.value || 'male';
      this.activationPreserveOtherBranch = false;
      this.activationError = '';
      this.activationSubmitting = false;
      this.activationDialogOpen = true;
    },
    closeActivationDialog() {
      if (this.activationSubmitting) {
        return;
      }

      this.activationDialogOpen = false;
      this.activationError = '';
      this.activationBranch = this.selectedBranch || this.branchOptions[0]?.value || 'male';
      this.activationPreserveOtherBranch = false;
    },
    openManageDialog() {
      this.manageChoice = this.manageOptions[0]?.value || '';
      this.manageSubmitting = false;
      this.manageDialogOpen = true;
    },
    closeManageDialog() {
      if (this.manageSubmitting) {
        return;
      }

      this.manageDialogOpen = false;
      this.manageChoice = '';
    },
    triggerCopyQuestions() {
      this.copySubmitting = false;
      this.copyDialogOpen = true;
    },
    closeCopyDialog() {
      if (this.copySubmitting) {
        return;
      }

      this.copyDialogOpen = false;
    },
    async confirmActivation() {
      const minutes = Number(this.openDurationMinutes);

      if (!Number.isFinite(minutes) || minutes <= 0) {
        this.activationError = 'أدخل مدة فتح صحيحة بالدقائق';
        return;
      }

      this.activationSubmitting = true;

      try {
        await this.applyActivation(this.activationBranch, this.activationPreserveOtherBranch);
        this.activationDialogOpen = false;
        this.activationError = '';
        this.$toast.success('تم تفعيل الاختبار النهائي');
      } catch (error) {
        this.activationError = error?.response?.data?.message || 'تعذر حفظ الإعدادات';
      } finally {
        this.activationSubmitting = false;
      }
    },
    async applyActivation(branchCode, preserveOtherBranch = false) {
      const closesAt = this.formatDateTimeForApi(new Date(Date.now() + (Number(this.openDurationMinutes) * 60000)));
      const targetBranches = branchCode === 'all' ? ['male', 'female'] : [branchCode];
      const otherBranches = ['male', 'female'].filter((value) => !targetBranches.includes(value));

      await Promise.all(targetBranches.map((targetBranch) => this.toggleFinalExamEnabled({
        branchCode: targetBranch,
        closesAt,
      })));

      if (!preserveOtherBranch && branchCode !== 'all') {
        await Promise.all(otherBranches
          .filter((otherBranch) => this.isBranchActive(otherBranch))
          .map((otherBranch) => this.toggleFinalExamEnabled({
            branchCode: otherBranch,
            closesAt: null,
          })));
      }
    },
    async closeBranches(branches) {
      await Promise.all(branches
        .filter((branchCode) => this.isBranchActive(branchCode))
        .map((branchCode) => this.toggleFinalExamEnabled({
          branchCode,
          closesAt: null,
        })));
    },
    async confirmManageAction() {
      if (!this.manageChoice) {
        return;
      }

      this.manageSubmitting = true;

      try {
        if (this.manageChoice.startsWith('close_')) {
          const branchCode = this.manageChoice.replace('close_', '');
          await this.closeBranches(branchCode === 'all' ? ['male', 'female'] : [branchCode]);
          this.$toast.success('تم تحديث حالة الاختبار النهائي');
          this.closeManageDialog();
          return;
        }

        const branchCode = this.manageChoice.replace('open_', '');
        this.closeManageDialog();
        this.activationBranch = branchCode === 'all' ? 'all' : branchCode;
        this.activationPreserveOtherBranch = branchCode !== 'all';
        this.activationError = '';
        this.activationSubmitting = false;
        this.activationDialogOpen = true;
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحديث حالة الاختبار النهائي');
      } finally {
        this.manageSubmitting = false;
      }
    },
    formatDateTimeForApi(value) {
      const year = value.getFullYear();
      const month = String(value.getMonth() + 1).padStart(2, '0');
      const day = String(value.getDate()).padStart(2, '0');
      const hours = String(value.getHours()).padStart(2, '0');
      const minutes = String(value.getMinutes()).padStart(2, '0');
      const seconds = String(value.getSeconds()).padStart(2, '0');

      return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },
    async copyQuestions(move) {
      try {
        await this.copyFinalExamQuestions({
          from: this.selectedBranch,
          to: this.selectedBranch === 'male' ? 'female' : 'male',
          move,
        });
        this.$toast.success(move ? 'تم نقل الأسئلة' : 'تم نسخ الأسئلة');
        return true;
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تنفيذ العملية');
        return false;
      }
    },
    async confirmCopyQuestions() {
      this.copySubmitting = true;

      try {
        const copied = await this.copyQuestions(false);

        if (copied) {
          this.copyDialogOpen = false;
        }
      } finally {
        this.copySubmitting = false;
      }
    },
    removeQuestion(questionId) {
      if (!questionId || this.pendingDeletedQuestionIds.includes(questionId)) {
        return;
      }

      this.pendingDeletedQuestionIds = [...this.pendingDeletedQuestionIds, questionId];
      this.questionDraftErrors = {
        ...this.questionDraftErrors,
        [questionId]: '',
      };
    },
    handleCreateQuestionDialogChange(open) {
      this.questionDialogOpen = open;

      if (!open) {
        this.questionForms = [emptyQuestionForm()];
        this.questionErrors = [''];
        this.pasteText = '';
      }
    },
    updateQuestionForm(index, patch) {
      this.questionForms = this.questionForms.map((form, formIndex) => (formIndex === index ? { ...form, ...patch } : form));
      this.questionErrors = this.questionErrors.map((error, errorIndex) => (errorIndex === index ? '' : error));
    },
    clearQuestionError(index) {
      this.questionErrors = this.questionErrors.map((error, errorIndex) => (errorIndex === index ? '' : error));
    },
    availableAnswers(form) {
      return form.options.map((option) => option.trim()).filter(Boolean);
    },
    handleOptionChange(formIndex, optionIndex, value) {
      this.questionForms = this.questionForms.map((form, index) => {
        if (index !== formIndex) {
          return form;
        }

        const options = form.options.map((option, currentOptionIndex) => (currentOptionIndex === optionIndex ? value : option));
        const sanitizedOptions = options.map((option) => option.trim()).filter(Boolean);

        return {
          ...form,
          options,
          correctAnswer: sanitizedOptions.includes(form.correctAnswer) ? form.correctAnswer : '',
        };
      });
    },
    handleExistingOptionChange(questionId, optionIndex, value) {
      const draft = this.questionDrafts[questionId];

      if (!draft) {
        return;
      }

      const options = draft.options.map((option, currentIndex) => (currentIndex === optionIndex ? value : option));
      const sanitizedOptions = options.map((option) => option.trim()).filter(Boolean);
      const correctAnswer = sanitizedOptions.some((option) => this.normalizeAnswer(option) === this.normalizeAnswer(draft.correctAnswer))
        ? draft.correctAnswer
        : '';

      this.updateQuestionDraft(questionId, { options, correctAnswer });
    },
    handleExistingAddOptionField(questionId) {
      const draft = this.questionDrafts[questionId];

      if (!draft) {
        return;
      }

      this.updateQuestionDraft(questionId, { options: [...draft.options, ''] });
    },
    selectExistingCorrectOption(questionId, optionIndex) {
      const optionValue = this.questionDrafts[questionId]?.options?.[optionIndex]?.trim() || '';

      if (!optionValue) {
        return;
      }

      this.updateQuestionDraft(questionId, { correctAnswer: optionValue, correctAnswerTouched: true });
      this.clearQuestionDraftError(questionId);
    },
    isExistingCorrectOption(questionId, option) {
      const draft = this.questionDrafts[questionId];

      if (!draft?.correctAnswerTouched) {
        return false;
      }

      return this.normalizeAnswer(option) === this.normalizeAnswer(draft.correctAnswer);
    },
    handleOptionPaste(formIndex, optionIndex, event) {
      const pastedOptions = splitPastedQuestionOptions(event.clipboardData.getData('text'));

      if (pastedOptions.length < 2) {
        return;
      }

      event.preventDefault();
      this.questionForms = this.questionForms.map((form, index) => {
        if (index !== formIndex) {
          return form;
        }

        const options = [...form.options];
        while (options.length < optionIndex + pastedOptions.length) {
          options.push('');
        }

        pastedOptions.forEach((option, pastedIndex) => {
          options[optionIndex + pastedIndex] = option;
        });

        const sanitizedOptions = options.map((option) => option.trim()).filter(Boolean);

        return {
          ...form,
          options,
          correctAnswer: sanitizedOptions.includes(form.correctAnswer) ? form.correctAnswer : '',
        };
      });
    },
    mapImportedDraftToForm(draft, defaultPoints, preferredType) {
      const effectiveType = preferredType === 'text' ? 'text' : draft.type;

      return {
        ...emptyQuestionForm(effectiveType),
        prompt: draft.prompt,
        type: effectiveType,
        options: effectiveType === 'multiple' ? (draft.options.length >= 2 ? draft.options : ['', '']) : [],
        points: String(defaultPoints),
        correctAnswer: '',
      };
    },
    handlePasteImport() {
      const text = this.pasteText.trim();

      if (!text) {
        return;
      }

      const rawPoints = Number(this.questionForms[0]?.points ?? '1');
      const defaultPoints = Number.isFinite(rawPoints) && rawPoints >= 0 ? rawPoints : 1;
      const preferredType = this.questionForms[0]?.type ?? 'multiple';
      const importedQuestions = parseImportedQuestionsFromText(text);

      if (!importedQuestions.length) {
        return;
      }

      this.questionForms = importedQuestions.map((question) => this.mapImportedDraftToForm(question, defaultPoints, preferredType));
      this.questionErrors = this.questionForms.map(() => '');
      this.pasteText = '';
    },
    handleBulkPaste(event) {
      const text = event.clipboardData.getData('text');

      if (!text.trim()) {
        return;
      }

      event.preventDefault();
      this.pasteText = text;
      this.handlePasteImport();
    },
    handleAddOptionField(formIndex) {
      this.questionForms = this.questionForms.map((form, index) => (index === formIndex ? { ...form, options: [...form.options, ''] } : form));
    },
    handleAddQuestionSlot(type = 'multiple') {
      this.questionForms = [...this.questionForms, emptyQuestionForm(type === 'text' ? 'text' : 'multiple')];
      this.questionErrors = [...this.questionErrors, ''];
    },
    handleRemoveQuestionSlot(index) {
      if (this.questionForms.length <= 1) {
        return;
      }

      this.questionForms = this.questionForms.filter((_, currentIndex) => currentIndex !== index);
      this.questionErrors = this.questionErrors.filter((_, currentIndex) => currentIndex !== index);
    },
    validateQuestionDraft(form) {
      const prompt = String(form?.prompt || '').trim();
      const options = form?.type === 'multiple'
        ? (form.options || []).map((option) => option.trim()).filter(Boolean)
        : (form?.type === 'truefalse' ? ['صح', 'خطأ'] : []);

      if (!prompt) {
        return 'أدخل السؤال.';
      }

      if (form?.type === 'multiple' && options.length < 2) {
        return 'أدخل خيارين على الأقل.';
      }

      if (form?.type === 'multiple' && !String(form?.correctAnswer || '').trim()) {
        return 'اختر الإجابة الصحيحة.';
      }

      const points = Number(form?.points);

      if (!Number.isFinite(points) || points < 0) {
        return 'أدخل درجة صحيحة.';
      }

      return '';
    },
    async submitQuestions() {
      let hasError = false;
      const nextErrors = this.questionForms.map((form) => {
        const error = this.validateQuestionDraft(form);

        if (error) {
          hasError = true;
        }

        return error;
      });

      if (hasError) {
        this.questionErrors = nextErrors;
        return;
      }

      try {
        for (const form of this.questionForms) {
          const options = form.type === 'multiple'
            ? form.options.map((option) => option.trim()).filter(Boolean)
            : [];

          await this.addFinalExamQuestion({
            branchCode: this.selectedBranch,
            prompt: form.prompt.trim(),
            type: form.type,
            options,
            allowFile: false,
            points: Number(form.points || 1),
            correctAnswer: form.correctAnswer.trim(),
          });
        }

        this.$toast.success(this.questionForms.length > 1 ? 'تمت إضافة الأسئلة' : 'تمت إضافة السؤال');
        this.handleCreateQuestionDialogChange(false);
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر إضافة السؤال');
      }
    },
    async handleSaveAllQuestions() {
      if (this.isSaving) {
        return;
      }

      let hasError = false;
      const nextDraftErrors = {};

      this.visibleBranchQuestions.forEach((question) => {
        const validationError = this.validateQuestionDraft(this.questionDrafts[question.id]);
        nextDraftErrors[question.id] = validationError;

        if (validationError) {
          hasError = true;
        }
      });

      const nextErrors = this.questionForms.map((form) => {
        const error = this.validateQuestionDraft(form);

        if (error) {
          hasError = true;
        }

        return error;
      });

      this.questionDraftErrors = {
        ...this.questionDraftErrors,
        ...nextDraftErrors,
      };

      if (hasError) {
        this.questionErrors = nextErrors;
        return;
      }

      this.isSaving = true;

      try {
        for (const question of this.visibleBranchQuestions) {
          const draft = this.questionDrafts[question.id];
          const options = draft.type === 'multiple'
            ? draft.options.map((option) => option.trim()).filter(Boolean)
            : [];

          await this.updateFinalExamQuestion({
            questionId: question.id,
            question: {
              prompt: draft.prompt.trim(),
              type: draft.type,
              options,
              allowFile: false,
              points: Number(draft.points || 1),
              correctAnswer: String(draft.correctAnswer || '').trim(),
            },
          });
        }

        for (const questionId of this.pendingDeletedQuestionIds) {
          await this.deleteFinalExamQuestion(questionId);
        }

        for (const form of this.questionForms) {
          const options = form.type === 'multiple'
            ? form.options.map((option) => option.trim()).filter(Boolean)
            : [];

          await this.addFinalExamQuestion({
            branchCode: this.selectedBranch,
            prompt: form.prompt.trim(),
            type: form.type,
            options,
            allowFile: false,
            points: Number(form.points || 1),
            correctAnswer: String(form.correctAnswer || '').trim(),
          });
        }

        this.$toast.success('تم حفظ الأسئلة بنجاح');
        this.questionForms = [emptyQuestionForm()];
        this.questionErrors = [''];
        this.pendingDeletedQuestionIds = [];
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ الأسئلة');
      } finally {
        this.isSaving = false;
      }
    },
  },
};
</script>

<style scoped>
.final-exam-page {
  min-height: 100%;
  background: linear-gradient(180deg, #f7fbff 0%, #eef5f7 100%);
}

.final-exam-page--embedded {
  background: transparent;
}

.final-exam-page--embedded .final-exam-page__container {
  max-width: none;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.final-exam-page__container {
  max-width: 1180px;
}

.final-exam-topbar,
.final-exam-topbar__actions,
.final-exam-tabs,
.final-exam-list-shell__header,
.final-exam-row,
.final-exam-row__meta,
.assessment-dialog__header,
.assessment-dialog__footer,
.assessment-dialog__title-wrap,
.assessment-form-card__topline {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.assessment-options-grid__item {
  display: flex;
  align-items: stretch;
  gap: 8px;
}

.final-exam-topbar {
  align-items: flex-start;
  justify-content: space-between;
}

.final-exam-topbar__title {
  margin: 0;
  color: #0f172a;
  font-size: clamp(1.95rem, 2.5vw, 2.8rem);
  font-weight: 900;
}

.final-exam-topbar__actions {
  align-items: flex-end;
  justify-content: flex-end;
}

.final-exam-duration-field {
  display: grid;
  gap: 8px;
  min-width: 190px;
}

.final-exam-duration-field--dialog {
  min-width: 100%;
}

.final-exam-branch-picker {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.final-exam-branch-picker__button {
  min-height: 44px;
  font-size: 0.92rem;
}

.final-exam-duration-field__label {
  color: #64748b;
  font-size: 0.9rem;
  font-weight: 700;
}

.assessment-select {
  width: 100%;
}

.final-exam-duration-field__input,
.assessment-input {
  text-align: right;
}

.final-exam-duration-field__input:focus,
.assessment-input:focus,
.assessment-dialog__paste:focus {
  border-color: rgba(13, 90, 115, 0.38);
  box-shadow: 0 0 0 4px rgba(13, 90, 115, 0.08);
}

.final-exam-primary-button,
.final-exam-row__delete,
.assessment-dialog__add-slot {
  border: 0;
  transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
}

.final-exam-primary-button {
  min-height: 48px;
  padding: 0 22px;
}

.final-exam-primary-button--ghost {
  min-width: 138px;
}

.final-exam-primary-button:hover,
.final-exam-row__delete:hover,
.assessment-primary-button:hover,
.assessment-secondary-button:hover,
.assessment-dialog__add-slot:hover {
  transform: translateY(-1px);
}

.final-exam-tabs {
  align-items: center;
  gap: 16px;
}

.final-exam-tab {
  min-width: 180px;
  min-height: 56px;
}

.final-exam-copy-dialog__text {
  margin: 0;
  color: #334155;
  font-size: 1rem;
  line-height: 1.9;
}

.final-exam-list-shell {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  backdrop-filter: blur(10px);
  padding: 28px;
}

.assessment-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 18px;
  margin-bottom: 20px;
}

.assessment-toolbar--embedded {
  align-items: flex-end;
}

.assessment-toolbar__field {
  flex: 1 1 260px;
  display: grid;
  gap: 8px;
}

.assessment-toolbar__label {
  color: #114258;
  font-size: 0.92rem;
  font-weight: 800;
}

.assessment-empty-state {
  border: 1px dashed rgba(148, 163, 184, 0.35);
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.74);
  padding: 18px;
  color: #64748b;
  font-size: 0.96rem;
}

.assessment-cards-shell {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
  gap: 20px;
  margin-bottom: 24px;
}

@media (min-width: 1264px) {
  .assessment-cards-shell {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.assessment-course-card {
  border: 1px solid rgba(214, 229, 238, 0.82);
  border-radius: 28px;
  background: #fff;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
  min-height: 0;
  padding: 24px;
}

.assessment-course-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.assessment-course-card__copy {
  flex: 1;
  min-height: 4rem;
  text-align: right;
}

.assessment-course-card__title {
  margin: 0;
  color: #123f56;
  font-size: 1.05rem;
  font-weight: 900;
  line-height: 1.8;
  text-align: right;
}

.assessment-course-card__buttons {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-top: 12px;
}

.assessment-course-card__button {
  min-height: 50px;
  font-size: 0.95rem;
  font-weight: 900;
  line-height: 1.25;
}

.assessment-indicators-card {
  border-radius: 28px;
  background: #ffffff;
  padding: 32px;
  margin-bottom: 24px;
}

.assessment-indicators-card__header {
  margin-bottom: 24px;
}

.assessment-indicators-card__title {
  margin: 0;
  color: #114258;
  font-size: 1.35rem;
  font-weight: 900;
  text-align: right;
}

.assessment-indicators-card__controls {
  display: flex;
  gap: 20px;
  margin-bottom: 30px;
}

.assessment-indicators-card__filter-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}

.assessment-indicators-card__label {
  color: #114258;
  font-weight: 900;
  font-size: 0.95rem;
}

.assessment-indicators-panel {
  border: 1px solid rgba(220, 235, 240, 0.7);
  border-radius: 24px;
  padding: 50px;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  gap: 60px;
}

.assessment-indicators-panel--single {
  justify-content: center;
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

.assessment-toolbar__actions {
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
}


.assessment-inline-builder {
  display: grid;
  gap: 18px;
  margin-top: 18px;
}

.assessment-inline-builder__list {
  display: grid;
  gap: 14px;
}

.assessment-form-card--inline {
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
}

.assessment-inline-builder__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.assessment-inline-builder__add {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  min-width: 52px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #156c82;
  box-shadow: none;
}

.assessment-inline-builder__add :deep(.v-icon) {
  color: currentColor !important;
}

.assessment-inline-builder__menu {
  display: grid;
  min-width: 180px;
  padding: 6px 0;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 14px 28px rgba(17, 65, 96, 0.14);
  overflow: hidden;
}

.assessment-inline-builder__menu-item {
  display: block;
  width: 100%;
  min-height: 46px;
  padding: 0 18px;
  border: 0;
  background: transparent;
  color: #21465f;
  font-size: 0.96rem;
  font-weight: 700;
  text-align: right;
}

.assessment-inline-builder__menu-item:hover {
  background: rgba(31, 111, 150, 0.08);
}

:deep(.final-exam-inline-builder-menu__content) {
  box-shadow: none;
}

.assessment-form-card__meta {
  display: flex;
  justify-content: flex-end;
  width: 100%;
}

.assessment-form-card__trash {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  min-width: 28px;
  padding: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: #dc2626;
  box-shadow: none;
}

.assessment-form-card__prompt-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 150px;
  gap: 12px;
  margin-top: 14px;
}

.assessment-form-card__field-group--prompt {
  min-width: 0;
}

.assessment-form-card__field-group--points-inline {
  align-self: end;
}

.assessment-inline-list__actions {
  margin-top: 14px;
}

.assessment-options-grid__item--correct .assessment-input {
  border-color: rgba(15, 159, 99, 0.42);
  box-shadow: 0 0 0 2px rgba(15, 159, 99, 0.1);
}

.assessment-options-grid__check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  min-width: 44px;
  border: 1px solid rgba(148, 163, 184, 0.36);
  border-radius: 18px;
  background: #f8fafc;
  color: #7b8794;
  transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.assessment-options-grid__check--active {
  border-color: rgba(15, 159, 99, 0.42);
  background: rgba(15, 159, 99, 0.12);
  color: #047857;
}

.assessment-options-grid__check:disabled {
  opacity: 0.45;
}

.final-exam-list-shell__header {
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.final-exam-list-shell__title {
  margin: 0;
  color: #0f172a;
  font-size: 1.18rem;
  font-weight: 900;
}

.final-exam-list-shell__count {
  color: #64748b;
  font-size: 0.95rem;
  font-weight: 700;
}

.final-exam-list-shell__empty {
  color: #64748b;
  line-height: 1.9;
}

.final-exam-list {
  display: grid;
  gap: 12px;
}

.final-exam-row {
  align-items: flex-start;
  padding: 16px 0;
  border-bottom: 1px solid rgba(226, 232, 240, 0.9);
}

.final-exam-row:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

.final-exam-row__index {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 14px;
  background: #eff6ff;
  color: #0d5a73;
  font-weight: 900;
  flex: 0 0 auto;
}

.final-exam-row__content {
  flex: 1 1 320px;
  min-width: 0;
}

.final-exam-row__prompt {
  color: #0f172a;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.9;
}

.final-exam-row__meta {
  align-items: center;
  margin-top: 8px;
  color: #64748b;
  font-size: 0.92rem;
}

.final-exam-row__options {
  margin-top: 10px;
  color: #334155;
  line-height: 1.9;
}

.final-exam-row__delete {
  min-height: 42px;
  padding: 0 16px;
  border-radius: 999px;
  background: #eff6ff;
  color: #0d5a73;
  font-weight: 800;
}

.final-exam-activation-dialog {
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 24px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
  overflow: hidden;
}

.final-exam-activation-dialog__header,
.final-exam-activation-dialog__footer {
  padding: 16px 18px;
}

.final-exam-activation-dialog__header {
  border-bottom: 1px solid rgba(148, 163, 184, 0.16);
}

.final-exam-activation-dialog__footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  border-top: 1px solid rgba(148, 163, 184, 0.16);
}

.final-exam-activation-dialog__title {
  color: #0f172a;
  font-size: 1.08rem;
  font-weight: 900;
}

.final-exam-activation-dialog__body {
  padding: 18px;
}

.final-exam-activation-dialog__error {
  margin-top: 14px;
  color: #b42318;
  font-size: 0.9rem;
  font-weight: 700;
}

.assessment-dialog__import-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.16);
}

.assessment-dialog__import-row--inline {
  padding: 0;
  margin-top: 16px;
  margin-bottom: 18px;
  border-bottom: 0;
}

.assessment-dialog__paste {
  flex: 1;
  min-height: 72px;
  max-height: 140px;
  padding: 12px 14px;
  border: 1px solid rgba(148, 163, 184, 0.24);
  border-radius: 18px;
  outline: none;
  resize: vertical;
  background: rgba(248, 250, 252, 0.72);
  color: #0f172a;
  font-size: 0.94rem;
  line-height: 1.8;
}

.assessment-form-card:not(.assessment-form-card--inline) {
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 22px;
  background: rgba(248, 250, 252, 0.72);
  padding: 18px;
}

.assessment-form-card__topline {
  align-items: center;
  justify-content: space-between;
}

.assessment-form-card__index,
.assessment-form-card__label {
  color: #0f172a;
  font-size: 0.92rem;
  font-weight: 800;
}

.assessment-form-card__type-switcher {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin-top: 14px;
}

.assessment-form-card__type {
  min-height: 42px;
}

.assessment-form-card__field-group {
  display: grid;
  gap: 8px;
  margin-top: 16px;
}

.assessment-form-card__field-group--compact {
  margin-top: 0;
}

.assessment-form-card__triple-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.assessment-options-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: 1fr;
}

.assessment-static-options {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.assessment-options-grid__append {
  width: 34px;
  min-width: 34px;
}

.assessment-static-options__item {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.82);
  color: #0f172a;
  font-size: 0.95rem;
  font-weight: 700;
}

.assessment-form-card__error {
  margin: 12px 0 0;
  color: #dc2626;
  font-size: 0.88rem;
  font-weight: 700;
}

.assessment-dialog__add-slot {
  min-height: 44px;
  border-radius: 18px;
  border: 1px dashed rgba(13, 90, 115, 0.24);
  background: rgba(13, 90, 115, 0.04);
  color: #0d5a73;
  font-weight: 800;
}

@media (max-width: 960px) {
  .assessment-form-card__prompt-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .assessment-cards-shell {
    grid-template-columns: 1fr;
  }

  .assessment-course-card__buttons {
    grid-template-columns: 1fr;
  }

  .assessment-indicators-card__controls,
  .assessment-indicators-panel {
    flex-direction: column;
  }

  .assessment-indicators-panel {
    padding: 28px 20px;
    gap: 28px;
  }

  .final-exam-topbar {
    flex-direction: column;
    align-items: stretch;
  }

  .final-exam-topbar__actions {
    justify-content: flex-start;
  }

  .assessment-dialog__footer,
  .assessment-dialog__header {
    padding-left: 14px;
    padding-right: 14px;
  }

  .assessment-dialog__footer {
    flex-direction: column;
  }

  .final-exam-activation-dialog__footer {
    flex-direction: column;
  }

  .final-exam-branch-picker {
    grid-template-columns: 1fr;
  }
}
</style>
