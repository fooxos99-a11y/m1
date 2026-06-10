<template>
  <div
    class="admin-links"
    :class="{ 'admin-links--embedded': embedded }"
  >
    <section class="admin-links__hero">
      <div class="admin-links__eyebrow">
        الروابط المباشرة
      </div>
      <h2 class="admin-links__title">
        صفحات الوصول السريع
      </h2>
      <p class="admin-links__subtitle">
        انسخ الروابط المباشرة للاختبارات والمهام وشاركها دون الحاجة لفتح نافذة مستقلة.
      </p>
    </section>

    <section class="admin-links__grid">
      <article
        v-for="item in directAccessLinks"
        :key="item.id"
        class="admin-links__card"
      >
        <div class="admin-links__card-title">
          {{ item.title }}
        </div>
        <div class="admin-links__card-url">
          {{ item.url }}
        </div>
        <AppButton
          variant="secondary"
          class="admin-links__copy"
          @click="copyLink(item.url)"
        >
          <span>نسخ الرابط</span>
          <v-icon small>
            mdi-content-copy
          </v-icon>
        </AppButton>
      </article>
    </section>
  </div>
</template>

<script>
import { AppButton } from '../components/ui';

export default {
  name: 'AdminLinksView',
  components: {
    AppButton,
  },
  props: {
    embedded: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    directAccessLinks() {
      const origin = typeof window !== 'undefined' ? window.location.origin : '';

      return [
        {
          id: 'pre',
          title: 'رابط الاختبارات القبلية',
          url: `${origin}/course/pre`,
        },
        {
          id: 'post',
          title: 'رابط الاختبارات البعدية',
          url: `${origin}/course/post`,
        },
        {
          id: 'tasks',
          title: 'رابط المهام الأدائية',
          url: `${origin}/tasks`,
        },
        {
          id: 'final-exam',
          title: 'رابط الاختبار النهائي',
          url: `${origin}/final-exam`,
        },
      ];
    },
  },
  methods: {
    async copyLink(url) {
      if (!url) {
        return;
      }

      try {
        if (navigator?.clipboard?.writeText) {
          await navigator.clipboard.writeText(url);
        }

        this.$toast.success('تم نسخ الرابط');
      } catch (error) {
        this.$toast.error(error?.message || 'تعذر نسخ الرابط');
      }
    },
  },
};
</script>

<style scoped>
.admin-links {
  display: grid;
  gap: 18px;
}

.admin-links__hero,
.admin-links__card {
  border: 1px solid rgba(255, 255, 255, 0.82);
  border-radius: 28px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 250, 250, 0.96) 100%);
  box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
}

.admin-links__hero {
  padding: 24px 28px;
}

.admin-links__eyebrow {
  color: #6b7f90;
  font-size: 0.9rem;
  font-weight: 800;
}

.admin-links__title {
  margin: 8px 0 0;
  color: #0f3554;
  font-size: 1.7rem;
  font-weight: 900;
}

.admin-links__subtitle {
  margin: 12px 0 0;
  color: #5c7386;
  font-size: 1rem;
  line-height: 1.9;
}

.admin-links__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.admin-links__card {
  padding: 22px;
}

.admin-links__card-title {
  color: #0f3554;
  font-size: 1.05rem;
  font-weight: 900;
}

.admin-links__card-url {
  margin-top: 10px;
  color: #587186;
  font-size: 0.95rem;
  line-height: 1.8;
  word-break: break-word;
  direction: ltr;
  text-align: left;
}

.admin-links__copy {
  margin-top: 18px;
}

@media (max-width: 860px) {
  .admin-links__grid {
    grid-template-columns: 1fr;
  }
}
</style>
