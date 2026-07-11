<template>
  <div
    class="communications-page"
    :class="{ 'communications-page--embedded': embedded }"
  >
    <template v-if="isNotificationsOnlyMode">
      <div class="prep-layout communications-page__prep-shell">
        <section class="prep-card communications-page__prep-card">
          <div class="prep-filters communications-page__prep-filters">
            <div class="prep-field communications-page__prep-field communications-page__prep-field--message">
              <label class="prep-field__label">نص الإشعار</label>
              <AppTextField
                v-model.trim="notificationForm.message"
                dense
                outlined
                hide-details
                placeholder="اكتب الإشعار الذي تريد إرساله للمعلمين"
                class="prep-select communications-page__prep-input"
              />
            </div>

            <div class="prep-field communications-page__prep-field">
              <label class="prep-field__label">الفرع</label>
              <AppSelect
                v-model="notificationForm.targetBranchId"
                :items="branchOptions"
                item-text="label"
                item-value="value"
                dense
                outlined
                hide-details
                class="prep-select communications-page__prep-input"
              />
            </div>

            <AppRawButton
              type="button"
              class="attendance-toggle communications-page__bulk-toggle"
              :class="{
                'attendance-toggle--active': allNotificationTargetStudentsSelected,
                'attendance-toggle--partial': selectedNotificationTargetStudentsCount > 0 && !allNotificationTargetStudentsSelected,
              }"
              :disabled="!notificationTargetStudents.length || notificationSubmitting"
              :aria-label="allNotificationTargetStudentsSelected ? 'إلغاء تحديد الكل' : 'تحديد الكل'"
              :title="allNotificationTargetStudentsSelected ? 'إلغاء تحديد الكل' : 'تحديد الكل'"
              @click="toggleAllNotificationTargets"
            >
              <span class="attendance-toggle__dot" />
            </AppRawButton>
          </div>

          <div class="prep-table-wrap communications-page__prep-table-wrap">
            <table class="prep-table communications-page__prep-table">
              <thead>
                <tr>
                  <th>الاسم</th>
                  <th>رقم الدخول</th>
                  <th>التحديد</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!notificationTargetStudents.length">
                  <td
                    colspan="3"
                    class="prep-table__empty"
                  >
                    لا يوجد معلمون مطابقون للفرع المحدد.
                  </td>
                </tr>
                <tr
                  v-for="student in notificationTargetStudents"
                  :key="student.value"
                >
                  <td class="prep-table__name">
                    {{ student.label }}
                  </td>
                  <td class="prep-table__login">
                    {{ student.value || '---' }}
                  </td>
                  <td class="prep-table__status">
                    <AppRawButton
                      type="button"
                      class="attendance-toggle"
                      :class="{ 'attendance-toggle--active': notificationForm.targetLoginIds.includes(student.value) }"
                      :disabled="notificationSubmitting"
                      @click.prevent="toggleNotificationTarget(student.value)"
                    >
                      <span class="attendance-toggle__dot" />
                    </AppRawButton>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="communications-page__prep-actions">
            <AppButton
              variant="primary"
              :loading="notificationSubmitting"
              @click="submitNotification"
            >
              {{ notificationSubmitting ? 'جارٍ الإرسال...' : 'إرسال' }}
            </AppButton>
          </div>
        </section>
      </div>
    </template>

    <v-container class="communications-page__container py-8 py-md-10">
      <section
        v-if="!isNotificationsOnlyMode"
        class="communications-page__hero"
      >
        <div>
          <div class="communications-page__eyebrow">
            {{ pageEyebrow }}
          </div>
          <h1 class="communications-page__title">
            {{ pageTitle }}
          </h1>
          <p class="communications-page__subtitle">
            {{ pageDescription }}
          </p>
        </div>
      </section>

      <section
        v-if="!isNotificationsOnlyMode"
        class="communications-page__layout"
      >
        <article
          v-if="showComposer"
          class="communications-page__panel communications-page__panel--composer"
        >
          <template v-if="showNotificationsForm">
            <div class="communications-page__panel-head">
              <div>
                <div class="communications-page__panel-caption">
                  إرسال إشعار
                </div>
                <h2 class="communications-page__panel-title">
                  صياغة الإشعار
                </h2>
              </div>
            </div>

            <div class="communications-page__stack">
              <div class="communications-page__field">
                <div class="communications-page__field-label">
                  العنوان
                </div>
                <AppTextField
                  v-model.trim="notificationForm.title"
                  dense
                  outlined
                  hide-details
                />
              </div>

              <div class="communications-page__field">
                <div class="communications-page__field-label">
                  الفرع المستهدف
                </div>
                <AppSelect
                  v-model="notificationForm.targetBranchId"
                  :items="branchOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                />
              </div>

              <div class="communications-page__field">
                <div class="communications-page__field-label">
                  نص الإشعار
                </div>
                <v-textarea
                  v-model.trim="notificationForm.message"
                  outlined
                  hide-details
                  rows="3"
                  auto-grow
                />
              </div>

              <div
                v-if="notificationTargetStudents.length"
                class="communications-page__student-box"
              >
                <div class="communications-page__student-head">
                  <div class="communications-page__field-label">
                    المعلمون المستهدفون
                  </div>
                  <AppRawButton
                    type="button"
                    class="communications-page__text-button"
                    @click="toggleAllNotificationTargets"
                  >
                    {{ allNotificationTargetStudentsSelected ? 'إلغاء تحديد الكل' : 'تحديد الكل' }}
                  </AppRawButton>
                </div>

                <div class="communications-page__student-list">
                  <label
                    v-for="student in notificationTargetStudents"
                    :key="student.value"
                    class="communications-page__student-item"
                  >
                    <span class="communications-page__student-copy">{{ student.label }}</span>
                    <AppRawButton
                      type="button"
                      class="communications-page__selector"
                      :class="{ 'communications-page__selector--active': notificationForm.targetLoginIds.includes(student.value) }"
                      @click.prevent="toggleNotificationTarget(student.value)"
                    />
                  </label>
                </div>
              </div>

              <div class="communications-page__actions">
                <AppButton
                  variant="primary"
                  :loading="notificationSubmitting"
                  @click="submitNotification"
                >
                  {{ notificationSubmitting ? 'جارٍ الإرسال...' : 'إرسال' }}
                </AppButton>
              </div>
            </div>
          </template>

          <template v-if="showActivityForm">
            <div class="communications-page__panel-head communications-page__panel-head--spaced">
              <div>
                <div class="communications-page__panel-caption">
                  إضافة سجل نشاط
                </div>
                <h2 class="communications-page__panel-title">
                  حدث إداري جديد
                </h2>
              </div>
            </div>

            <div class="communications-page__stack">
              <div class="communications-page__field">
                <div class="communications-page__field-label">
                  العملية
                </div>
                <AppTextField
                  v-model.trim="activityForm.action"
                  dense
                  outlined
                  hide-details
                />
              </div>

              <div class="communications-page__field">
                <div class="communications-page__field-label">
                  الهدف
                </div>
                <AppTextField
                  v-model.trim="activityForm.target"
                  dense
                  outlined
                  hide-details
                />
              </div>

              <div class="communications-page__field communications-page__field-grid">
                <div>
                  <div class="communications-page__field-label">
                    الحالة
                  </div>
                  <AppSelect
                    v-model="activityForm.status"
                    :items="statusOptions"
                    dense
                    outlined
                    hide-details
                  />
                </div>
                <div>
                  <div class="communications-page__field-label">
                    اسم المنفذ
                  </div>
                  <AppTextField
                    v-model.trim="activityForm.actorName"
                    dense
                    outlined
                    hide-details
                  />
                </div>
              </div>

              <div class="communications-page__field">
                <div class="communications-page__field-label">
                  الدور
                </div>
                <AppTextField
                  v-model.trim="activityForm.actorRole"
                  dense
                  outlined
                  hide-details
                />
              </div>

              <div class="communications-page__field">
                <div class="communications-page__field-label">
                  التفاصيل
                </div>
                <v-textarea
                  v-model.trim="activityForm.details"
                  outlined
                  hide-details
                  rows="3"
                  auto-grow
                />
              </div>

              <div class="communications-page__actions">
                <AppButton
                  variant="primary"
                  :loading="activitySubmitting"
                  @click="submitActivityLog"
                >
                  {{ activitySubmitting ? 'جارٍ الحفظ...' : 'حفظ السجل' }}
                </AppButton>
              </div>
            </div>
          </template>
        </article>

        <article class="communications-page__panel communications-page__panel--feed">
          <section
            v-if="showNotificationsList"
            class="communications-page__section"
          >
            <div class="communications-page__section-head">
              <div>
                <div class="communications-page__panel-title">
                  الإشعارات الحالية
                </div>
                <div class="communications-page__section-meta">
                  {{ notifications.length }} إشعار
                </div>
              </div>
              <AppRawButton
                type="button"
                class="communications-page__text-button"
                @click="reloadNotifications"
              >
                تحديث
              </AppRawButton>
            </div>

            <div
              v-if="notifications.length === 0"
              class="communications-page__empty"
            >
              لا توجد إشعارات محفوظة حاليًا.
            </div>
            <div
              v-else
              class="communications-page__list"
            >
              <article
                v-for="notification in notifications"
                :key="notification.id"
                class="communications-page__item-card"
              >
                <div class="communications-page__item-head">
                  <AppRawButton
                    type="button"
                    class="communications-page__danger-button"
                    @click="removeNotification(notification.id)"
                  >
                    حذف
                  </AppRawButton>
                  <div>
                    <div class="communications-page__item-title">
                      {{ notification.title }}
                    </div>
                    <div class="communications-page__item-subtitle">
                      {{ notification.createdAt || 'بدون تاريخ' }}
                    </div>
                  </div>
                </div>
                <div class="communications-page__item-body">
                  {{ notification.message }}
                </div>
                <div class="communications-page__item-foot">
                  {{ notification.targetBranchId || 'كل الفروع' }}
                </div>
              </article>
            </div>
          </section>

          <section
            v-if="showActivityList"
            class="communications-page__section"
            :class="{ 'communications-page__section--spaced': showNotificationsList }"
          >
            <div class="communications-page__section-head">
              <div>
                <div class="communications-page__panel-title">
                  سجل النشاط
                </div>
                <div class="communications-page__section-meta">
                  {{ activityLogs.length }} عملية
                </div>
              </div>
              <AppRawButton
                type="button"
                class="communications-page__text-button"
                @click="reloadActivityLogs"
              >
                تحديث
              </AppRawButton>
            </div>

            <div
              v-if="activityLogs.length === 0"
              class="communications-page__empty"
            >
              لا توجد سجلات نشاط محفوظة حاليًا.
            </div>
            <div
              v-else
              class="communications-page__list"
            >
              <article
                v-for="log in activityLogs"
                :key="log.id"
                class="communications-page__item-card"
              >
                <div class="communications-page__item-head">
                  <span
                    class="communications-page__status-badge"
                    :class="statusBadgeClass(log.status)"
                  >
                    {{ log.status }}
                  </span>
                  <div>
                    <div class="communications-page__item-title">
                      {{ log.action }}
                    </div>
                    <div class="communications-page__item-subtitle">
                      {{ log.createdAt || 'بدون تاريخ' }}
                    </div>
                  </div>
                </div>
                <div class="communications-page__item-body">
                  {{ log.target }}
                </div>
                <div class="communications-page__item-foot">
                  {{ log.actorName || 'بدون اسم' }} - {{ log.actorRole || 'بدون دور' }}
                </div>
                <div
                  v-if="log.details"
                  class="communications-page__item-note"
                >
                  {{ log.details }}
                </div>
              </article>
            </div>
          </section>
        </article>
      </section>
    </v-container>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import {
  AppButton, AppRawButton, AppSelect, AppTextField,
} from '../components/ui';

const emptyNotification = (currentUser) => ({
  title: '',
  message: '',
  targetBranchId: null,
  targetLoginIds: [],
  createdByName: currentUser?.name || 'مشرف النظام',
  createdByRole: currentUser?.role || 'admin',
});

const emptyActivity = (currentUser) => ({
  action: '',
  target: '',
  status: 'نجحت',
  details: '',
  actorName: currentUser?.name || 'مشرف النظام',
  actorRole: currentUser?.role || 'admin',
});

export default {
  name: 'AdminCommunicationsView',
  components: {
    AppButton,
    AppRawButton,
    AppSelect,
    AppTextField,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
    panelMode: {
      type: String,
      default: 'communications',
    },
  },
  data() {
    return {
      notificationForm: emptyNotification(),
      activityForm: emptyActivity(),
      notificationSubmitting: false,
      activitySubmitting: false,
      branchOptions: [
        { label: 'كل الفروع', value: null },
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
      ],
      statusOptions: ['نجحت', 'فشلت', 'ألغيت'],
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'currentUser']),
    pageEyebrow() {
      if (this.panelMode === 'notifications') {
        return 'الإشعارات';
      }

      if (this.panelMode === 'activitylog') {
        return 'سجل النشاط';
      }

      return 'الاتصالات الإدارية';
    },
    pageTitle() {
      if (this.panelMode === 'notifications') {
        return 'الإشعارات';
      }

      if (this.panelMode === 'activitylog') {
        return 'سجل النشاط';
      }

      return 'الإشعارات وسجل النشاط';
    },
    pageDescription() {
      if (this.panelMode === 'notifications') {
        return 'إرسال التنبيهات ومراجعة الإشعارات بنفس تدفق العمل الإداري المرجعي.';
      }

      if (this.panelMode === 'activitylog') {
        return 'مراجعة السجلات وإضافة الأحداث الإدارية من نفس الصفحة.';
      }

      return 'مركز الاتصالات الإدارية بين الإشعارات وسجل النشاط.';
    },
    isNotificationsOnlyMode() {
      return this.panelMode === 'notifications';
    },
    showComposer() {
      return this.showNotificationsForm || this.showActivityForm;
    },
    showNotificationsForm() {
      return this.panelMode !== 'activitylog';
    },
    showNotificationsList() {
      return this.panelMode !== 'activitylog';
    },
    showActivityForm() {
      return this.panelMode !== 'notifications';
    },
    showActivityList() {
      return this.panelMode !== 'notifications';
    },
    notifications() {
      return [...(this.dashboardSnapshot?.notifications || [])]
        .sort((left, right) => new Date(right.createdAt || 0).getTime() - new Date(left.createdAt || 0).getTime());
    },
    activityLogs() {
      return [...(this.dashboardSnapshot?.activityLogs || [])]
        .sort((left, right) => new Date(right.createdAt || 0).getTime() - new Date(left.createdAt || 0).getTime());
    },
    notificationTargetStudents() {
      const targetBranch = this.notificationForm.targetBranchId;
      const students = this.dashboardSnapshot?.students || [];

      return students
        .filter((student) => !targetBranch || student.branchId === targetBranch)
        .sort((left, right) => (left.name || '').localeCompare(right.name || '', 'ar'))
        .map((student) => ({
          label: student.name,
          value: student.loginId,
        }));
    },
    allNotificationTargetStudentsSelected() {
      return this.notificationTargetStudents.length > 0
        && this.notificationTargetStudents.every((student) => this.notificationForm.targetLoginIds.includes(student.value));
    },
    selectedNotificationTargetStudentsCount() {
      return this.notificationTargetStudents.filter((student) => this.notificationForm.targetLoginIds.includes(student.value)).length;
    },
  },
  created() {
    this.notificationForm = emptyNotification(this.currentUser);
    this.activityForm = emptyActivity(this.currentUser);

    if (!this.dashboardSnapshot) {
      this.loadDashboardSnapshot();
    }
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot', 'addNotification', 'deleteNotification', 'addActivityLog', 'reloadActivityLogs', 'reloadNotifications']),
    toggleNotificationTarget(loginId) {
      if (this.notificationForm.targetLoginIds.includes(loginId)) {
        this.notificationForm.targetLoginIds = this.notificationForm.targetLoginIds.filter((currentId) => currentId !== loginId);
        return;
      }

      this.notificationForm.targetLoginIds = [...this.notificationForm.targetLoginIds, loginId];
    },
    toggleAllNotificationTargets() {
      this.notificationForm.targetLoginIds = this.allNotificationTargetStudentsSelected
        ? []
        : this.notificationTargetStudents.map((student) => student.value);
    },
    statusBadgeClass(status) {
      return status === 'نجحت'
        ? 'communications-page__status-badge--success'
        : 'communications-page__status-badge--warning';
    },
    async submitNotification() {
      if (!this.notificationForm.message) {
        this.$toast.error('أدخل نص الإشعار أولًا');
        return;
      }

      if (!this.notificationForm.title) {
        this.notificationForm.title = this.notificationForm.message.slice(0, 80) || 'إشعار إداري';
      }

      this.notificationSubmitting = true;

      try {
        await this.addNotification({ ...this.notificationForm });
        this.notificationForm = emptyNotification(this.currentUser);
        this.$toast.success('تم إرسال الإشعار');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر إرسال الإشعار');
      } finally {
        this.notificationSubmitting = false;
      }
    },
    async removeNotification(notificationId) {
      try {
        await this.deleteNotification(notificationId);
        this.$toast.success('تم حذف الإشعار');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حذف الإشعار');
      }
    },
    async submitActivityLog() {
      if (!this.activityForm.action || !this.activityForm.target) {
        this.$toast.error('أدخل العملية والهدف أولًا');
        return;
      }

      this.activitySubmitting = true;

      try {
        await this.addActivityLog({ ...this.activityForm });
        this.activityForm = emptyActivity(this.currentUser);
        this.$toast.success('تم حفظ السجل');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ السجل');
      } finally {
        this.activitySubmitting = false;
      }
    },
  },
};
</script>

<style scoped>
.communications-page {
  min-height: 100%;
  background: linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
}

.communications-page--embedded {
  background: transparent;
}

.communications-page__container {
  max-width: 1240px;
}

.communications-page__prep-shell {
  width: 100%;
}

.communications-page__prep-card {
  background: #fff;
  border: 1px solid #e6edf3;
  border-radius: 30px;
  padding: 28px 28px 18px;
  box-shadow: 0 12px 34px rgba(15, 23, 42, 0.04);
}

.communications-page__prep-filters {
  position: relative;
  display: grid;
  grid-template-columns: minmax(340px, 1.45fr) minmax(260px, 1fr);
  align-items: end;
  gap: 16px;
  padding-inline-end: 58px;
  padding-bottom: 14px;
  border-bottom: 1px solid #e8eef4;
}

.communications-page__prep-field {
  min-width: 0;
}

.communications-page__prep-field--message {
  min-width: 0;
}

.communications-page__prep-input {
  width: 100%;
}

.communications-page__prep-input :deep(.v-input__slot) {
  min-height: 50px !important;
}

.prep-layout {
  width: 100%;
}

.prep-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.prep-card__title {
  margin: 0;
  color: #0f3554;
  font-size: 2rem;
  font-weight: 800;
  line-height: 1.2;
}

.prep-field__label {
  display: block;
  margin: 0 0 10px;
  color: #0f3554;
  font-size: 1rem;
  font-weight: 700;
}

.communications-page__prep-table-wrap {
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

.communications-page__bulk-toggle {
  position: absolute;
  top: 40px;
  inset-inline-end: 0;
}

.communications-page__prep-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 18px;
}

.communications-page__hero {
  margin-bottom: 24px;
}

.communications-page__eyebrow {
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 800;
}

.communications-page__title {
  margin: 8px 0 0;
  color: #0f172a;
  font-size: 1.9rem;
  font-weight: 900;
}

.communications-page__subtitle {
  margin: 10px 0 0;
  color: #64748b;
  font-size: 0.96rem;
  font-weight: 600;
}

.communications-page__layout {
  display: grid;
  gap: 20px;
  grid-template-columns: minmax(320px, 0.95fr) minmax(0, 1.35fr);
}

.communications-page__panel {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  padding: 24px;
}

.communications-page__panel-head {
  margin-bottom: 18px;
}

.communications-page__panel-head--spaced {
  margin-top: 24px;
}

.communications-page__panel-caption,
.communications-page__field-label,
.communications-page__section-meta {
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 700;
}

.communications-page__panel-title {
  color: #0f172a;
  font-size: 1.12rem;
  font-weight: 900;
}

.communications-page__stack {
  display: grid;
  gap: 16px;
}

.communications-page__field-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.communications-page__student-box {
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 22px;
  background: #fff;
  padding: 16px;
}

.communications-page__student-head,
.communications-page__section-head,
.communications-page__item-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.communications-page__student-list,
.communications-page__list {
  display: grid;
  gap: 12px;
}

.communications-page__student-list {
  max-height: 260px;
  margin-top: 14px;
  overflow: auto;
}

.communications-page__student-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 18px;
  background: rgba(248, 250, 252, 0.78);
  padding: 12px 14px;
}

.communications-page__student-copy {
  color: #0f172a;
  font-size: 0.92rem;
  font-weight: 700;
}

.communications-page__actions {
  display: flex;
  justify-content: flex-end;
}

.communications-page__primary-button,
.communications-page__danger-button,
.communications-page__text-button {
  border: 0;
  background: transparent;
  cursor: pointer;
}

.communications-page__primary-button {
  min-height: 48px;
  padding: 0 24px;
  border-radius: 999px;
  background: linear-gradient(180deg, #1e809b 0%, #146d88 100%);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 900;
  box-shadow: 0 10px 24px rgba(18, 96, 119, 0.18);
}

.communications-page__primary-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.communications-page__text-button {
  color: #146d88;
  font-size: 0.86rem;
  font-weight: 800;
}

.communications-page__section--spaced {
  margin-top: 26px;
}

.communications-page__empty {
  margin-top: 14px;
  border: 1px dashed rgba(15, 23, 42, 0.14);
  border-radius: 22px;
  background: rgba(248, 250, 252, 0.72);
  color: #475569;
  padding: 18px 20px;
  font-weight: 700;
}

.communications-page__item-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 22px;
  background: rgba(248, 250, 252, 0.78);
  padding: 16px;
}

.communications-page__item-title {
  color: #0f172a;
  font-size: 0.98rem;
  font-weight: 800;
}

.communications-page__item-subtitle,
.communications-page__item-foot,
.communications-page__item-note {
  color: #64748b;
  font-size: 0.84rem;
  font-weight: 700;
}

.communications-page__item-body {
  margin-top: 12px;
  color: #0f172a;
  font-size: 0.92rem;
  line-height: 1.8;
}

.communications-page__item-foot,
.communications-page__item-note {
  margin-top: 10px;
}

.communications-page__danger-button {
  color: #dc2626;
  font-size: 0.82rem;
  font-weight: 900;
}

.communications-page__status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 30px;
  padding: 0 12px;
  border-radius: 999px;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 900;
}

.communications-page__status-badge--success {
  background: #16a34a;
}

.communications-page__status-badge--warning {
  background: #d97706;
}

@media (max-width: 1100px) {
  .communications-page__prep-filters,
  .communications-page__layout {
    grid-template-columns: minmax(0, 1fr);
  }

  .communications-page__prep-filters {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 14px;
    padding-inline-end: 0;
  }

  .communications-page__prep-field--message {
    grid-column: 1 / -1;
  }

  .communications-page__prep-field:not(.communications-page__prep-field--message) {
    grid-column: 1;
  }

  .communications-page__bulk-toggle {
    position: static;
    grid-column: 2;
    justify-self: end;
    align-self: end;
  }
}

@media (max-width: 960px) {
  .communications-page__prep-card {
    padding: 22px 18px 12px;
    border-radius: 24px;
  }

  .prep-card__title {
    font-size: 1.65rem;
  }

  .communications-page__prep-filters > :last-child {
    justify-self: start;
  }
}

@media (max-width: 680px) {
  .communications-page__field-grid,
  .communications-page__student-head,
  .communications-page__section-head,
  .communications-page__item-head {
    grid-template-columns: minmax(0, 1fr);
    display: grid;
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
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #e7eef4;
  }

  .prep-table tbody tr:last-child {
    border-bottom: none;
  }

  .prep-table tbody td {
    padding: 0;
    border-bottom: none;
  }

  .prep-table__name {
    min-width: 0;
  }

  .prep-table__login {
    display: none !important;
  }

  .prep-table__status {
    width: auto;
    text-align: left;
  }

  .prep-table__name::before {
    content: none;
  }

  .prep-table__login::before {
    content: none;
  }

  .prep-table__status::before {
    content: none;
  }

  .communications-page__prep-actions {
    justify-content: flex-start;
  }
}
</style>
