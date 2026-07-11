<template>
  <div class="landing-page">
    <header
      class="landing-header"
      :class="{ 'landing-header--scrolled': scrolled }"
    >
      <div class="landing-shell landing-header__inner">
        <a
          href="#home"
          class="brand-mark"
        >
          <div class="brand-mark__logos">
            <img
              :src="$publicAsset('شعار-الجمعية.png')"
              alt="شعار الجمعية"
              class="site-logo"
              :class="scrolled ? 'site-logo--scrolled' : 'site-logo--top'"
            >
            <img
              :src="$publicAsset('اللوقو-شفاف.png')"
              alt="شعار برنامج رخصة ممارس"
              class="site-logo"
              :class="scrolled ? 'site-logo--scrolled' : 'site-logo--top'"
            >
          </div>
          <div
            class="brand-mark__text"
            :class="{ 'brand-mark__text--light': !scrolled }"
          >
            <div class="brand-mark__title">{{ pageContent.brandTitle }}</div>
          </div>
        </a>

        <nav class="landing-nav">
          <a
            v-for="item in pageNavItems"
            :key="item.href"
            :href="item.href"
          >{{ item.label }}</a>
        </nav>

        <div class="landing-header__actions">
          <div
            class="account-menu account-menu--header"
            @click.stop
          >
            <AppIconButton
              variant="plain"
              class="header-icon-link"
              :aria-label="isAuthenticated ? 'الحساب' : 'التسجيل'"
              @click="handleAccountClick"
            >
              <svg
                viewBox="0 0 24 24"
                class="header-icon-svg"
                aria-hidden="true"
              >
                <circle
                  cx="12"
                  cy="8"
                  r="3.25"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.9"
                />
                <path
                  d="M6.75 18.25c0-2.7 2.35-4.75 5.25-4.75s5.25 2.05 5.25 4.75"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.9"
                  stroke-linecap="round"
                />
              </svg>
            </AppIconButton>

            <transition name="mobile-menu-fade">
              <div
                v-if="accountMenuOpen && isAuthenticated"
                class="account-menu__panel account-menu__panel--header"
              >
                <AppButton
                  variant="plain"
                  class="account-menu__name"
                  @click="openProfileDialog"
                >
                  {{ profileName }}
                </AppButton>
                <AppButton
                  v-for="item in accountMenuItems"
                  :key="item.key"
                  variant="plain"
                  class="account-menu__item"
                  :class="{
                    'account-menu__item--danger': item.danger,
                    'account-menu__item--disabled': item.disabled,
                  }"
                  :disabled="item.disabled"
                  @click="handleAccountMenuAction(item)"
                >
                  {{ item.label }}
                </AppButton>
              </div>
            </transition>
          </div>

          <v-menu
            offset-y
            bottom
            :nudge-bottom="28"
            min-width="250"
            transition="slide-y-transition"
            content-class="licenses-menu"
          >
            <template #activator="{ on, attrs }">
              <AppIconButton
                variant="plain"
                class="licenses-menu-toggle"
                aria-label="البرامج والرخص"
                v-bind="attrs"
                v-on="on"
              >
                <span
                  class="licenses-menu-toggle__bars"
                  aria-hidden="true"
                >
                  <span class="licenses-menu-toggle__bar" />
                  <span class="licenses-menu-toggle__bar" />
                  <span class="licenses-menu-toggle__bar" />
                </span>
              </AppIconButton>
            </template>
            <v-list class="licenses-list">
              <v-list-item disabled>
                <div class="licenses-menu-header">
                  اختر الرخصة المهنية
                </div>
              </v-list-item>
              <v-divider />
              <v-list-item
                v-for="program in licensePrograms"
                :key="program.key"
                class="license-item"
                :disabled="!program.available"
                @click="openProgram(program)"
              >
                <v-list-item-icon>
                  <v-icon :color="program.available ? 'var(--app-primary)' : 'grey'">
                    {{ program.available ? program.icon : 'mdi-lock-outline' }}
                  </v-icon>
                </v-list-item-icon>
                <v-list-item-content>
                  <v-list-item-title>{{ program.title }}</v-list-item-title>
                  <v-list-item-subtitle v-if="program.menuSubtitle">
                    {{ program.menuSubtitle }}
                  </v-list-item-subtitle>
                </v-list-item-content>
              </v-list-item>
            </v-list>
          </v-menu>
        </div>
      </div>
    </header>

    <section
      id="home"
      class="hero-section"
    >
      <div class="hero-orbit hero-orbit--large" />
      <div class="hero-orbit hero-orbit--medium" />
      <div class="hero-glow" />
      <div class="hero-grid" />
      <div class="hero-top-shade" />

      <div class="landing-shell hero-section__inner">
        <div class="hero-copy">
          <div class="hero-logos">
            <img
              :src="$publicAsset('شعار-الجمعية.png')"
              alt="شعار الجمعية"
              class="hero-logo hero-logo--association"
            >
            <img
              :src="$publicAsset('اللوقو-شفاف.png')"
              alt="شعار برنامج رخصة ممارس"
              class="hero-logo"
            >
          </div>
          <div class="hero-divider" />
          <h1 class="hero-title">
            {{ pageContent.heroTitle }}
          </h1>
          <p class="hero-text">
            {{ pageContent.heroText }}
          </p>
          <div class="hero-actions">
            <AppButton
              variant="plain"
              href="#about"
              class="hero-outline-btn"
            >
              {{ pageContent.heroSecondaryButtonLabel }}
              <v-icon small>
                mdi-arrow-left
              </v-icon>
            </AppButton>
          </div>
        </div>
      </div>

      <div class="hero-bottom-fade" />
    </section>

    <main>
      <section
        id="about"
        class="content-section content-section--soft"
      >
        <div class="landing-shell narrow-shell about-shell">
          <div class="section-pill section-pill--center">
            <span class="section-pill__dot" />
            {{ pageContent.aboutEyebrow }}
          </div>
          <h2 class="section-heading">
            {{ pageContent.aboutTitlePrefix }} <span>{{ pageContent.aboutTitleHighlight }}</span>
          </h2>
          <p class="section-lead">
            {{ pageContent.aboutLead }}
          </p>
          <p class="section-copy section-copy--center">
            {{ pageContent.aboutBody }}
          </p>

          <div class="goals-panel">
            <h3 class="sub-heading">
              {{ pageContent.goalsHeadingPrefix }} <span>{{ pageContent.goalsHeadingHighlight }}</span>
            </h3>
            <ul class="goals-grid">
              <li
                v-for="goal in pageGoals"
                :key="goal"
                class="goal-card"
              >
                <p>{{ goal }}</p>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <section class="content-section content-section--soft stats-section">
        <div
          ref="programStatsSection"
          class="landing-shell landing-shell--wide section-layer"
        >
          <div class="stats-heading">
            <h2 class="our-numbers__title">
              أرقامنا
            </h2>
          </div>

          <div class="our-numbers__graduates our-numbers__graduates--program">
            <article
              v-for="indicator in programIndicators"
              :key="indicator.key"
              class="our-numbers__graduate-item"
            >
              <strong>{{ animatedProgramStatValue(indicator.display) }}</strong>
              <span>
                {{ indicator.label }}
              </span>
            </article>
          </div>
        </div>
      </section>

      <section
        id="competencies"
        class="content-section content-section--pattern content-section--decorated"
      >
        <div class="decor-blob decor-blob--top-right" />
        <div class="decor-blob decor-blob--bottom-left" />

        <div class="landing-shell section-layer">
          <div class="section-ribbon section-ribbon--dark">
            <h2>{{ pageContent.competenciesTitle }}</h2>
          </div>

          <div class="domains-grid">
            <article
              v-for="domain in pageDomains"
              :key="domain.title"
              class="domain-card"
            >
              <div class="domain-card__header">
                <div class="domain-card__title-wrap">
                  <span class="domain-card__bar" />
                  <h3>{{ domain.title }}</h3>
                </div>
                <div class="domain-card__icon">
                  <v-icon>{{ domain.icon }}</v-icon>
                </div>
              </div>
              <ul>
                <li
                  v-for="(item, index) in domain.items"
                  :key="item"
                >
                  <span class="domain-card__index">{{ String(index + 1).padStart(2, '0') }}</span>
                  <span>{{ item }}</span>
                </li>
              </ul>
            </article>
          </div>
        </div>
      </section>

      <section
        id="includes"
        class="content-section content-section--soft content-section--rings"
      >
        <div class="rings-layer">
          <div class="rings-layer__ring rings-layer__ring--outer" />
          <div class="rings-layer__ring rings-layer__ring--inner" />
        </div>

        <div class="landing-shell section-layer">
          <div class="section-ribbon">
            <h2>{{ pageContent.includesTitle }}</h2>
          </div>

          <div class="includes-grid">
            <article
              v-for="item in pageIncludesItems"
              :key="item.num"
              class="include-card"
            >
              <div class="include-card__badge">
                {{ item.num }}
              </div>
              <h3>{{ item.title }}</h3>
              <div class="include-card__line" />
            </article>
          </div>
        </div>
      </section>

      <section
        id="requirements"
        class="content-section content-section--pattern content-section--decorated"
      >
        <div class="decor-blob decor-blob--top-left" />
        <div class="decor-blob decor-blob--bottom-right" />

        <div class="landing-shell section-layer">
          <div class="section-ribbon section-ribbon--dark">
            <h2>{{ pageContent.requirementsTitle }}</h2>
          </div>

          <div class="requirements-list">
            <article
              v-for="item in pageRequirements"
              :key="item.text"
              class="requirement-row"
            >
              <div class="requirement-row__icon">
                <v-icon>{{ item.icon }}</v-icon>
              </div>
              <p>{{ item.text }}</p>
            </article>
          </div>

          <div class="recitation-panel">
            <div class="recitation-panel__heading">
              <span class="recitation-panel__bar" />
              <h3>{{ pageContent.recitationTitle }}</h3>
            </div>
            <div class="recitation-grid recitation-grid--split">
              <article
                v-for="item in pageRecitation"
                :key="item.tag"
                class="recitation-card"
              >
                <span class="recitation-card__tag">{{ item.tag }}</span>
                <div class="recitation-card__body">
                  <div class="recitation-card__icon recitation-card__icon--light">
                    <v-icon>{{ item.icon }}</v-icon>
                  </div>
                  <div>{{ item.text }}</div>
                </div>
              </article>
            </div>
          </div>

          <div class="recitation-mechanism">
            <div class="recitation-mechanism__header">
              <div class="recitation-mechanism__icon">
                <v-icon>mdi-book-open-variant</v-icon>
              </div>
              <h3>{{ pageContent.recitationMechanismTitle }}</h3>
            </div>
            <div class="recitation-mechanism__list">
              <article
                v-for="(item, index) in pageContent.recitationMechanismItems"
                :key="`${index}-${item}`"
                class="recitation-mechanism__item"
              >
                <span class="recitation-mechanism__bullet">{{ index + 1 }}</span>
                <p>{{ item }}</p>
              </article>
            </div>
          </div>
        </div>
      </section>

      <section
        id="duration"
        class="content-section content-section--soft content-section--decorated"
      >
        <div class="decor-blob decor-blob--top-right" />

        <div class="landing-shell narrow-shell section-layer">
          <div class="section-ribbon">
            <h2>{{ pageContent.durationTitle }}</h2>
          </div>

          <div class="duration-quick-grid">
            <article
              v-for="item in pageDurationQuickInfo"
              :key="item.label"
              class="duration-quick-card"
            >
              <span class="duration-quick-card__tag">{{ item.label }}</span>
              <div class="duration-quick-card__body">
                <div class="duration-quick-card__value">
                  {{ item.value }}
                </div>
              </div>
            </article>
          </div>

          <div class="duration-text-card">
            <p>
              {{ pageContent.durationDescriptionPrimary }}
            </p>
            <p>
              {{ pageContent.durationDescriptionSecondary }}
            </p>
          </div>

          <div class="start-dates-section">
            <div class="start-dates-heading">
              <span />
              <h3>{{ pageContent.startDatesTitle }}</h3>
              <span />
            </div>
            <div class="start-dates-list">
              <article
                v-for="date in pageStartDates"
                :key="date.tag"
                class="start-date-card"
              >
                <span class="start-date-card__tag">{{ date.tag }}</span>
                <div class="start-date-card__body">
                  <div class="start-date-card__icon start-date-card__icon--light">
                    <v-icon>mdi-calendar-month</v-icon>
                  </div>
                  <div>{{ date.text }}</div>
                </div>
              </article>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="landing-footer">
      <div class="landing-shell landing-footer__grid">
        <div>
          <div class="footer-brand">
            <img
              :src="$publicAsset('اللوقو-شفاف.png')"
              alt="شعار برنامج رخصة ممارس"
              class="footer-brand__logo"
            >
            <div class="footer-brand__title">
              {{ pageContent.footerBrandTitle }}
            </div>
          </div>
          <p class="footer-text">
            {{ pageContent.footerDescription }}
          </p>
        </div>

        <div>
          <h4>{{ pageContent.footerQuickLinksTitle }}</h4>
          <ul class="footer-links">
            <li
              v-for="item in pageNavItems"
              :key="item.href"
            >
              <a :href="item.href">{{ item.label }}</a>
            </li>
          </ul>
        </div>

        <div>
          <h4>{{ pageContent.footerContactTitle }}</h4>
          <ul class="footer-links footer-links--contact">
            <li>
              <v-icon small>
                mdi-map-marker-outline
              </v-icon><span>{{ pageContent.footerAddress }}</span>
            </li>
            <li>
              <v-icon small>
                mdi-phone-outline
              </v-icon><span dir="ltr">{{ pageContent.footerPhone }}</span>
            </li>
          </ul>
        </div>

        <div>
          <h4>{{ pageContent.footerPoliciesTitle }}</h4>
          <ul class="footer-links">
            <li><span class="footer-links__muted">{{ pageContent.footerPrivacyLabel }}</span></li>
            <li><span class="footer-links__muted">{{ pageContent.footerTermsLabel }}</span></li>
          </ul>
        </div>
      </div>

      <div class="landing-shell landing-footer__bottom">
        <div>© {{ currentYear }} {{ pageContent.footerCopyright }}</div>
        <div class="landing-footer__developed">
          {{ pageContent.footerDevelopedBy }}
        </div>
      </div>
    </footer>

    <AppDialog
      v-model="loginDialogOpen"
      max-width="560"
      @close="closeLoginDialog"
    >
      <section class="login-modal__panel">
        <div class="login-modal__brand">
          <img
            :src="$publicAsset('شعار-الجمعية.png')"
            alt="شعار الجمعية"
            class="login-modal__logo"
          >
          <img
            :src="$publicAsset('اللوقو-شفاف.png')"
            alt="شعار البرنامج"
            class="login-modal__logo login-modal__logo--program"
          >
        </div>

        <div class="login-modal__copy">
          <h3>{{ pageContent.loginDialogTitle }}</h3>
        </div>

        <v-alert
          v-if="authError"
          type="error"
          dense
          text
          class="login-modal__alert"
        >
          {{ authError }}
        </v-alert>

        <v-form
          class="login-modal__form"
          @submit.prevent="submitLoginDialog"
        >
          <v-text-field
            v-model.trim="loginForm.loginCode"
            class="login-modal__field"
            :label="pageContent.loginCodeLabel"
            outlined
            dense
            autocomplete="username"
          />
          <v-text-field
            v-model="loginForm.password"
            class="login-modal__field"
            :type="showLoginPassword ? 'text' : 'password'"
            :label="pageContent.loginPasswordLabel"
            outlined
            dense
            autocomplete="current-password"
            :append-icon="showLoginPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
            @click:append="showLoginPassword = !showLoginPassword"
          />
          <AppButton
            native-type="submit"
            block
            class="login-modal__submit"
            :loading="authLoading"
          >
            {{ pageContent.loginSubmitLabel }}
          </AppButton>
        </v-form>
      </section>
    </AppDialog>

    <AppDialog
      v-model="profileDialogOpen"
      max-width="520"
      @close="closeProfileDialog"
    >
      <section
        v-if="currentUser"
        class="profile-modal__panel"
      >
        <div class="profile-modal__card">
          <div class="profile-modal__label">
            الاسم
          </div>
          <div class="profile-modal__value">
            {{ profileName }}
          </div>
        </div>

        <div class="profile-modal__card">
          <div class="profile-modal__label">
            نوع الحساب
          </div>
          <div class="profile-modal__value">
            {{ accountRoleLabel }}
          </div>
        </div>

        <div class="profile-modal__card">
          <div class="profile-modal__label">
            رقم الدخول
          </div>
          <div class="profile-modal__value">
            {{ currentUser.loginCode }}
          </div>
        </div>
      </section>
    </AppDialog>
  </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex';
import { AppButton, AppDialog, AppIconButton } from '../components/ui';
import { fetchPublicRegistrationStatus, fetchPublicSnapshot, fetchPublicStats } from '../services/api';
import { normalizePractitionerPageContent } from '../utils/practitionerPageContent';
import { resolveUserHomeLabel, resolveUserHomeRoute } from '../utils/authRoutes';

const PRACTITIONER_NAV_LINKS = ['#about', '#competencies', '#requirements'];
const PRACTITIONER_PROGRAM_INDICATORS = [
  { key: 'memorization', label: 'مجموع الأجزاء المقروءة', display: '1820+', progress: 100 },
  { key: 'attendance', label: 'الحضور', display: '95%', progress: 95 },
  { key: 'assessments', label: 'اختبار قبلي وبعدي', display: '1920+', progress: 100 },
  { key: 'courses', label: 'دورة', display: '24', progress: 100 },
  { key: 'tasks', label: 'المهام الأدائية', display: '630+', progress: 100 },
  { key: 'completed30', label: 'عدد خريجي هذه الدفعة معلم ومعلمة', display: '110', progress: 100 },
];
const DISPLAY_NUMBER_PATTERN = /^(.*?)([+-]?\d+(?:\.\d+)?)([^\d]*)$/;
const easeOutCubic = (value) => 1 - ((1 - value) ** 3);

export default {
  name: 'PractitionerView',
  components: {
    AppDialog,
    AppButton,
    AppIconButton,
  },
  data() {
    return {
      scrolled: false,
      accountMenuOpen: false,
      profileDialogOpen: false,
      currentTimestamp: Date.now(),
      statsAnimationProgress: 0,
      statsAnimationFrameId: null,
      statsAnimationStarted: false,
      statsSectionVisible: false,
      menuClockTimer: null,
      publicAutoRefreshTimer: null,
      loginDialogOpen: false,
      isRegistrationOpen: false,
      publicSnapshot: null,
      publicStats: null,
      showLoginPassword: false,
      loginRedirectPath: '',
      loginForm: {
        loginCode: '',
        password: '',
      },
      navItems: [
        { href: '#about', label: 'عن البرنامج' },
        { href: '#competencies', label: 'مجالات وكفايات البرنامج' },
        { href: '#requirements', label: 'المتطلبات' },
      ],
      licensePrograms: [
        {
          key: 'licenses',
          title: 'الرخص المهنية',
          menuSubtitle: '',
          available: true,
          route: { name: 'home', hash: '#programs' },
          icon: 'mdi-view-grid-outline',
        },
        {
          key: 'manager',
          title: 'رخصة مدير',
          menuSubtitle: '',
          available: false,
          route: null,
          icon: 'mdi-briefcase-outline',
        },
        {
          key: 'supervisor',
          title: 'رخصة مشرف',
          menuSubtitle: '',
          available: false,
          route: null,
          icon: 'mdi-eye-outline',
        },
        {
          key: 'secretary',
          title: 'رخصة سكرتير',
          menuSubtitle: '',
          available: false,
          route: null,
          icon: 'mdi-clipboard-text-outline',
        },
      ],
      goals: [
        'التعرّف على أهمية العلم الشرعي وأهم مسائل العقيدة والطهارة والصلاة',
        'إتقان أساسيات تعليم القرآن الكريم ومبادئ علم التجويد',
        'توظيف الأساليب التربوية المناسبة في التعامل مع الطلاب',
        'استحضار أهمية الرسالة التعليمية والالتزام بها',
        'تطبيق مهارات التواصل والتخطيط في البيئة التعليمية',
        'إدارة الحلقة القرآنية وتنظيمها بكفاءة',
      ],
      domains: [
        {
          title: 'كفايات المجال الشرعي',
          icon: 'mdi-book-open-page-variant',
          items: [
            'أهمية العلم الشرعي وأثره في حياة المعلم/ة',
            'أهم مسائل التوحيد والإيمان',
            'أهم مسائل الطهارة',
            'الأحكام العامة للصلاة',
          ],
        },
        {
          title: 'كفايات المجال التعليمي',
          icon: 'mdi-school-outline',
          items: [
            'مبادئ أحكام التجويد نظريًا وتطبيقيًا',
            'استراتيجيات تعليم القرآن الكريم',
            'مباحث وآداب قرآنية',
          ],
        },
        {
          title: 'كفايات المجال التربوي',
          icon: 'mdi-hand-heart-outline',
          items: [
            'مدخل في التربية وأهميتها وخصائصها',
            'خصائص المراحل العمرية واحتياجاتها',
            'الأساليب التربوية',
            'بناء القيم وتعزيز السلوك',
            'الصحة النفسية في البيئة التعليمية',
          ],
        },
        {
          title: 'كفايات المجال المهاري',
          icon: 'mdi-star-four-points-outline',
          items: [
            'مهارات التواصل الفعال',
            'إدارة الحلقة القرآنية',
            'تكامل شخصية المعلم',
            'مهارات التخطيط',
            'التعامل مع النظام التقني (ناظم)',
            'الدور الاستراتيجي للمعلم والمعلمة',
          ],
        },
      ],
      includesItems: [
        { num: '01', title: 'لقاءات تدريبية حضورية', icon: 'mdi-school' },
        { num: '02', title: 'مهام أدائية تطبيقية', icon: 'mdi-clipboard-check-outline' },
        { num: '03', title: 'عرض القرآن', icon: 'mdi-book-open-blank-variant' },
        { num: '04', title: 'اختبارات قبلية وبعدية', icon: 'mdi-file-document-check-outline' },
        { num: '05', title: 'اختبار نهائي', icon: 'mdi-trophy-outline' },
      ],
      requirements: [
        { text: 'حضور ما لا يقل عن (10) لقاءات من اللقاءات التدريبية', icon: 'mdi-calendar-check-outline' },
        { text: 'تنفيذ (80%) من المهام الأدائية', icon: 'mdi-clipboard-check-multiple-outline' },
        { text: 'اجتياز الاختبار النهائي بنسبة لا تقل عن (70%)', icon: 'mdi-text-box-check-outline' },
        { text: 'الالتزام بآداب وأخلاقيات تعليم القرآن الكريم', icon: 'mdi-heart-outline' },
      ],
      recitation: [
        { tag: 'الرجال', text: 'المعلمون: عرض كامل القرآن', icon: 'mdi-book-open-page-variant-outline' },
        { tag: 'النساء', text: 'المعلمات: عرض (15) جزءًا', icon: 'mdi-book-open-page-variant-outline' },
      ],
      durationQuickInfo: [
        { label: 'المدة', value: 'ستة أسابيع', icon: 'mdi-clock-outline' },
        { label: 'التكرار', value: 'دورتان أسبوعيًا', icon: 'mdi-calendar-week' },
        { label: 'آلية التنفيذ', value: 'حضوريًا', icon: 'mdi-map-marker-outline' },
      ],
      startDates: [
        { tag: 'الرجال', text: 'يوم الإثنين 18 / 10 / 1447هـ' },
        { tag: 'النساء', text: 'يوم السبت 23 / 10 / 1447هـ' },
      ],
    };
  },
  computed: {
    ...mapState(['dashboardSnapshot', 'authError', 'authLoading', 'currentUser']),
    ...mapGetters(['isAuthenticated']),
    currentYear() {
      return new Date().getFullYear();
    },
    pageContent() {
      return normalizePractitionerPageContent(this.publicSnapshot?.practitionerPageContent || this.dashboardSnapshot?.practitionerPageContent || null);
    },
    pageNavItems() {
      return this.pageContent.navItems.map((item, index) => ({
        href: PRACTITIONER_NAV_LINKS[index] || '#about',
        label: item.label,
      }));
    },
    pageGoals() {
      return this.pageContent.goals;
    },
    pageDomains() {
      return this.pageContent.domains.map((domain, index) => ({
        ...domain,
        icon: this.domains[index]?.icon || 'mdi-book-open-page-variant',
      }));
    },
    pageIncludesItems() {
      return this.pageContent.includesItems.map((item, index) => ({
        ...item,
        icon: this.includesItems[index]?.icon || 'mdi-check',
      }));
    },
    pageRequirements() {
      return this.pageContent.requirements.map((text, index) => ({
        text,
        icon: this.requirements[index]?.icon || 'mdi-check-circle-outline',
      }));
    },
    pageRecitation() {
      return this.pageContent.recitation.map((item, index) => ({
        ...item,
        icon: this.recitation[index]?.icon || 'mdi-book-open-page-variant-outline',
      }));
    },
    pageDurationQuickInfo() {
      return this.pageContent.durationQuickInfo.map((item, index) => ({
        ...item,
        icon: this.durationQuickInfo[index]?.icon || 'mdi-clock-outline',
      }));
    },
    pageStartDates() {
      return this.pageContent.startDates;
    },
    licenseShortcutItems() {
      return [
        { key: 'home', label: 'العودة للرئيسية', icon: 'mdi-home-outline', to: { name: 'home', hash: '#home' } },
        { key: 'licenses', label: 'الرخص المهنية', icon: 'mdi-view-grid-outline', to: { name: 'home', hash: '#programs' } },
        { key: 'practitioner', label: 'رخصة ممارس', icon: 'mdi-certificate-outline', to: { name: 'practitioner' } },
        { key: 'manager', label: 'رخصة مدير', icon: 'mdi-lock-outline', disabled: true },
        { key: 'supervisor', label: 'رخصة مشرف', icon: 'mdi-lock-outline', disabled: true },
        { key: 'secretary', label: 'رخصة سكرتير', icon: 'mdi-lock-outline', disabled: true },
        ...this.pageNavItems.map((item) => ({
          key: item.href,
          label: item.label,
          icon: 'mdi-arrow-top-left',
          href: item.href,
        })),
      ];
    },
    profileName() {
      return this.currentUser?.name || this.currentUser?.full_name || 'الحساب';
    },
    accountRoleLabel() {
      const labels = {
        admin: 'مدير النمو المهني',
        male_manager: 'مشرف',
        female_manager: 'مشرفة',
        reciter: 'مقرئ',
        student: 'طالب',
        trainee: 'معلم',
      };

      return labels[this.currentUser?.role] || 'مستخدم';
    },
    currentStudentRecord() {
      if (this.currentUser?.role !== 'student') {
        return null;
      }

      const loginCode = String(this.currentUser?.loginCode || '').trim();
      return (this.dashboardSnapshot?.students || []).find((student) => student.loginId === loginCode) || null;
    },
    currentStudentBranchId() {
      return this.currentStudentRecord?.branchId || '';
    },
    currentStudentCourse() {
      return ((this.dashboardSnapshot?.courses || []).filter((course) => course.entityType !== 'task')).find((course) => course.isActive) || null;
    },
    studentOpenTask() {
      return ((this.dashboardSnapshot?.courses || [])
        .filter((course) => course.entityType === 'task')
        .sort((left, right) => Number(left.sortOrder || 0) - Number(right.sortOrder || 0)))
        .find((task) => this.isStudentTaskEnabled(task)) || null;
    },
    isStudentPreEnabled() {
      return this.isStudentAssessmentEnabled('pre');
    },
    isStudentPostEnabled() {
      return this.isStudentAssessmentEnabled('post');
    },
    isStudentTasksEnabled() {
      return Boolean(this.studentOpenTask);
    },
    isStudentFinalExamEnabled() {
      if (!this.currentStudentBranchId) {
        return false;
      }

      const branchSetting = this.dashboardSnapshot?.finalExamSettings?.[this.currentStudentBranchId] || { isEnabled: false, closesAt: null };

      if (!branchSetting.isEnabled || !branchSetting.closesAt) {
        return false;
      }

      const closesAt = new Date(branchSetting.closesAt).getTime();
      return Number.isFinite(closesAt) && closesAt > this.currentTimestamp;
    },
    accountMenuItems() {
      if (this.currentUser?.role !== 'student') {
        return [
          { key: 'primary', label: this.primaryRouteLabel, action: 'route', route: this.primaryRoute },
          { key: 'logout', label: 'تسجيل الخروج', action: 'logout', danger: true },
        ];
      }

      return [
        { key: 'student', label: 'حسابي', action: 'route', route: { name: 'student' } },
        ...(this.isStudentPreEnabled
          ? [{ key: 'pre', label: 'الاختبار القبلي', action: 'route', route: { name: 'courses', params: { assessmentType: 'pre' } } }]
          : []),
        ...(this.isStudentPostEnabled
          ? [{ key: 'post', label: 'الاختبار البعدي', action: 'route', route: { name: 'courses', params: { assessmentType: 'post' } } }]
          : []),
        ...(this.isStudentTasksEnabled
          ? [{ key: 'tasks', label: 'المهمة الأدائية', action: 'route', route: this.studentOpenTask ? { name: 'tasks', query: { taskId: this.studentOpenTask.id } } : { name: 'tasks' } }]
          : []),
        ...(this.isStudentFinalExamEnabled
          ? [{ key: 'final-exam', label: 'الاختبار النهائي', action: 'route', route: { name: 'final-exam' } }]
          : []),
        { key: 'logout', label: 'تسجيل الخروج', action: 'logout', danger: true },
      ];
    },
    primaryRoute() {
      return resolveUserHomeRoute(this.currentUser);
    },
    primaryRouteLabel() {
      return resolveUserHomeLabel(this.currentUser);
    },
    programIndicators() {
      return PRACTITIONER_PROGRAM_INDICATORS.map((indicator) => ({
        ...indicator,
        label: this.pageContent.indicatorLabels[indicator.key] || indicator.label,
      }));
    },
    siteStats() {
      const s = this.publicStats;
      return [
        {
          key: 'graduates',
          value: s ? s.graduates : null,
          suffix: '+',
          label: 'المتخرجين',
        },
        {
          key: 'satisfaction',
          value: s ? s.satisfactionRate : null,
          suffix: '%',
          label: 'نسبة الرضا',
        },
        {
          key: 'courses',
          value: s ? s.courses : null,
          suffix: '+',
          label: 'عدد الدورات',
        },
      ];
    },
  },
  watch: {
    '$route.query.login': {
      immediate: true,
      handler() {
        this.syncLoginDialogFromRoute();
      },
    },
  },
  created() {
    this.menuClockTimer = window.setInterval(() => {
      this.currentTimestamp = Date.now();
    }, 1000);

    this.loadPublicData();
    this.loadRegistrationStatus();
    this.publicAutoRefreshTimer = window.setInterval(() => {
      this.refreshAvailabilitySilently();
    }, 2000);

    if (this.isAuthenticated) {
      this.loadDashboardSnapshot();
    }
  },
  mounted() {
    this.handleScroll();
    window.addEventListener('scroll', this.handleScroll, { passive: true });
    window.addEventListener('resize', this.handleResize, { passive: true });
    window.addEventListener('click', this.handleWindowClick);
  },
  beforeDestroy() {
    if (this.statsAnimationFrameId && typeof window !== 'undefined') {
      window.cancelAnimationFrame(this.statsAnimationFrameId);
      this.statsAnimationFrameId = null;
    }

    if (this.menuClockTimer) {
      window.clearInterval(this.menuClockTimer);
      this.menuClockTimer = null;
    }

    if (this.publicAutoRefreshTimer) {
      window.clearInterval(this.publicAutoRefreshTimer);
      this.publicAutoRefreshTimer = null;
    }

    window.removeEventListener('scroll', this.handleScroll);
    window.removeEventListener('resize', this.handleResize);
    window.removeEventListener('click', this.handleWindowClick);
  },
  methods: {
    ...mapActions(['loadDashboardSnapshot', 'login', 'logout']),
    async loadPublicData() {
      try {
        const [snapshot, stats] = await Promise.all([
          fetchPublicSnapshot(),
          fetchPublicStats(),
        ]);

        this.publicSnapshot = snapshot;
        this.publicStats = stats;
      } catch {
        this.publicSnapshot = null;
        this.publicStats = null;
      }
    },
    async loadRegistrationStatus() {
      try {
        const payload = await fetchPublicRegistrationStatus();
        this.isRegistrationOpen = Boolean(payload?.isOpen);
      } catch (error) {
        this.isRegistrationOpen = false;
      }
    },
    async refreshAvailabilitySilently() {
      try {
        if (this.isAuthenticated) {
          await this.loadDashboardSnapshot();
          return;
        }

        await this.loadPublicData();
      } catch {
        // Keep the last known state when background polling fails.
      }
    },
    getWindowMeta(value) {
      if (!value) {
        return { opensAt: '', closesAt: '' };
      }

      if (typeof value === 'string') {
        return { opensAt: '', closesAt: value };
      }

      return {
        opensAt: value.opensAt || '',
        closesAt: value.closesAt || '',
      };
    },
    isWindowActive(value) {
      const windowMeta = this.getWindowMeta(value);

      if (!windowMeta.closesAt) {
        return false;
      }

      const closesAt = new Date(windowMeta.closesAt).getTime();

      if (!Number.isFinite(closesAt) || closesAt <= this.currentTimestamp) {
        return false;
      }

      if (!windowMeta.opensAt) {
        return true;
      }

      const opensAt = new Date(windowMeta.opensAt).getTime();
      return !Number.isFinite(opensAt) || opensAt <= this.currentTimestamp;
    },
    isStudentAssessmentEnabled(type) {
      if (!this.currentStudentCourse || !this.currentStudentBranchId) {
        return false;
      }

      const branchAvailability = this.currentStudentCourse.branchAvailability?.[this.currentStudentBranchId] || {};
      const enabledBySettings = type === 'pre'
        ? Boolean(this.currentStudentCourse.isPreEnabled && branchAvailability.pre !== false)
        : Boolean(this.currentStudentCourse.isPostEnabled && branchAvailability.post !== false);

      if (!enabledBySettings) {
        return false;
      }

      const branchWindow = this.getWindowMeta(this.currentStudentCourse.assessmentWindows?.[this.currentStudentBranchId]?.[type]);
      const globalWindow = this.getWindowMeta(this.currentStudentCourse.assessmentWindows?.global?.[type]);
      const hasWindowConfig = Boolean(branchWindow.closesAt || globalWindow.closesAt);

      if (!hasWindowConfig) {
        return true;
      }

      return this.isWindowActive(branchWindow) || this.isWindowActive(globalWindow);
    },
    isStudentTaskEnabled(task) {
      if (!task || !this.currentStudentBranchId) {
        return false;
      }

      const branchAvailability = task.branchAvailability?.[this.currentStudentBranchId] || {};

      if (!(task.isTasksEnabled && branchAvailability.tasks !== false)) {
        return false;
      }

      const branchWindow = this.getWindowMeta(task.assessmentWindows?.[this.currentStudentBranchId]?.tasks);
      const globalWindow = this.getWindowMeta(task.assessmentWindows?.global?.tasks);
      const hasWindowConfig = Boolean(branchWindow.closesAt || globalWindow.closesAt);

      if (!hasWindowConfig) {
        return true;
      }

      return this.isWindowActive(branchWindow) || this.isWindowActive(globalWindow);
    },
    openProgram(program) {
      if (!program?.available || !program.route) {
        return;
      }

      this.$router.push(program.route).catch(() => {});
    },
    maybeStartStatsAnimation() {
      if (this.statsAnimationStarted || !this.statsSectionVisible) {
        return;
      }

      this.statsAnimationStarted = true;
      this.startStatsAnimation();
    },
    startStatsAnimation(duration = 3600) {
      if (this.statsAnimationFrameId && typeof window !== 'undefined') {
        window.cancelAnimationFrame(this.statsAnimationFrameId);
      }

      if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        this.statsAnimationProgress = 1;
        return;
      }

      const start = window.performance?.now?.() ?? Date.now();
      this.statsAnimationProgress = 0;

      const tick = (timestamp) => {
        const progress = Math.min(1, ((timestamp ?? Date.now()) - start) / duration);
        this.statsAnimationProgress = easeOutCubic(progress);
        this.statsAnimationFrameId = progress < 1 ? window.requestAnimationFrame(tick) : null;
      };

      this.statsAnimationFrameId = window.requestAnimationFrame(tick);
    },
    animatedProgramStatValue(display) {
      const text = String(display ?? '');
      const match = text.match(DISPLAY_NUMBER_PATTERN);

      if (!match) {
        return text;
      }

      const [, prefix, rawValue, suffix] = match;
      const targetValue = Number(rawValue);

      if (!Number.isFinite(targetValue)) {
        return text;
      }

      const decimals = (rawValue.split('.')[1] || '').length;
      const animatedValue = targetValue * this.statsAnimationProgress;
      const roundedValue = decimals > 0
        ? animatedValue.toFixed(decimals)
        : Math.round(animatedValue);
      const formattedValue = new Intl.NumberFormat('ar-SA').format(Number(roundedValue));

      return `${prefix}${formattedValue}${suffix}`;
    },
    handleScroll() {
      this.scrolled = window.scrollY > 20;

      if (!this.statsAnimationStarted) {
        const section = this.$refs.programStatsSection;

        if (section) {
          const bounds = section.getBoundingClientRect();
          const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
          this.statsSectionVisible = bounds.top <= viewportHeight * 0.78 && bounds.bottom >= viewportHeight * 0.22;
          this.maybeStartStatsAnimation();
        }
      }
    },
    handleWindowClick() {
      this.accountMenuOpen = false;
    },
    handleResize() {
    },
    handleAccountClick() {
      if (this.isAuthenticated) {
        this.accountMenuOpen = !this.accountMenuOpen;
        return;
      }

      this.openLoginDialog(this.$route.query.redirect || '');
    },
    openProfileDialog() {
      this.accountMenuOpen = false;
      this.profileDialogOpen = true;
    },
    handleAccountMenuAction(item) {
      if (!item || item.disabled) {
        return;
      }

      if (item.action === 'profile') {
        this.openProfileDialog();
        return;
      }

      if (item.action === 'logout') {
        this.submitLogout();
        return;
      }

      if (item.action === 'route' && item.route) {
        this.accountMenuOpen = false;
        this.profileDialogOpen = false;
        this.$router.push(item.route).catch(() => {});
      }
    },
    closeProfileDialog() {
      this.profileDialogOpen = false;
    },
    goToDashboard() {
      this.accountMenuOpen = false;
      this.profileDialogOpen = false;
      this.$router.push(this.primaryRoute).catch(() => {});
    },
    async submitLogout() {
      this.accountMenuOpen = false;
      this.profileDialogOpen = false;
      await this.logout();
      this.$router.replace({ name: 'practitioner' }).catch(() => {});
    },
    openLoginDialog(redirectPath = '') {
      this.loginRedirectPath = redirectPath || this.$route.query.redirect || '';
      this.loginDialogOpen = true;

      const query = {
        ...this.$route.query,
        login: '1',
      };

      if (this.loginRedirectPath) {
        query.redirect = this.loginRedirectPath;
      }

      this.$router.replace({ name: 'practitioner', query, hash: this.$route.hash }).catch(() => {});
    },
    closeLoginDialog() {
      this.loginDialogOpen = false;
      this.showLoginPassword = false;

      const query = { ...this.$route.query };
      delete query.login;
      delete query.redirect;

      this.$router.replace({ name: 'practitioner', query, hash: this.$route.hash }).catch(() => {});
    },
    syncLoginDialogFromRoute() {
      if (this.isAuthenticated) {
        this.loginDialogOpen = false;
        this.accountMenuOpen = false;
        return;
      }

      if (this.$route.query.login === '1') {
        this.loginDialogOpen = true;
        this.loginRedirectPath = this.$route.query.redirect || '';
      } else {
        this.loginDialogOpen = false;
      }
    },
    async submitLoginDialog() {
      if (!this.loginForm.loginCode || !this.loginForm.password) {
        return;
      }

      try {
        await this.login(this.loginForm);
        const redirectTarget = this.loginRedirectPath || { name: 'practitioner' };
        this.accountMenuOpen = false;
        this.closeLoginDialog();
        this.$router.replace(redirectTarget).catch(() => {});
      } catch {
        // Error text is already handled by the store.
      }
    },
  },
};
</script>

<style scoped>
.landing-page {
  direction: rtl;
  color: #08384a;
  background: linear-gradient(180deg, #f7fcfb 0%, #eef8f7 34%, #f8fcfc 68%, #ffffff 100%);
  font-family: 'Tajawal', 'Segoe UI', Tahoma, Arial, sans-serif;
}

.landing-shell {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
}

.landing-shell--wide {
  width: min(1560px, calc(100% - 32px));
}

.narrow-shell {
  width: min(960px, calc(100% - 32px));
}

.landing-header {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  z-index: 30;
  transition: 0.35s ease;
}

.landing-header--scrolled {
  background: rgba(255, 255, 255, 0.86);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(206, 226, 230, 0.9);
  box-shadow: 0 12px 34px -24px rgba(8, 65, 89, 0.32);
}

.landing-header__inner {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  min-height: 94px;
}

.brand-mark {
  display: inline-flex;
  align-items: center;
  gap: 18px;
  margin-inline-end: 28px;
  text-decoration: none;
  flex-shrink: 0;
  position: relative;
  z-index: 2;
}

.brand-mark__logos {
  display: flex;
  align-items: center;
  gap: 14px;
}

.site-logo {
  width: auto;
  height: 44px;
  object-fit: contain;
  transition: 0.35s ease;
  filter: drop-shadow(0 8px 18px rgba(8, 65, 89, 0.2));
}

.site-logo--top {
  filter: brightness(0) invert(1) drop-shadow(0 8px 18px rgba(0, 0, 0, 0.22));
}

.site-logo--scrolled,
.footer-brand__logo {
  filter: brightness(0) saturate(100%) invert(31%) sepia(53%) saturate(1050%) hue-rotate(154deg) brightness(91%) contrast(94%) drop-shadow(0 8px 18px rgba(8, 65, 89, 0.12));
}

.brand-mark__text {
  text-align: right;
  color: #063041;
  line-height: 1.2;
  white-space: nowrap;
}

.brand-mark__text--light {
  color: #fff;
}

.brand-mark__title {
  font-size: 0.72rem;
  font-weight: 900;
}

.landing-nav {
  display: none;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 0 18px;
  text-align: center;
}

.landing-nav a {
  color: #fff;
  text-decoration: none;
  padding: 12px 20px;
  border-radius: 999px;
  font-size: 1.08rem;
  font-weight: 800;
  transition: 0.24s ease;
}

.landing-header--scrolled .landing-nav a {
  color: #063041;
}

.landing-nav a:hover {
  background: rgba(255, 255, 255, 0.12);
}

.landing-header--scrolled .landing-nav a:hover {
  background: rgba(6, 48, 65, 0.1);
  color: #063041;
}

.landing-header__actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  flex-shrink: 0;
  position: relative;
  z-index: 2;
}

.header-registration-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 112px;
  height: 46px;
  padding: 0 20px;
  border-radius: 999px;
  background: #ffffff;
  color: #0f3554;
  font-size: 0.95rem;
  font-weight: 900;
  text-decoration: none;
  box-shadow: 0 12px 28px rgba(8, 56, 74, 0.18);
  transition: transform 0.22s ease, box-shadow 0.22s ease, background-color 0.22s ease;
}

.header-registration-link:hover {
  transform: translateY(-1px);
  box-shadow: 0 16px 30px rgba(8, 56, 74, 0.24);
}

.landing-header--scrolled .header-registration-link {
  background: #0f3554;
  color: #ffffff;
}

.account-menu {
  position: relative;
}

.account-menu--header {
  display: flex;
  align-items: center;
}

.account-menu__panel {
  position: absolute;
  top: calc(100% + 12px);
  left: 50%;
  transform: translateX(-50%);
  width: 260px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 24px 28px;
  border-radius: 20px;
  background: #f4f6f8;
  border: none;
  box-shadow: 0 15px 35px -10px rgba(8, 65, 89, 0.4);
  text-align: right;
  z-index: 25;
}

.account-menu__panel--header {
  top: calc(100% + 16px);
  left: 0;
  transform: none;
}

.account-menu__name {
  width: 100%;
  background: transparent;
  border: none;
  outline: none;
  color: #08384a;
  font-size: 1.15rem;
  font-weight: 800;
  line-height: 1.5;
  text-align: right;
  padding-bottom: 20px;
  cursor: pointer;
}

.account-menu__name:hover {
  color: #0e4f68;
}

.account-menu__item {
  width: 100%;
  background: transparent;
  border: none;
  outline: none;
  text-align: right;
  padding: 8px 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: #2b5668;
  cursor: pointer;
  transition: all 0.2s ease;
}

.account-menu__item + .account-menu__item {
  margin-top: 10px;
}

.account-menu__item:hover {
  color: #08384a;
  transform: translateX(-4px);
}

.account-menu__item--disabled {
  opacity: 0.38;
  cursor: default;
  pointer-events: none;
}

.account-menu__item--danger {
  color: #db3838;
}

.account-menu__item--danger:hover {
  color: #b02323;
}

.header-icon-link,
.mobile-menu-toggle,
.licenses-menu-toggle {
  width: 50px;
  height: 50px;
  background: transparent;
  color: #fff;
  text-decoration: none;
  border: none;
  outline: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: none;
}

.header-icon-link .v-icon,
.mobile-menu-toggle .v-icon {
  color: inherit !important;
}

.header-icon-svg {
  width: 28px;
  height: 28px;
  display: block;
  color: inherit;
}

.header-icon-link {
  width: 50px;
  padding: 0;
}

.landing-header--scrolled .header-icon-link {
}

.landing-header--scrolled .header-icon-link,
.landing-header--scrolled .mobile-menu-toggle,
.landing-header--scrolled .licenses-menu-toggle {
  color: #063041;
}

.licenses-menu-toggle {
  padding: 0;
  transition: transform 0.25s ease, opacity 0.25s ease;
}

.licenses-menu-toggle:hover {
  transform: translateY(-1px);
  opacity: 0.88;
}

.licenses-menu-toggle__bars {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: center;
  gap: 5px;
  width: 28px;
  height: 22px;
}

.licenses-menu-toggle__bar {
  display: block;
  height: 2.5px;
  border-radius: 999px;
  background: currentColor;
  transform-origin: center right;
  transition: width 0.25s ease, transform 0.25s ease, opacity 0.25s ease;
}

.licenses-menu-toggle__bar:nth-child(1) {
  width: 28px;
}

.licenses-menu-toggle__bar:nth-child(2) {
  width: 20px;
}

.licenses-menu-toggle__bar:nth-child(3) {
  width: 25px;
}

.licenses-menu-toggle:hover .licenses-menu-toggle__bar:nth-child(2) {
  width: 28px;
}

.licenses-menu-toggle:hover .licenses-menu-toggle__bar:nth-child(3) {
  width: 21px;
}

.licenses-menu-header {
  font-weight: bold;
  font-size: 1.1rem;
  padding: 10px 0;
  color: #08384a;
}

::v-deep(.licenses-menu) {
  max-width: calc(100vw - 32px);
}

.license-item {
  border-radius: 8px;
  margin: 4px;
}

.license-item:hover {
  background-color: #f7fcfb;
}

.hero-account-trigger {
  display: inline-flex;
  align-items: center;
  gap: 16px;
  min-width: 260px;
  padding: 14px 20px;
  border: 1px solid rgba(208, 226, 236, 0.92);
  border-radius: 24px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 253, 0.96) 100%);
  box-shadow: 0 20px 44px rgba(8, 56, 74, 0.2);
  text-align: right;
  color: #0b3f5b;
  transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
}

.hero-account-trigger--header {
  min-width: 220px;
  padding: 10px 16px;
  border-radius: 20px;
  box-shadow: 0 14px 30px rgba(8, 56, 74, 0.18);
}

.hero-account-trigger:hover {
  transform: translateY(-2px);
  box-shadow: 0 24px 52px rgba(8, 56, 74, 0.24);
  border-color: #b8d5e4;
}

.hero-account-trigger__icon-wrap {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 18px;
  background: #f3f9fc;
  color: #1484a7;
}

.hero-account-trigger__icon-wrap--header {
  width: 40px;
  height: 40px;
  border-radius: 14px;
}

.hero-account-trigger__icon {
  width: 24px;
  height: 24px;
  display: block;
}

.hero-account-trigger__content {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  min-width: 0;
}

.hero-account-trigger__eyebrow {
  color: #7890a2;
  font-size: 0.82rem;
  font-weight: 800;
}

.hero-account-trigger__title {
  color: #0b3f5b;
  font-size: 1.02rem;
  font-weight: 900;
}

.mobile-menu-toggle {
  cursor: pointer;
}

.mobile-nav-panel {
  background: rgba(255, 255, 255, 0.96);
  border-top: 1px solid rgba(206, 226, 230, 0.9);
  box-shadow: 0 20px 40px -28px rgba(8, 65, 89, 0.28);
}

.mobile-nav {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px 0 18px;
}

.mobile-nav a,
.mobile-nav__dashboard {
  display: block;
  padding: 12px 16px;
  border-radius: 16px;
  color: #08384a;
  font-weight: 800;
  text-decoration: none;
}

.mobile-nav a:hover,
.mobile-nav__dashboard:hover {
  background: rgba(16, 118, 153, 0.08);
}

.mobile-nav__dashboard {
  color: #107699;
}

.hero-section {
  position: relative;
  min-height: 100svh;
  display: flex;
  align-items: center;
  overflow: hidden;
  background: radial-gradient(circle at 50% 35%, #1c8ca5 0%, #167190 36%, #084159 100%);
}

.hero-section__inner {
  position: relative;
  z-index: 1;
  padding: 128px 0 110px;
}

.hero-copy {
  max-width: 980px;
  margin: 0 auto;
  text-align: center;
  color: #fff;
}

.hero-logos {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 26px;
  margin-bottom: 28px;
}

.hero-logo {
  width: auto;
  height: 126px;
  object-fit: contain;
  filter: brightness(0) invert(1) drop-shadow(0 12px 24px rgba(0, 0, 0, 0.18));
}

.hero-logo--association {
  margin-top: 6px;
}

.hero-divider {
  width: 98px;
  height: 5px;
  margin: 0 auto 30px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
}

.hero-title {
  margin: 0;
  font-size: clamp(2.8rem, 7vw, 5.2rem);
  line-height: 1.15;
  font-weight: 900;
}

.hero-text {
  max-width: 820px;
  margin: 24px auto 0;
  font-size: clamp(1rem, 2vw, 1.28rem);
  line-height: 2;
  color: rgba(255, 255, 255, 0.86);
}

.hero-actions {
  display: flex;
  align-items: center;
  gap: 14px;
  justify-content: center;
  margin-top: 38px;
}

.hero-primary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 178px;
  height: 54px;
  padding: 0 26px;
  border-radius: 999px;
  background: #ffffff;
  color: #0f3554;
  font-weight: 900;
  text-decoration: none;
  box-shadow: 0 18px 34px rgba(8, 56, 74, 0.24);
  transition: transform 0.22s ease, box-shadow 0.22s ease;
}

.hero-primary-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 22px 42px rgba(8, 56, 74, 0.3);
}

.hero-outline-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-width: 178px;
  height: 54px;
  padding: 0 26px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.44);
  font-weight: 800;
  text-decoration: none;
  transition: 0.22s ease;
  color: #fff;
  background: rgba(255, 255, 255, 0.04);
}

.hero-outline-btn .v-icon {
  color: #fff !important;
}

.hero-orbit,
.hero-glow,
.hero-grid,
.hero-bottom-fade,
.hero-top-shade {
  position: absolute;
  pointer-events: none;
}

.hero-orbit {
  left: 50%;
  top: 50%;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  transform: translate(-50%, -50%);
}

.hero-orbit--large {
  width: 34rem;
  height: 34rem;
  animation: slow-spin 28s linear infinite;
}

.hero-orbit--medium {
  width: 22rem;
  height: 22rem;
  animation: float 6s ease-in-out infinite;
}

.hero-glow {
  left: 50%;
  top: 50%;
  width: 11rem;
  height: 11rem;
  transform: translate(-50%, -50%);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.1);
  filter: blur(48px);
  animation: soft-pulse 5s ease-in-out infinite;
}

.hero-grid {
  inset: 0;
  opacity: 0.08;
  background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0);
  background-size: 28px 28px;
}

.hero-top-shade {
  inset-inline: 0;
  top: 0;
  height: 160px;
  background: linear-gradient(to bottom, rgba(8, 65, 89, 0.55) 0%, transparent 100%);
}

.hero-bottom-fade {
  right: 0;
  left: 0;
  bottom: 0;
  height: 96px;
  background: linear-gradient(to top, #f3fbfb 0%, transparent 100%);
}

.content-section {
  position: relative;
  padding: 96px 0;
}

.content-section--soft {
  background:
    radial-gradient(circle at 50% -8%, rgba(33, 148, 178, 0.22) 0%, rgba(20, 104, 133, 0.12) 26%, rgba(244, 251, 252, 0.92) 56%, #ffffff 100%),
    linear-gradient(180deg, #f4fbfc 0%, #eef8f9 52%, #ffffff 100%);
}

.content-section--pattern {
  background:
    radial-gradient(circle at 50% 10%, rgba(28, 140, 165, 0.12) 0%, rgba(8, 65, 89, 0.08) 24%, rgba(245, 251, 252, 0.95) 58%, #ffffff 100%),
    linear-gradient(180deg, #f7fcfd 0%, #eef7f9 100%);
}

.content-section--decorated,
.content-section--rings {
  overflow: hidden;
}

.section-layer,
.about-shell {
  position: relative;
  z-index: 1;
}

.decor-blob {
  position: absolute;
  width: 24rem;
  height: 24rem;
  border-radius: 999px;
  filter: blur(56px);
  opacity: 0.5;
  pointer-events: none;
}

.decor-blob--top-right {
  top: -8rem;
  right: -5rem;
  background: rgba(16, 118, 153, 0.08);
}

.decor-blob--bottom-left {
  bottom: -8rem;
  left: -5rem;
  background: rgba(8, 65, 89, 0.12);
}

.decor-blob--top-left {
  top: -8rem;
  left: -5rem;
  background: rgba(16, 118, 153, 0.08);
}

.decor-blob--bottom-right {
  bottom: -8rem;
  right: -5rem;
  background: rgba(8, 65, 89, 0.12);
}

.rings-layer {
  position: absolute;
  inset: 0;
  pointer-events: none;
  opacity: 0.04;
}

.rings-layer__ring {
  position: absolute;
  top: 50%;
  left: 50%;
  border: 2px solid #107699;
  border-radius: 999px;
  transform: translate(-50%, -50%);
}

.rings-layer__ring--outer {
  width: 700px;
  height: 700px;
}

.rings-layer__ring--inner {
  width: 500px;
  height: 500px;
}

.section-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 999px;
  background: rgba(22, 113, 144, 0.08);
  color: #167190;
  font-size: 0.9rem;
  font-weight: 800;
}

.section-pill--soft {
  background: rgba(22, 113, 144, 0.06);
}

.section-pill--center {
  display: flex;
  width: fit-content;
  margin-inline: auto;
}

.section-pill__dot {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: #084159;
}

.section-heading,
.sub-heading {
  text-align: center;
  margin: 22px 0 0;
  font-weight: 900;
  line-height: 1.25;
}

.section-heading {
  font-size: clamp(2rem, 5vw, 3.1rem);
}

.sub-heading {
  font-size: clamp(1.6rem, 4vw, 2.3rem);
}

.section-heading span,
.sub-heading span {
  background: linear-gradient(135deg, #107699, #084159);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.section-lead,
.section-copy--center {
  text-align: center;
  max-width: 820px;
  margin-inline: auto;
  color: #64748b;
  line-height: 1.95;
}

.section-lead {
  margin-top: 20px;
}

.section-copy--center {
  margin-top: 16px;
}

.goals-panel {
  margin-top: 44px;
  padding-top: 32px;
  border-top: 1px solid rgba(148, 163, 184, 0.22);
}

.goals-grid,
.domains-grid,
.duration-quick-grid {
  display: grid;
  gap: 18px;
}

.goals-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: 26px;
  list-style: none;
  padding: 0;
}

.goal-card {
  padding: 24px;
  border-radius: 28px;
  border: 1px solid rgba(22, 113, 144, 0.14);
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
}

.goal-card p {
  margin: 0;
  color: #0d536d;
  font-size: 1rem;
  font-weight: 800;
  line-height: 2;
}

.stats-heading {
  text-align: center;
  margin-bottom: 40px;
}

.our-numbers__title {
  font-size: clamp(1.8rem, 3.5vw, 2.8rem);
  font-weight: 900;
  color: #08384a;
  margin: 0;
  letter-spacing: 0;
}

.our-numbers__graduates {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0;
  max-width: 1280px;
  margin: 0 auto;
  padding-top: 28px;
  border-top: 1px solid rgba(26, 106, 133, 0.18);
}

.our-numbers__graduates--program {
  margin-top: 0;
}

.our-numbers__graduate-item {
  position: relative;
  min-height: 104px;
  padding: 8px 24px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 12px;
}

.our-numbers__graduate-item + .our-numbers__graduate-item {
  border-inline-start: 1px solid rgba(26, 106, 133, 0.16);
}

.our-numbers__graduate-item::after {
  content: "";
  width: 34px;
  height: 3px;
  border-radius: 999px;
  background: #2a94b2;
  opacity: 0.72;
  order: 3;
}

.our-numbers__graduate-item span {
  max-width: 12rem;
  color: #4d6677;
  font-size: 0.88rem;
  font-weight: 800;
  line-height: 1.65;
  text-align: center;
  order: 2;
}

.our-numbers__graduate-item strong {
  color: #0b5872;
  font-size: clamp(2rem, 3vw, 2.65rem);
  font-weight: 900;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  order: 1;
}

.section-ribbon {
  display: flex;
  justify-content: center;
  margin-bottom: 64px;
}

.section-ribbon h2 {
  margin: 0;
  padding: 18px 36px;
  border-radius: 0 0 28px 28px;
  background: linear-gradient(135deg, #2a94b2 0%, #167190 48%, #084159 100%);
  color: #fff;
  font-size: clamp(1.5rem, 4vw, 2.55rem);
  font-weight: 900;
  box-shadow: 0 22px 45px -28px rgba(7, 53, 72, 0.9);
}

.section-ribbon--dark h2 {
  background: linear-gradient(135deg, #2a94b2 0%, #167190 46%, #0d536d 100%);
}

.domains-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.domain-card {
  padding: 32px;
  border-radius: 28px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  background: rgba(237, 248, 248, 0.65);
  box-shadow: 0 10px 26px -18px rgba(15, 23, 42, 0.28);
}

.domain-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 20px;
  margin-bottom: 22px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.2);
}

.domain-card__title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
}

.domain-card__bar {
  width: 4px;
  height: 34px;
  border-radius: 999px;
  background: #167190;
}

.domain-card h3 {
  margin: 0;
  color: #167190;
  font-size: 1.35rem;
  font-weight: 900;
}

.domain-card__icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 18px;
  background: linear-gradient(135deg, #2a94b2 0%, #167190 48%, #084159 100%);
  color: #fff;
}

.domain-card__icon .v-icon,
.requirement-row__icon .v-icon,
.recitation-card__icon .v-icon,
.duration-quick-card__icon .v-icon,
.start-date-card__icon .v-icon {
  color: inherit !important;
}

.domain-card ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 14px;
}

.domain-card li {
  display: flex;
  align-items: center;
  gap: 12px;
}

.domain-card__index {
  flex: 0 0 28px;
  height: 28px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  background: rgba(22, 113, 144, 0.08);
  color: #167190;
  font-size: 0.8rem;
  font-weight: 900;
}

.includes-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 20px;
  max-width: 1120px;
  margin: 0 auto;
}

.include-card {
  position: relative;
  padding: 58px 22px 28px;
  border-radius: 28px;
  text-align: center;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(244, 250, 252, 0.96) 100%);
  border: 1px solid rgba(148, 163, 184, 0.18);
  box-shadow: 0 12px 26px -20px rgba(15, 23, 42, 0.3);
}

.include-card__badge {
  position: absolute;
  top: -18px;
  left: 50%;
  transform: translateX(-50%) rotate(6deg);
  width: 56px;
  height: 56px;
  border-radius: 20px;
  display: grid;
  place-items: center;
  background: linear-gradient(135deg, #2a94b2 0%, #167190 48%, #084159 100%);
  color: #fff;
  font-weight: 900;
  box-shadow: 0 16px 28px -18px rgba(22, 113, 144, 0.5);
}

.include-card h3 {
  margin: 0;
  min-height: 3.4rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  font-weight: 900;
  line-height: 1.7;
  color: #08384a;
}

.include-card__line {
  width: 44px;
  height: 4px;
  margin: 16px auto 0;
  border-radius: 999px;
  background: rgba(10, 76, 97, 0.56);
}

.requirements-list {
  max-width: 900px;
  margin: 0 auto;
  display: grid;
  gap: 16px;
}

.requirement-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 18px 16px 22px;
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(241, 250, 252, 0.95) 0%, rgba(235, 247, 249, 0.9) 100%);
  border: 1px solid rgba(131, 181, 196, 0.22);
  box-shadow: 0 16px 34px -26px rgba(8, 65, 89, 0.34);
}

.requirement-row__icon {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 999px;
  background: linear-gradient(135deg, #2a94b2 0%, #167190 46%, #084159 100%);
  color: #fff;
  flex: 0 0 38px;
  box-shadow: 0 14px 24px -18px rgba(8, 65, 89, 0.5);
}

.recitation-card__icon,
.duration-quick-card__icon,
.start-date-card__icon {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 16px;
  background: transparent;
  border: 1px solid rgba(16, 118, 153, 0.28);
  color: #107699;
  flex: 0 0 44px;
}

.requirement-row p {
  margin: 0;
  flex: 1;
  font-weight: 800;
  line-height: 1.9;
  color: #0b4157;
}

.recitation-panel {
  max-width: 900px;
  margin: 56px auto 0;
}

.recitation-mechanism {
  max-width: 960px;
  margin: 64px auto 0;
  padding: 0;
}

.recitation-mechanism__header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 26px;
}

.recitation-mechanism__icon {
  width: 42px;
  height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: #eef8fa;
  border: 1px solid rgba(16, 118, 153, 0.22);
  color: #107699;
}

.recitation-mechanism__icon .v-icon {
  color: #107699;
}

.recitation-mechanism__header h3 {
  margin: 0;
  color: #08384a;
  font-size: 1.35rem;
  font-weight: 900;
}

.recitation-mechanism__list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.recitation-mechanism__item {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 12px;
  align-items: start;
  padding: 18px 0 18px 18px;
  border-top: 1px solid rgba(16, 118, 153, 0.18);
  color: #264653;
  line-height: 1.9;
  font-size: 0.98rem;
  font-weight: 700;
}

.recitation-mechanism__item p {
  margin: 0;
}

.recitation-mechanism__bullet {
  display: inline-grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: #107699;
  color: #ffffff;
  font-size: 0.9rem;
  font-weight: 900;
  line-height: 1;
}

.recitation-panel__heading {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 22px;
}

.recitation-panel__heading h3 {
  margin: 0;
  color: #107699;
  font-size: 1.18rem;
  font-weight: 900;
}

.recitation-panel__bar {
  width: 6px;
  height: 28px;
  border-radius: 999px;
  background: #084159;
}

.recitation-grid,
.start-dates-list {
  display: grid;
  gap: 16px;
}

.recitation-grid--split {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.recitation-card,
.start-date-card,
.duration-quick-card {
  position: relative;
  padding: 26px 20px 20px;
  border-radius: 24px;
  background: linear-gradient(180deg, rgba(241, 250, 252, 0.94) 0%, rgba(234, 246, 249, 0.88) 100%);
  border: 1px solid rgba(131, 181, 196, 0.18);
}

.recitation-card__tag,
.start-date-card__tag,
.duration-quick-card__tag {
  position: absolute;
  top: -10px;
  right: 18px;
  padding: 6px 12px;
  border-radius: 999px;
  background: linear-gradient(135deg, #2a94b2 0%, #167190 48%, #084159 100%);
  color: #fff;
  font-size: 0.82rem;
  font-weight: 900;
}

.recitation-card__body,
.start-date-card__body,
.duration-quick-card__body,
.duration-quick-card {
  display: flex;
  align-items: center;
  gap: 0;
}

.recitation-card__icon,
.duration-quick-card__icon,
.start-date-card__icon,
.recitation-card__icon--light,
.start-date-card__icon--light {
  display: none;
}

.duration-text-card {
  border-radius: 24px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(241, 249, 251, 0.94) 100%);
  border: 1px solid rgba(131, 181, 196, 0.18);
  box-shadow: 0 14px 28px -22px rgba(8, 65, 89, 0.28);
}

.duration-quick-card__label {
  color: #64748b;
  font-size: 0.82rem;
}

.duration-quick-card__value {
  width: 100%;
  font-weight: 900;
  font-size: 1.18rem;
}

.duration-quick-card__body {
  min-height: 48px;
  justify-content: flex-end;
}

.duration-text-card {
  margin-top: 22px;
  padding: 26px;
  line-height: 2;
}

.duration-text-card p {
  margin: 0;
}

.duration-text-card p + p {
  margin-top: 12px;
}

.start-dates-section {
  margin-top: 36px;
}

.start-dates-heading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 24px;
}

.start-dates-heading span {
  flex: 1;
  height: 1px;
  background: rgba(148, 163, 184, 0.28);
}

.start-dates-heading h3 {
  margin: 0;
  color: #107699;
  font-size: 1.18rem;
  font-weight: 900;
}

.landing-footer {
  border-top: 1px solid rgba(148, 163, 184, 0.18);
  background: #fff;
  padding: 72px 0 24px;
}

.landing-footer__grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr 1fr 1fr;
  gap: 28px;
}

.footer-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.footer-brand__logo {
  width: auto;
  height: 48px;
  object-fit: contain;
}

.footer-brand__title,
.landing-footer h4 {
  font-weight: 900;
}

.footer-text,
.footer-links {
  color: #64748b;
  line-height: 1.9;
}

.footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-links li + li {
  margin-top: 8px;
}

.footer-links--contact li {
  display: flex;
  align-items: center;
  gap: 10px;
}

.footer-links a {
  color: inherit;
  text-decoration: none;
}

.footer-links__muted {
  color: inherit;
  cursor: default;
}

.landing-footer__bottom {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 34px;
  padding-top: 18px;
  border-top: 1px solid rgba(148, 163, 184, 0.18);
  color: #64748b;
  font-size: 0.82rem;
}

.landing-footer__developed {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
}

.login-modal {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(6, 48, 65, 0.42);
  backdrop-filter: blur(10px);
}

.login-modal__panel {
  position: relative;
  width: min(460px, 100%);
  padding: 30px 26px;
  border-radius: 30px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 28px 56px -34px rgba(1, 27, 37, 0.55);
}

.login-modal__brand {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 14px;
  margin-bottom: 18px;
}

.login-modal__logo {
  height: 58px;
  width: auto;
  object-fit: contain;
}

.login-modal__logo--program {
  filter: brightness(0) saturate(100%) invert(31%) sepia(53%) saturate(1050%) hue-rotate(154deg) brightness(91%) contrast(94%);
}

.login-modal__copy {
  text-align: center;
  color: #063041;
}

.login-modal__copy h3 {
  margin: 0 0 6px;
  font-size: 2.2rem;
  font-weight: 900;
}

.login-modal__alert {
  margin-top: 18px;
}

.login-modal__form {
  margin-top: 20px;
}

.login-modal__field {
  margin-bottom: 10px;
}

.login-modal__field::v-deep .v-input__slot {
  min-height: 56px !important;
  border-radius: 22px !important;
  background: #fff !important;
}

.login-modal__field::v-deep fieldset {
  border-color: rgba(42, 148, 178, 0.28) !important;
  border-width: 1.5px !important;
}

.login-modal__field::v-deep .v-label,
.login-modal__field::v-deep input,
.login-modal__field::v-deep .v-input__append-inner .v-icon {
  color: #0f5670 !important;
}

.login-modal__field::v-deep .v-text-field--outlined.v-input--is-focused fieldset {
  border-color: #1b7692 !important;
  box-shadow: 0 0 0 3px rgba(42, 148, 178, 0.12);
}

.login-modal__submit {
  margin-top: 8px;
  min-height: 50px;
  font-weight: 800;
  font-size: 1.22rem;
}

.profile-modal__panel {
  width: min(560px, 100%);
  padding: 38px 40px 34px;
  border-radius: 38px;
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 28px 56px -34px rgba(1, 27, 37, 0.55);
}

.profile-modal__card {
  margin-top: 18px;
  padding: 8px 4px;
}

.profile-modal__label {
  color: #6b8795;
  font-size: 1rem;
  font-weight: 700;
}

.profile-modal__value {
  margin-top: 10px;
  color: #08384a;
  font-size: 1.72rem;
  font-weight: 900;
}

@keyframes float {
  0%, 100% { transform: translate(-50%, -50%) translateY(0); }
  50% { transform: translate(-50%, -50%) translateY(-12px); }
}

@keyframes slow-spin {
  from { transform: translate(-50%, -50%) rotate(0deg); }
  to { transform: translate(-50%, -50%) rotate(360deg); }
}

@keyframes soft-pulse {
  0%, 100% { opacity: 0.42; }
  50% { opacity: 0.9; }
}

.mobile-menu-fade-enter-active,
.mobile-menu-fade-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}

.mobile-menu-fade-enter,
.mobile-menu-fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

@media (max-width: 1400px) {
  .our-numbers__graduates {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 1100px) {
  .includes-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .landing-footer__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1101px) {
  .landing-header__inner {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    padding-inline: 56px;
  }

  .brand-mark {
    justify-self: end;
  }

  .landing-nav {
    display: flex;
    position: static;
    justify-self: center;
    transform: none;
    max-width: 100%;
  }

  .landing-header__actions {
    position: absolute;
    top: 50%;
    left: 8px;
    transform: translateY(-50%);
    justify-self: auto;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .mobile-menu-toggle,
  .mobile-nav-panel {
    display: none;
  }
}

@media (max-width: 860px) {
  .landing-header__inner {
    align-items: flex-start;
    gap: 14px;
  }

  .landing-header__actions {
    gap: 8px;
  }

  .goals-grid,
  .domains-grid,
  .duration-quick-grid,
  .includes-grid,
  .recitation-grid--split,
  .landing-footer__grid,
  .our-numbers__graduates {
    grid-template-columns: 1fr 1fr;
  }

  .our-numbers__graduate-item:nth-child(odd) {
    border-inline-start: 0;
  }

  .our-numbers__graduate-item:nth-child(n + 3) {
    padding-top: 24px;
    border-top: 1px solid rgba(26, 106, 133, 0.13);
  }

  .landing-header__inner,
  .landing-footer__bottom {
    flex-direction: column;
  }

  .landing-footer__developed {
    position: static;
    transform: none;
  }

  .hero-account-trigger {
    width: min(100%, 360px);
    min-width: 0;
  }
}

@media (max-width: 640px) {
  .landing-page {
    overflow-x: clip;
  }

  .landing-shell,
  .narrow-shell {
    width: min(100% - 24px, 100%);
  }

  .landing-shell--wide {
    width: min(100% - 24px, 100%);
  }

  .landing-header__inner {
    min-height: auto;
    padding-top: 14px;
    padding-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .brand-mark {
    display: none;
  }

  .landing-header__actions {
    width: 100%;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-inline-start: 0;
  }

  .hero-section__inner {
    padding: 112px 0 82px;
  }

  .hero-copy {
    text-align: center;
  }

  .hero-title {
    font-size: clamp(2rem, 10vw, 2.6rem);
    line-height: 1.3;
  }

  .hero-text,
  .section-lead,
  .section-copy--center,
  .goal-card p,
  .requirement-row p,
  .duration-text-card {
    line-height: 1.85;
  }

  .hero-text {
    font-size: 0.98rem;
    margin-bottom: 28px;
  }

  .hero-logos {
    flex-direction: row;
    justify-content: center;
    flex-wrap: nowrap;
    gap: 12px;
  }

  .hero-actions {
    flex-direction: column;
  }

  .hero-logo {
    height: 82px;
  }

  .hero-logo--association {
    height: 78px;
  }

  .hero-logo--hero-white {
    height: 108px;
    transform: translateY(-6px);
  }

  .hero-actions > * {
    width: 100%;
  }

  .hero-primary-btn,
  .hero-outline-btn {
    width: 100%;
    min-height: 52px;
    justify-content: center;
  }

  .goals-grid,
  .domains-grid,
  .duration-quick-grid,
  .includes-grid,
  .recitation-grid--split,
  .landing-footer__grid,
  .our-numbers__graduates {
    grid-template-columns: 1fr;
  }

  .our-numbers__graduates {
    gap: 0;
    padding-top: 16px;
  }

  .our-numbers__graduate-item {
    min-height: 96px;
    padding: 20px 12px;
    border-inline-start: 0 !important;
  }

  .our-numbers__graduate-item + .our-numbers__graduate-item,
  .our-numbers__graduate-item:nth-child(n + 3) {
    border-top: 1px solid rgba(26, 106, 133, 0.13);
  }

  .requirement-row {
    align-items: flex-start;
    flex-wrap: wrap;
    padding: 16px;
    border-radius: 22px;
  }

  .requirement-row__icon {
    flex: 0 0 34px;
    width: 34px;
    height: 34px;
  }

  .account-menu--header {
    display: flex;
    align-items: center;
  }

  .header-icon-link,
  .licenses-menu-toggle {
    width: 44px;
    height: 44px;
  }

  ::v-deep(.licenses-menu) {
    right: 16px !important;
    left: auto !important;
    width: min(330px, calc(100vw - 32px)) !important;
    max-width: calc(100vw - 32px) !important;
  }

  .account-menu__panel--header {
    left: auto;
    right: 0;
    transform: none;
    width: min(228px, calc(100vw - 24px));
    max-width: calc(100vw - 24px);
    padding: 16px 18px 18px;
  }

  .section-ribbon h2 {
    width: 100%;
    padding: 16px 18px;
    border-radius: 22px;
    text-align: center;
  }

  .domain-card,
  .goal-card,
  .include-card,
  .recitation-card,
  .start-date-card,
  .duration-quick-card,
  .duration-text-card,
  .login-modal__panel,
  .profile-modal__panel {
    border-radius: 22px;
  }

  .domain-card,
  .goal-card,
  .include-card,
  .duration-text-card {
    padding: 20px 18px;
  }

  .include-card {
    padding-top: 52px;
  }

  .include-card h3 {
    min-height: 0;
  }

  .domain-card__header,
  .recitation-panel__heading,
  .start-dates-heading {
    align-items: flex-start;
  }

  .recitation-card__tag,
  .start-date-card__tag,
  .duration-quick-card__tag {
    right: 14px;
  }

  .recitation-mechanism {
    margin-top: 48px;
  }

  .recitation-mechanism__header {
    justify-content: flex-start;
    align-items: center;
    margin-bottom: 18px;
  }

  .recitation-mechanism__header h3 {
    font-size: 1.15rem;
    line-height: 1.6;
  }

  .recitation-mechanism__list {
    grid-template-columns: 1fr;
    gap: 0;
  }

  .recitation-mechanism__item {
    padding: 16px 0;
  }

  .recitation-mechanism__bullet {
    width: 32px;
    height: 32px;
  }

  .login-modal__panel {
    padding: 26px 18px;
  }

  .account-menu__panel {
    left: 50%;
    transform: translateX(-50%);
    width: min(228px, calc(100vw - 32px));
    padding: 16px 18px 18px;
  }

  .account-menu__panel--header {
    top: calc(100% + 8px);
    left: auto;
    right: 0;
    transform: none;
    width: min(172px, calc(100vw - 20px));
    max-width: calc(100vw - 20px);
    padding: 10px 12px 12px;
    border-radius: 16px;
  }

  .account-menu__name {
    font-size: 0.92rem;
    line-height: 1.35;
    padding-bottom: 10px;
  }

  .account-menu__item {
    padding: 6px 0;
    font-size: 0.92rem;
  }

  .account-menu__item + .account-menu__item {
    margin-top: 4px;
  }

  .profile-modal__panel {
    padding: 24px 18px;
    border-radius: 28px;
  }

  .profile-modal__value {
    font-size: 1.4rem;
  }
}
</style>
