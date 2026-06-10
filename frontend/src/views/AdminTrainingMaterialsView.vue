<template>
  <div
    class="admin-training-materials"
    :class="{ 'admin-training-materials--embedded': embedded }"
  >
    <section class="admin-training-materials__card">
      <div class="admin-training-materials__toolbar">
        <div class="prep-field admin-training-materials__filter-field">
          <label class="prep-field__label">المادة التدريبية</label>
          <AppSelect
            :key="materialSelectResetKey"
            v-model="materialSelectValue"
            :items="materialOptions"
            item-text="label"
            item-value="value"
            placeholder="اختر المادة"
            persistent-placeholder
            hide-details
            dense
            outlined
            class="prep-select admin-training-materials__select"
          />
        </div>

        <div
          v-if="selectedMaterial"
          class="admin-training-materials__toolbar-actions"
        >
          <button
            type="button"
            class="admin-training-materials__toolbar-icon-button admin-training-materials__toolbar-icon-button--edit"
            aria-label="تعديل المادة"
            @click="openEditDialog()"
          >
            <v-icon small>
              mdi-pencil-outline
            </v-icon>
          </button>
          <button
            type="button"
            class="admin-training-materials__toolbar-icon-button admin-training-materials__toolbar-icon-button--delete"
            aria-label="حذف المادة"
            :disabled="deletingId === selectedMaterial.id"
            @click="removeMaterial(selectedMaterial.id)"
          >
            <i
              class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
              aria-hidden="true"
            />
          </button>
        </div>
      </div>

      <div
        v-if="!selectedMaterial"
        class="admin-training-materials__empty"
      >
        {{ hasMaterials ? 'اختر مادة من القائمة أو اختر إضافة مادة لإنشاء مادة جديدة.' : 'لا توجد مواد تدريبية بعد. اختر إضافة مادة من القائمة لإنشاء أول مادة.' }}
      </div>

      <div v-else>
        <TrainingMaterialsList
          :materials="[selectedMaterial]"
          preview-in-dialog
          empty-title="لا توجد بيانات لعرضها."
          empty-description=""
        />
      </div>
    </section>

    <AppDialog
      v-model="dialogOpen"
      max-width="760"
      @close="closeDialog"
    >
      <div class="admin-training-materials__dialog">
        <AppDialogHeader :title="dialogTitle" />

        <AppDialogBody class="admin-training-materials__dialog-body">
          <div
            v-if="formError"
            class="admin-training-materials__form-error"
            role="alert"
          >
            {{ formError }}
          </div>

          <label class="admin-training-materials__field">
            <span class="admin-training-materials__label">العنوان</span>
            <input
              v-model.trim="form.title"
              type="text"
              class="admin-training-materials__input"
            >
          </label>

          <label class="admin-training-materials__field">
            <span class="admin-training-materials__label">الفرع</span>
            <AppSelect
              v-model="form.branchId"
              :items="branchOptions"
              item-text="label"
              item-value="id"
              dense
              outlined
              hide-details
              class="admin-training-materials__select"
            />
          </label>

          <label class="admin-training-materials__field">
            <span class="admin-training-materials__label">الوصف</span>
            <textarea
              v-model.trim="form.description"
              class="admin-training-materials__textarea"
              rows="4"
            />
          </label>

          <div class="admin-training-materials__field">
            <div class="admin-training-materials__attachments-header">
              <span class="admin-training-materials__label">المرفقات</span>
              <button
                type="button"
                class="admin-training-materials__add-attachment"
                @click="addAttachmentRow"
              >
                +
              </button>
            </div>

            <div class="admin-training-materials__attachments">
              <div
                v-for="(attachment, index) in form.attachments"
                :key="attachment.id"
                class="admin-training-materials__attachment-block"
              >
                <div class="admin-training-materials__attachment-row">
                  <input
                    v-model.trim="attachment.label"
                    type="text"
                    class="admin-training-materials__input admin-training-materials__attachment-name-input"
                    placeholder="اسم الملف أو رابط المقطع"
                  >

                  <div class="admin-training-materials__attachment-actions">
                    <label
                      class="admin-training-materials__attachment-picker"
                      title="اختيار ملف"
                    >
                      <input
                        type="file"
                        accept="*/*"
                        class="admin-training-materials__hidden-file-input"
                        @change="handleAttachmentFileChange(index, $event)"
                      >
                      <v-icon size="21">
                        mdi-file-upload-outline
                      </v-icon>
                    </label>

                    <button
                      type="button"
                      class="admin-training-materials__remove-attachment"
                      aria-label="حذف المرفق"
                      @click="removeAttachmentRow(index)"
                    >
                      <i
                        class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                        aria-hidden="true"
                      />
                    </button>
                  </div>
                </div>

                <div
                  v-if="attachment.file"
                  class="admin-training-materials__files-note"
                >
                  {{ attachment.file.name }}
                </div>
                <div
                  v-else-if="attachment.existingFileName"
                  class="admin-training-materials__files-note"
                >
                  {{ attachment.existingFileName }}
                </div>
              </div>
            </div>
          </div>
        </AppDialogBody>

        <AppDialogFooter class="admin-training-materials__dialog-footer">
          <AppButton
            variant="secondary"
            @click="closeDialog"
          >
            إلغاء
          </AppButton>
          <AppButton
            variant="primary"
            :loading="submitting"
            @click="submitMaterial"
          >
            {{ submitButtonLabel }}
          </AppButton>
        </AppDialogFooter>
      </div>
    </AppDialog>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import {
  AppButton,
  AppDialog,
  AppDialogBody,
  AppDialogFooter,
  AppDialogHeader,
  AppSelect,
} from '../components/ui';
import TrainingMaterialsList from '../components/TrainingMaterialsList.vue';
import { createTrainingMaterial, deleteTrainingMaterial, updateTrainingMaterial } from '../services/api';

const ADD_MATERIAL_OPTION_VALUE = '__add_material__';

let attachmentDraftCounter = 0;

const createAttachmentDraft = (overrides = {}) => ({
  id: `attachment-${attachmentDraftCounter += 1}`,
  label: '',
  file: null,
  existingAttachmentId: '',
  existingFileName: '',
  url: '',
  ...overrides,
});

const resolveDefaultAttachmentLabel = (file) => {
  const fileName = String(file?.name || '').trim();

  if (!fileName) {
    return '';
  }

  const extensionIndex = fileName.lastIndexOf('.');

  if (extensionIndex <= 0) {
    return fileName;
  }

  return fileName.slice(0, extensionIndex).trim() || fileName;
};

export default {
  name: 'AdminTrainingMaterialsView',
  components: {
    AppButton,
    AppDialog,
    AppDialogBody,
    AppDialogFooter,
    AppDialogHeader,
    AppSelect,
    TrainingMaterialsList,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      dialogOpen: false,
      dialogMode: 'create',
      editingMaterialId: '',
      selectedMaterialId: '',
      materialSelectResetKey: 0,
      submitting: false,
      deletingId: '',
      formError: '',
      form: {
        title: '',
        branchId: 'all',
        description: '',
        attachments: [createAttachmentDraft()],
      },
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot']),
    trainingMaterials() {
      return this.dashboardSnapshot?.trainingMaterials || [];
    },
    hasMaterials() {
      return this.trainingMaterials.length > 0;
    },
    materialOptions() {
      return [
        ...this.trainingMaterials.map((material) => ({
          label: material.title,
          value: material.id,
        })),
        { label: 'إضافة مادة', value: ADD_MATERIAL_OPTION_VALUE },
      ];
    },
    materialSelectValue: {
      get() {
        return this.selectedMaterialId || null;
      },
      set(value) {
        if (value === ADD_MATERIAL_OPTION_VALUE) {
          const previousSelectedId = this.selectedMaterialId;

          this.openCreateDialog();
          this.$nextTick(() => {
            this.selectedMaterialId = previousSelectedId;
            this.materialSelectResetKey += 1;
          });
          return;
        }

        this.selectedMaterialId = value || '';
      },
    },
    selectedMaterial() {
      return this.trainingMaterials.find((material) => material.id === this.selectedMaterialId) || null;
    },
    branchOptions() {
      return [
        { id: 'all', label: 'كل الفروع' },
        { id: 'supervision', label: 'الإشراف' },
        ...((this.dashboardSnapshot?.branches || []).map((branch) => ({ id: branch.id, label: branch.label }))),
      ];
    },
    dialogTitle() {
      return this.dialogMode === 'edit' ? 'تعديل المادة التدريبية' : 'إضافة مادة تدريبية';
    },
    submitButtonLabel() {
      return this.dialogMode === 'edit' ? 'حفظ التعديلات' : 'إضافة المادة';
    },
  },
  watch: {
    trainingMaterials: {
      immediate: true,
      handler(nextMaterials) {
        if (!nextMaterials.length) {
          this.selectedMaterialId = '';
          return;
        }

        if (!nextMaterials.some((material) => material.id === this.selectedMaterialId)) {
          this.selectedMaterialId = nextMaterials[0].id;
        }
      },
    },
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot']),
    openCreateDialog() {
      this.dialogMode = 'create';
      this.editingMaterialId = '';
      this.formError = '';
      this.resetForm();
      this.dialogOpen = true;
    },
    openEditDialog(material = this.selectedMaterial) {
      if (!material) {
        return;
      }

      this.dialogMode = 'edit';
      this.editingMaterialId = material.id;
      this.formError = '';
      this.form = {
        title: material.title || '',
        branchId: material.targetBranchId || 'all',
        description: material.description || '',
        attachments: (material.attachments || []).map((attachment) => createAttachmentDraft({
          label: attachment.type === 'youtube'
            ? (attachment.url || '')
            : (attachment.displayName || attachment.originalName || attachment.name || ''),
          existingAttachmentId: attachment.id || '',
          existingFileName: attachment.type === 'youtube' ? '' : (attachment.originalName || attachment.name || ''),
          url: attachment.type === 'youtube' ? (attachment.url || '') : '',
        })),
      };

      if (!this.form.attachments.length) {
        this.form.attachments = [createAttachmentDraft()];
      }

      this.dialogOpen = true;
    },
    closeDialog() {
      this.dialogOpen = false;
      this.resetForm();
      this.dialogMode = 'create';
      this.editingMaterialId = '';
      this.formError = '';
      if (this.selectedMaterialId === ADD_MATERIAL_OPTION_VALUE) {
        this.selectedMaterialId = '';
      }
      this.materialSelectResetKey += 1;
    },
    addAttachmentRow() {
      this.form.attachments.push(createAttachmentDraft());
    },
    removeAttachmentRow(index) {
      this.form.attachments.splice(index, 1);
    },
    handleAttachmentFileChange(index, event) {
      const [file] = Array.from(event?.target?.files || []);

      if (!this.form.attachments[index]) {
        return;
      }

      this.$set(this.form.attachments[index], 'file', file || null);

      if (!file) {
        return;
      }

      this.$set(this.form.attachments[index], 'existingAttachmentId', '');
      this.$set(this.form.attachments[index], 'existingFileName', '');
      this.$set(this.form.attachments[index], 'url', '');

      const currentLabel = String(this.form.attachments[index].label || '').trim();

      if (!currentLabel) {
        this.$set(this.form.attachments[index], 'label', resolveDefaultAttachmentLabel(file));
      }
    },
    normalizedAttachments() {
      return this.form.attachments
        .map((attachment) => {
          const value = String(attachment.label || '').trim();
          const url = this.normalizeYoutubeUrl(value);

          return {
            id: String(attachment.existingAttachmentId || '').trim(),
            label: url ? 'مقطع يوتيوب' : (value || resolveDefaultAttachmentLabel(attachment.file)),
            file: attachment.file || null,
            url,
          };
        })
        .filter((attachment) => attachment.id || attachment.label || attachment.file || attachment.url);
    },
    isYoutubeUrl(value) {
      return /^(https?:\/\/)?(www\.|m\.)?(youtube\.com|youtu\.be)\//i.test(String(value || '').trim());
    },
    normalizeYoutubeUrl(value) {
      const url = String(value || '').trim();

      if (!this.isYoutubeUrl(url)) {
        return '';
      }

      return /^https?:\/\//i.test(url) ? url : `https://${url}`;
    },
    resolveRequestError(error) {
      const errors = error?.response?.data?.errors;

      if (errors && typeof errors === 'object') {
        const firstMessage = Object.values(errors).flat().find(Boolean);

        if (firstMessage) {
          return String(firstMessage);
        }
      }

      return error?.response?.data?.message
        || (error?.code === 'ECONNABORTED' ? 'انتهت مهلة رفع الملف. حاول مرة أخرى.' : '')
        || 'تعذر حفظ المادة التدريبية. تأكد من البيانات وحجم الملف ثم حاول مرة أخرى.';
    },
    resetForm() {
      this.form = {
        title: '',
        branchId: 'all',
        description: '',
        attachments: [createAttachmentDraft()],
      };
    },
    async submitMaterial() {
      this.formError = '';
      const attachments = this.normalizedAttachments();

      if (!this.form.title || !attachments.length) {
        this.formError = 'أدخل العنوان وأضف ملفًا أو رابط يوتيوب واحدًا على الأقل.';
        this.$toast.error(this.formError);
        return;
      }

      const incompleteAttachment = attachments.find((attachment) => !attachment.label
        || (!attachment.id && !attachment.file && !attachment.url));

      if (incompleteAttachment) {
        this.formError = 'أدخل اسم الملف واختر ملفًا، أو ألصق رابط يوتيوب صحيحًا.';
        this.$toast.error(this.formError);
        return;
      }

      this.submitting = true;

      try {
        const payload = {
          title: this.form.title,
          description: this.form.description,
          branchId: this.form.branchId,
          attachments,
        };

        const isEditMode = this.dialogMode === 'edit' && this.editingMaterialId;
        const result = isEditMode
          ? await updateTrainingMaterial(this.editingMaterialId, payload)
          : await createTrainingMaterial(payload);

        await this.loadDashboardSnapshot();
        this.selectedMaterialId = result?.id || this.selectedMaterialId;
        this.closeDialog();
        this.$toast.success(this.dialogMode === 'edit' ? 'تم تحديث المادة التدريبية' : 'تمت إضافة المادة التدريبية');
      } catch (error) {
        this.formError = this.resolveRequestError(error);
        this.$toast.error(this.formError);
      } finally {
        this.submitting = false;
      }
    },
    async removeMaterial(materialId) {
      if (!materialId) {
        return;
      }

      this.deletingId = materialId;

      try {
        await deleteTrainingMaterial(materialId);
        await this.loadDashboardSnapshot();
        this.$toast.success('تم حذف المادة التدريبية');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حذف المادة التدريبية');
      } finally {
        this.deletingId = '';
      }
    },
  },
};
</script>

<style scoped>
.admin-training-materials {
  display: grid;
  gap: 18px;
}

.admin-training-materials__card,
.admin-training-materials__dialog {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 250, 250, 0.96) 100%);
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
}

.admin-training-materials__card {
  padding: 24px;
}

.admin-training-materials__toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.admin-training-materials__filter-field {
  width: min(420px, 100%);
}

.admin-training-materials__toolbar-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.admin-training-materials__toolbar-icon-button {
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

.admin-training-materials__toolbar-icon-button--edit:hover {
  border-color: rgba(27, 111, 135, 0.38);
  color: #1b6f87;
}

.admin-training-materials__toolbar-icon-button--delete:hover {
  border-color: rgba(190, 69, 69, 0.32);
  color: #b42323;
  background: rgba(190, 69, 69, 0.07);
}

.admin-training-materials__toolbar-icon-button:disabled {
  opacity: 0.6;
  cursor: default;
}

.admin-training-materials__empty {
  border: 1px dashed rgba(159, 191, 207, 0.9);
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.86);
  color: #5f7384;
  padding: 30px 24px;
  text-align: center;
  line-height: 1.9;
}

.admin-training-materials__delete-material {
  border: 0;
  border-radius: 999px;
  background: rgba(196, 64, 64, 0.12);
  color: #a52828;
  cursor: pointer;
  font-weight: 800;
  padding: 10px 16px;
}

.admin-training-materials__delete-material:disabled {
  opacity: 0.6;
  cursor: default;
}

.admin-training-materials__dialog {
  overflow: hidden;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
}

.admin-training-materials__dialog-body {
  overflow-y: auto;
  max-height: calc(88vh - 150px);
  padding-inline-end: 8px;
}

.admin-training-materials__form-error {
  margin-bottom: 16px;
  padding: 12px 14px;
  border: 1px solid rgba(185, 28, 28, 0.18);
  border-radius: 12px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 0.9rem;
  font-weight: 800;
  line-height: 1.7;
}

.admin-training-materials__field {
  display: grid;
  gap: 8px;
  margin-bottom: 14px;
}

.admin-training-materials__label {
  color: #567285;
  font-size: 0.9rem;
  font-weight: 800;
}

.admin-training-materials__input,
.admin-training-materials__select :deep(.v-input__slot),
.admin-training-materials__textarea,
.admin-training-materials__file-input {
  width: 100%;
  border: 1px solid #d7e5eb;
  border-radius: 18px;
  background: #fff;
  color: #15354e;
  padding: 14px 16px;
}

.admin-training-materials__attachments {
  display: grid;
  gap: 12px;
}

.admin-training-materials__attachments-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.admin-training-materials__attachment-block {
  display: grid;
  gap: 8px;
  padding: 14px;
  border: 1px solid rgba(215, 229, 235, 0.9);
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.88);
}

.admin-training-materials__attachment-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
}

.admin-training-materials__attachment-picker {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: #256b83;
  cursor: pointer;
}

.admin-training-materials__attachment-picker:hover {
  background: rgba(37, 107, 131, 0.08);
  color: #164e63;
}

.admin-training-materials__hidden-file-input {
  display: none;
}

.admin-training-materials__attachment-name-input {
  min-width: 0;
}

.admin-training-materials__attachment-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}

.admin-training-materials__textarea {
  resize: vertical;
  min-height: 110px;
}

.admin-training-materials__select :deep(.v-input__slot) {
  min-height: 54px !important;
  box-shadow: none !important;
}

.admin-training-materials__select :deep(.v-select__selection),
.admin-training-materials__select :deep(.v-select__selections),
.admin-training-materials__select :deep(input) {
  color: #15354e !important;
  font-weight: 700;
}

.admin-training-materials__files-note {
  color: #5a7385;
  font-size: 0.9rem;
  line-height: 1.8;
}

.admin-training-materials__remove-attachment {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: #9d2c2c;
  cursor: pointer;
}

.admin-training-materials__remove-attachment:hover {
  color: #7f1d1d;
}

.admin-training-materials__add-attachment {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  margin-top: 4px;
  border: 1px solid rgba(143, 191, 211, 0.72);
  border-radius: 999px;
  background: #fff;
  color: #107699;
  cursor: pointer;
  font-size: 1.3rem;
  font-weight: 800;
}

.admin-training-materials__dialog-footer,
.admin-training-materials__actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 18px;
}

@media (max-width: 720px) {
  .admin-training-materials__toolbar,
  .admin-training-materials__attachment-row {
    grid-template-columns: 1fr;
  }

  .admin-training-materials__toolbar {
    align-items: stretch;
  }

  .admin-training-materials__toolbar-actions {
    justify-content: flex-end;
  }
}
</style>
