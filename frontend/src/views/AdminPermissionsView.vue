<template>
  <div
    class="permissions-admin"
    :class="{ 'permissions-admin--embedded': embedded }"
  >
    <v-container class="permissions-admin__container py-8 py-md-10">
      <div
        v-if="dashboardError"
        class="permissions-admin__alert permissions-admin__alert--error"
      >
        {{ dashboardError }}
      </div>

      <section class="permissions-admin__panel">
        <article class="permissions-admin__card">
          <div
            v-if="currentUser?.role === 'admin'"
            class="permissions-admin__section-switcher"
          >
            <AppButton
              variant="plain"
              class="permissions-admin__section-chip"
              :class="{ 'permissions-admin__section-chip--active': activeSection === 'permissions' }"
              @click="activeSection = 'permissions'"
            >
              الصلاحيات
            </AppButton>
            <AppButton
              variant="plain"
              class="permissions-admin__section-chip"
              :class="{ 'permissions-admin__section-chip--active': activeSection === 'supervision' }"
              @click="activeSection = 'supervision'"
            >
              الإشراف
            </AppButton>
          </div>

          <template v-if="activeSection === 'permissions'">
            <div class="permissions-admin__role-switcher">
              <div class="permissions-admin__role-field">
                <div class="permissions-admin__role-label">
                  نوع المسؤول
                </div>
                <AppSelect
                  v-model="selectedRole"
                  :items="roleCards"
                  item-text="label"
                  item-value="value"
                  outlined
                  dense
                  hide-details
                  class="permissions-admin__role-select"
                />
              </div>
            </div>

            <div class="permissions-admin__groups">
              <section
                v-for="group in permissionGroups"
                :key="`${activeRoleCard.value}-${group.label}`"
                class="permissions-admin__group"
              >
                <div class="permissions-admin__group-title">
                  {{ group.label }}
                </div>

                <div
                  v-for="permission in group.permissions"
                  :key="`${activeRoleCard.value}-${permission.key}`"
                  class="permissions-admin__permission-row"
                >
                  <v-switch
                    :input-value="isPermissionEnabled(activeRoleCard.value, permission.key)"
                    inset
                    hide-details
                    color="primary"
                    class="permissions-admin__switch"
                    :disabled="savingKey === `${activeRoleCard.value}:${permission.key}`"
                    @change="updatePermission(activeRoleCard.value, permission.key, $event)"
                  />
                  <span
                    class="permissions-admin__permission-label"
                    :class="{ 'permissions-admin__permission-label--muted': !isPermissionEnabled(activeRoleCard.value, permission.key) }"
                  >
                    {{ permission.label }}
                  </span>
                </div>
              </section>
            </div>
          </template>

          <AdminSupervisionView
            v-else
            embedded
          />
        </article>
      </section>
    </v-container>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import { AppButton, AppSelect } from '../components/ui';
import AdminSupervisionView from './AdminSupervisionView.vue';

const PERMISSION_GROUPS = [
  {
    label: 'الطلاب',
    permissions: [
      { key: 'add_student', label: 'إضافة طالب' },
      { key: 'delete_student', label: 'حذف طالب' },
      { key: 'edit_student', label: 'تعديل بيانات طالب' },
    ],
  },
  {
    label: 'الاختبارات',
    permissions: [
      { key: 'edit_pre_questions', label: 'تعديل أسئلة الاختبار القبلي' },
      { key: 'edit_post_questions', label: 'تعديل أسئلة الاختبار البعدي' },
      { key: 'edit_tasks', label: 'تعديل المهام الأدائية' },
      { key: 'open_pre_exam', label: 'فتح الاختبار القبلي' },
      { key: 'open_post_exam', label: 'فتح الاختبار البعدي' },
    ],
  },
  {
    label: 'الإقراء',
    permissions: [
      { key: 'add_reciter', label: 'إضافة مقرئ' },
      { key: 'delete_reciter', label: 'حذف مقرئ' },
      { key: 'edit_reciter', label: 'تعديل بيانات مقرئ' },
      { key: 'transfer_reciter_student', label: 'نقل الطالب المرتبط بمقرئ' },
    ],
  },
  {
    label: 'الصفحات',
    permissions: [
      { key: 'page_notifications', label: 'صفحة الإشعارات' },
      { key: 'page_results', label: 'صفحة النتائج' },
    ],
  },
  {
    label: 'سجل النشاط والنسخة الاحتياطية',
    permissions: [
      { key: 'page_activity_log', label: 'صفحة سجل النشاط' },
      { key: 'backup_export', label: 'تصدير نسخة احتياطية' },
      { key: 'backup_import', label: 'رفع نسخة احتياطية' },
      { key: 'backup_restore', label: 'استرجاع نسخة احتياطية' },
    ],
  },
];

export default {
  name: 'AdminPermissionsView',
  components: {
    AppButton,
    AppSelect,
    AdminSupervisionView,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      savingKey: '',
      selectedRole: 'male_manager',
      activeSection: 'permissions',
      roleCards: [
        { value: 'male_manager', label: 'مسؤول المعلمين' },
        { value: 'female_manager', label: 'مسؤول المعلمات' },
      ],
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'dashboardError', 'currentUser']),
    permissionGroups() {
      return PERMISSION_GROUPS;
    },
    activeRoleCard() {
      return this.roleCards.find((role) => role.value === this.selectedRole) || this.roleCards[0];
    },
  },
  watch: {
    activeSection() {
      this.emitTopbarState();
    },
  },
  created() {
    this.emitTopbarState();
  },
  methods: {
    ...mapActions({ updateRolePermission: 'setRolePermission' }),
    emitTopbarState() {
      this.$emit('permissions-topbar-state', {
        isAdmin: false,
        activeSection: this.activeSection,
      });
    },
    toggleTopbarSection() {
      if (this.currentUser?.role !== 'admin') {
        return;
      }

      this.activeSection = this.activeSection === 'supervision' ? 'permissions' : 'supervision';
    },
    isPermissionEnabled(role, key) {
      return this.dashboardSnapshot?.rolePermissions?.[role]?.[key] === true;
    },
    async updatePermission(role, key, isEnabled) {
      const nextValue = Boolean(isEnabled);
      const entryKey = `${role}:${key}`;

      this.savingKey = entryKey;

      try {
        await this.updateRolePermission({ role, key, isEnabled: nextValue });
        this.$toast.success('تم تحديث الصلاحية');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحديث الصلاحية');
      } finally {
        this.savingKey = '';
      }
    },
  },
};
</script>

<style scoped>
.permissions-admin {
  min-height: 100%;
  background: linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
}

.permissions-admin--embedded {
  background: transparent;
}

.permissions-admin__container {
  max-width: 1200px;
}

.permissions-admin__hero {
  margin-bottom: 24px;
}

.permissions-admin__eyebrow {
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 800;
}

.permissions-admin__title {
  margin: 8px 0 0;
  color: #0f172a;
  font-size: 1.9rem;
  font-weight: 900;
}

.permissions-admin__subtitle {
  margin: 10px 0 0;
  color: #64748b;
  font-size: 0.96rem;
  font-weight: 600;
}

.permissions-admin__alert {
  margin-bottom: 18px;
  border-radius: 22px;
  padding: 18px 20px;
  font-weight: 700;
}

.permissions-admin__alert--error {
  border: 1px solid rgba(220, 38, 38, 0.18);
  background: rgba(220, 38, 38, 0.06);
  color: #b91c1c;
}

.permissions-admin__panel {
  display: grid;
  gap: 18px;
}

.permissions-admin__role-switcher {
  margin-bottom: 18px;
}

.permissions-admin__section-switcher {
  display: flex;
  align-items: stretch;
  gap: 12px;
  margin-bottom: 22px;
}

.permissions-admin__section-chip {
  flex: 1 1 0;
  min-height: 58px;
  padding: 0 24px;
  border: 1px solid rgba(201, 224, 233, 0.95);
  border-radius: 22px;
  background: #fff;
  color: #315f73;
  font-size: 1.02rem;
  font-weight: 900;
  transition: 0.18s ease;
}

.permissions-admin__section-chip--active {
  border-color: transparent;
  background: linear-gradient(180deg, #1e809b 0%, #146d88 100%);
  color: #fff;
}

.permissions-admin__role-field {
  max-width: 340px;
}

.permissions-admin__role-label {
  margin-bottom: 8px;
  color: #315f73;
  font-size: 0.86rem;
  font-weight: 900;
}

.permissions-admin__role-select {
  width: 100%;
}

.permissions-admin__role-select :deep(.v-input__slot) {
  min-height: 54px !important;
  border-radius: 18px;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
}

.permissions-admin__role-select :deep(.v-select__selection),
.permissions-admin__role-select :deep(.v-select__selections),
.permissions-admin__role-select :deep(input) {
  color: #123f56;
  font-size: 0.98rem;
  font-weight: 800;
}

.permissions-admin__card {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  padding: 24px;
}

.permissions-admin__card-head {
  margin-bottom: 14px;
}

.permissions-admin__card-caption {
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 700;
}

.permissions-admin__card-title {
  margin: 6px 0 0;
  color: #0f172a;
  font-size: 1.2rem;
  font-weight: 900;
}

.permissions-admin__groups {
  display: grid;
  gap: 2px;
}

.permissions-admin__group {
  padding-top: 14px;
}

.permissions-admin__group-title {
  padding-bottom: 8px;
  color: #64748b;
  font-size: 0.76rem;
  font-weight: 900;
}

.permissions-admin__permission-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
  padding: 12px 0;
}

.permissions-admin__permission-row:last-child {
  border-bottom: 0;
}

.permissions-admin__switch {
  margin: 0;
  padding: 0;
}

.permissions-admin__permission-label {
  color: #0f172a;
  font-size: 0.94rem;
  font-weight: 700;
}

.permissions-admin__permission-label--muted {
  color: #64748b;
}

@media (max-width: 960px) {
  .permissions-admin__section-switcher,
  .permissions-admin__role-switcher {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
  }

  .permissions-admin__role-field {
    max-width: none;
  }
}
</style>
