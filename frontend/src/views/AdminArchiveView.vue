<template>
  <div
    class="admin-archive-view"
    :class="{ 'admin-archive-view--embedded': embedded }"
    dir="rtl"
  >
    <v-container class="admin-archive-view__container py-8 py-md-10">
      <section class="admin-archive-view__card">
        <div class="admin-archive-view__toolbar">
          <div class="admin-archive-view__field admin-archive-view__field--select">
            <label class="admin-archive-view__field-label">الأرشيف</label>
            <AppSelect
              v-model="selectedArchiveId"
              :items="archives"
              item-text="name"
              item-value="id"
              label="اختر الدفعة أو الأرشيف"
              dense
              outlined
              hide-details
              class="admin-archive-view__select"
              @change="fetchArchiveData"
            >
              <template #item="{ item, on, attrs }">
                <div
                  class="admin-archive-view__select-option"
                  v-bind="attrs"
                  v-on="on"
                >
                  <span class="admin-archive-view__select-option-label">{{ item.name }}</span>
                  <button
                    type="button"
                    class="admin-archive-view__select-option-delete"
                    :disabled="deletingArchiveId === item.id"
                    :aria-label="`حذف الأرشيف ${item.name}`"
                    @mousedown.stop.prevent
                    @click.stop.prevent="deleteArchive(item)"
                  >
                    <i
                      class="fa-solid fa-trash-can"
                      aria-hidden="true"
                    />
                  </button>
                </div>
              </template>
            </AppSelect>
          </div>

          <div class="admin-archive-view__field admin-archive-view__field--search">
            <label class="admin-archive-view__field-label">البحث باسم الطالب</label>
            <div class="admin-archive-view__search-row">
              <input
                v-model="studentSearchQuery"
                type="text"
                class="admin-archive-view__search-input"
                placeholder="اكتب اسم الطالب"
              >
            </div>
          </div>
        </div>

        <div
          v-if="!canShowArchiveWorkspace"
          class="admin-archive-view__empty"
        >
          يرجى اختيار الأرشيف أو البحث باسم الطالب لعرض السجلات.
        </div>

        <div
          v-else
          class="admin-archive-view__workspace"
        >
          <div class="admin-archive-view__table-shell">
            <div class="admin-archive-view__table-head">
              <h2 class="admin-archive-view__section-title">
                {{ displayedStudentsTitle }}
              </h2>
            </div>

            <v-data-table
              :headers="studentHeaders"
              :items="displayedStudents"
              :loading="loading || searchLoading"
              class="admin-archive-view__table elevation-0"
              :no-data-text="displayedStudentsEmptyText"
            >
              <template #[`item.full_name`]="{ item }">
                <button
                  type="button"
                  class="admin-archive-view__student-link"
                  :class="{ 'admin-archive-view__student-link--active': selectedArchivedStudentId === item.id }"
                  @click="openArchivedStudentDetail(item)"
                >
                  {{ item.full_name }}
                </button>
              </template>
              <template #[`item.archive_name`]="{ item }">
                {{ item.archive_name || '---' }}
              </template>
              <template #[`item.branch`]="{ item }">
                {{ item.branch ? item.branch.name : '' }}
              </template>
              <template #[`item.created_at`]="{ item }">
                {{ formatDate(item.created_at) }}
              </template>
            </v-data-table>
          </div>
        </div>
      </section>

      <AppDialog
        v-model="detailDialogOpen"
        max-width="1080"
        @close="closeArchivedStudentDetailDialog"
      >
        <div class="archive-dialog archive-dialog--detail">
          <AppDialogHeader
            title="سجل الطالب المؤرشف"
          />

          <AppDialogBody class="archive-dialog__detail-body">
            <div
              v-if="detailLoading"
              class="admin-archive-view__details-empty"
            >
              جارٍ تحميل بيانات الطالب المؤرشف...
            </div>

            <div
              v-else-if="selectedArchivedStudentDetail"
              class="admin-archive-view__details"
            >
              <div class="admin-archive-view__details-header">
                <div>
                  <h3 class="admin-archive-view__details-name">
                    {{ selectedArchivedStudentDetail.student.name }}
                  </h3>
                  <div
                    v-if="selectedArchivedStudentDetail.student.note"
                    class="admin-archive-view__details-meta"
                  >
                    الملاحظة: {{ selectedArchivedStudentDetail.student.note }}
                  </div>
                </div>
              </div>

              <section class="admin-archive-view__stats">
                <div
                  v-for="item in buildArchivedStudentStats(selectedArchivedStudentDetail)"
                  :key="item.key"
                  class="admin-archive-view__stat-card"
                >
                  <span class="admin-archive-view__stat-value">{{ item.value }}</span>
                  <span class="admin-archive-view__stat-label">{{ item.label }}</span>
                </div>
              </section>

              <section
                v-if="selectedArchivedStudentDetail.finalExam"
                class="admin-archive-view__final-exam-card"
              >
                <div class="admin-archive-view__mini-title">
                  الاختبار النهائي
                </div>
                <div class="admin-archive-view__assessment-grid">
                  <div class="admin-archive-view__assessment-item">
                    <span class="admin-archive-view__assessment-label">الدرجة</span>
                    <strong>{{ formatScore(selectedArchivedStudentDetail.finalExam.manualScore) }}</strong>
                  </div>
                  <div class="admin-archive-view__assessment-item">
                    <span class="admin-archive-view__assessment-label">تاريخ الإرسال</span>
                    <strong>{{ formatDateTime(selectedArchivedStudentDetail.finalExam.submittedAt) }}</strong>
                  </div>
                </div>
              </section>
            </div>

            <div
              v-else
              class="admin-archive-view__details-empty"
            >
              لا توجد بيانات لعرضها لهذا الطالب المؤرشف.
            </div>
          </AppDialogBody>

          <AppDialogFooter class="archive-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeArchivedStudentDetailDialog"
            >
              إغلاق
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="createDialog"
        max-width="520"
        @close="closeCreateDialog"
      >
        <div class="archive-dialog">
          <AppDialogHeader
            title="إضافة أرشيف جديد"
          />

          <AppDialogBody>
            <div class="archive-dialog__field">
              <label class="archive-dialog__label">اسم الأرشيف</label>
              <input
                v-model.trim="newArchiveName"
                type="text"
                class="archive-dialog__input"
                placeholder="مثال: الدفعة الأولى"
                @keyup.enter="createArchive"
              >
            </div>
            <div class="archive-dialog__field">
              <label class="archive-dialog__label">عدد الدورات</label>
              <input
                v-model.number="newArchiveCoursesCount"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
                class="archive-dialog__input"
                placeholder="0"
                @keyup.enter="createArchive"
              >
            </div>
            <div class="archive-dialog__field">
              <label class="archive-dialog__label">الدفعة</label>
              <AppSelect
                v-model="newArchiveBatchType"
                :items="batchTypeOptions"
                item-text="label"
                item-value="value"
                dense
                outlined
                hide-details
                :menu-props="{ top: true, offsetY: true }"
                class="archive-dialog__select"
              />
            </div>
          </AppDialogBody>

          <AppDialogFooter class="archive-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeCreateDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="saving"
              :disabled="!newArchiveName.trim()"
              @click="createArchive"
            >
              إضافة
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="archiveAllDialog"
        max-width="620"
        @close="closeArchiveAllDialog"
      >
        <div class="archive-dialog">
          <AppDialogHeader
            title="أرشفة المحتوى الحالي"
          />

          <AppDialogBody>
            <div class="archive-dialog__field">
              <label class="archive-dialog__label">اسم الأرشيف</label>
              <input
                v-model.trim="archiveAllName"
                type="text"
                class="archive-dialog__input"
                placeholder="مثال: الدفعة الأولى 1447"
                @keyup.enter="archiveAllContent"
              >
            </div>
            <div class="archive-dialog__field">
              <label class="archive-dialog__label">الدفعة</label>
              <AppSelect
                v-model="archiveAllBatchType"
                :items="batchTypeOptions"
                item-text="label"
                item-value="value"
                dense
                outlined
                hide-details
                :menu-props="{ top: true, offsetY: true }"
                class="archive-dialog__select"
              />
            </div>
          </AppDialogBody>

          <AppDialogFooter class="archive-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeArchiveAllDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="saving"
              :disabled="!archiveAllName.trim()"
              @click="archiveAllContent"
            >
              أرشفة
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>

      <AppDialog
        v-model="addStudentDialog"
        max-width="620"
        @close="closeAddStudentDialog"
      >
        <div class="archive-dialog">
          <AppDialogHeader
            title="إضافة طالب إلى الأرشيف"
          />

          <AppDialogBody>
            <div class="archive-dialog__field">
              <label class="archive-dialog__label">اسم الطالب</label>
              <input
                v-model.trim="manualStudentName"
                type="text"
                class="archive-dialog__input"
                placeholder="اكتب اسم الطالب"
                @keyup.enter="archiveStudent"
              >
            </div>
          </AppDialogBody>

          <AppDialogFooter class="archive-dialog__footer">
            <AppButton
              variant="secondary"
              @click="closeAddStudentDialog"
            >
              إلغاء
            </AppButton>
            <AppButton
              variant="primary"
              :loading="saving"
              :disabled="!manualStudentName.trim()"
              @click="archiveStudent"
            >
              إضافة
            </AppButton>
          </AppDialogFooter>
        </div>
      </AppDialog>
    </v-container>
  </div>
</template>

<script>
import api from '../services/api';
import {
  AppButton, AppDialog, AppDialogBody, AppDialogFooter, AppDialogHeader, AppSelect,
} from '../components/ui';

export default {
  name: 'AdminArchiveView',
  components: {
    AppButton,
    AppDialog,
    AppDialogBody,
    AppDialogFooter,
    AppDialogHeader,
    AppSelect,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      archives: [],
      selectedArchiveId: null,
      archiveData: null,
      loading: false,
      saving: false,
      deletingArchiveId: '',
      createDialog: false,
      newArchiveName: '',
      newArchiveCoursesCount: 0,
      newArchiveBatchType: 'all',
      archiveAllDialog: false,
      archiveAllName: '',
      archiveAllBatchType: 'all',
      addStudentDialog: false,
      manualStudentName: '',
      studentSearchQuery: '',
      searchDebounceId: null,
      searchLoading: false,
      searchPerformed: false,
      searchResults: [],
      selectedArchivedStudentId: '',
      selectedArchivedStudentDetail: null,
      detailDialogOpen: false,
      detailLoading: false,
      batchTypeOptions: [
        { label: 'معلمين', value: 'male' },
        { label: 'معلمات', value: 'female' },
        { label: 'الجميع', value: 'all' },
      ],
      studentHeaders: [
        { text: 'اسم الطالب', value: 'full_name' },
        { text: 'الدفعة', value: 'archive_name' },
        { text: 'رقم الدخول', value: 'login_code' },
        { text: 'الفرع', value: 'branch' },
        { text: 'تاريخ الإضافة', value: 'created_at' },
      ],
    };
  },
  computed: {
    selectedArchive() {
      return this.archives.find((archive) => archive.id === this.selectedArchiveId) || null;
    },
    hasSearchResults() {
      return this.searchPerformed && this.studentSearchQuery.trim().length > 0;
    },
    canShowArchiveWorkspace() {
      return Boolean(this.selectedArchiveId || this.hasSearchResults);
    },
    displayedStudents() {
      const source = this.hasSearchResults
        ? this.searchResults
        : (this.archiveData?.students || []);

      return source.map((student) => ({
        ...student,
        archive_name: student.archive_name || this.archiveData?.archive?.name || this.selectedArchive?.name || '',
      }));
    },
    displayedStudentsTitle() {
      return this.hasSearchResults ? 'نتائج البحث' : 'الطلاب المؤرشفون';
    },
    displayedStudentsEmptyText() {
      return this.hasSearchResults
        ? 'لا يوجد طلاب مطابقون لهذا الاسم في أي دفعة'
        : 'لا يوجد طلاب في هذا الأرشيف';
    },
  },
  watch: {
    studentSearchQuery() {
      this.scheduleArchivedStudentSearch();
    },
  },
  created() {
    this.fetchArchives();
  },
  beforeDestroy() {
    if (this.searchDebounceId) {
      clearTimeout(this.searchDebounceId);
    }
  },
  methods: {
    formatDate(value) {
      if (!value) {
        return '';
      }

      return new Date(value).toLocaleDateString();
    },
    formatDateTime(value) {
      if (!value) {
        return 'لا يوجد';
      }

      return new Date(value).toLocaleString();
    },
    formatScore(value) {
      if (value === null || value === undefined || value === '') {
        return 'لا يوجد';
      }

      return Number(value);
    },
    openCreateDialog() {
      this.createDialog = true;
    },
    openArchiveAllDialog() {
      this.archiveAllDialog = true;
    },
    scheduleArchivedStudentSearch() {
      if (this.searchDebounceId) {
        clearTimeout(this.searchDebounceId);
      }

      this.searchDebounceId = window.setTimeout(() => {
        this.searchDebounceId = null;
        this.searchArchivedStudents();
      }, 300);
    },
    buildArchivedStudentStats(detail) {
      const summary = detail?.summary || {};

      return [
        { key: 'pre', label: 'مرات الاختبار القبلي', value: Number(summary.preTests || 0) },
        { key: 'post', label: 'مرات الاختبار البعدي', value: Number(summary.postTests || 0) },
        { key: 'tasks', label: 'المهام المرسلة', value: Number(summary.tasks || 0) },
        { key: 'attendance', label: 'مرات التحضير', value: Number(summary.attendance || 0) },
      ];
    },
    clearArchivedStudentDetail() {
      this.selectedArchivedStudentId = '';
      this.selectedArchivedStudentDetail = null;
      this.detailDialogOpen = false;
    },
    closeArchivedStudentDetailDialog() {
      this.clearArchivedStudentDetail();
    },
    closeCreateDialog() {
      this.createDialog = false;
      this.newArchiveName = '';
      this.newArchiveCoursesCount = 0;
      this.newArchiveBatchType = 'all';
    },
    closeArchiveAllDialog() {
      this.archiveAllDialog = false;
      this.archiveAllName = '';
      this.archiveAllBatchType = 'all';
    },
    closeAddStudentDialog() {
      this.addStudentDialog = false;
      this.manualStudentName = '';
    },
    notifySuccess(message) {
      if (this.$toast?.success) {
        this.$toast.success(message);
        return;
      }

      window.alert(message);
    },
    notifyError(message) {
      if (this.$toast?.error) {
        this.$toast.error(message);
        return;
      }

      window.alert(message);
    },
    async fetchArchives() {
      try {
        const res = await api.get('/dashboard/archives');
        this.archives = res.data;
      } catch (err) {
        this.notifyError('تعذر تحميل الأرشيفات.');
      }
    },
    async fetchArchiveData() {
      if (!this.selectedArchiveId) {
        this.archiveData = null;
        this.clearArchivedStudentDetail();
        return;
      }

      this.loading = true;
      this.clearArchivedStudentDetail();

      try {
        const res = await api.get(`/dashboard/archives/${this.selectedArchiveId}`);
        this.archiveData = res.data;
      } catch (err) {
        this.notifyError('تعذر تحميل بيانات الأرشيف.');
      } finally {
        this.loading = false;
      }
    },
    async deleteArchive(archive) {
      if (!archive || this.deletingArchiveId) {
        return;
      }

      if (!window.confirm(`هل تريد حذف الأرشيف "${archive.name}"؟`)) {
        return;
      }

      this.deletingArchiveId = archive.id;

      try {
        await api.delete(`/dashboard/archives/${archive.id}`);
        this.selectedArchiveId = null;
        this.archiveData = null;
        this.clearArchivedStudentDetail();
        await this.fetchArchives();
        this.notifySuccess('تم حذف الأرشيف بنجاح.');
      } catch (err) {
        this.notifyError(err?.response?.data?.message || 'تعذر حذف الأرشيف.');
      } finally {
        this.deletingArchiveId = '';
      }
    },
    async searchArchivedStudents() {
      const term = this.studentSearchQuery.trim();

      if (!term) {
        this.searchPerformed = false;
        this.searchResults = [];
        this.clearArchivedStudentDetail();
        return;
      }

      this.searchLoading = true;
      this.searchPerformed = true;
      this.clearArchivedStudentDetail();

      try {
        const res = await api.get('/dashboard/archives/search/students', {
          params: { name: term },
        });
        this.searchResults = Array.isArray(res.data) ? res.data : [];
      } catch (err) {
        this.searchResults = [];
        this.notifyError('تعذر تنفيذ البحث في الأرشيف.');
      } finally {
        this.searchLoading = false;
      }
    },
    async openArchivedStudentDetail(student) {
      const archiveId = student?.archive_id || this.selectedArchiveId;

      if (!archiveId || !student?.id || this.detailLoading) {
        return;
      }

      this.selectedArchivedStudentId = student.id;
      this.selectedArchivedStudentDetail = null;
      this.detailDialogOpen = true;
      this.detailLoading = true;

      try {
        const res = await api.get(`/dashboard/archives/${archiveId}/students/${student.id}`);
        this.selectedArchivedStudentDetail = res.data;
      } catch (err) {
        this.notifyError('تعذر تحميل السجل الكامل للطالب المؤرشف.');
      } finally {
        this.detailLoading = false;
      }
    },
    async createArchive() {
      if (!this.newArchiveName.trim()) {
        return;
      }

      this.saving = true;

      try {
        await api.post('/dashboard/archives', {
          name: this.newArchiveName,
          courses_count: Math.max(0, Number(this.newArchiveCoursesCount) || 0),
          batch_type: this.newArchiveBatchType,
        });
        await this.fetchArchives();
        this.closeCreateDialog();
        this.notifySuccess('تم إنشاء الأرشيف بنجاح.');
      } catch (err) {
        this.notifyError(err?.response?.data?.message || 'حدث خطأ أثناء إنشاء الأرشيف.');
      } finally {
        this.saving = false;
      }
    },
    async archiveStudent() {
      if (!this.selectedArchiveId || !this.manualStudentName.trim()) {
        return;
      }

      const studentName = this.manualStudentName.trim();
      this.saving = true;

      try {
        await api.post(`/dashboard/archives/${this.selectedArchiveId}/students`, {
          name: studentName,
        });
        this.closeAddStudentDialog();
        await this.fetchArchiveData();
        this.notifySuccess('تمت إضافة الطالب إلى الأرشيف.');
      } catch (err) {
        this.notifyError('تعذر نقل الطالب إلى الأرشيف.');
      } finally {
        this.saving = false;
      }
    },
    async archiveAllContent() {
      if (!this.archiveAllName.trim()) {
        return;
      }

      const archiveName = this.archiveAllName.trim();
      this.saving = true;

      try {
        const res = await api.post('/dashboard/archives/archive-all', {
          name: archiveName,
          batch_type: this.archiveAllBatchType,
        });
        const createdArchiveId = res?.data?.archive?.id || '';
        this.closeArchiveAllDialog();
        await this.fetchArchives();
        await this.$store.dispatch('loadDashboardSnapshot');

        if (createdArchiveId) {
          this.selectedArchiveId = createdArchiveId;
          await this.fetchArchiveData();
        } else if (this.selectedArchiveId) {
          await this.fetchArchiveData();
        }

        this.notifySuccess('تم نقل كافة العناصر الحالية إلى الأرشيف بنجاح.');
      } catch (err) {
        this.notifyError(err?.response?.data?.message || 'فشلت عملية الأرشفة الحالية.');
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.admin-archive-view {
  min-height: 100%;
  background: linear-gradient(180deg, #f8fbfb 0%, #eef5f5 100%);
}

.admin-archive-view--embedded {
  background: transparent;
}

.admin-archive-view--embedded .admin-archive-view__container {
  max-width: none;
  padding: 0 !important;
}

.admin-archive-view--embedded .admin-archive-view__card {
  border: 0;
  border-radius: 0;
  background: transparent;
  box-shadow: none;
  padding: 0;
}

.admin-archive-view__container {
  max-width: 1200px;
}

.admin-archive-view__card {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 250, 250, 0.96) 100%);
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
  padding: 24px;
}

.admin-archive-view__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}

.admin-archive-view__field {
  min-width: 280px;
  max-width: 360px;
}

.admin-archive-view__field--select {
  flex: 1 1 320px;
}

.admin-archive-view__field--search {
  flex: 1 1 320px;
  max-width: 420px;
}

.admin-archive-view__field-label {
  display: block;
  margin-bottom: 8px;
  color: #334155;
  font-size: 0.88rem;
  font-weight: 800;
}

.admin-archive-view__select {
  width: 100%;
}

.admin-archive-view__select-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
}

.admin-archive-view__select-option-label {
  min-width: 0;
  flex: 1;
}

.admin-archive-view__select-option-delete {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: none;
  background: transparent;
  color: #c13636;
  cursor: pointer;
  transition: color 0.2s ease, opacity 0.2s ease;
}

.admin-archive-view__select-option-delete:hover:not(:disabled) {
  color: #9f1f1f;
}

.admin-archive-view__select-option-delete:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.admin-archive-view__search-row {
  display: flex;
  align-items: center;
  width: 100%;
}

.admin-archive-view__search-input {
  width: 100%;
  min-width: 0;
  border: 1px solid rgba(42, 148, 178, 0.28);
  border-radius: 18px;
  padding: 13px 16px;
  background: #fff;
  color: #0f172a;
  font-size: 0.95rem;
  font-weight: 700;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.admin-archive-view__search-input::placeholder {
  color: #94a3b8;
  font-weight: 600;
}

.admin-archive-view__search-input:focus {
  border-color: rgba(42, 148, 178, 0.65);
  box-shadow: 0 0 0 4px rgba(42, 148, 178, 0.12);
  background: #fcfefe;
}

.admin-archive-view__manual-action {
  align-self: flex-end;
}

.admin-archive-view__empty {
  border: 1px dashed rgba(42, 148, 178, 0.28);
  border-radius: 24px;
  padding: 28px 22px;
  text-align: center;
  color: #64748b;
  font-weight: 700;
  background: rgba(248, 251, 251, 0.78);
}

.admin-archive-view__workspace {
  display: block;
}

.admin-archive-view__table-shell {
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 24px;
  overflow: hidden;
  background: #fff;
}

.admin-archive-view__table-head {
  padding: 18px 20px 0;
}

.admin-archive-view__section-title {
  margin: 0;
  color: #0f172a;
  font-size: 1.05rem;
  font-weight: 900;
}

.admin-archive-view__student-link {
  border: 0;
  padding: 0;
  background: transparent;
  color: #0f3554;
  font-weight: 800;
  cursor: pointer;
  transition: color 0.2s ease;
}

.admin-archive-view__student-link:hover,
.admin-archive-view__student-link--active {
  color: #2a94b2;
}

.admin-archive-view__table :deep(.v-data-table__wrapper) {
  border-radius: 0 0 24px 24px;
}

.admin-archive-view__details-shell {
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 24px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(244, 249, 250, 0.96) 100%);
  box-shadow: 0 18px 42px rgba(15, 23, 42, 0.06);
  min-height: 100%;
}

.admin-archive-view__details,
.admin-archive-view__details-empty {
  padding: 22px;
}

.admin-archive-view__details-empty {
  color: #64748b;
  font-weight: 700;
  line-height: 1.9;
}

.admin-archive-view__details-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.admin-archive-view__details-eyebrow {
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 800;
}

.admin-archive-view__details-name {
  margin: 6px 0 10px;
  color: #0f172a;
  font-size: 1.5rem;
  font-weight: 900;
}

.admin-archive-view__details-meta {
  color: #475569;
  font-size: 0.9rem;
  font-weight: 700;
  line-height: 1.8;
}

.admin-archive-view__stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 22px;
}

.admin-archive-view__stat-card {
  border: 1px solid rgba(42, 148, 178, 0.16);
  border-radius: 18px;
  padding: 14px 10px;
  text-align: center;
  background: rgba(248, 251, 251, 0.92);
}

.admin-archive-view__stat-value {
  display: block;
  color: #0f3554;
  font-size: 1.15rem;
  font-weight: 900;
}

.admin-archive-view__stat-label {
  display: block;
  margin-top: 6px;
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 800;
}

.admin-archive-view__mini-title {
  margin-bottom: 12px;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.admin-archive-view__parts-section,
.admin-archive-view__courses-section,
.admin-archive-view__final-exam-card {
  margin-top: 22px;
}

.admin-archive-view__parts-grid {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 8px;
}

.admin-archive-view__part-chip {
  border-radius: 14px;
  padding: 10px 6px;
  text-align: center;
  background: #edf3f5;
  color: #64748b;
  font-weight: 800;
}

.admin-archive-view__part-chip--active {
  background: linear-gradient(135deg, #2a94b2, #0f6d88);
  color: #fff;
}

.admin-archive-view__course-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.admin-archive-view__course-card,
.admin-archive-view__final-exam-card {
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 20px;
  padding: 16px;
  background: #fff;
}

.admin-archive-view__course-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.admin-archive-view__course-title {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.admin-archive-view__course-type {
  margin-top: 6px;
  color: #64748b;
  font-size: 0.84rem;
  font-weight: 700;
}

.admin-archive-view__attendance-badge {
  border-radius: 999px;
  padding: 8px 12px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 0.8rem;
  font-weight: 800;
}

.admin-archive-view__attendance-badge--active {
  background: rgba(34, 197, 94, 0.14);
  color: #15803d;
}

.admin-archive-view__assessment-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.admin-archive-view__assessment-item {
  border-radius: 16px;
  padding: 12px;
  background: #f8fbfb;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.admin-archive-view__assessment-label {
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 800;
}

.admin-archive-view__assessment-item strong {
  color: #0f3554;
  font-size: 1rem;
  font-weight: 900;
}

.admin-archive-view__assessment-item small {
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 700;
}

.admin-archive-view__course-foot {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 14px;
  margin-top: 14px;
  color: #475569;
  font-size: 0.82rem;
  font-weight: 700;
}

.archive-dialog {
  padding: 24px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbfb 100%);
}

.archive-dialog--detail {
  max-height: min(88vh, 980px);
  overflow: hidden;
}

.archive-dialog__detail-body {
  max-height: calc(88vh - 180px);
  overflow: auto;
}

.archive-dialog__field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.archive-dialog__field + .archive-dialog__field {
  margin-top: 16px;
}

.archive-dialog__label {
  color: #334155;
  font-size: 0.88rem;
  font-weight: 800;
}

.archive-dialog__input {
  width: 100%;
  border: 1px solid rgba(148, 163, 184, 0.34);
  border-radius: 18px;
  padding: 14px 16px;
  background: #fff;
  color: #0f172a;
  font-size: 0.95rem;
  font-weight: 600;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.archive-dialog__input:focus {
  border-color: rgba(42, 148, 178, 0.65);
  box-shadow: 0 0 0 4px rgba(42, 148, 178, 0.12);
}

.archive-dialog__select {
  width: 100%;
}

.archive-dialog__footer {
  margin-top: 20px;
  justify-content: flex-end;
}

@media (max-width: 960px) {
  .admin-archive-view__workspace {
    grid-template-columns: 1fr;
  }

  .admin-archive-view__toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .admin-archive-view__field,
  .admin-archive-view__field--select {
    max-width: none;
    width: 100%;
  }

  .admin-archive-view__manual-action {
    align-self: stretch;
  }

  .admin-archive-view__stats,
  .admin-archive-view__assessment-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .admin-archive-view__parts-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .admin-archive-view__details-header,
  .admin-archive-view__course-head {
    flex-direction: column;
  }

  .admin-archive-view__stats,
  .admin-archive-view__assessment-grid,
  .admin-archive-view__parts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
