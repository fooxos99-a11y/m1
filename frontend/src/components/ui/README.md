# UI Components

استورد المكونات من ملف واحد:

```js
import {
  AppButton,
  AppInput,
  AppTextField,
  AppSelect,
  AppDialog,
  AppCard,
  AppTable,
  AppPagination,
  AppEmptyState,
  AppErrorState,
  AppLoadingSpinner,
  AppSkeleton,
} from '@/components/ui';
```

استخدم مكونات `App*` بدل إنشاء زر أو حقل أو حالة تحميل جديدة داخل الصفحة.

`AppTextField` هو الغلاف الموحد لحقول Vuetify داخل لوحة التحكم، ويمرر الخصائص والأحداث والفتحات دون تغيير.
