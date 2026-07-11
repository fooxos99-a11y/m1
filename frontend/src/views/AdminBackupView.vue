<template>
  <div
    class="backup-page"
    :class="{ 'backup-page--embedded': embedded, 'backup-page--dialog': dialogMode }"
  >
    <template v-if="dialogMode">
      <div class="backup-dialog-card">
        <div class="backup-dialog-card__header">
          <h2 class="backup-dialog-card__title">
            النسخة الاحتياطية
          </h2>
        </div>

        <div class="backup-dialog-card__body">
          <AppRawButton
            type="button"
            class="backup-dialog-card__action backup-dialog-card__action--primary"
            @click="downloadCurrentSnapshot"
          >
            <span>تحميل</span>
            <v-icon small>
              mdi-download
            </v-icon>
          </AppRawButton>

          <AppRawButton
            type="button"
            class="backup-dialog-card__action backup-dialog-card__action--secondary"
            @click="openFilePicker"
          >
            <span>رفع</span>
            <v-icon small>
              mdi-file-upload-outline
            </v-icon>
          </AppRawButton>

          <AppRawButton
            type="button"
            class="backup-dialog-card__action backup-dialog-card__action--danger"
            :disabled="!importedBackup && !importedFileName"
            @click="clearImportedBackup"
          >
            <span>حذف</span>
            <v-icon small>
              mdi-delete-outline
            </v-icon>
          </AppRawButton>

          <AppRawButton
            type="button"
            class="backup-dialog-card__action backup-dialog-card__action--restore"
            :disabled="!canRestoreBackup"
            @click="openRestoreDialog"
          >
            <span>استرجاع</span>
            <v-icon small>
              mdi-restore
            </v-icon>
          </AppRawButton>

          <div
            v-if="importedFileName"
            class="backup-dialog-card__file-name"
          >
            الملف الحالي: {{ importedFileName }}
          </div>

          <div
            v-if="pageError"
            class="backup-page__alert backup-page__alert--error backup-page__alert--dialog"
          >
            {{ pageError }}
          </div>

          <input
            ref="fileInput"
            type="file"
            accept="application/zip,.zip,application/json,.json"
            class="backup-page__file-input"
            @change="handleBackupFileSelect"
          >
        </div>

        <div class="backup-dialog-card__footer">
          <AppRawButton
            type="button"
            class="backup-dialog-card__close"
            @click="$emit('close')"
          >
            إغلاق
          </AppRawButton>
        </div>
      </div>
    </template>

    <v-container
      v-else
      class="backup-page__container py-8 py-md-10"
    >
      <section
        v-if="!embedded"
        class="backup-page__hero"
      >
        <div>
          <div class="backup-page__eyebrow">
            النسخة الاحتياطية
          </div>
          <h1 class="backup-page__title">
            إدارة النسخ الاحتياطية
          </h1>
          <p class="backup-page__subtitle">
            تصدير بيانات اللوحة الحالية، استيراد ملف نسخة احتياطية، ثم مقارنة المحتوى قبل أي استرجاع فعلي.
          </p>
        </div>
      </section>

      <section class="backup-page__actions-grid">
        <article class="backup-page__action-card">
          <div class="backup-page__action-caption">
            تصدير
          </div>
          <h2 class="backup-page__action-title">
            تنزيل نسخة من البيانات الحالية
          </h2>
          <p class="backup-page__action-text">
            يتم تنزيل Snapshot كامل بصيغة JSON من البيانات الحالية المعروضة في لوحة التحكم.
          </p>
          <AppRawButton
            type="button"
            class="backup-page__primary-button"
            @click="downloadCurrentSnapshot"
          >
            تصدير النسخة الحالية
          </AppRawButton>
        </article>

        <article class="backup-page__action-card">
          <div class="backup-page__action-caption">
            استيراد
          </div>
          <h2 class="backup-page__action-title">
            رفع ملف نسخة احتياطية
          </h2>
          <p class="backup-page__action-text">
            اختر ملف JSON مُصدّر سابقًا لمراجعة الفروقات بين النسخة الحالية والنسخة المرفوعة.
          </p>
          <div class="backup-page__action-row">
            <AppRawButton
              type="button"
              class="backup-page__primary-button"
              @click="openFilePicker"
            >
              اختيار الملف
            </AppRawButton>
            <span class="backup-page__file-name">{{ importedFileName || 'لم يتم اختيار ملف بعد' }}</span>
          </div>
          <input
            ref="fileInput"
            type="file"
            accept="application/zip,.zip,application/json,.json"
            class="backup-page__file-input"
            @change="handleBackupFileSelect"
          >
        </article>
      </section>

      <div
        v-if="pageError"
        class="backup-page__alert backup-page__alert--error"
      >
        {{ pageError }}
      </div>

      <section class="backup-page__content-grid">
        <article class="backup-page__panel">
          <div class="backup-page__panel-head">
            <div>
              <div class="backup-page__panel-caption">
                النسخة الحالية
              </div>
              <h2 class="backup-page__panel-title">
                ملخص البيانات الحالية
              </h2>
            </div>
          </div>

          <div class="backup-page__summary-grid">
            <div
              v-for="item in currentSummary"
              :key="item.label"
              class="backup-page__summary-card"
            >
              <div class="backup-page__summary-value">
                {{ item.value }}
              </div>
              <div class="backup-page__summary-label">
                {{ item.label }}
              </div>
            </div>
          </div>
        </article>

        <article class="backup-page__panel">
          <div class="backup-page__panel-head">
            <div>
              <div class="backup-page__panel-caption">
                النسخة المرفوعة
              </div>
              <h2 class="backup-page__panel-title">
                المقارنة قبل الاسترجاع
              </h2>
            </div>
          </div>

          <div
            v-if="!importedBackup && !importedBackupFile"
            class="backup-page__empty"
          >
            ارفع ملف نسخة احتياطية لعرض المقارنة التفصيلية.
          </div>
          <template v-else-if="importedBackup">
            <div class="backup-page__import-note">
              <div>الملف: {{ importedFileName }}</div>
              <div>السجلات المقارنة: {{ comparisonRows.length }}</div>
            </div>

            <div class="backup-page__comparison-list">
              <article
                v-for="row in comparisonRows"
                :key="row.label"
                class="backup-page__comparison-card"
              >
                <div class="backup-page__comparison-head">
                  <div class="backup-page__comparison-title">
                    {{ row.label }}
                  </div>
                  <div
                    class="backup-page__comparison-diff"
                    :class="diffClass(row.diff)"
                  >
                    {{ formatDiff(row.diff) }}
                  </div>
                </div>
                <div class="backup-page__comparison-values">
                  <span>الحالي: {{ row.current }}</span>
                  <span>النسخة: {{ row.backup }}</span>
                </div>
              </article>
            </div>

            <div class="backup-page__restore-note">
              سيستبدل الاسترجاع البيانات الحالية ببيانات النسخة المرفوعة. ملفات المواد التدريبية المرفوعة كملفات لا تُضمّن داخل JSON، أما روابط يوتيوب فتُستعاد.
            </div>

            <AppRawButton
              type="button"
              class="backup-page__danger-button"
              :disabled="!canRestoreBackup"
              @click="openRestoreDialog"
            >
              استرجاع النسخة المرفوعة
            </AppRawButton>
          </template>
          <template v-else>
            <div class="backup-page__import-note">
              <div>الملف: {{ importedFileName }}</div>
              <div>نوع النسخة: ZIP كامل مع الملفات</div>
            </div>
            <div class="backup-page__restore-note">
              سيتم قراءة محتوى ZIP على الخادم واسترجاع البيانات والملفات المرفقة داخله.
            </div>
            <AppRawButton
              type="button"
              class="backup-page__danger-button"
              :disabled="!canRestoreBackup"
              @click="openRestoreDialog"
            >
              استرجاع النسخة المرفوعة
            </AppRawButton>
          </template>
        </article>
      </section>
    </v-container>

    <div
      v-if="restoreDialogOpen"
      class="backup-confirm"
      role="dialog"
      aria-modal="true"
    >
      <div class="backup-confirm__card">
        <h3 class="backup-confirm__title">
          تأكيد الاسترجاع
        </h3>
        <p class="backup-confirm__text">
          سيتم استبدال بيانات الداشبورد الحالية بملف النسخة الاحتياطية المرفوع. لا تغلق الصفحة حتى تكتمل العملية.
        </p>
        <div class="backup-confirm__actions">
          <AppRawButton
            type="button"
            class="backup-confirm__button backup-confirm__button--secondary"
            :disabled="isRestoring"
            @click="closeRestoreDialog"
          >
            إلغاء
          </AppRawButton>
          <AppRawButton
            type="button"
            class="backup-confirm__button backup-confirm__button--danger"
            :disabled="isRestoring"
            @click="restoreImportedBackup"
          >
            {{ isRestoring ? 'جارٍ الاسترجاع...' : 'استرجاع الآن' }}
          </AppRawButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import { exportDashboardBackup, restoreDashboardBackup, restoreDashboardBackupFile } from '../services/api';
import { AppRawButton } from '../components/ui';

const buildSummary = (snapshot) => {
  const safe = snapshot || {};
  const students = safe.students || [];

  return [
    { label: 'الطلاب', value: students.length },
    { label: 'الأجزاء المحفوظة', value: students.reduce((sum, student) => sum + ((student.completedParts || []).length), 0) },
    { label: 'المقرئون', value: (safe.reciters || []).length },
    { label: 'الدورات', value: (safe.courses || []).length },
    { label: 'الحضور', value: (safe.attendance || []).length },
    { label: 'الإشعارات', value: (safe.notifications || []).length },
    { label: 'النتائج', value: (safe.submissions || []).length },
    { label: 'أسئلة الاستبيان', value: (safe.satisfactionQuestions || []).length },
    { label: 'ردود الاستبيان', value: (safe.satisfactionResponses || []).length },
    { label: 'أسئلة النهائي', value: (safe.finalExamQuestions || []).length },
    { label: 'نتائج النهائي', value: (safe.finalExamSubmissions || []).length },
  ];
};

export default {
  name: 'AdminBackupView',
  components: {
    AppRawButton,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
    dialogMode: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      importedBackup: null,
      importedBackupFile: null,
      importedFileName: '',
      pageError: '',
      restoreDialogOpen: false,
      isRestoring: false,
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot']),
    canRestoreBackup() {
      return Boolean(this.importedBackup || this.importedBackupFile) && !this.isRestoring;
    },
    currentSummary() {
      return buildSummary(this.dashboardSnapshot);
    },
    comparisonRows() {
      if (!this.importedBackup) {
        return [];
      }

      const backupSummary = buildSummary(this.importedBackup);

      return this.currentSummary.map((item, index) => ({
        label: item.label,
        current: item.value,
        backup: backupSummary[index]?.value || 0,
        diff: (backupSummary[index]?.value || 0) - item.value,
      }));
    },
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot']),
    openFilePicker() {
      this.$refs.fileInput?.click();
    },
    clearImportedBackup() {
      this.importedBackup = null;
      this.importedBackupFile = null;
      this.importedFileName = '';
      this.pageError = '';
      this.restoreDialogOpen = false;
    },
    downloadCurrentSnapshot() {
      exportDashboardBackup()
        .then((response) => {
          const url = window.URL.createObjectURL(response.data);
          const anchor = document.createElement('a');
          const fallbackName = `momars-backup-${new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-')}.zip`;
          const disposition = response.headers?.['content-disposition'] || '';
          const fileName = disposition.match(/filename="?([^"]+)"?/i)?.[1] || fallbackName;

          anchor.href = url;
          anchor.download = fileName;
          anchor.click();
          window.URL.revokeObjectURL(url);
        })
        .catch(() => {
          this.$toast.error('تعذر تحميل النسخة الاحتياطية.');
        });
    },
    async handleBackupFileSelect(event) {
      const file = event?.target?.files?.[0];
      event.target.value = '';

      if (!file) {
        return;
      }

      this.pageError = '';

      try {
        if (/\.zip$/i.test(file.name) || file.type === 'application/zip') {
          this.importedBackup = null;
          this.importedBackupFile = file;
          this.importedFileName = file.name;
          return;
        }

        const text = await file.text();
        const parsed = JSON.parse(text);

        if (!parsed || typeof parsed !== 'object') {
          throw new Error('invalid-backup');
        }

        this.importedBackup = parsed;
        this.importedBackupFile = null;
        this.importedFileName = file.name;
      } catch {
        this.clearImportedBackup();
        this.pageError = 'تعذر قراءة الملف. تأكد أنه ملف JSON صالح صادر من النظام.';
      }
    },
    openRestoreDialog() {
      if (!this.canRestoreBackup) {
        return;
      }

      this.pageError = '';
      this.restoreDialogOpen = true;
    },
    closeRestoreDialog() {
      if (this.isRestoring) {
        return;
      }

      this.restoreDialogOpen = false;
    },
    async restoreImportedBackup() {
      if ((!this.importedBackup && !this.importedBackupFile) || this.isRestoring) {
        return;
      }

      this.isRestoring = true;
      this.pageError = '';

      try {
        if (this.importedBackupFile) {
          await restoreDashboardBackupFile(this.importedBackupFile);
        } else {
          await restoreDashboardBackup(this.importedBackup);
        }
        await this.loadDashboardSnapshot();
        this.clearImportedBackup();
        this.$toast.success('تم استرجاع النسخة الاحتياطية بنجاح');
      } catch (error) {
        this.pageError = error?.response?.data?.message || 'تعذر استرجاع النسخة الاحتياطية.';
        this.$toast.error(this.pageError);
      } finally {
        this.isRestoring = false;
      }
    },
    diffClass(diff) {
      if (diff > 0) {
        return 'backup-page__comparison-diff--positive';
      }

      if (diff < 0) {
        return 'backup-page__comparison-diff--negative';
      }

      return 'backup-page__comparison-diff--neutral';
    },
    formatDiff(diff) {
      if (diff > 0) {
        return `+${diff}`;
      }

      return String(diff);
    },
  },
};
</script>

<style scoped>
.backup-page {
  min-height: 100%;
  background: linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
}

.backup-page--embedded {
  background: transparent;
}

.backup-page--dialog {
  background: transparent;
  min-height: 0;
}

.backup-dialog-card {
  border-radius: 34px;
  background: #fff;
  box-shadow: 0 28px 80px rgba(15, 23, 42, 0.22);
  overflow: hidden;
}

.backup-dialog-card__header {
  padding: 28px 30px 18px;
  border-bottom: 1px solid #dbe8f0;
}

.backup-dialog-card__title {
  margin: 0;
  color: #0b3f5b;
  font-size: 2rem;
  font-weight: 900;
}

.backup-dialog-card__body {
  display: grid;
  gap: 12px;
  padding: 26px 30px;
}

.backup-dialog-card__action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 48px;
  width: 100%;
  border-radius: 18px;
  font-size: 1rem;
  font-weight: 900;
  cursor: pointer;
}

.backup-dialog-card__action ::v-deep .v-icon {
  color: #fff !important;
}

.backup-dialog-card__action--primary {
  border: 0;
  background: linear-gradient(180deg, #1e809b 0%, #146d88 100%);
  color: #fff;
}

.backup-dialog-card__action--secondary {
  border: 1px solid #cbe1eb;
  background: #fff;
  color: #0f3554;
}

.backup-dialog-card__action--danger {
  border: 0;
  background: #e02424;
  color: #fff;
}

.backup-dialog-card__action--restore {
  border: 0;
  background: #0f766e;
  color: #fff;
}

.backup-dialog-card__action:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.backup-dialog-card__file-name {
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 700;
  text-align: right;
}

.backup-dialog-card__footer {
  display: flex;
  justify-content: flex-start;
  padding: 0 30px 30px;
  border-top: 1px solid #dbe8f0;
  padding-top: 14px;
}

.backup-dialog-card__close {
  min-height: 44px;
  padding: 0 22px;
  border: 1px solid #cbe1eb;
  border-radius: 999px;
  background: #fff;
  color: #0f3554;
  font-size: 0.98rem;
  font-weight: 900;
  cursor: pointer;
}

.backup-page__alert--dialog {
  margin-bottom: 0;
}

.backup-page__container {
  max-width: 1240px;
}

.backup-page__hero {
  margin-bottom: 24px;
}

.backup-page__eyebrow {
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 800;
}

.backup-page__title {
  margin: 8px 0 0;
  color: #0f172a;
  font-size: 1.9rem;
  font-weight: 900;
}

.backup-page__subtitle {
  margin: 10px 0 0;
  color: #64748b;
  font-size: 0.96rem;
  font-weight: 600;
}

.backup-page__actions-grid,
.backup-page__content-grid {
  display: grid;
  gap: 20px;
}

.backup-page__actions-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-bottom: 20px;
}

.backup-page__content-grid {
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
}

.backup-page__action-card,
.backup-page__panel {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
  padding: 24px;
}

.backup-page__action-caption,
.backup-page__panel-caption,
.backup-page__summary-label,
.backup-page__comparison-values,
.backup-page__file-name {
  color: #64748b;
  font-size: 0.84rem;
  font-weight: 700;
}

.backup-page__action-title,
.backup-page__panel-title,
.backup-page__comparison-title {
  color: #0f172a;
  font-weight: 900;
}

.backup-page__action-title,
.backup-page__panel-title {
  margin-top: 8px;
  font-size: 1.14rem;
}

.backup-page__action-text {
  margin: 12px 0 0;
  color: #475569;
  font-size: 0.92rem;
  line-height: 1.8;
}

.backup-page__action-row {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-top: 18px;
}

.backup-page__primary-button {
  min-height: 48px;
  padding: 0 24px;
  border: 0;
  border-radius: 999px;
  background: linear-gradient(180deg, #1e809b 0%, #146d88 100%);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 900;
  box-shadow: 0 10px 24px rgba(18, 96, 119, 0.18);
  cursor: pointer;
}

.backup-page__danger-button {
  min-height: 48px;
  width: 100%;
  margin-top: 18px;
  padding: 0 20px;
  border: 0;
  border-radius: 18px;
  background: #dc2626;
  color: #fff;
  font-size: 0.98rem;
  font-weight: 900;
  cursor: pointer;
}

.backup-page__danger-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.backup-page__file-input {
  display: none;
}

.backup-page__alert {
  margin-bottom: 18px;
  border-radius: 22px;
  padding: 18px 20px;
  font-weight: 700;
}

.backup-page__alert--error {
  border: 1px solid rgba(220, 38, 38, 0.18);
  background: rgba(220, 38, 38, 0.06);
  color: #b91c1c;
}

.backup-page__panel-head {
  margin-bottom: 18px;
}

.backup-page__summary-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.backup-page__summary-card,
.backup-page__comparison-card {
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 22px;
  background: rgba(248, 250, 252, 0.78);
  padding: 16px;
}

.backup-page__summary-value {
  color: #146d88;
  font-size: 1.5rem;
  font-weight: 900;
}

.backup-page__import-note,
.backup-page__restore-note,
.backup-page__empty {
  border-radius: 22px;
  padding: 16px 18px;
  font-weight: 700;
}

.backup-page__import-note {
  margin-bottom: 14px;
  background: rgba(20, 109, 136, 0.06);
  color: #0f172a;
}

.backup-page__empty,
.backup-page__restore-note {
  border: 1px dashed rgba(15, 23, 42, 0.14);
  background: rgba(248, 250, 252, 0.72);
  color: #475569;
}

.backup-page__comparison-list {
  display: grid;
  gap: 12px;
}

.backup-page__comparison-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.backup-page__comparison-values {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 10px;
}

.backup-page__comparison-diff {
  font-size: 0.9rem;
  font-weight: 900;
}

.backup-page__comparison-diff--positive {
  color: #15803d;
}

.backup-page__comparison-diff--negative {
  color: #dc2626;
}

.backup-page__comparison-diff--neutral {
  color: #64748b;
}

.backup-page__restore-note {
  margin-top: 14px;
}

.backup-confirm {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgba(15, 23, 42, 0.45);
}

.backup-confirm__card {
  width: min(100%, 440px);
  border-radius: 24px;
  background: #fff;
  padding: 26px;
  box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
  direction: rtl;
  text-align: right;
}

.backup-confirm__title {
  margin: 0 0 10px;
  color: #0f3554;
  font-size: 1.35rem;
  font-weight: 900;
}

.backup-confirm__text {
  margin: 0;
  color: #52657a;
  font-size: 0.98rem;
  line-height: 1.8;
}

.backup-confirm__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-start;
  gap: 10px;
  margin-top: 22px;
}

.backup-confirm__button {
  min-height: 44px;
  padding: 0 20px;
  border-radius: 999px;
  font-size: 0.95rem;
  font-weight: 900;
  cursor: pointer;
}

.backup-confirm__button--secondary {
  border: 1px solid #cbe1eb;
  background: #fff;
  color: #0f3554;
}

.backup-confirm__button--danger {
  border: 0;
  background: #dc2626;
  color: #fff;
}

.backup-confirm__button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 960px) {
  .backup-page__actions-grid,
  .backup-page__content-grid,
  .backup-page__summary-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .backup-page__action-row,
  .backup-page__comparison-head {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
