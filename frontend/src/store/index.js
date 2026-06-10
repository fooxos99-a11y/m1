import Vue from 'vue';
import Vuex from 'vuex';
import { disconnectDashboardRealtime, subscribeDashboardRealtime } from '../services/realtime';
import {
  activateCourse,
  bulkImportAssessments,
  copyFinalExamQuestions,
  createTaskTemplate as createTaskTemplateRequest,
  createActivityLog,
  createDashboardAccount,
  createNotification,
  createStudent,
  createCourse,
  createCourseQuestion,
  createFinalExamQuestion,
  createSatisfactionQuestion,
  deactivateAllCourses,
  deleteDashboardAccount,
  deleteReciter,
  deleteNotification,
  deleteStudent,
  deleteCourse,
  deleteCourseQuestion,
  deleteFinalExamQuestion,
  deleteSatisfactionQuestion,
  fetchActivityLogs,
  fetchDashboardAccounts,
  fetchCurrentUser,
  fetchDashboardSnapshot,
  fetchNotifications,
  getStoredAuthToken,
  getStoredAuthUser,
  login,
  logout,
  saveManualAttendance,
  saveReciter,
  setRolePermission as setRolePermissionRequest,
  setAuthToken,
  setStoredAuthUser,
  setFinalExamManualScore,
  submitAssessment,
  submitFinalExam,
  submitSatisfactionResponses,
  updateStudent,
  updateCourse,
  updateCourseQuestion,
  updateFinalExamQuestion,
  updateCourseSortOrder,
  updateFinalExamNotificationTemplate,
  updateFinalExamSetting,
  updateTaskTemplate as updateTaskTemplateRequest,
} from '../services/api';

Vue.use(Vuex);

let realtimeUnsubscribe = null;

export default new Vuex.Store({
  state: {
    appName: 'Momars',
    apiBaseUrl: process.env.VUE_APP_API_BASE_URL || 'http://localhost:8000/api',
    authToken: getStoredAuthToken(),
    currentUser: getStoredAuthUser(),
    authLoading: false,
    authChecked: false,
    authError: '',
    dashboardSnapshot: null,
    dashboardLoading: false,
    dashboardError: '',
  },
  getters: {
    isAuthenticated(state) {
      return Boolean(state.authToken && state.currentUser);
    },
  },
  mutations: {
    setApiBaseUrl(state, value) {
      state.apiBaseUrl = value;
    },
    setAuthLoading(state, value) {
      state.authLoading = value;
    },
    setAuthChecked(state, value) {
      state.authChecked = value;
    },
    setAuthError(state, value) {
      state.authError = value;
    },
    setAuthState(state, { token, user }) {
      state.authToken = token;
      state.currentUser = user;
    },
    clearAuthState(state) {
      state.authToken = '';
      state.currentUser = null;
      state.dashboardSnapshot = null;
    },
    setDashboardLoading(state, value) {
      state.dashboardLoading = value;
    },
    setDashboardSnapshot(state, value) {
      state.dashboardSnapshot = value;
    },
    setDashboardError(state, value) {
      state.dashboardError = value;
    },
  },
  actions: {
    async bootstrapAuth({ state, commit, dispatch }) {
      if (state.authChecked) {
        return;
      }

      if (!state.authToken) {
        commit('setAuthChecked', true);
        return;
      }

      commit('setAuthLoading', true);

      try {
        const user = await fetchCurrentUser();
        setStoredAuthUser(user);
        commit('setAuthState', { token: state.authToken, user });
      } catch (error) {
        if (error?.response?.status === 401) {
          setAuthToken('');
          setStoredAuthUser(null);
          commit('clearAuthState');
          commit('setAuthError', error?.response?.data?.message || 'انتهت جلسة الدخول.');
        } else {
          commit('setAuthError', error?.response?.data?.message || 'تعذر التحقق من الجلسة الآن.');
        }
      } finally {
        commit('setAuthLoading', false);
        commit('setAuthChecked', true);
      }

      if (state.currentUser) {
        await dispatch('loadDashboardSnapshot');
        await dispatch('initializeRealtime');
      }
    },
    async login({ commit, dispatch }, credentials) {
      commit('setAuthLoading', true);
      commit('setAuthError', '');

      try {
        const { token, user } = await login(credentials);
        setAuthToken(token);
        setStoredAuthUser(user);
        commit('setAuthState', { token, user });
        commit('setAuthChecked', true);
        await dispatch('loadDashboardSnapshot');
        await dispatch('initializeRealtime');
      } catch (error) {
        const message = error?.response?.data?.errors?.login_code?.[0]
          || error?.response?.data?.message
          || 'تعذر تسجيل الدخول.';
        commit('setAuthError', message);
        throw error;
      } finally {
        commit('setAuthLoading', false);
      }
    },
    async logout({ commit }) {
      try {
        await logout();
      } catch {
        // Ignore logout transport errors and clear local session regardless.
      }

      setAuthToken('');
      setStoredAuthUser(null);
      if (realtimeUnsubscribe) {
        realtimeUnsubscribe();
        realtimeUnsubscribe = null;
      }
      disconnectDashboardRealtime();
      commit('clearAuthState');
      commit('setAuthChecked', true);
      commit('setAuthError', '');
      commit('setDashboardError', '');
    },
    async loadDashboardSnapshot({ commit }) {
      if (!getStoredAuthToken()) {
        commit('setDashboardSnapshot', null);
        commit('setDashboardError', '');
        return;
      }

      commit('setDashboardLoading', true);
      commit('setDashboardError', '');

      try {
        const snapshot = await fetchDashboardSnapshot();
        commit('setDashboardSnapshot', snapshot);
      } catch (error) {
        if (error?.response?.status === 401) {
          setAuthToken('');
          setStoredAuthUser(null);
          commit('clearAuthState');
          commit('setAuthChecked', true);
          commit('setDashboardError', 'انتهت صلاحية الجلسة. سجّل الدخول مرة أخرى.');
          return;
        }

        const message = error?.response?.data?.message || error?.message || 'تعذر تحميل بيانات لوحة التحكم.';
        commit('setDashboardError', message);
      } finally {
        commit('setDashboardLoading', false);
      }
    },
    async initializeRealtime({ state, dispatch }) {
      if (!state.authToken || realtimeUnsubscribe) {
        return;
      }

      realtimeUnsubscribe = subscribeDashboardRealtime({
        onNotificationCreated: () => {
          dispatch('reloadNotifications');
        },
        onNotificationDeleted: () => {
          dispatch('reloadNotifications');
        },
        onActivityLogged: () => {
          dispatch('reloadActivityLogs');
        },
      });
    },
    async setManualAttendance({ dispatch }, payload) {
      await saveManualAttendance(payload);
      await dispatch('loadDashboardSnapshot');
    },
    async submitAssessment({ dispatch }, payload) {
      const result = await submitAssessment(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async addCourse({ dispatch }, payload) {
      const result = await createCourse(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async addTaskTemplate({ dispatch }, payload) {
      const result = await createTaskTemplateRequest(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async updateTaskTemplate({ dispatch }, { templateId, updates }) {
      await updateTaskTemplateRequest(templateId, updates);
      await dispatch('loadDashboardSnapshot');
    },
    async fetchDashboardAccounts() {
      return fetchDashboardAccounts();
    },
    async createDashboardAccount({ dispatch }, payload) {
      const result = await createDashboardAccount(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async deleteDashboardAccount({ dispatch }, accountId) {
      await deleteDashboardAccount(accountId);
      await dispatch('loadDashboardSnapshot');
    },
    async updateCourse({ dispatch }, { courseId, updates }) {
      await updateCourse(courseId, updates);
      await dispatch('loadDashboardSnapshot');
    },
    async deleteCourse({ dispatch }, courseId) {
      await deleteCourse(courseId);
      await dispatch('loadDashboardSnapshot');
    },
    async reorderCourses({ dispatch }, orderedIds) {
      await updateCourseSortOrder(orderedIds);
      await dispatch('loadDashboardSnapshot');
    },
    async activateCourse({ dispatch }, { courseId, settings }) {
      await activateCourse(courseId, settings);
      await dispatch('loadDashboardSnapshot');
    },
    async deactivateAllCourses({ dispatch }) {
      await deactivateAllCourses();
      await dispatch('loadDashboardSnapshot');
    },
    async addQuestion({ dispatch }, { courseId, question }) {
      const result = await createCourseQuestion(courseId, question);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async updateQuestion({ dispatch }, { questionId, question }) {
      await updateCourseQuestion(questionId, question);
      await dispatch('loadDashboardSnapshot');
    },
    async deleteQuestion({ dispatch }, questionId) {
      await deleteCourseQuestion(questionId);
      await dispatch('loadDashboardSnapshot');
    },
    async bulkImportAssessments({ dispatch }, payload) {
      const result = await bulkImportAssessments(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async addSatisfactionQuestion({ dispatch }, payload) {
      const result = await createSatisfactionQuestion(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async deleteSatisfactionQuestion({ dispatch }, questionId) {
      await deleteSatisfactionQuestion(questionId);
      await dispatch('loadDashboardSnapshot');
    },
    async deleteSatisfactionQuestions({ dispatch }, questionIds) {
      await Promise.all((questionIds || []).map((questionId) => deleteSatisfactionQuestion(questionId)));
      await dispatch('loadDashboardSnapshot');
    },
    async submitSatisfactionResponses({ dispatch }, responses) {
      const result = await submitSatisfactionResponses(responses);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async addFinalExamQuestion({ dispatch }, payload) {
      const result = await createFinalExamQuestion(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async updateFinalExamQuestion({ dispatch }, { questionId, question }) {
      await updateFinalExamQuestion(questionId, question);
      await dispatch('loadDashboardSnapshot');
    },
    async deleteFinalExamQuestion({ dispatch }, questionId) {
      await deleteFinalExamQuestion(questionId);
      await dispatch('loadDashboardSnapshot');
    },
    async toggleFinalExamEnabled({ dispatch }, { branchCode, closesAt, notificationTemplate }) {
      await updateFinalExamSetting(branchCode, {
        isEnabled: closesAt !== null,
        closesAt: closesAt !== null ? closesAt : null,
        ...(notificationTemplate !== undefined ? { notificationTemplate } : {}),
      });
      await dispatch('loadDashboardSnapshot');
    },
    async updateFinalExamNotificationTemplate({ dispatch }, { branchCode, notificationTemplate }) {
      await updateFinalExamNotificationTemplate(branchCode, notificationTemplate);
      await dispatch('loadDashboardSnapshot');
    },
    async submitFinalExam({ dispatch }, payload) {
      const result = await submitFinalExam(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async copyFinalExamQuestions({ dispatch }, payload) {
      await copyFinalExamQuestions(payload);
      await dispatch('loadDashboardSnapshot');
    },
    async setFinalExamManualScore({ dispatch }, { submissionId, score }) {
      await setFinalExamManualScore(submissionId, score);
      await dispatch('loadDashboardSnapshot');
    },
    async addStudent({ dispatch }, payload) {
      const result = await createStudent(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async updateStudent({ dispatch }, { studentId, updates }) {
      const result = await updateStudent(studentId, updates);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async deleteStudent({ dispatch }, studentId) {
      await deleteStudent(studentId);
      await dispatch('loadDashboardSnapshot').catch(() => {});
    },
    async saveReciter({ dispatch }, payload) {
      const result = await saveReciter(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async deleteReciter({ dispatch }, loginCode) {
      await deleteReciter(loginCode);
      await dispatch('loadDashboardSnapshot').catch(() => {});
    },
    async addNotification({ dispatch }, payload) {
      const result = await createNotification(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async deleteNotification({ dispatch }, notificationId) {
      await deleteNotification(notificationId);
      await dispatch('loadDashboardSnapshot');
    },
    async addActivityLog({ dispatch }, payload) {
      const result = await createActivityLog(payload);
      await dispatch('loadDashboardSnapshot');

      return result;
    },
    async reloadActivityLogs({ state, commit }) {
      try {
        const logs = await fetchActivityLogs();
        commit('setDashboardSnapshot', {
          ...(state.dashboardSnapshot || {}),
          activityLogs: logs,
        });
      } catch {
        // Keep existing snapshot data on remote load failure.
      }
    },
    async reloadNotifications({ state, commit }) {
      try {
        const notifications = await fetchNotifications();
        commit('setDashboardSnapshot', {
          ...(state.dashboardSnapshot || {}),
          notifications,
        });
      } catch {
        // Keep existing snapshot data on remote load failure.
      }
    },
    async setRolePermission({ dispatch }, payload) {
      await setRolePermissionRequest(payload);
      await dispatch('loadDashboardSnapshot');
    },
  },
  modules: {},
});
