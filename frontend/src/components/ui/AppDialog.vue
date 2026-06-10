<template>
  <dialog
    ref="dialog"
    class="modal"
    :class="{ 'modal--scrollable': scrollable }"
    :style="dialogStyle"
    @cancel="handleNativeClose"
    @close="handleNativeClose"
    @click="handleBackdropClick"
  >
    <div
      class="modal-box"
      :style="boxStyle"
    >
      <slot />
    </div>
  </dialog>
</template>

<script>
export default {
  name: 'AppDialog',
  model: {
    prop: 'value',
    event: 'input',
  },
  props: {
    value: {
      type: Boolean,
      default: false,
    },
    maxWidth: {
      type: [String, Number],
      default: '620',
    },
    scrollable: {
      type: Boolean,
      default: false,
    },
    closeOnBackdrop: {
      type: Boolean,
      default: true,
    },
    persistent: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    normalizedMaxWidth() {
      if (typeof this.maxWidth === 'number') {
        return `${this.maxWidth}px`;
      }

      const value = String(this.maxWidth || '').trim();

      if (!value) {
        return '620px';
      }

      return /^\d+$/.test(value) ? `${value}px` : value;
    },
    dialogStyle() {
      return {
        '--modal-box-max-width': this.normalizedMaxWidth,
      };
    },
    boxStyle() {
      return {
        maxWidth: 'var(--modal-box-max-width)',
      };
    },
  },
  watch: {
    value: {
      immediate: true,
      handler(nextValue) {
        this.syncState(nextValue);
      },
    },
  },
  methods: {
    syncState(isOpen) {
      const dialog = this.$refs.dialog;

      if (!dialog) {
        return;
      }

      if (isOpen && !dialog.open) {
        dialog.showModal();
        return;
      }

      if (!isOpen && dialog.open) {
        dialog.close();
      }
    },
    handleNativeClose(event) {
      if (this.persistent && event?.type === 'cancel') {
        event.preventDefault();
        return;
      }

      if (this.value) {
        this.$emit('input', false);
      }

      this.$emit('close');
    },
    handleBackdropClick(event) {
      if (!this.closeOnBackdrop || this.persistent || event.target !== this.$refs.dialog) {
        return;
      }

      this.$refs.dialog.close();
    },
    showModal() {
      this.syncState(true);
    },
    close() {
      this.syncState(false);
    },
  },
};
</script>
