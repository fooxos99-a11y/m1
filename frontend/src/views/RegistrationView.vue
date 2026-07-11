<template>
  <div class="registration-entry">
    <div class="registration-entry__orbit registration-entry__orbit--large" />
    <div class="registration-entry__orbit registration-entry__orbit--medium" />
    <div class="registration-entry__orbit registration-entry__orbit--small" />
    <div class="registration-entry__glow" />
    <div class="registration-entry__grid" />
    <div class="registration-entry__shade" />
    <div class="registration-entry__radial registration-entry__radial--one" />
    <div class="registration-entry__radial registration-entry__radial--two" />

    <v-container class="registration-entry__container py-8 py-md-12">
      <div class="registration-entry__stage">
        <v-card
          class="registration-entry__shell pa-5 pa-sm-6 pa-md-8"
          elevation="0"
        >
          <div class="registration-entry__brand">
            <img
              :src="$publicAsset('اللوقو-شفاف.png')"
              alt="شعار برنامج رخصة ممارس"
              class="registration-entry__logo"
            >
            <div class="registration-entry__divider" />
            <h1 class="registration-entry__title">
              التسجيل في برنامج رخصة ممارس
            </h1>
          </div>

          <div
            v-if="loading"
            class="registration-entry__state"
          >
            جارٍ تحميل التسجيل...
          </div>

          <div
            v-else-if="!isOpen"
            class="registration-entry__state registration-entry__state--closed"
          >
            التسجيل مغلق حاليًا.
          </div>

          <div
            v-else-if="submittedSuccessfully"
            class="registration-entry__state registration-entry__state--success"
          >
            تم الإرسال بنجاح
          </div>

          <template v-else>
            <div class="registration-entry__form-grid">
              <div class="registration-entry__field">
                <label class="registration-entry__label">الاسم</label>
                <AppTextField
                  v-model.trim="form.name"
                  dense
                  outlined
                  hide-details
                  class="registration-entry__input"
                  placeholder="الاسم"
                />
              </div>

              <div class="registration-entry__field">
                <label class="registration-entry__label">رقم الهوية</label>
                <AppTextField
                  v-model.trim="form.loginCode"
                  dense
                  outlined
                  hide-details
                  class="registration-entry__input"
                  placeholder="رقم الهوية"
                />
              </div>

              <div class="registration-entry__field">
                <label class="registration-entry__label">الجنس</label>
                <AppSelect
                  v-model="form.gender"
                  :items="genderOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="registration-entry__input"
                  placeholder="اختر الجنس"
                />
              </div>

              <div class="registration-entry__field">
                <label class="registration-entry__label">العمر</label>
                <AppTextField
                  v-model.number="form.age"
                  type="number"
                  min="1"
                  max="120"
                  dense
                  outlined
                  hide-details
                  class="registration-entry__input"
                  placeholder="العمر"
                />
              </div>

              <div
                v-for="field in registrationFields"
                :key="field.id"
                class="registration-entry__field"
              >
                <label class="registration-entry__label">{{ field.label }}</label>
                <AppTextField
                  v-if="field.type === 'text'"
                  v-model.trim="form.answers[field.id]"
                  dense
                  outlined
                  hide-details
                  class="registration-entry__input"
                  :placeholder="field.label"
                />
                <AppSelect
                  v-else
                  v-model="form.answers[field.id]"
                  :items="field.options"
                  dense
                  outlined
                  hide-details
                  class="registration-entry__input"
                  :placeholder="field.label"
                />
              </div>
            </div>

            <div class="registration-entry__actions">
              <AppButton
                variant="primary"
                :loading="submitting"
                @click="submitRegistration"
              >
                إرسال الطلب
              </AppButton>
            </div>
          </template>
        </v-card>
      </div>
    </v-container>
  </div>
</template>

<script>
import { AppButton, AppSelect, AppTextField } from '../components/ui';
import { fetchPublicRegistrationStatus, submitPublicRegistrationRequest } from '../services/api';

export default {
  name: 'RegistrationView',
  components: {
    AppButton,
    AppSelect,
    AppTextField,
  },
  data() {
    return {
      loading: false,
      submitting: false,
      submittedSuccessfully: false,
      isOpen: false,
      registrationFields: [],
      genderOptions: [
        { label: 'ذكر', value: 'male' },
        { label: 'أنثى', value: 'female' },
      ],
      form: {
        name: '',
        loginCode: '',
        gender: '',
        age: null,
        answers: {},
      },
    };
  },
  created() {
    this.loadStatus();
  },
  methods: {
    async loadStatus() {
      this.loading = true;

      try {
        const payload = await fetchPublicRegistrationStatus();
        this.isOpen = Boolean(payload?.isOpen);
        this.registrationFields = Array.isArray(payload?.fields) ? payload.fields : [];
        this.resetAnswers();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحميل حالة التسجيل');
      } finally {
        this.loading = false;
      }
    },
    async submitRegistration() {
      if (!this.form.name || !this.form.loginCode || !this.form.gender || !this.form.age || this.hasMissingRequiredAnswers()) {
        this.$toast.error('أكمل الاسم ورقم الهوية والجنس والعمر أولًا');
        return;
      }

      this.submitting = true;

      try {
        await submitPublicRegistrationRequest(this.form);
        this.$toast.success('تم الإرسال بنجاح');
        this.submittedSuccessfully = true;
        this.form = {
          name: '',
          loginCode: '',
          gender: '',
          age: null,
          answers: this.createEmptyAnswers(),
        };
      } catch (error) {
        const message = error?.response?.data?.errors?.loginCode?.[0]
          || error?.response?.data?.errors?.age?.[0]
          || error?.response?.data?.errors?.answers?.[0]
          || error?.response?.data?.errors?.registration?.[0]
          || error?.response?.data?.message
          || 'تعذر إرسال طلب التسجيل';
        this.$toast.error(message);
      } finally {
        this.submitting = false;
      }
    },
    createEmptyAnswers() {
      return Object.fromEntries(this.registrationFields.map((field) => [field.id, '']));
    },
    resetAnswers() {
      this.form.answers = this.createEmptyAnswers();
    },
    hasMissingRequiredAnswers() {
      return this.registrationFields.some((field) => field.required && !String(this.form.answers[field.id] || '').trim());
    },
  },
};
</script>

<style scoped>
.registration-entry {
  --registration-deep-color: #08384a;
  --registration-accent-color: #2a94b2;
  --registration-soft-color: #d9eff4;
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background: #08384a;
}

.registration-entry__container {
  position: relative;
  z-index: 2;
  max-width: 1040px;
}

.registration-entry__stage {
  max-width: 760px;
  margin: 0 auto;
}

.registration-entry__shell {
  position: relative;
  border: 1px solid rgba(255, 255, 255, 0.22);
  border-radius: 34px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(244, 250, 252, 0.95) 100%);
  box-shadow: 0 32px 80px rgba(3, 27, 36, 0.3);
}

.registration-entry__brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-bottom: 28px;
  text-align: center;
}

.registration-entry__logo {
  width: 96px;
  max-width: 100%;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(29%) sepia(57%) saturate(1148%) hue-rotate(157deg) brightness(90%) contrast(92%) drop-shadow(0 10px 18px rgba(10, 76, 102, 0.18));
}

.registration-entry__divider {
  width: 110px;
  height: 1px;
  background: rgba(8, 56, 74, 0.18);
}

.registration-entry__title {
  color: var(--registration-deep-color);
  font-size: 2rem;
  font-weight: 900;
  line-height: 1.4;
  margin: 0;
}

.registration-entry__state {
  padding: 28px;
  border-radius: 28px;
  background: rgba(248, 250, 252, 0.72);
  color: #244154;
  text-align: center;
  font-size: 1rem;
  font-weight: 800;
}

.registration-entry__state--closed {
  color: #b91c1c;
}

.registration-entry__state--success {
  border-color: rgba(22, 163, 74, 0.22);
  background: rgba(220, 252, 231, 0.78);
  color: #166534;
}

.registration-entry__form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}

.registration-entry__field {
  min-width: 0;
}

.registration-entry__label {
  display: block;
  margin-bottom: 8px;
  color: #244154;
  font-size: 0.96rem;
  font-weight: 800;
}

.registration-entry__input {
  width: 100%;
}

.registration-entry__input :deep(.v-input__slot) {
  min-height: var(--app-select-min-height) !important;
  padding-inline: 16px !important;
  border-radius: var(--app-select-radius) !important;
  background: var(--app-select-bg) !important;
  box-shadow: var(--app-select-shadow) !important;
  direction: rtl;
}

.registration-entry__input :deep(fieldset) {
  border-color: var(--app-select-border) !important;
  border-width: 1px !important;
}

.registration-entry__input.v-input--is-focused :deep(fieldset) {
  border-color: var(--app-select-border-strong) !important;
}

.registration-entry__input.v-input--is-focused :deep(.v-input__slot) {
  box-shadow: var(--app-select-focus-shadow) !important;
}

.registration-entry__input :deep(input),
.registration-entry__input :deep(.v-select__selection),
.registration-entry__input :deep(.v-select__selections) {
  color: var(--app-select-text) !important;
  font-family: 'Tajawal', system-ui, sans-serif !important;
  font-size: 1rem !important;
  font-weight: 500 !important;
  line-height: 1.45 !important;
  text-align: right;
}

.registration-entry__input :deep(.v-select__selections) {
  justify-content: flex-start;
}

.registration-entry__actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 24px;
}

.registration-entry__orbit,
.registration-entry__glow,
.registration-entry__grid,
.registration-entry__shade,
.registration-entry__radial {
  position: absolute;
  pointer-events: none;
}

.registration-entry__orbit {
  border: 1px solid rgba(137, 220, 245, 0.16);
  border-radius: 999px;
}

.registration-entry__orbit--large {
  top: -120px;
  left: -130px;
  width: 420px;
  height: 420px;
}

.registration-entry__orbit--medium {
  top: 15%;
  right: -120px;
  width: 300px;
  height: 300px;
}

.registration-entry__orbit--small {
  bottom: -110px;
  left: 18%;
  width: 240px;
  height: 240px;
}

.registration-entry__glow {
  inset: auto auto -18% -8%;
  width: 420px;
  height: 420px;
  background: radial-gradient(circle, rgba(42, 148, 178, 0.18) 0%, rgba(8, 56, 74, 0) 70%);
}

.registration-entry__grid {
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: radial-gradient(circle at center, black 35%, transparent 85%);
  opacity: 0.22;
}

.registration-entry__shade {
  inset: 0 0 auto 0;
  height: 260px;
  background: linear-gradient(180deg, rgba(3, 27, 36, 0.72) 0%, rgba(8, 56, 74, 0) 100%);
}

.registration-entry__radial {
  width: 280px;
  height: 280px;
  border-radius: 50%;
  filter: blur(10px);
}

.registration-entry__radial--one {
  top: 12%;
  left: 7%;
  background: rgba(56, 189, 248, 0.16);
}

.registration-entry__radial--two {
  right: 10%;
  bottom: 10%;
  background: rgba(110, 231, 183, 0.12);
}

@media (max-width: 760px) {
  .registration-entry__title {
    font-size: 1.55rem;
  }

  .registration-entry__form-grid {
    grid-template-columns: 1fr;
  }

  .registration-entry__actions {
    justify-content: stretch;
  }

  .registration-entry__actions :deep(.app-button) {
    width: 100%;
  }
}
</style>
