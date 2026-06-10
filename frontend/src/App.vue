<template>
  <v-app>
    <v-main class="app-shell">
      <transition
        name="app-route"
        mode="out-in"
      >
        <router-view :key="routeViewKey" />
      </transition>
    </v-main>
  </v-app>
</template>

<script>
export default {
  name: 'AppRoot',
  computed: {
    routeViewKey() {
      const routeName = this.$route?.name || this.$route?.path || 'route';
      const params = this.$route?.params || {};

      return `${routeName}:${JSON.stringify(params)}`;
    },
  },
};
</script>

<style>
.app-route-enter-active,
.app-route-leave-active {
  transition: opacity 0.28s ease, transform 0.28s ease;
}

.app-route-enter,
.app-route-leave-to {
  opacity: 0;
  transform: translateY(12px);
}

@media (prefers-reduced-motion: reduce) {
  .app-route-enter-active,
  .app-route-leave-active {
    transition: opacity 0.01s linear;
  }

  .app-route-enter,
  .app-route-leave-to {
    transform: none;
  }
}
</style>