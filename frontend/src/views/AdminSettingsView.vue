<template>
  <div
    class="settings-admin"
    :class="{ 'settings-admin--embedded': embedded }"
  >
    <v-container class="settings-admin__container py-8 py-md-10">
      <section class="settings-admin__shell">
        <aside
          v-if="!hideSidebar"
          class="settings-admin__sidebar"
        >
          <div class="settings-admin__sidebar-title">
            الإعدادات
          </div>

          <AppButton
            v-for="item in items"
            :key="item.id"
            variant="plain"
            class="settings-admin__nav-item"
            :class="{ 'settings-admin__nav-item--active': selectedItemId === item.id }"
            @click="handleItemClick(item)"
          >
            {{ item.label }}
          </AppButton>
        </aside>

        <section class="settings-admin__content">
          <template v-if="selectedItem && selectedPanelComponent">
            <div
              v-if="selectedItemActions.length"
              class="settings-admin__actions settings-admin__actions--panel"
            >
              <AppButton
                v-for="action in selectedItemActions"
                :key="action.id"
                :variant="action.variant"
                @click="runSelectedItemAction(action.id)"
              >
                {{ action.label }}
              </AppButton>
            </div>

            <div class="settings-admin__panel-shell">
              <component
                :is="selectedPanelComponent"
                ref="selectedPanel"
                v-bind="selectedPanelProps"
                @permissions-topbar-state="$emit('permissions-topbar-state', $event)"
                @registration-topbar-state="$emit('registration-topbar-state', $event)"
              />
            </div>
          </template>
        </section>
      </section>
    </v-container>
  </div>
</template>

<script>
import { AppButton } from '../components/ui';
import AdminArchiveView from './AdminArchiveView.vue';
import AdminHomePageSettingsView from './AdminHomePageSettingsView.vue';
import AdminPeopleView from './AdminPeopleView.vue';
import AdminPermissionsView from './AdminPermissionsView.vue';
import AdminRegistrationView from './AdminRegistrationView.vue';
import AdminTemplatesView from './AdminTemplatesView.vue';

export default {
  name: 'AdminSettingsView',
  components: {
    AppButton,
    AdminArchiveView,
    AdminHomePageSettingsView,
    AdminPeopleView,
    AdminPermissionsView,
    AdminRegistrationView,
    AdminTemplatesView,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
    activeItemId: {
      type: String,
      default: '',
    },
    hideSidebar: {
      type: Boolean,
      default: false,
    },
    items: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      selectedItemId: '',
    };
  },
  computed: {
    resolvedSelectedItemId() {
      return this.activeItemId || this.selectedItemId;
    },
    selectedItem() {
      return this.items.find((item) => item.id === this.resolvedSelectedItemId) || this.items[0] || null;
    },
    selectedPanelComponent() {
      switch (this.selectedItem?.id) {
        case 'home':
          return AdminHomePageSettingsView;
        case 'permissions':
          return AdminPermissionsView;
        case 'archive':
          return AdminArchiveView;
        case 'registration':
          return AdminRegistrationView;
        case 'users':
          return AdminPeopleView;
        case 'templates':
          return AdminTemplatesView;
        default:
          return null;
      }
    },
    selectedPanelProps() {
      return { embedded: true };
    },
    selectedItemActions() {
      switch (this.selectedItem?.id) {
        case 'users':
          return [
            { id: 'edit', label: 'تعديل', variant: 'secondary' },
            { id: 'create', label: 'إضافة', variant: 'primary' },
          ];
        default:
          return [];
      }
    },
  },
  watch: {
    activeItemId: {
      immediate: true,
      handler(nextValue) {
        if (nextValue && this.items.some((item) => item.id === nextValue)) {
          this.selectedItemId = nextValue;
        }
      },
    },
    items: {
      immediate: true,
      handler(nextItems) {
        if (!nextItems.length) {
          this.selectedItemId = '';
          return;
        }

        if (!nextItems.some((item) => item.id === this.selectedItemId)) {
          this.selectedItemId = nextItems[0].id;
        }
      },
    },
  },
  methods: {
    openCreateDialog() {
      this.$refs.selectedPanel?.openCreateDialog?.();
    },
    openArchiveAllDialog() {
      this.$refs.selectedPanel?.openArchiveAllDialog?.();
    },
    copyRegistrationLink() {
      this.$refs.selectedPanel?.copyRegistrationLink?.();
    },
    toggleRegistration() {
      this.$refs.selectedPanel?.toggleRegistration?.();
    },
    openFieldsDialog() {
      this.$refs.selectedPanel?.openFieldsDialog?.();
    },
    togglePermissionsWorkspaceSection() {
      this.$refs.selectedPanel?.toggleTopbarSection?.();
    },
    handleItemClick(item) {
      this.selectedItemId = item.id;
    },
    runSelectedItemAction(actionId) {
      const panel = this.$refs.selectedPanel;

      if (this.selectedItem?.id === 'users') {
        if (actionId === 'create') {
          panel?.openCreateDialog?.();
          return;
        }

        if (actionId === 'edit') {
          panel?.openEditDialog?.();
        }

        return;
      }

      if (this.selectedItem?.id === 'archive') {
        if (actionId === 'create') {
          panel?.openCreateDialog?.();
          return;
        }

        if (actionId === 'archive-all') {
          panel?.openArchiveAllDialog?.();
        }
      }
    },
  },
};
</script>

<style scoped>
.settings-admin {
  min-height: 100%;
  background: linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
}

.settings-admin--embedded {
  background: transparent;
}

.settings-admin__container {
  max-width: 1320px;
}

.settings-admin__shell {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 22px;
  align-items: start;
}

.settings-admin__shell:has(.settings-admin__content:only-child),
.settings-admin__shell--content-only {
  grid-template-columns: minmax(0, 1fr);
}

.settings-admin__sidebar {
  position: sticky;
  top: 24px;
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: #ffffff;
  box-shadow: 0 20px 44px rgba(15, 23, 42, 0.06);
  padding: 18px;
}

.settings-admin__sidebar-title {
  margin-bottom: 14px;
  color: #0f172a;
  font-size: 1.05rem;
  font-weight: 900;
}

.settings-admin__nav-item {
  width: 100%;
  border: 0;
  border-radius: 18px;
  padding: 14px 16px;
  background: transparent;
  color: #475569;
  font-size: 0.95rem;
  font-weight: 800;
  text-align: right;
  cursor: pointer;
  transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
}

.settings-admin__nav-item + .settings-admin__nav-item {
  margin-top: 8px;
}

.settings-admin__nav-item:hover {
  background: rgba(42, 148, 178, 0.08);
  color: #0f3554;
}

.settings-admin__nav-item--active {
  background: linear-gradient(135deg, rgba(42, 148, 178, 0.14), rgba(15, 109, 136, 0.08));
  color: #0f3554;
}

.settings-admin__content {
  min-width: 0;
}

.settings-admin__shell > .settings-admin__content:only-child {
  grid-column: 1 / -1;
}

.settings-admin__dialog-card {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 250, 250, 0.96) 100%);
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
}

.settings-admin__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.settings-admin__actions--panel {
  margin-bottom: 18px;
  justify-content: flex-start;
}

.settings-admin__panel-shell {
  min-width: 0;
}

.settings-admin__dialog-card {
  padding: 24px;
}

.settings-admin__dialog-card--compact {
  display: flex;
  justify-content: flex-start;
}

@media (max-width: 960px) {
  .settings-admin__shell {
    grid-template-columns: 1fr;
  }

  .settings-admin__sidebar {
    position: static;
  }

}
</style>
