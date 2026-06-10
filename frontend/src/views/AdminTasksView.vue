<template>
  <div
    class="tasks-page"
    :class="{ 'tasks-page--embedded': embedded }"
  >
    <v-container class="tasks-page__container py-8 py-md-10">
      <v-alert
        v-if="dashboardError"
        type="error"
        outlined
        class="tasks-page__alert mb-6"
      >
        {{ dashboardError }}
      </v-alert>

      <AdminAssessmentView
        ref="assessmentPanel"
        embedded
        assessment-type-override="tasks"
        :skip-initial-load="true"
        @assessment-topbar-state="$emit('assessment-topbar-state', $event)"
      />

      <AppDialog
        v-model="questionDialogOpen"
        max-width="780"
        scrollable
      >
        <div class="assessment-dialog">
          <AppDialogHeader title="إضافة أسئلة" />

          <AppDialogBody class="assessment-dialog__body">
            <article
              v-for="(form, formIndex) in questionForms"
              :key="`task-question-form-${formIndex}`"
              class="assessment-form-card"
            >
              <div class="assessment-form-card__topline">
                <span class="assessment-form-card__index">السؤال {{ formIndex + 1 }}</span>
                <AppIconButton
                  v-if="questionForms.length > 1"
                  variant="danger"
                  size="sm"
                  class="assessment-form-card__remove"
                  @click="handleRemoveQuestionSlot(formIndex)"
                >
                  <i
                    class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                    aria-hidden="true"
                  />
                </AppIconButton>
              </div>

              <div class="assessment-form-card__type-switcher">
                <AppChoiceButton
                  class="assessment-form-card__type"
                  :active="form.type === 'truefalse'"
                  @click="handleQuestionTypeChange(formIndex, 'truefalse')"
                >
                  صح وخطأ
                </AppChoiceButton>
                <AppChoiceButton
                  class="assessment-form-card__type"
                  :active="form.type === 'text'"
                  @click="handleQuestionTypeChange(formIndex, 'text')"
                >
                  نصي
                </AppChoiceButton>
                <AppChoiceButton
                  class="assessment-form-card__type"
                  :active="form.type === 'multiple'"
                  @click="handleQuestionTypeChange(formIndex, 'multiple')"
                >
                  خيارات
                </AppChoiceButton>
              </div>

              <div
                v-if="formIndex === 0"
                class="assessment-dialog__import-row assessment-dialog__import-row--inline"
              >
                <textarea
                  v-model="pasteText"
                  class="assessment-dialog__paste"
                  placeholder="الصق الأسئلة هنا وسيتم تقسيمها تلقائيًا"
                  @paste="handleBulkPaste"
                />
              </div>

              <div class="assessment-form-card__field-group">
                <label class="assessment-form-card__label">السؤال</label>
                <input
                  :value="form.prompt"
                  type="text"
                  class="assessment-input"
                  placeholder="اكتب السؤال"
                  @input="updateQuestionForm(formIndex, { prompt: $event.target.value }); clearQuestionError(formIndex)"
                >
              </div>

              <div
                v-if="form.type === 'multiple'"
                class="assessment-form-card__field-group"
              >
                <label class="assessment-form-card__label">الخيارات</label>
                <div class="assessment-options-grid">
                  <div
                    v-for="(option, optionIndex) in form.options"
                    :key="`task-option-${formIndex}-${optionIndex}`"
                    class="assessment-options-grid__item"
                  >
                    <input
                      :value="option"
                      type="text"
                      class="assessment-input"
                      :placeholder="`الخيار ${optionIndex + 1}`"
                      @input="handleOptionChange(formIndex, optionIndex, $event.target.value)"
                      @paste="handleOptionPaste(formIndex, optionIndex, $event)"
                    >
                    <AppIconButton
                      v-if="optionIndex === form.options.length - 1"
                      variant="primary"
                      class="assessment-options-grid__append"
                      @click="handleAddOptionField(formIndex)"
                    >
                      <v-icon small>
                        mdi-plus
                      </v-icon>
                    </AppIconButton>
                  </div>
                </div>
              </div>

              <div
                v-if="form.type === 'truefalse'"
                class="assessment-form-card__field-group"
              >
                <label class="assessment-form-card__label">الخيارات</label>
                <div class="assessment-static-options">
                  <div class="assessment-static-options__item">
                    صح
                  </div>
                  <div class="assessment-static-options__item">
                    خطأ
                  </div>
                </div>
              </div>

              <div class="assessment-form-card__triple-grid">
                <div class="assessment-form-card__field-group assessment-form-card__field-group--compact">
                  <label class="assessment-form-card__label">الدرجة</label>
                  <input
                    :value="form.points"
                    type="number"
                    min="0"
                    class="assessment-input"
                    placeholder="1"
                    @input="updateQuestionForm(formIndex, { points: $event.target.value })"
                  >
                </div>

                <div class="assessment-form-card__field-group assessment-form-card__field-group--compact">
                  <label class="assessment-form-card__label">الإجابة الصحيحة</label>

                  <AppNativeSelect
                    v-if="form.type === 'multiple'"
                    :value="form.correctAnswer"
                    class="assessment-select"
                    @change="updateQuestionForm(formIndex, { correctAnswer: $event.target.value })"
                  >
                    <option value="">
                      اختر
                    </option>
                    <option
                      v-for="answer in availableAnswers(form)"
                      :key="`${answer}-${formIndex}`"
                      :value="answer"
                    >
                      {{ answer }}
                    </option>
                  </AppNativeSelect>

                  <AppNativeSelect
                    v-else-if="form.type === 'truefalse'"
                    :value="form.correctAnswer || 'صح'"
                    class="assessment-select"
                    @change="updateQuestionForm(formIndex, { correctAnswer: $event.target.value })"
                  >
                    <option value="صح">
                      صح
                    </option>
                    <option value="خطأ">
                      خطأ
                    </option>
                  </AppNativeSelect>

                  <input
                    v-else
                    :value="form.correctAnswer"
                    type="text"
                    class="assessment-input"
                    placeholder="اكتب الإجابة"
                    @input="updateQuestionForm(formIndex, { correctAnswer: $event.target.value })"
                  >
                </div>

                <div class="assessment-form-card__field-group assessment-form-card__field-group--compact">
                  <label class="assessment-form-card__label">إرفاق ملف</label>
                  <AppNativeSelect
                    :value="form.allowFile"
                    class="assessment-select"
                    @change="updateQuestionForm(formIndex, { allowFile: $event.target.value })"
                  >
                    <option value="yes">
                      يسمح
                    </option>
                    <option value="no">
                      لا يسمح
                    </option>
                  </AppNativeSelect>
                </div>
              </div>

              <p
                v-if="questionErrors[formIndex]"
                class="assessment-form-card__error"
              >
                {{ questionErrors[formIndex] }}
              </p>
            </article>

            <button
              type="button"
              class="assessment-dialog__add-slot"
              @click="handleAddQuestionSlot"
            >
              إضافة سؤال
            </button>
          </AppDialogBody>

          <AppDialogFooter class="assessment-dialog__footer">
            <AppButton
              variant="secondary"
              @click="handleCreateQuestionDialogChange(false)"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              @click="handleAddDraftQuestion"
            >
              حفظ {{ questionForms.length > 1 ? `(${questionForms.length} أسئلة)` : '' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="saveTemplateDialogOpen"
        max-width="620"
      >
        <div class="tasks-dialog tasks-dialog--compact">
          <AppDialogHeader title="حفظ القالب" />

          <AppDialogBody class="tasks-dialog__body">
            <p class="tasks-dialog__text">
              هل تريد حفظ القالب؟
            </p>
          </AppDialogBody>

          <AppDialogFooter class="tasks-dialog__footer">
            <AppButton
              variant="secondary"
              :disabled="createSubmitting"
              @click="confirmDocumentSave(false)"
            >
              لا
            </AppButton>
            <AppButton
              variant="primary"
              :disabled="createSubmitting"
              @click="confirmDocumentSave(true)"
            >
              نعم
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="renameDialogOpen"
        max-width="620"
      >
        <div class="tasks-dialog tasks-dialog--compact">
          <AppDialogHeader title="تعديل اسم المهمة" />

          <AppDialogBody class="tasks-dialog__body">
            <input
              v-model="renameTitle"
              type="text"
              class="tasks-input"
              placeholder="اسم المهمة"
            >
          </AppDialogBody>

          <AppDialogFooter class="tasks-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeRenameDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="renameSubmitting"
              @click="submitRename"
            >
              {{ renameSubmitting ? 'جارٍ الحفظ...' : 'حفظ' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="deleteDialogOpen"
        max-width="620"
      >
        <div class="tasks-dialog tasks-dialog--compact">
          <AppDialogHeader title="تأكيد حذف المهمة" />

          <AppDialogBody class="tasks-dialog__body">
            <p class="tasks-dialog__text">
              هل أنت متأكد من حذف المهمة
              <strong>{{ deleteTitle || 'المحددة' }}</strong>
              ؟
            </p>
          </AppDialogBody>

          <AppDialogFooter class="tasks-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeDeleteDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="danger"
              :loading="deleteSubmitting"
              @click="confirmDelete"
            >
              {{ deleteSubmitting ? 'جارٍ الحذف...' : 'حذف' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="availabilityDialogOpen"
        max-width="560"
      >
        <div class="tasks-dialog tasks-dialog--compact">
          <AppDialogHeader title="فتح المهمة الأدائية" />

          <AppDialogBody class="tasks-dialog__body">
            <div
              v-if="!managedBranchId"
              class="tasks-field mb-4"
            >
              <label class="tasks-field__label">الفرع</label>
              <AppNativeSelect
                v-model="availabilityBranch"
                class="tasks-select"
              >
                <option value="all">
                  الكل
                </option>
                <option value="male">
                  معلمين
                </option>
                <option value="female">
                  معلمات
                </option>
              </AppNativeSelect>
            </div>

            <div class="tasks-field">
              <label class="tasks-field__label">مدة الفتح بالدقائق</label>
              <input
                v-model.number="availabilityMinutes"
                type="number"
                min="1"
                class="tasks-input"
                placeholder="60"
              >
            </div>

            <div
              v-if="availabilityError"
              class="tasks-dialog__error mt-4"
            >
              {{ availabilityError }}
            </div>
          </AppDialogBody>

          <AppDialogFooter class="tasks-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeAvailabilityDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="availabilitySubmitting"
              @click="confirmTaskAvailability()"
            >
              {{ availabilitySubmitting ? 'جارٍ الفتح...' : 'فتح' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="manageDialogOpen"
        max-width="560"
      >
        <div class="tasks-dialog tasks-dialog--compact">
          <AppDialogHeader title="إدارة حالة المهمة الأدائية" />

          <AppDialogBody class="tasks-dialog__body">
            <div class="tasks-field">
              <label class="tasks-field__label">الإجراء</label>
              <AppNativeSelect
                v-model="manageChoice"
                class="tasks-select"
              >
                <option
                  v-for="option in currentManageOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </AppNativeSelect>
            </div>
          </AppDialogBody>

          <AppDialogFooter class="tasks-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeManageDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="manageSubmitting"
              :disabled="!manageChoice || manageSubmitting"
              @click="confirmManageAction"
            >
              {{ manageSubmitting ? 'جارٍ التنفيذ...' : 'متابعة' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="branchConflictDialogOpen"
        max-width="620"
      >
        <div class="tasks-dialog tasks-dialog--compact">
          <AppDialogHeader title="تنبيه: فرع نشط" />

          <AppDialogBody class="tasks-dialog__body">
            <p
              v-if="branchConflict.pendingBranch && branchConflict.activeBranch"
              class="tasks-dialog__text"
            >
              الفرع
              <strong>{{ branchLabel(branchConflict.activeBranch) }}</strong>
              مفتوح حاليًا. هل تريد أيضًا تفعيل الفرع
              <strong>{{ branchLabel(branchConflict.pendingBranch) }}</strong>
              ؟
            </p>
          </AppDialogBody>

          <AppDialogFooter class="tasks-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeBranchConflictDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              @click="confirmTaskAvailability(true)"
            >
              متابعة
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>
    </v-container>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import AdminAssessmentView from './AdminAssessmentView.vue';
import {
  AppButton, AppChoiceButton, AppDialog, AppDialogBody, AppDialogFooter,
  AppDialogHeader, AppIconButton, AppNativeSelect,
} from '../components/ui';
import { hasMeaningfulDocumentContent } from '../utils/documentContent';

const branchLabels = {
  male: 'معلمين',
  female: 'معلمات',
};

const QUESTION_LINE_PATTERN = /[؟?؛:.]\s*$/;
const NUMBER_TOKEN = '0-9\u0660-\u0669\u06F0-\u06F9';
const OPTION_LETTER_TOKEN = 'A-Da-d\u0623\u0628\u062C\u062F\u0627';
const QUESTION_START_PATTERN = new RegExp(`^\\s*[${NUMBER_TOKEN}]{1,3}\\s*[).:؛/-]?\\s+`);
const QUESTION_END_NUMBER_PATTERN = new RegExp(`\\s*[).:؛/-]?\\s*[${NUMBER_TOKEN}]{1,3}\\s*$`);
const LEADING_LIST_MARKER_PATTERN = new RegExp(`^\\s*(?:\\(?[${NUMBER_TOKEN}]{1,3}\\)?\\s*[-–—.)(:/؛]\\s*|\\(?[${NUMBER_TOKEN}]{1,3}\\)?\\s+|(?:\\([${OPTION_LETTER_TOKEN}]\\)|[${OPTION_LETTER_TOKEN}]\\s*[-–—.)(:/؛])\\s*)`);
const OPTION_MARKER_PATTERN = new RegExp(`^\\s*(?:[-*•●▪◦]|\\(?[${NUMBER_TOKEN}]{1,3}\\)?\\s*[)(.:؛/-]|\\([${OPTION_LETTER_TOKEN}]\\)|[${OPTION_LETTER_TOKEN}]\\s*[)(.:؛/-])\\s*`);
const TRAILING_OPTION_MARKER_PATTERN = new RegExp(`\\s*(?:\\([${OPTION_LETTER_TOKEN}]\\)|\\(?[${NUMBER_TOKEN}]{1,3}\\)?\\s*[)(.:؛/-])\\s*$`);
const OPTION_MARKER_ANYWHERE_PATTERN = new RegExp(`(?:\\([${OPTION_LETTER_TOKEN}]\\)|[${OPTION_LETTER_TOKEN}]\\s*[)(.:؛/-]|\\(?[${NUMBER_TOKEN}]{1,3}\\)?\\s*[)(.:؛/-])`);
const INLINE_OPTION_SPLIT_PATTERN = new RegExp(`\\s+(?=(?:\\([${OPTION_LETTER_TOKEN}]\\)|[${OPTION_LETTER_TOKEN}]\\s*[)(.:؛/-]|\\(?[${NUMBER_TOKEN}]{1,3}\\)?\\s*[)(.:؛/-]))`, 'g');
const ANSWER_LINE_PATTERN = /^(?:الإجابة(?:\s+الصحيحة)?|الجواب(?:\s+الصحيح)?|answer|correct\s*answer|solution|الدرجة|التعليل|التفسير)\s*[:：-]/i;
const INSTRUCTION_LINE_PATTERN = /^(?:التعليمات|إرشادات|ملاحظات|instructions?)\s*[:：-]/i;
const NOISE_LINE_PATTERN = /^(?:page\s*\d+|\d+\s*\/\s*\d+|\d+)$/i;
const NUMBERED_MARKER_PATTERN = new RegExp(`^\\s*\\(?[${NUMBER_TOKEN}]{1,3}\\)?\\s*[)(.:؛/-]`);

const emptyTaskDraft = () => ({
  title: '',
  points: 0,
  youtubeUrl: '',
  content: '',
});

const emptyQuestionForm = () => ({
  prompt: '',
  type: 'multiple',
  options: ['', ''],
  allowFile: 'no',
  points: '1',
  correctAnswer: '',
});

const normalizeLine = (value) => String(value || '')
  .replace(/\u00a0/g, ' ')
  .replace(/\s+/g, ' ')
  .replace(/\s+([؟?؛:.,])/g, '$1')
  .replace(/([.،؛:?؟])\1+/g, '$1')
  .replace(/^[-–—•●▪◦.،؛:]+\s*/, '')
  .trim();

const stripLeadingMarker = (value) => normalizeLine(String(value || '').replace(LEADING_LIST_MARKER_PATTERN, ''));
const stripTrailingQuestionNumber = (value) => normalizeLine(String(value || '').replace(QUESTION_END_NUMBER_PATTERN, ''));
const stripOptionMarker = (value) => normalizeLine(String(value || '').replace(OPTION_MARKER_PATTERN, '').replace(TRAILING_OPTION_MARKER_PATTERN, ''));
const isQuestionLine = (value) => QUESTION_LINE_PATTERN.test(normalizeLine(value));
const isQuestionStartLine = (value) => {
  const normalized = normalizeLine(value);
  if (!normalized) {
    return false;
  }

  return QUESTION_START_PATTERN.test(normalized) || QUESTION_END_NUMBER_PATTERN.test(normalized);
};
const isOptionLine = (value) => {
  const normalized = normalizeLine(value);
  if (!normalized) {
    return false;
  }

  return OPTION_MARKER_PATTERN.test(normalized) || TRAILING_OPTION_MARKER_PATTERN.test(normalized);
};
const shouldIgnoreLine = (value) => {
  const normalized = normalizeLine(value);
  return !normalized || NOISE_LINE_PATTERN.test(normalized) || ANSWER_LINE_PATTERN.test(normalized) || INSTRUCTION_LINE_PATTERN.test(normalized);
};
const isExplicitQuestionBoundary = (lines, index) => {
  const line = lines[index];

  if (!isQuestionStartLine(line)) {
    return false;
  }

  const normalizedPrompt = stripTrailingQuestionNumber(stripLeadingMarker(line));

  if (isQuestionLine(normalizedPrompt)) {
    return true;
  }

  if (!NUMBERED_MARKER_PATTERN.test(normalizeLine(line))) {
    return false;
  }

  for (let nextIndex = index + 1; nextIndex < lines.length; nextIndex += 1) {
    const nextLine = lines[nextIndex];

    if (shouldIgnoreLine(nextLine)) {
      continue;
    }

    return isOptionLine(nextLine) && !NUMBERED_MARKER_PATTERN.test(normalizeLine(nextLine));
  }

  return true;
};
const splitInlineOptions = (value) => {
  const normalized = normalizeLine(value);
  const firstMarkerIndex = normalized.search(OPTION_MARKER_ANYWHERE_PATTERN);

  if (firstMarkerIndex <= 0) {
    return { prompt: normalized, options: [] };
  }

  const prompt = normalizeLine(normalized.slice(0, firstMarkerIndex));
  const inlineOptionsSource = normalizeLine(normalized.slice(firstMarkerIndex));

  if (!prompt || !inlineOptionsSource) {
    return { prompt: normalized, options: [] };
  }

  const options = inlineOptionsSource
    .split(INLINE_OPTION_SPLIT_PATTERN)
    .map(stripOptionMarker)
    .filter(Boolean);

  if (options.length < 2) {
    return { prompt: normalized, options: [] };
  }

  return { prompt, options };
};
const parseImportedQuestionsFromText = (text) => {
  const rawLines = String(text || '')
    .replace(/\r\n?/g, '\n')
    .split('\n')
    .map(normalizeLine);

  const importedQuestions = [];
  let index = 0;

  while (index < rawLines.length) {
    const line = rawLines[index];

    if (shouldIgnoreLine(line)) {
      index += 1;
      continue;
    }

    if (!isQuestionStartLine(line) && !isQuestionLine(line)) {
      index += 1;
      continue;
    }

    const questionParts = [stripTrailingQuestionNumber(stripLeadingMarker(line))];
    let cursor = index + 1;

    if (!isQuestionLine(questionParts[0])) {
      while (cursor < rawLines.length) {
        const nextLine = rawLines[cursor];

        if (!nextLine) {
          cursor += 1;
          continue;
        }

        if (isExplicitQuestionBoundary(rawLines, cursor) || isOptionLine(nextLine)) {
          break;
        }

        questionParts.push(stripTrailingQuestionNumber(nextLine));
        cursor += 1;

        if (isQuestionLine(nextLine)) {
          break;
        }
      }
    }

    const prompt = questionParts.join(' ').trim();
    const inlineSplit = splitInlineOptions(prompt);
    const resolvedPrompt = inlineSplit.prompt;

    if (!resolvedPrompt) {
      index += 1;
      continue;
    }

    const markedOptions = [...inlineSplit.options];

    while (cursor < rawLines.length) {
      const candidate = rawLines[cursor];

      if (!candidate) {
        cursor += 1;
        continue;
      }

      if (shouldIgnoreLine(candidate)) {
        cursor += 1;
        continue;
      }

      if (isExplicitQuestionBoundary(rawLines, cursor)) {
        break;
      }

      if (isOptionLine(candidate)) {
        markedOptions.push(stripOptionMarker(candidate));
        cursor += 1;
        continue;
      }

      if (isQuestionStartLine(candidate) || isQuestionLine(candidate) || markedOptions.length > 0) {
        break;
      }

      cursor += 1;
    }

    if (!isQuestionLine(resolvedPrompt) && markedOptions.length === 0) {
      index += 1;
      continue;
    }

    importedQuestions.push({
      prompt: resolvedPrompt,
      type: markedOptions.length >= 2 ? 'multiple' : 'text',
      options: markedOptions.filter((option, optionIndex, collection) => collection.findIndex((candidate) => candidate === option) === optionIndex),
    });

    index = cursor;
  }

  return importedQuestions.filter((question, questionIndex, collection) => collection.findIndex((candidate) => candidate.prompt === question.prompt) === questionIndex);
};

const splitPastedQuestionOptions = (value) => {
  const normalizedValue = String(value || '').replace(/\r\n?/g, '\n').trim();

  if (!normalizedValue) {
    return [];
  }

  const markerPattern = /(^|[\s\n])(?:[A-Za-z\u0621-\u064A]|\d{1,2})\s*[-–—.):]/gm;
  const markers = [];
  let match;

  while ((match = markerPattern.exec(normalizedValue)) !== null) {
    markers.push({ labelStart: match.index + match[1].length });
  }

  if (markers.length < 2) {
    return [];
  }

  return markers
    .map((marker, markerIndex) => {
      const nextMarkerStart = markers[markerIndex + 1]?.labelStart ?? normalizedValue.length;
      return String(normalizedValue.slice(marker.labelStart, nextMarkerStart) || '')
        .trim()
        .replace(/^(?:[A-Za-z\u0621-\u064A]|\d{1,2})\s*[-–—.):]\s*/, '')
        .trim();
    })
    .filter(Boolean);
};

const arrayMove = (items, fromIndex, toIndex) => {
  const clone = [...items];
  const [moved] = clone.splice(fromIndex, 1);
  clone.splice(toIndex, 0, moved);
  return clone;
};

export default {
  name: 'AdminTasksView',
  components: {
    AdminAssessmentView,
    AppDialog,
    AppButton,
    AppChoiceButton,
    AppIconButton,
    AppNativeSelect,
    AppDialogHeader,
    AppDialogBody,
    AppDialogFooter,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      selectedTaskId: '',
      createDialogOpen: false,
      createMode: 'questions',
      createSubmitting: false,
      createError: '',
      taskDraft: emptyTaskDraft(),
      selectedTemplateId: '',
      draftQuestions: [],
      questionDialogOpen: false,
      questionForms: [emptyQuestionForm()],
      questionErrors: [''],
      pasteText: '',
      saveTemplateDialogOpen: false,
      renameDialogOpen: false,
      renameTaskId: '',
      renameTitle: '',
      renameSubmitting: false,
      deleteDialogOpen: false,
      deletingTaskId: '',
      deleteTitle: '',
      deleteSubmitting: false,
      dragTaskId: '',
      availabilityDialogOpen: false,
      availabilityTaskId: '',
      availabilityMinutes: 60,
      availabilityBranch: 'all',
      availabilitySubmitting: false,
      availabilityError: '',
      taskSkipBranchConflict: false,
      manageDialogOpen: false,
      manageTaskId: '',
      manageChoice: '',
      manageSubmitting: false,
      branchConflictDialogOpen: false,
      branchConflict: {
        activeBranch: '',
        pendingBranch: '',
      },
      currentTimestamp: Date.now(),
      taskCountdownTimer: null,
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'dashboardError', 'currentUser']),
    tasks() {
      return [...(this.dashboardSnapshot?.courses || [])]
        .filter((course) => course.entityType === 'task')
        .sort((left, right) => Number(left.sortOrder || 0) - Number(right.sortOrder || 0));
    },
    selectedTask() {
      return this.tasks.find((task) => task.id === this.selectedTaskId) || null;
    },
    taskTemplates() {
      return this.dashboardSnapshot?.taskTemplates || [];
    },
    selectedTemplate() {
      return this.taskTemplates.find((template) => template.id === this.selectedTemplateId) || null;
    },
    canEdit() {
      return this.currentUser?.role === 'admin';
    },
    managedBranchId() {
      if (this.currentUser?.role === 'male_manager') {
        return 'male';
      }

      if (this.currentUser?.role === 'female_manager') {
        return 'female';
      }

      return '';
    },
    managedBranchLabel() {
      return this.managedBranchId ? branchLabels[this.managedBranchId] : '';
    },
    canManage() {
      return this.canEdit || Boolean(this.managedBranchId);
    },
    activeTasksCount() {
      return this.tasks.filter((task) => this.isTaskActive(task)).length;
    },
    questionTasksCount() {
      return this.tasks.filter((task) => task.taskMode !== 'document').length;
    },
    documentTasksCount() {
      return this.tasks.filter((task) => task.taskMode === 'document').length;
    },
    currentManageTask() {
      return this.tasks.find((task) => task.id === this.manageTaskId) || null;
    },
    currentManageOptions() {
      if (!this.currentManageTask) {
        return [];
      }

      return this.getTaskManageOptions(this.currentManageTask);
    },
  },
  created() {
    this.taskCountdownTimer = window.setInterval(() => {
      this.currentTimestamp = Date.now();
    }, 1000);

    this.loadDashboardSnapshot();
  },
  beforeDestroy() {
    if (this.taskCountdownTimer) {
      window.clearInterval(this.taskCountdownTimer);
      this.taskCountdownTimer = null;
    }
  },
  methods: {
    ...mapActions([
      'loadDashboardSnapshot',
      'addCourse',
      'addTaskTemplate',
      'updateCourse',
      'updateTaskTemplate',
      'deleteCourse',
      'addQuestion',
      'reorderCourses',
    ]),
    branchLabel(branchId) {
      return branchLabels[branchId] || '';
    },
    getWindowMeta(value) {
      if (!value) {
        return { closesAt: '', durationMinutes: 0 };
      }

      if (typeof value === 'string') {
        return { closesAt: value, durationMinutes: 0 };
      }

      return {
        closesAt: value.closesAt || '',
        durationMinutes: Number(value.durationMinutes) || 0,
      };
    },
    isTaskBranchActive(task, branchId) {
      if (!task || !branchId) {
        return false;
      }

      const branchEnabled = task.branchAvailability?.[branchId]?.tasks !== false;
      const branchWindow = this.getWindowMeta(task.assessmentWindows?.[branchId]?.tasks).closesAt;

      return Boolean(task.isTasksEnabled && branchEnabled && branchWindow);
    },
    isTaskActive(task, branchId = '') {
      const targetBranch = branchId || this.managedBranchId;

      if (targetBranch) {
        return this.isTaskBranchActive(task, targetBranch);
      }

      return this.isTaskBranchActive(task, 'male') || this.isTaskBranchActive(task, 'female');
    },
    taskStatusLabel(task) {
      const maleActive = this.isTaskBranchActive(task, 'male');
      const femaleActive = this.isTaskBranchActive(task, 'female');

      if (this.managedBranchId) {
        return maleActive || femaleActive ? `مفتوحة لفرع ${this.managedBranchLabel}` : 'مغلقة';
      }

      if (maleActive && femaleActive) {
        return 'مفتوحة للكل';
      }

      if (maleActive) {
        return 'مفتوحة للمعلمين';
      }

      if (femaleActive) {
        return 'مفتوحة للمعلمات';
      }

      return 'مغلقة';
    },
    taskDeadlineLabel(task) {
      if (this.managedBranchId) {
        return this.formatDateTime(this.getWindowMeta(task.assessmentWindows?.[this.managedBranchId]?.tasks).closesAt);
      }

      const globalDeadline = this.getWindowMeta(task.assessmentWindows?.global?.tasks).closesAt;
      if (globalDeadline) {
        return this.formatDateTime(globalDeadline);
      }

      const maleDeadline = this.getWindowMeta(task.assessmentWindows?.male?.tasks).closesAt;
      const femaleDeadline = this.getWindowMeta(task.assessmentWindows?.female?.tasks).closesAt;
      const fallback = maleDeadline || femaleDeadline;

      return this.formatDateTime(fallback);
    },
    taskActionLabel(task) {
      if (!this.isTaskActive(task)) {
        return 'بدء';
      }

      const maleActive = this.isTaskBranchActive(task, 'male');
      const femaleActive = this.isTaskBranchActive(task, 'female');

      if (this.managedBranchId) {
        return this.formatCountdown(this.getWindowMeta(task.assessmentWindows?.[this.managedBranchId]?.tasks).closesAt);
      }

      if (maleActive && femaleActive) {
        return this.formatCountdown(this.getWindowMeta(task.assessmentWindows?.global?.tasks).closesAt);
      }

      const branchId = maleActive ? 'male' : 'female';
      return `${this.branchLabel(branchId)} ${this.formatCountdown(this.getWindowMeta(task.assessmentWindows?.[branchId]?.tasks).closesAt)}`;
    },
    formatDateTime(value) {
      if (!value) {
        return '';
      }

      const parsed = new Date(value);
      if (Number.isNaN(parsed.getTime())) {
        return '';
      }

      return new Intl.DateTimeFormat('ar-SA', {
        hour: 'numeric',
        minute: '2-digit',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
      }).format(parsed);
    },
    formatCountdown(value) {
      const parsed = new Date(value).getTime();

      if (!Number.isFinite(parsed) || parsed <= this.currentTimestamp) {
        return '00:00';
      }

      const totalSeconds = Math.max(0, Math.floor((parsed - this.currentTimestamp) / 1000));
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;

      if (hours > 0) {
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
      }

      return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    },
    openTask(taskId) {
      this.selectedTaskId = taskId;
    },
    closeTaskEditor() {
      this.selectedTaskId = '';
    },
    handleTaskDragStart(taskId, event) {
      if (!this.canEdit) {
        return;
      }

      this.dragTaskId = taskId;
      if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', taskId);
      }
    },
    handleTaskDragOver(taskId) {
      if (!this.canEdit || !this.dragTaskId || this.dragTaskId === taskId) {
        return;
      }
    },
    async handleTaskDrop(targetTaskId) {
      if (!this.canEdit || !this.dragTaskId || this.dragTaskId === targetTaskId) {
        this.dragTaskId = '';
        return;
      }

      const ids = this.tasks.map((task) => task.id);
      const fromIndex = ids.indexOf(this.dragTaskId);
      const toIndex = ids.indexOf(targetTaskId);

      this.dragTaskId = '';

      if (fromIndex === -1 || toIndex === -1) {
        return;
      }

      try {
        await this.reorderCourses(arrayMove(ids, fromIndex, toIndex));
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ ترتيب المهام');
      }
    },
    handleTaskDragEnd() {
      this.dragTaskId = '';
    },
    handleCurrentAssessmentAvailabilityAction() {
      this.$refs.assessmentPanel?.handleCurrentAssessmentAvailabilityAction?.();
    },
    openCreateDialog(mode) {
      this.createMode = mode;
      this.taskDraft = emptyTaskDraft();
      this.selectedTemplateId = '';
      this.draftQuestions = [];
      this.createError = '';
      this.createSubmitting = false;
      this.createDialogOpen = true;
      this.handleCreateQuestionDialogChange(false);
    },
    closeCreateDialog() {
      this.createDialogOpen = false;
      this.taskDraft = emptyTaskDraft();
      this.selectedTemplateId = '';
      this.draftQuestions = [];
      this.questionDialogOpen = false;
      this.questionForms = [emptyQuestionForm()];
      this.questionErrors = [''];
      this.pasteText = '';
      this.saveTemplateDialogOpen = false;
      this.createError = '';
      this.createSubmitting = false;
    },
    clearSelectedTemplate() {
      this.selectedTemplateId = '';
      this.taskDraft.content = '';
    },
    selectTaskTemplate(template) {
      this.selectedTemplateId = template.id;
      this.taskDraft.content = String(template.content || '');
    },
    async submitTaskCreate() {
      const title = String(this.taskDraft.title || '').trim();
      const content = String(this.taskDraft.content || '').trim();

      if (!title) {
        this.createError = 'أدخل اسم المهمة الأدائية.';
        return;
      }

      if (this.createMode === 'document' && !hasMeaningfulDocumentContent(content)) {
        this.createError = 'أدخل محتوى المهمة الأدائية.';
        return;
      }

      if (this.createMode === 'questions' && this.draftQuestions.length === 0) {
        this.createError = 'أضف سؤالًا واحدًا على الأقل قبل حفظ المهمة الأدائية.';
        return;
      }

      this.createError = '';

      if (this.createMode === 'document') {
        this.saveTemplateDialogOpen = true;
        return;
      }

      await this.finalizeTaskCreate(false);
    },
    closeSaveTemplateDialog() {
      if (this.createSubmitting) {
        return;
      }

      this.saveTemplateDialogOpen = false;
    },
    async confirmDocumentSave(shouldSaveTemplate) {
      await this.finalizeTaskCreate(shouldSaveTemplate);
    },
    async finalizeTaskCreate(shouldSaveTemplate) {
      const title = String(this.taskDraft.title || '').trim();
      const content = String(this.taskDraft.content || '').trim();

      this.createSubmitting = true;
      this.createError = '';

      try {
        let taskTemplateId = '';
        let taskTemplateName = '';

        if (this.createMode === 'document' && shouldSaveTemplate) {
          if (this.selectedTemplate) {
            taskTemplateId = this.selectedTemplate.id;
            taskTemplateName = this.selectedTemplate.name;
            await this.updateTaskTemplate({
              templateId: taskTemplateId,
              updates: {
                content,
              },
            });
          } else {
            const createdTemplate = await this.addTaskTemplate({
              name: title,
              content,
            });
            taskTemplateId = createdTemplate.id;
            taskTemplateName = createdTemplate.name;
            this.selectedTemplateId = createdTemplate.id;
          }
        }

        const created = await this.addCourse({
          title,
          isActive: false,
          entityType: 'task',
          taskMode: this.createMode,
          taskTemplateId,
          taskTemplateName,
          taskTemplateContent: this.createMode === 'document' ? content : '',
          youtubeUrl: this.createMode === 'document' ? String(this.taskDraft.youtubeUrl || '').trim() : '',
        });

        if (this.createMode === 'document') {
          await this.addQuestion({
            courseId: created.id,
            question: {
              assessmentType: 'tasks',
              prompt: 'إرفاق ملف المهمة الأدائية',
              type: 'text',
              options: [],
              allowFile: true,
              points: Number(this.taskDraft.points) || 0,
              correctAnswer: '',
            },
          });
        } else {
          for (const question of this.draftQuestions) {
            await this.addQuestion({
              courseId: created.id,
              question: {
                assessmentType: 'tasks',
                ...question,
              },
            });
          }
        }

        this.$toast.success('تم إنشاء المهمة الأدائية');
        this.closeCreateDialog();
        this.selectedTaskId = created.id;
      } catch (error) {
        this.createError = error?.response?.data?.message || 'تعذر إنشاء المهمة الأدائية';
      } finally {
        this.saveTemplateDialogOpen = false;
        this.createSubmitting = false;
      }
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
    },
    clearQuestionError(index) {
      this.questionErrors = this.questionErrors.map((error, errorIndex) => (errorIndex === index ? '' : error));
    },
    availableAnswers(form) {
      return form.options.map((option) => option.trim()).filter(Boolean);
    },
    handleQuestionTypeChange(formIndex, type) {
      this.questionForms = this.questionForms.map((form, index) => {
        if (index !== formIndex) {
          return form;
        }

        return {
          ...form,
          type,
          options: type === 'multiple' ? (form.options.length > 1 ? form.options : ['', '']) : (type === 'truefalse' ? ['صح', 'خطأ'] : ['', '']),
          points: type === 'truefalse' ? '1' : form.points,
          correctAnswer: type === 'multiple' ? form.correctAnswer : (type === 'truefalse' ? (['صح', 'خطأ'].includes(form.correctAnswer) ? form.correctAnswer : 'صح') : ''),
        };
      });
      this.clearQuestionError(formIndex);
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
    handleAddOptionField(formIndex) {
      this.questionForms = this.questionForms.map((form, index) => (index === formIndex ? { ...form, options: [...form.options, ''] } : form));
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
      const effectiveType = preferredType === 'multiple'
        ? 'multiple'
        : (preferredType === 'truefalse' && draft.type === 'text' ? 'truefalse' : draft.type);

      return {
        ...emptyQuestionForm(),
        prompt: draft.prompt,
        type: effectiveType,
        options: effectiveType === 'truefalse' ? ['صح', 'خطأ'] : (draft.options.length >= 2 ? draft.options : ['', '']),
        points: String(defaultPoints),
        correctAnswer: effectiveType === 'truefalse' ? 'صح' : '',
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
    handleAddQuestionSlot() {
      this.questionForms = [...this.questionForms, emptyQuestionForm()];
      this.questionErrors = [...this.questionErrors, ''];
    },
    handleRemoveQuestionSlot(index) {
      if (this.questionForms.length <= 1) {
        return;
      }

      this.questionForms = this.questionForms.filter((_, currentIndex) => currentIndex !== index);
      this.questionErrors = this.questionErrors.filter((_, currentIndex) => currentIndex !== index);
    },
    handleAddDraftQuestion() {
      let hasError = false;
      const nextErrors = this.questionForms.map((form) => {
        const prompt = form.prompt.trim();
        const options = form.type === 'multiple'
          ? form.options.map((option) => option.trim()).filter(Boolean)
          : (form.type === 'truefalse' ? ['صح', 'خطأ'] : []);

        if (!prompt) {
          hasError = true;
          return 'أدخل السؤال.';
        }

        if (form.type === 'multiple' && options.length < 2) {
          hasError = true;
          return 'أدخل خيارين على الأقل.';
        }

        if ((form.type === 'multiple' || form.type === 'truefalse') && !form.correctAnswer.trim()) {
          hasError = true;
          return 'اختر الإجابة الصحيحة.';
        }

        const points = Number(form.points);

        if (!Number.isFinite(points) || points < 0) {
          hasError = true;
          return 'أدخل درجة صحيحة.';
        }

        return '';
      });

      if (hasError) {
        this.questionErrors = nextErrors;
        return;
      }

      const newDraftQuestions = this.questionForms.map((form) => {
        const options = form.type === 'multiple'
          ? form.options.map((option) => option.trim()).filter(Boolean)
          : (form.type === 'truefalse' ? ['صح', 'خطأ'] : []);

        return {
          prompt: form.prompt.trim(),
          type: form.type,
          options,
          allowFile: form.allowFile === 'yes',
          points: Number(form.points),
          correctAnswer: form.correctAnswer.trim(),
        };
      });

      this.draftQuestions = [...this.draftQuestions, ...newDraftQuestions];
      this.handleCreateQuestionDialogChange(false);
    },
    removeDraftQuestion(index) {
      this.draftQuestions = this.draftQuestions.filter((_, questionIndex) => questionIndex !== index);
    },
    openRenameDialog(task) {
      this.renameTaskId = task.id;
      this.renameTitle = task.title || '';
      this.renameSubmitting = false;
      this.renameDialogOpen = true;
    },
    closeRenameDialog() {
      this.renameDialogOpen = false;
      this.renameTaskId = '';
      this.renameTitle = '';
      this.renameSubmitting = false;
    },
    async submitRename() {
      if (!this.renameTaskId || !String(this.renameTitle || '').trim()) {
        return;
      }

      this.renameSubmitting = true;

      try {
        await this.updateCourse({
          courseId: this.renameTaskId,
          updates: {
            title: String(this.renameTitle || '').trim(),
          },
        });
        this.$toast.success('تم تعديل اسم المهمة');
        this.closeRenameDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تعديل اسم المهمة');
      } finally {
        this.renameSubmitting = false;
      }
    },
    openDeleteDialog(task) {
      this.deletingTaskId = task.id;
      this.deleteTitle = task.title || '';
      this.deleteSubmitting = false;
      this.deleteDialogOpen = true;
    },
    closeDeleteDialog() {
      this.deleteDialogOpen = false;
      this.deletingTaskId = '';
      this.deleteTitle = '';
      this.deleteSubmitting = false;
    },
    async confirmDelete() {
      if (!this.deletingTaskId) {
        return;
      }

      const taskId = this.deletingTaskId;
      this.deleteSubmitting = true;

      try {
        await this.deleteCourse(taskId);

        if (this.selectedTaskId === taskId) {
          this.selectedTaskId = '';
        }

        this.$toast.success('تم حذف المهمة الأدائية');
        this.closeDeleteDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حذف المهمة الأدائية');
      } finally {
        this.deleteSubmitting = false;
      }
    },
    handleTaskAvailabilityAction(task) {
      if (!this.canManage) {
        return;
      }

      if (!this.isTaskActive(task)) {
        this.openAvailabilityDialog(task.id, this.managedBranchId || 'all', false);
        return;
      }

      if (this.managedBranchId) {
        this.closeTaskAvailability(task.id, this.managedBranchId);
        return;
      }

      this.openManageDialog(task.id);
    },
    openAvailabilityDialog(taskId, branch = 'all', skipConflict = false) {
      const task = this.tasks.find((item) => item.id === taskId);
      const sourceWindow = branch === 'all'
        ? this.getWindowMeta(task?.assessmentWindows?.global?.tasks)
        : this.getWindowMeta(task?.assessmentWindows?.[branch]?.tasks);

      this.availabilityTaskId = taskId;
      this.availabilityBranch = this.managedBranchId || branch || 'all';
      this.availabilityMinutes = sourceWindow.durationMinutes > 0 ? sourceWindow.durationMinutes : 60;
      this.availabilityError = '';
      this.availabilitySubmitting = false;
      this.taskSkipBranchConflict = skipConflict;
      this.availabilityDialogOpen = true;
    },
    closeAvailabilityDialog() {
      this.availabilityDialogOpen = false;
      this.availabilityTaskId = '';
      this.availabilityMinutes = 60;
      this.availabilityBranch = this.managedBranchId || 'all';
      this.availabilitySubmitting = false;
      this.availabilityError = '';
      this.taskSkipBranchConflict = false;
    },
    async confirmTaskAvailability(skipBranchConflictCheck = false) {
      const task = this.tasks.find((item) => item.id === this.availabilityTaskId);
      const duration = Number(this.availabilityMinutes);
      const targetBranch = this.managedBranchId || this.availabilityBranch;
      const shouldBypassBranchConflict = skipBranchConflictCheck || this.taskSkipBranchConflict;

      if (!task || !Number.isFinite(duration) || duration <= 0) {
        this.availabilityError = 'أدخل مدة صحيحة بالدقائق.';
        return;
      }

      if (!targetBranch) {
        this.availabilityError = 'اختر فرعًا.';
        return;
      }

      if (targetBranch !== 'all') {
        const otherBranch = targetBranch === 'male' ? 'female' : 'male';
        const otherBranchActive = this.isTaskBranchActive(task, otherBranch);

        if (otherBranchActive && !shouldBypassBranchConflict && !this.managedBranchId) {
          this.branchConflict = {
            activeBranch: otherBranch,
            pendingBranch: targetBranch,
          };
          this.branchConflictDialogOpen = true;
          return;
        }
      }

      this.branchConflictDialogOpen = false;

      const targetBranches = targetBranch === 'all' ? ['male', 'female'] : [targetBranch];
      const otherBranches = ['male', 'female'].filter((branchId) => !targetBranches.includes(branchId));
      const closesAt = new Date(Date.now() + duration * 60 * 1000).toISOString();
      const existingGlobalClose = this.getWindowMeta(task.assessmentWindows?.global?.tasks).closesAt;
      const nextGlobalClose = !existingGlobalClose || new Date(existingGlobalClose) < new Date(closesAt)
        ? closesAt
        : existingGlobalClose;

      const branchAvailability = {
        male: { ...(task.branchAvailability?.male || {}) },
        female: { ...(task.branchAvailability?.female || {}) },
      };
      const assessmentWindows = {
        global: { ...(task.assessmentWindows?.global || {}), tasks: nextGlobalClose },
        male: { ...(task.assessmentWindows?.male || {}) },
        female: { ...(task.assessmentWindows?.female || {}) },
      };

      targetBranches.forEach((branchId) => {
        branchAvailability[branchId] = { ...branchAvailability[branchId], tasks: true };
        assessmentWindows[branchId] = { ...assessmentWindows[branchId], tasks: closesAt };
      });

      otherBranches.forEach((branchId) => {
        assessmentWindows[branchId] = {
          ...assessmentWindows[branchId],
          tasks: targetBranch === 'all'
            ? closesAt
            : (shouldBypassBranchConflict && this.isTaskBranchActive(task, branchId)
              ? assessmentWindows[branchId].tasks
              : undefined),
        };

        if (targetBranch === 'all') {
          branchAvailability[branchId] = { ...branchAvailability[branchId], tasks: true };
        } else if (!(shouldBypassBranchConflict && this.isTaskBranchActive(task, branchId))) {
          branchAvailability[branchId] = { ...branchAvailability[branchId], tasks: false };
        }
      });

      this.availabilitySubmitting = true;
      this.availabilityError = '';

      try {
        await this.updateCourse({
          courseId: task.id,
          updates: {
            isTasksEnabled: true,
            branchAvailability,
            assessmentWindows,
          },
        });

        const otherActiveTasks = this.tasks.filter((item) => item.id !== task.id && item.isTasksEnabled);
        await Promise.all(otherActiveTasks.map((item) => this.updateCourse({
          courseId: item.id,
          updates: {
            isTasksEnabled: false,
            assessmentWindows: {
              ...(item.assessmentWindows || {}),
              global: { ...(item.assessmentWindows?.global || {}), tasks: undefined },
              male: { ...(item.assessmentWindows?.male || {}), tasks: undefined },
              female: { ...(item.assessmentWindows?.female || {}), tasks: undefined },
            },
          },
        }).catch(() => undefined)));

        this.$toast.success('تم فتح المهمة الأدائية');
        this.closeAvailabilityDialog();
      } catch (error) {
        this.availabilityError = error?.response?.data?.message || 'تعذر فتح المهمة الأدائية';
      } finally {
        this.availabilitySubmitting = false;
      }
    },
    closeBranchConflictDialog() {
      this.branchConflictDialogOpen = false;
      this.branchConflict = {
        activeBranch: '',
        pendingBranch: '',
      };
    },
    async closeTaskAvailability(taskId, branchId = '') {
      const task = this.tasks.find((item) => item.id === taskId);
      if (!task) {
        return;
      }

      try {
        if (branchId) {
          const remainingBranch = branchId === 'male' ? 'female' : 'male';
          const remainingBranchActive = this.isTaskBranchActive(task, remainingBranch);

          await this.updateCourse({
            courseId: task.id,
            updates: {
              isTasksEnabled: remainingBranchActive,
              branchAvailability: {
                ...(task.branchAvailability || {}),
                [branchId]: {
                  ...(task.branchAvailability?.[branchId] || {}),
                  tasks: false,
                },
              },
              assessmentWindows: {
                ...(task.assessmentWindows || {}),
                global: {
                  ...(task.assessmentWindows?.global || {}),
                  tasks: remainingBranchActive ? this.getWindowMeta(task.assessmentWindows?.global?.tasks).closesAt : undefined,
                },
                [branchId]: {
                  ...(task.assessmentWindows?.[branchId] || {}),
                  tasks: undefined,
                },
              },
            },
          });
        } else {
          await this.updateCourse({
            courseId: task.id,
            updates: {
              isTasksEnabled: false,
              assessmentWindows: {
                ...(task.assessmentWindows || {}),
                global: { ...(task.assessmentWindows?.global || {}), tasks: undefined },
                male: { ...(task.assessmentWindows?.male || {}), tasks: undefined },
                female: { ...(task.assessmentWindows?.female || {}), tasks: undefined },
              },
            },
          });
        }

        this.$toast.success('تم تحديث حالة المهمة الأدائية');
        this.closeManageDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحديث حالة المهمة الأدائية');
      }
    },
    getTaskManageOptions(task) {
      const maleActive = this.isTaskBranchActive(task, 'male');
      const femaleActive = this.isTaskBranchActive(task, 'female');

      if (this.managedBranchId) {
        return maleActive || femaleActive
          ? [{ value: `close_${this.managedBranchId}`, label: `إغلاق ${this.branchLabel(this.managedBranchId)}` }]
          : [];
      }

      if (maleActive && femaleActive) {
        return [
          { value: 'close_all', label: 'إغلاق الكل' },
          { value: 'close_male', label: 'إغلاق معلمين' },
          { value: 'close_female', label: 'إغلاق معلمات' },
        ];
      }

      if (maleActive) {
        return [
          { value: 'close_male', label: 'إغلاق معلمين' },
          { value: 'open_female', label: 'بدء معلمات' },
          { value: 'open_all', label: 'بدء الكل' },
        ];
      }

      if (femaleActive) {
        return [
          { value: 'close_female', label: 'إغلاق معلمات' },
          { value: 'open_male', label: 'بدء معلمين' },
          { value: 'open_all', label: 'بدء الكل' },
        ];
      }

      return [
        { value: 'open_all', label: 'بدء الكل' },
        { value: 'open_male', label: 'بدء معلمين' },
        { value: 'open_female', label: 'بدء معلمات' },
      ];
    },
    openManageDialog(taskId) {
      const task = this.tasks.find((item) => item.id === taskId);
      if (!task) {
        return;
      }

      const options = this.getTaskManageOptions(task);
      this.manageTaskId = taskId;
      this.manageChoice = options[0]?.value || '';
      this.manageSubmitting = false;
      this.manageDialogOpen = true;
    },
    closeManageDialog() {
      this.manageDialogOpen = false;
      this.manageTaskId = '';
      this.manageChoice = '';
      this.manageSubmitting = false;
    },
    async confirmManageAction() {
      if (!this.manageTaskId || !this.manageChoice) {
        return;
      }

      if (this.manageChoice.startsWith('close_')) {
        this.manageSubmitting = true;
        try {
          const branch = this.manageChoice === 'close_all' ? '' : this.manageChoice.replace('close_', '');
          await this.closeTaskAvailability(this.manageTaskId, branch);
        } finally {
          this.manageSubmitting = false;
        }
        return;
      }

      const branch = this.manageChoice === 'open_all' ? 'all' : this.manageChoice.replace('open_', '');
      this.closeManageDialog();
      this.openAvailabilityDialog(this.manageTaskId, branch, true);
    },
  },
};
</script>

<style scoped>
.tasks-page {
  min-height: 100%;
  direction: rtl;
}

.tasks-page__container {
  max-width: 1220px;
}

.tasks-page--embedded .tasks-page__container {
  max-width: none;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.tasks-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 0;
}

.tasks-hero__title {
  margin: 0;
  font-size: 2rem;
  font-weight: 800;
  color: #08384a;
}

.tasks-hero__actions :deep(.v-icon) {
  color: #fff !important;
}

.tasks-menu {
  min-width: 200px;
  padding: 8px;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 22px 45px rgba(8, 56, 74, 0.16);
}

.tasks-menu__item {
  width: 100%;
  padding: 12px 14px;
  border: 0;
  border-radius: 14px;
  background: transparent;
  text-align: right;
  font-size: 0.95rem;
  font-weight: 700;
  color: #08384a;
}

.tasks-menu__item:hover {
  background: rgba(13, 90, 115, 0.08);
}

.tasks-create-shell {
  padding: 0;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.95);
  border: 1px solid rgba(255, 255, 255, 0.8);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
}

.tasks-create-shell__body {
  display: grid;
  gap: 18px;
  padding: 20px 24px 24px;
}

.tasks-create-shell__top-actions {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  margin-bottom: 4px;
}

.tasks-toolbar-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 48px;
  padding: 0 20px;
  border-radius: 999px;
  box-shadow: 0 8px 18px rgba(8, 65, 89, 0.06);
  transition: 0.18s ease;
  font-size: 0.96rem;
  font-weight: 900;
}

.tasks-toolbar-pill:hover {
  transform: translateY(-1px);
}

.tasks-add-question-button {
  border: 1px solid transparent;
  background: linear-gradient(180deg, #1e809b 0%, #146d88 100%);
  color: #fff;
}

.tasks-toolbar-pill--ghost {
  border: 1px solid #cfe3ea;
  background: #fff;
  color: #0b5873;
}

.tasks-create-shell__actions {
  display: flex;
  justify-content: flex-start;
}

.tasks-document-shell {
  border: 1px solid rgba(13, 90, 115, 0.12);
  border-radius: 24px;
  background: rgba(248, 251, 251, 0.9);
  padding: 14px;
}

.tasks-list-shell {
  padding: 24px;
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid rgba(13, 90, 115, 0.08);
  box-shadow: 0 20px 48px rgba(8, 56, 74, 0.06);
}

.tasks-list-shell__empty {
  padding: 28px 20px;
  border-radius: 20px;
  border: 1px dashed rgba(13, 90, 115, 0.22);
  text-align: center;
  color: rgba(8, 56, 74, 0.72);
}

.tasks-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 18px;
}

.task-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-height: 250px;
  padding: 24px;
  border-radius: 24px;
  background: #fff;
  border: 1px solid rgba(13, 90, 115, 0.08);
  box-shadow: 0 20px 48px rgba(8, 56, 74, 0.07);
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.task-card[draggable='true'] {
  cursor: grab;
}

.task-card--dragging {
  opacity: 0.55;
  transform: scale(0.985);
}

.task-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.task-card__copy {
  flex: 1;
  min-width: 0;
}

.task-card__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.task-card__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 0 14px;
  border-radius: 999px;
  background: rgba(13, 90, 115, 0.1);
  color: #0d5a73;
  font-size: 0.82rem;
  font-weight: 800;
}

.task-card__badge--document {
  background: rgba(196, 61, 61, 0.1);
  color: #9e2e2e;
}

.task-card__drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 12px;
  background: rgba(13, 90, 115, 0.08);
  color: #0d5a73;
}

.task-card__title {
  margin: 14px 0 10px;
  font-size: 1.2rem;
  font-weight: 800;
  line-height: 1.8;
  color: #08384a;
}

.task-card__meta,
.task-card__deadline {
  display: flex;
  align-items: center;
  gap: 8px;
  color: rgba(8, 56, 74, 0.65);
  font-size: 0.9rem;
}

.task-card__deadline {
  margin-top: 10px;
}

.task-card__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.task-card__icon-button {
  width: 36px;
  height: 36px;
}

.task-card__buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: auto;
}

.task-card__button {
  min-height: 46px;
}

.tasks-dialog {
  border-radius: 28px;
  background: #fff;
  overflow: hidden;
}

.tasks-dialog--compact {
  max-width: 100%;
}

.tasks-dialog__header {
  padding: 24px 28px 18px;
  border-bottom: 1px solid rgba(13, 90, 115, 0.08);
}

.tasks-dialog__title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #08384a;
}

.tasks-dialog__body {
  padding: 24px 28px;
}

.tasks-dialog__footer {
  display: flex;
  justify-content: flex-start;
  gap: 12px;
  padding: 0 28px 24px;
}

.tasks-dialog__text,
.tasks-dialog__error,
.tasks-inline-note {
  font-size: 0.95rem;
  line-height: 1.9;
  color: rgba(8, 56, 74, 0.8);
}

.tasks-inline-note--dashed {
  padding: 16px 18px;
  border-radius: 18px;
  border: 1px dashed rgba(13, 90, 115, 0.24);
  background: rgba(248, 251, 251, 0.76);
}

.tasks-dialog__error {
  color: #b33939;
}

.tasks-form-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 140px;
  gap: 14px;
}

.tasks-form-grid--question {
  grid-template-columns: 140px minmax(0, 1fr) 140px;
}

.tasks-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tasks-field__label {
  font-size: 0.92rem;
  font-weight: 700;
  color: #08384a;
}

.tasks-select {
  width: 100%;
}

.tasks-input,
.tasks-textarea {
  width: 100%;
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid rgba(13, 90, 115, 0.14);
  background: #fdfefe;
  color: #08384a;
  outline: none;
}

.tasks-input:focus,
.tasks-textarea:focus,
.tasks-document-shell:focus-within {
  border-color: rgba(13, 90, 115, 0.38);
  box-shadow: 0 0 0 4px rgba(13, 90, 115, 0.08);
}

.tasks-textarea {
  min-height: 220px;
  resize: vertical;
  line-height: 1.9;
}

.tasks-textarea--question {
  min-height: 120px;
}

.tasks-paste-shell {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.tasks-paste-textarea {
  flex: 1;
  min-height: 84px;
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid rgba(13, 90, 115, 0.14);
  background: #f8fbfb;
  resize: vertical;
  line-height: 1.9;
}

.tasks-secondary-button--small {
  min-height: 42px;
  padding: 0 14px;
}

.tasks-form-card {
  padding: 18px;
  border-radius: 22px;
  border: 1px solid rgba(13, 90, 115, 0.12);
  background: rgba(248, 251, 251, 0.9);
}

.tasks-form-card + .tasks-form-card {
  margin-top: 16px;
}

.tasks-form-card__topline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.tasks-form-card__index {
  font-size: 0.92rem;
  font-weight: 800;
  color: #0d5a73;
}

.tasks-form-card__remove {
  border: 0;
  background: transparent;
}

.tasks-type-switcher {
  display: flex;
  gap: 8px;
  margin-top: 16px;
  flex-wrap: wrap;
}

.tasks-type-switcher__item {
  min-height: 40px;
  padding: 0 16px;
  border-radius: 999px;
  border: 1px solid rgba(13, 90, 115, 0.14);
  background: #fff;
  color: #08384a;
  font-weight: 700;
}

.tasks-type-switcher__item--active {
  background: #0d5a73;
  color: #fff;
  border-color: #0d5a73;
}

.tasks-options-grid {
  display: grid;
  gap: 10px;
}

.tasks-options-grid--static {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.tasks-option-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tasks-option-add {
  width: 42px;
  height: 42px;
  border-radius: 14px;
  border: 1px solid rgba(13, 90, 115, 0.14);
  background: #fff;
  color: #0d5a73;
}

.tasks-static-option {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  border-radius: 14px;
  background: rgba(13, 90, 115, 0.08);
  color: #08384a;
  font-weight: 700;
}

.tasks-add-question {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: 48px;
  margin-top: 16px;
  border-radius: 18px;
  border: 1px dashed rgba(13, 90, 115, 0.3);
  background: rgba(13, 90, 115, 0.04);
  color: #0d5a73;
  font-weight: 700;
}

.tasks-draft-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.tasks-draft-list {
  display: grid;
  gap: 12px;
}

.tasks-draft-card {
  padding: 16px;
  border-radius: 18px;
  border: 1px solid rgba(13, 90, 115, 0.1);
  background: #f8fbfb;
}

.tasks-draft-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.tasks-draft-card__title {
  font-weight: 800;
  color: #08384a;
  line-height: 1.8;
}

.tasks-draft-card__meta {
  margin-top: 6px;
  color: rgba(8, 56, 74, 0.65);
  font-size: 0.9rem;
}

.tasks-draft-options {
  display: grid;
  gap: 8px;
  margin-top: 12px;
}

.tasks-draft-option,
.tasks-draft-answer {
  padding: 10px 12px;
  border-radius: 12px;
  background: #fff;
  color: #08384a;
}

.tasks-draft-option--correct,
.tasks-draft-answer {
  color: #1d7c52;
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

.assessment-form-card {
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 22px;
  background: rgba(248, 250, 252, 0.72);
  padding: 18px;
}

.assessment-form-card__topline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
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

.assessment-select {
  width: 100%;
}

.assessment-input {
  text-align: right;
}

.assessment-input:focus,
.assessment-dialog__paste:focus,
.tasks-document-editor:focus {
  border-color: rgba(13, 90, 115, 0.38);
  box-shadow: 0 0 0 4px rgba(13, 90, 115, 0.08);
}

.assessment-options-grid,
.assessment-static-options {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.assessment-options-grid__item {
  display: flex;
  align-items: stretch;
  gap: 8px;
}

.assessment-options-grid__append {
  width: 42px;
  min-width: 42px;
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
  margin: 14px 0 0;
  color: #b42318;
  font-size: 0.9rem;
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
  .tasks-form-grid {
    grid-template-columns: 1fr;
  }

  .tasks-form-grid--question {
    grid-template-columns: 1fr;
  }

  .assessment-form-card__triple-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 700px) {
  .tasks-hero {
    flex-direction: column;
    padding: 22px;
  }

  .tasks-create-shell {
    padding: 22px;
  }

  .task-card__buttons,
  .tasks-dialog__footer,
  .assessment-dialog__footer {
    grid-template-columns: 1fr;
    flex-direction: column;
  }

  .tasks-create-shell__top-actions,
  .tasks-paste-shell,
  .tasks-draft-header,
  .tasks-option-row {
    flex-direction: column;
    align-items: stretch;
  }

  .assessment-dialog__import-row,
  .assessment-dialog__body,
  .assessment-dialog__header,
  .assessment-dialog__footer {
    padding-left: 14px;
    padding-right: 14px;
  }

  .assessment-dialog__import-row {
    margin-top: 16px;
  }
}
</style>
