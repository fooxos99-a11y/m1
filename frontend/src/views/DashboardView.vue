<template>
  <div class="dashboard-page">
    <aside
      class="dashboard-sidebar"
      :class="{ 'dashboard-sidebar--open': mobileMenuOpen }"
    >
      <router-link
        :to="{ name: 'practitioner' }"
        class="dashboard-sidebar__brand"
      >
        <img
          src="/اللوقو-شفاف.png"
          alt="شعار البرنامج"
          class="dashboard-sidebar__logo"
        >
        <div class="dashboard-sidebar__title">
          لوحة التحكم
        </div>
      </router-link>

      <nav class="dashboard-nav">
        <template v-for="item in dashboardMenu">
          <div
            v-if="item.dividerBefore"
            :key="`${item.id}-divider`"
            class="dashboard-nav__divider"
          />
          <div
            :key="item.id"
            class="dashboard-nav__entry"
          >
            <button
              type="button"
              class="dashboard-nav__item"
              :class="{ 'dashboard-nav__item--active': isMenuItemActive(item) }"
              :disabled="panelLoading"
              @click="handleMenu(item)"
            >
              <span class="dashboard-nav__copy">
                <span class="dashboard-nav__icon"><v-icon small>{{ item.icon }}</v-icon></span>
                <span class="dashboard-nav__label">{{ item.label }}</span>
                <span
                  v-if="item.id === 'settings'"
                  class="dashboard-nav__chevron"
                  :class="{ 'dashboard-nav__chevron--open': settingsMenuOpen }"
                >
                  <v-icon small>mdi-chevron-down</v-icon>
                </span>
              </span>
              <span
                v-if="item.id !== 'settings'"
                class="dashboard-nav__dot"
              />
            </button>

            <div
              v-if="item.id === 'settings' && settingsMenuOpen && settingsItems.length"
              class="dashboard-subnav"
            >
              <button
                v-for="setting in settingsItems"
                :key="setting.id"
                type="button"
                class="dashboard-subnav__item"
                :class="{ 'dashboard-subnav__item--active': selectedSettingsItemId === setting.id && activeMenu === 'settings' }"
                :disabled="panelLoading"
                @click="openSettingsItem(setting.id)"
              >
                {{ setting.label }}
              </button>
            </div>
          </div>
        </template>
      </nav>
    </aside>

    <div
      v-if="mobileMenuOpen"
      class="dashboard-backdrop"
      @click="mobileMenuOpen = false"
    />

    <main class="dashboard-main">
      <header class="dashboard-topbar">
        <div class="dashboard-topbar__mobile">
          <AppIconButton
            variant="primary"
            class="dashboard-icon-button"
            aria-label="فتح القائمة"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <v-icon>{{ mobileMenuOpen ? 'mdi-close' : 'mdi-menu' }}</v-icon>
          </AppIconButton>
        </div>
        <div class="dashboard-topbar__welcome">
          <div class="dashboard-topbar__label">
            مرحبًا
          </div>
          <div class="dashboard-topbar__name">
            {{ adminName }}
          </div>
        </div>
        <div
          v-if="activeMenu === 'settings' && selectedSettingsItemId === 'permissions' && permissionsTopbarState.isAdmin"
          class="dashboard-topbar__actions"
        >
          <AppChoiceButton
            variant="soft"
            :active="true"
            class="dashboard-topbar__action dashboard-topbar__action--choice"
            @click="togglePermissionsWorkspaceSection"
          >
            {{ permissionsTopbarState.activeSection === 'supervision' ? 'الصلاحيات' : 'الإشراف' }}
          </AppChoiceButton>
        </div>
        <div
          v-if="activeMenu === 'satisfaction'"
          class="dashboard-topbar__actions"
        >
          <AppButton
            variant="primary"
            class="dashboard-topbar__action"
            @click="openSatisfactionWorkspaceAddDialog"
          >
            إضافة
          </AppButton>
        </div>
        <div
          v-if="activeMenu === 'settings' && selectedSettingsItemId === 'archive'"
          class="dashboard-topbar__actions"
        >
          <AppButton
            variant="secondary"
            class="dashboard-topbar__action dashboard-topbar__action--ghost"
            @click="openArchiveWorkspaceArchiveAllDialog"
          >
            أرشفة المحتوى الحالي
          </AppButton>
          <AppButton
            variant="primary"
            class="dashboard-topbar__action"
            @click="openArchiveWorkspaceCreateDialog"
          >
            إضافة
          </AppButton>
        </div>
        <div
          v-if="activeMenu === 'settings' && selectedSettingsItemId === 'registration'"
          class="dashboard-topbar__actions"
        >
          <AppButton
            variant="secondary"
            class="dashboard-topbar__action dashboard-topbar__action--ghost"
            @click="copyRegistrationWorkspaceLink"
          >
            نسخ الرابط
          </AppButton>
          <AppButton
            variant="secondary"
            class="dashboard-topbar__action dashboard-topbar__action--ghost"
            @click="openRegistrationWorkspaceFieldsDialog"
          >
            بيانات التسجيل
          </AppButton>
          <AppButton
            :variant="registrationTopbarState.isOpen ? 'secondary' : 'success'"
            class="dashboard-topbar__action"
            :loading="registrationTopbarState.loading"
            @click="toggleRegistrationWorkspaceState"
          >
            {{ registrationTopbarState.isOpen ? 'إغلاق التسجيل' : 'فتح التسجيل' }}
          </AppButton>
        </div>
        <div
          v-if="showTopbarCountdown"
          class="dashboard-topbar__timer-list"
        >
          <div
            v-for="timer in topbarCountdownItems"
            :key="`${activeMenu}-${timer.branchCode || 'all'}-${timer.closesAt || 'none'}`"
            class="dashboard-topbar__timer"
          >
            <span class="dashboard-topbar__timer-text">{{ timer.text }}</span>
            <span class="dashboard-topbar__timer-label">{{ timer.label }}</span>
          </div>
        </div>
        <div
          v-if="showAssessmentTopbarAction"
          class="dashboard-topbar__actions"
        >
          <AppButton
            variant="primary"
            class="dashboard-topbar__action"
            @click="triggerAssessmentTopbarAction"
          >
            {{ assessmentTopbarState.label }}
          </AppButton>
        </div>
        <div
          v-if="activeMenu === 'overview' && overviewTopActions.length"
          id="quick-links"
          class="dashboard-topbar__actions dashboard-topbar__actions--overview"
        >
          <AppButton
            v-for="action in overviewTopActions"
            :key="action.id"
            variant="secondary"
            class="dashboard-overview-action"
            :disabled="panelLoading || overviewDialogLoading"
            @click="handleOverviewAction(action)"
          >
            <v-icon small>
              {{ action.icon }}
            </v-icon>
            <span>{{ action.label }}</span>
          </AppButton>
        </div>
        <div
          v-if="activeMenu === 'finalexam'"
          class="dashboard-topbar__actions"
        >
          <AppButton
            variant="secondary"
            class="dashboard-topbar__action dashboard-topbar__action--ghost"
            @click="triggerFinalExamCopy"
          >
            نسخ
          </AppButton>
          <AppButton
            variant="primary"
            class="dashboard-topbar__action"
            @click="openFinalExamActivationDialog"
          >
            {{ finalExamTopbarState.isEnabled ? 'إيقاف الاختبار' : 'بدء' }}
          </AppButton>
        </div>
      </header>

      <div
        v-if="dashboardError"
        class="dashboard-error"
      >
        تعذر الاتصال بقاعدة البيانات: {{ dashboardError }}
      </div>

      <section
        v-if="showDashboardLoader"
        class="dashboard-loader-shell"
      >
        <div class="dashboard-loader-shell__inner">
          <div
            class="loader"
            aria-hidden="true"
          />
        </div>
      </section>

      <template v-else-if="activeMenu === 'overview'">
        <section
          id="overview"
          class="dashboard-section dashboard-section--hero"
        >
          <div class="dashboard-card dashboard-card--overview-header">
            <div class="dashboard-overview-header__top">
              <div class="dashboard-card__title-wrap">
                <h1 class="dashboard-card__title dashboard-card__title--overview">
                  المؤشرات الإجمالية
                </h1>
              </div>
              <div
                v-if="showOverviewBranchFilter"
                class="dashboard-overview-filter"
              >
                <AppSelect
                  v-model="selectedOverviewBranch"
                  :items="overviewBranchOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="dashboard-overview-filter__select"
                />
              </div>
            </div>
          </div>

          <div class="dashboard-card dashboard-card--indicators">
            <div class="dashboard-indicators-grid">
              <article
                v-for="indicator in dashboardIndicators"
                :key="indicator.key"
                class="dashboard-indicator-card"
              >
                <div
                  class="dashboard-indicator-ring"
                  :class="{ 'dashboard-indicator-ring--complete': indicator.progress >= 100, 'dashboard-indicator-ring--empty': indicator.progress <= 0 }"
                  :style="indicatorStyle(indicator.progress)"
                >
                  <svg
                    viewBox="0 0 200 200"
                    class="dashboard-indicator-ring__svg"
                    aria-hidden="true"
                  >
                    <defs>
                      <linearGradient
                        :id="`dashboard-indicator-gradient-${indicator.key}`"
                        x1="0%"
                        y1="0%"
                        x2="100%"
                        y2="100%"
                      >
                        <stop
                          offset="0%"
                          stop-color="#2a94b2"
                        />
                        <stop
                          offset="55%"
                          stop-color="#1b7692"
                        />
                        <stop
                          offset="100%"
                          stop-color="#0d5a73"
                        />
                      </linearGradient>
                    </defs>
                    <circle
                      class="dashboard-indicator-ring__track"
                      cx="100"
                      cy="100"
                      r="78"
                    />
                    <circle
                      class="dashboard-indicator-ring__progress"
                      :style="{ stroke: `url(#dashboard-indicator-gradient-${indicator.key})` }"
                      cx="100"
                      cy="100"
                      r="78"
                    />
                  </svg>
                  <div class="dashboard-indicator-ring__inner">
                    {{ animatedIndicatorDisplay(indicator.display) }}
                  </div>
                </div>
                <div class="dashboard-indicator-card__label">
                  {{ indicator.label }}
                </div>
              </article>
            </div>
          </div>
        </section>
      </template>

      <section
        v-else
        class="dashboard-workspace-panel"
        :class="{
          'dashboard-workspace-panel--assessment': isAssessmentWorkspace,
          'dashboard-workspace-panel--communications': isCommunicationsWorkspace,
          'dashboard-workspace-panel--registration': isRegistrationWorkspace,
        }"
      >
        <component
          :is="currentWorkspaceComponent"
          ref="workspacePanel"
          v-bind="currentWorkspaceProps"
          @assessment-topbar-state="handleAssessmentTopbarState"
          @final-exam-topbar-state="handleFinalExamTopbarState"
          @permissions-topbar-state="handlePermissionsTopbarState"
          @registration-topbar-state="handleRegistrationTopbarState"
          @open-dialog-item="handleSettingsDialogItem"
        />
      </section>

      <AppDialog
        v-model="linksDialogOpen"
        max-width="760"
      >
        <v-card class="dashboard-dialog-card pa-5">
          <div class="dashboard-links-dialog-grid">
            <article
              v-for="item in directAccessLinks"
              :key="item.id"
              class="dashboard-links-dialog-card"
            >
              <div class="dashboard-links-dialog-card__title">
                {{ item.title }}
              </div>
              <div class="dashboard-links-dialog-card__url">
                {{ item.url }}
              </div>
              <AppButton
                variant="secondary"
                class="dashboard-links-dialog-card__copy"
                @click="copyDirectAccessLink(item)"
              >
                <span>نسخ الرابط</span>
                <v-icon small>
                  mdi-content-copy
                </v-icon>
              </AppButton>
            </article>
          </div>
        </v-card>
      </AppDialog>

      <AppDialog
        v-model="adminsDialogOpen"
        max-width="760"
        @close="resetAdminDialog"
      >
        <v-card class="dashboard-dialog-card dashboard-supervision-manager pa-0">
          <div class="dashboard-dialog-card__header dashboard-dialog-card__header--panel dashboard-supervision-manager__header px-5 py-4">
            <div>
              <h2 class="dashboard-dialog-card__title">
                الإشراف
              </h2>
            </div>
          </div>

          <div class="dashboard-supervision-manager__body px-5 pt-5 pb-4">
            <div class="dashboard-admins-list dashboard-supervision-manager__list-shell">
              <div
                v-if="adminsLoading"
                class="dashboard-empty-state"
              >
                جارٍ تحميل المشرفين...
              </div>
              <div
                v-else-if="dashboardAccounts.length === 0"
                class="dashboard-empty-state"
              >
                لا توجد حسابات إشرافية حالياً.
              </div>
              <div
                v-else
                class="dashboard-admins-stack dashboard-admins-stack--supervision"
              >
                <div
                  v-for="account in dashboardAccounts"
                  :key="account.id"
                  class="dashboard-admin-card dashboard-admin-card--supervision"
                >
                  <div class="dashboard-admin-card__content dashboard-admin-card__content--supervision">
                    <div class="dashboard-admin-card__name">
                      {{ account.name }}
                    </div>
                    <div class="dashboard-admin-card__meta">
                      {{ roleLabel(account.role) }} - {{ account.loginCode }}
                    </div>
                  </div>

                  <AppIconButton
                    variant="danger"
                    class="dashboard-course-row__icon dashboard-course-row__icon--delete"
                    :disabled="adminDeletingId === account.id || currentUser?.id === account.id"
                    @click="removeDashboardAccount(account.id)"
                  >
                    <i
                      class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                      aria-hidden="true"
                    />
                  </AppIconButton>
                </div>
              </div>
            </div>

            <div class="dashboard-supervision-form">
              <div class="dashboard-supervision-field">
                <label class="dashboard-supervision-field__label">المسمى</label>
                <AppSelect
                  v-model="adminForm.role"
                  :items="adminRoleOptions"
                  item-text="label"
                  item-value="value"
                  dense
                  outlined
                  hide-details
                  class="dashboard-supervision-field__input"
                />
              </div>

              <div class="dashboard-supervision-field">
                <label class="dashboard-supervision-field__label">الاسم</label>
                <AppTextField
                  v-model.trim="adminForm.name"
                  placeholder="الاسم"
                  dense
                  outlined
                  hide-details
                  class="dashboard-supervision-field__input"
                />
              </div>

              <div class="dashboard-supervision-field">
                <label class="dashboard-supervision-field__label">رقم الدخول</label>
                <AppTextField
                  v-model.trim="adminForm.loginCode"
                  placeholder="رقم الدخول"
                  dense
                  outlined
                  hide-details
                  class="dashboard-supervision-field__input"
                />
              </div>
            </div>

            <div
              v-if="adminError"
              class="dashboard-dialog-error"
            >
              {{ adminError }}
            </div>
          </div>

          <AppDialogFooter class="dashboard-supervision-manager__footer px-5 py-4">
            <AppButton
              variant="primary"
              class="dashboard-supervision-manager__submit"
              :loading="adminSubmitting"
              @click="submitDashboardAccount"
            >
              إضافة
            </AppButton>
            <AppButton
              variant="secondary"
              class="dashboard-course-manager__cancel"
              @click="resetAdminDialog"
            >
              إلغاء
            </AppButton>
          </AppDialogFooter>
        </v-card>
      </AppDialog>

      <AppDialog
        v-model="templatesDialogOpen"
        max-width="720"
      >
        <v-card class="dashboard-dialog-card dashboard-templates-manager pa-0">
          <div class="dashboard-dialog-card__header dashboard-dialog-card__header--panel dashboard-templates-manager__header px-5 py-4">
            <div>
              <h2 class="dashboard-dialog-card__title">
                قوالب إشعارات الدورات
              </h2>
            </div>
          </div>

          <div class="dashboard-templates-manager__body px-5 pt-5 pb-4">
            <div class="dashboard-template-field">
              <label class="dashboard-supervision-field__label">الدورة</label>
              <AppSelect
                v-model="selectedTemplateCourseId"
                :items="courseDialogOptions"
                item-text="label"
                item-value="value"
                dense
                outlined
                hide-details
                class="dashboard-supervision-field__input dashboard-template-field__input"
              />
            </div>

            <div class="dashboard-template-grid">
              <div class="dashboard-template-field">
                <label class="dashboard-supervision-field__label">قالب القبلي</label>
                <v-textarea
                  v-model="templateDraft.pre"
                  rows="3"
                  outlined
                  hide-details
                  class="dashboard-template-field__input dashboard-template-field__input--textarea"
                />
              </div>

              <div class="dashboard-template-field">
                <label class="dashboard-supervision-field__label">قالب البعدي</label>
                <v-textarea
                  v-model="templateDraft.post"
                  rows="3"
                  outlined
                  hide-details
                  class="dashboard-template-field__input dashboard-template-field__input--textarea"
                />
              </div>

              <div class="dashboard-template-field">
                <label class="dashboard-supervision-field__label">قالب المهام</label>
                <v-textarea
                  v-model="templateDraft.tasks"
                  rows="3"
                  outlined
                  hide-details
                  class="dashboard-template-field__input dashboard-template-field__input--textarea"
                />
              </div>
            </div>
          </div>

          <AppDialogFooter class="dashboard-supervision-manager__footer dashboard-templates-manager__footer px-5 py-4">
            <AppButton
              variant="secondary"
              class="dashboard-course-manager__cancel"
              @click="templatesDialogOpen = false"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              class="dashboard-supervision-manager__submit"
              :loading="templatesSubmitting"
              :disabled="!selectedTemplateCourseId"
              @click="saveNotificationTemplates"
            >
              حفظ
            </AppButton>
          </AppDialogFooter>
        </v-card>
      </AppDialog>

      <AppDialog
        v-model="backupDialogOpen"
        max-width="760"
      >
        <AdminBackupView
          :embedded="true"
          :dialog-mode="true"
          @close="backupDialogOpen = false"
        />
      </AppDialog>

      <AppDialog
        v-model="satisfactionAddDialogOpen"
        max-width="640"
        @close="closeSatisfactionAddDialog"
      >
        <v-card class="dashboard-dialog-card pa-5">
          <div class="dashboard-dialog-card__header">
            <div>
              <div class="dashboard-dialog-card__eyebrow">
                استبيان الرضا
              </div>
              <h2 class="dashboard-dialog-card__title">
                إضافة سؤال جديد
              </h2>
            </div>
          </div>

          <div class="dashboard-satisfaction-dialog__note">
            سيتم إضافة هذا السؤال تلقائيًا لجميع الاختبارات البعدية في الدورات المفعلة.
          </div>

          <AppTextField
            v-model.trim="satisfactionQuestionDraft.prompt"
            label="نص السؤال"
            dense
            outlined
            class="dashboard-dialog-card__field"
          />

          <AppSelect
            v-model="satisfactionQuestionDraft.type"
            :items="satisfactionQuestionTypeOptions"
            item-text="label"
            item-value="value"
            label="نوع السؤال"
            dense
            outlined
            class="dashboard-dialog-card__field"
          />

          <v-switch
            v-model="satisfactionQuestionDraft.isRequired"
            inset
            hide-details
            class="dashboard-satisfaction-dialog__switch"
            label="سؤال إلزامي"
          />

          <AppDialogFooter class="dashboard-dialog-card__actions">
            <AppButton
              variant="secondary"
              @click="closeSatisfactionAddDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="satisfactionSubmitting"
              @click="submitSatisfactionQuestion"
            >
              إضافة
            </AppButton>
          </AppDialogFooter>
        </v-card>
      </AppDialog>

      <AppDialog
        v-model="satisfactionDeleteDialogOpen"
        max-width="640"
        @close="closeSatisfactionDeleteDialog"
      >
        <v-card class="dashboard-dialog-card pa-5">
          <div class="dashboard-dialog-card__header">
            <div>
              <div class="dashboard-dialog-card__eyebrow">
                استبيان الرضا
              </div>
              <h2 class="dashboard-dialog-card__title">
                حذف سؤال
              </h2>
            </div>
          </div>

          <div
            v-if="!satisfactionQuestionOptions.length"
            class="dashboard-empty-state"
          >
            لا توجد أسئلة متاحة للحذف.
          </div>
          <template v-else>
            <div class="dashboard-satisfaction-dialog__note">
              سيتم حذف السؤال المحدد من جميع الدورات التي تحتوي على نفس النص ونفس النوع.
            </div>

            <AppSelect
              v-model="selectedSatisfactionDeleteKey"
              :items="satisfactionQuestionOptions"
              item-text="label"
              item-value="value"
              label="السؤال"
              dense
              outlined
              class="dashboard-dialog-card__field"
            />

            <AppDialogFooter class="dashboard-dialog-card__actions">
              <AppButton
                variant="secondary"
                @click="closeSatisfactionDeleteDialog"
              >
                إلغاء
              </AppButton>
              <AppButton
                variant="danger"
                :loading="satisfactionDeleting"
                @click="confirmDeleteSatisfactionQuestion"
              >
                حذف
              </AppButton>
            </AppDialogFooter>
          </template>
        </v-card>
      </AppDialog>
    </main>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import AdminAssessmentView from './AdminAssessmentView.vue';
import AdminTasksView from './AdminTasksView.vue';
import AdminFinalExamView from './AdminFinalExamView.vue';
import AdminPeopleView from './AdminPeopleView.vue';
import AdminCommunicationsView from './AdminCommunicationsView.vue';
import AdminTrainingMaterialsView from './AdminTrainingMaterialsView.vue';
import AdminResultsView from './AdminResultsView.vue';
import AdminSatisfactionView from './AdminSatisfactionView.vue';
import AdminBackupView from './AdminBackupView.vue';
import AdminPermissionsView from './AdminPermissionsView.vue';
import AdminArchiveView from './AdminArchiveView.vue';
import AdminSettingsView from './AdminSettingsView.vue';
import {
  AppButton, AppChoiceButton, AppDialog, AppDialogFooter, AppIconButton, AppSelect,
  AppTextField,
} from '../components/ui';
import { buildProgramIndicators } from '../utils/programIndicators';
import indicatorAnimation from '../mixins/indicatorAnimation';

export default {
  name: 'DashboardView',
  components: {
    AppDialog,
    AppButton,
    AppChoiceButton,
    AppIconButton,
    AppSelect,
    AppDialogFooter,
    AppTextField,
    AdminAssessmentView,
    AdminTasksView,
    AdminFinalExamView,
    AdminPeopleView,
    AdminCommunicationsView,
    AdminTrainingMaterialsView,
    AdminResultsView,
    AdminSatisfactionView,
    AdminBackupView,
    AdminPermissionsView,
    AdminArchiveView,
    AdminSettingsView,
  },
  mixins: [indicatorAnimation],
  data() {
    return {
      mobileMenuOpen: false,
      activeMenu: 'overview',
      panelLoading: false,
      selectedOverviewBranch: 'all',
      linksDialogOpen: false,
      adminsDialogOpen: false,
      templatesDialogOpen: false,
      backupDialogOpen: false,
      templatesSubmitting: false,
      adminsLoading: false,
      adminSubmitting: false,
      adminDeletingId: '',
      adminError: '',
      selectedTemplateCourseId: '',
      selectedSatisfactionCourseId: '',
      templateDraft: {
        pre: '',
        post: '',
        tasks: '',
      },
      dashboardAccounts: [],
      adminForm: {
        role: 'male_manager',
        name: '',
        loginCode: '',
      },
      satisfactionAddDialogOpen: false,
      satisfactionDeleteDialogOpen: false,
      satisfactionSubmitting: false,
      satisfactionDeleting: false,
      satisfactionQuestionDraft: {
        prompt: '',
        type: 'rating',
        isRequired: true,
      },
      selectedSatisfactionDeleteKey: '',
      finalExamTopbarState: {
        branchCode: 'male',
        branchLabel: 'معلمين',
        isEnabled: false,
        closesAt: null,
        timers: [],
      },
      assessmentTopbarState: {
        visible: false,
        label: '',
        type: '',
        isEnabled: false,
        closesAt: null,
        timers: [],
      },
      registrationTopbarState: {
        isOpen: false,
        loading: false,
      },
      permissionsTopbarState: {
        isAdmin: false,
        activeSection: 'permissions',
      },
      currentTimestamp: Date.now(),
      dashboardClockIntervalId: null,
      settingsMenuOpen: false,
      selectedSettingsItemId: '',
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'dashboardLoading', 'dashboardError', 'currentUser']),
    isAdmin() {
      return this.currentUser?.role === 'admin';
    },
    managerPermissions() {
      return this.dashboardSnapshot?.rolePermissions?.[this.currentUser?.role] || {};
    },
    showDashboardLoader() {
      return this.panelLoading || (this.dashboardLoading && !this.dashboardSnapshot);
    },
    overviewDialogLoading() {
      return this.templatesSubmitting || this.adminsLoading || this.adminSubmitting;
    },
    adminName() {
      return this.currentUser?.name || this.currentUser?.loginCode || 'مدير النظام';
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
    showOverviewBranchFilter() {
      return !this.managedBranchId;
    },
    effectiveOverviewBranch() {
      return this.managedBranchId || this.selectedOverviewBranch;
    },
    dashboardMenu() {
      return [
        { id: 'overview', label: 'الرئيسية', icon: 'mdi-home-outline', mode: 'panel' },
        { id: 'courses', label: 'الدورات', icon: 'mdi-database-outline', mode: 'panel' },
        { id: 'tasks', label: 'المهام الأدائية', icon: 'mdi-clipboard-text-outline', mode: 'panel' },
        { id: 'finalexam', label: 'الاختبار النهائي', icon: 'mdi-school-outline', mode: 'panel' },
        { id: 'satisfaction', label: 'استبيان الرضا', icon: 'mdi-clipboard-text-clock-outline', mode: 'panel' },
        { id: 'users', label: 'المستخدمين', icon: 'mdi-account-group-outline', mode: 'panel' },
        { id: 'notifications', label: 'الإشعارات', icon: 'mdi-bell-outline', mode: 'panel' },
        { id: 'materials', label: 'المواد التدريبية', icon: 'mdi-folder-multiple-outline', mode: 'panel' },
        { id: 'results', label: 'النتائج', icon: 'mdi-chart-box-outline', mode: 'panel' },
        { id: 'settings', label: 'الاعدادات', icon: 'mdi-cog-outline', mode: 'panel', dividerBefore: true },
      ].filter((item) => this.canAccessPanel(item.id));
    },
    settingsItems() {
      const items = [];

      if (this.isAdmin) {
        items.push({ id: 'home', label: 'صفحة رخصة ممارس', kind: 'panel' });
      }

      if (this.canAccessPanel('permissions')) {
        items.push({
          id: 'permissions',
          label: this.isAdmin ? 'الإشراف والصلاحيات' : 'الصلاحيات',
          kind: 'panel',
        });
      }

      if (this.canAccessPanel('archive')) {
        items.push({ id: 'archive', label: 'الأرشيف', kind: 'panel' });
      }

      if (this.canAccessPanel('registration')) {
        items.push({ id: 'registration', label: 'التسجيل', kind: 'panel' });
      }

      if (this.isAdmin) {
        items.push({ id: 'templates', label: 'القوالب', kind: 'panel' });
      }

      return items;
    },
    adminLinks() {
      return [
        {
          name: 'courses',
          title: 'الدورات',
          icon: 'mdi-view-dashboard-outline',
          panel: 'courses',
        },
        {
          name: 'tasks',
          title: 'إدارة المهام',
          icon: 'mdi-clipboard-list-outline',
          panel: 'tasks',
        },
        {
          name: 'final',
          title: 'إدارة النهائي',
          icon: 'mdi-trophy-outline',
          panel: 'finalexam',
        },
        {
          name: 'people',
          title: 'المعلمون والمقرئون',
          icon: 'mdi-account-group-outline',
          panel: 'users',
        },
        {
          name: 'communications',
          title: 'الإشعارات',
          icon: 'mdi-bell-outline',
          panel: 'notifications',
        },
        {
          name: 'results',
          title: 'النتائج والحضور',
          icon: 'mdi-chart-box-outline',
          panel: 'results',
        },
      ].filter((item) => this.canAccessPanel(item.panel));
    },
    overviewTopActions() {
      return [];
    },
    courseDialogOptions() {
      return (this.dashboardSnapshot?.courses || []).map((course) => ({
        label: course.title,
        value: course.id,
      }));
    },
    selectedTemplateCourse() {
      return (this.dashboardSnapshot?.courses || []).find((course) => course.id === this.selectedTemplateCourseId) || null;
    },
    directAccessLinks() {
      const origin = typeof window !== 'undefined' ? window.location.origin : '';

      return [
        {
          id: 'pre',
          title: 'رابط الاختبارات القبلية',
          url: `${origin}/course/pre`,
        },
        {
          id: 'post',
          title: 'رابط الاختبارات البعدية',
          url: `${origin}/course/post`,
        },
        {
          id: 'tasks',
          title: 'رابط المهام الأدائية',
          url: `${origin}/course/tasks`,
        },
        {
          id: 'final-exam',
          title: 'رابط الاختبار النهائي',
          url: `${origin}/final-exam`,
        },
      ];
    },
    satisfactionCourseOptions() {
      return [...((this.dashboardSnapshot?.courses || []).filter((course) => course.entityType !== 'task' && course.isPostEnabled))]
        .sort((left, right) => (left.sortOrder || 0) - (right.sortOrder || 0))
        .map((course) => ({
          label: course.title,
          value: course.id,
        }));
    },
    selectedSatisfactionCourse() {
      const selected = (this.dashboardSnapshot?.courses || []).find((course) => course.id === this.selectedSatisfactionCourseId);

      if (selected) {
        return selected;
      }

      const [firstOption] = this.satisfactionCourseOptions;

      return (this.dashboardSnapshot?.courses || []).find((course) => course.id === firstOption?.value) || null;
    },
    selectedSatisfactionQuestions() {
      if (!this.selectedSatisfactionCourse) {
        return [];
      }

      return [...(this.dashboardSnapshot?.satisfactionQuestions || [])]
        .filter((question) => question.courseId === this.selectedSatisfactionCourse.id)
        .sort((left, right) => left.sortOrder - right.sortOrder);
    },
    satisfactionQuestionOptions() {
      const seen = new Set();

      return this.selectedSatisfactionQuestions
        .filter((question) => {
          const key = this.getSatisfactionQuestionKey(question);

          if (seen.has(key)) {
            return false;
          }

          seen.add(key);
          return true;
        })
        .map((question) => ({
          label: `${question.prompt} - ${question.type === 'rating' ? 'تقييم' : 'نصي'}`,
          value: this.getSatisfactionQuestionKey(question),
        }));
    },
    selectedSatisfactionDeleteQuestions() {
      return (this.dashboardSnapshot?.satisfactionQuestions || []).filter((question) => (
        this.getSatisfactionQuestionKey(question) === this.selectedSatisfactionDeleteKey
      ));
    },
    selectedSatisfactionResponses() {
      if (!this.selectedSatisfactionCourse) {
        return [];
      }

      return (this.dashboardSnapshot?.satisfactionResponses || []).filter((response) => response.courseId === this.selectedSatisfactionCourse.id);
    },
    satisfactionRatingIndicators() {
      return this.selectedSatisfactionQuestions
        .filter((question) => question.type === 'rating')
        .map((question) => {
          const values = this.selectedSatisfactionResponses
            .filter((response) => response.questionId === question.id && response.ratingValue !== null && response.ratingValue !== undefined)
            .map((response) => Number(response.ratingValue))
            .filter((value) => Number.isFinite(value));
          const average = values.length ? values.reduce((sum, value) => sum + value, 0) / values.length : null;

          return {
            id: question.id,
            label: question.prompt,
            count: values.length,
            average,
            progress: average === null ? 0 : average * 10,
            display: average === null ? '--' : average.toFixed(1),
          };
        });
    },
    satisfactionQuestionTypeOptions() {
      return [
        { label: 'تقييم من 1 إلى 10', value: 'rating' },
        { label: 'إجابة نصية', value: 'text' },
      ];
    },
    adminRoleOptions() {
      return [
        { label: 'مدير عام', value: 'admin' },
        { label: 'مشرف', value: 'male_manager' },
        { label: 'مشرفة', value: 'female_manager' },
      ];
    },
    overviewBranchOptions() {
      if (this.managedBranchId) {
        return [
          {
            label: this.managedBranchId === 'female' ? 'معلمات' : 'معلمين',
            value: this.managedBranchId,
          },
        ];
      }

      return [
        { label: 'الكل', value: 'all' },
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
      ];
    },
    overviewStudents() {
      const students = this.dashboardSnapshot?.students || [];

      if (this.effectiveOverviewBranch === 'all') {
        return students;
      }

      return students.filter((student) => student.branchId === this.effectiveOverviewBranch);
    },
    overviewAttendance() {
      const attendance = this.dashboardSnapshot?.attendance || [];

      if (this.effectiveOverviewBranch === 'all') {
        return attendance;
      }

      const loginIds = new Set(this.overviewStudents.map((student) => student.loginId).filter(Boolean));

      return attendance.filter((record) => loginIds.has(record.loginId));
    },
    overviewSubmissions() {
      const submissions = this.dashboardSnapshot?.submissions || [];

      if (this.effectiveOverviewBranch === 'all') {
        return submissions;
      }

      const loginIds = new Set(this.overviewStudents.map((student) => student.loginId).filter(Boolean));

      return submissions.filter((item) => loginIds.has(item.loginId));
    },
    currentWorkspaceComponent() {
      switch (this.activeMenu) {
        case 'results':
          return AdminResultsView;
        case 'courses':
          return AdminAssessmentView;
        case 'tasks':
          return AdminTasksView;
        case 'finalexam':
          return AdminFinalExamView;
        case 'users':
          return AdminPeopleView;
        case 'materials':
          return AdminTrainingMaterialsView;
        case 'permissions':
          return AdminPermissionsView;
        case 'archive':
          return AdminArchiveView;
        case 'settings':
          return AdminSettingsView;
        case 'satisfaction':
          return AdminSatisfactionView;
        case 'notifications':
        case 'activitylog':
          return AdminCommunicationsView;
        default:
          return null;
      }
    },
    routeWorkspacePanel() {
      return this.normalizeDashboardPanel(this.$route.query.panel);
    },
    routeWorkspaceAssessmentType() {
      return this.normalizeDashboardAssessmentType(this.$route.query.assessmentType);
    },
    routeWorkspaceCourseId() {
      return typeof this.$route.query.courseId === 'string' ? this.$route.query.courseId.trim() : '';
    },
    routeSettingsItemId() {
      return typeof this.$route.query.settingsItem === 'string' ? this.$route.query.settingsItem.trim() : '';
    },
    currentWorkspaceProps() {
      if (this.activeMenu === 'results') {
        return { embedded: true, panelMode: 'results' };
      }

      if (this.activeMenu === 'courses') {
        return {
          embedded: true,
          assessmentTypeOverride: this.routeWorkspaceAssessmentType,
          courseIdOverride: this.routeWorkspaceCourseId,
        };
      }

      if (this.activeMenu === 'tasks') {
        return { embedded: true };
      }

      if (this.activeMenu === 'notifications') {
        return { embedded: true, panelMode: 'notifications' };
      }

      if (this.activeMenu === 'materials') {
        return { embedded: true };
      }

      if (this.activeMenu === 'activitylog') {
        return { embedded: true, panelMode: 'activitylog' };
      }

      if (this.activeMenu === 'settings') {
        return {
          embedded: true,
          items: this.settingsItems,
          activeItemId: this.selectedSettingsItemId,
          hideSidebar: true,
        };
      }

      if (['finalexam', 'users', 'permissions', 'satisfaction', 'archive'].includes(this.activeMenu)) {
        return { embedded: true };
      }

      return {};
    },
    isAssessmentWorkspace() {
      return ['courses', 'tasks', 'finalexam', 'satisfaction'].includes(this.activeMenu);
    },
    isCommunicationsWorkspace() {
      return ['notifications', 'activitylog', 'materials'].includes(this.activeMenu);
    },
    isRegistrationWorkspace() {
      return this.activeMenu === 'settings' && this.selectedSettingsItemId === 'registration';
    },
    dashboardIndicators() {
      return buildProgramIndicators({
        students: this.overviewStudents,
        courses: this.dashboardSnapshot?.courses || [],
        submissions: this.overviewSubmissions,
        attendance: this.overviewAttendance,
      });
    },
    dashboardIndicatorsSignature() {
      return this.dashboardIndicators
        .map((indicator) => `${indicator.key}:${indicator.progress}:${indicator.display}`)
        .join('|');
    },
    recentNotifications() {
      return (this.dashboardSnapshot?.notifications || []).slice(0, 3);
    },
    recentActivityLogs() {
      return (this.dashboardSnapshot?.activityLogs || []).slice(0, 3);
    },
    hasSatisfactionCourses() {
      return (this.dashboardSnapshot?.courses || []).some((course) => course.entityType !== 'task' && course.isPostEnabled);
    },
    hasSatisfactionQuestions() {
      return (this.dashboardSnapshot?.satisfactionQuestions || []).length > 0;
    },
    finalExamCountdownItems() {
      if (this.activeMenu !== 'finalexam') {
        return [];
      }

      return this.buildTopbarCountdownItems(
        this.finalExamTopbarState.timers,
        'متبقي على الإغلاق:',
        this.finalExamTopbarState.branchLabel,
        this.finalExamTopbarState.closesAt,
      );
    },
    showFinalExamCountdown() {
      return this.finalExamCountdownItems.length > 0;
    },
    assessmentCountdownItems() {
      if (!['courses', 'tasks'].includes(this.activeMenu)) {
        return [];
      }

      return this.buildTopbarCountdownItems(
        this.assessmentTopbarState.timers,
        'متبقي على الإغلاق:',
        '',
        this.assessmentTopbarState.closesAt,
      );
    },
    showAssessmentCountdown() {
      return this.assessmentCountdownItems.length > 0;
    },
    showTopbarCountdown() {
      return this.showAssessmentCountdown || this.showFinalExamCountdown;
    },
    topbarCountdownItems() {
      return this.activeMenu === 'finalexam'
        ? this.finalExamCountdownItems
        : this.assessmentCountdownItems;
    },
    showAssessmentTopbarAction() {
      return ['courses', 'tasks'].includes(this.activeMenu) && this.assessmentTopbarState.visible;
    },
  },
  watch: {
    managedBranchId: {
      immediate: true,
      handler(value) {
        if (value) {
          this.selectedOverviewBranch = value;
        }
      },
    },
    selectedTemplateCourse(course) {
      const templates = course?.assessmentNotificationTemplates || {};

      this.templateDraft = {
        pre: templates.pre || '',
        post: templates.post || '',
        tasks: templates.tasks || '',
      };
    },
    dashboardSnapshot: {
      immediate: true,
      handler() {
        this.ensureSatisfactionCourseSelection();
      },
    },
    dashboardIndicatorsSignature: {
      immediate: true,
      handler() {
        if (this.activeMenu !== 'overview') {
          return;
        }

        this.$nextTick(() => {
          this.restartIndicatorAnimation();
        });
      },
    },
    '$route.fullPath'() {
      this.syncDashboardStateFromRoute();
    },
    mobileMenuOpen(isOpen) {
      this.updateBodyScrollLock(isOpen);
    },
    activeMenu(menu) {
      if (menu === 'settings') {
        this.settingsMenuOpen = true;

        if (!this.selectedSettingsItemId || !this.settingsItems.some((item) => item.id === this.selectedSettingsItemId)) {
          this.selectedSettingsItemId = this.settingsItems[0]?.id || '';
        }
      }

      if (menu !== 'finalexam') {
        this.finalExamTopbarState = {
          branchCode: 'male',
          branchLabel: 'معلمين',
          isEnabled: false,
          closesAt: null,
          timers: [],
        };
      }

      if (!['courses', 'tasks'].includes(menu)) {
        this.assessmentTopbarState = {
          visible: false,
          label: '',
          type: '',
          isEnabled: false,
          closesAt: null,
          timers: [],
        };
      }

      if (!this.dashboardMenu.some((item) => item.id === menu)) {
        this.activeMenu = this.dashboardMenu[0]?.id || 'overview';
        return;
      }

      if (menu === 'overview') {
        this.$nextTick(() => {
          this.restartIndicatorAnimation();
        });
      }

      this.syncDashboardRouteQuery(menu);
    },
    selectedSettingsItemId() {
      if (this.activeMenu === 'settings') {
        this.syncDashboardRouteQuery('settings');
      }
    },
  },
  created() {
    this.dashboardClockIntervalId = window.setInterval(() => {
      this.currentTimestamp = Date.now();
    }, 1000);

    this.initializeDashboard();
  },
  mounted() {
    this.restartIndicatorAnimation();
    this.updateBodyScrollLock(this.mobileMenuOpen);
  },
  beforeDestroy() {
    if (this.dashboardClockIntervalId) {
      window.clearInterval(this.dashboardClockIntervalId);
      this.dashboardClockIntervalId = null;
    }

    this.updateBodyScrollLock(false);
  },
  methods: {
    ...mapActions([
      'loadDashboardSnapshot',
      'fetchDashboardAccounts',
      'createDashboardAccount',
      'deleteDashboardAccount',
      'updateCourse',
      'addSatisfactionQuestion',
      'deleteSatisfactionQuestions',
    ]),
    updateBodyScrollLock(locked) {
      if (typeof document === 'undefined' || typeof window === 'undefined') {
        return;
      }

      const shouldLock = Boolean(locked && window.innerWidth <= 1024);
      const bodyStyle = document.body?.style;
      const htmlStyle = document.documentElement?.style;

      if (!bodyStyle || !htmlStyle) {
        return;
      }

      bodyStyle.overflow = shouldLock ? 'hidden' : '';
      htmlStyle.overflow = shouldLock ? 'hidden' : '';
    },
    formatCountdownLabel(durationMs) {
      const totalSeconds = Math.max(0, Math.floor(durationMs / 1000));
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;

      if (hours > 0) {
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
      }

      return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    },
    buildTopbarCountdownItems(timers, defaultText, fallbackBranchLabel = '', fallbackClosesAt = null) {
      const normalizedTimers = Array.isArray(timers) && timers.length
        ? timers
        : (fallbackClosesAt
          ? [{ branchCode: '', branchLabel: fallbackBranchLabel, closesAt: fallbackClosesAt }]
          : []);

      return normalizedTimers.reduce((items, timer) => {
        const parsed = new Date(timer?.closesAt || '');

        if (Number.isNaN(parsed.getTime())) {
          return items;
        }

        const durationMs = Math.max(0, parsed.getTime() - this.currentTimestamp);

        if (durationMs <= 0) {
          return items;
        }

        items.push({
          branchCode: timer?.branchCode || '',
          branchLabel: timer?.branchLabel || '',
          text: timer?.branchLabel
            ? `${defaultText} ${timer.branchLabel}`
            : defaultText,
          label: this.formatCountdownLabel(durationMs),
          closesAt: timer?.closesAt || null,
        });

        return items;
      }, []);
    },
    isMenuItemActive(item) {
      if (item.id === 'settings') {
        return this.settingsMenuOpen || this.activeMenu === 'settings';
      }

      return this.activeMenu === item.id;
    },
    getSatisfactionQuestionKey(question) {
      return `${question.prompt}::${question.type}`;
    },
    async openSettingsItem(itemId) {
      if (!this.settingsItems.some((item) => item.id === itemId)) {
        return;
      }

      this.selectedSettingsItemId = itemId;
      this.settingsMenuOpen = true;
      await this.openPanel('settings');
    },
    ensureSatisfactionCourseSelection() {
      const [firstCourse] = this.satisfactionCourseOptions;

      if (!this.selectedSatisfactionCourseId || !this.satisfactionCourseOptions.some((course) => course.value === this.selectedSatisfactionCourseId)) {
        this.selectedSatisfactionCourseId = firstCourse?.value || '';
      }

      if (!this.selectedSatisfactionDeleteKey || !this.satisfactionQuestionOptions.some((question) => question.value === this.selectedSatisfactionDeleteKey)) {
        this.selectedSatisfactionDeleteKey = this.satisfactionQuestionOptions[0]?.value || '';
      }
    },
    openSatisfactionAddDialog() {
      this.satisfactionQuestionDraft = {
        prompt: '',
        type: 'rating',
        isRequired: true,
      };
      this.satisfactionAddDialogOpen = true;
    },
    closeSatisfactionAddDialog() {
      this.satisfactionAddDialogOpen = false;
      this.satisfactionSubmitting = false;
    },
    openSatisfactionDeleteDialog() {
      this.ensureSatisfactionCourseSelection();
      this.satisfactionDeleteDialogOpen = true;
    },
    closeSatisfactionDeleteDialog() {
      this.satisfactionDeleteDialogOpen = false;
      this.satisfactionDeleting = false;
    },
    async submitSatisfactionQuestion() {
      if (!this.satisfactionQuestionDraft.prompt) {
        this.$toast.error('أدخل نص السؤال أولًا');
        return;
      }

      this.satisfactionSubmitting = true;

      try {
        await this.addSatisfactionQuestion({
          prompt: this.satisfactionQuestionDraft.prompt,
          type: this.satisfactionQuestionDraft.type,
          isRequired: this.satisfactionQuestionDraft.isRequired,
        });
        this.$toast.success('تمت إضافة سؤال الاستبيان');
        this.closeSatisfactionAddDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر إضافة سؤال الاستبيان');
      } finally {
        this.satisfactionSubmitting = false;
      }
    },
    async confirmDeleteSatisfactionQuestion() {
      const questionIds = this.selectedSatisfactionDeleteQuestions.map((question) => question.id);

      if (!questionIds.length) {
        this.$toast.error('اختر سؤالًا صالحًا للحذف');
        return;
      }

      this.satisfactionDeleting = true;

      try {
        await this.deleteSatisfactionQuestions(questionIds);
        this.$toast.success('تم حذف سؤال الاستبيان');
        this.closeSatisfactionDeleteDialog();
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حذف سؤال الاستبيان');
      } finally {
        this.satisfactionDeleting = false;
      }
    },
    openUsersCreateDialog() {
      if (this.activeMenu !== 'users') {
        return;
      }

      this.$refs.workspacePanel?.openCreateDialog?.();
    },
    openUsersEditDialog() {
      if (this.activeMenu !== 'users') {
        return;
      }

      this.$refs.workspacePanel?.openEditDialog?.();
    },
    openArchiveWorkspaceCreateDialog() {
      if (!(this.activeMenu === 'settings' && this.selectedSettingsItemId === 'archive')) {
        return;
      }

      this.$refs.workspacePanel?.openCreateDialog?.();
    },
    openArchiveWorkspaceArchiveAllDialog() {
      if (!(this.activeMenu === 'settings' && this.selectedSettingsItemId === 'archive')) {
        return;
      }

      this.$refs.workspacePanel?.openArchiveAllDialog?.();
    },
    openMaterialsWorkspaceCreateDialog() {
      if (this.activeMenu !== 'materials') {
        return;
      }

      this.$refs.workspacePanel?.openCreateDialog?.();
    },
    copyRegistrationWorkspaceLink() {
      if (!(this.activeMenu === 'settings' && this.selectedSettingsItemId === 'registration')) {
        return;
      }

      this.$refs.workspacePanel?.copyRegistrationLink?.();
    },
    openRegistrationWorkspaceFieldsDialog() {
      if (!(this.activeMenu === 'settings' && this.selectedSettingsItemId === 'registration')) {
        return;
      }

      this.$refs.workspacePanel?.openFieldsDialog?.();
    },
    toggleRegistrationWorkspaceState() {
      if (!(this.activeMenu === 'settings' && this.selectedSettingsItemId === 'registration')) {
        return;
      }

      this.$refs.workspacePanel?.toggleRegistration?.();
    },
    togglePermissionsWorkspaceSection() {
      if (!(this.activeMenu === 'settings' && this.selectedSettingsItemId === 'permissions')) {
        return;
      }

      this.$refs.workspacePanel?.togglePermissionsWorkspaceSection?.();
    },
    async handleSettingsDialogItem(itemId) {
      if (itemId === 'links') {
        this.openLinksDialog();
        return;
      }

      if (itemId === 'supervision') {
        await this.openAdminsDialog();
        return;
      }

      if (itemId === 'templates') {
        this.openTemplatesDialog();
      }
    },
    openSatisfactionWorkspaceAddDialog() {
      if (this.activeMenu !== 'satisfaction') {
        return;
      }

      this.$refs.workspacePanel?.openAddDialog?.();
    },
    openSatisfactionWorkspaceDeleteDialog() {
      if (this.activeMenu !== 'satisfaction') {
        return;
      }

      this.$refs.workspacePanel?.openDeleteDialog?.();
    },
    openFinalExamActivationDialog() {
      if (this.activeMenu !== 'finalexam') {
        return;
      }

      this.$refs.workspacePanel?.openActivationDialog?.();
    },
    triggerFinalExamCopy() {
      if (this.activeMenu !== 'finalexam') {
        return;
      }

      this.$refs.workspacePanel?.triggerCopyQuestions?.();
    },
    triggerAssessmentTopbarAction() {
      if (!['courses', 'tasks'].includes(this.activeMenu)) {
        return;
      }

      this.$refs.workspacePanel?.handleCurrentAssessmentAvailabilityAction?.();
    },
    handleAssessmentTopbarState(payload) {
      this.assessmentTopbarState = {
        visible: !!payload?.visible,
        label: payload?.label || '',
        type: payload?.type || '',
        isEnabled: !!payload?.isEnabled,
        closesAt: payload?.closesAt || null,
        timers: Array.isArray(payload?.timers) ? payload.timers : [],
      };
    },
    handleFinalExamTopbarState(payload) {
      this.finalExamTopbarState = {
        branchCode: payload?.branchCode || 'male',
        branchLabel: payload?.branchLabel || 'معلمين',
        isEnabled: !!payload?.isEnabled,
        closesAt: payload?.closesAt || null,
        timers: Array.isArray(payload?.timers) ? payload.timers : [],
      };
    },
    handleRegistrationTopbarState(payload) {
      this.registrationTopbarState = {
        isOpen: !!payload?.isOpen,
        loading: !!payload?.loading,
      };
    },
    handlePermissionsTopbarState(payload) {
      this.permissionsTopbarState = {
        isAdmin: !!payload?.isAdmin,
        activeSection: payload?.activeSection || 'permissions',
      };
    },
    async initializeDashboard() {
      this.panelLoading = true;

      try {
        await this.loadDashboardSnapshot();
        this.syncDashboardStateFromRoute();
      } finally {
        this.panelLoading = false;
      }
    },
    normalizeDashboardPanel(panel) {
      const value = typeof panel === 'string' ? panel.trim().toLowerCase() : '';

      return this.dashboardMenu.some((item) => item.id === value) ? value : '';
    },
    normalizeDashboardAssessmentType(type) {
      return ['pre', 'post'].includes(type) ? type : 'pre';
    },
    hasSameRouteQuery(nextQuery) {
      const currentQuery = this.$route.query || {};
      const currentKeys = Object.keys(currentQuery).sort();
      const nextKeys = Object.keys(nextQuery).sort();

      if (currentKeys.length !== nextKeys.length) {
        return false;
      }

      return currentKeys.every((key, index) => key === nextKeys[index] && String(currentQuery[key]) === String(nextQuery[key]));
    },
    syncDashboardStateFromRoute() {
      if (this.$route.name !== 'dashboard') {
        return;
      }

      const routePanel = this.routeWorkspacePanel;

      if (routePanel && this.activeMenu !== routePanel) {
        this.activeMenu = routePanel;
      }

      if (routePanel === 'settings' && this.routeSettingsItemId && this.settingsItems.some((item) => item.id === this.routeSettingsItemId)) {
        this.selectedSettingsItemId = this.routeSettingsItemId;
        this.settingsMenuOpen = true;
      }
    },
    syncDashboardRouteQuery(panel = this.activeMenu) {
      if (this.$route.name !== 'dashboard') {
        return;
      }

      const nextQuery = {};

      if (panel && panel !== 'overview') {
        nextQuery.panel = panel;
      }

      if (panel === 'courses' && this.routeWorkspaceAssessmentType === 'post') {
        nextQuery.assessmentType = 'post';
      }

      if (panel === 'courses' && this.routeWorkspaceCourseId) {
        nextQuery.courseId = this.routeWorkspaceCourseId;
      }

      if (panel === 'settings' && this.selectedSettingsItemId) {
        nextQuery.settingsItem = this.selectedSettingsItemId;
      }

      if (this.hasSameRouteQuery(nextQuery)) {
        return;
      }

      this.$router.replace({
        name: 'dashboard',
        query: nextQuery,
      }).catch(() => {});
    },
    async openPanel(panel) {
      if (this.panelLoading) {
        return;
      }

      if (!['courses', 'tasks'].includes(panel)) {
        this.assessmentTopbarState = {
          visible: false,
          label: '',
          type: '',
          isEnabled: false,
          closesAt: null,
          timers: [],
        };
      }

      this.mobileMenuOpen = false;

      if (this.activeMenu === panel && this.dashboardSnapshot) {
        return;
      }

      this.panelLoading = true;

      try {
        await this.loadDashboardSnapshot();

        if (!this.dashboardError) {
          this.activeMenu = panel;
        }
      } finally {
        this.panelLoading = false;
      }
    },
    scrollToSection(targetId) {
      this.$nextTick(() => {
        const target = document.getElementById(targetId);

        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    },
    async copyDirectAccessLink(item) {
      if (!item?.url) {
        return;
      }

      try {
        if (navigator?.clipboard?.writeText) {
          await navigator.clipboard.writeText(item.url);
        }

        this.$toast.success('تم نسخ الرابط');
      } catch (error) {
        this.$toast.error(error?.message || 'تعذر نسخ الرابط');
      }
    },
    async handleOverviewAction(action) {
      if (this.panelLoading || this.overviewDialogLoading) {
        return;
      }

      if (action.id === 'links') {
        this.openLinksDialog();
        return;
      }

      if (action.id === 'supervision') {
        await this.openAdminsDialog();
        return;
      }

      if (action.id === 'templates') {
        this.openTemplatesDialog();
      }
    },
    async handleMenu(item) {
      this.mobileMenuOpen = false;

      if (item.id === 'settings') {
        this.settingsMenuOpen = !this.settingsMenuOpen;

        if (this.settingsMenuOpen) {
          this.assessmentTopbarState = {
            visible: false,
            label: '',
            type: '',
            isEnabled: false,
            closesAt: null,
            timers: [],
          };
        }

        return;
      }

      if (item.id !== 'settings') {
        this.settingsMenuOpen = false;
      }

      if (item.id === 'backup') {
        this.backupDialogOpen = true;
        return;
      }

      if (item.mode === 'page' && item.target) {
        this.$router.push({ name: item.target });
        return;
      }

      if (item.mode === 'panel') {
        await this.openPanel(item.id);
        return;
      }

      this.activeMenu = 'overview';
      this.scrollToSection(item.target);
    },
    indicatorStyle(progress) {
      const normalized = Math.round(this.animatedIndicatorPercent(progress));
      const radius = 78;
      const circumference = 2 * Math.PI * radius;
      const filled = normalized === 100 ? circumference : (circumference * normalized) / 100;

      return {
        '--indicator-dasharray': `${filled} ${circumference}`,
      };
    },
    hasPermission(key) {
      if (this.isAdmin) {
        return true;
      }

      return this.managerPermissions?.[key] === true;
    },
    canAccessPanel(panelId) {
      if (this.isAdmin) {
        return true;
      }

      switch (panelId) {
        case 'overview':
          return true;
        case 'attendance':
        case 'results':
          return this.hasPermission('page_results');
        case 'courses':
          return this.hasPermission('edit_pre_questions')
            || this.hasPermission('open_pre_exam')
            || this.hasPermission('edit_post_questions')
            || this.hasPermission('open_post_exam');
        case 'tasks':
          return this.hasPermission('edit_tasks');
        case 'settings':
          return this.settingsItems.length > 0;
        case 'users':
          return this.hasPermission('add_student')
            || this.hasPermission('delete_student')
            || this.hasPermission('edit_student')
            || this.hasPermission('add_reciter')
            || this.hasPermission('delete_reciter')
            || this.hasPermission('edit_reciter')
            || this.hasPermission('transfer_reciter_student');
        case 'notifications':
        case 'materials':
          return this.hasPermission('page_notifications') || this.hasPermission('page_activity_log');
        case 'backup':
          return this.hasPermission('backup_export') || this.hasPermission('backup_import') || this.hasPermission('backup_restore');
        case 'registration':
          return this.hasPermission('add_student')
            || this.hasPermission('delete_student')
            || this.hasPermission('edit_student');
        case 'finalexam':
        case 'permissions':
        case 'satisfaction':
        default:
          return false;
      }
    },
    canAccessOverviewAction(actionId) {
      if (this.isAdmin) {
        return true;
      }

      return actionId === 'links';
    },
    roleLabel(role) {
      return {
        admin: 'مدير عام',
        male_manager: 'مشرف',
        female_manager: 'مشرفة',
      }[role] || role;
    },
    ensureDialogCourseSelection() {
      const [firstCourse] = this.courseDialogOptions;

      if (!this.selectedTemplateCourseId || !this.courseDialogOptions.some((option) => option.value === this.selectedTemplateCourseId)) {
        this.selectedTemplateCourseId = firstCourse?.value || '';
      }
    },
    openLinksDialog() {
      this.linksDialogOpen = true;
    },
    async openAdminsDialog() {
      this.adminError = '';
      this.adminsLoading = true;

      try {
        this.dashboardAccounts = await this.fetchDashboardAccounts();
        this.adminsDialogOpen = true;
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر تحميل الحسابات الإشرافية');
      } finally {
        this.adminsLoading = false;
      }
    },
    openTemplatesDialog() {
      this.ensureDialogCourseSelection();
      this.templatesDialogOpen = true;
    },
    async saveNotificationTemplates() {
      if (!this.selectedTemplateCourseId) {
        return;
      }

      this.templatesSubmitting = true;

      try {
        await this.updateCourse({
          courseId: this.selectedTemplateCourseId,
          updates: {
            assessmentNotificationTemplates: {
              pre: this.templateDraft.pre || '',
              post: this.templateDraft.post || '',
              tasks: this.templateDraft.tasks || '',
            },
          },
        });
        this.$toast.success('تم حفظ القوالب');
        this.templatesDialogOpen = false;
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حفظ القوالب');
      } finally {
        this.templatesSubmitting = false;
      }
    },
    resetAdminDialog() {
      this.adminError = '';
      this.adminForm = {
        role: 'male_manager',
        name: '',
        loginCode: '',
      };
      this.adminsDialogOpen = false;
    },
    async submitDashboardAccount() {
      this.adminError = '';

      if (!this.adminForm.name || !this.adminForm.loginCode) {
        this.adminError = 'أدخل الاسم ورقم الدخول.';
        return;
      }

      this.adminSubmitting = true;

      try {
        const created = await this.createDashboardAccount({ ...this.adminForm });
        this.dashboardAccounts = [...this.dashboardAccounts, created];
        this.$toast.success('تمت إضافة الحساب الإشرافي');
        this.adminForm = {
          role: 'male_manager',
          name: '',
          loginCode: '',
        };
      } catch (error) {
        this.adminError = error?.response?.data?.message || 'تعذر إضافة الحساب الإشرافي';
      } finally {
        this.adminSubmitting = false;
      }
    },
    async removeDashboardAccount(accountId) {
      this.adminDeletingId = accountId;

      try {
        await this.deleteDashboardAccount(accountId);
        this.dashboardAccounts = this.dashboardAccounts.filter((account) => account.id !== accountId);
        this.$toast.success('تم حذف الحساب');
      } catch (error) {
        this.$toast.error(error?.response?.data?.message || 'تعذر حذف الحساب');
      } finally {
        this.adminDeletingId = '';
      }
    },
  },
};
</script>

<style scoped>
.dashboard-page {
  min-height: 100vh;
  direction: rtl;
  background: linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
  color: #08384a;
}

.dashboard-sidebar {
  position: fixed;
  top: 0;
  right: 0;
  z-index: 20;
  display: flex;
  flex-direction: column;
  width: 336px;
  height: 100vh;
  padding: 22px 22px 28px;
  border-left: 1px solid rgba(214, 229, 233, 0.88);
  background: #fff;
  box-shadow: 10px 0 35px rgba(15, 23, 42, 0.03);
  overflow-y: auto;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-y;
}

.dashboard-sidebar__brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 6px 20px;
  color: inherit;
  text-decoration: none;
}

.dashboard-sidebar__logo {
  width: auto;
  height: 40px;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(31%) sepia(53%) saturate(1050%) hue-rotate(154deg) brightness(91%) contrast(94%) drop-shadow(0 8px 18px rgba(8, 65, 89, 0.12));
}

.dashboard-sidebar__title {
  color: #0f172a;
  font-size: 1.24rem;
  font-weight: 900;
}

.dashboard-nav {
  display: grid;
  gap: 6px;
  margin-top: 10px;
}

.dashboard-nav__entry {
  display: grid;
  gap: 8px;
}

.dashboard-nav__divider {
  height: 1px;
  margin: 12px 18px;
  background: rgba(210, 225, 230, 0.9);
}

.dashboard-nav__item {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  width: 100%;
  padding: 14px 28px 14px 54px;
  border: 0;
  border-radius: 18px;
  background: transparent;
  color: #08384a;
  text-align: right;
  cursor: pointer;
  transition: 0.2s ease;
}

.dashboard-nav__item:disabled {
  cursor: wait;
  opacity: 0.72;
}

.dashboard-nav__item:hover,
.dashboard-nav__item--active {
  background: rgba(16, 118, 153, 0.06);
}

.dashboard-nav__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  color: #107699;
  flex: 0 0 24px;
}

.dashboard-nav__icon :deep(.v-icon) {
  font-size: 22px !important;
  color: inherit !important;
}

.dashboard-nav__item--active .dashboard-nav__icon {
  color: #0b6f8c;
}

.dashboard-nav__copy {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  flex: 1 1 auto;
  gap: 16px;
  width: 100%;
}

.dashboard-nav__label {
  flex: 1;
  font-size: 1.05rem;
  font-weight: 500;
  text-align: right;
}

.dashboard-nav__chevron {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  color: #6b7f90;
  transition: transform 0.2s ease, color 0.2s ease;
}

.dashboard-nav__chevron--open {
  transform: translateY(-50%) rotate(180deg);
  color: #0b6f8c;
}

.dashboard-subnav {
  display: grid;
  gap: 6px;
  padding: 0 0 4px 0;
}

.dashboard-subnav__item {
  width: 100%;
  border: 0;
  border-radius: 16px;
  padding: 11px 28px 11px 54px;
  background: transparent;
  color: #5d7285;
  text-align: right;
  font-size: 0.94rem;
  font-weight: 800;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.dashboard-subnav__item:hover,
.dashboard-subnav__item--active {
  background: rgba(16, 118, 153, 0.08);
  color: #0f3554;
}

.dashboard-nav__dot {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #cfe3e8;
}

.dashboard-main {
  min-height: 100vh;
  margin-right: 336px;
  padding: 24px 28px 36px;
  min-width: 0;
}

.dashboard-topbar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 16px;
  margin-bottom: 24px;
  padding: 18px 20px;
  border-radius: 32px;
  border: 1px solid rgba(255, 255, 255, 0.7);
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.06);
  backdrop-filter: blur(12px);
}

.dashboard-topbar__mobile {
  display: none;
}

.dashboard-topbar__label {
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 700;
}

.dashboard-topbar__welcome {
  margin-inline-start: 0;
  margin-inline-end: auto;
  text-align: right;
}

.dashboard-topbar__actions {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  margin-inline-start: 0;
  margin-inline-end: 0;
}

.dashboard-topbar__timer-list {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.dashboard-topbar__timer {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 16px;
  border-radius: 16px;
  background: rgba(34, 197, 94, 0.12);
  color: #15803d;
  font-size: 0.96rem;
  font-weight: 900;
  white-space: nowrap;
}

.dashboard-topbar__timer-text {
  color: #15803d;
}

.dashboard-topbar__timer-label {
  direction: ltr;
  font-variant-numeric: tabular-nums;
}

.dashboard-topbar__actions--overview {
  flex-wrap: wrap;
  justify-content: flex-start;
  margin-inline-start: 24px;
}

.dashboard-topbar__action {
  min-width: 112px;
  white-space: nowrap;
}

.dashboard-topbar__action--choice {
  min-width: 132px;
}

.dashboard-topbar__action:disabled {
  opacity: 0.6;
}

.dashboard-topbar__action--ghost {
  min-width: 112px;
}

.dashboard-topbar__action--add {
  min-width: 112px;
}

.dashboard-topbar__action-icon {
  font-size: 1rem !important;
}

.dashboard-topbar__action-label {
  line-height: 1;
}

.dashboard-topbar__name {
  margin-top: 4px;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.dashboard-icon-button {
  width: 42px;
  height: 42px;
}

.dashboard-error {
  margin-bottom: 16px;
  padding: 14px 18px;
  border-radius: 20px;
  border: 1px solid rgba(220, 38, 38, 0.2);
  background: rgba(220, 38, 38, 0.05);
  color: #b91c1c;
  font-size: 0.92rem;
  font-weight: 600;
}

.dashboard-section {
  margin-bottom: 24px;
}

.dashboard-card {
  border-radius: 28px;
  border: 1px solid rgba(255, 255, 255, 0.8);
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  padding: 24px;
}

.dashboard-card--header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 22px;
}

.dashboard-card__title {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.dashboard-card--overview-header {
  display: grid;
  gap: 18px;
  padding: 24px 28px;
  direction: rtl;
}

.dashboard-overview-header__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
}

.dashboard-card__title--overview {
  font-size: 1.55rem;
}

.dashboard-overview-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 16px;
  direction: rtl;
}

.dashboard-overview-action {
  min-height: 54px;
  padding: 0 24px;
  flex-direction: row-reverse;
}

.dashboard-overview-filter {
  width: min(180px, 100%);
}

.dashboard-overview-filter__select :deep(.v-input__slot) {
  min-height: 46px !important;
  border-radius: 18px !important;
  box-shadow: none !important;
  direction: rtl;
  cursor: pointer;
}

.dashboard-overview-filter__select :deep(input),
.dashboard-overview-filter__select :deep(.v-select__selection),
.dashboard-overview-filter__select :deep(.v-select__selections) {
  text-align: right;
  justify-content: flex-end;
  cursor: pointer !important;
}

.dashboard-overview-filter__select :deep(input) {
  pointer-events: none;
  user-select: none;
  caret-color: transparent;
}

.dashboard-overview-filter__select :deep(.v-input__append-inner) {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 0 !important;
  margin-right: auto !important;
  margin-left: 0 !important;
  cursor: pointer;
}

.dashboard-overview-filter__select :deep(.v-input__icon--append .v-icon) {
  color: #1f6f96 !important;
  font-size: 22px !important;
  opacity: 1 !important;
  display: none !important;
}

.dashboard-satisfaction-shell {
  display: grid;
  gap: 22px;
}

.dashboard-satisfaction-shell__heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.dashboard-satisfaction-shell__hint {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 0.92rem;
  font-weight: 600;
  line-height: 1.8;
}

.dashboard-satisfaction-shell__actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.dashboard-satisfaction-action {
  border: 1px solid rgba(16, 118, 153, 0.18) !important;
  background: #fff !important;
  color: #107699 !important;
}

.dashboard-satisfaction-action--danger {
  border-color: rgba(220, 38, 38, 0.18) !important;
  color: #dc2626 !important;
}

.dashboard-satisfaction-toolbar {
  display: flex;
  justify-content: flex-start;
}

.dashboard-satisfaction-toolbar__select {
  width: min(320px, 100%);
}

.dashboard-satisfaction-questions {
  display: grid;
  gap: 14px;
}

.dashboard-satisfaction-question {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px 20px;
  border: 1px solid rgba(16, 118, 153, 0.1);
  border-radius: 24px;
  background: #f6fbfd;
}

.dashboard-satisfaction-question__title {
  color: #0f172a;
  font-size: 1rem;
  font-weight: 800;
}

.dashboard-satisfaction-question__meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.dashboard-satisfaction-badge {
  display: inline-flex;
  align-items: center;
  min-height: 34px;
  padding: 0 14px;
  border-radius: 999px;
  background: rgba(16, 118, 153, 0.08);
  color: #0b6f8c;
  font-size: 0.84rem;
  font-weight: 800;
}

.dashboard-satisfaction-badge--required {
  background: rgba(220, 38, 38, 0.08);
  color: #dc2626;
}

.dashboard-satisfaction-metrics {
  display: grid;
  gap: 18px;
}

.dashboard-satisfaction-metrics__title {
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.dashboard-satisfaction-indicators {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
}

.dashboard-satisfaction-indicators__meta {
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 700;
}

.dashboard-satisfaction-dialog__note {
  margin-bottom: 14px;
  color: #64748b;
  font-size: 0.92rem;
  font-weight: 600;
  line-height: 1.8;
}

.dashboard-satisfaction-dialog__switch {
  margin-top: -6px;
}

.dashboard-card--indicators {
  padding: 42px 34px 28px;
}

.dashboard-indicators-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 42px 34px;
}

.dashboard-indicator-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 22px;
  text-align: center;
}

.dashboard-indicator-ring {
  position: relative;
  width: 214px;
  height: 214px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  filter: drop-shadow(0 18px 28px rgba(149, 185, 198, 0.24));
}

.dashboard-indicator-ring__svg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.dashboard-indicator-ring__track,
.dashboard-indicator-ring__progress {
  fill: none;
  stroke-width: 28;
}

.dashboard-indicator-ring__track {
  stroke: #d9e8f0;
}

.dashboard-indicator-ring__progress {
  stroke-linecap: round;
  stroke-dasharray: var(--indicator-dasharray);
}

.dashboard-indicator-ring--empty .dashboard-indicator-ring__progress {
  opacity: 0;
}

.dashboard-indicator-ring__inner {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 68%;
  height: 68%;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.98) 0%, rgba(242, 249, 252, 0.94) 62%, rgba(231, 242, 247, 0.7) 100%);
  box-shadow: inset 0 2px 14px rgba(255, 255, 255, 0.95);
  color: #0f5670;
  font-size: 2.6rem;
  font-weight: 900;
  letter-spacing: -0.04em;
}

.dashboard-indicator-card__label {
  max-width: 11rem;
  font-weight: 900;
  line-height: 1.55;
  color: #08384a;
  font-size: 1.06rem;
}

.dashboard-section__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.dashboard-section__heading h2 {
  margin: 0;
  color: #0f172a;
  font-size: 1.25rem;
  font-weight: 900;
}

.dashboard-links-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.dashboard-links-dialog-grid {
  display: grid;
  gap: 14px;
}

.dashboard-links-dialog-card {
  border: 1px solid rgba(20, 109, 136, 0.14);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.96);
  padding: 22px 22px 20px;
}

.dashboard-links-dialog-card__title {
  color: #0f3554;
  font-size: 1.15rem;
  font-weight: 900;
}

.dashboard-links-dialog-card__url {
  margin-top: 10px;
  color: #5b7b8c;
  font-size: 0.98rem;
  line-height: 1.8;
  word-break: break-word;
}

.dashboard-links-dialog-card__copy {
  min-height: 44px;
  margin-top: 16px;
  font-size: 0.96rem;
  font-weight: 900;
}

.dashboard-link-card {
  display: block;
  padding: 18px;
  border-radius: 22px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  background: rgba(255, 255, 255, 0.82);
  text-align: right;
  text-decoration: none;
  cursor: pointer;
  transition: 0.18s ease;
}

.dashboard-link-card:disabled {
  cursor: wait;
  opacity: 0.72;
}

.dashboard-link-card:hover {
  transform: translateY(-2px);
  border-color: rgba(15, 118, 110, 0.26);
  box-shadow: 0 18px 34px -26px rgba(15, 23, 42, 0.45);
}

.dashboard-link-card__icon {
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 16px;
  background: linear-gradient(135deg, #107699, #084159);
  color: #fff;
}

.dashboard-link-card__title {
  margin-top: 16px;
  color: #0f172a;
  font-weight: 900;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.35fr) minmax(300px, 0.8fr);
  gap: 18px;
}

.dashboard-workspace-panel {
  border-radius: 28px;
  border: 1px solid rgba(255, 255, 255, 0.8);
  background: rgba(255, 255, 255, 0.95);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  overflow: hidden;
}

.dashboard-workspace-panel--assessment {
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  overflow: visible;
}

.dashboard-workspace-panel--communications {
  border: 0;
  background: transparent;
  box-shadow: none;
  overflow: visible;
}

.dashboard-workspace-panel--registration {
  border: 0;
  background: transparent;
  box-shadow: none;
  overflow: visible;
}

.dashboard-loader-shell {
  display: grid;
  place-items: center;
  min-height: 540px;
  padding: 40px 24px;
  border-radius: 28px;
  border: 1px solid rgba(255, 255, 255, 0.8);
  background:
    radial-gradient(circle at top, rgba(87, 197, 221, 0.16), transparent 32%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(241, 248, 250, 0.98) 100%);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
}

.dashboard-loader-shell__inner {
  display: grid;
  justify-items: center;
  gap: 16px;
  text-align: center;
}

.dashboard-loader-shell__title {
  color: #0a4c61;
  font-size: 1.08rem;
  font-weight: 900;
}

.dashboard-loader-shell__text {
  color: #5b7380;
  font-size: 0.94rem;
  font-weight: 700;
}

.loader {
  width: 50px;
  aspect-ratio: 1;
  display: grid;
}

.loader::before,
.loader::after {
  content: '';
  grid-area: 1/1;
  --c: no-repeat radial-gradient(farthest-side, #0f7291 92%, #0000);
  background:
    var(--c) 50% 0,
    var(--c) 50% 100%,
    var(--c) 100% 50%,
    var(--c) 0 50%;
  background-size: 12px 12px;
  animation: dashboard-loader-spin 1s infinite;
}

.loader::before {
  margin: 4px;
  filter: hue-rotate(28deg) saturate(1.2);
  background-size: 8px 8px;
  animation-timing-function: linear;
}

@keyframes dashboard-loader-spin {
  100% {
    transform: rotate(.5turn);
  }
}

.dashboard-grid__main {
  display: grid;
  gap: 18px;
}

.dashboard-grid__side {
  display: grid;
  gap: 18px;
}

.dashboard-card--sticky {
  position: sticky;
  top: 24px;
}

.dashboard-empty-state {
  padding: 24px;
  border-radius: 22px;
  border: 1px dashed rgba(148, 163, 184, 0.42);
  background: rgba(255, 255, 255, 0.56);
  color: #64748b;
  text-align: center;
}

.dashboard-stack-list {
  display: grid;
  gap: 12px;
}

.dashboard-stack-item {
  padding: 16px;
  border-radius: 20px;
  border: 1px solid rgba(148, 163, 184, 0.16);
  background: rgba(248, 250, 252, 0.84);
}

.dashboard-stack-item__title {
  color: #0f172a;
  font-weight: 800;
}

.dashboard-stack-item__text {
  margin-top: 8px;
  color: #475569;
  line-height: 1.8;
}

.dashboard-stack-item__meta {
  margin-top: 8px;
  color: #64748b;
  font-size: 0.8rem;
}

.dashboard-stack-item__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.dashboard-status-badge {
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 800;
}

.dashboard-status-badge--success {
  background: rgba(16, 185, 129, 0.12);
  color: #047857;
}

.dashboard-status-badge--warning {
  background: rgba(249, 115, 22, 0.12);
  color: #c2410c;
}

.dashboard-permissions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.dashboard-dialog-card {
  border-radius: 28px !important;
}

.dashboard-dialog-card--panel {
  overflow: hidden;
}

.dashboard-course-manager {
  border-radius: 34px !important;
}

.dashboard-course-manager__header {
  border-bottom: 1px solid rgba(214, 229, 238, 0.95);
}

.dashboard-course-manager__header .dashboard-dialog-card__title {
  color: #0e3f59;
  font-size: 2rem;
}

.dashboard-course-manager__body {
  background: #fcfeff;
}

.dashboard-course-manager__toolbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 150px;
  gap: 18px;
  align-items: center;
  margin-bottom: 18px;
}

.dashboard-course-manager__submit {
  min-width: 132px;
}

.dashboard-course-manager__input :deep(.v-input__slot) {
  min-height: 58px !important;
  border-width: 2px !important;
  border-color: #1a86a5 !important;
  border-radius: 22px !important;
  box-shadow: 0 0 0 3px rgba(26, 134, 165, 0.12) !important;
}

.dashboard-course-manager__input :deep(input) {
  text-align: right;
  color: #0f3550;
  font-weight: 700;
}

.dashboard-course-manager__list-shell {
  padding: 14px;
  border: 1px solid #e3edf3;
  border-radius: 26px;
  background: #f7fbfd;
}

.dashboard-course-manager__list {
  display: grid;
  gap: 12px;
  max-height: 360px;
  overflow-y: auto;
  padding-inline-end: 4px;
}

.dashboard-course-row {
  display: flex;
  direction: ltr;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  padding: 14px 18px;
  border: 1px solid #e4edf3;
  border-radius: 22px;
  background: #fff;
}

.dashboard-course-row__title {
  flex: 1;
  color: #113d57;
  font-size: 1.05rem;
  font-weight: 800;
  text-align: right;
}

.dashboard-course-row__actions {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.dashboard-course-row__icon {
  width: auto;
  height: auto;
  padding: 0;
  box-shadow: none;
}

.dashboard-course-row__icon .app-action-icon {
  color: inherit;
  font-size: 18px;
}

.dashboard-course-row__icon :deep(.v-icon) {
  color: inherit !important;
  font-size: 22px !important;
  opacity: 1 !important;
  display: inline-flex !important;
}

.dashboard-course-row__icon:disabled {
  opacity: 0.5;
}

.dashboard-course-manager__footer {
  display: flex;
  justify-content: flex-start;
  border-top: 1px solid rgba(214, 229, 238, 0.95);
}

.dashboard-course-manager__cancel {
  min-height: 52px;
  padding: 0 28px !important;
  border-radius: 999px !important;
  border-color: #c6e1ea !important;
  color: #103f56 !important;
  font-size: 1rem !important;
  font-weight: 900 !important;
}

.dashboard-course-manager__submit--danger {
  background: #dc2626 !important;
}

.dashboard-dialog-card__text {
  margin: 0;
  color: #496579;
  font-size: 1rem;
  font-weight: 700;
  line-height: 2;
  text-align: right;
}

.dashboard-supervision-manager {
  border-radius: 34px !important;
}

.dashboard-supervision-manager__header {
  border-bottom: 1px solid rgba(214, 229, 238, 0.95);
}

.dashboard-supervision-manager__header .dashboard-dialog-card__title {
  color: #0e3f59;
  font-size: 2rem;
}

.dashboard-supervision-manager__body {
  background: #fcfeff;
}

.dashboard-supervision-manager__list-shell {
  margin-bottom: 18px;
  padding: 14px;
  border: 1px solid #e3edf3;
  border-radius: 26px;
  background: #f7fbfd;
}

.dashboard-admins-stack--supervision {
  gap: 12px;
}

.dashboard-admin-card--supervision {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 16px;
  padding: 12px 14px;
  border-radius: 22px;
  border: 1px solid #d9e8f0;
  background: #fff;
}

.dashboard-admin-card__content--supervision {
  text-align: right;
}

.dashboard-supervision-form {
  display: grid;
  gap: 18px;
}

.dashboard-supervision-field__label {
  display: block;
  margin: 0 0 10px;
  color: #4f6d83;
  font-size: 0.98rem;
  font-weight: 800;
}

.dashboard-supervision-field__input :deep(.v-input__slot) {
  min-height: 52px !important;
  border-radius: 18px !important;
  border-color: #cfe1eb !important;
  box-shadow: none !important;
}

.dashboard-supervision-field__input :deep(input),
.dashboard-supervision-field__input :deep(.v-select__selection),
.dashboard-supervision-field__input :deep(.v-select__selections) {
  text-align: right;
  justify-content: flex-end;
  color: #103f56;
  font-weight: 700;
}

.dashboard-supervision-field__input :deep(.v-input__append-inner) {
  margin-top: 0 !important;
  margin-right: auto !important;
  margin-left: 0 !important;
}

.dashboard-supervision-manager__footer {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 14px;
  border-top: 1px solid rgba(214, 229, 238, 0.95);
}

.dashboard-supervision-manager__submit {
  min-height: 52px !important;
  padding: 0 30px !important;
  border-radius: 999px !important;
  font-size: 1rem !important;
  font-weight: 900 !important;
  letter-spacing: 0 !important;
  box-shadow: none !important;
}

.dashboard-dialog-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.dashboard-dialog-card__header--panel {
  margin-bottom: 0;
  border-bottom: 1px solid rgba(226, 232, 240, 0.8);
}

.dashboard-dialog-card__eyebrow {
  color: #6b7e8f;
  font-size: 0.82rem;
  font-weight: 800;
}

.dashboard-dialog-card__title {
  margin: 6px 0 0;
  color: #0f172a;
  font-size: 1.3rem;
  font-weight: 900;
}

.dashboard-dialog-card__field {
  margin-bottom: 16px;
}

.dashboard-dialog-card__actions {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  margin-top: 20px;
}

.dashboard-admins-list {
  margin-bottom: 16px;
}

.dashboard-admins-stack {
  display: grid;
  gap: 10px;
}

.dashboard-admin-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid rgba(203, 213, 225, 0.65);
  border-radius: 18px;
  background: rgba(248, 250, 252, 0.8);
}

.dashboard-admin-card__name {
  color: #0f172a;
  font-weight: 800;
}

.dashboard-admin-card__meta {
  margin-top: 4px;
  color: #64748b;
  font-size: 0.82rem;
}

.dashboard-dialog-form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.dashboard-dialog-error {
  margin-top: 14px;
  color: #b91c1c;
  font-size: 0.88rem;
  font-weight: 700;
}

.dashboard-templates-manager {
  border-radius: 34px !important;
}

.dashboard-templates-manager__header {
  border-bottom: 1px solid rgba(214, 229, 238, 0.95);
}

.dashboard-templates-manager__header .dashboard-dialog-card__title {
  color: #0e3f59;
  font-size: 1.7rem;
}

.dashboard-templates-manager__body {
  display: grid;
  gap: 14px;
  background: #fcfeff;
}

.dashboard-templates-manager__footer {
  gap: 12px;
}

.dashboard-template-field {
  display: grid;
  gap: 10px;
}

.dashboard-template-field__input :deep(.v-input__slot) {
  min-height: 52px !important;
  border-radius: 18px !important;
  border-color: #cfe1eb !important;
  box-shadow: none !important;
}

.dashboard-template-field__input :deep(input),
.dashboard-template-field__input :deep(textarea),
.dashboard-template-field__input :deep(.v-select__selection),
.dashboard-template-field__input :deep(.v-select__selections) {
  text-align: right;
  justify-content: flex-end;
  color: #103f56;
  font-weight: 700;
}

.dashboard-template-field__input :deep(textarea) {
  line-height: 1.9;
}

.dashboard-template-field__input--textarea :deep(.v-input__slot) {
  padding-top: 10px !important;
}

.dashboard-template-grid {
  display: grid;
  gap: 18px;
}

.dashboard-embedded-dialog {
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
  border-radius: 28px;
}

.dashboard-permission-card {
  padding: 16px;
  border-radius: 22px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  background: rgba(248, 250, 252, 0.84);
}

.dashboard-permission-card__title {
  color: #0f172a;
  font-weight: 900;
  margin-bottom: 12px;
}

.dashboard-chip-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.dashboard-chip {
  display: inline-flex;
  align-items: center;
  padding: 7px 12px;
  border-radius: 999px;
  background: rgba(15, 118, 110, 0.08);
  color: #0f766e;
  font-size: 0.8rem;
  font-weight: 700;
}

.dashboard-card :deep(.upload-card) {
  min-height: 0;
  border: 0;
  background: transparent;
  box-shadow: none;
  padding: 0;
}

.dashboard-backdrop {
  position: fixed;
  inset: 0;
  z-index: 15;
  background: rgba(15, 23, 42, 0.24);
}

@media (max-width: 1200px) {
  .dashboard-indicators-grid,
  .dashboard-links-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 1024px) {
  .dashboard-sidebar {
    transform: translateX(100%);
    transition: transform 0.25s ease;
    width: min(336px, calc(100vw - 28px));
    max-width: calc(100vw - 28px);
  }

  .dashboard-sidebar--open {
    transform: translateX(0);
  }

  .dashboard-main {
    margin-right: 0;
    padding: 20px 16px 28px;
  }

  .dashboard-topbar__mobile {
    display: block;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-card--sticky {
    position: static;
  }
}

@media (max-width: 700px) {
  .dashboard-topbar,
  .dashboard-card--header,
  .dashboard-card--overview-header {
    border-radius: 24px;
  }

  .dashboard-topbar {
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    padding: 12px 14px;
    gap: 8px;
  }

  .dashboard-topbar__welcome {
    display: none;
  }

  .dashboard-topbar__actions,
  .dashboard-topbar__actions--overview {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    width: auto;
    gap: 8px;
    margin-inline: 0;
  }

  .dashboard-topbar__action,
  .dashboard-overview-action {
    width: auto;
    min-width: 0;
    min-height: 36px;
    padding: 0 12px !important;
    border-radius: 12px !important;
    font-size: 0.84rem !important;
  }

  .dashboard-topbar__timer-list {
    display: inline-flex;
    flex-wrap: wrap;
    width: auto;
    gap: 8px;
  }

  .dashboard-topbar__timer {
    min-height: 36px;
    padding: 0 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    white-space: nowrap;
  }

  .dashboard-topbar__timer-text {
    line-height: 1.2;
  }

  .dashboard-overview-actions {
    flex-direction: column;
  }

  .dashboard-topbar__actions--overview {
    justify-content: flex-start;
    margin-inline-start: 0;
  }

  .dashboard-topbar__mobile {
    display: inline-flex;
    align-items: center;
    margin-inline-end: 2px;
  }

  .dashboard-icon-button {
    width: 36px;
    height: 36px;
  }

  .dashboard-overview-header__top,
  .dashboard-dialog-form-grid {
    grid-template-columns: 1fr;
    display: grid;
  }

  .dashboard-course-manager__toolbar {
    grid-template-columns: 1fr;
  }

  .dashboard-course-row {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .dashboard-course-row__actions,
  .dashboard-course-manager__footer,
  .dashboard-supervision-manager__footer {
    justify-content: flex-end;
  }

  .dashboard-indicators-grid,
  .dashboard-links-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-links-dialog-card__copy {
    width: 100%;
  }

  .dashboard-indicator-ring {
    width: 186px;
    height: 186px;
  }

  .dashboard-indicator-ring__inner {
    font-size: 2.3rem;
  }
}

@media (max-width: 520px) {
  .dashboard-main {
    padding: 16px 12px 24px;
  }

  .dashboard-topbar {
    padding: 10px 12px;
    gap: 6px;
  }

  .dashboard-topbar__actions,
  .dashboard-topbar__actions--overview,
  .dashboard-topbar__timer-list {
    gap: 6px;
  }

  .dashboard-topbar__action,
  .dashboard-overview-action {
    min-height: 34px;
    padding: 0 10px !important;
    font-size: 0.78rem !important;
  }

  .dashboard-topbar__timer {
    min-height: 34px;
    padding: 0 9px;
    font-size: 0.76rem;
  }

  .dashboard-sidebar {
    width: calc(100vw - 18px);
    max-width: calc(100vw - 18px);
    padding: 16px 14px 22px;
  }

  .dashboard-sidebar__brand {
    padding-inline: 4px;
  }

  .dashboard-sidebar__title {
    font-size: 1.05rem;
  }

  .dashboard-nav__item,
  .dashboard-subnav__item {
    padding-right: 16px;
    padding-left: 42px;
  }

  .dashboard-nav__copy {
    gap: 10px;
  }

  .dashboard-nav__label {
    font-size: 0.96rem;
  }

  .dashboard-workspace-panel,
  .dashboard-loader-shell,
  .dashboard-card,
  .dashboard-dialog-card {
    border-radius: 20px !important;
  }

  .dashboard-links-dialog-card,
  .dashboard-admin-card,
  .dashboard-admin-card--supervision,
  .dashboard-course-row,
  .dashboard-satisfaction-question,
  .dashboard-link-card,
  .dashboard-stack-item,
  .dashboard-permission-card {
    border-radius: 18px;
  }

  .dashboard-indicator-ring {
    width: 164px;
    height: 164px;
  }

  .dashboard-indicator-ring__inner {
    font-size: 2rem;
  }

  .dashboard-dialog-card__actions,
  .dashboard-course-manager__footer,
  .dashboard-supervision-manager__footer {
    flex-direction: column-reverse;
    align-items: stretch;
  }

  .dashboard-dialog-card__actions > *,
  .dashboard-course-manager__footer > *,
  .dashboard-supervision-manager__footer > * {
    width: 100%;
  }
}
</style>
