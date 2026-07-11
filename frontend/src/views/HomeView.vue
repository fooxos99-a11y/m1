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
              :class="{ 'site-logo--light': !scrolled }"
            >
          </div>
          <div
            class="brand-mark__text"
            :class="{ 'brand-mark__text--light': !scrolled }"
          >
            {{ homePageContent.brandTitle }}
          </div>
        </a>

        <div class="landing-header__actions">
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
                v-bind="attrs"
                aria-label="البرامج والرخص"
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
                  {{ homePageContent.licensesMenuTitle }}
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
      class="hero-section hero-section--licenses"
    >
      <div class="hero-orbit hero-orbit--large" />
      <div class="hero-orbit hero-orbit--medium" />
      <div class="hero-orbit hero-orbit--small" />
      <div class="hero-glow" />
      <div class="hero-grid" />
      <div class="hero-top-shade" />
      <div class="hero-radial hero-radial--one" />
      <div class="hero-radial hero-radial--two" />

      <div class="landing-shell hero-section__inner">
        <div class="hero-layout">
          <div class="hero-copy hero-copy--licenses">
            <div class="hero-logos hero-logos--centered">
              <img
                :src="$publicAsset('شعار-الجمعية.png')"
                alt="شعار الجمعية"
                class="hero-logo hero-logo--association hero-logo--hero-white"
              >
            </div>
            <div class="hero-divider" />
            <h1 class="hero-title">
              {{ homePageContent.heroTitle }}
            </h1>
            <p class="hero-text">
              {{ homePageContent.heroText }}
            </p>

            <div class="hero-actions">
              <AppButton
                variant="plain"
                class="hero-primary-btn"
                @click="scrollToSection('programs')"
              >
                {{ homePageContent.heroPrimaryButtonLabel }}
              </AppButton>

              <AppButton
                variant="plain"
                class="hero-outline-btn hero-outline-btn--licenses"
                @click="scrollToSection('achievements')"
              >
                {{ homePageContent.heroSecondaryButtonLabel }}
              </AppButton>
            </div>
          </div>
        </div>
      </div>

      <div class="hero-bottom-fade" />
    </section>

    <section
      id="achievements"
      ref="achievementsSection"
      class="landing-section achievements-section"
    >
      <div class="landing-shell">
        <div class="our-numbers">
          <h2 class="our-numbers__title">
            أرقامنا
          </h2>
          <div class="our-numbers__row">
            <div class="our-numbers__stat">
              <div class="our-numbers__num">
                {{ animatedStatValue(publicStats ? publicStats.batches : 0) }}<span class="our-numbers__plus">+</span>
              </div>
              <div class="our-numbers__lbl">
                عدد الدفعات
              </div>
            </div>
            <div class="our-numbers__divider" />
            <div class="our-numbers__stat">
              <div class="our-numbers__num">
                {{ animatedStatValue(publicStats ? publicStats.courses : 0) }}<span class="our-numbers__plus">+</span>
              </div>
              <div class="our-numbers__lbl">
                عدد الدورات
              </div>
            </div>
            <div class="our-numbers__divider" />
            <div class="our-numbers__stat">
              <div class="our-numbers__num">
                {{ animatedStatValue(publicStats ? publicStats.graduates : 0) }}<span class="our-numbers__plus">+</span>
              </div>
              <div class="our-numbers__lbl">
                عدد الخريجين
              </div>
            </div>
          </div>
          <div class="our-numbers__graduates">
            <div
              v-for="item in graduateDetailStats"
              :key="item.key"
              class="our-numbers__graduate-item"
            >
              <span>{{ item.label }}</span>
              <strong>{{ animatedStatValue(item.value) }}</strong>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section
      id="programs"
      ref="programsSection"
      class="landing-section programs-section"
    >
      <div class="landing-shell">
        <div class="section-heading">
          <h2 class="section-heading__title">
            {{ homePageContent.programsSectionTitle }}
          </h2>
        </div>

        <div class="programs-grid">
          <article
            v-for="(program, index) in licensePrograms"
            :key="program.key"
            class="program-card"
            :class="{
              'program-card--poster-design': program.poster,
              'program-card--manager-design': program.key === 'manager',
              'program-card--practitioner-design': program.key === 'practitioner',
              'program-card--from-right': index < 2,
              'program-card--from-left': index >= 2,
              'program-card--reveal-visible': programsRevealed,
            }"
            :style="program.posterVars || null"
          >
            <template v-if="program.poster">
              <div class="manager-license-card__circle" />

              <header class="manager-license-card__header">
                <h3>{{ program.title }}</h3>
                <div class="manager-license-card__line" />
                <p>{{ program.description }}</p>
              </header>

              <div class="manager-license-card__content">
                <div class="manager-license-card__illustration">
                  <div class="manager-license-card__dots">
                    <span
                      v-for="dot in 12"
                      :key="dot"
                    />
                  </div>

                  <div class="manager-license-card__leaves">
                    <span class="manager-license-card__leaf manager-license-card__leaf--one" />
                    <span class="manager-license-card__leaf manager-license-card__leaf--two" />
                    <span class="manager-license-card__leaf manager-license-card__leaf--three" />
                    <span class="manager-license-card__leaf manager-license-card__leaf--four" />
                  </div>

                  <div class="manager-license-card__platform" />

                  <div class="manager-license-card__document">
                    <svg
                      v-if="program.key !== 'manager'"
                      class="manager-license-card__certificate"
                      viewBox="0 0 24 24"
                      aria-hidden="true"
                    >
                      <template v-if="program.key === 'practitioner'">
                        <path d="M12 6C10.2 4.8 8.1 4.2 6 4.2C4.9 4.2 3.8 4.4 3 4.8V18.5C3.8 18.1 4.9 17.9 6 17.9C8.1 17.9 10.2 18.5 12 19.8C13.8 18.5 15.9 17.9 18 17.9C19.1 17.9 20.2 18.1 21 18.5V4.8C20.2 4.4 19.1 4.2 18 4.2C15.9 4.2 13.8 4.8 12 6Z" />
                        <path d="M12 6V19.8" />
                        <path d="M6 8.2C7.4 8.2 9.2 8.6 10.6 9.4" />
                        <path d="M18 8.2C16.6 8.2 14.8 8.6 13.4 9.4" />
                      </template>
                      <template v-else-if="program.key === 'supervisor'">
                        <path d="M8 3L10.5 8" />
                        <path d="M16 3L13.5 8" />
                        <path d="M9 3H15" />
                        <circle
                          cx="12"
                          cy="14"
                          r="6"
                        />
                        <path d="M12 10.8L13 12.9L15.3 13.2L13.65 14.8L14.05 17.1L12 16L9.95 17.1L10.35 14.8L8.7 13.2L11 12.9L12 10.8Z" />
                      </template>
                      <template v-else>
                        <path d="M3 7A2 2 0 0 1 5 5H9.8L12 7.4H19A2 2 0 0 1 21 9.4V17A2 2 0 0 1 19 19H5A2 2 0 0 1 3 17V7Z" />
                        <path d="M3 10H21" />
                        <path d="M7 14H14" />
                        <path d="M7 16.5H12" />
                      </template>
                    </svg>
                    <div
                      v-else
                      class="manager-license-card__briefcase"
                    />
                    <span class="manager-license-card__doc-line manager-license-card__doc-line--medium" />
                    <span class="manager-license-card__doc-line manager-license-card__doc-line--short" />
                    <span class="manager-license-card__doc-spacer" />
                    <span class="manager-license-card__doc-line manager-license-card__doc-line--long" />
                    <span class="manager-license-card__doc-line manager-license-card__doc-line--long" />
                    <span class="manager-license-card__doc-line manager-license-card__doc-line--long" />
                    <span class="manager-license-card__signature" />
                    <span class="manager-license-card__doc-line manager-license-card__doc-line--bottom" />
                  </div>
                </div>

                <div class="manager-license-card__features">
                  <div
                    v-for="(feature, featureIndex) in program.features"
                    :key="feature"
                    class="manager-license-card__feature"
                  >
                    <div class="manager-license-card__icon-box">
                      <svg
                        v-if="featureIndex === 0"
                        class="manager-license-card__icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                      >
                        <template v-if="program.key === 'secretary'">
                          <rect
                            x="4"
                            y="4"
                            width="16"
                            height="16"
                            rx="2"
                          />
                          <path d="M8 8H16" />
                          <path d="M8 12H14" />
                          <path d="M8 16H12" />
                          <path d="M6.5 8L7 8.5L8 7.5" />
                        </template>
                        <template v-else-if="program.key === 'supervisor'">
                          <path d="M4 19V7" />
                          <path d="M4 19H20" />
                          <path d="M8 16V12" />
                          <path d="M12 16V9" />
                          <path d="M16 16V5" />
                          <path d="M8 12L12 9L16 5" />
                        </template>
                        <template v-else-if="program.key === 'practitioner'">
                          <path d="M4 12L8 16L20 4" />
                          <path d="M4 6H14" />
                          <path d="M4 18H10" />
                        </template>
                        <template v-else>
                          <path d="M4 19V5" />
                          <path d="M4 19H20" />
                          <path d="M8 16V11" />
                          <path d="M12 16V8" />
                          <path d="M16 16V13" />
                        </template>
                      </svg>
                      <svg
                        v-else-if="featureIndex === 1"
                        class="manager-license-card__icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                      >
                        <template v-if="program.key === 'secretary'">
                          <path d="M4 6A2 2 0 0 1 6 4H18A2 2 0 0 1 20 6V18A2 2 0 0 1 18 20H6A2 2 0 0 1 4 18Z" />
                          <path d="M8 4V20" />
                          <path d="M11 8H16" />
                          <path d="M11 12H16" />
                          <path d="M11 16H14" />
                        </template>
                        <template v-else-if="program.key === 'supervisor'">
                          <path d="M4 20H20" />
                          <circle
                            cx="7"
                            cy="9"
                            r="2"
                          />
                          <circle
                            cx="12"
                            cy="6"
                            r="2"
                          />
                          <circle
                            cx="17"
                            cy="12"
                            r="2"
                          />
                          <path d="M7 11V16" />
                          <path d="M12 8V16" />
                          <path d="M17 14V16" />
                        </template>
                        <template v-else-if="program.key === 'practitioner'">
                          <rect
                            x="4"
                            y="3"
                            width="16"
                            height="18"
                            rx="2"
                          />
                          <path d="M8 8H16" />
                          <path d="M8 12H16" />
                          <path d="M8 16H13" />
                          <path d="M15 16L17 18L20 14" />
                        </template>
                        <template v-else>
                          <rect
                            x="4"
                            y="5"
                            width="16"
                            height="14"
                            rx="2"
                          />
                          <path d="M8 9H16" />
                          <path d="M8 13H13" />
                          <path d="M16 2V6" />
                          <path d="M8 2V6" />
                        </template>
                      </svg>
                      <svg
                        v-else
                        class="manager-license-card__icon"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                      >
                        <template v-if="program.key === 'secretary'">
                          <rect
                            x="4"
                            y="5"
                            width="16"
                            height="15"
                            rx="2"
                          />
                          <path d="M8 3V7" />
                          <path d="M16 3V7" />
                          <path d="M4 10H20" />
                          <path d="M8 14H8.01" />
                          <path d="M12 14H12.01" />
                          <path d="M16 14H16.01" />
                          <path d="M15 18L16.2 19.2L18.5 16.7" />
                        </template>
                        <template v-else-if="program.key === 'supervisor'">
                          <circle
                            cx="7"
                            cy="8"
                            r="3"
                          />
                          <circle
                            cx="17"
                            cy="8"
                            r="3"
                          />
                          <path d="M7 11V15" />
                          <path d="M17 11V15" />
                          <path d="M7 15H17" />
                          <path d="M12 15V20" />
                        </template>
                        <template v-else-if="program.key === 'practitioner'">
                          <path d="M4 20H20" />
                          <path d="M7 16V10" />
                          <path d="M12 16V6" />
                          <path d="M17 16V12" />
                          <path d="M7 10L12 6L17 12" />
                        </template>
                        <template v-else>
                          <path d="M4 21V9L12 4L20 9V21" />
                          <path d="M9 21V14H15V21" />
                          <path d="M7 10H7.01" />
                          <path d="M17 10H17.01" />
                        </template>
                      </svg>
                    </div>
                    <span>{{ feature }}</span>
                  </div>
                </div>
              </div>
            </template>

            <div
              v-else
              class="program-card__body"
            >
              <div class="program-card__top">
                <div
                  class="program-card__icon-wrap"
                  :style="{ background: program.cardColorLight }"
                >
                  <v-icon
                    :color="program.cardColor"
                    size="26"
                  >
                    {{ program.icon }}
                  </v-icon>
                </div>
                <span
                  class="program-card__status-badge"
                  :style="{ color: program.cardColor, background: program.cardColorLight }"
                >
                  {{ program.status }}
                </span>
              </div>
              <h3
                class="program-card__title"
                :style="{ color: program.cardColor }"
              >
                {{ program.title }}
              </h3>
              <p class="program-card__text">
                {{ program.description }}
              </p>

              <div
                v-if="program.audience"
                class="program-card__audience"
              >
                {{ program.audience }}
              </div>

              <div
                v-if="program.stats.length"
                class="program-card__stats"
              >
                <div
                  v-for="stat in program.stats"
                  :key="stat.key"
                  class="program-card__stat"
                >
                  <strong>{{ animatedStatValue(stat.value) }}</strong>
                  <span>{{ stat.label }}</span>
                </div>
              </div>

              <ul
                v-if="program.features && program.features.length"
                class="program-card__features"
              >
                <li
                  v-for="feature in program.features"
                  :key="feature"
                  :style="{ '--dot-color': program.cardColor }"
                >
                  {{ feature }}
                </li>
              </ul>

              <AppButton
                variant="plain"
                class="program-card__action"
                :style="program.available
                  ? { background: program.cardColor, borderColor: program.cardColor }
                  : {}"
                :disabled="!program.available"
                @click="openProgram(program)"
              >
                {{ program.available ? homePageContent.programAvailableActionLabel : homePageContent.programUpcomingActionLabel }}
              </AppButton>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="landing-section faq-section">
      <div class="landing-shell">
        <div class="section-heading">
          <div class="section-heading__eyebrow">
            {{ homePageContent.faqEyebrow }}
          </div>
          <h2 class="section-heading__title">
            {{ homePageContent.faqTitle }}
          </h2>
          <p class="section-heading__text">
            {{ homePageContent.faqText }}
          </p>
        </div>

        <div class="faq-grid">
          <article
            v-for="item in faqItems"
            :key="item.question"
            class="faq-card"
          >
            <h3 class="faq-card__question">
              {{ item.question }}
            </h3>
            <p class="faq-card__answer">
              {{ item.answer }}
            </p>
          </article>
        </div>
      </div>
    </section>

    <footer class="landing-footer">
      <div class="landing-shell landing-footer__grid">
        <div>
          <div class="footer-brand">
            <div class="footer-brand__title">
              {{ homePageContent.footerBrandTitle }}
            </div>
          </div>
          <p class="footer-text">
            {{ homePageContent.footerDescription }}
          </p>
        </div>

        <div>
          <h4>{{ homePageContent.footerAboutTitle }}</h4>
          <ul class="footer-links">
            <li>
              <router-link :to="{ name: 'home' }">
                {{ homePageContent.footerHomeLabel }}
              </router-link>
            </li>
            <li>
              <router-link :to="{ name: 'practitioner' }">
                {{ homePageContent.footerLicensesLabel }}
              </router-link>
            </li>
          </ul>
        </div>

        <div>
          <h4>{{ homePageContent.footerContactTitle }}</h4>
          <ul class="footer-links footer-links--contact">
            <li>
              <v-icon small>
                mdi-map-marker-outline
              </v-icon>
              <span>{{ homePageContent.footerAddress }}</span>
            </li>
            <li>
              <v-icon small>
                mdi-phone-outline
              </v-icon>
              <span dir="ltr">{{ homePageContent.footerPhone }}</span>
            </li>
          </ul>
        </div>

        <div>
          <h4>{{ homePageContent.footerPoliciesTitle }}</h4>
          <ul class="footer-links">
            <li><span class="footer-links__muted">{{ homePageContent.footerPrivacyLabel }}</span></li>
            <li><span class="footer-links__muted">{{ homePageContent.footerTermsLabel }}</span></li>
          </ul>
        </div>
      </div>

      <div class="landing-shell landing-footer__bottom">
        <div>© {{ currentYear }} {{ homePageContent.footerCopyright }}</div>
        <div class="landing-footer__developed">
          {{ homePageContent.footerDevelopedBy }}
        </div>
      </div>
    </footer>

    <!-- Login & Profile Modals -->
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
        </div>
        <div class="login-modal__copy">
          <h3>تسجيل الدخول</h3>
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
            label="رقم الدخول"
            outlined
            dense
            autocomplete="username"
          />
          <v-text-field
            v-model="loginForm.password"
            class="login-modal__field"
            :type="showLoginPassword ? 'text' : 'password'"
            label="كلمة المرور"
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
            دخول
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
import { fetchPublicSnapshot, fetchPublicStats } from '../services/api';
import { resolveUserHomeLabel, resolveUserHomeRoute } from '../utils/authRoutes';
import { normalizeHomePageContent } from '../utils/homePageContent';

const LICENSE_PROGRAM_META = [
  {
    key: 'manager',
    status: 'قريباً',
    available: false,
    route: null,
    icon: 'mdi-briefcase-outline',
    cardColor: '#08384a',
    cardColorLight: '#e8f6fb',
    poster: true,
    posterVars: {
      '--poster-title': '#07385f',
      '--poster-text': '#1d3f5d',
      '--poster-line-start': '#1d75b9',
      '--poster-line-end': '#6bb7e8',
      '--poster-circle-start': '#eaf5fb',
      '--poster-circle-end': '#f8fbfd',
      '--poster-platform-start': '#eef8fb',
      '--poster-platform-end': '#d9eef5',
      '--poster-platform-shadow': 'rgba(20, 95, 145, 0.13)',
      '--poster-platform-after': '#eef4f7',
      '--poster-leaf-start': '#7ec4d9',
      '--poster-leaf-end': '#2b8dbd',
      '--poster-document-shadow': 'rgba(9, 55, 95, 0.14)',
      '--poster-doc-line': '#b8dce7',
      '--poster-accent': '#0e7da7',
      '--poster-dot': '#8ac6d8',
      '--poster-feature-shadow': 'rgba(11, 47, 74, 0.08)',
      '--poster-feature-shadow-hover': 'rgba(11, 47, 74, 0.12)',
      '--poster-icon-bg-start': '#eef8fb',
      '--poster-icon-bg-end': '#d9eef5',
      '--poster-icon-border': '#d6e8ef',
      '--poster-feature-text': '#123a58',
    },
  },
  {
    key: 'practitioner',
    status: 'متاحة الآن',
    available: true,
    route: { name: 'practitioner' },
    icon: 'mdi-certificate-outline',
    cardColor: '#08384a',
    cardColorLight: '#e8f6fb',
    poster: true,
    posterVars: {
      '--poster-title': '#145a37',
      '--poster-text': '#2c5a45',
      '--poster-line-start': '#1f8f57',
      '--poster-line-end': '#7fd8a9',
      '--poster-circle-start': '#e7f7ef',
      '--poster-circle-end': '#f7fcf9',
      '--poster-platform-start': '#eefaf4',
      '--poster-platform-end': '#d9f1e3',
      '--poster-platform-shadow': 'rgba(31, 143, 87, 0.12)',
      '--poster-platform-after': '#eef6f1',
      '--poster-leaf-start': '#8fd6b2',
      '--poster-leaf-end': '#1f8f57',
      '--poster-document-shadow': 'rgba(20, 90, 55, 0.14)',
      '--poster-doc-line': '#c7e7d3',
      '--poster-accent': '#1f8f57',
      '--poster-dot': '#8fd6b2',
      '--poster-feature-shadow': 'rgba(20, 90, 55, 0.08)',
      '--poster-feature-shadow-hover': 'rgba(20, 90, 55, 0.12)',
      '--poster-icon-bg-start': '#eefaf4',
      '--poster-icon-bg-end': '#d9f1e3',
      '--poster-icon-border': '#d3eadb',
      '--poster-feature-text': '#214838',
    },
  },
  {
    key: 'supervisor',
    status: 'قريباً',
    available: false,
    route: null,
    icon: 'mdi-eye-outline',
    cardColor: '#08384a',
    cardColorLight: '#e8f6fb',
    poster: true,
    posterVars: {
      '--poster-title': '#8a5a00',
      '--poster-text': '#5d471c',
      '--poster-line-start': '#d99a13',
      '--poster-line-end': '#ffd66b',
      '--poster-circle-start': '#fff4d8',
      '--poster-circle-end': '#fffdf7',
      '--poster-platform-start': '#fff8e6',
      '--poster-platform-end': '#f7e4ad',
      '--poster-platform-shadow': 'rgba(217, 154, 19, 0.12)',
      '--poster-platform-after': '#fbf5e8',
      '--poster-leaf-start': '#ffd66b',
      '--poster-leaf-end': '#d99a13',
      '--poster-document-shadow': 'rgba(117, 82, 18, 0.14)',
      '--poster-doc-line': '#f1dca7',
      '--poster-accent': '#d99a13',
      '--poster-dot': '#ffd66b',
      '--poster-feature-shadow': 'rgba(117, 82, 18, 0.08)',
      '--poster-feature-shadow-hover': 'rgba(117, 82, 18, 0.12)',
      '--poster-icon-bg-start': '#fff8e6',
      '--poster-icon-bg-end': '#f7e4ad',
      '--poster-icon-border': '#ecd79d',
      '--poster-feature-text': '#4c3a14',
    },
  },
  {
    key: 'secretary',
    status: 'قريباً',
    available: false,
    route: null,
    icon: 'mdi-clipboard-text-outline',
    cardColor: '#08384a',
    cardColorLight: '#e8f6fb',
    poster: true,
    posterVars: {
      '--poster-title': '#0f6f77',
      '--poster-text': '#25545a',
      '--poster-line-start': '#12a8b4',
      '--poster-line-end': '#74dce4',
      '--poster-circle-start': '#e7fbfc',
      '--poster-circle-end': '#fbffff',
      '--poster-platform-start': '#eefcfd',
      '--poster-platform-end': '#d8f4f6',
      '--poster-platform-shadow': 'rgba(18, 168, 180, 0.12)',
      '--poster-platform-after': '#eef8f8',
      '--poster-leaf-start': '#74dce4',
      '--poster-leaf-end': '#12a8b4',
      '--poster-document-shadow': 'rgba(18, 108, 115, 0.14)',
      '--poster-doc-line': '#c8edf0',
      '--poster-accent': '#12a8b4',
      '--poster-dot': '#74dce4',
      '--poster-feature-shadow': 'rgba(18, 108, 115, 0.08)',
      '--poster-feature-shadow-hover': 'rgba(18, 108, 115, 0.12)',
      '--poster-icon-bg-start': '#eefcfd',
      '--poster-icon-bg-end': '#d8f4f6',
      '--poster-icon-border': '#cfeaec',
      '--poster-feature-text': '#244f55',
    },
  },
];

const PROGRAM_CONTENT_INDEX_BY_KEY = {
  practitioner: 0,
  manager: 1,
  supervisor: 2,
  secretary: 3,
};

export default {
  name: 'HomeView',
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
      loginDialogOpen: false,
      showLoginPassword: false,
      loginRedirectPath: '',
      loginForm: {
        loginCode: '',
        password: '',
      },
      publicSnapshot: null,
      publicStats: null,
      statsAnimationProgress: 0,
      statsAnimationFrameId: null,
      statsAnimationStarted: false,
      animatedAchievements: {
        maleTrainees: 0,
        femaleTrainees: 0,
        satisfactionRate: 0,
        licenseCount: 0,
      },
      currentTimestamp: Date.now(),
      menuClockTimer: null,
      achievementAnimationStarted: false,
      achievementSectionVisible: false,
      achievementAnimationTimer: null,
      programsRevealed: false,
      publicDataLoaded: false,
    };
  },
  computed: {
    ...mapState(['authError', 'authLoading', 'currentUser']),
    ...mapGetters(['isAuthenticated']),
    currentYear() {
      return new Date().getFullYear();
    },
    homePageContent() {
      return normalizeHomePageContent(this.publicSnapshot?.homePageContent || null);
    },
    licensePrograms() {
      const orderedPrograms = [
        ...LICENSE_PROGRAM_META.filter((program) => program.key === 'practitioner'),
        ...LICENSE_PROGRAM_META.filter((program) => program.key !== 'practitioner'),
      ];

      return orderedPrograms.map((program) => {
        const content = this.homePageContent.programs[PROGRAM_CONTENT_INDEX_BY_KEY[program.key]] || {};
        const stats = this.licenseProgramStats(program.key);

        return {
          ...program,
          title: content.title || '',
          menuSubtitle: content.menuSubtitle || '',
          description: content.description || '',
          audience: content.audience || '',
          features: Array.isArray(content.features) ? content.features : [],
          stats,
        };
      });
    },
    graduateDetailStats() {
      const details = this.publicStats?.graduateDetails || {};

      return [
        { key: 'manager', label: 'عدد خريجين رخصة مدير', value: details.manager || 0 },
        { key: 'supervisor', label: 'عدد خريجين رخصة مشرف', value: details.supervisor || 0 },
        { key: 'secretary', label: 'عدد خريجين رخصة سكرتير', value: details.secretary || 0 },
        { key: 'practitioner', label: 'عدد خريجين رخصة ممارس', value: details.practitioner || 0 },
      ];
    },
    faqItems() {
      return this.homePageContent.faqItems;
    },
    publicStudents() {
      return this.publicSnapshot?.students || [];
    },
    publicSatisfactionResponses() {
      return this.publicSnapshot?.satisfactionResponses || [];
    },
    licenseStatsDetails() {
      return this.publicStats?.licenseDetails || {};
    },
    maleTraineesCount() {
      return this.publicStudents.filter((student) => student.branchId === 'male').length;
    },
    femaleTraineesCount() {
      return this.publicStudents.filter((student) => student.branchId === 'female').length;
    },
    satisfactionRatePercent() {
      const ratingValues = this.publicSatisfactionResponses
        .map((item) => Number(item.ratingValue))
        .filter((value) => Number.isFinite(value));

      if (!ratingValues.length) {
        return 0;
      }

      const average = ratingValues.reduce((sum, value) => sum + value, 0) / ratingValues.length;
      return Math.round(average * 10);
    },
    achievementStats() {
      return [
        {
          key: 'maleTrainees',
          title: this.homePageContent.achievements.maleTraineesTitle,
          icon: 'mdi-account-group-outline',
          value: this.maleTraineesCount,
          animatedValue: this.animatedAchievements.maleTrainees,
          format: 'number',
        },
        {
          key: 'femaleTrainees',
          title: this.homePageContent.achievements.femaleTraineesTitle,
          icon: 'mdi-account-multiple-outline',
          value: this.femaleTraineesCount,
          animatedValue: this.animatedAchievements.femaleTrainees,
          format: 'number',
        },
        {
          key: 'satisfactionRate',
          title: this.homePageContent.achievements.satisfactionRateTitle,
          icon: 'mdi-star-four-points-outline',
          value: this.satisfactionRatePercent,
          animatedValue: this.animatedAchievements.satisfactionRate,
          format: 'percent',
        },
        {
          key: 'licenseCount',
          title: this.homePageContent.achievements.licenseCountTitle,
          icon: 'mdi-certificate-outline',
          value: this.licensePrograms.length,
          animatedValue: this.animatedAchievements.licenseCount,
          format: 'number',
        },
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
    accountMenuItems() {
      if (this.currentUser?.role !== 'student') {
        return [
          { key: 'primary', label: this.primaryRouteLabel, action: 'route', route: this.primaryRoute },
          { key: 'logout', label: 'تسجيل الخروج', action: 'logout', danger: true },
        ];
      }
      return [
        { key: 'student', label: 'حسابي', action: 'route', route: { name: 'student' } },
        { key: 'logout', label: 'تسجيل الخروج', action: 'logout', danger: true },
      ];
    },
    primaryRoute() {
      return resolveUserHomeRoute(this.currentUser);
    },
    primaryRouteLabel() {
      return resolveUserHomeLabel(this.currentUser);
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
     this.loadPublicStats();
   },
  mounted() {
    this.handleScroll();
    window.addEventListener('scroll', this.handleScroll, { passive: true });
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
      if (this.achievementAnimationTimer && typeof window !== 'undefined') {
        window.clearTimeout(this.achievementAnimationTimer);
        this.achievementAnimationTimer = null;
      }
    window.removeEventListener('scroll', this.handleScroll);
    window.removeEventListener('click', this.handleWindowClick);
  },
  methods: {
    ...mapActions(['login', 'logout']),
    async loadPublicData() {
      try {
        this.publicSnapshot = await fetchPublicSnapshot();
      } catch {
        this.publicSnapshot = null;
      } finally {
        this.publicDataLoaded = true;
        this.maybeStartAchievementAnimation();
      }
    },
    async loadPublicStats() {
      try {
        this.publicStats = await fetchPublicStats();
        this.$nextTick(() => this.maybeStartStatsAnimation());
      } catch {
        this.publicStats = null;
      }
    },
    maybeStartStatsAnimation() {
      if (this.statsAnimationStarted || !this.publicStats || !this.achievementSectionVisible) {
        return;
      }

      this.statsAnimationStarted = true;
      this.startStatsAnimation();
    },
    startStatsAnimation(duration = 3600) {
      if (this.statsAnimationFrameId) window.cancelAnimationFrame(this.statsAnimationFrameId);
      if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) { this.statsAnimationProgress = 1; return; }
      const start = window.performance?.now?.() ?? Date.now();
      this.statsAnimationProgress = 0;
      const easeOut = (t) => 1 - ((1 - t) ** 3);
      const tick = (ts) => {
        const p = Math.min(1, ((ts ?? Date.now()) - start) / duration);
        this.statsAnimationProgress = easeOut(p);
        this.statsAnimationFrameId = p < 1 ? window.requestAnimationFrame(tick) : null;
      };
      this.statsAnimationFrameId = window.requestAnimationFrame(tick);
    },
    animatedStatValue(value) {
      if (value === null || value === undefined) return '0';
      return new Intl.NumberFormat('ar-SA').format(Math.round(Number(value) * this.statsAnimationProgress));
    },
    licenseProgramStats(key) {
      const details = this.licenseStatsDetails[key];

      if (!details) {
        return [];
      }

      return [
        { key: 'graduates', label: 'المستفيدين', value: details.graduates || 0 },
        { key: 'batches', label: 'الدفعات', value: details.batches || 0 },
        { key: 'courses', label: 'الدورات', value: details.courses || 0 },
      ];
    },
    maybeStartAchievementAnimation() {
      if (this.achievementAnimationStarted || !this.achievementSectionVisible || !this.publicDataLoaded) {
        return;
      }

      this.achievementAnimationStarted = true;
      this.animateAchievementStats();
    },
    animateAchievementStats() {
      const targets = {
        maleTrainees: this.maleTraineesCount,
        femaleTrainees: this.femaleTraineesCount,
        satisfactionRate: this.satisfactionRatePercent,
        licenseCount: this.licensePrograms.length,
      };

      const startValues = { ...this.animatedAchievements };
      const duration = 2800;
      const startedAt = typeof performance !== 'undefined' ? performance.now() : Date.now();

      if (this.achievementAnimationTimer && typeof window !== 'undefined') {
        window.clearTimeout(this.achievementAnimationTimer);
      }

      const step = (timestamp) => {
        const now = typeof timestamp === 'number' ? timestamp : Date.now();
        const progress = Math.min(1, (now - startedAt) / duration);
        const eased = 1 - Math.pow(1 - progress, 3);

        this.animatedAchievements = Object.keys(targets).reduce((result, key) => {
          result[key] = Math.round(startValues[key] + ((targets[key] - startValues[key]) * eased));
          return result;
        }, {});

        if (progress < 1 && typeof window !== 'undefined') {
          this.achievementAnimationTimer = window.setTimeout(() => step(Date.now()), 16);
        } else {
          this.achievementAnimationTimer = null;
        }
      };

      if (typeof window !== 'undefined') {
        this.achievementAnimationTimer = window.setTimeout(() => step(Date.now()), 16);
      } else {
        this.animatedAchievements = targets;
      }
    },
    formatAchievementValue(item) {
      const resolvedValue = item.animatedValue;

      if (item.format === 'percent') {
        return `${Math.max(0, resolvedValue)}%`;
      }

      return new Intl.NumberFormat('ar-SA').format(Math.max(0, resolvedValue));
    },
    scrollToSection(sectionId) {
      const element = document.getElementById(sectionId);

      if (!element) {
        return;
      }

      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },
    openProgram(program) {
      if (!program?.available || !program.route) {
        return;
      }

      this.$router.push(program.route).catch(() => {});
    },
    handleScroll() {
      this.scrolled = window.scrollY > 20;
      if (!this.programsRevealed) {
        const programsSection = this.$refs.programsSection;

        if (programsSection) {
          const programBounds = programsSection.getBoundingClientRect();
          const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
          this.programsRevealed = programBounds.top <= viewportHeight * 0.76 && programBounds.bottom >= viewportHeight * 0.16;
        }
      }

      if (!this.achievementAnimationStarted) {
        const section = this.$refs.achievementsSection;

        if (!section || window.scrollY <= 0) {
          return;
        }

        const bounds = section.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        this.achievementSectionVisible = bounds.top <= viewportHeight * 0.78 && bounds.bottom >= viewportHeight * 0.22;
        this.maybeStartStatsAnimation();
        this.maybeStartAchievementAnimation();
      }
    },
    handleWindowClick() {
      this.accountMenuOpen = false;
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
       if (!item || item.disabled) return;
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
     async submitLogout() {
       this.accountMenuOpen = false;
       this.profileDialogOpen = false;
       await this.logout();
       this.$router.replace({ name: 'home' }).catch(() => {});
     },
    openLoginDialog(redirectPath = '') {
      this.loginRedirectPath = redirectPath || this.$route.query.redirect || '';
      this.loginDialogOpen = true;
      const query = { ...this.$route.query, login: '1' };
      if (this.loginRedirectPath) query.redirect = this.loginRedirectPath;
      this.$router.replace({ name: 'home', query, hash: this.$route.hash }).catch(() => {});
    },
    closeLoginDialog() {
      this.loginDialogOpen = false;
      this.showLoginPassword = false;
      const query = { ...this.$route.query };
      delete query.login;
      delete query.redirect;
      this.$router.replace({ name: 'home', query, hash: this.$route.hash }).catch(() => {});
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
       if (!this.loginForm.loginCode || !this.loginForm.password) return;
       try {
         await this.login(this.loginForm);
         this.accountMenuOpen = false;
         this.closeLoginDialog();
         this.$router.replace(resolveUserHomeRoute(this.currentUser)).catch(() => {});
       } catch (e) {
         // Handle error automatically
       }
     },
  },
};
</script>

<style scoped>
/* Custom styles */

/* Use existing landing styles, and customize the new menu */
.landing-page {
  direction: rtl;
  color: #2a94b2;
  background:
    radial-gradient(circle at top right, rgba(34, 197, 94, 0.08), transparent 24%),
    radial-gradient(circle at top left, rgba(42, 148, 178, 0.12), transparent 28%),
    linear-gradient(180deg, #f7fcfb 0%, #eef8f7 34%, #f8fcfc 68%, #ffffff 100%);
  font-family: 'Tajawal', 'Segoe UI', Tahoma, Arial, sans-serif;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.hero-section--licenses {
  min-height: 100vh;
  display: flex;
  align-items: center;
}

.landing-section {
  position: relative;
  padding: 96px 0;
}

.section-heading {
  max-width: 760px;
  margin: 0 auto 40px;
  text-align: center;
}

.section-heading--split {
  max-width: none;
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 24px;
  text-align: right;
}

.section-heading__eyebrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  border-radius: 999px;
  background: rgba(42, 148, 178, 0.12);
  color: var(--app-primary, #2a94b2);
  font-size: 0.92rem;
  font-weight: 700;
  margin-bottom: 18px;
}

.section-heading__title {
  font-size: clamp(2rem, 4vw, 3rem);
  line-height: 1.2;
  font-weight: 800;
  color: #08384a;
  margin-bottom: 16px;
}

.section-heading__text {
  font-size: 1.04rem;
  line-height: 1.9;
  color: #5a7a87;
}

.section-heading__text--compact {
  max-width: 420px;
  margin: 0;
}

.hero-layout {
  display: block;
}

.hero-copy--licenses {
  text-align: center;
  max-width: 760px;
  margin: 0 auto;
}

.hero-radial {
  position: absolute;
  border-radius: 50%;
  filter: blur(30px);
  pointer-events: none;
}

.hero-radial--one {
  width: 280px;
  height: 280px;
  right: 6%;
  top: 22%;
  background: rgba(56, 189, 248, 0.14);
}

.hero-radial--two {
  width: 220px;
  height: 220px;
  left: 10%;
  bottom: 18%;
  background: rgba(110, 231, 183, 0.1);
}


.hero-primary-btn,
.hero-outline-btn--licenses {
  height: 54px;
  padding: 0 28px;
  border-radius: 999px;
  font-size: 1rem;
  font-weight: 800;
  cursor: pointer;
  border: 1px solid transparent;
  transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
}

.hero-primary-btn {
  color: #0b4c61;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(229, 241, 246, 0.94) 100%);
  border-color: rgba(255, 255, 255, 0.55);
  box-shadow: 0 24px 45px -28px rgba(7, 46, 61, 0.52);
}

.hero-primary-btn:hover,
.hero-outline-btn--licenses:hover {
  transform: translateY(-2px);
}

.hero-primary-btn:hover {
  box-shadow: 0 28px 52px -30px rgba(7, 46, 61, 0.62);
}

.hero-outline-btn--licenses {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.24);
  backdrop-filter: blur(10px);
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


.achievements-section {
  margin-top: -18px;
}

.achievements-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}

.achievement-card {
  position: relative;
  padding: 28px 24px;
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(42, 148, 178, 0.98) 0%, rgba(17, 101, 126, 0.96) 100%);
  border: 1px solid rgba(170, 220, 232, 0.16);
  box-shadow: 0 30px 56px -40px rgba(7, 65, 88, 0.5);
  overflow: hidden;
  animation: achievementCardReveal 0.8s ease both;
}

.achievement-card:nth-child(2) {
  animation-delay: 0.08s;
}

.achievement-card:nth-child(3) {
  animation-delay: 0.16s;
}

.achievement-card:nth-child(4) {
  animation-delay: 0.24s;
}

.achievement-card::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), transparent 45%);
  pointer-events: none;
}

.achievement-card__icon {
  position: relative;
  z-index: 1;
  width: 54px;
  height: 54px;
  display: grid;
  place-items: center;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.12);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
  margin-bottom: 24px;
}

.achievement-card__value {
  position: relative;
  z-index: 1;
  font-size: clamp(2rem, 4vw, 2.7rem);
  font-weight: 800;
  color: #ffffff;
  margin-bottom: 12px;
  letter-spacing: -0.03em;
}

.achievement-card__title {
  position: relative;
  z-index: 1;
  font-size: 1.05rem;
  font-weight: 800;
  margin-bottom: 0;
  color: #dff6fb;
}

@keyframes achievementCardReveal {
  from {
    opacity: 0;
    transform: translateY(18px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.programs-section {
  background: linear-gradient(180deg, rgba(239, 248, 249, 0.75), rgba(255, 255, 255, 0.96));
}

.programs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 28px;
}

.program-card {
  border-radius: 20px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 2px 16px rgba(8, 56, 74, 0.08), 0 1px 4px rgba(0, 0, 0, 0.05);
  opacity: 0;
  transform: translateX(var(--program-card-reveal-offset, 0));
  transition:
    opacity 0.58s ease,
    transform 0.58s cubic-bezier(0.22, 1, 0.36, 1),
    box-shadow 0.22s ease;
  display: flex;
  flex-direction: column;
  border: 1px solid #e8f0f3;
}

.program-card--from-right {
  --program-card-reveal-offset: 34px;
}

.program-card--from-left {
  --program-card-reveal-offset: -34px;
}

.program-card--reveal-visible {
  opacity: 1;
  transform: translateX(0);
}

.program-card--reveal-visible:nth-child(2) {
  transition-delay: 0.08s;
}

.program-card--reveal-visible:nth-child(3) {
  transition-delay: 0.14s;
}

.program-card--reveal-visible:nth-child(4) {
  transition-delay: 0.2s;
}

.program-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 36px rgba(8, 56, 74, 0.13), 0 2px 8px rgba(0, 0, 0, 0.07);
}

.program-card--reveal-visible:hover {
  transform: translateY(-4px);
  transition-delay: 0s;
}

.program-card--poster-design {
  position: relative;
  min-height: 420px;
  padding: 30px 28px;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 25px 60px rgba(11, 47, 74, 0.12);
}

.program-card--poster-design:hover {
  transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
  .program-card {
    opacity: 1;
    transform: none;
    transition: box-shadow 0.22s ease;
  }
}

.manager-license-card__circle {
  position: absolute;
  right: -190px;
  bottom: -170px;
  z-index: 0;
  width: 430px;
  height: 430px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--poster-circle-start, #eaf5fb), var(--poster-circle-end, #f8fbfd));
}

.manager-license-card__header,
.manager-license-card__content {
  position: relative;
  z-index: 1;
}

.manager-license-card__header {
  margin-bottom: 24px;
  text-align: right;
}

.manager-license-card__header h3 {
  margin: 0;
  color: var(--poster-title, #07385f);
  font-size: 2rem;
  font-weight: 900;
}

.manager-license-card__line {
  width: 58px;
  height: 5px;
  margin: 18px 0 22px auto;
  border-radius: 20px;
  background: linear-gradient(90deg, var(--poster-line-start, #1d75b9), var(--poster-line-end, #6bb7e8));
}

.manager-license-card__header p {
  max-width: 100%;
  margin: 0;
  color: var(--poster-text, #1d3f5d);
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.75;
}

.manager-license-card__content {
  display: grid;
  grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
  align-items: center;
  gap: 24px;
  direction: ltr;
}

.manager-license-card__illustration {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 240px;
  transform: scale(0.8);
  transform-origin: center;
}

.manager-license-card__platform {
  position: absolute;
  bottom: 10px;
  width: min(420px, 92%);
  height: 80px;
  border-radius: 50%;
  background: linear-gradient(180deg, var(--poster-platform-start, #eef8fb), var(--poster-platform-end, #d9eef5));
  box-shadow: 0 25px 35px var(--poster-platform-shadow, rgba(20, 95, 145, 0.13));
}

.manager-license-card__platform::after {
  content: '';
  position: absolute;
  right: 25px;
  bottom: -22px;
  left: 25px;
  z-index: -1;
  height: 45px;
  border-radius: 50%;
  background: var(--poster-platform-after, #eef4f7);
}

.manager-license-card__leaves {
  position: absolute;
  bottom: 80px;
  width: min(520px, 100%);
  height: 230px;
}

.manager-license-card__leaf {
  position: absolute;
  width: 42px;
  height: 120px;
  border-radius: 90% 0 90% 0;
  background: linear-gradient(160deg, var(--poster-leaf-start, #7ec4d9), var(--poster-leaf-end, #2b8dbd));
  opacity: 0.85;
}

.manager-license-card__leaf--one {
  bottom: 10px;
  left: 60px;
  transform: rotate(-35deg);
}

.manager-license-card__leaf--two {
  bottom: 30px;
  left: 125px;
  transform: rotate(-15deg);
}

.manager-license-card__leaf--three {
  right: 70px;
  bottom: 20px;
  transform: rotate(35deg);
}

.manager-license-card__leaf--four {
  right: 130px;
  bottom: 40px;
  transform: rotate(55deg);
}

.manager-license-card__document {
  position: relative;
  z-index: 2;
  width: 198px;
  height: 258px;
  padding: 34px 24px;
  border-radius: 26px;
  background: #fff;
  box-shadow: 0 18px 40px var(--poster-document-shadow, rgba(9, 55, 95, 0.14));
}

.manager-license-card__doc-line {
  display: block;
  height: 6px;
  margin-bottom: 13px;
  border-radius: 20px;
  background: var(--poster-doc-line, #b8dce7);
}

.manager-license-card__doc-line--short {
  width: 50%;
}

.manager-license-card__doc-line--medium {
  width: 72%;
}

.manager-license-card__doc-line--long {
  width: 100%;
}

.manager-license-card__doc-line--bottom {
  position: absolute;
  right: 24px;
  bottom: 24px;
  width: 50%;
}

.manager-license-card__doc-spacer {
  display: block;
  height: 30px;
}

.manager-license-card__briefcase {
  position: absolute;
  top: 28px;
  right: 24px;
  width: 46px;
  height: 38px;
  border: 4px solid var(--poster-accent, #0e7da7);
  border-radius: 9px;
}

.manager-license-card__briefcase::before {
  content: '';
  position: absolute;
  top: -14px;
  left: 50%;
  width: 18px;
  height: 9px;
  border: 4px solid var(--poster-accent, #0e7da7);
  border-bottom: 0;
  border-radius: 8px 8px 0 0;
  transform: translateX(-50%);
}

.manager-license-card__briefcase::after {
  content: '';
  position: absolute;
  top: 13px;
  right: -4px;
  left: -4px;
  height: 4px;
  background: var(--poster-accent, #0e7da7);
}

.manager-license-card__certificate {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 60px;
  height: 60px;
  fill: none;
  stroke: var(--poster-accent, #1f8f57);
  stroke-width: 1.75;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.manager-license-card__signature {
  position: absolute;
  bottom: 30px;
  left: 24px;
  width: 74px;
  height: 28px;
  border-bottom: 4px solid var(--poster-accent, #0e7da7);
  border-radius: 50%;
  transform: rotate(-8deg);
}

.manager-license-card__dots {
  position: absolute;
  top: 28px;
  left: 14px;
  display: grid;
  grid-template-columns: repeat(4, 6px);
  gap: 9px;
  opacity: 0.35;
}

.manager-license-card__dots span {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--poster-dot, #8ac6d8);
}

.manager-license-card__features {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  direction: rtl;
}

.manager-license-card__feature {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  min-height: 58px;
  padding: 10px 12px;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 18px 35px var(--poster-feature-shadow, rgba(11, 47, 74, 0.08));
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.manager-license-card__feature:hover {
  transform: translateY(-4px);
  box-shadow: 0 22px 42px var(--poster-feature-shadow-hover, rgba(11, 47, 74, 0.12));
}

.manager-license-card__icon-box {
  order: -1;
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-left: 1px solid var(--poster-icon-border, #d6e8ef);
  border-radius: 14px;
  background: linear-gradient(135deg, var(--poster-icon-bg-start, #eef8fb), var(--poster-icon-bg-end, #d9eef5));
}

.manager-license-card__feature span {
  flex: 1;
  color: var(--poster-feature-text, #123a58);
  font-size: 0.94rem;
  font-weight: 700;
  text-align: right;
}

.manager-license-card__icon {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: var(--poster-accent, #087da8);
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.program-card__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.program-card__icon-wrap {
  width: 50px;
  height: 50px;
  display: grid;
  place-items: center;
  border-radius: 14px;
}

.program-card__status-badge {
  font-size: 0.78rem;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 999px;
  letter-spacing: 0.02em;
}

.program-card__body {
  padding: 26px 28px 28px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.program-card__title {
  font-size: 1.35rem;
  font-weight: 800;
  margin: 0 0 12px;
}

.program-card__text {
  color: #4a6270;
  line-height: 1.85;
  margin-bottom: 16px;
}

.program-card__audience {
  font-weight: 700;
  color: #334e5a;
  margin-bottom: 18px;
  font-size: 0.92rem;
}

.program-card__stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0 0 18px;
}

.program-card__stat {
  display: grid;
  gap: 4px;
  min-height: 68px;
  place-items: center;
  padding: 10px 8px;
  border: 1px solid rgba(8, 56, 74, 0.1);
  border-radius: 16px;
  background: rgba(232, 246, 251, 0.62);
  text-align: center;
}

.program-card__stat strong {
  color: #08384a;
  font-size: 1.28rem;
  font-weight: 900;
  line-height: 1;
}

.program-card__stat span {
  color: #587785;
  font-size: 0.78rem;
  font-weight: 800;
}

.program-card__features {
  list-style: none;
  padding: 0;
  margin: 0 0 24px;
  display: grid;
  gap: 10px;
}

.program-card__features li {
  position: relative;
  padding-inline-start: 18px;
  color: #587785;
  line-height: 1.7;
}

.program-card__features li::before {
  content: '';
  position: absolute;
  inset-inline-start: 0;
  top: 10px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--dot-color, #2a94b2);
}

.program-card__action {
  margin-top: auto;
  height: 50px;
  border-radius: 14px;
  font-weight: 800;
  font-size: 0.95rem;
  border: 2px solid #d1dfe5;
  cursor: pointer;
  color: #fff;
  transition: opacity 0.18s ease;
}

.program-card__action:disabled {
  background: #edf3f5 !important;
  border-color: #d1dfe5 !important;
  color: #8096a0;
  cursor: default;
}

.program-card__action:not(:disabled):hover {
  opacity: 0.88;
}

.faq-section {
  padding-top: 84px;
  background: linear-gradient(180deg, rgba(239, 248, 249, 0.4), rgba(255, 255, 255, 0.96));
}

.faq-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 22px;
}

.faq-card {
  padding: 28px 24px;
  border-radius: 28px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbfc 100%);
  border: 1px solid rgba(211, 229, 234, 0.95);
  box-shadow: 0 24px 44px -36px rgba(8, 65, 89, 0.28);
}

.faq-card__question {
  margin: 0 0 14px;
  color: #2a94b2;
  font-size: 1.16rem;
  font-weight: 800;
  line-height: 1.6;
}

.faq-card__answer {
  margin: 0;
  color: #5f7d89;
  line-height: 1.95;
}

.landing-shell {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
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
  padding: 0;
  border-radius: 0;
  background: transparent;
  backdrop-filter: none;
}

.site-logo {
  width: auto;
  height: 62px;
  object-fit: contain;
  transition: 0.35s ease;
  filter: drop-shadow(0 10px 24px rgba(0, 0, 0, 0.18));
}

.site-logo--light {
  filter: brightness(0) invert(1) drop-shadow(0 10px 24px rgba(0, 0, 0, 0.26));
}

.brand-mark__text {
  color: #08384a;
  font-size: 1.12rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  transition: color 0.3s ease;
}

.brand-mark__text--light {
  color: #ffffff;
}

.landing-header--scrolled .brand-mark__logos {
  background: transparent;
  box-shadow: none;
}

.landing-header__actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-inline-start: auto;
}

.licenses-menu-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #ffffff;
  transition: transform 0.25s ease, opacity 0.25s ease;
  cursor: pointer;
  outline: none;
}

.licenses-menu-toggle:hover {
  transform: translateY(-1px);
  opacity: 0.88;
}

.landing-header--scrolled .licenses-menu-toggle {
  color: #08384a;
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

.hero-section {
  position: relative;
  padding-top: 140px;
  padding-bottom: 120px;
  display: flex;
  align-items: center;
  overflow: hidden;
  background: #08384a;
}

.hero-orbit {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  border-radius: 50%;
  border: 1px dashed rgba(255, 255, 255, 0.08);
  pointer-events: none;
}
.hero-orbit--large { width: 1400px; height: 1400px; animation: orbitSpin 120s linear infinite; }
.hero-orbit--medium { width: 900px; height: 900px; animation: orbitSpin 80s linear infinite reverse; }
.hero-orbit--small { width: 520px; height: 520px; animation: orbitSpin 56s linear infinite; }
@keyframes orbitSpin { from { transform: translate(-50%, -50%) rotate(0deg); } to { transform: translate(-50%, -50%) rotate(360deg); } }

.hero-glow {
  position: absolute;
  top: -20%;
  right: -10%;
  width: 800px;
  height: 800px;
  background: radial-gradient(circle, rgba(42, 148, 178, 0.15) 0%, rgba(8, 56, 74, 0) 70%);
  border-radius: 50%;
  pointer-events: none;
  filter: blur(40px);
}

.hero-grid {
  position: absolute;
  inset: 0;
  background-image: 
    linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
}

.hero-top-shade {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 180px;
  background: linear-gradient(180deg, rgba(3, 27, 36, 0.7) 0%, rgba(8, 56, 74, 0) 100%);
  pointer-events: none;
  z-index: 10;
}

.hero-bottom-fade {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 140px;
  background: linear-gradient(0deg, #f7fcfb 0%, rgba(247, 252, 251, 0) 100%);
  z-index: 10;
}

.hero-section__inner {
  position: relative;
  z-index: 15;
}

.hero-logos {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 20px;
  margin-bottom: 20px;
}

.hero-logos--centered {
  justify-content: center;
}

.hero-logo {
  height: 72px;
  width: auto;
  object-fit: contain;
  filter: drop-shadow(0 12px 24px rgba(0, 0, 0, 0.3));
}

.hero-logo--hero-white {
  height: 156px;
  transform: translateY(-18px);
  filter: brightness(0) invert(1) drop-shadow(0 20px 36px rgba(0, 0, 0, 0.28));
}

.hero-divider {
  width: 58px;
  height: 4px;
  background: var(--app-primary, #2a94b2);
  border-radius: 4px;
  margin: 0 auto 28px;
}

.hero-title {
  font-size: clamp(2.5rem, 5vw, 4rem);
  font-weight: 800;
  line-height: 1.2;
  color: #fff;
  margin-bottom: 24px;
  letter-spacing: -0.02em;
}

.hero-text {
  font-size: clamp(1.08rem, 2vw, 1.2rem);
  line-height: 1.7;
  color: #b1d0d9;
  margin: 0 auto 40px;
  font-weight: 500;
  max-width: 760px;
}

.hero-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  flex-wrap: wrap;
}

.hero-outline-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  height: 52px;
  padding: 0 28px;
  border-radius: 26px;
  font-size: 1.05rem;
  font-weight: 700;
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.3);
  text-decoration: none;
  transition: 0.3s;
  backdrop-filter: blur(10px);
}
.hero-outline-btn:hover {
  background: rgba(255, 255, 255, 0.15);
  border-color: #fff;
  transform: translateY(-2px);
}

.landing-footer {
  background: #fff;
  border-top: 1px solid #edf4f5;
  padding-top: 80px;
  margin-top: auto;
}

.landing-footer__grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 48px;
  margin-bottom: 60px;
}

.footer-brand__logo {
  height: 48px;
  margin-bottom: 16px;
}

.footer-brand__title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #2a94b2;
  margin-bottom: 16px;
}

.footer-text {
  font-size: 0.95rem;
  line-height: 1.7;
  color: #4a7585;
}

.landing-footer h4 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #2a94b2;
  margin-bottom: 24px;
}

.footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-links li {
  margin-bottom: 14px;
}

.footer-links a {
  color: #4a7585;
  text-decoration: none;
  font-size: 0.95rem;
  font-weight: 500;
  transition: 0.2s;
}

.footer-links a:hover {
  color: #2a94b2;
}

.footer-links__muted {
  color: #6b8791;
  cursor: default;
  font-size: 0.95rem;
  font-weight: 500;
}

.footer-links--contact li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #4a7585;
  font-size: 0.95rem;
  font-weight: 500;
}

.footer-links--contact .v-icon {
  color: #2a94b2;
}

.landing-footer__bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 0;
  border-top: 1px solid #edf4f5;
  color: #6c91a0;
  font-size: 0.9rem;
  font-weight: 500;
  flex-wrap: wrap;
  gap: 16px;
}

@media (max-width: 1024px) {
  .achievements-grid,
  .achievement-band,
  .programs-grid,
  .journey-grid {
    grid-template-columns: 1fr 1fr;
  }

  .manager-license-card__content {
    grid-template-columns: minmax(180px, 220px) minmax(0, 1fr);
    gap: 18px;
  }

  .section-heading--split {
    display: block;
  }

  .landing-footer__grid {
    grid-template-columns: 1fr 1fr;
    gap: 40px;
  }
}

@media (max-width: 600px) {
  .landing-page {
    overflow-x: clip;
  }

  .landing-section {
    padding: 72px 0;
  }

  .landing-header__inner {
    min-height: 84px;
    padding-top: 10px;
    padding-bottom: 10px;
  }

  .brand-mark {
    gap: 10px;
    margin-inline-end: 0;
  }

  .brand-mark__text {
    display: none;
  }

  .site-logo {
    height: 52px;
  }

  .hero-section {
    min-height: auto;
    padding-top: 116px;
    padding-bottom: 80px;
  }

  .hero-title {
    font-size: clamp(2rem, 10vw, 2.7rem);
    line-height: 1.28;
  }

  .hero-text,
  .program-card__text,
  .faq-card__answer {
    line-height: 1.82;
  }

  .hero-text {
    font-size: 0.98rem;
    margin-bottom: 28px;
  }

  .hero-stats-strip,
  .achievements-grid,
  .programs-grid,
  .faq-grid,
  .landing-footer__grid {
    grid-template-columns: 1fr;
    gap: 32px;
  }

  .hero-actions {
    width: 100%;
    flex-direction: column;
  }

  ::v-deep(.licenses-menu) {
    right: 16px !important;
    left: auto !important;
    width: min(330px, calc(100vw - 32px)) !important;
    max-width: calc(100vw - 32px) !important;
  }

  .hero-primary-btn,
  .hero-outline-btn--licenses,
  .program-card__action,
  .hero-panel__action {
    width: 100%;
    min-height: 52px;
  }

  .hero-title,
  .section-heading,
  .hero-copy--licenses {
    text-align: center;
  }

  .hero-logos,
  .hero-actions {
    justify-content: center;
  }

  .hero-logo--hero-white {
    height: 116px;
    transform: translateY(-8px);
  }

  .program-card__body,
  .faq-card,
  .achievement-card {
    padding: 22px 18px;
    border-radius: 22px;
  }

  .program-card {
    border-radius: 18px;
  }

  .program-card--poster-design {
    min-height: auto;
    padding: 22px 18px;
    border-radius: 22px;
  }

  .manager-license-card__circle {
    right: -240px;
    bottom: -220px;
  }

  .manager-license-card__header {
    margin-bottom: 18px;
  }

  .manager-license-card__header h3 {
    font-size: 1.35rem;
  }

  .manager-license-card__header p {
    font-size: 0.9rem;
    line-height: 1.75;
  }

  .manager-license-card__content {
    grid-template-columns: minmax(118px, 132px) minmax(0, 1fr);
    gap: 14px;
    align-items: start;
  }

  .manager-license-card__line {
    margin-inline-start: auto;
  }

  .manager-license-card__illustration {
    min-height: 170px;
    transform: none;
  }

  .manager-license-card__dots {
    top: 10px;
    left: 6px;
    grid-template-columns: repeat(3, 6px);
    gap: 8px;
  }

  .manager-license-card__leaves {
    bottom: 30px;
    width: 100%;
    height: 110px;
  }

  .manager-license-card__leaf {
    width: 24px;
    height: 72px;
  }

  .manager-license-card__leaf--one {
    left: 8px;
    bottom: 8px;
  }

  .manager-license-card__leaf--two {
    left: 38px;
    bottom: 18px;
  }

  .manager-license-card__leaf--three {
    right: 12px;
    bottom: 12px;
  }

  .manager-license-card__leaf--four {
    right: 40px;
    bottom: 24px;
  }

  .manager-license-card__platform {
    bottom: 2px;
    width: 100%;
    height: 42px;
  }

  .manager-license-card__platform::after {
    right: 18px;
    bottom: -12px;
    left: 18px;
    height: 24px;
  }

  .manager-license-card__document {
    width: 116px;
    height: 154px;
    padding: 28px 16px 22px;
    border-radius: 18px;
  }

  .manager-license-card__certificate {
    top: 16px;
    right: 14px;
    width: 42px;
    height: 42px;
    stroke-width: 2;
  }

  .manager-license-card__briefcase {
    top: 20px;
    right: 18px;
    width: 34px;
    height: 28px;
    border-width: 3px;
  }

  .manager-license-card__briefcase::before {
    top: -11px;
    width: 14px;
    height: 7px;
    border-width: 3px;
  }

  .manager-license-card__briefcase::after {
    top: 10px;
    right: -3px;
    left: -3px;
    height: 3px;
  }

  .manager-license-card__doc-line {
    height: 4px;
    margin-bottom: 8px;
  }

  .manager-license-card__doc-spacer {
    height: 18px;
  }

  .manager-license-card__signature {
    bottom: 22px;
    left: 16px;
    width: 44px;
    height: 16px;
    border-bottom-width: 3px;
  }

  .manager-license-card__doc-line--bottom {
    right: 16px;
    bottom: 16px;
  }

  .manager-license-card__features {
    gap: 8px;
  }

  .manager-license-card__feature {
    min-height: 54px;
    padding: 8px 10px;
    border-radius: 14px;
    gap: 10px;
  }

  .manager-license-card__icon-box {
    width: 40px;
    height: 40px;
    border-radius: 12px;
  }

  .manager-license-card__icon {
    width: 22px;
    height: 22px;
  }

  .manager-license-card__feature span {
    font-size: 0.86rem;
    line-height: 1.5;
  }

  .program-card__top {
    gap: 12px;
  }

  .program-card__title {
    font-size: 1.18rem;
  }

  .program-card__status-badge {
    padding-inline: 10px;
  }

  .section-heading__title,
  .our-numbers__title {
    line-height: 1.32;
  }

  .hero-divider {
    margin-inline: auto;
  }

  .hero-text {
    margin-inline: auto;
  }

  .landing-footer__bottom {
    flex-direction: column;
    text-align: center;
  }
}

/* Modals */
.login-modal__panel,
.profile-modal__panel {
  padding: 16px;
}

.login-modal__brand {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-bottom: 32px;
}

.login-modal__logo {
  height: 48px;
  object-fit: contain;
}

.login-modal__copy {
  text-align: center;
  margin-bottom: 32px;
}

.login-modal__copy h3 {
  font-size: 1.5rem;
  font-weight: 800;
  color: #2a94b2;
}

.profile-modal__card {
  background: #f7fcfb;
  border: 1px solid #edf4f5;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
}

.profile-modal__label {
  font-size: 0.9rem;
  font-weight: 600;
  color: #4a7585;
  margin-bottom: 4px;
}

.profile-modal__value {
  font-size: 1.1rem;
  font-weight: 700;
  color: #2a94b2;
}

/* ── أرقامنا ─────────────────────────────────────── */
.achievements-section {
  background: linear-gradient(160deg, #f5f8fb 0%, #eef3f7 100%);
  padding: 64px 0 72px;
}

.our-numbers {
  text-align: center;
}

.our-numbers__title {
  font-size: clamp(1.8rem, 3.5vw, 2.8rem);
  font-weight: 900;
  color: #08384a;
  margin: 0 0 52px;
  letter-spacing: -0.02em;
}

.our-numbers__row {
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 820px;
  margin: 0 auto;
}

.our-numbers__graduates {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0;
  max-width: 1080px;
  margin: 48px auto 0;
  padding-top: 28px;
  border-top: 1px solid rgba(26, 106, 133, 0.18);
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
}

.our-numbers__graduate-item span {
  max-width: 12rem;
  color: #4d6677;
  font-size: 0.88rem;
  font-weight: 800;
  line-height: 1.65;
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

.our-numbers__graduate-item::after {
  order: 3;
}

.our-numbers__stat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 8px 32px;
  transition: transform 0.2s ease;
}

.our-numbers__stat:hover {
  transform: translateY(-3px);
}

.our-numbers__num {
  font-size: clamp(2.4rem, 4.5vw, 3.8rem);
  font-weight: 800;
  color: #1a6a85;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
  display: flex;
  align-items: flex-start;
  gap: 2px;
}

.our-numbers__plus {
  font-size: 55%;
  font-weight: 700;
  color: #2a94b2;
  margin-top: 4px;
}

.our-numbers__lbl {
  font-size: clamp(0.9rem, 1.6vw, 1.1rem);
  font-weight: 500;
  color: #1a3a4f;
}

.our-numbers__divider {
  width: 1px;
  height: 72px;
  background: linear-gradient(to bottom, transparent, #b0c8d8, transparent);
  flex-shrink: 0;
}

@media (max-width: 900px) {
  .our-numbers__graduates {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    row-gap: 28px;
  }

  .our-numbers__graduate-item:nth-child(odd) {
    border-inline-start: 0;
  }

  .our-numbers__graduate-item:nth-child(n + 3) {
    padding-top: 24px;
    border-top: 1px solid rgba(26, 106, 133, 0.13);
  }
}

@media (max-width: 600px) {
  .our-numbers__row {
    flex-direction: column;
    gap: 32px;
  }

  .our-numbers__graduates {
    grid-template-columns: 1fr;
    gap: 0;
    margin-top: 36px;
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

  .our-numbers__divider {
    width: 80px;
    height: 1px;
    background: linear-gradient(to right, transparent, #b0c8d8, transparent);
  }

  .our-numbers__stat {
    padding: 0;
  }

  .our-numbers__num {
    font-size: clamp(2.1rem, 12vw, 3rem);
  }

  .our-numbers__lbl {
    line-height: 1.6;
  }
}
</style>
