<template>
  <AppDialog
    :value="value"
    max-width="760"
    @input="$emit('input', $event)"
    @close="closeDialog"
  >
    <div class="registration-fields-dialog app-dialog">
      <AppDialogHeader title="تعديل بيانات التسجيل" />

      <AppDialogBody class="registration-fields-dialog__body">
        <div
          class="registration-fields-dialog__fields-stack"
          aria-label="حقول التسجيل الأساسية"
        >
          <div
            v-for="field in fixedFields"
            :key="field.id"
            class="registration-fields-dialog__field-editor registration-fields-dialog__field-editor--fixed"
          >
            <div class="registration-fields-dialog__field-grid">
              <label class="registration-fields-dialog__accept-field">
                <span class="registration-fields-dialog__accept-label">السؤال</span>
                <AppTextField
                  :value="field.label"
                  class="registration-fields-dialog__input"
                  dense
                  outlined
                  hide-details
                  disabled
                />
              </label>

              <label class="registration-fields-dialog__accept-field">
                <span class="registration-fields-dialog__accept-label">النوع</span>
                <AppSelect
                  :value="field.type"
                  :items="fieldTypeOptions"
                  item-text="label"
                  item-value="value"
                  class="registration-fields-dialog__input"
                  dense
                  outlined
                  hide-details
                  disabled
                />
              </label>
            </div>
          </div>
        </div>

        <div class="registration-fields-dialog__fields-stack">
          <div
            v-for="(field, index) in formFields"
            :key="field.id"
            class="registration-fields-dialog__field-editor"
          >
            <div class="registration-fields-dialog__field-grid">
              <label class="registration-fields-dialog__accept-field">
                <span class="registration-fields-dialog__accept-label">السؤال</span>
                <AppTextField
                  v-model.trim="field.label"
                  class="registration-fields-dialog__input"
                  dense
                  outlined
                  hide-details
                />
              </label>

              <label class="registration-fields-dialog__accept-field">
                <span class="registration-fields-dialog__accept-label">النوع</span>
                <AppSelect
                  v-model="field.type"
                  :items="fieldTypeOptions"
                  item-text="label"
                  item-value="value"
                  class="registration-fields-dialog__input"
                  dense
                  outlined
                  hide-details
                />
              </label>
            </div>

            <label
              v-if="field.type === 'select'"
              class="registration-fields-dialog__accept-field"
            >
              <span class="registration-fields-dialog__accept-label">خيارات القائمة - كل خيار في سطر</span>
              <textarea
                v-model.trim="field.optionsText"
                class="registration-fields-dialog__textarea"
                rows="4"
              />
            </label>

            <div class="registration-fields-dialog__field-actions">
              <v-checkbox
                v-model="field.required"
                label="إلزامي"
                hide-details
                dense
                class="registration-fields-dialog__field-required"
              />
              <AppIconButton
                variant="danger"
                @click="removeRegistrationField(index)"
              >
                <i
                  class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                  aria-hidden="true"
                />
              </AppIconButton>
            </div>
          </div>
        </div>

        <AppButton
          variant="secondary"
          class="registration-fields-dialog__add-field"
          @click="addRegistrationField"
        >
          إضافة سؤال
        </AppButton>
      </AppDialogBody>

      <AppDialogFooter class="registration-fields-dialog__footer">
        <AppButton
          variant="secondary"
          @click="closeDialog"
        >
          إلغاء
        </AppButton>
        <AppButton
          variant="primary"
          :loading="loading"
          @click="submitFields"
        >
          حفظ
        </AppButton>
      </AppDialogFooter>
    </div>
  </AppDialog>
</template>

<script>
import {
  AppButton, AppDialog, AppDialogBody, AppDialogFooter, AppDialogHeader,
  AppIconButton, AppSelect, AppTextField,
} from './ui';

export default {
  name: 'RegistrationFieldsDialog',
  components: {
    AppButton,
    AppDialog,
    AppDialogBody,
    AppDialogFooter,
    AppDialogHeader,
    AppIconButton,
    AppSelect,
    AppTextField,
  },
  model: {
    prop: 'value',
    event: 'input',
  },
  props: {
    value: {
      type: Boolean,
      default: false,
    },
    fields: {
      type: Array,
      default: () => [],
    },
    loading: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      formFields: [],
      fixedFields: [
        { id: 'name', label: 'الاسم', type: 'text' },
        { id: 'loginCode', label: 'رقم الهوية', type: 'number' },
        { id: 'gender', label: 'الجنس', type: 'select' },
        { id: 'phone', label: 'رقم الجوال', type: 'number' },
      ],
      fieldTypeOptions: [
        { label: 'نصي', value: 'text' },
        { label: 'رقم', value: 'number' },
        { label: 'قائمة منسدلة', value: 'select' },
      ],
    };
  },
  watch: {
    value: {
      immediate: true,
      handler(isOpen) {
        if (isOpen) {
          this.syncFieldDrafts();
          return;
        }

        this.formFields = [];
      },
    },
    fields: {
      deep: true,
      handler() {
        if (this.value) {
          this.syncFieldDrafts();
        }
      },
    },
  },
  methods: {
    syncFieldDrafts() {
      this.formFields = this.fields.map((field) => this.createFieldDraft(field));
    },
    createFieldDraft(field = {}) {
      const options = Array.isArray(field.options) ? field.options : [];

      return {
        id: field.id || `field-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        label: field.label || '',
        type: ['number', 'select'].includes(field.type) ? field.type : 'text',
        required: field.required !== false,
        optionsText: options.join('\n'),
      };
    },
    addRegistrationField() {
      this.formFields.push(this.createFieldDraft());
    },
    removeRegistrationField(index) {
      this.formFields.splice(index, 1);
    },
    normalizeFieldDrafts() {
      return this.formFields
        .map((field) => ({
          id: field.id,
          label: String(field.label || '').trim(),
          type: ['number', 'select'].includes(field.type) ? field.type : 'text',
          required: Boolean(field.required),
          options: String(field.optionsText || '')
            .split(/\r?\n/)
            .map((option) => option.trim())
            .filter(Boolean),
        }))
        .filter((field) => field.label);
    },
    submitFields() {
      this.$emit('save', this.normalizeFieldDrafts());
    },
    closeDialog() {
      this.$emit('input', false);
      this.$emit('close');
    },
  },
};
</script>

<style scoped>
.registration-fields-dialog__accept-field {
  display: grid;
  gap: 8px;
  margin: 0;
}

.registration-fields-dialog__accept-label {
  color: #334155;
  font-size: 0.9rem;
  font-weight: 800;
}

.registration-fields-dialog__body {
  gap: 16px;
}

.registration-fields-dialog__fields-stack {
  display: grid;
  gap: 12px;
}

.registration-fields-dialog__field-editor {
  display: grid;
  gap: 12px;
  padding: 12px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  border-radius: 14px;
  background: rgba(248, 250, 252, 0.62);
}

.registration-fields-dialog__field-editor--fixed {
  background: rgba(241, 245, 249, 0.78);
}

.registration-fields-dialog__field-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(160px, 220px);
  gap: 12px;
}

.registration-fields-dialog__input {
  width: 100%;
}

.registration-fields-dialog__input :deep(.v-input__slot) {
  min-height: 48px !important;
  border-radius: 12px !important;
  box-shadow: none !important;
  direction: rtl;
}

.registration-fields-dialog__input :deep(input),
.registration-fields-dialog__input :deep(.v-select__selection),
.registration-fields-dialog__input :deep(.v-select__selections) {
  text-align: right;
}

.registration-fields-dialog__textarea {
  width: 100%;
  min-height: 96px;
  padding: 12px 14px;
  border: 1px solid rgba(143, 191, 211, 0.72);
  border-radius: 12px;
  background: #ffffff;
  color: #123f56;
  font: inherit;
  resize: vertical;
  outline: none;
}

.registration-fields-dialog__textarea:focus {
  border-color: #107699;
  box-shadow: 0 0 0 4px rgba(16, 118, 153, 0.14);
}

.registration-fields-dialog__field-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.registration-fields-dialog__field-required {
  margin: 0;
}

.registration-fields-dialog__add-field {
  justify-self: start;
  margin-top: 2px;
}

.registration-fields-dialog__footer {
  flex-shrink: 0;
}

@media (max-width: 960px) {
  .registration-fields-dialog__field-grid {
    grid-template-columns: 1fr;
  }
}
</style>
