<template>
  <div class="people-page">
    <div
      v-if="dashboardError"
      class="people-alert people-alert--error"
    >
      {{ dashboardError }}
    </div>

    <section class="people-toolbar">
      <div class="people-toolbar__filters">
        <div
          v-if="!managedBranchId"
          class="people-filter-field"
        >
          <div class="people-filter-field__label">
            الفرع
          </div>
          <AppSelect
            v-model="selectedBranch"
            :items="branchFilterOptions"
            item-text="label"
            item-value="value"
            dense
            outlined
            hide-details
            class="people-select"
            @change="handleBranchFilterChange"
          />
        </div>

        <div class="people-filter-field">
          <div class="people-filter-field__label">
            الفلتر
          </div>
          <AppSelect
            v-model="selectedFilter"
            :items="filterOptions"
            item-text="label"
            item-value="value"
            dense
            outlined
            hide-details
            class="people-select"
          />
        </div>
      </div>

      <div class="people-toolbar__actions">
        <button
          type="button"
          class="people-toolbar-button people-toolbar-button--primary"
          @click="openCreateDialog"
        >
          إضافة
        </button>
      </div>
    </section>

    <section
      v-if="visiblePeopleCards.length === 0"
      class="people-empty-state"
    >
      {{ isReciterDirectoryMode ? 'لا يوجد مقرئون مطابقون للفلاتر الحالية.' : 'لا يوجد معلمون مطابقون للفلاتر الحالية.' }}
    </section>

    <section
      v-else
      class="people-cards-list"
    >
      <article
        v-for="person in visiblePeopleCards"
        :key="person.cardKey"
        class="people-card"
        :class="{ 'people-card--active': !isReciterDirectoryMode && selectedStudentId === person.id }"
        @click="!isReciterDirectoryMode && selectStudent(person.id)"
      >
        <div class="people-card__header">
          <div class="people-card__actions">
            <button
              v-if="!isReciterDirectoryMode"
              type="button"
              class="people-card__badge"
              :class="person.isCertified ? 'people-card__badge--certified' : 'people-card__badge--pending'"
              @click.stop="toggleStudentCertified(person)"
            >
              {{ person.isCertified ? 'تم الاعتماد' : 'اعتماد مجاز' }}
            </button>

            <button
              type="button"
              class="people-card__icon-button people-card__icon-button--edit"
              :aria-label="isReciterDirectoryMode ? 'تعديل المقرئ' : 'تعديل المعلم'"
              @click.stop="openEditDialogFor(isReciterDirectoryMode ? 'reciter' : 'student', person.id)"
            >
              <v-icon small>
                mdi-pencil-outline
              </v-icon>
            </button>

            <button
              type="button"
              class="people-card__icon-button people-card__icon-button--delete"
              :aria-label="isReciterDirectoryMode ? 'حذف المقرئ' : 'حذف المعلم'"
              @click.stop="openDeletePersonDialog(person)"
            >
              <i
                class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                aria-hidden="true"
              />
            </button>
          </div>

          <div class="people-card__identity">
            <h3 class="people-card__name">
              {{ person.name }}
            </h3>
            <button
              v-if="!isReciterDirectoryMode"
              type="button"
              class="people-card__meta-button"
              :class="{ 'people-card__meta-button--unlinked': person.reciterName === 'غير مرتبط' }"
              @click.stop="openReciterAssignmentDialog(person)"
            >
              المقرئ: {{ person.reciterName }}
            </button>
            <div
              v-else
              class="people-card__meta"
            >
              المعلمون: {{ person.linkedStudentNames }}
            </div>
            <div class="people-card__meta">
              رقم الدخول: {{ person.loginId }}
            </div>
          </div>
        </div>

        <div
          v-if="!isReciterDirectoryMode"
          class="people-card__metrics"
        >
          <div
            v-for="metric in person.metrics"
            :key="metric.key"
            class="people-metric"
            :class="{ 'people-metric--clickable': metric.key === 'parts' }"
            @click.stop="handleMetricClick(person, metric.key)"
          >
            <div class="people-metric__row">
              <div class="people-metric__value">
                {{ metric.display }}
              </div>
              <div class="people-metric__label">
                {{ metric.label }}
              </div>
            </div>
            <div class="people-metric__track">
              <div
                class="people-metric__fill"
                :style="{ width: metric.progressWidth }"
              />
            </div>
          </div>
        </div>
      </article>
    </section>

    <AppDialog
      :value="partsDialogOpen"
      max-width="560"
      @input="handlePartsDialogToggle"
    >
      <div class="people-dialog people-parts-dialog">
        <AppDialogBody class="people-parts-dialog__body">
          <div class="people-parts-dialog__header">
            <button
              type="button"
              class="people-parts-dialog__close-button"
              aria-label="إغلاق"
              @click="closePartsDialog"
            >
              ×
            </button>

            <div class="people-parts-dialog__title">
              الأجزاء المقروءة
            </div>

            <span
              class="people-parts-dialog__header-spacer"
              aria-hidden="true"
            />
          </div>

          <div class="people-parts-dialog__grid">
            <button
              v-for="part in partsRange"
              :key="part"
              type="button"
              class="people-parts-dialog__circle"
              :class="{ 'people-parts-dialog__circle--active': isCompletedDialogPart(part) }"
              :disabled="!canManageStudentParts || partsDialogSavingKey === `${partsDialogStudentId}:${part}`"
              :aria-pressed="isCompletedDialogPart(part) ? 'true' : 'false'"
              @click="toggleDialogPart(part)"
            >
              {{ part }}
            </button>
          </div>
        </AppDialogBody>
      </div>
    </AppDialog>

    <AppDialog
      :value="deleteDialogOpen"
      max-width="460"
      @input="handleDeleteDialogToggle"
    >
      <div class="people-dialog people-confirm-dialog">
        <AppDialogHeader
          class="people-dialog__header"
          title="تأكيد الحذف"
          title-tag="h2"
        />

        <AppDialogBody compact>
          <p class="people-confirm-dialog__text">
            هل تريد حذف {{ deleteTargetTypeLabel }}
            <strong>{{ deleteTargetName }}</strong>؟
          </p>
        </AppDialogBody>

        <AppDialogFooter class="people-dialog__actions">
          <AppButton
            variant="danger"
            class="people-dialog__submit people-dialog__submit--danger"
            :disabled="deleteDialogSubmitting"
            @click="confirmDeletePerson"
          >
            {{ deleteDialogSubmitting ? 'جارٍ الحذف...' : 'حذف' }}
          </AppButton>
          <AppButton
            variant="secondary"
            class="people-dialog__cancel"
            :disabled="deleteDialogSubmitting"
            @click="closeDeleteDialog"
          >
            إلغاء
          </AppButton>
        </AppDialogFooter>
      </div>
    </AppDialog>

    <AppDialog
      :value="reciterDialogOpen"
      max-width="520"
      @input="handleReciterDialogToggle"
    >
      <div class="people-dialog people-reciter-dialog">
        <AppDialogHeader
          class="people-dialog__header"
          title="اختر المقرئ"
          title-tag="h2"
        />

        <div class="people-dialog__divider" />

        <AppDialogBody
          class="people-dialog__form people-reciter-dialog__form"
          compact
        >
          <div class="people-dialog__field">
            <label class="people-dialog__label">اختر المقرئ/ة</label>
            <AppSelect
              v-model="reciterDialogReciterId"
              :items="reciterAssignmentOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="people-dialog__select"
            />
          </div>
        </AppDialogBody>

        <AppDialogFooter class="people-dialog__actions">
          <AppButton
            variant="secondary"
            class="people-dialog__cancel"
            :disabled="reciterDialogSaving"
            @click="closeReciterDialog"
          >
            إلغاء
          </AppButton>
          <AppButton
            variant="primary"
            class="people-dialog__submit"
            :disabled="reciterDialogSaving"
            @click="submitReciterAssignment"
          >
            حفظ
          </AppButton>
        </AppDialogFooter>
      </div>
    </AppDialog>

    <AppDialog
      :value="manageDialogOpen"
      max-width="640"
      @input="handleManageDialogToggle"
    >
      <div class="people-dialog people-manage-dialog">
        <AppDialogHeader
          class="people-dialog__header"
          title="إدارة المستخدمين"
          title-tag="h2"
        />

        <div class="people-dialog__divider" />

        <AppDialogBody
          compact
          class="people-dialog__form people-manage-dialog__form"
        >
          <div class="people-dialog__field">
            <label class="people-dialog__label">اختر النوع</label>
            <AppSelect
              v-model="manageEntityType"
              :items="entityOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              :menu-props="manageSelectMenuProps"
              class="people-dialog__select"
              @change="handleManageContextChange"
            />
          </div>

          <div class="people-dialog__field">
            <label class="people-dialog__label">اختر الفرع</label>
            <AppSelect
              v-model="manageBranchId"
              :items="branchOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              :menu-props="manageSelectMenuProps"
              class="people-dialog__select"
              @change="handleManageContextChange"
            />
          </div>

          <div
            v-if="!isDirectCardEdit"
            class="people-dialog__field"
          >
            <label class="people-dialog__label">{{ manageTargetLabel }}</label>
            <AppSelect
              v-model="manageTargetId"
              :items="manageTargetOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              placeholder="اختر."
              persistent-placeholder
              :menu-props="manageSelectMenuProps"
              class="people-dialog__select"
            />
          </div>
        </AppDialogBody>

        <AppDialogFooter class="people-dialog__actions">
          <AppButton
            variant="secondary"
            class="people-dialog__submit people-dialog__submit--manage"
            :disabled="!canManageSelectedEntity || manageDialogSubmitting"
            @click="openEditFromManageDialog"
          >
            تعديل البيانات
          </AppButton>
          <AppButton
            variant="danger"
            class="people-dialog__submit people-dialog__submit--danger"
            :disabled="!canManageSelectedEntity || manageDialogSubmitting"
            @click="submitManageDeleteWithSuccessFallback"
          >
            {{ manageDialogSubmitting ? 'جارٍ الحذف...' : 'حذف' }}
          </AppButton>
          <AppButton
            variant="secondary"
            class="people-dialog__cancel"
            :disabled="manageDialogSubmitting"
            @click="closeManageDialog"
          >
            إلغاء
          </AppButton>
        </AppDialogFooter>
      </div>
    </AppDialog>

    <AppDialog
      :value="dialogOpen"
      max-width="720"
      :persistent="bulkFilePickerOpen"
      @input="handleDialogToggle"
    >
      <div class="people-dialog">
        <AppDialogHeader
          class="people-dialog__header"
          :title="dialogTitle"
          title-tag="h2"
        >
          <template #actions>
            <AppButton
              v-if="!isEditing"
              variant="secondary"
              class="people-dialog__bulk-button"
              :disabled="bulkImporting"
              @click="openBulkFilePicker"
            >
              {{ bulkImporting ? 'جارٍ الاستيراد...' : 'إضافة جماعية' }}
            </AppButton>
            <input
              ref="bulkFileInput"
              type="file"
              accept=".xlsx,.xls,.csv"
              class="people-dialog__bulk-input"
              @change="handleBulkFileChange"
            >
          </template>
        </AppDialogHeader>

        <div class="people-dialog__divider" />

        <AppDialogBody class="people-dialog__form">
          <div
            v-if="!isDirectCardEdit"
            class="people-dialog__field"
          >
            <label class="people-dialog__label">اختر النوع</label>
            <AppSelect
              v-model="dialogEntityType"
              :items="entityOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="people-dialog__select"
              @change="handleDialogContextChange"
            />
          </div>

          <div
            v-if="!isDirectCardEdit"
            class="people-dialog__field"
          >
            <label class="people-dialog__label">اختر الفرع</label>
            <AppSelect
              v-model="dialogBranchId"
              :items="branchOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="people-dialog__select"
              @change="handleDialogContextChange"
            />
          </div>

          <div
            v-if="isEditing && !isDirectCardEdit"
            class="people-dialog__field"
          >
            <label class="people-dialog__label">{{ dialogTargetLabel }}</label>
            <AppSelect
              v-model="editingTargetId"
              :items="editTargetOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
              class="people-dialog__select"
              @change="handleEditingTargetChange"
            />
          </div>

          <div class="people-dialog__field">
            <label class="people-dialog__label">{{ dialogNameLabel }}</label>
            <AppTextField
              v-model.trim="activeName"
              :placeholder="dialogNamePlaceholder"
              dense
              outlined
              hide-details
              class="people-dialog__input"
            />
          </div>

          <div
            v-if="dialogEntityType === 'reciter'"
            class="people-dialog__field"
          >
            <label class="people-dialog__label">المعلمون المرتبطون (اختياري)</label>
            <div class="people-linked-students">
              <button
                v-for="student in linkedStudentOptions"
                :key="student.value"
                type="button"
                class="people-linked-students__item"
                :class="{ 'people-linked-students__item--active': reciterForm.studentIds.includes(student.value) }"
                @click="toggleLinkedStudent(student.value)"
              >
                <span class="people-linked-students__mark" />
                <span class="people-linked-students__name">{{ student.label }}</span>
              </button>
              <div
                v-if="linkedStudentOptions.length === 0"
                class="people-linked-students__empty"
              >
                لا يوجد معلمون في هذا الفرع حاليًا.
              </div>
            </div>
          </div>

          <div class="people-dialog__field">
            <label class="people-dialog__label">رقم الدخول</label>
            <AppTextField
              v-model.trim="activeLoginCode"
              placeholder="رقم الدخول"
              dense
              outlined
              hide-details
              class="people-dialog__input"
            />
          </div>
        </AppDialogBody>

        <AppDialogFooter class="people-dialog__actions">
          <AppButton
            variant="primary"
            class="people-dialog__submit"
            @click="submitDialog"
          >
            {{ isEditing ? 'تحديث' : 'إضافة' }}
          </AppButton>
          <AppButton
            variant="secondary"
            class="people-dialog__cancel"
            @click="closeDialog"
          >
            إلغاء
          </AppButton>
        </AppDialogFooter>
      </div>
    </AppDialog>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import * as XLSX from 'xlsx';
import { createStudent as createStudentRequest, saveReciter as saveReciterRequest, toggleStudentPart } from '../services/api';
import {
  AppButton, AppDialog, AppDialogBody, AppDialogFooter, AppDialogHeader, AppSelect,
  AppTextField,
} from '../components/ui';

const BULK_NAME_HEADER_KEYS = ['name', 'full name', 'student name', 'reciter name', 'الاسم', 'اسم', 'اسم المعلم', 'اسم المقرئ'];
const BULK_LOGIN_HEADER_KEYS = ['login', 'login code', 'code', 'id', 'number', 'رقم الدخول', 'رقم', 'الرقم'];

const emptyStudentForm = () => ({
  name: '',
  loginId: '',
  branchId: 'male',
  note: '',
});

const emptyReciterForm = () => ({
  name: '',
  loginCode: '',
  branchId: 'male',
  studentIds: [],
});

const clampPercent = (value) => {
  const numeric = Number(value || 0);

  if (!Number.isFinite(numeric)) {
    return 0;
  }

  return Math.max(0, Math.min(100, Math.round(numeric)));
};

const ratioPercent = (value, total) => {
  if (!total) {
    return 0;
  }

  return clampPercent((value / total) * 100);
};

export default {
  name: 'AdminPeopleView',
  components: {
    AppDialog,
    AppButton,
    AppSelect,
    AppDialogHeader,
    AppDialogBody,
    AppDialogFooter,
    AppTextField,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      selectedStudentId: '',
      selectedBranch: 'male',
      selectedFilter: 'all',
      activeToastId: null,
      activeToastTimer: null,
      partsDialogOpen: false,
      partsDialogStudentId: '',
      partsDialogSavingKey: '',
      partsDialogParts: [],
      reciterDialogOpen: false,
      reciterDialogStudentId: '',
      reciterDialogReciterId: '',
      reciterDialogSaving: false,
      deleteDialogOpen: false,
      deleteDialogSubmitting: false,
      deleteTarget: null,
      deleteTargetIsReciter: false,
      manageDialogOpen: false,
      manageEntityType: 'student',
      manageBranchId: 'male',
      manageTargetId: '',
      manageDialogSubmitting: false,
      dialogOpen: false,
      isEditing: false,
      isDirectCardEdit: false,
      editingBranchId: '',
      editingTargetId: '',
      dialogEntityType: 'student',
      studentForm: emptyStudentForm(),
      reciterForm: emptyReciterForm(),
      branchOptions: [
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
      ],
      baseFilterOptions: [
        { label: 'الترتيب الأبجدي أ-ي', value: 'all' },
        { label: 'الأعلى إنجازًا', value: 'highest-progress' },
        { label: 'الأقل إنجازًا', value: 'lowest-progress' },
        { label: 'المجازون', value: 'certified' },
      ],
      entityOptions: [
        { label: 'معلم/ة', value: 'student' },
        { label: 'مقرئ', value: 'reciter' },
      ],
      bulkImporting: false,
      bulkFilePickerOpen: false,
      bulkFilePickerResetTimer: null,
    };
  },
  computed: {
    ...mapState(['currentUser', 'dashboardSnapshot', 'dashboardError']),
    managedBranchId() {
      if (this.currentUser?.role === 'male_manager') {
        return 'male';
      }

      if (this.currentUser?.role === 'female_manager') {
        return 'female';
      }

      return '';
    },
    effectiveSelectedBranch() {
      return this.managedBranchId || this.selectedBranch;
    },
    isReciterDirectoryMode() {
      return ['reciters-male', 'reciters-female'].includes(this.effectiveSelectedBranch);
    },
    effectiveStudentBranch() {
      if (this.effectiveSelectedBranch === 'reciters-male') {
        return 'male';
      }

      if (this.effectiveSelectedBranch === 'reciters-female') {
        return 'female';
      }

      return this.effectiveSelectedBranch;
    },
    filterOptions() {
      if (this.isReciterDirectoryMode) {
        return this.baseFilterOptions.filter((option) => option.value === 'all');
      }

      return this.baseFilterOptions;
    },
    canManageStudentParts() {
      return ['admin', 'male_manager', 'female_manager', 'reciter'].includes(this.currentUser?.role);
    },
    branchFilterOptions() {
      if (this.managedBranchId) {
        return this.branchOptions.filter((option) => option.value === this.managedBranchId);
      }

      return [
        ...this.branchOptions,
        { label: 'مقرئين', value: 'reciters-male' },
        { label: 'مقرئات', value: 'reciters-female' },
      ];
    },
    students() {
      return this.dashboardSnapshot?.students || [];
    },
    reciters() {
      return this.dashboardSnapshot?.reciters || [];
    },
    courses() {
      return this.dashboardSnapshot?.courses || [];
    },
    attendance() {
      return this.dashboardSnapshot?.attendance || [];
    },
    submissions() {
      return this.dashboardSnapshot?.submissions || [];
    },
    selectedStudentRecord() {
      return this.students.find((student) => student.id === this.selectedStudentId) || null;
    },
    partsDialogStudent() {
      return this.students.find((student) => student.id === this.partsDialogStudentId) || null;
    },
    partsRange() {
      const limit = this.partsDialogStudent?.branchId === 'female' ? 10 : 30;

      return Array.from({ length: limit }, (_, index) => index + 1);
    },
    reciterDialogStudent() {
      return this.students.find((student) => student.id === this.reciterDialogStudentId) || null;
    },
    assignedReciterRecord() {
      if (!this.reciterDialogStudent) {
        return null;
      }

      return this.reciters.find((reciter) => (reciter.studentIds || []).includes(this.reciterDialogStudent.id)) || null;
    },
    editingStudentRecord() {
      return this.students.find((student) => student.id === this.editingTargetId) || null;
    },
    editingReciterRecord() {
      return this.reciters.find((reciter) => reciter.id === this.editingTargetId) || null;
    },
    managedTargetRecord() {
      if (!this.manageTargetId) {
        return null;
      }

      if (this.manageEntityType === 'student') {
        return this.students.find((student) => student.id === this.manageTargetId) || null;
      }

      return this.reciters.find((reciter) => reciter.id === this.manageTargetId) || null;
    },
    dialogTitle() {
      if (this.isEditing) {
        return this.dialogEntityType === 'student' ? 'تعديل معلم/ة' : 'تعديل مقرئ';
      }

      return this.dialogEntityType === 'student' ? 'إضافة معلم/ة' : 'إضافة مقرئ';
    },
    dialogTargetLabel() {
      return this.dialogEntityType === 'student' ? 'اختر المعلم/ة' : 'اختر المقرئ';
    },
    dialogNameLabel() {
      return this.dialogEntityType === 'student' ? 'اسم المعلم/ة' : 'اسم المقرئ';
    },
    dialogNamePlaceholder() {
      return this.dialogEntityType === 'student' ? 'اسم المعلم/ة' : 'اسم المقرئ';
    },
    manageTargetLabel() {
      return this.manageEntityType === 'student' ? 'اختر المعلم/ة' : 'اختر المقرئ';
    },
    deleteTargetName() {
      return this.deleteTarget?.name || 'المحدد';
    },
    deleteTargetTypeLabel() {
      return this.deleteTargetIsReciter ? 'المقرئ' : 'المعلم';
    },
    manageSelectMenuProps() {
      return {
        attach: '.people-manage-dialog',
        contentClass: 'people-manage-dropdown',
        offsetY: true,
        bottom: true,
        top: false,
        nudgeBottom: 8,
        closeOnClick: true,
        closeOnContentClick: true,
        maxHeight: 320,
      };
    },
    manageTargetOptions() {
      if (this.manageEntityType === 'student') {
        return this.students
          .filter((student) => !this.manageBranchId || student.branchId === this.manageBranchId)
          .map((student) => ({
            label: `${student.name} - ${student.loginId || student.loginCode || 'بدون رقم'}`,
            value: student.id,
          }))
          .sort((left, right) => left.label.localeCompare(right.label, 'ar'));
      }

      return this.reciters
        .filter((reciter) => !this.manageBranchId || reciter.branchId === this.manageBranchId)
        .map((reciter) => ({
          label: `${reciter.name} - ${reciter.loginCode || 'بدون رقم'}`,
          value: reciter.id,
        }))
        .sort((left, right) => left.label.localeCompare(right.label, 'ar'));
    },
    canManageSelectedEntity() {
      return Boolean(this.managedTargetRecord);
    },
    editTargetOptions() {
      if (this.dialogEntityType === 'student') {
        return this.students
          .filter((student) => !this.editingBranchId || student.branchId === this.editingBranchId)
          .map((student) => ({
            label: `${student.name} - ${student.loginId || student.loginCode || 'بدون رقم'}`,
            value: student.id,
          }))
          .sort((left, right) => left.label.localeCompare(right.label, 'ar'));
      }

      return this.reciters
        .filter((reciter) => !this.editingBranchId || reciter.branchId === this.editingBranchId)
        .map((reciter) => ({
          label: `${reciter.name} - ${reciter.loginCode || 'بدون رقم'}`,
          value: reciter.id,
        }))
        .sort((left, right) => left.label.localeCompare(right.label, 'ar'));
    },
    linkedStudentOptions() {
      return this.students
        .filter((student) => student.branchId === this.activeBranchId)
        .map((student) => ({
          label: student.name,
          value: student.id,
        }))
        .sort((left, right) => left.label.localeCompare(right.label, 'ar'));
    },
    reciterAssignmentOptions() {
      if (!this.reciterDialogStudent) {
        return [{ label: 'غير مرتبط', value: '' }];
      }

      return [
        { label: 'غير مرتبط', value: '' },
        ...this.reciters
          .filter((reciter) => reciter.branchId === this.reciterDialogStudent.branchId)
          .map((reciter) => ({
            label: `${reciter.name} - ${reciter.loginCode || 'بدون رقم'}`,
            value: reciter.id,
          }))
          .sort((left, right) => left.label.localeCompare(right.label, 'ar')),
      ];
    },
    nonTaskCoursesCount() {
      return this.courses.filter((course) => course.entityType !== 'task').length || 0;
    },
    taskCoursesCount() {
      return this.courses.filter((course) => course.entityType === 'task').length || 0;
    },
    studentCards() {
      return this.students.map((student) => {
        const studentLoginId = student.loginId || student.loginCode;
        const assignedReciter = this.reciters.find((reciter) => (reciter.studentIds || []).includes(student.id));
        const attendanceCount = this.attendance.filter((record) => record.loginId === studentLoginId).length;
        const preCount = new Set(this.submissions
          .filter((item) => item.loginId === studentLoginId && item.assessmentType === 'pre')
          .map((item) => item.courseId)).size;
        const postCount = new Set(this.submissions
          .filter((item) => item.loginId === studentLoginId && item.assessmentType === 'post')
          .map((item) => item.courseId)).size;
        const tasksCount = new Set(this.submissions
          .filter((item) => item.loginId === studentLoginId && item.assessmentType === 'tasks')
          .map((item) => item.courseId)).size;
        const partsCount = (student.completedParts || []).length;
        const partsProgress = ratioPercent(partsCount, student.branchId === 'female' ? 10 : 30);
        const attendanceProgress = ratioPercent(attendanceCount, this.nonTaskCoursesCount || 1);
        const preProgress = ratioPercent(preCount, this.nonTaskCoursesCount || 1);
        const postProgress = ratioPercent(postCount, this.nonTaskCoursesCount || 1);
        const tasksProgress = ratioPercent(tasksCount, this.taskCoursesCount || 1);
        const totalProgress = clampPercent((partsProgress + attendanceProgress + preProgress + postProgress + tasksProgress) / 5);

        return {
          ...student,
          loginId: studentLoginId,
          reciterName: assignedReciter?.name || 'غير مرتبط',
          overallProgress: totalProgress,
          metrics: [
            { key: 'parts', label: 'الأجزاء', display: `${partsProgress}%`, progressWidth: `${partsProgress}%` },
            { key: 'attendance', label: 'الحضور', display: `${attendanceProgress}%`, progressWidth: `${attendanceProgress}%` },
            { key: 'pre', label: 'القبلي', display: `${preProgress}%`, progressWidth: `${preProgress}%` },
            { key: 'post', label: 'البعدي', display: `${postProgress}%`, progressWidth: `${postProgress}%` },
            { key: 'tasks', label: 'المهام الأدائية', display: `${tasksProgress}%`, progressWidth: `${tasksProgress}%` },
            { key: 'overall', label: 'الإجمالي', display: `${totalProgress}%`, progressWidth: `${totalProgress}%` },
          ],
        };
      });
    },
    filteredStudentCards() {
      let cards = this.studentCards.slice();

      if (this.effectiveStudentBranch) {
        cards = cards.filter((student) => student.branchId === this.effectiveStudentBranch);
      }

      if (this.selectedFilter === 'certified') {
        cards = cards.filter((student) => student.isCertified);
      }

      if (this.selectedFilter === 'highest-progress') {
        cards = cards.sort((left, right) => right.overallProgress - left.overallProgress || left.name.localeCompare(right.name, 'ar'));
      } else if (this.selectedFilter === 'lowest-progress') {
        cards = cards.sort((left, right) => left.overallProgress - right.overallProgress || left.name.localeCompare(right.name, 'ar'));
      }

      return cards;
    },
    reciterCards() {
      const branchId = this.effectiveStudentBranch;

      return this.reciters
        .filter((reciter) => !branchId || reciter.branchId === branchId)
        .map((reciter) => {
          const linkedStudents = this.students
            .filter((student) => (reciter.studentIds || []).includes(student.id))
            .map((student) => student.name)
            .sort((left, right) => left.localeCompare(right, 'ar'));

          return {
            ...reciter,
            cardKey: `reciter:${reciter.id}`,
            loginId: reciter.loginCode || 'بدون رقم',
            linkedStudentNames: linkedStudents.length ? linkedStudents.join('، ') : 'لا يوجد',
          };
        })
        .sort((left, right) => left.name.localeCompare(right.name, 'ar'));
    },
    visiblePeopleCards() {
      if (this.isReciterDirectoryMode) {
        return this.reciterCards;
      }

      return this.filteredStudentCards.map((student) => ({
        ...student,
        cardKey: `student:${student.id}`,
      }));
    },
    activeName: {
      get() {
        return this.dialogEntityType === 'student' ? this.studentForm.name : this.reciterForm.name;
      },
      set(value) {
        if (this.dialogEntityType === 'student') {
          this.studentForm.name = value;
        } else {
          this.reciterForm.name = value;
        }
      },
    },
    activeBranchId: {
      get() {
        return this.dialogEntityType === 'student' ? this.studentForm.branchId : this.reciterForm.branchId;
      },
      set(value) {
        if (this.dialogEntityType === 'student') {
          this.studentForm.branchId = value;
        } else {
          this.reciterForm.branchId = value;
        }
      },
    },
    activeLoginCode: {
      get() {
        return this.dialogEntityType === 'student' ? this.studentForm.loginId : this.reciterForm.loginCode;
      },
      set(value) {
        if (this.dialogEntityType === 'student') {
          this.studentForm.loginId = value;
        } else {
          this.reciterForm.loginCode = value;
        }
      },
    },
    dialogBranchId: {
      get() {
        return this.isEditing ? (this.editingBranchId || this.activeBranchId) : this.activeBranchId;
      },
      set(value) {
        if (this.isEditing) {
          this.editingBranchId = value;
        }

        this.activeBranchId = value;
      },
    },
  },
  created() {
    if (this.managedBranchId) {
      this.selectedBranch = this.managedBranchId;
    }

    this.loadDashboardSnapshot();
  },
  beforeDestroy() {
    window.removeEventListener('focus', this.handleBulkFilePickerWindowFocus);

    if (this.bulkFilePickerResetTimer) {
      window.clearTimeout(this.bulkFilePickerResetTimer);
      this.bulkFilePickerResetTimer = null;
    }

    if (this.activeToastTimer) {
      window.clearTimeout(this.activeToastTimer);
      this.activeToastTimer = null;
    }

    if (this.activeToastId !== null && typeof this.$toast.dismiss === 'function') {
      this.$toast.dismiss(this.activeToastId);
      this.activeToastId = null;
    }
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot', 'addStudent', 'updateStudent', 'saveReciter', 'deleteStudent', 'deleteReciter']),
    showTimedToast(type, message, options = {}) {
      if (this.activeToastTimer) {
        window.clearTimeout(this.activeToastTimer);
        this.activeToastTimer = null;
      }

      if (this.activeToastId !== null && typeof this.$toast.dismiss === 'function') {
        this.$toast.dismiss(this.activeToastId);
        this.activeToastId = null;
      }

      const method = typeof this.$toast?.[type] === 'function' ? this.$toast[type] : this.$toast.success;
      const timeout = typeof options.timeout === 'number' ? options.timeout : 3000;
      const toastId = method(message, {
        ...options,
        timeout,
      });

      this.activeToastId = toastId;
      this.activeToastTimer = window.setTimeout(() => {
        if (this.activeToastId !== null && typeof this.$toast.dismiss === 'function') {
          this.$toast.dismiss(this.activeToastId);
        }

        this.activeToastId = null;
        this.activeToastTimer = null;
      }, timeout);
    },
    async submitManageDeleteWithSuccessFallback() {
      if (!this.managedTargetRecord || this.manageDialogSubmitting) {
        return;
      }

      const targetId = this.managedTargetRecord.id;
      const targetLoginCode = this.managedTargetRecord.loginCode;
      this.manageDialogSubmitting = true;

      try {
        if (this.manageEntityType === 'student') {
          await this.deleteStudent(targetId);

          if (this.selectedStudentId === targetId) {
            this.selectedStudentId = '';
          }

          this.showTimedToast('success', 'تم حذف المعلم بنجاح');
        } else {
          await this.deleteReciter(targetLoginCode);
          this.showTimedToast('success', 'تم حذف المقرئ بنجاح');
        }

        this.closeManageDialog();
      } catch (error) {
        const wasActuallyDeleted = this.manageEntityType === 'student'
          ? !this.students.some((student) => student.id === targetId)
          : !this.reciters.some((reciter) => reciter.loginCode === targetLoginCode);

        if (wasActuallyDeleted) {
          if (this.manageEntityType === 'student' && this.selectedStudentId === targetId) {
            this.selectedStudentId = '';
          }

          this.showTimedToast('success', 'تم الحذف بنجاح');
          this.closeManageDialog();
          return;
        }

        this.showTimedToast('error', error?.response?.data?.message || 'تعذر حذف العنصر');
        this.manageDialogSubmitting = false;
      }
    },
    async toggleStudentCertified(student) {
      try {
        await this.updateStudent({
          studentId: student.id,
          updates: {
            isCertified: !student.isCertified,
          },
        });

        this.showTimedToast('success', !student.isCertified ? 'تم اعتماد المعلم كمجاز' : 'تمت إزالة الاعتماد');
      } catch (error) {
        this.showTimedToast('error', error?.response?.data?.message || 'تعذر تحديث حالة الاعتماد');
      }
    },
    handleDialogToggle(value) {
      if (value) {
        this.dialogOpen = true;
        return;
      }

      if (this.bulkFilePickerOpen) {
        this.dialogOpen = true;
        return;
      }

      this.closeDialog();
    },
    handlePartsDialogToggle(value) {
      if (value) {
        this.partsDialogOpen = true;
        return;
      }

      this.closePartsDialog();
    },
    handleReciterDialogToggle(value) {
      if (value) {
        this.reciterDialogOpen = true;
        return;
      }

      this.closeReciterDialog();
    },
    handleManageDialogToggle(value) {
      if (value) {
        this.manageDialogOpen = true;
        return;
      }

      this.closeManageDialog();
    },
    handleDeleteDialogToggle(value) {
      if (value) {
        this.deleteDialogOpen = true;
        return;
      }

      this.closeDeleteDialog();
    },
    handleBranchFilterChange() {
      if (this.isReciterDirectoryMode) {
        this.selectedFilter = 'all';
      }
    },
    selectStudent(studentId) {
      this.selectedStudentId = studentId;
    },
    openDeletePersonDialog(person) {
      if (!person) {
        return;
      }

      this.deleteTarget = person;
      this.deleteTargetIsReciter = this.isReciterDirectoryMode;
      this.deleteDialogSubmitting = false;
      this.deleteDialogOpen = true;
    },
    closeDeleteDialog() {
      if (this.deleteDialogSubmitting) {
        return;
      }

      this.resetDeleteDialog();
    },
    resetDeleteDialog() {
      this.deleteDialogOpen = false;
      this.deleteDialogSubmitting = false;
      this.deleteTarget = null;
      this.deleteTargetIsReciter = false;
    },
    async confirmDeletePerson() {
      if (!this.deleteTarget || this.deleteDialogSubmitting) {
        return;
      }

      const person = this.deleteTarget;
      const isReciter = this.deleteTargetIsReciter;
      this.deleteDialogSubmitting = true;

      try {
        if (isReciter) {
          await this.deleteReciter(person.loginCode);
          this.showTimedToast('success', 'تم حذف المقرئ');
          this.resetDeleteDialog();
          return;
        }

        await this.deleteStudent(person.id);

        if (this.selectedStudentId === person.id) {
          this.selectedStudentId = '';
        }

        this.showTimedToast('success', 'تم حذف المعلم');
        this.resetDeleteDialog();
      } catch (error) {
        this.showTimedToast('error', error?.response?.data?.message || 'تعذر حذف العنصر');
        this.deleteDialogSubmitting = false;
      }
    },
    openReciterAssignmentDialog(student) {
      if (!student) {
        return;
      }

      const assignedReciter = this.reciters.find((reciter) => (reciter.studentIds || []).includes(student.id));

      this.selectedStudentId = student.id;
      this.reciterDialogStudentId = student.id;
      this.reciterDialogReciterId = assignedReciter?.id || '';
      this.reciterDialogOpen = true;
    },
    closeReciterDialog() {
      this.reciterDialogOpen = false;
      this.reciterDialogStudentId = '';
      this.reciterDialogReciterId = '';
      this.reciterDialogSaving = false;
    },
    handleMetricClick(student, metricKey) {
      if (metricKey !== 'parts') {
        return;
      }

      this.selectedStudentId = student.id;
      this.partsDialogStudentId = student.id;
      this.partsDialogParts = [...(student.completedParts || [])];
      this.partsDialogOpen = true;
    },
    closePartsDialog() {
      this.partsDialogOpen = false;
      this.partsDialogStudentId = '';
      this.partsDialogSavingKey = '';
      this.partsDialogParts = [];
    },
    isCompletedDialogPart(part) {
      return this.partsDialogParts.includes(part);
    },
    async submitReciterAssignment() {
      const student = this.reciterDialogStudent;
      const currentReciter = this.assignedReciterRecord;
      const nextReciter = this.reciters.find((reciter) => reciter.id === this.reciterDialogReciterId) || null;

      if (!student || this.reciterDialogSaving) {
        return;
      }

      if ((currentReciter?.id || '') === (nextReciter?.id || '')) {
        this.showTimedToast('info', 'لم يتم تغيير المقرئ.');
        this.closeReciterDialog();
        return;
      }

      this.reciterDialogSaving = true;

      try {
        if (currentReciter) {
          await this.saveReciter({
            currentLoginCode: currentReciter.loginCode,
            name: currentReciter.name,
            loginCode: currentReciter.loginCode,
            branchId: currentReciter.branchId,
            linkedStudentIds: (currentReciter.studentIds || []).filter((studentId) => studentId !== student.id),
          });
        }

        if (nextReciter) {
          await this.saveReciter({
            currentLoginCode: nextReciter.loginCode,
            name: nextReciter.name,
            loginCode: nextReciter.loginCode,
            branchId: nextReciter.branchId,
            linkedStudentIds: Array.from(new Set([...(nextReciter.studentIds || []), student.id])),
          });
        }

        this.showTimedToast('success', nextReciter ? 'تم تحديث ربط المقرئ' : 'تم فك ربط المقرئ');
        this.closeReciterDialog();
      } catch (error) {
        this.showTimedToast('error', error?.response?.data?.message || 'تعذر تحديث ربط المقرئ');
        this.reciterDialogSaving = false;
      }
    },
    async toggleDialogPart(partNumber) {
      const student = this.partsDialogStudent;

      if (!student || this.partsDialogSavingKey || !this.canManageStudentParts) {
        return;
      }

      const assignedReciter = this.reciters.find((reciter) => (reciter.studentIds || []).includes(student.id));

      const isCompleted = this.partsDialogParts.includes(partNumber);
      const previousParts = [...this.partsDialogParts];

      this.partsDialogSavingKey = `${student.id}:${partNumber}`;
      this.partsDialogParts = isCompleted
        ? this.partsDialogParts.filter((part) => part !== partNumber)
        : [...this.partsDialogParts, partNumber].sort((left, right) => left - right);

      try {
        await toggleStudentPart({
          studentId: student.id,
          partNumber,
          reciterId: assignedReciter?.id || null,
          shouldMarkComplete: !isCompleted,
        });
        await this.loadDashboardSnapshot();
      } catch (error) {
        this.partsDialogParts = previousParts;
        this.showTimedToast('error', error?.response?.data?.message || 'تعذر حفظ الجزء المقروء');
      } finally {
        this.partsDialogSavingKey = '';
      }
    },
    resetForms() {
      this.studentForm = emptyStudentForm();
      this.reciterForm = emptyReciterForm();
      this.editingBranchId = '';
      this.editingTargetId = '';
      this.dialogEntityType = 'student';
      this.isEditing = false;
      this.isDirectCardEdit = false;
    },
    resetManageDialog() {
      this.manageEntityType = 'student';
      this.manageBranchId = this.effectiveSelectedBranch === 'all' ? 'male' : this.effectiveSelectedBranch;
      this.manageTargetId = '';
      this.manageDialogSubmitting = false;
    },
    populateStudentForm(student) {
      if (!student) {
        this.studentForm = emptyStudentForm();
        return;
      }

      this.studentForm = {
        name: student.name,
        loginId: student.loginId || student.loginCode || '',
        branchId: student.branchId,
        note: student.note || '',
      };
    },
    populateReciterForm(reciter) {
      if (!reciter) {
        this.reciterForm = emptyReciterForm();
        return;
      }

      this.reciterForm = {
        name: reciter.name,
        loginCode: reciter.loginCode || '',
        branchId: reciter.branchId,
        studentIds: [...(reciter.studentIds || [])],
      };
    },
    openCreateDialog() {
      this.resetForms();
      const initialBranchId = this.effectiveSelectedBranch === 'all' ? 'male' : this.effectiveSelectedBranch;
      this.studentForm.branchId = initialBranchId;
      this.reciterForm.branchId = initialBranchId;
      this.dialogOpen = true;
    },
    openManageDialog() {
      this.resetManageDialog();
      this.manageDialogOpen = true;
    },
    openEditDialog() {
      const initialStudent = this.selectedStudentRecord || null;

      this.resetForms();
      this.isEditing = true;
      this.dialogEntityType = 'student';
      this.editingBranchId = initialStudent?.branchId || (this.effectiveSelectedBranch !== 'all' ? this.effectiveSelectedBranch : '');
      this.editingTargetId = initialStudent?.id || '';
      this.populateStudentForm(initialStudent);
      this.dialogOpen = true;
    },
    openEditDialogFor(entityType, targetId, directCardEdit = true) {
      this.resetForms();
      this.isEditing = true;
      this.isDirectCardEdit = directCardEdit;
      this.dialogEntityType = entityType;
      this.editingTargetId = targetId;

      if (entityType === 'student') {
        const student = this.students.find((item) => item.id === targetId) || null;
        this.editingBranchId = student?.branchId || '';
        this.populateStudentForm(student);
      } else {
        const reciter = this.reciters.find((item) => item.id === targetId) || null;
        this.editingBranchId = reciter?.branchId || '';
        this.populateReciterForm(reciter);
      }

      this.dialogOpen = true;
    },
    handleDialogContextChange() {
      this.editingTargetId = '';

      if (this.dialogEntityType === 'student') {
        this.populateStudentForm(null);
        this.studentForm.branchId = this.editingBranchId || this.studentForm.branchId;
      } else {
        this.populateReciterForm(null);
        this.reciterForm.branchId = this.editingBranchId || this.reciterForm.branchId;
        this.reciterForm.studentIds = this.reciterForm.studentIds.filter((studentId) => this.linkedStudentOptions.some((student) => student.value === studentId));
      }
    },
    handleEditingTargetChange(targetId) {
      this.editingTargetId = targetId;

      if (this.dialogEntityType === 'student') {
        const student = this.editingStudentRecord;
        this.editingBranchId = student?.branchId || this.editingBranchId;
        this.populateStudentForm(student);
        return;
      }

      const reciter = this.editingReciterRecord;
      this.editingBranchId = reciter?.branchId || this.editingBranchId;
      this.populateReciterForm(reciter);
    },
    handleManageContextChange() {
      this.manageTargetId = '';
    },
    closeManageDialog() {
      this.manageDialogOpen = false;
      this.resetManageDialog();
    },
    openEditFromManageDialog() {
      if (!this.managedTargetRecord || this.manageDialogSubmitting) {
        return;
      }

      this.openEditDialogFor(this.manageEntityType, this.manageTargetId);
      this.closeManageDialog();
    },
    async submitManageDelete() {
      if (!this.managedTargetRecord || this.manageDialogSubmitting) {
        return;
      }

      this.manageDialogSubmitting = true;

      try {
        if (this.manageEntityType === 'student') {
          await this.deleteStudent(this.managedTargetRecord.id);

          if (this.selectedStudentId === this.managedTargetRecord.id) {
            this.selectedStudentId = '';
          }

          this.showTimedToast('success', 'تم حذف المعلم');
        } else {
          await this.deleteReciter(this.managedTargetRecord.loginCode);
          this.showTimedToast('success', 'تم حذف المقرئ');
        }

        this.closeManageDialog();
      } catch (error) {
        this.showTimedToast('error', error?.response?.data?.message || 'تعذر حذف العنصر');
        this.manageDialogSubmitting = false;
      }
    },
    toggleLinkedStudent(studentId) {
      if (this.dialogEntityType !== 'reciter') {
        return;
      }

      const selectedIds = new Set(this.reciterForm.studentIds || []);

      if (selectedIds.has(studentId)) {
        selectedIds.delete(studentId);
      } else {
        selectedIds.add(studentId);
      }

      this.reciterForm.studentIds = Array.from(selectedIds);
    },
    closeDialog() {
      this.dialogOpen = false;
      this.resetForms();
    },
    openBulkFilePicker() {
      if (this.bulkImporting) {
        return;
      }

      this.bulkFilePickerOpen = true;
      window.removeEventListener('focus', this.handleBulkFilePickerWindowFocus);
      window.addEventListener('focus', this.handleBulkFilePickerWindowFocus, { once: true });
      this.$refs.bulkFileInput?.click();
    },
    handleBulkFilePickerWindowFocus() {
      if (this.bulkFilePickerResetTimer) {
        window.clearTimeout(this.bulkFilePickerResetTimer);
      }

      this.bulkFilePickerResetTimer = window.setTimeout(() => {
        this.bulkFilePickerOpen = false;
        this.bulkFilePickerResetTimer = null;
      }, 150);
    },
    clearBulkFileInput() {
      if (this.$refs.bulkFileInput) {
        this.$refs.bulkFileInput.value = '';
      }
    },
    normalizeBulkCell(value) {
      return String(value == null ? '' : value).trim();
    },
    findBulkHeaderIndex(headerRow, candidates) {
      return headerRow.findIndex((cell) => candidates.includes(cell.toLowerCase()));
    },
    looksLikeLoginCode(value) {
      const normalized = this.normalizeBulkCell(value);

      if (!normalized) {
        return false;
      }

      return /^[A-Za-z0-9_-]{4,}$/.test(normalized) && /\d/.test(normalized);
    },
    async parseBulkImportFile(file) {
      const buffer = await file.arrayBuffer();
      const workbook = XLSX.read(buffer, { type: 'array' });
      const firstSheetName = workbook.SheetNames[0];

      if (!firstSheetName) {
        return [];
      }

      const sheet = workbook.Sheets[firstSheetName];
      const rawRows = XLSX.utils.sheet_to_json(sheet, {
        header: 1,
        raw: false,
        defval: '',
      });

      const rows = rawRows
        .map((row) => Array.isArray(row) ? row.map((cell) => this.normalizeBulkCell(cell)) : [])
        .filter((row) => row.some((cell) => cell));

      if (!rows.length) {
        return [];
      }

      let dataRows = rows;
      let nameIndex = 0;
      let loginIndex = 1;

      const detectedNameIndex = this.findBulkHeaderIndex(rows[0], BULK_NAME_HEADER_KEYS);
      const detectedLoginIndex = this.findBulkHeaderIndex(rows[0], BULK_LOGIN_HEADER_KEYS);

      if (detectedNameIndex !== -1 || detectedLoginIndex !== -1) {
        dataRows = rows.slice(1);
        if (detectedNameIndex !== -1) {
          nameIndex = detectedNameIndex;
        }
        if (detectedLoginIndex !== -1) {
          loginIndex = detectedLoginIndex;
        }
      }

      return dataRows
        .map((row) => {
          const compactValues = row.filter((cell) => cell);
          let name = this.normalizeBulkCell(row[nameIndex] || compactValues[0] || '');
          let loginCode = this.normalizeBulkCell(row[loginIndex] || compactValues[1] || '');

          if (this.looksLikeLoginCode(name) && loginCode && !this.looksLikeLoginCode(loginCode)) {
            [name, loginCode] = [loginCode, name];
          }

          return { name, loginCode };
        })
        .filter((entry) => entry.name);
    },
    async handleBulkFileChange(event) {
      const file = event?.target?.files?.[0];

      if (!file) {
        return;
      }

      this.bulkImporting = true;

      try {
        const entries = await this.parseBulkImportFile(file);

        if (!entries.length) {
          this.showTimedToast('error', 'لم يتم العثور على أسماء صالحة داخل الملف.');
          return;
        }

        const branchId = this.dialogBranchId || this.activeBranchId || 'male';
        const failures = [];
        let successCount = 0;

        for (const entry of entries) {
          try {
            if (this.dialogEntityType === 'student') {
              await createStudentRequest({
                name: entry.name,
                loginId: entry.loginCode,
                branchId,
                note: '',
              });
            } else {
              await saveReciterRequest({
                currentLoginCode: null,
                name: entry.name,
                loginCode: entry.loginCode,
                branchId,
                linkedStudentIds: [],
              });
            }

            successCount += 1;
          } catch (error) {
            failures.push({
              name: entry.name,
              message: error?.response?.data?.message || 'تعذر الاستيراد',
            });
          }
        }

        if (successCount > 0) {
          await this.loadDashboardSnapshot();
        }

        if (successCount > 0 && failures.length === 0) {
          this.showTimedToast('success', `تمت إضافة ${successCount} ${this.dialogEntityType === 'student' ? 'معلم' : 'مقرئ'} من الملف.`);
          return;
        }

        if (successCount > 0 && failures.length > 0) {
          const sampleFailures = failures.slice(0, 3).map((item) => `${item.name}: ${item.message}`).join(' | ');
          this.showTimedToast('info', `تمت إضافة ${successCount} عنصر، وتعذر استيراد ${failures.length}. ${sampleFailures}`, { timeout: 6500 });
          return;
        }

        this.showTimedToast('error', failures[0]?.message || 'تعذر استيراد الملف.');
      } catch (error) {
        this.showTimedToast('error', 'تعذر قراءة ملف الإكسل. تأكد من أن أول ورقة تحتوي على الأسماء والأرقام.');
      } finally {
        this.bulkImporting = false;
        this.bulkFilePickerOpen = false;
        this.clearBulkFileInput();
      }
    },
    async submitDialog() {
      try {
        if (this.dialogEntityType === 'student') {
          if (this.isEditing) {
            if (!this.editingStudentRecord) {
              this.showTimedToast('error', 'اختر المعلم أولًا');
              return;
            }

            await this.updateStudent({
              studentId: this.editingStudentRecord.id,
              updates: {
                name: this.studentForm.name,
                loginCode: this.studentForm.loginId,
                branchId: this.studentForm.branchId,
                note: this.studentForm.note,
              },
            });
            this.selectedStudentId = this.editingStudentRecord.id;
            this.showTimedToast('success', 'تم تحديث بيانات المعلم');
          } else {
            await this.addStudent({
              name: this.studentForm.name,
              loginId: this.studentForm.loginId,
              branchId: this.studentForm.branchId,
              note: this.studentForm.note,
            });
            this.showTimedToast('success', 'تمت إضافة المعلم');
          }

          await this.loadDashboardSnapshot();
        } else {
          if (this.isEditing && !this.editingReciterRecord) {
            this.showTimedToast('error', 'اختر المقرئ أولًا');
            return;
          }

          await this.saveReciter({
            currentLoginCode: this.isEditing ? this.editingReciterRecord.loginCode : null,
            name: this.reciterForm.name,
            loginCode: this.reciterForm.loginCode,
            branchId: this.reciterForm.branchId,
            linkedStudentIds: this.reciterForm.studentIds,
          });
          this.showTimedToast('success', this.isEditing ? 'تم تحديث المقرئ' : 'تمت إضافة المقرئ');
          await this.loadDashboardSnapshot();
        }

        this.dialogOpen = false;
        this.resetForms();
      } catch (error) {
        this.showTimedToast('error', error?.response?.data?.message || 'تعذر حفظ البيانات');
      }
    },
  },
};
</script>

<style scoped>
.people-page {
  padding: 8px 0 0;
  direction: rtl;
  text-align: right;
}

.people-alert {
  margin-bottom: 18px;
  padding: 14px 18px;
  border-radius: 20px;
  font-size: 0.94rem;
  font-weight: 700;
}

.people-alert--error {
  border: 1px solid rgba(220, 38, 38, 0.16);
  background: rgba(254, 242, 242, 0.95);
  color: #b91c1c;
}

.people-toolbar {
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
  gap: 18px;
  margin-bottom: 22px;
}

.people-toolbar__actions {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  flex-wrap: wrap;
}

.people-toolbar-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 48px;
  padding: 0 20px;
  border-radius: 999px;
  border: 1px solid #cfe3ea;
  background: #fff;
  color: #0b5873;
  box-shadow: 0 8px 18px rgba(8, 65, 89, 0.06);
  transition: 0.18s ease;
  font-size: 0.96rem;
  font-weight: 900;
}

.people-toolbar-button--primary {
  border-color: transparent;
  background: linear-gradient(180deg, #1e809b 0%, #146d88 100%);
  color: #fff;
}

.people-toolbar-button--danger {
  border-color: transparent;
  background: linear-gradient(180deg, #dc2626 0%, #b91c1c 100%);
  color: #fff;
}

.people-toolbar-button--ghost {
  color: #1e6077;
}

.people-toolbar-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.people-toolbar__filters {
  display: flex;
  align-items: flex-end;
  justify-content: flex-start;
  gap: 18px;
  flex-wrap: wrap;
}

.people-filter-field {
  width: min(260px, 100%);
  flex: 1 1 240px;
}

.people-filter-field__label {
  margin-bottom: 8px;
  color: #5f7684;
  font-size: 0.95rem;
  font-weight: 700;
  text-align: right;
}

.people-select :deep(.v-input__slot) {
  min-height: 42px !important;
  border-radius: 16px !important;
  box-shadow: none !important;
  direction: rtl;
}

.people-select :deep(input),
.people-select :deep(.v-select__selection),
.people-select :deep(.v-select__selections) {
  text-align: right;
  justify-content: flex-end;
}

.people-select :deep(.v-input__append-inner) {
  margin-top: 0 !important;
  margin-right: auto !important;
  margin-left: 0 !important;
}

.people-empty-state {
  display: grid;
  place-items: center;
  min-height: 240px;
  border-radius: 28px;
  border: 1px dashed rgba(195, 214, 223, 0.9);
  background: rgba(255, 255, 255, 0.88);
  color: #5f7684;
  font-size: 1rem;
  font-weight: 700;
}

.people-cards-list {
  display: grid;
  gap: 18px;
}

.people-card {
  padding: 18px 18px 20px;
  border-radius: 30px;
  border: 1px solid rgba(219, 231, 236, 0.92);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.97) 0%, rgba(252, 254, 255, 0.96) 100%);
  box-shadow: 0 14px 32px rgba(12, 64, 82, 0.08);
  cursor: pointer;
  transition: 0.18s ease;
}

.people-card--active {
  border-color: rgba(30, 128, 155, 0.4);
  box-shadow: 0 18px 36px rgba(18, 96, 119, 0.12);
}

.people-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-direction: row-reverse;
  gap: 16px;
  margin-bottom: 18px;
}

.people-card__actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-start;
}

.people-card__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 106px;
  min-height: 40px;
  padding: 0 18px;
  border-radius: 999px;
  border: 1px solid #d4e5ec;
  color: #406070;
  font-size: 0.95rem;
  font-weight: 800;
  background: #fff;
  cursor: pointer;
}

.people-card__icon-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border-radius: 999px;
  border: 1px solid #d4e5ec;
  background: #fff;
  color: #527082;
  cursor: pointer;
}

.people-card__icon-button--edit:hover {
  border-color: rgba(27, 111, 135, 0.38);
  color: #1b6f87;
}

.people-card__icon-button--delete:hover {
  border-color: rgba(190, 69, 69, 0.32);
  color: #b42323;
  background: rgba(190, 69, 69, 0.07);
}

.people-card__badge--certified {
  border-color: rgba(34, 139, 84, 0.28);
  background: rgba(34, 139, 84, 0.12);
  color: #1f7a48;
}

.people-card__badge--pending {
  color: #6a8593;
}

.people-card__identity {
  text-align: right;
}

.people-card__name {
  margin: 0;
  color: #0b4560;
  font-size: 1.04rem;
  font-weight: 900;
}

.people-card__meta {
  margin-top: 4px;
  color: #7b93a1;
  font-size: 0.92rem;
  font-weight: 600;
}

.people-card__meta-button {
  margin-top: 4px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #1b6f87;
  font-size: 0.92rem;
  font-weight: 700;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 4px;
}

.people-card__meta-button--unlinked {
  color: #b66a2f;
}

.people-card__metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.people-metric {
  padding: 14px 16px 16px;
  border-radius: 20px;
  border: 1px solid rgba(220, 231, 237, 0.95);
  background: #fff;
}

.people-metric--clickable {
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.people-metric--clickable:hover {
  transform: translateY(-1px);
  border-color: #98c8d8;
  box-shadow: 0 14px 28px rgba(16, 86, 110, 0.1);
}

.people-metric__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.people-metric__value {
  color: #0e4b63;
  font-size: 0.96rem;
  font-weight: 900;
}

.people-metric__label {
  color: #5d7483;
  font-size: 0.96rem;
  font-weight: 800;
}

.people-metric__track {
  height: 12px;
  border-radius: 999px;
  background: #edf3f6;
  overflow: hidden;
}

.people-metric__fill {
  height: 100%;
  min-width: 24px;
  border-radius: inherit;
  background: linear-gradient(90deg, #1c86a0 0%, #1f7f98 100%);
}

.people-dialog {
  padding: 34px 40px 40px;
  border-radius: 36px;
  background: #fff;
  direction: rtl;
  text-align: right;
}

.people-confirm-dialog {
  border-radius: 24px;
}

.people-confirm-dialog__text {
  margin: 0;
  color: #314a58;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.8;
}

.people-confirm-dialog__text strong {
  color: #0b4560;
  font-weight: 900;
}

.people-manage-dialog {
  position: relative;
  overflow: visible;
}

.people-manage-dialog__form {
  overflow: visible;
}

.people-manage-dialog :deep(.v-menu__content) {
  z-index: 40 !important;
}

:deep(.people-manage-dropdown) {
  z-index: 10000 !important;
  border: 1px solid rgba(146, 199, 219, 0.9) !important;
  border-radius: 18px !important;
  box-shadow: 0 24px 56px rgba(15, 23, 42, 0.22) !important;
}

.people-dialog__select :deep(input::placeholder) {
  color: rgba(13, 72, 98, 0.42) !important;
  opacity: 1;
  font-weight: 800;
}

.people-parts-dialog__body {
  display: grid;
  gap: 18px;
  width: min(78vw, 448px);
  padding: 12px 12px 18px;
}

.people-parts-dialog__header {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr) 36px;
  align-items: center;
  width: 100%;
}

.people-parts-dialog__title {
  margin: 0;
  color: #0f172a;
  font-size: 1.15rem;
  font-weight: 900;
  text-align: center;
  line-height: 1.2;
}

.people-parts-dialog__header-spacer {
  display: block;
  width: 36px;
  height: 36px;
}

.people-parts-dialog__close-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: #5f788e;
  font-size: 1.9rem;
  line-height: 0.9;
  cursor: pointer;
  justify-self: start;
}

.people-parts-dialog__grid {
  display: grid;
  grid-template-columns: repeat(6, 52px);
  gap: 12px;
  width: 100%;
  justify-content: center;
  justify-items: center;
}

.people-parts-dialog__circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-width: 0;
  aspect-ratio: 1;
  padding: 0;
  border: 1px solid #d8e5ed;
  border-radius: 999px;
  background: #fff;
  color: #60788d;
  font-size: 0.94rem;
  font-weight: 900;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease, color 0.18s ease;
}

.people-parts-dialog__circle:hover {
  transform: translateY(-1px);
  border-color: #72bfd6;
  box-shadow: 0 10px 22px rgba(14, 116, 144, 0.12);
}

.people-parts-dialog__circle--active {
  border-color: rgba(14, 116, 144, 0.2);
  background: linear-gradient(145deg, #0d7490, #0f3f5c);
  color: #fff;
  box-shadow: 0 12px 26px rgba(8, 61, 93, 0.22);
}

.people-parts-dialog__circle:disabled {
  opacity: 0.72;
  cursor: wait;
}

.people-dialog__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.people-dialog__bulk-button {
  min-height: 42px;
  padding: 0 20px;
  border-radius: 999px;
  border: 1px solid #c9e0e9;
  background: #fff;
  color: #2f6377;
  font-size: 0.96rem;
  font-weight: 900;
}

.people-dialog__bulk-input {
  display: none;
}

.people-dialog__title {
  margin: 0;
  color: #0a445e;
  font-size: 1.14rem;
  font-weight: 900;
}

.people-dialog__divider {
  height: 1px;
  margin: 18px 0 26px;
  background: #e1edf2;
}

.people-dialog__form {
  display: grid;
  gap: 18px;
}

.people-dialog__field {
  display: grid;
  gap: 10px;
}

.people-linked-students {
  display: grid;
  gap: 10px;
  max-height: 260px;
  padding: 12px;
  overflow-y: auto;
  border: 1px solid rgba(216, 228, 234, 0.96);
  border-radius: 26px;
  background: linear-gradient(180deg, rgba(248, 252, 253, 0.98) 0%, rgba(255, 255, 255, 0.98) 100%);
}

.people-linked-students__item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  min-height: 54px;
  padding: 0 16px;
  border: 1px solid rgba(214, 227, 233, 0.94);
  border-radius: 22px;
  background: #fff;
  color: #173f55;
  font-size: 1rem;
  font-weight: 700;
  text-align: right;
}

.people-linked-students__item--active {
  border-color: rgba(30, 128, 155, 0.32);
  background: rgba(30, 128, 155, 0.08);
  color: #15637d;
}

.people-linked-students__mark {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid rgba(60, 163, 201, 0.82);
  background: #fff;
  flex: 0 0 24px;
}

.people-linked-students__item--active .people-linked-students__mark {
  border-color: #1e809b;
  background: radial-gradient(circle at center, #1e809b 0 42%, #fff 43% 100%);
}

.people-linked-students__name {
  flex: 1;
}

.people-linked-students__empty {
  padding: 20px 14px;
  color: #6c8592;
  font-size: 0.95rem;
  font-weight: 700;
  text-align: center;
}

.people-dialog__label {
  color: #0d4862;
  font-size: 0.98rem;
  font-weight: 900;
  text-align: right;
}

.people-dialog__select :deep(.v-input__slot),
.people-dialog__input :deep(.v-input__slot) {
  min-height: 50px !important;
  border-radius: 18px !important;
  box-shadow: none !important;
  direction: rtl;
}

.people-dialog__select :deep(input),
.people-dialog__select :deep(.v-select__selection),
.people-dialog__select :deep(.v-select__selections),
.people-dialog__input :deep(input) {
  text-align: right;
}

.people-dialog__select :deep(.v-select__selection),
.people-dialog__select :deep(.v-select__selections) {
  justify-content: flex-end;
}

.people-dialog__select :deep(.v-input__append-inner) {
  margin-top: 0 !important;
  margin-right: auto !important;
  margin-left: 0 !important;
}

.people-dialog__actions {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 18px;
}

.people-dialog__submit,
.people-dialog__cancel {
  min-height: 54px;
  padding: 0 26px;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 900;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.people-dialog__submit--danger {
  border-color: transparent;
  background: linear-gradient(180deg, #dc2626 0%, #b91c1c 100%);
  color: #fff;
  box-shadow: 0 10px 24px rgba(185, 28, 28, 0.18);
}

.people-dialog__submit--danger:hover,
.people-dialog__submit--danger:focus,
.people-dialog__submit--danger:active {
  background: linear-gradient(180deg, #dc2626 0%, #b91c1c 100%);
  color: #fff;
  transform: translateY(-2px);
  box-shadow: 0 14px 28px rgba(185, 28, 28, 0.18);
}

.people-dialog__submit--manage {
  border-color: rgba(16, 118, 153, 0.18);
  background: #ffffff;
  color: #154b67;
  box-shadow: rgba(27, 31, 35, 0.06) 0 1px 0;
}

.people-dialog__submit--manage:hover,
.people-dialog__submit--manage:focus,
.people-dialog__submit--manage:active {
  background: #ffffff;
  color: #154b67;
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(27, 31, 35, 0.08);
}

.people-dialog__submit {
  border: 0;
  background: linear-gradient(180deg, #1e809b 0%, #146d88 100%);
  color: #fff;
}

.people-dialog__cancel {
  border: 1px solid #c9e0e9;
  background: #fff;
  color: #315f73;
}

.people-dialog__cancel:hover,
.people-dialog__cancel:focus,
.people-dialog__cancel:active {
  border-color: #c9e0e9;
  background: #fff;
  color: #315f73;
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(44, 125, 156, 0.08);
}

@media (max-width: 960px) {
  .people-toolbar {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .people-toolbar__filters {
    width: 100%;
    flex-wrap: nowrap;
    gap: 12px;
  }

  .people-filter-field {
    width: 0;
    flex: 1 1 0;
  }

  .people-card__metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .people-parts-dialog__grid {
    grid-template-columns: repeat(6, 44px);
  }

  .people-parts-dialog__circle {
    width: 44px;
    height: 44px;
  }
}

@media (max-width: 640px) {
  .people-toolbar__filters {
    flex-direction: row;
    align-items: flex-end;
    justify-content: stretch;
    flex-wrap: nowrap;
    gap: 10px;
  }

  .people-toolbar__actions {
    display: none;
  }

  .people-card__header {
    flex-direction: column;
    align-items: stretch;
  }

  .people-filter-field {
    width: 0;
    flex: 1 1 0;
    min-width: 0;
  }

  .people-filter-field__label {
    margin-bottom: 6px;
    font-size: 0.84rem;
  }

  .people-card__metrics {
    grid-template-columns: minmax(0, 1fr);
  }

  .people-dialog {
    padding: 26px 18px 28px;
    border-radius: 28px;
  }

  .people-parts-dialog__body {
    width: 100%;
    padding-inline: 0;
  }

  .people-parts-dialog__grid {
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
  }

  .people-parts-dialog__circle {
    width: 100%;
    max-width: 44px;
    height: 44px;
  }

  .people-dialog__header,
  .people-dialog__actions {
    flex-wrap: wrap;
  }
}
</style>
