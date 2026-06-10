<template>
  <select
    :value="value"
    v-bind="forwardedAttrs"
    :class="['app-native-select', inheritedClass]"
    v-on="forwardedListeners"
    @change="handleChange"
  >
    <slot />
  </select>
</template>

<script>
const normalizeClass = (value) => {
  if (!value) {
    return [];
  }

  if (Array.isArray(value)) {
    return value.flatMap(normalizeClass);
  }

  if (typeof value === 'object') {
    return Object.entries(value)
      .filter(([, enabled]) => Boolean(enabled))
      .map(([className]) => className);
  }

  return String(value)
    .split(/\s+/)
    .map((className) => className.trim())
    .filter(Boolean);
};

export default {
  name: 'AppNativeSelect',
  inheritAttrs: false,
  model: {
    prop: 'value',
    event: 'input',
  },
  props: {
    value: {
      type: null,
      default: undefined,
    },
  },
  computed: {
    forwardedAttrs() {
      const attrs = { ...(this.$attrs || {}) };
      delete attrs.class;
      return attrs;
    },
    forwardedListeners() {
      return Object.fromEntries(
        Object.entries(this.$listeners || {}).filter(([eventName]) => !['input', 'change'].includes(eventName)),
      );
    },
    inheritedClass() {
      const vnodeData = this.$vnode?.data || {};
      return Array.from(new Set([
        ...normalizeClass(vnodeData.staticClass),
        ...normalizeClass(vnodeData.class),
        ...normalizeClass(this.$attrs.class),
      ]));
    },
  },
  methods: {
    handleChange(event) {
      this.$emit('input', event.target.value);
      this.$emit('change', event);
    },
  },
};
</script>
