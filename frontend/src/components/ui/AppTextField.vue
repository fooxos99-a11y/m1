<template>
  <v-text-field
    v-bind="$attrs"
    v-on="$listeners"
  >
    <template
      v-for="slotName in forwardedSlots"
      #[slotName]="slotProps"
    >
      <slot
        :name="slotName"
        v-bind="slotProps"
      />
    </template>
  </v-text-field>
</template>

<script>
export default {
  name: 'AppTextField',
  inheritAttrs: false,
  computed: {
    forwardedSlots() {
      return Object.keys(this.$scopedSlots);
    },
  },
};
</script>
