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
          <AppButton
            variant="plain"
            class="training-materials-list__preview-close"
            @click="closePreviewDialog"
          >
            إغلاق
          </AppButton>
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
          <div class="training-materials-list__title-group">
            <span
              class="training-materials-list__icon"
              aria-hidden="true"
            >
              <v-icon size="22">
                mdi-file-document-multiple-outline
              </v-icon>
            </span>
            <div>
              <div class="training-materials-list__title">
                {{ material.title }}
              </div>
            </div>
          </div>

          <div
            v-if="showEdit || showDelete"
            class="training-materials-list__actions"
          >
            <AppRawButton
              v-if="showEdit"
              type="button"
              class="training-materials-list__action-button training-materials-list__action-button--edit"
              aria-label="تعديل المادة"
              @click="$emit('edit', material)"
            >
              <v-icon small>
                mdi-pencil-outline
              </v-icon>
            </AppRawButton>
            <AppRawButton
              v-if="showDelete"
              type="button"
              class="training-materials-list__action-button training-materials-list__action-button--delete"
              aria-label="حذف المادة"
              :disabled="deletingId === material.id"
              @click="$emit('delete', material.id)"
            >
              <i
                class="fa-solid fa-trash-can app-action-icon app-action-icon--delete"
                aria-hidden="true"
              />
            </AppRawButton>
          </div>
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
            <span
              class="training-materials-list__attachment-icon"
              aria-hidden="true"
            >
              <v-icon size="18">
                {{ attachment.type === 'youtube' ? 'mdi-youtube' : 'mdi-paperclip' }}
              </v-icon>
            </span>
            <span class="training-materials-list__attachment-name">{{ attachment.displayName || attachment.originalName || attachment.name }}</span>
          </component>
        </div>
      </article>
    </div>
  </div>
</template>

<script>
import { AppButton, AppRawButton } from './ui';

export default {
  name: 'TrainingMaterialsList',
  components: {
    AppButton,
    AppRawButton,
  },
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
    showEdit: {
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
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.training-materials-list__card {
  position: relative;
  overflow: hidden;
  border: 1px solid rgba(144, 201, 214, 0.88);
  border-radius: 22px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fcfd 100%);
  box-shadow: 0 16px 34px rgba(15, 23, 42, 0.055);
  padding: 18px;
}

.training-materials-list__card::before {
  position: absolute;
  inset-block-start: 0;
  inset-inline: 0;
  height: 4px;
  border-radius: 22px 22px 0 0;
  background: linear-gradient(90deg, #0f7894, #8ac7d4);
  content: '';
}

.training-materials-list__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.training-materials-list__title-group {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-width: 0;
}

.training-materials-list__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 42px;
  height: 42px;
  border-radius: 14px;
  background: rgba(16, 118, 153, 0.09);
  color: #0f7894;
}

.training-materials-list__title {
  color: #10304a;
  font-size: 1.05rem;
  font-weight: 900;
  line-height: 1.55;
  overflow-wrap: anywhere;
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
  justify-content: flex-start;
  gap: 10px;
  width: 100%;
  min-height: 48px;
  border-radius: 16px;
  background: #f3fafb;
  border: 1px solid #d8eaef;
  padding: 11px 13px;
  color: #14415d;
  text-decoration: none;
  cursor: pointer;
  text-align: right;
  transition: border-color 0.18s ease, background 0.18s ease, transform 0.18s ease;
}

.training-materials-list__attachment:hover {
  border-color: rgba(16, 118, 153, 0.35);
  background: #ffffff;
  transform: translateY(-1px);
}

.training-materials-list__attachment:focus-visible {
  outline: 2px solid rgba(16, 118, 153, 0.38);
  outline-offset: 2px;
}

.training-materials-list__attachment-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 30px;
  height: 30px;
  border-radius: 10px;
  background: #ffffff;
  color: #0f7894;
}

.training-materials-list__attachment-name {
  min-width: 0;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.training-materials-list__actions {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.training-materials-list__action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border: 1px solid #d4e5ec;
  border-radius: 999px;
  background: #ffffff;
  color: #527082;
  cursor: pointer;
}

.training-materials-list__action-button--edit:hover {
  border-color: rgba(27, 111, 135, 0.38);
  color: #1b6f87;
}

.training-materials-list__action-button--delete:hover {
  border-color: rgba(190, 69, 69, 0.32);
  color: #b42323;
  background: rgba(190, 69, 69, 0.07);
}

.training-materials-list__action-button:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (max-width: 780px) {
  .training-materials-list__grid {
    grid-template-columns: 1fr;
  }

  .training-materials-list__preview-header {
    flex-direction: column;
    align-items: stretch;
  }

  .training-materials-list__head {
    gap: 12px;
  }

  .training-materials-list__actions {
    gap: 6px;
  }

  .training-materials-list__preview-frame,
  .training-materials-list__preview-video,
  .training-materials-list__preview-image {
    min-height: 48vh;
  }
}
</style>
