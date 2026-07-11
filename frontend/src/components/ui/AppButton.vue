<template>
  <router-link
    v-if="to"
    :to="to"
    class="app-button"
    :class="buttonClasses"
    v-bind="$attrs"
    v-on="$listeners"
  >
    <span
      v-if="loading"
      class="app-button__spinner"
      aria-hidden="true"
    />
    <span class="app-button__content">
      <slot />
    </span>
  </router-link>
  <a
    v-else-if="href"
    :href="href"
    class="app-button"
    :class="buttonClasses"
    v-bind="$attrs"
    v-on="$listeners"
  >
    <span
      v-if="loading"
      class="app-button__spinner"
      aria-hidden="true"
    />
    <span class="app-button__content">
      <slot />
    </span>
  </a>
  <button
    v-else
    :type="nativeType"
    class="app-button"
    :class="buttonClasses"
    :disabled="isDisabled"
    v-bind="$attrs"
    v-on="$listeners"
  >
    <span
      v-if="loading"
      class="app-button__spinner"
      aria-hidden="true"
    />
    <span class="app-button__content">
      <slot />
    </span>
  </button>
</template>

<script>
export default {
  name: 'AppButton',
  inheritAttrs: false,
  props: {
    variant: {
      type: String,
      default: 'primary',
      validator: (value) => ['primary', 'secondary', 'danger', 'success', 'plain'].includes(value),
    },
    nativeType: {
      type: String,
      default: 'button',
    },
    to: {
      type: [String, Object],
      default: null,
    },
    href: {
      type: String,
      default: '',
    },
    loading: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    block: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    isDisabled() {
      return this.disabled || this.loading;
    },
    buttonClasses() {
      return {
        [`app-button--${this.variant}`]: true,
        'app-button--block': this.block,
        'app-button--loading': this.loading,
      };
    },
  },
};
</script>
