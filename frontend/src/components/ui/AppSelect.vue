<template>
  <v-select
    :value="value"
    v-bind="$attrs"
    :menu-props="resolvedMenuProps"
    :class="['app-select', $attrs.class]"
    v-on="forwardedListeners"
    @input="$emit('input', $event)"
    @change="$emit('change', $event)"
  >
    <template
      v-for="slotName in forwardedScopedSlotNames"
      #[slotName]="slotProps"
    >
      <slot
        :name="slotName"
        v-bind="slotProps"
      />
    </template>
  </v-select>
</template>

<script>
const mergeContentClass = (baseClass, extraClass) => {
  const classes = [baseClass, extraClass]
    .flatMap((value) => String(value || '').split(/\s+/))
    .map((value) => value.trim())
    .filter(Boolean);

  return Array.from(new Set(classes)).join(' ');
};

export default {
  name: 'AppSelect',
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
    menuProps: {
      type: [Object, String],
      default: null,
    },
  },
  data() {
    return {
      menuAttachTarget: null,
    };
  },
  computed: {
    forwardedScopedSlotNames() {
      return Object.keys(this.$scopedSlots || {});
    },
    forwardedListeners() {
      return Object.fromEntries(
        Object.entries(this.$listeners || {}).filter(([eventName]) => !['input', 'change'].includes(eventName)),
      );
    },
    resolvedMenuProps() {
      const globalMenuProps = this.$dropdownMenuProps || {};

      if (typeof this.menuProps === 'string') {
        return {
          ...globalMenuProps,
          attach: this.menuAttachTarget,
          contentClass: mergeContentClass(globalMenuProps.contentClass, this.menuProps),
        };
      }

      const localMenuProps = this.menuProps || {};

      return {
        ...globalMenuProps,
        ...localMenuProps,
        attach: localMenuProps.attach !== undefined ? localMenuProps.attach : this.menuAttachTarget,
        contentClass: mergeContentClass(globalMenuProps.contentClass, localMenuProps.contentClass),
      };
    },
  },
  mounted() {
    this.menuAttachTarget = this.resolveAttachTarget();
  },
  methods: {
    resolveAttachTarget() {
      const modal = this.$el?.closest('.modal');

      if (modal) {
        return this.$el;
      }

      return false;
    },
  },
};
</script>
