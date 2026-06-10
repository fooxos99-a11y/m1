<template>
  <div
    class="assessment-page"
    :class="{ 'assessment-page--embedded': embedded }"
  >
    <div
      v-if="showQuestionDetails"
      class="assessment-page__glow assessment-page__glow--one"
    />
    <div
      v-if="showQuestionDetails"
      class="assessment-page__glow assessment-page__glow--two"
    />

    <v-container class="assessment-page__container py-8 py-md-12">
      <div
        v-if="!embedded"
        class="assessment-page__mobile-back"
      >
        <AppIconButton
          variant="primary"
          class="assessment-mobile-back"
          @click="navigateBack"
        >
          <v-icon small>
            mdi-arrow-right
          </v-icon>
        </AppIconButton>
      </div>

      <div
        v-if="!embedded && !isTasksPage"
        class="assessment-tabs"
      >
        <AppChoiceButton
          v-for="type in ['pre', 'post']"
          :key="type"
          variant="tab"
          class="assessment-tabs__item"
          :active="type === assessmentType"
          @click="switchAssessmentType(type)"
        >
          {{ assessmentLabels[type] }}
        </AppChoiceButton>
      </div>

      <v-alert
        v-if="dashboardError"
        type="error"
        outlined
        class="assessment-alert mb-6"
      >
        {{ dashboardError }}
      </v-alert>

      <section
        v-if="showCourseCards"
        class="assessment-cards-shell"
      >
        <article
          v-for="course in filteredCourses"
          :key="course.id"
          class="assessment-course-card"
        >
          <div class="assessment-course-card__top">
            <div class="assessment-course-card__copy">
              <h2 class="assessment-course-card__title">
                {{ course.title }}
              </h2>
            </div>

            <div
              v-if="canEditQuestions"
              class="assessment-course-card__actions"
            >
              <AppIconButton
                variant="danger"
                class="assessment-course-card__icon assessment-course-card__icon--delete"
                :disabled="deletingCourseId === course.id"
                @click="requestManagedCourseDelete(course)"
              >
                <i
                  class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                  aria-hidden="true"
                />
              </AppIconButton>

              <v-menu
                offset-y
                left
              >
                <template #activator="{ on, attrs }">
                  <AppIconButton
                    variant="primary"
                    class="assessment-course-card__icon"
                    v-bind="attrs"
                    v-on="on"
                  >
                    <i
                      class="fa-regular fa-pen-to-square app-action-icon app-action-icon--edit"
                      aria-hidden="true"
                    />
                  </AppIconButton>
                </template>

                <div class="assessment-course-card__menu">
                  <button
                    type="button"
                    class="assessment-course-card__menu-item"
                    @click="openCourseEditDialog(course)"
                  >
                    تعديل الاسم
                  </button>
                  <button
                    type="button"
                    class="assessment-course-card__menu-item"
                    @click="openCourseAssessmentHub(course.id)"
                  >
                    {{ isTasksPage ? 'تعديل المهمة' : 'تعديل الأسئلة' }}
                  </button>
                </div>
              </v-menu>
            </div>
          </div>

          <div class="assessment-course-card__buttons">
            <template v-if="isTasksPage">
              <AppChoiceButton
                block
                class="assessment-course-card__button"
                :active="selectedCourseId === course.id"
                @click="openCourseAssessmentHub(course.id)"
              >
                {{ course.taskMode === 'document' ? 'وورد' : 'الأسئلة' }}
              </AppChoiceButton>
              <AppChoiceButton
                block
                class="assessment-course-card__button"
                :active="selectedCourseId === course.id"
                @click="openCourseAssessmentHub(course.id)"
              >
                تحرير المهمة
              </AppChoiceButton>
            </template>
            <template v-else>
              <AppChoiceButton
                block
                class="assessment-course-card__button"
                :active="isAssessmentActive(course, 'pre')"
                @click="handleAssessmentAvailabilityAction(course, 'pre')"
              >
                {{ assessmentAvailabilityButtonLabel(course, 'pre') }}
              </AppChoiceButton>
              <AppChoiceButton
                block
                class="assessment-course-card__button"
                :active="isAssessmentActive(course, 'post')"
                @click="handleAssessmentAvailabilityAction(course, 'post')"
              >
                {{ assessmentAvailabilityButtonLabel(course, 'post') }}
              </AppChoiceButton>
            </template>
          </div>
        </article>
      </section>

      <section
        v-if="showCourseCards"
        class="assessment-indicators-card"
      >
        <div class="assessment-indicators-card__header">
          <h2 class="assessment-indicators-card__title">
            {{ isTasksPage ? 'مؤشرات المهام الأدائية' : 'مؤشرات الدورات' }}
          </h2>
        </div>

        <div class="assessment-indicators-card__controls">
          <div class="assessment-indicators-card__filter-group">
            <label class="assessment-indicators-card__label">{{ isTasksPage ? 'المهمة الأدائية' : 'الدورة' }}</label>
            <AppSelect
              v-model="assessmentIndicatorsCourseId"
              :items="assessmentIndicatorsCourseOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="assessment-select"
            />
          </div>

          <div
            v-if="!managedBranchId"
            class="assessment-indicators-card__filter-group"
          >
            <label class="assessment-indicators-card__label">الفرع</label>
            <AppSelect
              v-model="assessmentIndicatorsBranch"
              :items="assessmentIndicatorsBranchOptions"
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
          v-if="!assessmentIndicatorsCourse || !assessmentIndicatorsTotalStudents"
          class="assessment-empty-state"
        >
          {{ assessmentIndicatorsCourse ? 'لا يوجد معلمون مطابقون للفرع المحدد.' : (isTasksPage ? 'لا توجد مهمة أدائية محددة لعرض المؤشرات.' : 'لا توجد دورة محددة لعرض المؤشرات.') }}
        </div>

        <div
          v-else
          class="assessment-indicators-panel"
        >
          <template v-if="isTasksPage">
            <article class="assessment-score-indicator">
              <div
                class="assessment-score-indicator__ring"
                :style="taskIndicatorStyle"
              >
                <div class="assessment-score-indicator__ring-core">
                  {{ animatedIndicatorDisplay(`${taskSubmissionIndicator.percent}%`) }}
                </div>
              </div>
              <div class="assessment-score-indicator__text">
                <div class="assessment-score-indicator__subtitle">
                  نسبة تسليم المهمة
                </div>
                <div class="assessment-score-indicator__sublabel">
                  {{ taskSubmissionIndicator.submitted }} من {{ taskSubmissionIndicator.totalStudents }} معلم
                </div>
              </div>
            </article>
          </template>
          <template v-else>
            <article class="assessment-score-indicator">
              <div
                class="assessment-score-indicator__ring"
                :style="preIndicatorStyle"
              >
                <div class="assessment-score-indicator__ring-core">
                  {{ animatedIndicatorDisplay(`${assessmentPreIndicator.percent}%`) }}
                </div>
              </div>
              <div class="assessment-score-indicator__text">
                <div class="assessment-score-indicator__subtitle">
                  الاختبار القبلي
                </div>
                <div class="assessment-score-indicator__sublabel">
                  {{ assessmentPreIndicator.totalStudents }} معلم
                </div>
              </div>
            </article>

            <div
              class="assessment-score-diff"
              :class="assessmentScoreDiffClass"
            >
              <div class="assessment-score-diff__value">
                {{ assessmentScoreDiffLabel }}
              </div>
            </div>

            <article class="assessment-score-indicator">
              <div
                class="assessment-score-indicator__ring"
                :style="postIndicatorStyle"
              >
                <div class="assessment-score-indicator__ring-core">
                  {{ animatedIndicatorDisplay(`${assessmentPostIndicator.percent}%`) }}
                </div>
              </div>
              <div class="assessment-score-indicator__text">
                <div class="assessment-score-indicator__subtitle">
                  الاختبار البعدي
                </div>
                <div class="assessment-score-indicator__sublabel">
                  {{ assessmentPostIndicator.totalStudents }} معلم
                </div>
              </div>
            </article>
          </template>
        </div>
      </section>

      <template v-else-if="showUnifiedToolbar && isIndicatorsMode">
        <div class="prep-layout">
          <section class="prep-card">
            <div class="assessment-toolbar assessment-toolbar--embedded">
              <div class="assessment-toolbar__field">
                <label class="assessment-toolbar__label">الدورات</label>
                <AppSelect
                  v-model="courseSelectValue"
                  :items="courseSelectOptions"
                  item-text="label"
                  item-value="value"
                  :placeholder="coursePlaceholderLabel"
                  persistent-placeholder
                  dense
                  outlined
                  hide-details
                  class="assessment-select"
                >
                  <template
                    v-if="canEditQuestions"
                    #item="{ item, on, attrs }"
                  >
                    <div
                      class="assessment-select-option"
                      v-bind="attrs"
                      v-on="on"
                    >
                      <span class="assessment-select-option__label">{{ item.label }}</span>
                      <button
                        v-if="item.course"
                        type="button"
                        class="assessment-select-option__delete"
                        :disabled="deletingCourseId === item.value"
                        :aria-label="`حذف ${item.course.entityType === 'task' ? 'المهمة' : 'الدورة'} ${item.label}`"
                        @mousedown.stop.prevent
                        @click.stop.prevent="requestManagedCourseDelete(item.course)"
                      >
                        <i
                          class="fa-solid fa-trash-can"
                          aria-hidden="true"
                        />
                      </button>
                    </div>
                  </template>
                </AppSelect>
              </div>

              <div class="assessment-toolbar__field">
                <label class="assessment-toolbar__label">النوع</label>
                <AppSelect
                  v-model="viewMode"
                  :items="courseModeOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="assessment-select"
                />
              </div>
            </div>

            <div class="assessment-indicators-card__controls assessment-indicators-card__controls--embedded">
              <div
                v-if="!managedBranchId"
                class="assessment-indicators-card__filter-group"
              >
                <label class="assessment-indicators-card__label">الفرع</label>
                <AppSelect
                  v-model="assessmentIndicatorsBranch"
                  :items="assessmentIndicatorsBranchOptions"
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
              v-if="selectedCourse && !assessmentIndicatorsTotalStudents"
              class="assessment-empty-state"
            >
              لا يوجد معلمون مطابقون للفرع المحدد.
            </div>

            <div
              v-else-if="selectedCourse"
              class="assessment-indicators-panel"
            >
              <article class="assessment-score-indicator">
                <div
                  class="assessment-score-indicator__ring"
                  :style="preIndicatorStyle"
                >
                  <div class="assessment-score-indicator__ring-core">
                    {{ animatedIndicatorDisplay(assessmentPreIndicatorValueLabel) }}
                  </div>
                </div>
                <div class="assessment-score-indicator__text">
                  <div class="assessment-score-indicator__subtitle">
                    الاختبار القبلي
                  </div>
                  <div class="assessment-score-indicator__sublabel">
                    {{ assessmentPreIndicatorMetaLabel }}
                  </div>
                </div>
              </article>

              <div
                class="assessment-score-diff"
                :class="assessmentScoreDiffClass"
              >
                <div class="assessment-score-diff__value">
                  {{ assessmentScoreDiffLabel }}
                </div>
              </div>

              <article class="assessment-score-indicator">
                <div
                  class="assessment-score-indicator__ring"
                  :style="postIndicatorStyle"
                >
                  <div class="assessment-score-indicator__ring-core">
                    {{ animatedIndicatorDisplay(assessmentPostIndicatorValueLabel) }}
                  </div>
                </div>
                <div class="assessment-score-indicator__text">
                  <div class="assessment-score-indicator__subtitle">
                    الاختبار البعدي
                  </div>
                  <div class="assessment-score-indicator__sublabel">
                    {{ assessmentPostIndicatorMetaLabel }}
                  </div>
                </div>
              </article>
            </div>
          </section>
        </div>
      </template>

      <template v-else-if="showUnifiedToolbar && isAttendanceMode">
        <div class="prep-layout">
          <section class="prep-card">
            <div class="assessment-toolbar assessment-toolbar--embedded">
              <div class="assessment-toolbar__field">
                <label class="assessment-toolbar__label">الدورات</label>
                <AppSelect
                  v-model="courseSelectValue"
                  :items="courseSelectOptions"
                  item-text="label"
                  item-value="value"
                  :placeholder="coursePlaceholderLabel"
                  persistent-placeholder
                  dense
                  outlined
                  hide-details
                  class="assessment-select"
                >
                  <template
                    v-if="canEditQuestions"
                    #item="{ item, on, attrs }"
                  >
                    <div
                      class="assessment-select-option"
                      v-bind="attrs"
                      v-on="on"
                    >
                      <span class="assessment-select-option__label">{{ item.label }}</span>
                      <button
                        v-if="item.course"
                        type="button"
                        class="assessment-select-option__delete"
                        :disabled="deletingCourseId === item.value"
                        :aria-label="`حذف ${item.course.entityType === 'task' ? 'المهمة' : 'الدورة'} ${item.label}`"
                        @mousedown.stop.prevent
                        @click.stop.prevent="requestManagedCourseDelete(item.course)"
                      >
                        <i
                          class="fa-solid fa-trash-can"
                          aria-hidden="true"
                        />
                      </button>
                    </div>
                  </template>
                </AppSelect>
              </div>

              <div class="assessment-toolbar__field">
                <label class="assessment-toolbar__label">النوع</label>
                <AppSelect
                  v-model="viewMode"
                  :items="courseModeOptions"
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
              v-if="selectedCourse && saveStatusText"
              class="prep-card__status prep-card__status--inline"
              :class="{ 'prep-card__status--saving': isSavingAttendance }"
            >
              {{ saveStatusText }}
            </div>

            <div
              v-if="selectedCourse"
              class="prep-filters prep-filters--single"
            >
              <div
                v-if="!managedBranchId"
                class="prep-field prep-field--wide"
              >
                <label class="prep-field__label">الفرع</label>
                <AppSelect
                  v-model="attendanceBranchId"
                  :items="assessmentBranchOptions.filter((option) => option.value !== 'all')"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="prep-select"
                />
              </div>

              <button
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
              </button>
            </div>

            <div
              v-if="selectedCourse"
              class="prep-table-wrap"
            >
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
                      <button
                        type="button"
                        class="attendance-toggle"
                        :class="{ 'attendance-toggle--active': attendanceChecked.includes(student.id) }"
                        :disabled="isSavingAttendance"
                        @click="toggleAttendance(student.id)"
                      >
                        <span class="attendance-toggle__dot" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </template>

      <template v-else-if="hasCourseSelectionUi">
        <section
          v-if="isDocumentMode"
          class="assessment-layout"
        >
          <article class="assessment-card">
            <div
              class="assessment-toolbar assessment-toolbar--embedded"
            >
              <div class="assessment-toolbar__field">
                <label class="assessment-toolbar__label">{{ isTasksPage ? 'المهام الأدائية' : 'الدورات' }}</label>
                <AppSelect
                  v-model="courseSelectValue"
                  :items="courseSelectOptions"
                  item-text="label"
                  item-value="value"
                  :placeholder="coursePlaceholderLabel"
                  persistent-placeholder
                  dense
                  outlined
                  hide-details
                  class="assessment-select"
                >
                  <template
                    v-if="canEditQuestions"
                    #item="{ item, on, attrs }"
                  >
                    <div
                      class="assessment-select-option"
                      v-bind="attrs"
                      v-on="on"
                    >
                      <span class="assessment-select-option__label">{{ item.label }}</span>
                      <button
                        v-if="item.course"
                        type="button"
                        class="assessment-select-option__delete"
                        :disabled="deletingCourseId === item.value"
                        :aria-label="`حذف ${item.course.entityType === 'task' ? 'المهمة' : 'الدورة'} ${item.label}`"
                        @mousedown.stop.prevent
                        @click.stop.prevent="requestManagedCourseDelete(item.course)"
                      >
                        <i
                          class="fa-solid fa-trash-can"
                          aria-hidden="true"
                        />
                      </button>
                    </div>
                  </template>
                </AppSelect>
              </div>

              <div
                v-if="!isTasksPage"
                class="assessment-toolbar__field"
              >
                <label class="assessment-toolbar__label">{{ isTasksPage ? 'الدرجة' : 'النوع' }}</label>
                <AppSelect
                  v-model="viewMode"
                  :items="courseModeOptions"
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
              v-if="isTasksPage && selectedCourse"
              class="assessment-toolbar__aux-fields"
            >
              <div
                class="assessment-toolbar__field assessment-form-card__field-group assessment-form-card__field-group--compact"
              >
                <label class="assessment-form-card__label">الدرجة</label>
                <input
                  :value="taskPointsDraft"
                  type="number"
                  min="0"
                  class="assessment-input assessment-input--points"
                  placeholder="1"
                  @input="handleTaskPointsInput($event.target.value)"
                >
              </div>

              <div
                class="assessment-toolbar__field assessment-form-card__field-group assessment-form-card__field-group--compact"
              >
                <label class="assessment-form-card__label">الرابط</label>
                <input
                  v-model.trim="taskVideoUrlDraft"
                  type="url"
                  class="assessment-input"
                  :disabled="!canEditQuestions"
                  placeholder="ألصق رابط يوتيوب أو رابط فيديو مباشر"
                >
              </div>
            </div>

            <div
              v-if="isTasksPage && selectedCourse"
              class="assessment-toolbar__field assessment-toolbar__field--full assessment-form-card__field-group assessment-form-card__field-group--compact"
            >
              <div
                class="assessment-toolbar__field assessment-toolbar__field--full assessment-form-card__field-group assessment-form-card__field-group--compact"
              >
                <label class="assessment-form-card__label">الوصف</label>
                <textarea
                  v-model.trim="taskDescriptionDraft"
                  class="assessment-input assessment-input--multiline"
                  :disabled="!canEditQuestions"
                  placeholder="اكتب الوصف الذي سيظهر في المهمة"
                />
              </div>
            </div>

            <div
              v-if="isTasksPage && !selectedCourse"
              class="assessment-empty-state"
            >
              اختر المهمة أولًا.
            </div>

            <div class="assessment-template-shell__field">
              <div class="assessment-template-shell">
                <RichTextEditor
                  v-model="templateDraft"
                  :disabled="!canEditQuestions"
                  min-height="260px"
                />
              </div>
            </div>

            <div
              v-if="canEditQuestions"
              class="assessment-template-actions"
            >
              <AppButton
                variant="primary"
                :disabled="templateSaving"
                @click="saveTemplate"
              >
                {{ templateSaving ? 'جارٍ الحفظ...' : 'حفظ' }}
              </AppButton>
            </div>
          </article>
        </section>

        <section
          v-else
          class="assessment-layout"
        >
          <article class="assessment-card">
            <div class="assessment-toolbar assessment-toolbar--embedded">
              <div class="assessment-toolbar__field">
                <label class="assessment-toolbar__label">{{ isTasksPage ? 'المهام الأدائية' : 'الدورات' }}</label>
                <AppSelect
                  v-model="courseSelectValue"
                  :items="courseSelectOptions"
                  item-text="label"
                  item-value="value"
                  :placeholder="coursePlaceholderLabel"
                  persistent-placeholder
                  dense
                  outlined
                  hide-details
                  class="assessment-select"
                >
                  <template
                    v-if="canEditQuestions"
                    #item="{ item, on, attrs }"
                  >
                    <div
                      class="assessment-select-option"
                      v-bind="attrs"
                      v-on="on"
                    >
                      <span class="assessment-select-option__label">{{ item.label }}</span>
                      <button
                        v-if="item.course"
                        type="button"
                        class="assessment-select-option__delete"
                        :disabled="deletingCourseId === item.value"
                        :aria-label="`حذف ${item.course.entityType === 'task' ? 'المهمة' : 'الدورة'} ${item.label}`"
                        @mousedown.stop.prevent
                        @click.stop.prevent="requestManagedCourseDelete(item.course)"
                      >
                        <i
                          class="fa-solid fa-trash-can"
                          aria-hidden="true"
                        />
                      </button>
                    </div>
                  </template>
                </AppSelect>
              </div>

              <div
                v-if="!isTasksPage"
                class="assessment-toolbar__field"
              >
                <label class="assessment-toolbar__label">{{ isTasksPage ? 'الدرجة' : 'النوع' }}</label>
                <AppSelect
                  v-model="viewMode"
                  :items="courseModeOptions"
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
              v-if="isTasksPage && selectedCourse"
              class="assessment-toolbar__aux-fields"
            >
              <div class="assessment-toolbar__field assessment-form-card__field-group assessment-form-card__field-group--compact">
                <label class="assessment-form-card__label">الدرجة</label>
                <input
                  :value="taskPointsDraft"
                  type="number"
                  min="0"
                  class="assessment-input assessment-input--points"
                  placeholder="1"
                  @input="handleTaskPointsInput($event.target.value)"
                >
              </div>

              <div class="assessment-toolbar__field assessment-form-card__field-group assessment-form-card__field-group--compact">
                <label class="assessment-form-card__label">الرابط</label>
                <input
                  v-model.trim="taskVideoUrlDraft"
                  type="url"
                  class="assessment-input"
                  :disabled="!canEditQuestions"
                  placeholder="ألصق رابط يوتيوب أو رابط فيديو مباشر"
                >
              </div>
            </div>

            <div
              v-if="selectedCourse && selectedQuestions.length === 0"
              class="assessment-empty-state"
            >
              {{ isTasksPage ? 'لا توجد أسئلة بعد. استخدم + لإضافة خيارات أو نصي، أو اختر وورد.' : 'لا توجد أسئلة بعد.' }}
            </div>

            <div
              v-else-if="selectedCourse"
              class="assessment-inline-list"
            >
              <article
                v-for="(question, index) in selectedQuestions"
                :key="question.id"
                class="assessment-form-card assessment-form-card--inline assessment-inline-list__item"
              >
                <div class="assessment-form-card__prompt-row">
                  <div class="assessment-form-card__field-group assessment-form-card__field-group--compact assessment-form-card__field-group--prompt">
                    <div class="assessment-form-card__label-row">
                      <label class="assessment-form-card__label">{{ index + 1 }}.السؤال</label>
                      <button
                        v-if="canEditQuestions"
                        type="button"
                        class="assessment-form-card__trash"
                        aria-label="حذف السؤال"
                        @click="removeQuestion(question.id)"
                      >
                        <i
                          class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                          aria-hidden="true"
                        />
                      </button>
                    </div>
                    <input
                      :value="questionDrafts[question.id]?.prompt || ''"
                      type="text"
                      class="assessment-input"
                      placeholder="اكتب السؤال"
                      :disabled="!canEditQuestions || isSaving"
                      @input="updateQuestionDraft(question.id, { prompt: $event.target.value }); clearQuestionDraftError(question.id)"
                      @paste="handleExistingPromptPaste(question.id, $event)"
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
                      :disabled="!canEditQuestions || isSaving"
                      @input="updateQuestionDraft(question.id, { points: $event.target.value })"
                    >
                  </div>
                </div>

                <div
                  v-if="question.type === 'multiple'"
                  class="assessment-form-card__field-group"
                >
                  <div class="assessment-options-grid">
                    <div
                      v-for="(option, optionIndex) in (questionDrafts[question.id]?.options || [])"
                      :key="`${question.id}-${optionIndex}`"
                      class="assessment-options-grid__item"
                      :class="{ 'assessment-options-grid__item--correct': isExistingCorrectOption(question.id, option) }"
                    >
                      <button
                        v-if="option.trim()"
                        type="button"
                        class="assessment-options-grid__check"
                        :class="{ 'assessment-options-grid__check--active': isExistingCorrectOption(question.id, option) }"
                        :disabled="!canEditQuestions || isSaving"
                        @click="selectExistingCorrectOption(question.id, optionIndex)"
                      >
                        <v-icon small>
                          mdi-check
                        </v-icon>
                      </button>
                      <input
                        :value="option"
                        type="text"
                        class="assessment-input"
                        :placeholder="`الخيار ${optionIndex + 1}`"
                        :disabled="!canEditQuestions || isSaving"
                        @input="handleExistingOptionChange(question.id, optionIndex, $event.target.value)"
                        @paste="handleExistingOptionPaste(question.id, optionIndex, $event)"
                      >
                      <button
                        v-if="canEditQuestions && optionIndex === (questionDrafts[question.id]?.options || []).length - 1"
                        type="button"
                        class="assessment-options-grid__append"
                        aria-label="إضافة خيار"
                        :disabled="isSaving"
                        @click="handleExistingAddOptionField(question.id)"
                      >
                        <v-icon small>
                          mdi-plus
                        </v-icon>
                      </button>
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

            <div
              v-if="canEditQuestions && selectedCourse"
              class="assessment-inline-builder"
            >
              <div
                v-if="questionForms.length"
                class="assessment-inline-builder__list"
              >
                <article
                  v-for="(form, formIndex) in questionForms"
                  :key="`question-form-${formIndex}`"
                  class="assessment-form-card assessment-form-card--inline"
                >
                  <div class="assessment-form-card__topline">
                    <div class="assessment-form-card__meta">
                      <button
                        type="button"
                        class="assessment-form-card__trash"
                        aria-label="حذف السؤال"
                        @click="handleRemoveQuestionSlot(formIndex)"
                      >
                        <i
                          class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                          aria-hidden="true"
                        />
                      </button>
                    </div>
                  </div>

                  <div class="assessment-form-card__prompt-row">
                    <div class="assessment-form-card__field-group assessment-form-card__field-group--compact assessment-form-card__field-group--prompt">
                      <label class="assessment-form-card__label">{{ visibleSelectedQuestions.length + formIndex + 1 }}.السؤال</label>
                      <input
                        :value="form.prompt"
                        type="text"
                        class="assessment-input"
                        placeholder="اكتب السؤال"
                        @input="updateForm(formIndex, { prompt: $event.target.value }); clearFormError(formIndex)"
                        @paste="handlePromptPaste(formIndex, $event)"
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
                        @input="updateForm(formIndex, { points: $event.target.value })"
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
                        :class="{ 'assessment-options-grid__item--correct': isCorrectDraftOption(form, option) }"
                      >
                        <button
                          v-if="option.trim()"
                          type="button"
                          class="assessment-options-grid__check"
                          :class="{ 'assessment-options-grid__check--active': isCorrectDraftOption(form, option) }"
                          @click="selectCorrectOption(formIndex, optionIndex)"
                        >
                          <v-icon small>
                            mdi-check
                          </v-icon>
                        </button>
                        <input
                          :value="option"
                          type="text"
                          class="assessment-input"
                          :placeholder="`الخيار ${optionIndex + 1}`"
                          @input="handleOptionChange(formIndex, optionIndex, $event.target.value)"
                          @paste="handleOptionPaste(formIndex, optionIndex, $event)"
                        >
                        <button
                          v-if="optionIndex === form.options.length - 1"
                          type="button"
                          class="assessment-options-grid__append"
                          aria-label="إضافة خيار"
                          @click="handleAddOptionField(formIndex)"
                        >
                          <v-icon small>
                            mdi-plus
                          </v-icon>
                        </button>
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
                >
                  <template #activator="{ on, attrs }">
                    <button
                      type="button"
                      class="assessment-inline-builder__add"
                      :aria-label="isTasksPage ? 'إضافة مهمة أدائية' : 'إضافة سؤال'"
                      v-bind="attrs"
                      v-on="on"
                    >
                      <v-icon small>
                        mdi-plus
                      </v-icon>
                    </button>
                  </template>

                  <div class="assessment-inline-builder__menu">
                    <button
                      type="button"
                      class="assessment-inline-builder__menu-item"
                      @click="handleAddQuestionSlot('multiple')"
                    >
                      خيارات
                    </button>
                    <button
                      type="button"
                      class="assessment-inline-builder__menu-item"
                      @click="handleAddQuestionSlot('text')"
                    >
                      نصي
                    </button>
                    <button
                      v-if="isTasksPage && !selectedQuestions.length && !questionForms.length && !isDocumentMode"
                      type="button"
                      class="assessment-inline-builder__menu-item"
                      @click="handleAddQuestionSlot('document')"
                    >
                      وورد
                    </button>
                  </div>
                </v-menu>

                <AppButton
                  variant="primary"
                  :loading="isSaving"
                  :disabled="!canEditQuestions || isSaving"
                  @click="handleSaveAllQuestions"
                >
                  {{ isSaving ? 'جارٍ الحفظ...' : 'حفظ' }}
                </AppButton>
              </div>
            </div>
          </article>
        </section>
      </template>

      <section
        v-else
        class="assessment-empty-shell"
      >
        <h1 class="assessment-empty-message">
          {{ isTasksPage ? 'لا توجد مهام أدائية متاحة' : 'لا توجد دورات متاحة' }}
        </h1>
      </section>

      <AppDialog
        v-model="courseCreateDialogOpen"
        max-width="620"
      >
        <div class="assessment-dialog assessment-dialog--compact">
          <AppDialogHeader :title="isTasksPage ? 'إضافة مهمة أدائية' : 'إضافة دورة جديدة'" />

          <AppDialogBody
            class="assessment-dialog__body assessment-dialog__body--compact"
            compact
          >
            <input
              v-model="courseCreateTitle"
              type="text"
              class="assessment-input"
              :placeholder="isTasksPage ? 'اسم المهمة الأدائية' : 'اسم الدورة'"
            >

            <p
              v-if="isTasksPage && courseCreateMode === 'document'"
              class="assessment-dialog__text assessment-dialog__text--muted"
            >
              سيتم إنشاء مهمة وورد واعتماد الدرجة المحددة في الحقل العلوي للمرفق.
            </p>

            <template v-if="isTasksPage && courseCreateMode === 'document'">
              <div class="assessment-form-card__field-group assessment-dialog__spaced-input">
                <label class="assessment-form-card__label">الوصف</label>
                <div class="assessment-template-shell">
                  <RichTextEditor
                    v-model="courseCreateTemplateDraft"
                    min-height="220px"
                  />
                </div>
              </div>
            </template>

            <p
              v-else-if="isTasksPage && courseCreateQuestionType"
              class="assessment-dialog__text assessment-dialog__text--muted"
            >
              بعد إنشاء المهمة سيتم فتح محرر {{ courseCreateQuestionType === 'multiple' ? 'الخيارات' : 'السؤال النصي' }} مباشرة داخل نفس الصفحة.
            </p>
          </AppDialogBody>

          <AppDialogFooter class="assessment-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeCourseCreateDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="courseCreateSubmitting"
              @click="saveNewCourse"
            >
              {{ courseCreateSubmitting ? 'جارٍ الإضافة...' : 'إضافة' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="courseEditDialogOpen"
        max-width="620"
      >
        <div class="assessment-dialog assessment-dialog--compact">
          <AppDialogHeader title="تعديل اسم الدورة" />

          <AppDialogBody
            class="assessment-dialog__body assessment-dialog__body--compact"
            compact
          >
            <input
              v-model="courseEditTitle"
              type="text"
              class="assessment-input"
              placeholder="اسم الدورة"
            >
          </AppDialogBody>

          <AppDialogFooter class="assessment-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeCourseEditDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="courseEditSubmitting"
              @click="saveCourseEdit"
            >
              {{ courseEditSubmitting ? 'جارٍ الحفظ...' : 'حفظ' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="courseDeleteDialogOpen"
        max-width="620"
      >
        <div class="assessment-dialog assessment-dialog--compact">
          <AppDialogHeader :title="isTasksPage ? 'تأكيد حذف المهمة' : 'تأكيد حذف الدورة'" />

          <AppDialogBody
            class="assessment-dialog__body assessment-dialog__body--compact"
            compact
          >
            <p class="assessment-dialog__text">
              هل أنت متأكد من حذف {{ isTasksPage ? 'المهمة' : 'الدورة' }}
              <strong>{{ courseDeleteTitle || 'المحددة' }}</strong>
              ؟
            </p>
          </AppDialogBody>

          <AppDialogFooter class="assessment-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeCourseDeleteDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="danger"
              :loading="assessmentDeleteSubmitting"
              @click="confirmManagedCourseDelete"
            >
              {{ assessmentDeleteSubmitting ? 'جارٍ الحذف...' : 'حذف' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="assessmentAvailabilityDialogOpen"
        max-width="560"
      >
        <div class="assessment-dialog assessment-dialog--compact">
          <AppDialogHeader :title="`فتح ${availabilityDialogLabel}`" />

          <AppDialogBody
            class="assessment-dialog__body assessment-dialog__body--compact"
            compact
          >
            <div class="assessment-form-card assessment-form-card--flat">
              <div
                v-if="!managedBranchId"
                class="assessment-form-card__field-group assessment-form-card__field-group--compact"
              >
                <label class="assessment-form-card__label">اختر الفرع</label>
                <AppSelect
                  v-model="assessmentAvailabilityBranch"
                  :items="assessmentBranchOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="results-select"
                />
              </div>

              <div class="assessment-form-card__field-group assessment-form-card__field-group--compact">
                <label class="assessment-form-card__label">مدة فتح الاختبار بالدقائق</label>
                <input
                  v-model.number="assessmentAvailabilityMinutes"
                  type="number"
                  min="1"
                  class="assessment-input"
                  placeholder="60"
                >
              </div>
            </div>
          </AppDialogBody>

          <AppDialogFooter class="assessment-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeAssessmentAvailabilityDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="assessmentAvailabilitySubmitting"
              @click="confirmAssessmentAvailability"
            >
              {{ assessmentAvailabilitySubmitting ? 'جارٍ الفتح...' : 'فتح الاختبار' }}
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="assessmentManageDialogOpen"
        max-width="560"
      >
        <div class="assessment-dialog assessment-dialog--compact">
          <AppDialogHeader :title="`إدارة ${assessmentManageDialogLabel}`" />

          <AppDialogBody
            class="assessment-dialog__body assessment-dialog__body--compact"
            compact
          >
            <div class="assessment-form-card assessment-form-card--flat">
              <div class="assessment-form-card__field-group assessment-form-card__field-group--compact">
                <label class="assessment-form-card__label">الإجراء</label>
                <AppSelect
                  v-model="assessmentManageChoice"
                  :items="currentAssessmentManageOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="assessment-select"
                />
              </div>
            </div>
          </AppDialogBody>

          <AppDialogFooter class="assessment-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeAssessmentManageDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="assessmentManageSubmitting"
              @click="confirmAssessmentManageAction"
            >
              {{ assessmentManageSubmitting ? 'جارٍ التنفيذ...' : 'تأكيد' }}
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
  AppDialogHeader, AppIconButton, AppSelect,
} from '../components/ui';
import RichTextEditor from '../components/RichTextEditor.vue';
import indicatorAnimation from '../mixins/indicatorAnimation';

const assessmentLabels = {
  pre: 'الاختبار القبلي',
  post: 'الاختبار البعدي',
  tasks: 'المهام الأدائية',
};

const branchLabels = {
  male: 'معلمين',
  female: 'معلمات',
  all: 'الكل',
};

const CREATE_COURSE_OPTION = '__create_course__';

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

const emptyQuestionForm = () => ({
  prompt: '',
  type: 'multiple',
  options: ['', ''],
  points: '1',
  correctAnswer: '',
  correctAnswerTouched: false,
});

const normalizeLine = (value) => value
  .replace(/\u00a0/g, ' ')
  .replace(/\s+/g, ' ')
  .replace(/\s+([؟?؛:.,])/g, '$1')
  .replace(/([.،؛:?؟])\1+/g, '$1')
  .replace(/^[-–—•●▪◦.،؛:]+\s*/, '')
  .trim();

const stripLeadingMarker = (value) => normalizeLine(value.replace(LEADING_LIST_MARKER_PATTERN, ''));
const stripTrailingQuestionNumber = (value) => normalizeLine(value.replace(QUESTION_END_NUMBER_PATTERN, ''));
const stripOptionMarker = (value) => normalizeLine(value.replace(OPTION_MARKER_PATTERN, '').replace(TRAILING_OPTION_MARKER_PATTERN, ''));
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
  const rawLines = text
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

  return importedQuestions.filter(
    (question, questionIndex, collection) => collection.findIndex((candidate) => candidate.prompt === question.prompt) === questionIndex,
  );
};

const stripQuestionOptionLabel = (value) => value
  .trim()
  .replace(/^(?:[A-Za-z\u0621-\u064A]|\d{1,2})\s*[-–—.):]\s*/, '')
  .trim();

const splitPastedQuestionOptions = (value) => {
  const normalizedValue = value.replace(/\r\n?/g, '\n').trim();

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
      return stripQuestionOptionLabel(normalizedValue.slice(marker.labelStart, nextMarkerStart));
    })
    .filter(Boolean);
};

const normalizeAnswer = (value) => String(value || '')
  .trim()
  .toLowerCase()
  .replace(/[\u064B-\u065F\u0670]/g, '')
  .replace(/[أإآ]/g, 'ا')
  .replace(/ة/g, 'ه')
  .replace(/ى/g, 'ي')
  .replace(/[.،,؛;!?؟:]+$/g, '')
  .replace(/^[أ-ي]\s*[).\-:]\s*/i, '')
  .trim();

export default {
  name: 'AdminAssessmentView',
  components: {
    AppDialog,
    AppButton,
    AppChoiceButton,
    AppIconButton,
    AppSelect,
    AppDialogHeader,
    AppDialogBody,
    AppDialogFooter,
    RichTextEditor,
  },
  mixins: [indicatorAnimation],
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
    assessmentTypeOverride: {
      type: String,
      default: '',
    },
    courseIdOverride: {
      type: String,
      default: '',
    },
    skipInitialLoad: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      assessmentLabels,
      selectedCourseId: '',
      viewMode: this.embedded ? 'attendance' : '',
      selectedDetailAssessmentType: '',
      questionForms: [],
      questionErrors: [],
      questionDrafts: {},
      questionDraftErrors: {},
      pendingDeletedQuestionIds: [],
      isSaving: false,
      templateDraft: '',
      taskVideoUrlDraft: '',
      taskDescriptionDraft: '',
      templateSaving: false,
      courseCreateDialogOpen: false,
      courseCreateTitle: '',
      courseCreateMode: 'questions',
      courseCreateQuestionType: '',
      courseCreateTemplateDraft: '',
      courseCreateVideoUrlDraft: '',
      courseCreateDescriptionDraft: '',
      taskPointsDraft: '1',
      courseCreateSubmitting: false,
      courseEditDialogOpen: false,
      courseEditId: '',
      courseEditTitle: '',
      courseEditSubmitting: false,
      deletingCourseId: '',
      courseDeleteDialogOpen: false,
      courseDeleteId: '',
      courseDeleteTitle: '',
      assessmentDeleteSubmitting: false,
      assessmentAvailabilityDialogOpen: false,
      assessmentAvailabilityCourseId: '',
      assessmentAvailabilityType: 'pre',
      assessmentAvailabilityBranch: 'all',
      assessmentAvailabilityPreserveActiveBranches: false,
      assessmentAvailabilityMinutes: 60,
      assessmentAvailabilitySubmitting: false,
      assessmentManageDialogOpen: false,
      assessmentManageCourseId: '',
      assessmentManageType: 'pre',
      assessmentManageChoice: '',
      assessmentManageSubmitting: false,
      assessmentIndicatorsCourseId: '',
      assessmentIndicatorsBranch: 'all',
      attendanceBranchId: 'male',
      attendanceChecked: [],
      isSavingAttendance: false,
      saveStatusText: '',
      attendanceSaveTimer: null,
      currentTimestamp: Date.now(),
      assessmentCountdownTimer: null,
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'dashboardLoading', 'dashboardError', 'currentUser']),
    assessmentType() {
      const value = this.assessmentTypeOverride || this.$route.params.assessmentType;

      return ['pre', 'post', 'tasks'].includes(value) ? value : 'pre';
    },
    isTasksPage() {
      return this.assessmentType === 'tasks';
    },
    showUnifiedToolbar() {
      return this.embedded && !this.isTasksPage;
    },
    isIndicatorsMode() {
      return this.showUnifiedToolbar && this.viewMode === 'indicators';
    },
    isAttendanceMode() {
      return this.showUnifiedToolbar && this.viewMode === 'attendance';
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
    courseModeOptions() {
      const options = [
        { label: 'التحضير', value: 'attendance' },
        { label: 'الاختبار القبلي', value: 'pre' },
        { label: 'الاختبار البعدي', value: 'post' },
      ];

      if (this.filteredCourses.length) {
        options.push({ label: 'المؤشرات', value: 'indicators' });
      }

      return options;
    },
    coursePlaceholderLabel() {
      return this.isTasksPage ? 'اختر المهمة' : 'اختر الدورة';
    },
    hasCourseSelectionUi() {
      return this.filteredCourses.length > 0 || this.canEditQuestions;
    },
    filteredCourses() {
      const allCourses = this.dashboardSnapshot?.courses || [];

      return allCourses.filter((course) => (this.isTasksPage ? course.entityType === 'task' : course.entityType !== 'task'));
    },
    courseSelectOptions() {
      const options = this.filteredCourses.map((course) => ({
        label: course.title,
        value: course.id,
        course,
      }));

      if (this.canEditQuestions) {
        options.push({
          label: this.isTasksPage ? 'إضافة مهمة أدائية' : 'إضافة دورة جديدة',
          value: CREATE_COURSE_OPTION,
        });
      }

      return options;
    },
    courseSelectValue: {
      get() {
        return this.selectedCourseId || null;
      },
      set(value) {
        this.selectedCourseId = value || '';
      },
    },
    selectedCourse() {
      return this.filteredCourses.find((course) => course.id === this.selectedCourseId) || null;
    },
    selectedQuestions() {
      if (!this.selectedCourse) {
        return [];
      }

      if (!this.detailAssessmentType) {
        return [];
      }

      if (this.detailAssessmentType === 'pre') {
        return this.selectedCourse.preQuestions || [];
      }

      if (this.detailAssessmentType === 'post') {
        return this.selectedCourse.postQuestions || [];
      }

      return this.selectedCourse.taskQuestions || [];
    },
    visibleSelectedQuestions() {
      return this.selectedQuestions.filter((question) => !this.pendingDeletedQuestionIds.includes(question.id));
    },
    submissions() {
      return this.dashboardSnapshot?.submissions || [];
    },
    attendance() {
      return this.dashboardSnapshot?.attendance || [];
    },
    students() {
      return this.dashboardSnapshot?.students || [];
    },
    detailAssessmentType() {
      return this.selectedDetailAssessmentType || this.assessmentType;
    },
    isDocumentMode() {
      return this.isTasksPage && this.selectedCourse?.taskMode === 'document';
    },
    showCourseCards() {
      return false;
    },
    showQuestionDetails() {
      return Boolean(this.selectedCourseId);
    },
    availabilityDialogLabel() {
      return this.assessmentLabels[this.assessmentAvailabilityType] || 'الاختبار';
    },
    assessmentManageDialogLabel() {
      return this.assessmentLabels[this.assessmentManageType] || 'الاختبار';
    },
    assessmentBranchOptions() {
      if (this.managedBranchId) {
        return [
          { label: this.branchLabel(this.managedBranchId), value: this.managedBranchId },
        ];
      }

      return [
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
        { label: 'الكل', value: 'all' },
      ];
    },
    assessmentIndicatorsBranchOptions() {
      if (this.managedBranchId) {
        return [
          { label: this.branchLabel(this.managedBranchId), value: this.managedBranchId },
        ];
      }

      return [
        { label: 'الكل', value: 'all' },
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
      ];
    },
    assessmentIndicatorsCourseOptions() {
      return this.filteredCourses.map((course) => ({
        label: course.title,
        value: course.id,
      }));
    },
    assessmentIndicatorsCourse() {
      const activeCourseId = this.isIndicatorsMode && this.selectedCourseId
        ? this.selectedCourseId
        : this.assessmentIndicatorsCourseId;

      return this.filteredCourses.find((course) => course.id === activeCourseId) || null;
    },
    assessmentIndicatorStudents() {
      const targetBranchId = this.managedBranchId || this.assessmentIndicatorsBranch;

      if (targetBranchId === 'all') {
        return this.students;
      }

      return this.students.filter((student) => student.branchId === targetBranchId);
    },
    assessmentIndicatorsTotalStudents() {
      return this.assessmentIndicatorStudents.length;
    },
    assessmentPreIndicator() {
      return this.buildAssessmentScoreIndicator('pre');
    },
    assessmentPostIndicator() {
      return this.buildAssessmentScoreIndicator('post');
    },
    assessmentScoreDiff() {
      return this.assessmentPostIndicator.percent - this.assessmentPreIndicator.percent;
    },
    assessmentPreIndicatorValueLabel() {
      return this.formatScoreValue(this.assessmentPreIndicator.averageScore);
    },
    assessmentPostIndicatorValueLabel() {
      return this.formatScoreValue(this.assessmentPostIndicator.averageScore);
    },
    assessmentPreIndicatorMetaLabel() {
      return this.formatIndicatorMetaLabel(this.assessmentPreIndicator);
    },
    assessmentPostIndicatorMetaLabel() {
      return this.formatIndicatorMetaLabel(this.assessmentPostIndicator);
    },
    assessmentScoreDiffLabel() {
      return `${Math.abs(Math.round(this.assessmentScoreDiff))}%`;
    },
    assessmentScoreDiffClass() {
      if (this.assessmentScoreDiff > 0) {
        return 'assessment-score-diff--positive';
      }

      if (this.assessmentScoreDiff < 0) {
        return 'assessment-score-diff--negative';
      }

      return 'assessment-score-diff--neutral';
    },
    preIndicatorStyle() {
      return this.buildIndicatorRingStyle(this.assessmentPreIndicator.percent);
    },
    postIndicatorStyle() {
      return this.buildIndicatorRingStyle(this.assessmentPostIndicator.percent);
    },
    taskSubmissionIndicator() {
      if (!this.assessmentIndicatorsCourse || !this.assessmentIndicatorsTotalStudents) {
        return {
          submitted: 0,
          totalStudents: 0,
          percent: 0,
        };
      }

      const submitted = this.assessmentIndicatorStudents.reduce((sum, student) => {
        const hasSubmission = this.findAssessmentSubmission(student.loginId, 'tasks');
        return sum + (hasSubmission ? 1 : 0);
      }, 0);

      return {
        submitted,
        totalStudents: this.assessmentIndicatorsTotalStudents,
        percent: Math.round((submitted / this.assessmentIndicatorsTotalStudents) * 100),
      };
    },
    taskIndicatorStyle() {
      return this.buildIndicatorRingStyle(this.taskSubmissionIndicator.percent);
    },
    indicatorAnimationSignature() {
      return [
        this.isTasksPage ? 'tasks' : 'assessments',
        this.assessmentIndicatorsCourse?.id || '',
        this.assessmentIndicatorsBranch,
        this.assessmentPreIndicator.percent,
        this.assessmentPostIndicator.percent,
        this.taskSubmissionIndicator.percent,
      ].join('|');
    },
    currentAssessmentManageCourse() {
      return this.filteredCourses.find((course) => course.id === this.assessmentManageCourseId) || null;
    },
    currentAssessmentManageOptions() {
      if (!this.currentAssessmentManageCourse) {
        return [];
      }

      return this.getAssessmentManageOptions(this.currentAssessmentManageCourse, this.assessmentManageType);
    },
    attendanceStudents() {
      return this.students.filter((student) => student.branchId === (this.managedBranchId || this.attendanceBranchId));
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
      return this.attendance.filter((record) => record.courseId === this.selectedCourseId);
    },
    canEditQuestions() {
      return this.currentUser?.role === 'admin';
    },
    routeCourseId() {
      const rawValue = this.$route.query.courseId;

      return typeof rawValue === 'string' ? rawValue.trim() : '';
    },
    resolvedCourseId() {
      return (this.courseIdOverride || '').trim() || this.routeCourseId;
    },
    currentAssessmentActionType() {
      if (this.isTasksPage) {
        return 'tasks';
      }

      return this.viewMode === 'pre' || this.viewMode === 'post'
        ? this.viewMode
        : '';
    },
    canShowAssessmentStartButton() {
      return this.canEditQuestions && Boolean(this.selectedCourse && this.currentAssessmentActionType);
    },
    currentAssessmentActionLabel() {
      if (!this.selectedCourse || !this.currentAssessmentActionType) {
        return '';
      }

      const topbarLabel = this.currentAssessmentActionType === 'tasks'
        ? 'المهمة الأدائية'
        : (this.assessmentLabels[this.currentAssessmentActionType] || 'الاختبار');

      return this.isAssessmentActive(this.selectedCourse, this.currentAssessmentActionType)
        ? `إدارة ${topbarLabel}`
        : `بدء ${topbarLabel}`;
    },
    currentAssessmentActionClosesAt() {
      if (!this.selectedCourse || !this.currentAssessmentActionType) {
        return null;
      }

      const course = this.selectedCourse;
      const type = this.currentAssessmentActionType;
      const maleActive = this.isAssessmentBranchActive(course, type, 'male');
      const femaleActive = this.isAssessmentBranchActive(course, type, 'female');

      if (maleActive && femaleActive) {
        return this.getWindowMeta(course?.assessmentWindows?.global?.[type]).closesAt
          || this.getWindowMeta(course?.assessmentWindows?.male?.[type]).closesAt
          || this.getWindowMeta(course?.assessmentWindows?.female?.[type]).closesAt
          || null;
      }

      if (maleActive) {
        return this.getWindowMeta(course?.assessmentWindows?.male?.[type]).closesAt || null;
      }

      if (femaleActive) {
        return this.getWindowMeta(course?.assessmentWindows?.female?.[type]).closesAt || null;
      }

      return null;
    },
    currentAssessmentActionTimers() {
      if (!this.selectedCourse || !this.currentAssessmentActionType) {
        return [];
      }

      const course = this.selectedCourse;
      const type = this.currentAssessmentActionType;
      const maleActive = this.isAssessmentBranchActive(course, type, 'male');
      const femaleActive = this.isAssessmentBranchActive(course, type, 'female');

      if (!maleActive && !femaleActive) {
        return [];
      }

      if (maleActive && femaleActive) {
        const globalClosesAt = this.getWindowMeta(course?.assessmentWindows?.global?.[type]).closesAt || null;

        if (globalClosesAt) {
          return [
            { branchCode: 'male', branchLabel: this.branchLabel('male'), closesAt: globalClosesAt },
            { branchCode: 'female', branchLabel: this.branchLabel('female'), closesAt: globalClosesAt },
          ];
        }
      }

      const timers = [];

      if (maleActive) {
        const closesAt = this.getWindowMeta(course?.assessmentWindows?.male?.[type]).closesAt || null;

        if (closesAt) {
          timers.push({ branchCode: 'male', branchLabel: this.branchLabel('male'), closesAt });
        }
      }

      if (femaleActive) {
        const closesAt = this.getWindowMeta(course?.assessmentWindows?.female?.[type]).closesAt || null;

        if (closesAt) {
          timers.push({ branchCode: 'female', branchLabel: this.branchLabel('female'), closesAt });
        }
      }

      return timers;
    },
    assessmentTopbarState() {
      if (!this.embedded) {
        return {
          visible: false,
          label: '',
          type: '',
          isEnabled: false,
          closesAt: null,
          timers: [],
        };
      }

      return {
        visible: this.canShowAssessmentStartButton,
        label: this.currentAssessmentActionLabel,
        type: this.currentAssessmentActionType,
        isEnabled: this.isAssessmentActive(this.selectedCourse, this.currentAssessmentActionType),
        closesAt: this.currentAssessmentActionClosesAt,
        timers: this.currentAssessmentActionTimers,
      };
    },
  },
  watch: {
    managedBranchId: {
      immediate: true,
      handler(value) {
        if (!value) {
          return;
        }

        this.assessmentIndicatorsBranch = value;
        this.attendanceBranchId = value;
        this.assessmentAvailabilityBranch = value;
      },
    },
    assessmentType: {
      immediate: true,
      handler(type) {
        if (this.isTasksPage) {
          this.selectedDetailAssessmentType = 'tasks';
          return;
        }

        if (this.showUnifiedToolbar) {
          this.viewMode = 'attendance';
          this.selectedDetailAssessmentType = type;
          return;
        }

        if (type === 'pre' || type === 'post') {
          this.viewMode = type;
          this.selectedDetailAssessmentType = type;
        }
      },
    },
    assessmentTopbarState: {
      immediate: true,
      deep: true,
      handler(payload) {
        this.$emit('assessment-topbar-state', payload);
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
    filteredCourses: {
      immediate: true,
      handler() {
        this.syncSelectedCourse();
        this.syncAssessmentIndicatorsCourse();
      },
    },
    resolvedCourseId() {
      this.syncSelectedCourse();
    },
    selectedCourseId(newValue, oldValue) {
      if ((!this.showUnifiedToolbar && !this.isTasksPage) || newValue !== CREATE_COURSE_OPTION) {
        if (this.showUnifiedToolbar && !this.isTasksPage && newValue && newValue !== CREATE_COURSE_OPTION) {
          this.viewMode = 'attendance';
        }

        return;
      }

      this.openCourseCreateDialog();

      this.$nextTick(() => {
        this.selectedCourseId = oldValue && oldValue !== CREATE_COURSE_OPTION
          ? oldValue
          : this.defaultCourseSelectionId();
      });
    },
    viewMode: {
      immediate: true,
      handler(mode) {
        if (mode === 'pre' || mode === 'post') {
          this.selectedDetailAssessmentType = mode;
        }
      },
    },
    selectedCourse: {
      immediate: true,
      handler(course) {
        this.templateDraft = course?.taskMode === 'document' && typeof course?.taskTemplateContent === 'string'
          ? course.taskTemplateContent
          : '';
        this.taskVideoUrlDraft = typeof course?.youtubeUrl === 'string' ? course.youtubeUrl : '';
        this.taskDescriptionDraft = course?.taskMode === 'document' && typeof course?.taskDescription === 'string'
          ? course.taskDescription
          : '';

        if (this.isTasksPage) {
          const attachmentQuestion = (course?.taskQuestions || []).find((question) => question.allowFile);
          this.taskPointsDraft = String((attachmentQuestion || course?.taskQuestions?.[0])?.points ?? 1);
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
    selectedQuestions: {
      immediate: true,
      handler(questions) {
        this.syncQuestionDrafts(questions);
      },
    },
  },
  created() {
    this.assessmentCountdownTimer = window.setInterval(() => {
      this.currentTimestamp = Date.now();
    }, 1000);

    if (!this.skipInitialLoad) {
      this.loadDashboardSnapshot();
    }
  },
  beforeDestroy() {
    if (this.attendanceSaveTimer) {
      clearTimeout(this.attendanceSaveTimer);
      this.attendanceSaveTimer = null;
    }

    if (this.assessmentCountdownTimer) {
      window.clearInterval(this.assessmentCountdownTimer);
      this.assessmentCountdownTimer = null;
    }
  },
  methods: {
    ...mapActions([
      'loadDashboardSnapshot',
      'addCourse',
      'addQuestion',
      'updateQuestion',
      'deleteQuestion',
      'updateCourse',
      'deleteCourse',
      'activateCourse',
      'setManualAttendance',
    ]),
    normalizeAnswer,
    createQuestionDraft(question) {
      return {
        prompt: question?.prompt || '',
        type: question?.type === 'text' ? 'text' : 'multiple',
        options: question?.type === 'multiple' ? ((question?.options || []).length ? [...question.options] : ['', '']) : [],
        points: String(question?.points ?? 1),
        correctAnswer: question?.correctAnswer || '',
        correctAnswerTouched: false,
      };
    },
    syncQuestionDrafts(questions) {
      const nextDrafts = {};
      const nextErrors = {};

      (questions || []).forEach((question) => {
        nextDrafts[question.id] = this.createQuestionDraft(question);
        nextErrors[question.id] = this.questionDraftErrors[question.id] || '';
      });

      this.questionDrafts = nextDrafts;
      this.questionDraftErrors = nextErrors;
      this.pendingDeletedQuestionIds = this.pendingDeletedQuestionIds.filter((id) => Boolean(nextDrafts[id]));
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
    validateQuestionDraft(draft) {
      const prompt = String(draft?.prompt || '').trim();
      const options = draft?.type === 'multiple'
        ? (draft.options || []).map((option) => option.trim()).filter(Boolean)
        : [];

      if (!prompt) {
        return 'أدخل السؤال.';
      }

      if (draft?.type === 'multiple' && options.length < 2) {
        return 'أدخل خيارين على الأقل.';
      }

      if (draft?.type === 'multiple' && !String(draft?.correctAnswer || '').trim()) {
        return 'اختر الإجابة الصحيحة.';
      }

      const points = Number(draft?.points);

      if (!Number.isFinite(points) || points < 0) {
        return 'أدخل درجة صحيحة.';
      }

      return '';
    },
    buildIndicatorRingStyle(percent) {
      const safePercent = this.animatedIndicatorPercent(percent);

      return {
        background: `conic-gradient(#156c82 0 ${safePercent}%, #e9f2f5 ${safePercent}% 100%)`,
      };
    },
    buildAssessmentScoreIndicator(type) {
      if (!this.assessmentIndicatorsCourse || !this.assessmentIndicatorsTotalStudents) {
        return {
          count: 0,
          submitted: 0,
          totalStudents: this.assessmentIndicatorsTotalStudents,
          totalPoints: 0,
          averageScore: 0,
          percent: 0,
        };
      }

      const questions = type === 'pre'
        ? (this.assessmentIndicatorsCourse.preQuestions || [])
        : (this.assessmentIndicatorsCourse.postQuestions || []);
      const totalPoints = questions.reduce((sum, question) => sum + (Number(question.points || 0) || 0), 0);

      const scores = this.assessmentIndicatorStudents.reduce((items, student) => {
        const submission = this.findAssessmentSubmission(student.loginId, type);

        if (!submission) {
          return items;
        }

        items.push(this.resolveAssessmentSubmissionScore(submission, questions));
        return items;
      }, []);
      const submitted = scores.length;
      const averageScore = submitted
        ? scores.reduce((sum, score) => sum + score, 0) / submitted
        : 0;

      const percent = totalPoints > 0
        ? Math.round((averageScore / totalPoints) * 100)
        : 0;

      return {
        count: submitted,
        submitted,
        totalStudents: this.assessmentIndicatorsTotalStudents,
        totalPoints,
        averageScore,
        percent,
      };
    },
    formatScoreValue(value) {
      const safeValue = Number(value) || 0;

      if (Number.isInteger(safeValue)) {
        return String(safeValue);
      }

      return safeValue.toFixed(1);
    },
    formatIndicatorMetaLabel(indicator) {
      return `${Number(indicator?.submitted || 0)} معلم`;
    },
    findAssessmentSubmission(loginId, type) {
      if (!loginId || !this.assessmentIndicatorsCourse) {
        return null;
      }

      const matches = this.submissions.filter((item) => (
        item.courseId === this.assessmentIndicatorsCourse.id
        && item.assessmentType === type
        && item.loginId === loginId
      ));

      return matches[matches.length - 1] || null;
    },
    resolveAssessmentSubmissionScore(submission, questions) {
      if (!submission) {
        return 0;
      }

      if (submission.manualScore !== null && submission.manualScore !== undefined) {
        return Number(submission.manualScore) || 0;
      }

      if (!questions.length) {
        return 0;
      }

      const answerMap = new Map((submission.answers || []).map((answer) => [answer.questionId, answer]));

      return questions.reduce((sum, question) => {
        if (!question.correctAnswer) {
          return sum;
        }

        const answer = answerMap.get(question.id);
        const studentAnswer = String(answer?.value || '').trim();

        if (this.normalizeAnswer(studentAnswer) === this.normalizeAnswer(question.correctAnswer)) {
          return sum + Number(question.points || 0);
        }

        return sum;
      }, 0);
    },
    branchLabel(branchId) {
      return branchLabels[branchId] || '';
    },
    assessmentEnabledKey(type) {
      return {
        pre: 'isPreEnabled',
        post: 'isPostEnabled',
        tasks: 'isTasksEnabled',
      }[type] || 'isPreEnabled';
    },
    getWindowMeta(value) {
      if (!value) {
        return { opensAt: '', closesAt: '', durationMinutes: 0 };
      }

      if (typeof value === 'string') {
        return { opensAt: '', closesAt: value, durationMinutes: 0 };
      }

      return {
        opensAt: value.opensAt || '',
        closesAt: value.closesAt || '',
        durationMinutes: Number(value.durationMinutes) || 0,
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
    isAssessmentBranchActive(course, type, branchId) {
      if (!course || !branchId) {
        return false;
      }

      const enabled = Boolean(course[this.assessmentEnabledKey(type)]);
      const branchEnabled = course.branchAvailability?.[branchId]?.[type] !== false;
      const branchWindow = course.assessmentWindows?.[branchId]?.[type];

      return enabled && branchEnabled && this.isWindowActive(branchWindow);
    },
    isAssessmentActive(course, type) {
      return this.isAssessmentBranchActive(course, type, 'male') || this.isAssessmentBranchActive(course, type, 'female');
    },
    assessmentAvailabilityButtonLabel(course, type) {
      const label = this.assessmentLabels[type] || 'الاختبار';
      const maleActive = this.isAssessmentBranchActive(course, type, 'male');
      const femaleActive = this.isAssessmentBranchActive(course, type, 'female');

      if (!maleActive && !femaleActive) {
        return label;
      }

      if (maleActive && femaleActive) {
        const globalWindow = this.getWindowMeta(course?.assessmentWindows?.global?.[type]);
        const closesAt = globalWindow.closesAt || this.getWindowMeta(course?.assessmentWindows?.male?.[type]).closesAt;
        return `الكل ${this.formatCountdownFromDate(closesAt)}`;
      }

      const activeBranch = maleActive ? 'male' : 'female';
      const closesAt = this.getWindowMeta(course?.assessmentWindows?.[activeBranch]?.[type]).closesAt;
      return `${this.branchLabel(activeBranch)} ${this.formatCountdownFromDate(closesAt)}`;
    },
    formatCountdownFromDate(value) {
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
    handleAssessmentAvailabilityAction(course, type) {
      const resolvedType = this.resolveActionAssessmentType(type);

      if (!this.isAssessmentActive(course, resolvedType)) {
        this.openAssessmentAvailabilityDialog(course.id, resolvedType, 'all', false);
        return;
      }

      this.openAssessmentManageDialog(course.id, resolvedType);
    },
    handleCurrentAssessmentAvailabilityAction() {
      if (!this.selectedCourse || !this.currentAssessmentActionType) {
        return;
      }

      this.handleAssessmentAvailabilityAction(this.selectedCourse, this.currentAssessmentActionType);
    },
    syncSelectedCourse() {
      if (!this.hasCourseSelectionUi) {
        this.selectedCourseId = '';
        return;
      }

      if (this.resolvedCourseId && this.filteredCourses.some((course) => course.id === this.resolvedCourseId)) {
        this.selectedCourseId = this.resolvedCourseId;
        return;
      }

      if (this.filteredCourses.some((course) => course.id === this.selectedCourseId)) {
        return;
      }

      this.selectedCourseId = '';
    },
    defaultCourseSelectionId() {
      return '';
    },
    syncAssessmentIndicatorsCourse() {
      if (!this.filteredCourses.length) {
        this.assessmentIndicatorsCourseId = '';
        return;
      }

      if (this.filteredCourses.some((course) => course.id === this.assessmentIndicatorsCourseId)) {
        return;
      }

      this.assessmentIndicatorsCourseId = this.filteredCourses[0].id;
    },
    navigateBack() {
      this.$router.push({ name: 'dashboard' });
    },
    openCourseCreateDialog(mode = 'questions', questionType = '') {
      this.courseCreateTitle = '';
      this.courseCreateMode = mode;
      this.courseCreateQuestionType = questionType;
      this.courseCreateTemplateDraft = '';
      this.courseCreateVideoUrlDraft = '';
      this.courseCreateDescriptionDraft = '';
      this.courseCreateSubmitting = false;
      this.courseCreateDialogOpen = true;
    },
    closeCourseCreateDialog() {
      this.courseCreateDialogOpen = false;
      this.courseCreateTitle = '';
      this.courseCreateMode = 'questions';
      this.courseCreateQuestionType = '';
      this.courseCreateTemplateDraft = '';
      this.courseCreateVideoUrlDraft = '';
      this.courseCreateDescriptionDraft = '';
      this.courseCreateSubmitting = false;
    },
    normalizeTaskTemplateContent(value = this.templateDraft) {
      return typeof value === 'string' ? value : String(value ?? '');
    },
    normalizeTaskVideoUrl(value = this.taskVideoUrlDraft) {
      return typeof value === 'string' ? value.trim() : String(value ?? '').trim();
    },
    normalizeTaskDescription(value = this.taskDescriptionDraft) {
      return typeof value === 'string' ? value.trim() : String(value ?? '').trim();
    },
    async saveNewCourse() {
      const title = this.courseCreateTitle.trim();
      const taskPoints = Math.max(0, Number(this.taskPointsDraft) || 0);
      const taskCreateMode = this.courseCreateMode;
      const taskCreateQuestionType = this.courseCreateQuestionType;
      const normalizedTemplateContent = this.normalizeTaskTemplateContent(this.courseCreateTemplateDraft);
      const normalizedTaskVideoUrl = this.normalizeTaskVideoUrl(this.courseCreateVideoUrlDraft);
      const normalizedTaskDescription = this.normalizeTaskDescription(this.courseCreateDescriptionDraft);

      if (!title) {
        this.$toast.error(this.isTasksPage ? 'أدخل اسم المهمة الأدائية أولًا' : 'أدخل اسم الدورة أولًا');
        return;
      }

      this.courseCreateSubmitting = true;

      try {
        const result = await this.addCourse({
          title,
          entityType: this.isTasksPage ? 'task' : 'course',
          youtubeUrl: this.isTasksPage ? normalizedTaskVideoUrl : '',
          taskDescription: this.isTasksPage && taskCreateMode === 'document' ? normalizedTaskDescription : '',
          taskMode: this.isTasksPage ? taskCreateMode : null,
          ...(this.isTasksPage && taskCreateMode === 'document' ? { taskTemplateContent: normalizedTemplateContent } : {}),
        });
        const createdCourseId = result?.id || result?.course?.id || '';
        this.$toast.success(this.isTasksPage ? 'تمت إضافة المهمة الأدائية' : 'تمت إضافة الدورة');

        if (this.isTasksPage && createdCourseId && taskCreateMode === 'document') {
          await this.addQuestion({
            courseId: createdCourseId,
            question: {
              assessmentType: 'tasks',
              prompt: 'إرفاق ملف المهمة الأدائية',
              type: 'text',
              options: [],
              allowFile: true,
              points: taskPoints,
              correctAnswer: '',
            },
          });
        }

        this.closeCourseCreateDialog();
        if (!this.isTasksPage) {
          this.viewMode = 'attendance';
        }

        this.$nextTick(() => {
          if (createdCourseId && this.filteredCourses.some((course) => course.id === createdCourseId)) {
            this.selectedCourseId = createdCourseId;

            if (this.isTasksPage && taskCreateMode === 'questions' && taskCreateQuestionType) {
              this.questionForms = [{
                ...emptyQuestionForm(),
                type: taskCreateQuestionType,
                options: taskCreateQuestionType === 'multiple' ? ['', ''] : [],
                points: String(taskPoints),
              }];
              this.questionErrors = [''];
            }

            return;
          }

          const createdCourse = this.filteredCourses.find((course) => course.title === title);
          this.selectedCourseId = createdCourse?.id || this.selectedCourseId;
        });
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ الدورة');
      } finally {
        this.courseCreateSubmitting = false;
      }
    },
    switchAssessmentType(type) {
      if (type === this.assessmentType) {
        return;
      }

      this.$router.push({
        name: 'admin-assessment',
        params: { assessmentType: type },
        query: this.selectedCourseId ? { courseId: this.selectedCourseId } : {},
      });
    },
    openCourseQuestions(courseId, type) {
      this.selectedCourseId = courseId;
      this.viewMode = type;
      this.selectedDetailAssessmentType = type;
    },
    openCourseAssessmentHub(courseId) {
      this.selectedCourseId = courseId;
      this.viewMode = this.isTasksPage ? this.viewMode : this.assessmentType;
      this.selectedDetailAssessmentType = this.isTasksPage ? 'tasks' : this.assessmentType;
    },
    switchDetailAssessmentType(type) {
      if (!['pre', 'post'].includes(type) || this.detailAssessmentType === type) {
        return;
      }

      this.viewMode = type;
      this.selectedDetailAssessmentType = type;
    },
    closeQuestionDetails() {
      if (this.courseIdOverride) {
        this.$emit('close-course');
        return;
      }

      this.selectedCourseId = '';
      this.selectedDetailAssessmentType = '';
    },
    openAssessmentAvailabilityDialog(courseId, type, branch = 'all', preserveActiveBranches = false) {
      const resolvedType = this.resolveActionAssessmentType(type);
      const resolvedCourseId = this.resolveDialogCourseId(courseId);
      const course = this.filteredCourses.find((item) => item.id === resolvedCourseId) || this.selectedCourse;
      const currentWindow = branch === 'all'
        ? this.getWindowMeta(course?.assessmentWindows?.global?.[resolvedType])
        : this.getWindowMeta(course?.assessmentWindows?.[branch]?.[resolvedType]);

      this.assessmentAvailabilityCourseId = course?.id || resolvedCourseId;
      this.assessmentAvailabilityType = resolvedType;
      this.assessmentAvailabilityBranch = this.managedBranchId || branch;
      this.assessmentAvailabilityPreserveActiveBranches = preserveActiveBranches;
      this.assessmentAvailabilityMinutes = Number(currentWindow.durationMinutes) > 0 ? Number(currentWindow.durationMinutes) : 60;
      this.assessmentAvailabilitySubmitting = false;
      this.assessmentAvailabilityDialogOpen = true;
    },
    closeAssessmentAvailabilityDialog() {
      this.assessmentAvailabilityDialogOpen = false;
      this.assessmentAvailabilityCourseId = '';
      this.assessmentAvailabilityType = 'pre';
      this.assessmentAvailabilityBranch = this.managedBranchId || 'all';
      this.assessmentAvailabilityPreserveActiveBranches = false;
      this.assessmentAvailabilityMinutes = 60;
      this.assessmentAvailabilitySubmitting = false;
    },
    async confirmAssessmentAvailability() {
      const course = this.filteredCourses.find((item) => item.id === this.assessmentAvailabilityCourseId) || this.selectedCourse;
      const durationMinutes = Number(this.assessmentAvailabilityMinutes);
      const targetBranch = this.managedBranchId || this.assessmentAvailabilityBranch;

      if (!course) {
        this.$toast.error('تعذر تحديد الدورة الحالية. أعد اختيار الدورة ثم حاول مرة أخرى.');
        return;
      }

      if (!Number.isFinite(durationMinutes) || durationMinutes < 1) {
        this.$toast.error('أدخل مدة صحيحة بالدقائق');
        return;
      }

      if (!targetBranch) {
        this.$toast.error('اختر الفرع');
        return;
      }

      const now = new Date();
      const closesAt = new Date(now.getTime() + (durationMinutes * 60 * 1000)).toISOString();
      const windowPayload = {
        opensAt: now.toISOString(),
        closesAt,
        durationMinutes,
      };
      const targetBranches = targetBranch === 'all' ? ['male', 'female'] : [targetBranch];
      const otherBranches = ['male', 'female'].filter((branchId) => !targetBranches.includes(branchId));
      const branchAvailability = {
        male: { ...(course.branchAvailability?.male || {}) },
        female: { ...(course.branchAvailability?.female || {}) },
      };
      const nextWindows = {
        global: { ...(course.assessmentWindows?.global || {}) },
        male: { ...(course.assessmentWindows?.male || {}) },
        female: { ...(course.assessmentWindows?.female || {}) },
      };

      nextWindows.global[this.assessmentAvailabilityType] = targetBranch === 'all' ? windowPayload : undefined;

      targetBranches.forEach((branchId) => {
        branchAvailability[branchId] = {
          ...branchAvailability[branchId],
          [this.assessmentAvailabilityType]: true,
        };
        nextWindows[branchId] = {
          ...nextWindows[branchId],
          [this.assessmentAvailabilityType]: windowPayload,
        };
      });

      otherBranches.forEach((branchId) => {
        const shouldPreserve = this.assessmentAvailabilityPreserveActiveBranches && this.isAssessmentBranchActive(course, this.assessmentAvailabilityType, branchId);

        branchAvailability[branchId] = {
          ...branchAvailability[branchId],
          [this.assessmentAvailabilityType]: shouldPreserve,
        };
        nextWindows[branchId] = {
          ...nextWindows[branchId],
          [this.assessmentAvailabilityType]: shouldPreserve ? nextWindows[branchId]?.[this.assessmentAvailabilityType] : undefined,
        };
      });

      const nextSettings = course.isActive
        ? {
          pre: course.isPreEnabled,
          post: course.isPostEnabled,
          tasks: course.isTasksEnabled,
        }
        : { pre: false, post: false, tasks: false };

      nextSettings[this.assessmentAvailabilityType] = true;

      this.assessmentAvailabilitySubmitting = true;

      try {
        await this.updateCourse({
          courseId: course.id,
          updates: {
            branchAvailability,
            assessmentWindows: nextWindows,
          },
        });
        await this.activateCourse({
          courseId: course.id,
          settings: nextSettings,
        });
        this.$toast.success(`تم فتح ${this.availabilityDialogLabel}`);
        this.closeAssessmentAvailabilityDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر فتح الاختبار');
      } finally {
        this.assessmentAvailabilitySubmitting = false;
      }
    },
    getAssessmentManageOptions(course, type) {
      const maleActive = this.isAssessmentBranchActive(course, type, 'male');
      const femaleActive = this.isAssessmentBranchActive(course, type, 'female');

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
          { value: 'open_female', label: 'فتح معلمات' },
          { value: 'open_all', label: 'فتح الكل' },
        ];
      }

      if (femaleActive) {
        return [
          { value: 'close_female', label: 'إغلاق معلمات' },
          { value: 'open_male', label: 'فتح معلمين' },
          { value: 'open_all', label: 'فتح الكل' },
        ];
      }

      return [
        { value: 'open_all', label: 'فتح الكل' },
        { value: 'open_male', label: 'فتح معلمين' },
        { value: 'open_female', label: 'فتح معلمات' },
      ];
    },
    openAssessmentManageDialog(courseId, type) {
      const resolvedType = this.resolveActionAssessmentType(type);
      const resolvedCourseId = this.resolveDialogCourseId(courseId);
      const course = this.filteredCourses.find((item) => item.id === resolvedCourseId) || this.selectedCourse;

      if (!course) {
        return;
      }

      const options = this.getAssessmentManageOptions(course, resolvedType);
      this.assessmentManageCourseId = course.id || resolvedCourseId;
      this.assessmentManageType = resolvedType;
      this.assessmentManageChoice = options[0]?.value || '';
      this.assessmentManageSubmitting = false;
      this.assessmentManageDialogOpen = true;
    },
    closeAssessmentManageDialog() {
      this.assessmentManageDialogOpen = false;
      this.assessmentManageCourseId = '';
      this.assessmentManageType = 'pre';
      this.assessmentManageChoice = '';
      this.assessmentManageSubmitting = false;
    },
    async closeAssessmentAvailability(courseId, type, branchId = '') {
      const course = this.filteredCourses.find((item) => item.id === courseId);

      if (!course) {
        return;
      }

      const enabledKey = this.assessmentEnabledKey(type);

      try {
        if (branchId) {
          const remainingBranch = branchId === 'male' ? 'female' : 'male';
          const remainingActive = this.isAssessmentBranchActive(course, type, remainingBranch);

          await this.updateCourse({
            courseId,
            updates: {
              [enabledKey]: remainingActive,
              branchAvailability: {
                ...(course.branchAvailability || {}),
                [branchId]: {
                  ...(course.branchAvailability?.[branchId] || {}),
                  [type]: false,
                },
              },
              assessmentWindows: {
                ...(course.assessmentWindows || {}),
                global: {
                  ...(course.assessmentWindows?.global || {}),
                  [type]: undefined,
                },
                [branchId]: {
                  ...(course.assessmentWindows?.[branchId] || {}),
                  [type]: undefined,
                },
              },
            },
          });
        } else {
          await this.updateCourse({
            courseId,
            updates: {
              [enabledKey]: false,
              branchAvailability: {
                male: {
                  ...(course.branchAvailability?.male || {}),
                  [type]: false,
                },
                female: {
                  ...(course.branchAvailability?.female || {}),
                  [type]: false,
                },
              },
              assessmentWindows: {
                ...(course.assessmentWindows || {}),
                global: {
                  ...(course.assessmentWindows?.global || {}),
                  [type]: undefined,
                },
                male: {
                  ...(course.assessmentWindows?.male || {}),
                  [type]: undefined,
                },
                female: {
                  ...(course.assessmentWindows?.female || {}),
                  [type]: undefined,
                },
              },
            },
          });
        }

        this.$toast.success(`تم تحديث ${this.assessmentLabels[type] || 'الاختبار'}`);
        this.closeAssessmentManageDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحديث حالة الاختبار');
      }
    },
    resolveActionAssessmentType(type) {
      if (this.isTasksPage) {
        return 'tasks';
      }

      if (this.embedded) {
        if (this.currentAssessmentActionType) {
          return this.currentAssessmentActionType;
        }

        if (this.detailAssessmentType === 'pre' || this.detailAssessmentType === 'post') {
          return this.detailAssessmentType;
        }
      }

      return ['pre', 'post', 'tasks'].includes(type) ? type : this.assessmentType;
    },
    resolveDialogCourseId(courseId) {
      return courseId || this.selectedCourse?.id || this.selectedCourseId || this.resolvedCourseId || '';
    },
    async confirmAssessmentManageAction() {
      if (!this.assessmentManageCourseId || !this.assessmentManageChoice) {
        return;
      }

      if (this.assessmentManageChoice.startsWith('close_')) {
        this.assessmentManageSubmitting = true;

        try {
          const branch = this.assessmentManageChoice === 'close_all' ? '' : this.assessmentManageChoice.replace('close_', '');
          await this.closeAssessmentAvailability(this.assessmentManageCourseId, this.assessmentManageType, branch);
        } finally {
          this.assessmentManageSubmitting = false;
        }

        return;
      }

      const branch = this.assessmentManageChoice === 'open_all' ? 'all' : this.assessmentManageChoice.replace('open_', '');
      this.closeAssessmentManageDialog();
      this.openAssessmentAvailabilityDialog(this.assessmentManageCourseId, this.assessmentManageType, branch, branch !== 'all');
    },
    openCourseEditDialog(course) {
      this.courseEditId = course.id;
      this.courseEditTitle = course.title || '';
      this.courseEditSubmitting = false;
      this.courseEditDialogOpen = true;
    },
    requestManagedCourseDelete(course) {
      this.courseDeleteId = course.id;
      this.courseDeleteTitle = course.title || '';
      this.assessmentDeleteSubmitting = false;
      this.courseDeleteDialogOpen = true;
    },
    closeCourseEditDialog() {
      this.courseEditDialogOpen = false;
      this.courseEditId = '';
      this.courseEditTitle = '';
      this.courseEditSubmitting = false;
    },
    closeCourseDeleteDialog() {
      this.courseDeleteDialogOpen = false;
      this.courseDeleteId = '';
      this.courseDeleteTitle = '';
      this.assessmentDeleteSubmitting = false;
    },
    async saveCourseEdit() {
      if (!this.courseEditId || !this.courseEditTitle.trim()) {
        return;
      }

      this.courseEditSubmitting = true;

      try {
        await this.updateCourse({
          courseId: this.courseEditId,
          updates: {
            title: this.courseEditTitle.trim(),
          },
        });
        this.$toast.success('تم تعديل اسم الدورة');
        this.closeCourseEditDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تعديل اسم الدورة');
      } finally {
        this.courseEditSubmitting = false;
      }
    },
    async confirmManagedCourseDelete() {
      if (!this.courseDeleteId) {
        return;
      }

      const courseId = this.courseDeleteId;

      this.deletingCourseId = courseId;
      this.assessmentDeleteSubmitting = true;

      try {
        await this.deleteCourse(courseId);

        if (this.selectedCourseId === courseId) {
          this.closeQuestionDetails();
        }

        if (this.courseEditId === courseId) {
          this.closeCourseEditDialog();
        }

        this.$toast.success(this.isTasksPage ? 'تم حذف المهمة' : 'تم حذف الدورة');
        this.closeCourseDeleteDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || (this.isTasksPage ? 'تعذر حذف المهمة' : 'تعذر حذف الدورة'));
      } finally {
        this.deletingCourseId = '';
        this.assessmentDeleteSubmitting = false;
      }
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
      if (!this.selectedCourseId) {
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
          courseId: this.selectedCourseId,
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
    resetQuestionForms() {
      this.questionForms = [];
      this.questionErrors = [];
      this.isSaving = false;
    },
    updateForm(index, patch) {
      this.questionForms = this.questionForms.map((form, formIndex) => (formIndex === index ? { ...form, ...patch } : form));
    },
    clearFormError(index) {
      this.questionErrors = this.questionErrors.map((error, errorIndex) => (errorIndex === index ? '' : error));
    },
    handleOptionChange(formIndex, optionIndex, value) {
      this.questionForms = this.questionForms.map((form, index) => {
        if (index !== formIndex) {
          return form;
        }

        const previousOption = form.options[optionIndex] || '';
        const options = form.options.map((option, currentOptionIndex) => (currentOptionIndex === optionIndex ? value : option));
        const sanitizedOptions = options.map((option) => option.trim()).filter(Boolean);
        let correctAnswer = form.correctAnswer;

        if (this.normalizeAnswer(previousOption) === this.normalizeAnswer(form.correctAnswer)) {
          correctAnswer = value.trim();
        } else if (!sanitizedOptions.some((option) => this.normalizeAnswer(option) === this.normalizeAnswer(form.correctAnswer))) {
          correctAnswer = '';
        }

        return {
          ...form,
          options,
          correctAnswer,
        };
      });
    },
    selectCorrectOption(formIndex, optionIndex) {
      const optionValue = this.questionForms[formIndex]?.options?.[optionIndex]?.trim() || '';

      if (!optionValue) {
        return;
      }

      this.updateForm(formIndex, { correctAnswer: optionValue, correctAnswerTouched: true });
      this.clearFormError(formIndex);
    },
    handleExistingOptionChange(questionId, optionIndex, value) {
      const draft = this.questionDrafts[questionId];

      if (!draft) {
        return;
      }

      const previousOption = draft.options[optionIndex] || '';
      const options = draft.options.map((option, currentOptionIndex) => (currentOptionIndex === optionIndex ? value : option));
      const sanitizedOptions = options.map((option) => option.trim()).filter(Boolean);
      let correctAnswer = draft.correctAnswer;

      if (this.normalizeAnswer(previousOption) === this.normalizeAnswer(draft.correctAnswer)) {
        correctAnswer = value.trim();
      } else if (!sanitizedOptions.some((option) => this.normalizeAnswer(option) === this.normalizeAnswer(draft.correctAnswer))) {
        correctAnswer = '';
      }

      this.updateQuestionDraft(questionId, { options, correctAnswer });
    },
    handleExistingAddOptionField(questionId) {
      const draft = this.questionDrafts[questionId];

      if (!draft) {
        return;
      }

      this.updateQuestionDraft(questionId, { options: [...draft.options, ''] });
    },
    handleExistingOptionPaste(questionId, optionIndex, event) {
      const draft = this.questionDrafts[questionId];

      if (!draft) {
        return;
      }

      const pastedOptions = splitPastedQuestionOptions(event.clipboardData.getData('text'));

      if (pastedOptions.length < 2) {
        return;
      }

      event.preventDefault();
      const options = [...draft.options];

      while (options.length < optionIndex + pastedOptions.length) {
        options.push('');
      }

      pastedOptions.forEach((option, pastedIndex) => {
        options[optionIndex + pastedIndex] = option;
      });

      const sanitizedOptions = options.map((option) => option.trim()).filter(Boolean);
      const correctAnswer = sanitizedOptions.some((option) => this.normalizeAnswer(option) === this.normalizeAnswer(draft.correctAnswer))
        ? draft.correctAnswer
        : '';

      this.updateQuestionDraft(questionId, { options, correctAnswer });
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
      const normalizedOption = this.normalizeAnswer(option);
      const normalizedAnswer = this.normalizeAnswer(draft?.correctAnswer);

      if (!draft?.correctAnswerTouched || !normalizedOption || !normalizedAnswer) {
        return false;
      }

      return normalizedOption === normalizedAnswer;
    },
    handleExistingPromptPaste(questionId, event) {
      const draft = this.questionDrafts[questionId];

      if (!draft) {
        return;
      }

      const text = event.clipboardData.getData('text');

      if (!text.trim()) {
        return;
      }

      const importedQuestions = parseImportedQuestionsFromText(text);

      if (importedQuestions.length < 2) {
        return;
      }

      event.preventDefault();
      const rawPoints = Number(draft.points ?? '1');
      const defaultPoints = Number.isFinite(rawPoints) && rawPoints >= 0 ? rawPoints : 1;
      const preferredType = draft.type ?? 'multiple';
      const mappedQuestions = importedQuestions.map((question) => this.mapImportedDraftToForm(question, defaultPoints, preferredType));

      this.questionForms = [...this.questionForms, ...mappedQuestions];
      this.questionErrors = [...this.questionErrors, ...mappedQuestions.map(() => '')];
    },
    isCorrectDraftOption(form, option) {
      const normalizedOption = this.normalizeAnswer(option);
      const normalizedAnswer = this.normalizeAnswer(form.correctAnswer);

      if (!form.correctAnswerTouched || !normalizedOption || !normalizedAnswer) {
        return false;
      }

      return normalizedOption === normalizedAnswer;
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
          correctAnswer: sanitizedOptions.some((option) => this.normalizeAnswer(option) === this.normalizeAnswer(form.correctAnswer)) ? form.correctAnswer : '',
        };
      });
    },
    mapImportedDraftToForm(draft, defaultPoints, preferredType) {
      const effectiveType = draft.type === 'multiple' ? 'multiple' : (preferredType === 'multiple' && draft.options.length >= 2 ? 'multiple' : 'text');

      return {
        ...emptyQuestionForm(),
        prompt: draft.prompt,
        type: effectiveType,
        options: effectiveType === 'multiple' ? (draft.options.length >= 2 ? draft.options : ['', '']) : [],
        points: String(defaultPoints),
        correctAnswer: '',
      };
    },
    handlePromptPaste(formIndex, event) {
      const text = event.clipboardData.getData('text');

      if (!text.trim()) {
        return;
      }

      const importedQuestions = parseImportedQuestionsFromText(text);

      if (importedQuestions.length < 2) {
        return;
      }

      event.preventDefault();
      const rawPoints = Number(this.questionForms[formIndex]?.points ?? '1');
      const defaultPoints = Number.isFinite(rawPoints) && rawPoints >= 0 ? rawPoints : 1;
      const preferredType = this.questionForms[formIndex]?.type ?? 'multiple';
      const mappedQuestions = importedQuestions.map((question) => this.mapImportedDraftToForm(question, defaultPoints, preferredType));

      this.questionForms = [
        ...this.questionForms.slice(0, formIndex),
        ...mappedQuestions,
        ...this.questionForms.slice(formIndex + 1),
      ];
      this.questionErrors = [
        ...this.questionErrors.slice(0, formIndex),
        ...mappedQuestions.map(() => ''),
        ...this.questionErrors.slice(formIndex + 1),
      ];
    },
    handleAddQuestionSlot(type = 'multiple') {
      if (this.isTasksPage && !this.selectedCourse) {
        if (type === 'document') {
          this.openCourseCreateDialog('document');
          return;
        }

        this.openCourseCreateDialog('questions', type);
        return;
      }

      if (type === 'document') {
        this.switchTaskToDocumentMode();
        return;
      }

      const nextForm = {
        ...emptyQuestionForm(),
        type,
        options: type === 'multiple' ? ['', ''] : [],
        points: this.isTasksPage ? String(Math.max(0, Number(this.taskPointsDraft) || 0)) : '1',
      };

      this.questionForms = [...this.questionForms, nextForm];
      this.questionErrors = [...this.questionErrors, ''];
    },
    handleTaskPointsInput(value) {
      const normalizedPoints = String(value ?? '').trim();

      this.taskPointsDraft = normalizedPoints;

      if (!this.isTasksPage) {
        return;
      }

      this.questionForms = this.questionForms.map((form) => ({
        ...form,
        points: normalizedPoints,
      }));

      const updatedDrafts = { ...this.questionDrafts };

      this.visibleSelectedQuestions.forEach((question) => {
        if (!updatedDrafts[question.id]) {
          return;
        }

        updatedDrafts[question.id] = {
          ...updatedDrafts[question.id],
          points: normalizedPoints,
        };
      });

      this.questionDrafts = updatedDrafts;
    },
    handleRemoveQuestionSlot(index) {
      if (this.questionForms.length <= 1) {
        this.resetQuestionForms();
        return;
      }

      this.questionForms = this.questionForms.filter((_, currentIndex) => currentIndex !== index);
      this.questionErrors = this.questionErrors.filter((_, currentIndex) => currentIndex !== index);
    },
    async handleSaveAllQuestions() {
      if (!this.selectedCourse || this.isSaving) {
        return;
      }

      let hasError = false;
      const nextDraftErrors = {};
      const visibleQuestionIds = this.visibleSelectedQuestions.map((question) => question.id);

      visibleQuestionIds.forEach((questionId) => {
        const validationError = this.validateQuestionDraft(this.questionDrafts[questionId]);
        nextDraftErrors[questionId] = validationError;

        if (validationError) {
          hasError = true;
        }
      });

      const nextErrors = this.questionForms.map((form) => {
        const prompt = form.prompt.trim();
        const options = form.type === 'multiple'
          ? form.options.map((option) => option.trim()).filter(Boolean)
          : [];

        if (!prompt) {
          hasError = true;
          return 'أدخل السؤال.';
        }

        if (form.type === 'multiple' && options.length < 2) {
          hasError = true;
          return 'أدخل خيارين على الأقل.';
        }

        if (form.type === 'multiple' && !form.correctAnswer.trim()) {
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
        if (this.isTasksPage) {
          await this.updateCourse({
            courseId: this.selectedCourse.id,
            updates: {
              youtubeUrl: this.normalizeTaskVideoUrl(),
            },
          });
        }

        for (const question of this.visibleSelectedQuestions) {
          const draft = this.questionDrafts[question.id];

          await this.updateQuestion({
            questionId: question.id,
            question: {
              prompt: draft.prompt.trim(),
              type: draft.type,
              options: draft.type === 'multiple' ? draft.options.map((option) => option.trim()).filter(Boolean) : [],
              allowFile: false,
              points: Number(this.isTasksPage ? this.taskPointsDraft : draft.points),
              correctAnswer: draft.type === 'multiple' ? draft.correctAnswer.trim() : '',
            },
          });
        }

        for (const questionId of this.pendingDeletedQuestionIds) {
          await this.deleteQuestion(questionId);
        }

        for (const form of this.questionForms) {
          const options = form.type === 'multiple'
            ? form.options.map((option) => option.trim()).filter(Boolean)
            : [];

          await this.addQuestion({
            courseId: this.selectedCourse.id,
            question: {
              assessmentType: this.detailAssessmentType,
              prompt: form.prompt.trim(),
              type: form.type,
              options,
              allowFile: false,
              points: Number(this.isTasksPage ? this.taskPointsDraft : form.points),
              correctAnswer: form.type === 'multiple' ? form.correctAnswer.trim() : '',
            },
          });
        }

        this.$toast.success('تم حفظ الأسئلة بنجاح');
        this.resetQuestionForms();
        this.pendingDeletedQuestionIds = [];
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ الأسئلة');
      } finally {
        this.isSaving = false;
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
    async saveTemplate() {
      if (!this.selectedCourse) {
        return;
      }

      this.templateSaving = true;

      try {
        const normalizedTemplateContent = this.normalizeTaskTemplateContent();
        const normalizedTaskVideoUrl = this.normalizeTaskVideoUrl();
        const normalizedTaskDescription = this.normalizeTaskDescription();
        const attachmentQuestion = this.selectedQuestions.find((question) => question.allowFile) || this.selectedQuestions[0] || null;

        if (attachmentQuestion) {
          await this.updateQuestion({
            questionId: attachmentQuestion.id,
            question: {
              prompt: attachmentQuestion.allowFile ? 'إرفاق ملف المهمة الأدائية' : attachmentQuestion.prompt,
              type: 'text',
              options: [],
              allowFile: true,
              points: Number(this.taskPointsDraft),
              correctAnswer: '',
              attachmentName: attachmentQuestion.attachmentName || '',
              attachmentType: attachmentQuestion.attachmentType || '',
              attachmentDataUrl: attachmentQuestion.attachmentDataUrl || '',
            },
          });
        }

        await this.updateCourse({
          courseId: this.selectedCourse.id,
          updates: {
            taskTemplateContent: normalizedTemplateContent,
            youtubeUrl: normalizedTaskVideoUrl,
            taskDescription: normalizedTaskDescription,
          },
        });
        this.$toast.success('تم حفظ إعدادات المهمة بنجاح');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ إعدادات المهمة');
      } finally {
        this.templateSaving = false;
      }
    },
    async switchTaskToDocumentMode() {
      if (!this.isTasksPage || !this.selectedCourse) {
        return;
      }

      this.templateSaving = true;

      try {
        const normalizedTemplateContent = this.normalizeTaskTemplateContent();
        const normalizedTaskVideoUrl = this.normalizeTaskVideoUrl();
        const normalizedTaskDescription = this.normalizeTaskDescription();
        await this.updateCourse({
          courseId: this.selectedCourse.id,
          updates: {
            taskMode: 'document',
            taskTemplateContent: normalizedTemplateContent,
            youtubeUrl: normalizedTaskVideoUrl,
            taskDescription: normalizedTaskDescription,
          },
        });

        const attachmentQuestion = this.selectedQuestions.find((question) => question.allowFile);

        if (!attachmentQuestion) {
          await this.addQuestion({
            courseId: this.selectedCourse.id,
            question: {
              assessmentType: 'tasks',
              prompt: 'إرفاق ملف المهمة الأدائية',
              type: 'text',
              options: [],
              allowFile: true,
              points: Math.max(0, Number(this.taskPointsDraft) || 0),
              correctAnswer: '',
            },
          });
        } else {
          await this.updateQuestion({
            questionId: attachmentQuestion.id,
            question: {
              prompt: 'إرفاق ملف المهمة الأدائية',
              type: 'text',
              options: [],
              allowFile: true,
              points: Math.max(0, Number(this.taskPointsDraft) || 0),
              correctAnswer: '',
              attachmentName: attachmentQuestion.attachmentName || '',
              attachmentType: attachmentQuestion.attachmentType || '',
              attachmentDataUrl: attachmentQuestion.attachmentDataUrl || '',
            },
          });
        }

        this.resetQuestionForms();
        this.$toast.success('تم تحويل المهمة إلى وورد');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحويل المهمة إلى وورد');
      } finally {
        this.templateSaving = false;
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
  direction: rtl;
  background:
    radial-gradient(circle at top, rgba(16, 185, 129, 0.12), transparent 38%),
    linear-gradient(180deg, #f7fcfb 0%, #eff8f7 100%);
}

.assessment-page--embedded {
  min-height: auto;
  overflow: visible;
  border-radius: 0;
  background: transparent;
}

.assessment-page__glow {
  position: absolute;
  border-radius: 999px;
  pointer-events: none;
  filter: blur(18px);
  opacity: 0.5;
}

.assessment-page__glow--one {
  top: 40px;
  left: 8%;
  width: 260px;
  height: 260px;
  background: radial-gradient(circle, rgba(45, 212, 191, 0.25), transparent 70%);
}

.assessment-page__glow--two {
  top: 120px;
  right: 4%;
  width: 340px;
  height: 340px;
  background: radial-gradient(circle, rgba(8, 65, 89, 0.12), transparent 70%);
}

.assessment-page__container {
  position: relative;
  z-index: 1;
  max-width: 1120px;
}

.assessment-page--embedded .assessment-page__container {
  max-width: none;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.assessment-page__mobile-back {
  display: none;
  justify-content: flex-start;
  margin-bottom: 16px;
}

.assessment-mobile-back {
  width: 44px;
  height: 44px;
}

.assessment-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 24px;
}

.assessment-tabs__item {
  min-width: 136px;
  font-size: 0.95rem;
}

.assessment-alert {
  border-radius: 20px;
}

.assessment-layout {
  display: grid;
  gap: 24px;
}

.assessment-toolbar {
  display: grid;
  grid-template-columns: minmax(260px, 1.2fr) minmax(220px, 0.9fr);
  gap: 16px;
  margin-bottom: 24px;
  padding: 22px;
  border: 1px solid rgba(214, 229, 238, 0.82);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
}

.assessment-toolbar--embedded {
  margin-bottom: 24px;
  padding: 0 0 20px;
  border: 0;
  border-bottom: 1px solid rgba(214, 229, 238, 0.9);
  border-radius: 0;
  background: transparent;
  box-shadow: none;
}

.assessment-toolbar__field {
  min-width: 0;
}

.assessment-select-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
}

.assessment-select-option__label {
  min-width: 0;
  flex: 1;
}

.assessment-select-option__delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: none;
  background: transparent;
  color: #c13636;
  cursor: pointer;
  transition: color 0.2s ease, opacity 0.2s ease;
}

.assessment-select-option__delete:hover:not(:disabled) {
  color: #9f1f1f;
}

.assessment-select-option__delete:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.assessment-toolbar--tasks-document {
  grid-template-columns: minmax(240px, 1.2fr) minmax(160px, 0.55fr) minmax(220px, 0.95fr);
}

.assessment-toolbar__field--full {
  grid-column: 1 / -1;
}

.assessment-toolbar__aux-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
  gap: 14px;
  margin-top: 14px;
}

.assessment-toolbar__label {
  display: block;
  margin: 0 0 10px;
  color: #123f56;
  font-size: 0.95rem;
  font-weight: 900;
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

.prep-card__status {
  margin-top: 8px;
  color: #6c8193;
  font-size: 0.95rem;
}

.prep-card__status--inline {
  margin: 0 0 18px;
}

.prep-card__status--saving {
  color: #1787a6;
}

.prep-filters {
  display: grid;
  grid-template-columns: minmax(220px, 0.95fr) 54px;
  align-items: end;
  gap: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid #e8eef4;
}

.prep-filters--single {
  grid-template-columns: minmax(240px, 1fr) 54px;
}

.prep-filters__toggle {
  align-self: start;
  margin-top: 42px;
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

.assessment-cards-shell {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
  gap: 20px;
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

.assessment-course-card__actions {
  display: inline-flex;
  align-items: flex-start;
  gap: 6px;
  direction: ltr;
}

.assessment-course-card__icon {
  width: auto;
  height: auto;
  padding: 0;
}

.assessment-course-card__icon .app-action-icon {
  color: inherit;
  font-size: 18px;
}

.assessment-course-card__icon:focus,
.assessment-course-card__icon:focus-visible {
  outline: none;
  box-shadow: none;
}

.assessment-course-card__icon :deep(.v-icon) {
  color: inherit !important;
  font-size: 18px !important;
  opacity: 1 !important;
  display: inline-flex !important;
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

.assessment-availability-branch-picker {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.assessment-availability-branch-picker__button {
  min-height: 44px;
  border: 1px solid rgba(214, 229, 238, 0.95);
  border-radius: 14px;
  background: rgba(248, 251, 253, 0.72);
  color: #4d6577;
  font-size: 0.92rem;
  font-weight: 800;
}

.assessment-availability-branch-picker__button--active {
  border-color: rgba(13, 90, 115, 0.18);
  background: #0d5a73;
  color: #fff;
}

.assessment-course-card__menu {
  display: grid;
  min-width: 180px;
  padding: 6px 0;
  border: 0;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 14px 28px rgba(17, 65, 96, 0.14);
  overflow: hidden;
}

.assessment-course-card__menu-item {
  min-height: 44px;
  padding: 0 18px;
  color: #21465f;
  font-size: 1rem;
  font-weight: 700;
  text-align: right;
}

.assessment-course-card__menu-item:hover {
  background: rgba(31, 111, 150, 0.08);
}

.assessment-card,
.assessment-empty-card,
.assessment-dialog,
.assessment-question {
  border: 1px solid rgba(255, 255, 255, 0.82);
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  backdrop-filter: blur(10px);
}

.assessment-card {
  border-radius: 28px;
  padding: 28px;
}

.assessment-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.assessment-card__corner-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  margin-inline-start: auto;
  border: 0;
  background: transparent;
  color: #dc2626;
  transition: transform 0.18s ease, color 0.18s ease;
}

.assessment-card__corner-action:hover:not(:disabled) {
  transform: translateY(-1px);
  color: #b91c1c;
}

.assessment-card__corner-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.assessment-card__header--actions-only {
  justify-content: flex-start;
}

.assessment-card__copy {
  min-width: 0;
}

.assessment-card__title,
.assessment-empty-card__title,
.assessment-dialog__title {
  margin: 0;
  color: #0f172a;
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.2;
}

.assessment-card__subtitle,
.assessment-empty-card__text {
  margin: 8px 0 0;
  color: #64748b;
  line-height: 1.8;
}

.assessment-add-button {
  min-height: 44px;
  font-size: 0.96rem;
}

.assessment-empty-state {
  border: 1px dashed rgba(148, 163, 184, 0.35);
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.74);
  padding: 18px;
  color: #64748b;
  font-size: 0.96rem;
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

.assessment-score-diff {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding-top: 72px; /* vertically center with rings approx */
  min-width: 0;
  text-align: center;
}

.assessment-score-diff :deep(.v-icon) {
  color: currentColor !important;
}

.assessment-score-diff__icon {
  font-size: 1.4rem !important;
  font-weight: 900;
}

.assessment-score-diff__value {
  font-size: 1.3rem;
  font-weight: 900;
}

.assessment-score-diff--positive {
  color: #0f9f63;
}

.assessment-score-diff--negative {
  color: #dc2626;
}

.assessment-score-diff--neutral {
  color: #64748b;
}

.assessment-accordion {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 12px;
}

.assessment-question {
  width: 100%;
  border-radius: 22px !important;
  overflow: hidden;
}

.assessment-question::before {
  display: none;
}

.assessment-question__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
  width: 100%;
}

.assessment-question__headline {
  display: grid;
  flex: 1;
  min-width: 0;
  gap: 6px;
  text-align: right;
}

.assessment-question__actions {
  display: inline-flex;
  align-items: flex-start;
  justify-content: flex-start;
  gap: 10px;
  flex-shrink: 0;
}

.assessment-question__title {
  color: #0f172a;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.8;
}

.assessment-question__points {
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 600;
}

.assessment-question__delete {
  width: 28px;
  height: 28px;
  padding: 0;
}

.assessment-question__chevron {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  color: #64748b;
  transition: transform 0.18s ease, background 0.18s ease, color 0.18s ease;
}

.assessment-question__chevron--open {
  transform: rotate(180deg);
  color: #0f172a;
}

.assessment-question__content {
  padding-top: 0;
}

.assessment-question__content ::v-deep .v-expansion-panel-content__wrap {
  padding: 0 24px 24px;
}

.assessment-question__answer-shell {
  display: grid;
  gap: 10px;
  width: 100%;
  padding-top: 8px;
  border-top: 1px solid rgba(148, 163, 184, 0.18);
}

.assessment-question__option,
.assessment-question__text-answer {
  color: #0f172a;
  font-size: 0.96rem;
  line-height: 1.9;
}

.assessment-question__text-answer {
  font-weight: 700;
}

.assessment-question__option--correct,
.assessment-question__text-answer {
  color: #047857;
  font-weight: 800;
}

.assessment-inline-list {
  display: grid;
  gap: 18px;
}

.assessment-inline-list__item {
  padding-bottom: 14px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.16);
}

.assessment-inline-list__item:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

.assessment-inline-list__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 12px;
}

@media (max-width: 700px) {
  .assessment-question__header {
    gap: 12px;
  }

  .assessment-question__title {
    font-size: 0.94rem;
  }
}

.assessment-empty-shell {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 240px;
  padding-top: 12px;
}

.assessment-empty-message {
  margin: 0;
  color: #0f172a;
  font-size: clamp(1.8rem, 3vw, 2.4rem);
  font-weight: 900;
  line-height: 1.2;
  text-align: center;
}

.assessment-primary-button,
.assessment-secondary-button,
.assessment-dialog__add-slot,
.assessment-options-grid__append {
  transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.assessment-primary-button--danger {
  background: #dc2626;
}

.assessment-template-shell {
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 24px;
  background: rgba(248, 250, 252, 0.72);
  padding: 12px;
}

.assessment-template-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 12px;
  margin-top: 16px;
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

.assessment-form-card--flat {
  background: transparent;
  padding: 0;
}

.assessment-form-card__topline {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.assessment-form-card__index,
.assessment-form-card__label {
  color: #0f172a;
  font-size: 0.92rem;
  font-weight: 800;
}

.assessment-form-card__label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.assessment-form-card__meta {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.assessment-form-card__trash {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  background: transparent;
  color: #ef4444;
}

.assessment-form-card__field-group {
  display: grid;
  gap: 8px;
  margin-top: 16px;
}

.assessment-form-card__field-group--compact {
  margin-top: 0;
}

.assessment-form-card__prompt-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 108px;
  gap: 12px;
  align-items: end;
}

.assessment-form-card__field-group--prompt {
  min-width: 0;
}

.assessment-form-card__field-group--points-inline {
  min-width: 0;
}

.assessment-select {
  width: 100%;
}

.assessment-input {
  direction: rtl;
}

.assessment-input--multiline {
  min-height: 108px;
  padding-top: 12px;
  padding-bottom: 12px;
  resize: vertical;
}

.assessment-input--points {
  text-align: center;
}

.assessment-dialog__spaced-input {
  margin-top: 14px;
}

.assessment-dialog__body--compact .assessment-dialog__spaced-input {
  margin-top: 20px;
}

.assessment-dialog__text--muted {
  margin-top: 12px;
  color: #64748b;
}

.assessment-input:focus,
.assessment-dialog__paste:focus,
.assessment-template-shell:focus-within {
  border-color: var(--app-primary-border);
  box-shadow: 0 0 0 3px var(--app-primary-soft);
}

.assessment-options-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: 1fr;
}

.assessment-options-grid__item {
  display: flex;
  align-items: stretch;
  gap: 8px;
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

.assessment-options-grid__append {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  min-width: 34px;
  border: 0;
  border-radius: 0;
  background: transparent;
  color: #156c82;
  box-shadow: none;
  padding: 0;
}

.assessment-options-grid__append:disabled {
  opacity: 0.45;
}

.assessment-options-grid__append :deep(.v-icon) {
  color: currentColor !important;
}

.assessment-form-card__error {
  margin: 12px 0 0;
  color: #dc2626;
  font-size: 0.88rem;
  font-weight: 700;
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
  width: 28px;
  min-width: 28px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #156c82;
  box-shadow: none;
}

.assessment-inline-builder__add :deep(.v-icon) {
  color: currentColor !important;
}

.assessment-inline-builder__add {
  width: 52px;
  height: 52px;
  justify-self: center;
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
  min-height: 46px;
  padding: 0 18px;
  color: #21465f;
  font-size: 0.96rem;
  font-weight: 700;
  text-align: right;
}

.assessment-inline-builder__menu-item:hover {
  background: rgba(31, 111, 150, 0.08);
}

.assessment-dialog__add-slot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 52px;
  border: 1px dashed var(--app-primary-border);
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.82);
  color: var(--app-primary);
  font-weight: 800;
}

@media (max-width: 960px) {
  .assessment-toolbar,
  .assessment-cards-shell,
  .assessment-card__header,
  .assessment-dialog__header,
  .assessment-dialog__footer {
    grid-template-columns: 1fr;
  }

  .assessment-card__header,
  .assessment-dialog__header,
  .assessment-dialog__footer,
  .assessment-course-card__top {
    flex-direction: column;
    align-items: stretch;
  }

  .assessment-form-card__triple-grid,
  .assessment-options-grid,
  .assessment-course-card__buttons {
    grid-template-columns: 1fr;
  }

  .assessment-form-card__prompt-row {
    grid-template-columns: 1fr;
  }

  .assessment-toolbar__aux-fields {
    grid-template-columns: 1fr;
  }

  .assessment-inline-builder__actions {
    flex-direction: column;
  }

  .assessment-indicators-card__controls,
  .assessment-indicators-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .assessment-indicators-panel {
    padding: 24px;
    align-items: center;
  }

  .assessment-score-diff {
    justify-content: center;
    padding-top: 0;
  }

  .assessment-indicators-card__filter-group {
    min-width: 0;
    width: 100%;
  }

  .assessment-availability-branch-picker {
    grid-template-columns: 1fr;
  }

  .prep-filters {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .assessment-page__mobile-back {
    display: flex;
  }

  .assessment-card,
  .assessment-empty-card,
  .assessment-dialog {
    border-radius: 24px;
  }

  .assessment-card,
  .assessment-empty-card {
    padding: 20px;
  }

  .assessment-template-shell {
    padding: 10px;
    border-radius: 20px;
  }

  .assessment-template-actions {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .assessment-template-actions > * {
    width: 100%;
  }

  .assessment-dialog__import-row,
  .assessment-dialog__body,
  .assessment-dialog__header,
  .assessment-dialog__footer {
    padding-left: 14px;
    padding-right: 14px;
  }

  .assessment-dialog__import-row {
    flex-direction: column;
  }

  .assessment-primary-button,
  .assessment-secondary-button,
  .assessment-add-button {
    width: 100%;
  }

  .assessment-tabs {
    display: grid;
    grid-template-columns: 1fr;
  }

  .prep-card {
    padding: 20px;
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
    padding: 10px 0;
    border-bottom: none;
    text-align: right;
  }

  .prep-table tbody td::before {
    content: '';
    color: #5f7790;
    font-size: 0.9rem;
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
}
</style>
