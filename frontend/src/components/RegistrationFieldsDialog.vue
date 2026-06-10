<template>
  <AppDialog
    :value="value"
    max-width="760"
    @input="$emit('input', $event)"
    @close="closeDialog"
  >
    <div class="registration-fields-dialog">
      <AppDialogHeader title="تعديل بيانات التسجيل" />

      <AppDialogBody>
        <div
          class="registration-fields-dialog__fixed-fields"
          aria-label="حقول التسجيل الأساسية"
        >
          <button
            v-for="field in fixedFields"
            :key="field"
            type="button"
            class="registration-fields-dialog__fixed-field"
            disabled
          >
            {{ field }}
          </button>
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

      <AppDialogFooter>
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
      fixedFields: ['الاسم', 'رقم الهوية', 'الجنس', 'العمر'],
      fieldTypeOptions: [
        { label: 'نصي', value: 'text' },
        { label: 'قائمة منسدلة', value: 'select' },
      ],
    };
  },
  watch: {
    value: {
      immediate: true,
      handler(isOpen) {
        if (isOpen) {
          this.formFields = this.fields.map((field) => this.createFieldDraft(field));
          return;
        }

        this.formFields = [];
      },
    },
  },
  methods: {
    createFieldDraft(field = {}) {
      const options = Array.isArray(field.options) ? field.options : [];

      return {
        id: field.id || `field-${Date.now()}-${Math.random().toString(16).slice(2)}`,
        label: field.label || '',
        type: field.type === 'select' ? 'select' : 'text',
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
          type: field.type === 'select' ? 'select' : 'text',
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
  margin-top: 16px;
}

.registration-fields-dialog__accept-label {
  display: block;
  margin-bottom: 8px;
  color: #334155;
  font-size: 0.92rem;
  font-weight: 800;
}

.registration-fields-dialog__fields-stack {
  display: grid;
  gap: 14px;
}

.registration-fields-dialog__fixed-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}

.registration-fields-dialog__fixed-field {
  min-height: 64px;
  padding: 12px 14px;
  border: 1px solid rgba(20, 109, 136, 0.16);
  border-radius: 16px;
  background: rgba(8, 118, 153, 0.07);
  color: #0f172a;
  font: inherit;
  font-size: 0.94rem;
  font-weight: 900;
  text-align: right;
  cursor: not-allowed;
  opacity: 1;
}

.registration-fields-dialog__field-editor {
  padding: 14px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  border-radius: 18px;
  background: rgba(248, 250, 252, 0.88);
}

.registration-fields-dialog__field-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(160px, 220px);
  gap: 12px;
}

.registration-fields-dialog__textarea {
  width: 100%;
  min-height: 108px;
  padding: 12px 14px;
  border: 1px solid rgba(15, 23, 42, 0.2);
  border-radius: 12px;
  background: #ffffff;
  color: #0f172a;
  font: inherit;
  resize: vertical;
}

.registration-fields-dialog__field-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 12px;
}

.registration-fields-dialog__field-required {
  margin: 0;
}

.registration-fields-dialog__add-field {
  margin-top: 14px;
}

@media (max-width: 960px) {
  .registration-fields-dialog__fixed-fields,
  .registration-fields-dialog__field-grid {
    grid-template-columns: 1fr;
  }
}
</style>
