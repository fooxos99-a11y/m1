<template>
  <button
    :type="nativeType"
    v-bind="$attrs"
    v-on="$listeners"
  >
    <slot />
  </button>
</template>

<script>
export default {
  name: 'AppRawButton',
  inheritAttrs: false,
  props: {
    nativeType: {
      type: String,
      default: 'button',
    },
  },
};
</script>
