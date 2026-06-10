<template>
  <v-card
    outlined
    class="pa-4 upload-card"
  >
    <div class="text-subtitle-1 font-weight-bold mb-3">
      Uppy Placeholder
    </div>
    <div ref="uppyRoot" />
  </v-card>
</template>

<script>
import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import '@uppy/core/dist/style.min.css';
import '@uppy/dashboard/dist/style.min.css';

export default {
  name: 'UploadCard',
  data() {
    return {
      uppy: null,
    };
  },
  mounted() {
    this.uppy = new Uppy({
      autoProceed: false,
      restrictions: {
        maxNumberOfFiles: 3,
      },
    });

    this.uppy.use(Dashboard, {
      target: this.$refs.uppyRoot,
      inline: true,
      proudlyDisplayPoweredByUppy: false,
      height: 260,
    });
  },
  beforeDestroy() {
    if (this.uppy) {
      this.uppy.destroy();
    }
  },
};
</script>

<style scoped>
.upload-card {
  min-height: 340px;
  border-radius: 24px;
}
</style>