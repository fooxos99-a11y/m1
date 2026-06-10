<template>
  <label class="app-field" :class="{ 'app-field--error': error, 'app-field--disabled': disabled }">
    <span v-if="label" class="app-field__label">{{ label }}</span>
    <span class="app-field__control">
      <v-icon v-if="prependIcon" size="20" class="app-field__icon">{{ prependIcon }}</v-icon>
      <textarea
        v-if="multiline"
        :value="value"
        :rows="rows"
        :placeholder="placeholder"
        :disabled="disabled"
        class="app-field__input app-field__input--textarea"
        v-bind="$attrs"
        @input="$emit('input', $event.target.value)"
        @change="$emit('change', $event.target.value)"
      />
      <input
        v-else
        :value="value"
        :type="type"
        :placeholder="placeholder"
        :disabled="disabled"
        class="app-field__input"
        v-bind="$attrs"
        @input="$emit('input', $event.target.value)"
        @change="$emit('change', $event.target.value)"
      >
      <slot name="append" />
    </span>
    <span v-if="error || hint" class="app-field__message">{{ error || hint }}</span>
  </label>
</template>

<script>
export default {
  name: 'AppInput',
  inheritAttrs: false,
  props: {
    value: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    prependIcon: { type: String, default: '' },
    multiline: { type: Boolean, default: false },
    rows: { type: [String, Number], default: 4 },
    disabled: { type: Boolean, default: false },
  },
};
</script>
