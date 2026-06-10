<template>
  <div class="app-table">
    <div class="app-table__scroll">
      <table>
        <thead>
          <tr>
            <th v-for="column in columns" :key="column.key" :style="{ width: column.width || null }">{{ column.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, rowIndex) in items" :key="item[itemKey] ?? rowIndex" @click="$emit('row-click', item)">
            <td v-for="column in columns" :key="column.key" :data-label="column.label">
              <slot :name="`cell-${column.key}`" :item="item" :value="item[column.key]" :index="rowIndex">
                {{ formatValue(item[column.key], column, item) }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <AppLoadingSpinner v-if="loading" show-label class="app-table__state" />
    <AppEmptyState v-else-if="!items.length" :title="emptyTitle" :description="emptyDescription" class="app-table__state" />
  </div>
</template>

<script>
import AppEmptyState from './AppEmptyState.vue';
import AppLoadingSpinner from './AppLoadingSpinner.vue';

export default {
  name: 'AppTable',
  components: { AppEmptyState, AppLoadingSpinner },
  props: {
    columns: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    itemKey: { type: String, default: 'id' },
    loading: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'لا توجد بيانات' },
    emptyDescription: { type: String, default: '' },
  },
  methods: {
    formatValue(value, column, item) {
      return typeof column.format === 'function' ? column.format(value, item) : (value ?? '');
    },
  },
};
</script>
