<template>
  <div class="home-page-settings">
    <v-alert
      v-if="errorMessage"
      type="error"
      dense
      text
      class="mb-4"
    >
      {{ errorMessage }}
    </v-alert>

    <v-alert
      v-if="successMessage"
      type="success"
      dense
      text
      class="mb-4"
    >
      {{ successMessage }}
    </v-alert>

    <div class="home-page-settings__sections">
      <section class="home-page-settings__section">
        <div class="home-page-settings__section-header">
          <h3>المقدمة والتنقل</h3>
        </div>

        <div class="home-page-settings__grid">
          <AppTextField
            v-model="form.brandTitle"
            label="اسم البرنامج في الهيدر"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.heroTitle"
            label="العنوان الرئيسي"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.heroPrimaryButtonLabel"
            label="زر التسجيل"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.heroSecondaryButtonLabel"
            label="زر التعرف على البرنامج"
            outlined
            dense
            hide-details="auto"
          />
          <v-textarea
            v-model="form.heroText"
            label="الوصف الرئيسي"
            outlined
            rows="4"
            auto-grow
            hide-details="auto"
            class="home-page-settings__field--full"
          />
        </div>

        <div class="home-page-settings__cards mt-5">
          <article
            v-for="(item, index) in form.navItems"
            :key="`nav-${index}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                رابط التنقل {{ index + 1 }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف رابط التنقل"
                @click="deleteItem('navItems', index)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>
            <AppTextField
              v-model="item.label"
              :label="`اسم الرابط ${index + 1}`"
              outlined
              dense
              hide-details="auto"
            />
          </article>
        </div>
      </section>

      <section class="home-page-settings__section">
        <div class="home-page-settings__section-header">
          <h3>نبذة البرنامج والأهداف</h3>
        </div>

        <div class="home-page-settings__grid">
          <AppTextField
            v-model="form.aboutEyebrow"
            label="العنوان الصغير للقسم"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.aboutTitlePrefix"
            label="بداية عنوان النبذة"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.aboutTitleHighlight"
            label="الكلمة المميزة في العنوان"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.goalsHeadingPrefix"
            label="بداية عنوان الأهداف"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.goalsHeadingHighlight"
            label="الكلمة المميزة في عنوان الأهداف"
            outlined
            dense
            hide-details="auto"
          />
          <v-textarea
            v-model="form.aboutLead"
            label="الفقرة التعريفية الأساسية"
            outlined
            rows="4"
            auto-grow
            hide-details="auto"
            class="home-page-settings__field--full"
          />
          <v-textarea
            v-model="form.aboutBody"
            label="الفقرة التوضيحية الثانية"
            outlined
            rows="4"
            auto-grow
            hide-details="auto"
            class="home-page-settings__field--full"
          />
        </div>

        <div class="home-page-settings__cards mt-5">
          <article
            v-for="(goal, index) in form.goals"
            :key="`goal-${index}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                هدف {{ index + 1 }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف الهدف"
                @click="deleteItem('goals', index)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>
            <v-textarea
              v-model="form.goals[index]"
              :label="`نص الهدف ${index + 1}`"
              outlined
              rows="3"
              auto-grow
              hide-details="auto"
            />
          </article>
        </div>
      </section>

      <section class="home-page-settings__section">
        <div class="home-page-settings__section-header">
          <h3>المؤشرات</h3>
        </div>

        <div class="home-page-settings__grid">
          <AppTextField
            v-model="form.statsEyebrow"
            label="العنوان الصغير للمؤشرات"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.statsTitlePrefix"
            label="بداية عنوان المؤشرات"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.statsTitleHighlight"
            label="الكلمة المميزة في عنوان المؤشرات"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.indicatorLabels.memorization"
            label="مؤشر الأجزاء المقروءة"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.indicatorLabels.attendance"
            label="مؤشر الحضور"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.indicatorLabels.assessments"
            label="مؤشر الاختبار القبلي والبعدي"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.indicatorLabels.courses"
            label="مؤشر الدورات"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.indicatorLabels.tasks"
            label="مؤشر المهام الأدائية"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.indicatorLabels.completed30"
            label="مؤشر من أتموا 30 جزءًا"
            outlined
            dense
            hide-details="auto"
          />
        </div>
      </section>

      <section class="home-page-settings__section">
        <div class="home-page-settings__section-header">
          <h3>المجالات والكفايات</h3>
        </div>

        <div class="home-page-settings__grid mb-5">
          <AppTextField
            v-model="form.competenciesTitle"
            label="عنوان قسم المجالات والكفايات"
            outlined
            dense
            hide-details="auto"
            class="home-page-settings__field--full"
          />
        </div>

        <div class="home-page-settings__cards">
          <article
            v-for="(domain, domainIndex) in form.domains"
            :key="`domain-${domainIndex}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                مجال {{ domainIndex + 1 }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف المجال"
                @click="deleteItem('domains', domainIndex)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>

            <div class="home-page-settings__grid">
              <AppTextField
                v-model="domain.title"
                label="عنوان المجال"
                outlined
                dense
                hide-details="auto"
                class="home-page-settings__field--full"
              />
              <AppTextField
                v-for="(item, itemIndex) in domain.items"
                :key="`domain-${domainIndex}-item-${itemIndex}`"
                v-model="domain.items[itemIndex]"
                :label="`الكفاية ${itemIndex + 1}`"
                outlined
                dense
                hide-details="auto"
              />
            </div>
          </article>
        </div>
      </section>

      <section class="home-page-settings__section">
        <div class="home-page-settings__section-header">
          <h3>ما يتضمنه البرنامج</h3>
        </div>

        <div class="home-page-settings__grid mb-5">
          <AppTextField
            v-model="form.includesTitle"
            label="عنوان القسم"
            outlined
            dense
            hide-details="auto"
            class="home-page-settings__field--full"
          />
        </div>

        <div class="home-page-settings__cards">
          <article
            v-for="(item, index) in form.includesItems"
            :key="`include-${index}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                العنصر {{ item.num }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف العنصر"
                @click="deleteItem('includesItems', index)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>
            <AppTextField
              v-model="item.title"
              label="النص الظاهر"
              outlined
              dense
              hide-details="auto"
            />
          </article>
        </div>
      </section>

      <section class="home-page-settings__section">
        <div class="home-page-settings__section-header">
          <h3>المتطلبات وإتمام العرض</h3>
        </div>

        <div class="home-page-settings__grid mb-5">
          <AppTextField
            v-model="form.requirementsTitle"
            label="عنوان المتطلبات"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.recitationTitle"
            label="عنوان إتمام العرض"
            outlined
            dense
            hide-details="auto"
          />
        </div>

        <div class="home-page-settings__cards">
          <article
            v-for="(item, index) in form.requirements"
            :key="`requirement-${index}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                المتطلب {{ index + 1 }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف المتطلب"
                @click="deleteItem('requirements', index)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>
            <v-textarea
              v-model="form.requirements[index]"
              label="نص المتطلب"
              outlined
              rows="3"
              auto-grow
              hide-details="auto"
            />
          </article>
        </div>

        <div class="home-page-settings__cards mt-5">
          <article
            v-for="(item, index) in form.recitation"
            :key="`recitation-${index}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                بطاقة العرض {{ index + 1 }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف بطاقة العرض"
                @click="deleteItem('recitation', index)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>
            <div class="home-page-settings__grid">
              <AppTextField
                v-model="item.tag"
                label="التصنيف"
                outlined
                dense
                hide-details="auto"
              />
              <AppTextField
                v-model="item.text"
                label="النص"
                outlined
                dense
                hide-details="auto"
              />
            </div>
          </article>
        </div>

        <div class="home-page-settings__grid mt-5 mb-5">
          <AppTextField
            v-model="form.recitationMechanismTitle"
            label="عنوان آلية عرض القرآن"
            outlined
            dense
            hide-details="auto"
          />
        </div>

        <div class="home-page-settings__cards">
          <article
            v-for="(item, index) in form.recitationMechanismItems"
            :key="`recitation-mechanism-${index}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                بند آلية العرض {{ index + 1 }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف بند آلية العرض"
                @click="deleteItem('recitationMechanismItems', index)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>
            <v-textarea
              v-model="form.recitationMechanismItems[index]"
              label="نص البند"
              outlined
              rows="3"
              auto-grow
              hide-details="auto"
            />
          </article>
        </div>
      </section>

      <section class="home-page-settings__section">
        <div class="home-page-settings__section-header">
          <h3>مدة البرنامج والبداية</h3>
        </div>

        <div class="home-page-settings__grid mb-5">
          <AppTextField
            v-model="form.durationTitle"
            label="عنوان مدة البرنامج"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.startDatesTitle"
            label="عنوان البداية"
            outlined
            dense
            hide-details="auto"
          />
          <v-textarea
            v-model="form.durationDescriptionPrimary"
            label="الوصف الأول لمدة البرنامج"
            outlined
            rows="4"
            auto-grow
            hide-details="auto"
            class="home-page-settings__field--full"
          />
          <v-textarea
            v-model="form.durationDescriptionSecondary"
            label="الوصف الثاني لمدة البرنامج"
            outlined
            rows="4"
            auto-grow
            hide-details="auto"
            class="home-page-settings__field--full"
          />
        </div>

        <div class="home-page-settings__cards">
          <article
            v-for="(item, index) in form.durationQuickInfo"
            :key="`duration-info-${index}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                بطاقة المدة {{ index + 1 }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف بطاقة المدة"
                @click="deleteItem('durationQuickInfo', index)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>
            <div class="home-page-settings__grid">
              <AppTextField
                v-model="item.label"
                label="العنوان"
                outlined
                dense
                hide-details="auto"
              />
              <AppTextField
                v-model="item.value"
                label="القيمة"
                outlined
                dense
                hide-details="auto"
              />
            </div>
          </article>
        </div>

        <div class="home-page-settings__cards mt-5">
          <article
            v-for="(item, index) in form.startDates"
            :key="`start-date-${index}`"
            class="home-page-settings__card"
          >
            <div class="home-page-settings__card-head">
              <div class="home-page-settings__card-title">
                تاريخ البداية {{ index + 1 }}
              </div>
              <v-btn
                icon
                small
                color="error"
                aria-label="حذف تاريخ البداية"
                @click="deleteItem('startDates', index)"
              >
                <v-icon small>
                  mdi-delete-outline
                </v-icon>
              </v-btn>
            </div>
            <div class="home-page-settings__grid">
              <AppTextField
                v-model="item.tag"
                label="التصنيف"
                outlined
                dense
                hide-details="auto"
              />
              <AppTextField
                v-model="item.text"
                label="التاريخ"
                outlined
                dense
                hide-details="auto"
              />
            </div>
          </article>
        </div>
      </section>

      <section class="home-page-settings__section">
        <div class="home-page-settings__section-header">
          <h3>التذييل وتسجيل الدخول</h3>
        </div>

        <div class="home-page-settings__grid">
          <AppTextField
            v-model="form.footerBrandTitle"
            label="اسم البرنامج في التذييل"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.footerQuickLinksTitle"
            label="عنوان الروابط السريعة"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.footerContactTitle"
            label="عنوان التواصل"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.footerAddress"
            label="العنوان"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.footerPhone"
            label="الهاتف"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.footerPoliciesTitle"
            label="عنوان السياسات"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.footerPrivacyLabel"
            label="سياسة الخصوصية"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.footerTermsLabel"
            label="الشروط والأحكام"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.footerDevelopedBy"
            label="نص التطوير"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.loginDialogTitle"
            label="عنوان نافذة الدخول"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.loginCodeLabel"
            label="حقل رقم الدخول"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.loginPasswordLabel"
            label="حقل كلمة المرور"
            outlined
            dense
            hide-details="auto"
          />
          <AppTextField
            v-model="form.loginSubmitLabel"
            label="زر الدخول"
            outlined
            dense
            hide-details="auto"
          />
          <v-textarea
            v-model="form.footerDescription"
            label="وصف التذييل"
            outlined
            rows="4"
            auto-grow
            hide-details="auto"
            class="home-page-settings__field--full"
          />
          <AppTextField
            v-model="form.footerCopyright"
            label="نص الحقوق"
            outlined
            dense
            hide-details="auto"
            class="home-page-settings__field--full"
          />
        </div>
      </section>
    </div>

    <div class="home-page-settings__footer">
      <AppButton
        :loading="saving"
        @click="saveContent"
      >
        حفظ نصوص رخصة ممارس
      </AppButton>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import { AppButton, AppTextField } from '../components/ui';
import { updatePractitionerPageContent } from '../services/api';
import { clonePractitionerPageContent } from '../utils/practitionerPageContent';

export default {
  name: 'AdminHomePageSettingsView',
  components: {
    AppButton,
    AppTextField,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      form: clonePractitionerPageContent(),
      saving: false,
      errorMessage: '',
      successMessage: '',
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot']),
  },
  watch: {
    'dashboardSnapshot.practitionerPageContent': {
      immediate: true,
      handler(nextValue) {
        this.form = clonePractitionerPageContent(nextValue || null);
      },
    },
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot']),
    async deleteItem(key, index) {
      const items = this.form?.[key];

      if (!Array.isArray(items) || index < 0 || index >= items.length) {
        return;
      }

      if (!window.confirm('سيتم حذف هذا المربع نهائياً من الواجهة. هل تريد المتابعة؟')) {
        return;
      }

      items.splice(index, 1);
      await this.saveContent();
    },
    async saveContent() {
      this.saving = true;
      this.errorMessage = '';
      this.successMessage = '';

      try {
        await updatePractitionerPageContent(this.form);
        await this.loadDashboardSnapshot();
        this.successMessage = 'تم حفظ نصوص صفحة رخصة ممارس.';
      } catch (error) {
        this.errorMessage = error?.response?.data?.message || 'تعذر حفظ نصوص صفحة رخصة ممارس.';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
.home-page-settings {
  display: grid;
  gap: 20px;
}

.home-page-settings__section,
.home-page-settings__footer {
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 24px;
  background: #ffffff;
  box-shadow: 0 18px 38px rgba(15, 23, 42, 0.06);
}

.home-page-settings__sections {
  display: grid;
  gap: 18px;
}

.home-page-settings__section {
  padding: 22px;
}

.home-page-settings__section-header {
  margin-bottom: 16px;
}

.home-page-settings__section-header h3,
.home-page-settings__card-title {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 900;
}

.home-page-settings__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.home-page-settings__field--full {
  grid-column: 1 / -1;
}

.home-page-settings__cards {
  display: grid;
  gap: 14px;
}

.home-page-settings__card {
  border: 1px solid rgba(148, 163, 184, 0.16);
  border-radius: 20px;
  background: linear-gradient(180deg, #fbfdff 0%, #f6fafc 100%);
  padding: 18px;
}

.home-page-settings__card-title {
  margin-bottom: 14px;
}

.home-page-settings__card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 14px;
}

.home-page-settings__card-head .home-page-settings__card-title {
  margin-bottom: 0;
}

.home-page-settings__footer {
  display: flex;
  justify-content: flex-start;
  direction: ltr;
  padding: 18px 24px;
}

@media (max-width: 960px) {
  .home-page-settings__grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
