import Vue from 'vue';
import Router from 'vue-router';
import store from '../store';
import { resolveUserHomeRoute } from '../utils/authRoutes';

Vue.use(Router);

const HomeView = () => import(/* webpackChunkName: "public-home" */ '../views/HomeView.vue');
const PractitionerView = () => import(/* webpackChunkName: "public-practitioner" */ '../views/PractitionerView.vue');
const LoginView = () => import(/* webpackChunkName: "auth" */ '../views/LoginView.vue');
const DashboardView = () => import(/* webpackChunkName: "dashboard" */ '../views/DashboardView.vue');
const AdminPeopleView = () => import(/* webpackChunkName: "dashboard-admin-people" */ '../views/AdminPeopleView.vue');
const AdminCommunicationsView = () => import(/* webpackChunkName: "dashboard-admin-communications" */ '../views/AdminCommunicationsView.vue');
const AdminResultsView = () => import(/* webpackChunkName: "dashboard-admin-results" */ '../views/AdminResultsView.vue');
const CourseView = () => import(/* webpackChunkName: "course" */ '../views/CourseView.vue');
const SatisfactionView = () => import(/* webpackChunkName: "satisfaction" */ '../views/SatisfactionView.vue');
const FinalExamView = () => import(/* webpackChunkName: "final-exam" */ '../views/FinalExamView.vue');
const TasksView = () => import(/* webpackChunkName: "tasks" */ '../views/TasksView.vue');
const StudentView = () => import(/* webpackChunkName: "student" */ '../views/StudentView.vue');
const TraineeView = () => import(/* webpackChunkName: "trainee" */ '../views/TraineeView.vue');
const ReciterView = () => import(/* webpackChunkName: "reciter" */ '../views/ReciterView.vue');
const RegistrationView = () => import(/* webpackChunkName: "registration" */ '../views/RegistrationView.vue');
const NotFoundView = () => import(/* webpackChunkName: "not-found" */ '../views/NotFoundView.vue');

const MANAGER_ROLES = ['male_manager', 'female_manager'];

const getRolePermissions = () => {
  const role = store.state.currentUser?.role;

  if (!MANAGER_ROLES.includes(role)) {
    return {};
  }

  return store.state.dashboardSnapshot?.rolePermissions?.[role] || {};
};

const hasOneOfPermissions = (keys = []) => {
  if (!Array.isArray(keys) || keys.length === 0) {
    return false;
  }

  const permissions = getRolePermissions();

  return keys.some((key) => permissions[key] === true);
};

const canAccessDashboard = () => {
  const role = store.state.currentUser?.role;

  return role === 'admin' || MANAGER_ROLES.includes(role);
};

const canAccessAdminRoute = (to) => {
  const role = store.state.currentUser?.role;

  if (role === 'admin') {
    return true;
  }

  if (!MANAGER_ROLES.includes(role)) {
    return false;
  }

  switch (to.name) {
    case 'dashboard':
      return canAccessDashboard();
    case 'admin-assessment':
      return to.params.assessmentType === 'pre'
        ? hasOneOfPermissions(['edit_pre_questions', 'open_pre_exam'])
        : hasOneOfPermissions(['edit_post_questions', 'open_post_exam']);
    case 'admin-people':
      return hasOneOfPermissions([
        'add_student',
        'delete_student',
        'edit_student',
        'add_reciter',
        'delete_reciter',
        'edit_reciter',
        'transfer_reciter_student',
      ]);
    case 'admin-communications':
      return hasOneOfPermissions(['page_notifications', 'page_activity_log']);
    case 'admin-results':
      return hasOneOfPermissions(['page_results']);
    case 'admin-final-exam':
      return false;
    default:
      return canAccessDashboard();
  }
};

const resolveRedirectPath = (route) => {
  const fullPath = route?.fullPath || '';

  if (!fullPath || fullPath === '/' || fullPath.startsWith('/login')) {
    return '';
  }

  return fullPath;
};

const trimQueryValue = (value) => (typeof value === 'string' ? value.trim() : '');

const buildDashboardQuery = (panel, extras = {}) => {
  const query = {};

  if (panel && panel !== 'overview') {
    query.panel = panel;
  }

  Object.entries(extras).forEach(([key, value]) => {
    const normalized = trimQueryValue(value);

    if (normalized) {
      query[key] = normalized;
    }
  });

  return query;
};

const router = new Router({
  mode: 'history',
  base: process.env.VUE_APP_ROUTER_BASE || '/',
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    }

    if (to.hash) {
      return {
        selector: to.hash,
        behavior: 'smooth',
      };
    }

    return {
      x: 0,
      y: 0,
    };
  },
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/practitioner',
      name: 'practitioner',
      component: PractitionerView,
    },
    {
      path: '/registration',
      name: 'registration',
      component: RegistrationView,
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guestOnly: true },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAuth: true, requiresDashboardAccess: true },
    },
    {
      path: '/courses/:assessmentType?',
      name: 'courses',
      component: CourseView,
      meta: { requiresAuth: true },
      props: (route) => ({
        assessmentType: route.params.assessmentType || route.query.assessmentType || 'post',
      }),
    },
    {
      path: '/course',
      component: CourseView,
      meta: { requiresAuth: true },
      props: {
        assessmentType: 'pre',
      },
    },
    {
      path: '/course/pre',
      component: CourseView,
      meta: { requiresAuth: true },
      props: {
        assessmentType: 'pre',
      },
    },
    {
      path: '/course/post',
      component: CourseView,
      meta: { requiresAuth: true },
      props: {
        assessmentType: 'post',
      },
    },
    {
      path: '/course/tasks',
      component: TasksView,
      meta: { requiresAuth: true },
    },
    {
      path: '/satisfaction',
      name: 'satisfaction',
      component: SatisfactionView,
      meta: { requiresAuth: true },
    },
    {
      path: '/tasks',
      name: 'tasks',
      component: TasksView,
      meta: { requiresAuth: true },
    },
    {
      path: '/student',
      name: 'student',
      component: StudentView,
      meta: { requiresAuth: true },
    },
    {
      path: '/trainee',
      name: 'trainee',
      component: TraineeView,
      meta: { requiresAuth: true },
    },
    {
      path: '/reciter',
      name: 'reciter',
      component: ReciterView,
      meta: { requiresAuth: true },
    },
    {
      path: '/final-exam',
      name: 'final-exam',
      component: FinalExamView,
      meta: { requiresAuth: true },
    },
    {
      path: '/admin/assessments/:assessmentType',
      name: 'admin-assessment',
      redirect: (to) => {
        const assessmentType = trimQueryValue(to.params.assessmentType);
        const isTaskPanel = assessmentType === 'tasks';

        return {
          name: 'dashboard',
          query: buildDashboardQuery(isTaskPanel ? 'tasks' : 'courses', {
            assessmentType: isTaskPanel ? '' : assessmentType,
            courseId: to.query.courseId,
          }),
        };
      },
    },
    {
      path: '/admin/final-exam',
      name: 'admin-final-exam',
      redirect: () => ({
        name: 'dashboard',
        query: buildDashboardQuery('finalexam'),
      }),
    },
    {
      path: '/admin/people',
      name: 'admin-people',
      component: AdminPeopleView,
      meta: { requiresAuth: true, requiresDashboardAccess: true },
    },
    {
      path: '/admin/communications',
      name: 'admin-communications',
      component: AdminCommunicationsView,
      meta: { requiresAuth: true, requiresDashboardAccess: true },
    },
    {
      path: '/admin/results',
      name: 'admin-results',
      component: AdminResultsView,
      meta: { requiresAuth: true, requiresDashboardAccess: true },
    },
    {
      path: '*',
      name: 'not-found',
      component: NotFoundView,
    },
  ],
});

router.beforeEach(async (to, from, next) => {
  if (!store.state.authChecked) {
    await store.dispatch('bootstrapAuth');
  }

  const redirectPath = resolveRedirectPath(to);

  if (to.name === 'login' && !store.getters.isAuthenticated) {
    next({
      name: 'home',
      query: {
        ...to.query,
        login: '1',
        ...(redirectPath ? { redirect: redirectPath } : {}),
      },
    });
    return;
  }

  if (to.matched.some((record) => record.meta.requiresAuth) && !store.getters.isAuthenticated) {
    next({
      name: 'home',
      query: {
        login: '1',
        ...(redirectPath ? { redirect: redirectPath } : {}),
      },
    });
    return;
  }

  if (to.matched.some((record) => record.meta.requiresDashboardAccess) && !canAccessAdminRoute(to)) {
    next(resolveUserHomeRoute(store.state.currentUser));
    return;
  }

  if (to.matched.some((record) => record.meta.guestOnly) && store.getters.isAuthenticated) {
    if (typeof to.query.redirect === 'string' && to.query.redirect) {
      next(to.query.redirect);
      return;
    }

    next(resolveUserHomeRoute(store.state.currentUser));
    return;
  }

  next();
});

export default router;
