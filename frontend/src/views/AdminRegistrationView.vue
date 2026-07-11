<template>
  <div
    class="registration-admin"
    :class="{ 'registration-admin--embedded': embedded }"
  >
    <RegistrationFieldsDialog
      v-model="fieldsDialogOpen"
      :fields="registrationFields"
      :loading="fieldsSubmitting || loading"
      @save="saveRegistrationFields"
    />

    <section
      v-if="!embedded"
      class="registration-admin__hero"
    >
      <div>
        <div class="registration-admin__eyebrow">
          الإعدادات
        </div>
        <h1 class="registration-admin__title">
          التسجيل
        </h1>
        <p class="registration-admin__description">
          افتح أو أغلق رابط التسجيل، وراجع الطلبات وقم بقبولها أو رفضها مباشرة.
        </p>
      </div>

      <div class="registration-admin__hero-actions">
        <AppButton
          variant="secondary"
          @click="openFieldsDialog"
        >
          تعديل بيانات التسجيل
        </AppButton>
        <AppButton
          variant="secondary"
          @click="copyRegistrationLink"
        >
          نسخ الرابط
        </AppButton>
        <AppButton
          v-if="registrationUrl"
          variant="primary"
          :href="registrationUrl"
          target="_blank"
          rel="noopener noreferrer"
        >
          فتح الرابط
        </AppButton>
      </div>
    </section>

    <section class="registration-admin__requests-card">
      <div class="registration-admin__section-header">
        <div>
          <h2 class="registration-admin__section-title">
            الطلبات
          </h2>
          <div class="registration-admin__section-caption">
            الطلاب
          </div>
        </div>
      </div>

      <div
        v-if="loading"
        class="registration-admin__empty"
      >
        جارٍ تحميل بيانات التسجيل...
      </div>

      <div
        v-else-if="pendingRequests.length === 0"
        class="registration-admin__empty"
      >
        لا توجد طلبات طلاب معلقة حاليًا.
      </div>

      <div
        v-else
        class="registration-admin__requests-panel"
      >
        <div class="registration-admin__requests-panel-head">
          <span>بيانات الطلب</span>
          <span>الإجراء</span>
        </div>

        <article
          v-for="request in pendingRequests"
          :key="request.id"
          class="registration-admin__request-row"
        >
          <div class="registration-admin__request-main">
            <div class="registration-admin__request-head">
              <div>
                <h3 class="registration-admin__request-name">
                  {{ request.name }}
                </h3>
                <div class="registration-admin__request-meta">
                  رقم الهوية: {{ request.loginCode }}
                </div>
                <div class="registration-admin__request-meta">
                  الجنس: {{ genderLabel(request.gender) }}
                </div>
                <div
                  v-if="request.branchId"
                  class="registration-admin__request-meta"
                >
                  الفرع: {{ branchLabel(request.branchId) }}
                </div>
                <div
                  v-for="answer in requestAnswerList(request)"
                  :key="answer.label"
                  class="registration-admin__request-meta"
                >
                  {{ answer.label }}: {{ answer.value || '--' }}
                </div>
              </div>

              <span
                class="registration-admin__request-status"
                :class="`registration-admin__request-status--${request.status}`"
              >
                {{ statusLabel(request.status) }}
              </span>
              <AppRawButton
                v-if="request.status === 'pending' && selectedRequestId === request.id"
                type="button"
                class="registration-admin__request-close"
                :disabled="busyRequestId === request.id"
                title="إخفاء الطلب من نافذة التسجيل"
                aria-label="إخفاء الطلب من نافذة التسجيل"
                @click.stop="markRequestAccepted(request.id)"
              >
                <v-icon small>
                  mdi-close
                </v-icon>
              </AppRawButton>
            </div>

            <template v-if="request.status !== 'accepted'">
              <p
                v-if="request.note"
                class="registration-admin__request-note"
              >
                {{ request.note }}
              </p>

              <div class="registration-admin__request-dates">
                <span>أُرسل: {{ formatDate(request.createdAt) }}</span>
                <span v-if="request.reviewedAt">تمت المعالجة: {{ formatDate(request.reviewedAt) }}</span>
              </div>

              <div
                v-if="request.decisionReason"
                class="registration-admin__request-reason"
              >
                {{ request.decisionReason }}
              </div>
            </template>
          </div>

          <div
            v-if="request.status === 'pending'"
            class="registration-admin__request-actions"
            @click.stop
          >
            <AppButton
              variant="danger"
              :loading="busyRequestId === request.id && busyAction === 'reject'"
              :disabled="busyRequestId === request.id && busyAction !== 'reject'"
              @click="rejectRequest(request.id)"
            >
              رفض
            </AppButton>
            <AppButton
              variant="success"
              :loading="busyRequestId === request.id && busyAction === 'accept'"
              :disabled="busyRequestId === request.id && busyAction !== 'accept'"
              @click="acceptRequestDirect(request)"
            >
              قبول
            </AppButton>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script>
import { mapState } from 'vuex';
import {
  AppButton,
  AppRawButton,
} from '../components/ui';
import RegistrationFieldsDialog from '../components/RegistrationFieldsDialog.vue';
import {
  acceptRegistrationRequest,
  fetchRegistrationDashboardData,
  markRegistrationRequestAccepted,
  rejectRegistrationRequest,
  updateRegistrationFields,
  updateRegistrationSettings,
} from '../services/api';

export default {
  name: 'AdminRegistrationView',
  components: {
    AppButton,
    AppRawButton,
    RegistrationFieldsDialog,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      loading: false,
      settingsSubmitting: false,
      busyRequestId: '',
      busyAction: '',
      selectedRequestId: '',
      fieldsDialogOpen: false,
      fieldsSubmitting: false,
      isOpen: false,
      registrationUrl: '',
      registrationFields: [],
      requests: [],
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot']),
    branchesMap() {
      return Object.fromEntries((this.dashboardSnapshot?.branches || []).map((branch) => [branch.id, branch.label]));
    },
    pendingRequests() {
      return this.requests.filter((request) => request.status === 'pending');
    },
  },
  created() {
    this.loadRegistrationData();
  },
  methods: {
    emitTopbarState(extra = {}) {
      this.$emit('registration-topbar-state', {
        isOpen: this.isOpen,
        loading: this.settingsSubmitting,
        ...extra,
      });
    },
    selectRequestCard(requestId) {
      this.selectedRequestId = this.selectedRequestId === requestId ? '' : requestId;
    },
    genderLabel(gender) {
      if (gender === 'female') {
        return 'أنثى';
      }

      if (gender === 'male') {
        return 'ذكر';
      }

      return 'غير محدد';
    },
    async loadRegistrationData() {
      this.loading = true;

      try {
        const payload = await fetchRegistrationDashboardData();
        this.isOpen = Boolean(payload?.isOpen);
        this.registrationUrl = typeof window !== 'undefined'
          ? `${window.location.origin}/registration`
          : (payload?.registrationUrl || '');
        this.registrationFields = Array.isArray(payload?.fields) ? payload.fields : [];
        this.requests = Array.isArray(payload?.requests) ? payload.requests : [];
        this.emitTopbarState();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحميل بيانات التسجيل');
      } finally {
        this.loading = false;
      }
    },
    async toggleRegistration() {
      this.settingsSubmitting = true;
      this.emitTopbarState({ loading: true });

      try {
        await updateRegistrationSettings(!this.isOpen);
        this.isOpen = !this.isOpen;
        this.emitTopbarState();
        this.$toast.success(this.isOpen ? 'تم فتح التسجيل' : 'تم إغلاق التسجيل');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحديث حالة التسجيل');
      } finally {
        this.settingsSubmitting = false;
        this.emitTopbarState();
      }
    },
    openFieldsDialog() {
      this.fieldsDialogOpen = true;
    },
    async saveRegistrationFields(fields) {
      if (fields.some((field) => field.type === 'select' && field.options.length === 0)) {
        this.$toast.error('أضف خيارًا واحدًا على الأقل لكل قائمة منسدلة');
        return;
      }

      this.fieldsSubmitting = true;

      try {
        const payload = await updateRegistrationFields(fields);
        this.registrationFields = Array.isArray(payload?.fields) ? payload.fields : fields;
        this.fieldsDialogOpen = false;
        this.$toast.success('تم حفظ بيانات التسجيل');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ بيانات التسجيل');
      } finally {
        this.fieldsSubmitting = false;
      }
    },
    requestAnswerList(request) {
      return Object.values(request?.answers || {}).filter((answer) => answer?.label);
    },
    async acceptRequestDirect(request) {
      const requestId = request?.id;

      if (!requestId) {
        return;
      }

      this.busyRequestId = requestId;
      this.busyAction = 'accept';

      try {
        const branchId = request.gender === 'female' ? 'female' : 'male';
        const updatedRequest = await acceptRegistrationRequest(requestId, branchId);
        this.requests = this.requests.map((item) => (item.id === requestId ? updatedRequest : item));
        this.$toast.success('تم قبول الطلب وإنشاء الحساب');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر قبول الطلب');
      } finally {
        this.busyRequestId = '';
        this.busyAction = '';
      }
    },
    async rejectRequest(requestId) {
      this.busyRequestId = requestId;
      this.busyAction = 'reject';

      try {
        const updatedRequest = await rejectRegistrationRequest(requestId);
        this.requests = this.requests.map((request) => (request.id === requestId ? updatedRequest : request));
        this.$toast.success('تم رفض الطلب');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر رفض الطلب');
      } finally {
        this.busyRequestId = '';
        this.busyAction = '';
      }
    },
    async markRequestAccepted(requestId) {
      this.busyRequestId = requestId;
      this.busyAction = 'mark-accepted';

      try {
        const updatedRequest = await markRegistrationRequestAccepted(requestId);
        this.requests = this.requests.map((request) => (request.id === requestId ? updatedRequest : request));
        if (this.selectedRequestId === requestId) {
          this.selectedRequestId = '';
        }
        this.$toast.success('تم اعتماد الطلب وإخفاؤه من المعلّقة');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر اعتماد الطلب');
      } finally {
        this.busyRequestId = '';
        this.busyAction = '';
      }
    },
    async copyRegistrationLink() {
      if (!this.registrationUrl) {
        this.$toast.error('رابط التسجيل غير متاح');
        return;
      }

      try {
        if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(this.registrationUrl);
          this.$toast.success('تم نسخ رابط التسجيل');
          return;
        }

        if (typeof document !== 'undefined') {
          const input = document.createElement('textarea');
          input.value = this.registrationUrl;
          input.setAttribute('readonly', 'readonly');
          input.style.position = 'fixed';
          input.style.opacity = '0';
          document.body.appendChild(input);
          input.select();
          input.setSelectionRange(0, input.value.length);

          const copied = document.execCommand('copy');
          document.body.removeChild(input);

          if (copied) {
            this.$toast.success('تم نسخ رابط التسجيل');
            return;
          }
        }
      } catch {
        // Fall through to the shared error toast below.
      }

      this.$toast.error('تعذر نسخ الرابط');
    },
    branchLabel(branchId) {
      return this.branchesMap[branchId] || branchId || 'غير محدد';
    },
    statusLabel(status) {
      if (status === 'accepted') {
        return 'مقبول';
      }

      if (status === 'rejected') {
        return 'مرفوض';
      }

      return 'معلّق';
    },
    formatDate(value) {
      if (!value) {
        return '--';
      }

      const date = new Date(value);

      if (Number.isNaN(date.getTime())) {
        return '--';
      }

      return date.toLocaleString('ar-SA');
    },
  },
};
</script>

<style scoped>
.registration-admin {
  min-height: 100%;
}

.registration-admin__hero,
.registration-admin__requests-card,
.registration-admin__request-card,
.registration-admin__accept-dialog {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(246, 250, 250, 0.96) 100%);
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
}

.registration-admin__hero,
.registration-admin__request-card {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 18px;
}

.registration-admin__hero,
.registration-admin__requests-card {
  padding: 24px;
}

.registration-admin__hero {
  margin-bottom: 18px;
}

.registration-admin__eyebrow,
.registration-admin__request-meta,
.registration-admin__request-dates {
  color: #64748b;
}

.registration-admin__eyebrow,
.registration-admin__summary-label,
.registration-admin__request-meta,
.registration-admin__request-dates,
.registration-admin__request-reason {
  font-size: 0.9rem;
  font-weight: 700;
}

.registration-admin__title,
.registration-admin__section-title,
.registration-admin__request-name,
.registration-admin__summary-value {
  color: #0f172a;
  font-weight: 900;
}

.registration-admin__title {
  margin: 8px 0 10px;
  font-size: 1.9rem;
}

.registration-admin__description {
  max-width: 620px;
  margin: 0;
  color: #475569;
  font-size: 1rem;
  line-height: 1.8;
}

.registration-admin__hero-actions,
.registration-admin__request-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.registration-admin__accept-info {
  margin-bottom: 14px;
  padding: 12px 14px;
  border-radius: 16px;
  background: rgba(8, 118, 153, 0.08);
  color: #0f3554;
  font-size: 0.95rem;
  font-weight: 800;
}

.registration-admin__accept-copy {
  color: #475569;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.8;
}

.registration-admin__accept-footer {
  margin-top: 0;
}

.registration-admin__section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.registration-admin__section-title {
  margin: 0;
  font-size: 1.35rem;
}

.registration-admin__section-caption {
  margin-top: 4px;
  color: #64748b;
  font-size: 0.9rem;
  font-weight: 700;
}

.registration-admin__empty {
  padding: 24px;
  border-radius: 22px;
  background: rgba(148, 163, 184, 0.1);
  color: #475569;
  text-align: center;
  font-weight: 700;
}

.registration-admin__requests-panel {
  overflow: hidden;
  border: 1px solid rgba(20, 109, 136, 0.12);
  border-radius: 22px;
  background: rgba(255, 255, 255, 0.72);
}

.registration-admin__requests-panel-head,
.registration-admin__request-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
}

.registration-admin__requests-panel-head {
  padding: 14px 18px;
  background: rgba(8, 118, 153, 0.07);
  color: #0f5670;
  font-size: 0.86rem;
  font-weight: 900;
}

.registration-admin__request-row {
  padding: 18px;
  border-top: 1px solid rgba(15, 23, 42, 0.08);
  background: transparent;
  transition: background 0.18s ease;
}

.registration-admin__request-row:hover {
  background: rgba(248, 250, 252, 0.78);
}

.registration-admin__request-main {
  min-width: 0;
  flex: 1;
}

.registration-admin__request-head {
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.registration-admin__request-name {
  margin: 0 0 8px;
  font-size: 1.15rem;
}

.registration-admin__request-status {
  flex-shrink: 0;
  padding: 8px 12px;
  border-radius: 999px;
  font-size: 0.82rem;
  font-weight: 900;
}

.registration-admin__request-close {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(15, 23, 42, 0.18);
  border-radius: 10px;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  transition: all 0.18s ease;
}

.registration-admin__request-close:hover:not(:disabled) {
  color: #166534;
  border-color: rgba(22, 101, 52, 0.34);
  background: rgba(220, 252, 231, 0.85);
}

.registration-admin__request-close:disabled {
  opacity: 0.56;
  cursor: not-allowed;
}

.registration-admin__request-status--pending {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.registration-admin__request-status--accepted {
  background: rgba(22, 163, 74, 0.14);
  color: #166534;
}

.registration-admin__request-status--rejected {
  background: rgba(220, 38, 38, 0.12);
  color: #b91c1c;
}

.registration-admin__request-note,
.registration-admin__request-reason {
  margin: 12px 0 0;
  color: #334155;
  line-height: 1.8;
}

.registration-admin__request-dates {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 12px;
}

@media (max-width: 960px) {
  .registration-admin__hero,
  .registration-admin__section-header,
  .registration-admin__request-head {
    flex-direction: column;
    align-items: stretch;
  }

  .registration-admin__requests-panel-head {
    display: none;
  }

  .registration-admin__request-row {
    grid-template-columns: 1fr;
    align-items: stretch;
    gap: 12px;
  }

}
</style>
