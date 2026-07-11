<template>
  <button
    :type="nativeType"
    class="app-icon-button"
    :class="buttonClasses"
    :disabled="disabled"
    v-bind="$attrs"
    v-on="$listeners"
  >
    <span class="app-icon-button__content">
      <slot />
    </span>
  </button>
</template>

<script>
export default {
  name: 'AppIconButton',
  inheritAttrs: false,
  props: {
    variant: {
      type: String,
      default: 'neutral',
      validator: (value) => ['neutral', 'primary', 'danger', 'plain'].includes(value),
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['sm', 'md'].includes(value),
    },
    active: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    nativeType: {
      type: String,
      default: 'button',
    },
  },
  computed: {
    buttonClasses() {
      return {
        [`app-icon-button--${this.variant}`]: true,
        [`app-icon-button--${this.size}`]: true,
        'app-icon-button--active': this.active,
      };
    },
  },
};
</script>
