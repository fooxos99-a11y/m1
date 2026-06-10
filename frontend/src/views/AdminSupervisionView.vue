<template>
  <div
    class="admin-supervision"
    :class="{ 'admin-supervision--embedded': embedded }"
  >
    <section class="admin-supervision__card">
      <div class="admin-supervision__block">
        <div class="admin-supervision__section-title">
          إضافة حساب إشرافي
        </div>

        <div class="admin-supervision__form-grid">
          <div class="admin-supervision__field">
            <label class="admin-supervision__label">المسمى</label>
            <AppSelect
              v-model="form.role"
              :items="roleOptions"
              item-text="label"
              item-value="value"
              dense
              outlined
              hide-details
            />
          </div>

          <div class="admin-supervision__field">
            <label class="admin-supervision__label">الاسم</label>
            <AppTextField
              v-model.trim="form.name"
              placeholder="الاسم"
              dense
              outlined
              hide-details
            />
          </div>

          <div class="admin-supervision__field">
            <label class="admin-supervision__label">رقم الدخول</label>
            <AppTextField
              v-model.trim="form.loginCode"
              placeholder="رقم الدخول"
              dense
              outlined
              hide-details
            />
          </div>
        </div>

        <div
          v-if="errorMessage"
          class="admin-supervision__error"
        >
          {{ errorMessage }}
        </div>

        <div class="admin-supervision__actions">
          <AppButton
            variant="primary"
            :loading="submitting"
            @click="submitAccount"
          >
            إضافة
          </AppButton>
        </div>
      </div>

      <div class="admin-supervision__divider" />

      <div class="admin-supervision__block">
        <div class="admin-supervision__section-title">
          الحسابات الحالية
        </div>

        <div
          v-if="loading"
          class="admin-supervision__empty"
        >
          جارٍ تحميل الحسابات الإشرافية...
        </div>
        <div
          v-else-if="accounts.length === 0"
          class="admin-supervision__empty"
        >
          لا توجد حسابات إشرافية حالياً.
        </div>
        <div
          v-else
          class="admin-supervision__stack"
        >
          <article
            v-for="account in accounts"
            :key="account.id"
            class="admin-supervision__account"
          >
            <div class="admin-supervision__account-copy">
              <div class="admin-supervision__account-name">
                {{ account.name }}
              </div>
              <div class="admin-supervision__account-meta">
                {{ roleLabel(account.role) }} - {{ account.loginCode }}
              </div>
            </div>

            <AppIconButton
              variant="danger"
              class="admin-supervision__delete"
              :disabled="deletingId === account.id || currentUser?.id === account.id"
              @click="removeAccount(account.id)"
            >
              <i
                class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                aria-hidden="true"
              />
            </AppIconButton>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import {
  AppButton, AppIconButton, AppSelect, AppTextField,
} from '../components/ui';

export default {
  name: 'AdminSupervisionView',
  components: {
    AppButton,
    AppIconButton,
    AppSelect,
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
      loading: false,
      submitting: false,
      deletingId: '',
      errorMessage: '',
      accounts: [],
      form: {
        role: 'male_manager',
        name: '',
        loginCode: '',
      },
    };
  },
  computed: {
    ...mapState(['currentUser']),
    roleOptions() {
      return [
        { label: 'مشرف معلمين', value: 'male_manager' },
        { label: 'مشرف معلمات', value: 'female_manager' },
        { label: 'مدير عام', value: 'admin' },
      ];
    },
  },
  created() {
    this.loadAccounts();
  },
  methods: {
    ...mapActions(['fetchDashboardAccounts', 'createDashboardAccount', 'deleteDashboardAccount']),
    roleLabel(role) {
      return {
        admin: 'مدير عام',
        male_manager: 'مشرف معلمين',
        female_manager: 'مشرف معلمات',
      }[role] || role;
    },
    async loadAccounts() {
      this.errorMessage = '';
      this.loading = true;

      try {
        this.accounts = await this.fetchDashboardAccounts();
      } catch (error) {
        this.errorMessage = error?.response?.data?.message || 'تعذر تحميل الحسابات الإشرافية';
      } finally {
        this.loading = false;
      }
    },
    async submitAccount() {
      this.errorMessage = '';

      if (!this.form.name || !this.form.loginCode) {
        this.errorMessage = 'أدخل الاسم ورقم الدخول.';
        return;
      }

      this.submitting = true;

      try {
        const created = await this.createDashboardAccount({ ...this.form });
        this.accounts = [...this.accounts, created];
        this.form = {
          role: 'male_manager',
          name: '',
          loginCode: '',
        };
        this.$toast.success('تمت إضافة الحساب الإشرافي');
      } catch (error) {
        this.errorMessage = error?.response?.data?.message || 'تعذر إضافة الحساب الإشرافي';
      } finally {
        this.submitting = false;
      }
    },
    async removeAccount(accountId) {
      this.deletingId = accountId;

      try {
        await this.deleteDashboardAccount(accountId);
        this.accounts = this.accounts.filter((account) => account.id !== accountId);
        this.$toast.success('تم حذف الحساب');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حذف الحساب');
      } finally {
        this.deletingId = '';
      }
    },
  },
};
</script>

<style scoped>
.admin-supervision {
  display: block;
}

.admin-supervision--embedded .admin-supervision__card {
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  padding: 0;
}

.admin-supervision__card {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 250, 250, 0.96) 100%);
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
  padding: 22px;
}

.admin-supervision__block + .admin-supervision__block {
  margin-top: 22px;
}

.admin-supervision__divider {
  height: 1px;
  margin: 22px 0;
  background: rgba(201, 224, 233, 0.9);
}

.admin-supervision__section-title {
  color: #0f3554;
  font-size: 1.05rem;
  font-weight: 900;
  margin-bottom: 16px;
}

.admin-supervision__stack {
  display: grid;
  gap: 12px;
}

.admin-supervision__account {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 16px 18px;
  border: 1px solid #dfebf1;
  border-radius: 22px;
  background: #fbfdfe;
}

.admin-supervision__account-name {
  color: #0f3554;
  font-size: 1rem;
  font-weight: 900;
}

.admin-supervision__account-meta {
  margin-top: 6px;
  color: #61788b;
  font-size: 0.92rem;
  font-weight: 700;
}

.admin-supervision__form-grid {
  display: grid;
  gap: 14px;
}

.admin-supervision__field {
  display: grid;
  gap: 8px;
}

.admin-supervision__label {
  color: #0f3554;
  font-size: 0.95rem;
  font-weight: 800;
}

.admin-supervision__empty {
  color: #6b7f90;
  font-size: 0.98rem;
  font-weight: 700;
}

.admin-supervision__error {
  margin-top: 14px;
  color: #b42318;
  font-size: 0.95rem;
  font-weight: 700;
}

.admin-supervision__actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 18px;
}
</style>
