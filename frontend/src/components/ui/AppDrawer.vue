<template>
  <transition name="app-drawer">
    <div
      v-if="value"
      class="app-drawer-layer"
    >
      <button
        class="app-drawer__backdrop"
        type="button"
        aria-label="إغلاق"
        @click="close"
      />
      <aside
        class="app-drawer"
        :class="`app-drawer--${side}`"
        role="dialog"
        aria-modal="true"
      >
        <header class="app-drawer__header">
          <h2>{{ title }}</h2>
          <AppIconButton
            aria-label="إغلاق"
            variant="neutral"
            @click="close"
          >
            <v-icon size="20">
              mdi-close
            </v-icon>
          </AppIconButton>
        </header>
        <div class="app-drawer__body">
          <slot />
        </div>
        <footer
          v-if="$slots.footer"
          class="app-drawer__footer"
        >
          <slot name="footer" />
        </footer>
      </aside>
    </div>
  </transition>
</template>

<script>
import AppIconButton from '../AppIconButton.vue';

export default {
  name: 'AppDrawer',
  components: { AppIconButton },
  model: { prop: 'value', event: 'input' },
  props: {
    value: { type: Boolean, default: false },
    title: { type: String, default: '' },
    side: { type: String, default: 'right', validator: (value) => ['right', 'left'].includes(value) },
  },
  methods: {
    close() {
      this.$emit('input', false);
      this.$emit('close');
    },
  },
};
</script>
