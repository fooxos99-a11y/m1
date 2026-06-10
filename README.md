# ممارس

هذا مشروع منصة تعليمية وإدارية، مخصص لإدارة التسجيل والمتابعة والاختبارات والحضور والتقارير من مكان واحد.

المشروع مبني بشكل يفصل بين الواجهة والخلفية:

- `frontend/` واجهة المستخدم بـ Vue 2 و Vuetify.
- `backend/` API ولوحة التحكم والصلاحيات وقاعدة البيانات بـ Laravel 12.

## وش يسوي المشروع؟

المشروع يغطي أغلب الشغل التشغيلي اليومي، مثل:

- استقبال التسجيلات من الواجهة العامة.
- تسجيل الدخول وإدارة المستخدمين والصلاحيات.
- إدارة الطلاب والمقرئين والمتدربين.
- تشغيل الاختبارات القبلية والبعدية والمهام.
- إدارة الاختبار النهائي والأسئلة والنتائج.
- حفظ الحضور والمتابعة.
- الإشعارات وسجل النشاط داخل لوحة التحكم.
- صفحات عامة وصفحات خاصة بالمستخدمين حسب الدور.

## التقنيات المستخدمة

- Backend: Laravel 12
- Frontend: Vue 2 + Vuetify
- Database: MySQL للإنتاج وSQLite مناسب للتجربة المحلية
- Auth: Laravel Fortify + Sanctum
- Permissions: Spatie Permission
- Activity Logs: Spatie Activitylog
- File Handling: Spatie Medialibrary
- Realtime: Pusher
- Cache / Queue support: Redis

## التشغيل السريع

من جذر المشروع:

تشغيل الواجهة:

```bash
npm run dev
```

تشغيل البيئة المحلية كاملة:

```bash
npm run dev:all
```

بناء نسخة الإنتاج للواجهة:

```bash
npm run build
```

تشغيل الـ backend:

```bash
npm run serve:backend
```

أو على المنفذ المستخدم محليًا مع الواجهة:

```bash
npm run serve:backend:8001
```

تشغيل الـ migrations:

```bash
npm run migrate:backend
```

تشغيل الاختبارات:

```bash
npm test
```

## إعداد قاعدة البيانات

للإنتاج الأفضل تعتمد MySQL.

الخطوات:

1. انسخ `backend/.env.mysql.example` إلى `backend/.env`.
2. حط بيانات الاتصال بقاعدة البيانات.
3. شغّل `php artisan key:generate` داخل `backend/`.
4. شغّل `php artisan migrate --force` داخل `backend/`.

إذا `php` مو موجود في `PATH` عندك، تقدر تحدد متغير `PHP_BIN` قبل استخدام أوامر الـ backend من الجذر.

## ملاحظات تشغيل مهمة

- المصدر الأساسي لهيكل قاعدة البيانات موجود في `backend/database/migrations`.
- إذا بتستخدم رفع ملفات وصور، تأكد من تشغيل `php artisan storage:link` داخل `backend/`.
- إذا بتفعّل التحديثات اللحظية، عبّ بيانات `PUSHER_*` و `VUE_APP_PUSHER_*` داخل `backend/.env`.
- إذا بتفعّل التخزين المؤقت بشكل كامل، جهّز Redis واضبط `CACHE_STORE=redis`.

## حالة المشروع حاليًا

المشروع شغّال من ناحية البنية الأساسية والوظائف الرئيسية، وفيه واجهات وإداريات جاهزة للاستخدام والتطوير.

الموجود حاليًا بشكل واضح:

- نظام دخول وصلاحيات.
- لوحة تحكم إدارية.
- إدارة الأشخاص والطلاب والمقرئين.
- إدارة الاختبارات والمهام والاختبار النهائي.
- الحضور والنتائج وبعض صفحات المحتوى العام.
- API واختبارات backend تغطي السيناريوهات الرئيسية.

ولو الهدف تسليم نسخة إنتاجية نهائية، فيه شغل أخير يفضل يتقفل قبل الإطلاق التجاري الكامل، مثل:

- تحديثات الأمان لبعض الحزم.
- ضبط إعدادات الإنتاج النهائية.
- مراجعة الأداء وحجم ملفات الواجهة.

## ملفات مهمة

- `backend/README.md` فيه شرح خاص بتشغيل وإدارة الـ backend.

## ملاحظتي للمشتري أو للمطور اللي بيستلم

إذا هدفك تستلم مشروع مرتب وقابل للتطوير والتخصيص، فالبنية الحالية واضحة ومنظمة.

وإذا هدفك تطلقه إنتاجيًا مباشرة، فالأفضل تسوي جولة نهائية على الأمان والإعدادات التشغيلية قبل الإطلاق.
# Momars Migration Workspace

This repository now targets the buyer-required structure:

- `backend/`: Laravel 12 application, API, authentication, migrations, tests, and production database setup.
- `frontend/`: Vue 2 + Vuetify application.

## Primary commands

Run the new frontend from the repo root:

```bash
npm run dev
```

Run MySQL, the Laravel backend, and the frontend together from the repo root:

```bash
npm run dev:all
```

Build the new frontend from the repo root:

```bash
npm run build
```

Serve Laravel from the repo root:

```bash
npm run serve:backend
```

Serve Laravel on the working local port used by the frontend:

```bash
npm run serve:backend:8001
```

Run Laravel migrations:

```bash
npm run migrate:backend
```

Run backend tests:

```bash
npm test
```

## Database structure

The canonical database implementation is now the Laravel migration set inside `backend/database/migrations`.

- Local validation can still use SQLite.
- Delivery and production should use MySQL.
- The Laravel migration set is now the only active schema source in the workspace.

## MySQL setup

1. Copy `backend/.env.mysql.example` to `backend/.env`.
2. Fill in the MySQL host, database, username, and password.
3. Run `php artisan key:generate` inside `backend/`.
4. Run `php artisan migrate --force` inside `backend/`.

If `php` is not available in your system `PATH`, set `PHP_BIN` to the PHP executable path before using the root backend scripts.

## Buyer-required runtime stack

The Laravel backend now includes first-class integration for the buyer-required stack:

- `Spatie Activity Log` for dashboard activity history.
- `Redis` for cached dashboard activity and notifications.
- `Pusher` broadcasting for realtime dashboard notification and activity updates.

To activate the stack in deployment:

1. Fill the `PUSHER_*` and `VUE_APP_PUSHER_*` variables in `backend/.env`.
2. Ensure a Redis server is reachable from the Laravel backend.
3. Set `BROADCAST_CONNECTION=pusher` and `CACHE_STORE=redis` in the deployment environment.
4. Run `php artisan migrate --force` inside `backend/`.

## Local Windows MySQL helpers

Start the local MySQL instance prepared for this workspace:

```bash
npm run mysql:start
```

Install MySQL as an auto-start Windows service for this workspace:

```bash
npm run mysql:install-service
```

The MySQL service install script must be run from a VS Code window opened as Administrator.

## Current migration status

Already moved into the new structure:

- Laravel auth and API layer
- Laravel migrations for the core schema
- Vue admin dashboard
- Vue admin assessments
- Vue final exam management
- Vue people management
- Vue notifications and activity log management
- Vue results and attendance management

Still pending before final legacy removal:

- Public site pages from the old React app
- Student, trainee, reciter, and final user-facing pages in Vue
- Final production deployment wiring
