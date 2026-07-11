<template>
  <nav
    v-if="pageCount > 1"
    class="app-pagination"
    aria-label="التنقل بين الصفحات"
  >
    <button
      type="button"
      class="app-pagination__button"
      :disabled="value <= 1"
      aria-label="الصفحة السابقة"
      @click="setPage(value - 1)"
    >
      <v-icon size="20">
        mdi-chevron-right
      </v-icon>
    </button>
    <button
      v-for="page in visiblePages"
      :key="page"
      type="button"
      class="app-pagination__button"
      :class="{ 'app-pagination__button--active': page === value }"
      :aria-current="page === value ? 'page' : null"
      @click="setPage(page)"
    >
      {{ page }}
    </button>
    <button
      type="button"
      class="app-pagination__button"
      :disabled="value >= pageCount"
      aria-label="الصفحة التالية"
      @click="setPage(value + 1)"
    >
      <v-icon size="20">
        mdi-chevron-left
      </v-icon>
    </button>
  </nav>
</template>

<script>
export default {
  name: 'AppPagination',
  props: {
    value: { type: Number, default: 1 },
    pageCount: { type: Number, default: 1 },
    maxVisible: { type: Number, default: 5 },
  },
  computed: {
    visiblePages() {
      const count = Math.min(this.maxVisible, this.pageCount);
      const start = Math.max(1, Math.min(this.value - Math.floor(count / 2), this.pageCount - count + 1));
      return Array.from({ length: count }, (_, index) => start + index);
    },
  },
  methods: {
    setPage(page) {
      const nextPage = Math.max(1, Math.min(this.pageCount, page));
      this.$emit('input', nextPage);
      this.$emit('change', nextPage);
    },
  },
};
</script>
