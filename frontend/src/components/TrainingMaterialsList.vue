<template>
  <div class="training-materials-list">
    <v-dialog
      v-model="previewDialogOpen"
      max-width="1120"
    >
      <v-card class="training-materials-list__preview-dialog">
        <div class="training-materials-list__preview-header">
          <div class="training-materials-list__preview-title">
            {{ previewAttachmentName }}
          </div>
        </div>

        <div class="training-materials-list__preview-body">
          <img
            v-if="previewKind === 'image'"
            :src="previewAttachment?.url"
            :alt="previewAttachmentName"
            class="training-materials-list__preview-image"
          >
          <video
            v-else-if="previewKind === 'video'"
            :src="previewAttachment?.url"
            controls
            class="training-materials-list__preview-video"
          />
          <iframe
            v-else-if="previewKind === 'document' || previewKind === 'youtube'"
            :src="previewKind === 'youtube' ? previewAttachment?.embedUrl : previewAttachment?.url"
            class="training-materials-list__preview-frame"
            :title="previewAttachmentName"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
          />
          <div
            v-else
            class="training-materials-list__preview-empty"
          >
            لا تتوفر معاينة مباشرة لهذا النوع من الملفات داخل الصفحة.
          </div>
        </div>

        <div class="training-materials-list__preview-actions">
          <button
            type="button"
            class="training-materials-list__preview-close"
            @click="closePreviewDialog"
          >
            إغلاق
          </button>
        </div>
      </v-card>
    </v-dialog>

    <div
      v-if="!materials.length"
      class="training-materials-list__empty"
    >
      <div class="training-materials-list__empty-title">
        {{ emptyTitle }}
      </div>
      <div class="training-materials-list__empty-copy">
        {{ emptyDescription }}
      </div>
    </div>

    <div
      v-else
      class="training-materials-list__grid"
    >
      <article
        v-for="material in materials"
        :key="material.id"
        class="training-materials-list__card"
      >
        <div class="training-materials-list__head">
          <div>
            <div class="training-materials-list__title">
              {{ material.title }}
            </div>
          </div>

          <button
            v-if="showDelete"
            type="button"
            class="training-materials-list__delete"
            :disabled="deletingId === material.id"
            @click="$emit('delete', material.id)"
          >
            حذف
          </button>
        </div>

        <div
          v-if="material.description"
          class="training-materials-list__description"
        >
          {{ material.description }}
        </div>

        <div class="training-materials-list__attachments">
          <component
            :is="previewInDialog ? 'button' : 'a'"
            v-for="attachment in material.attachments"
            :key="attachment.id"
            :href="previewInDialog ? undefined : attachment.url"
            :target="previewInDialog ? undefined : '_blank'"
            :rel="previewInDialog ? undefined : 'noopener'"
            type="button"
            class="training-materials-list__attachment"
            @click="previewInDialog ? openPreviewDialog(attachment) : null"
          >
            <span class="training-materials-list__attachment-name">{{ attachment.displayName || attachment.originalName || attachment.name }}</span>
            <span class="training-materials-list__attachment-meta">{{ formatSize(attachment.size) }}</span>
          </component>
        </div>
      </article>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TrainingMaterialsList',
  props: {
    materials: {
      type: Array,
      default: () => [],
    },
    emptyTitle: {
      type: String,
      default: 'لا توجد مواد متاحة.',
    },
    emptyDescription: {
      type: String,
      default: 'أضف مادة جديدة لتظهر هنا.',
    },
    showDelete: {
      type: Boolean,
      default: false,
    },
    previewInDialog: {
      type: Boolean,
      default: false,
    },
    deletingId: {
      type: [String, Number],
      default: '',
    },
  },
  data() {
    return {
      previewDialogOpen: false,
      previewAttachment: null,
    };
  },
  computed: {
    previewAttachmentName() {
      return this.previewAttachment?.displayName || this.previewAttachment?.originalName || this.previewAttachment?.name || 'معاينة الملف';
    },
    previewKind() {
      if (this.previewAttachment?.type === 'youtube' && this.previewAttachment?.embedUrl) {
        return 'youtube';
      }

      const mimeType = String(this.previewAttachment?.mimeType || '').toLowerCase();
      const fileName = String(this.previewAttachment?.originalName || this.previewAttachment?.name || '').toLowerCase();

      if (mimeType.startsWith('image/')) {
        return 'image';
      }

      if (mimeType.startsWith('video/')) {
        return 'video';
      }

      if (mimeType === 'application/pdf'
        || mimeType.startsWith('text/')
        || /\.(pdf|txt|md|csv)$/i.test(fileName)) {
        return 'document';
      }

      return 'other';
    },
  },
  methods: {
    formatSize(size) {
      const bytes = Number(size || 0);

      if (bytes <= 0) {
        return '';
      }

      if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
      }

      return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    },
    openPreviewDialog(attachment) {
      if (!this.previewInDialog || !attachment?.url) {
        return;
      }

      this.previewAttachment = attachment;
      this.previewDialogOpen = true;
    },
    closePreviewDialog() {
      this.previewDialogOpen = false;
      this.previewAttachment = null;
    },
  },
};
</script>

<style scoped>
.training-materials-list {
  display: grid;
  gap: 16px;
}

.training-materials-list__preview-dialog {
  border-radius: 28px;
  overflow: hidden;
}

.training-materials-list__preview-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 22px 24px 18px;
  border-bottom: 1px solid rgba(225, 235, 241, 0.95);
}

.training-materials-list__preview-title {
  color: #10304a;
  font-size: 1.06rem;
  font-weight: 900;
}

.training-materials-list__preview-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(159, 191, 207, 0.9);
  border-radius: 999px;
  background: #f5fafb;
  color: #14415d;
  cursor: pointer;
  font-weight: 800;
  padding: 10px 18px;
  text-decoration: none;
}

.training-materials-list__preview-body {
  padding: 20px 24px;
  background: #f8fbfd;
}

.training-materials-list__preview-frame,
.training-materials-list__preview-video,
.training-materials-list__preview-image {
  width: 100%;
  min-height: 68vh;
  border: 0;
  border-radius: 20px;
  background: #ffffff;
}

.training-materials-list__preview-image {
  min-height: 0;
  max-height: 72vh;
  object-fit: contain;
}

.training-materials-list__preview-video {
  background: #0f172a;
}

.training-materials-list__preview-empty {
  border: 1px dashed rgba(159, 191, 207, 0.9);
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.94);
  color: #5f7384;
  padding: 28px 24px;
  text-align: center;
}

.training-materials-list__preview-actions {
  display: flex;
  justify-content: flex-end;
  padding: 0 24px 24px;
}

.training-materials-list__empty {
  border: 1px dashed rgba(159, 191, 207, 0.9);
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.9);
  padding: 32px 24px;
  text-align: center;
  color: #5f7384;
}

.training-materials-list__empty-title {
  color: #12344d;
  font-size: 1.05rem;
  font-weight: 800;
}

.training-materials-list__empty-copy {
  margin-top: 8px;
  line-height: 1.9;
}

.training-materials-list__grid {
  display: grid;
  gap: 16px;
}

.training-materials-list__card {
  border: 1px solid rgba(225, 235, 241, 0.95);
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 18px 36px rgba(15, 23, 42, 0.06);
  padding: 20px;
}

.training-materials-list__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.training-materials-list__title {
  color: #10304a;
  font-size: 1.05rem;
  font-weight: 900;
}

.training-materials-list__meta {
  margin-top: 6px;
  color: #72889a;
  font-size: 0.88rem;
}

.training-materials-list__description {
  margin-top: 14px;
  color: #486173;
  line-height: 1.9;
  white-space: pre-line;
}

.training-materials-list__attachments {
  display: grid;
  gap: 10px;
  margin-top: 16px;
}

.training-materials-list__attachment {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  border-radius: 18px;
  background: #f5fafb;
  border: 1px solid #dcecf0;
  padding: 12px 14px;
  color: #14415d;
  text-decoration: none;
  cursor: pointer;
  text-align: right;
}

.training-materials-list__attachment-name {
  font-weight: 700;
}

.training-materials-list__attachment-meta {
  color: #60798b;
  font-size: 0.85rem;
  white-space: nowrap;
}

.training-materials-list__delete {
  border: 0;
  border-radius: 999px;
  background: rgba(196, 64, 64, 0.12);
  color: #a52828;
  cursor: pointer;
  font-weight: 800;
  padding: 10px 16px;
}

.training-materials-list__delete:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (max-width: 720px) {
  .training-materials-list__preview-header {
    flex-direction: column;
    align-items: stretch;
  }

  .training-materials-list__head,
  .training-materials-list__attachment {
    flex-direction: column;
    align-items: flex-start;
  }

  .training-materials-list__preview-frame,
  .training-materials-list__preview-video,
  .training-materials-list__preview-image {
    min-height: 48vh;
  }
}
</style>
