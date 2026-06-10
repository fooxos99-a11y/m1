import axios from 'axios';

const STORAGE_NAMESPACE = process.env.VUE_APP_STORAGE_NAMESPACE || 'momars-practitioner';
const buildStorageKey = (suffix) => `${STORAGE_NAMESPACE}.${suffix}`;

const AUTH_TOKEN_STORAGE_KEY = buildStorageKey('authToken');
const ACCESS_SESSION_STORAGE_KEY = buildStorageKey('studentAccess');
const AUTH_USER_STORAGE_KEY = buildStorageKey('authUser');
const LEGACY_AUTH_TOKEN_STORAGE_KEY = 'momars.authToken';
const LEGACY_ACCESS_SESSION_STORAGE_KEY = 'momars.studentAccess';
const API_REQUEST_TIMEOUT_MS = 12000;

const resolveApiBaseUrl = () => {
  if (process.env.VUE_APP_API_BASE_URL) {
    return process.env.VUE_APP_API_BASE_URL;
  }

  if (typeof window !== 'undefined') {
    const host = window.location.hostname || 'localhost';
    const isLocalHost = host === '127.0.0.1' || host === 'localhost';

    if (isLocalHost && ['8080', '8081', '3000', '5173'].includes(window.location.port)) {
      return `http://${host}:8001/api`;
    }

    return `${window.location.origin}/api`;
  }

  return 'http://localhost:8000/api';
};

const readStoredToken = () => {
  if (typeof window === 'undefined') {
    return '';
  }

  return window.localStorage.getItem(AUTH_TOKEN_STORAGE_KEY) || '';
};

const migrateLegacyStorageKeys = () => {
  if (typeof window === 'undefined') {
    return;
  }

  if (!window.localStorage.getItem(AUTH_TOKEN_STORAGE_KEY)) {
    const legacyToken = window.localStorage.getItem(LEGACY_AUTH_TOKEN_STORAGE_KEY);

    if (legacyToken) {
      window.localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, legacyToken);
    }
  }

  if (!window.localStorage.getItem(ACCESS_SESSION_STORAGE_KEY)) {
    const legacySession = window.localStorage.getItem(LEGACY_ACCESS_SESSION_STORAGE_KEY);

    if (legacySession) {
      window.localStorage.setItem(ACCESS_SESSION_STORAGE_KEY, legacySession);
    }
  }
};

const baseHeaders = {
  Accept: 'application/json',
  'Content-Type': 'application/json',
};

const apiBaseUrl = resolveApiBaseUrl();

const apiClient = axios.create({
  baseURL: apiBaseUrl,
  timeout: API_REQUEST_TIMEOUT_MS,
  headers: baseHeaders,
});

const publicApiClient = axios.create({
  baseURL: apiBaseUrl,
  timeout: API_REQUEST_TIMEOUT_MS,
  headers: baseHeaders,
});

migrateLegacyStorageKeys();

const initialToken = readStoredToken();

if (initialToken) {
  apiClient.defaults.headers.common.Authorization = `Bearer ${initialToken}`;
}

export default apiClient;

export const getStoredAuthToken = () => readStoredToken();

export const getStoredAuthUser = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(AUTH_USER_STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
};

export const loadAccessSession = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(ACCESS_SESSION_STORAGE_KEY);

    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
};

export const saveAccessSession = (session) => {
  if (typeof window === 'undefined') {
    return;
  }

  if (!session) {
    window.localStorage.removeItem(ACCESS_SESSION_STORAGE_KEY);
    return;
  }

  window.localStorage.setItem(ACCESS_SESSION_STORAGE_KEY, JSON.stringify(session));
};

export const clearAccessSession = () => {
  if (typeof window === 'undefined') {
    return;
  }

  window.localStorage.removeItem(ACCESS_SESSION_STORAGE_KEY);
  window.localStorage.removeItem(LEGACY_ACCESS_SESSION_STORAGE_KEY);
};

export const setAuthToken = (token) => {
  if (typeof window !== 'undefined') {
    if (token) {
      window.localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, token);
    } else {
      window.localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
      window.localStorage.removeItem(LEGACY_AUTH_TOKEN_STORAGE_KEY);
    }
  }

  if (token) {
    apiClient.defaults.headers.common.Authorization = `Bearer ${token}`;
  } else {
    delete apiClient.defaults.headers.common.Authorization;
  }
};

export const setStoredAuthUser = (user) => {
  if (typeof window === 'undefined') {
    return;
  }

  if (!user) {
    window.localStorage.removeItem(AUTH_USER_STORAGE_KEY);
    return;
  }

  window.localStorage.setItem(AUTH_USER_STORAGE_KEY, JSON.stringify(user));
};

export const login = async ({ loginCode, password }) => {
  const response = await apiClient.post('/auth/login', {
    login_code: loginCode,
    password,
  });

  return response.data;
};

export const fetchCurrentUser = async () => {
  const response = await apiClient.get('/auth/user');

  return response.data;
};

export const logout = async () => {
  await apiClient.post('/auth/logout');
};

export const fetchDashboardSnapshot = async () => {
  const response = await apiClient.get('/dashboard/snapshot');

  return response.data;
};

export const fetchPublicSnapshot = async () => {
  const response = await publicApiClient.get('/public/snapshot');

  return response.data;
};

export const fetchPublicStats = async () => {
  const response = await publicApiClient.get('/public/stats');

  return response.data;
};

export const updateHomePageContent = async (content) => {
  const response = await apiClient.put('/dashboard/home-page-content', { content });

  return response.data;
};

export const updatePractitionerPageContent = async (content) => {
  const response = await apiClient.put('/dashboard/practitioner-page-content', { content });

  return response.data;
};

export const createTaskTemplate = async ({ name, content }) => {
  const response = await apiClient.post('/dashboard/task-templates', {
    name,
    content,
  });

  return response.data;
};

export const updateTaskTemplate = async (templateId, { name, content }) => {
  await apiClient.put(`/dashboard/task-templates/${templateId}`, {
    ...(name !== undefined ? { name } : {}),
    ...(content !== undefined ? { content } : {}),
  });
};

export const uploadEditorImage = async (file) => {
  const formData = new FormData();

  formData.append('image', file);

  const response = await apiClient.post('/dashboard/editor-images', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });

  return response.data;
};

export const saveManualAttendance = async ({ courseId, presentStudents }) => {
  await apiClient.post('/dashboard/manual-attendance', {
    courseId,
    presentStudents,
  });
};

export const submitAssessment = async ({ courseId, assessmentType, studentName, loginId, answers }) => {
  const response = await apiClient.post('/dashboard/assessment-submissions', {
    courseId,
    assessmentType,
    studentName,
    loginId,
    answers,
  });

  return response.data;
};

export const submitPublicAssessment = async ({ courseId, assessmentType, studentName, loginId, answers }) => {
  const response = await apiClient.post('/public/assessment-submissions', {
    courseId,
    assessmentType,
    studentName,
    loginId,
    answers,
  });

  return response.data;
};

export const bulkImportAssessments = async ({ courseId, assessmentType, submissions }) => {
  const response = await apiClient.post('/dashboard/assessment-import', {
    courseId,
    assessmentType,
    submissions,
  });

  return response.data;
};

const normalizeCoursePayload = (payload = {}) => {
  if (!payload || typeof payload !== 'object') {
    return payload;
  }

  if (!Object.prototype.hasOwnProperty.call(payload, 'taskTemplateContent')) {
    return payload;
  }

  const value = payload.taskTemplateContent;

  return {
    ...payload,
    taskTemplateContent:
      value == null
        ? value
        : typeof value === 'string'
          ? value
          : JSON.stringify(value),
  };
};

export const createCourse = async (payload) => {
  const response = await apiClient.post('/dashboard/courses', normalizeCoursePayload(payload));

  return response.data;
};

export const updateCourse = async (courseId, payload) => {
  await apiClient.put(`/dashboard/courses/${courseId}`, normalizeCoursePayload(payload));
};

export const deleteCourse = async (courseId) => {
  await apiClient.delete(`/dashboard/courses/${courseId}`);
};

export const updateCourseSortOrder = async (orderedIds) => {
  await apiClient.put('/dashboard/courses/sort-order', { orderedIds });
};

export const activateCourse = async (courseId, settings) => {
  await apiClient.post(`/dashboard/courses/${courseId}/activate`, settings || {});
};

export const deactivateAllCourses = async () => {
  await apiClient.post('/dashboard/courses/deactivate-all');
};

export const createCourseQuestion = async (courseId, payload) => {
  const response = await apiClient.post(`/dashboard/courses/${courseId}/questions`, payload);

  return response.data;
};

export const updateCourseQuestion = async (questionId, payload) => {
  await apiClient.put(`/dashboard/questions/${questionId}`, payload);
};

export const deleteCourseQuestion = async (questionId) => {
  await apiClient.delete(`/dashboard/questions/${questionId}`);
};

export const fetchDashboardAccounts = async () => {
  const response = await apiClient.get('/dashboard/accounts');

  return response.data;
};

export const createDashboardAccount = async (payload) => {
  const response = await apiClient.post('/dashboard/accounts', payload);

  return response.data;
};

export const deleteDashboardAccount = async (accountId) => {
  await apiClient.delete(`/dashboard/accounts/${accountId}`);
};

export const createSatisfactionQuestion = async (payload) => {
  const response = await apiClient.post('/dashboard/satisfaction-questions', payload);

  return response.data;
};

export const deleteSatisfactionQuestion = async (questionId) => {
  await apiClient.delete(`/dashboard/satisfaction-questions/${questionId}`);
};

export const submitPublicSatisfactionResponses = async (responses) => {
  const response = await apiClient.post('/public/satisfaction-responses', {
    responses,
  });

  return response.data;
};

export const submitSatisfactionResponses = async (responses) => {
  const response = await apiClient.post('/dashboard/satisfaction-responses', { responses });

  return response.data;
};

export const createFinalExamQuestion = async (payload) => {
  const response = await apiClient.post('/dashboard/final-exam/questions', payload);

  return response.data;
};

export const updateFinalExamQuestion = async (questionId, payload) => {
  await apiClient.put(`/dashboard/final-exam/questions/${questionId}`, payload);
};

export const deleteFinalExamQuestion = async (questionId) => {
  await apiClient.delete(`/dashboard/final-exam/questions/${questionId}`);
};

export const updateFinalExamSetting = async (branchCode, payload) => {
  await apiClient.put(`/dashboard/final-exam/settings/${branchCode}`, payload);
};

export const updateFinalExamNotificationTemplate = async (branchCode, notificationTemplate) => {
  await apiClient.put(`/dashboard/final-exam/settings/${branchCode}/notification-template`, {
    notificationTemplate,
  });
};

export const submitFinalExam = async (payload) => {
  const response = await apiClient.post('/dashboard/final-exam/submissions', payload);

  return response.data;
};

export const submitPublicFinalExam = async (payload) => {
  const response = await apiClient.post('/public/final-exam/submissions', payload);

  return response.data;
};

export const copyFinalExamQuestions = async (payload) => {
  await apiClient.post('/dashboard/final-exam/questions/copy', payload);
};

export const setFinalExamManualScore = async (submissionId, score) => {
  await apiClient.put(`/dashboard/final-exam/submissions/${submissionId}/manual-score`, { score });
};

export const setAssessmentManualScore = async (submissionId, score) => {
  await apiClient.put(`/dashboard/assessment-submissions/${submissionId}/manual-score`, { score });
};

export const createStudent = async (payload) => {
  const response = await apiClient.post('/students', payload);

  return response.data;
};

export const updateStudent = async (studentId, payload) => {
  const response = await apiClient.put(`/students/${studentId}`, payload);

  return response.data;
};

export const deleteStudent = async (studentId) => {
  await apiClient.delete(`/students/${studentId}`);
};

export const saveReciter = async (payload) => {
  const response = await apiClient.post('/reciters', payload);

  return response.data;
};

export const fetchReciterByLoginCode = async (loginCode) => {
  const response = await apiClient.get(`/reciters/by-login/${encodeURIComponent(loginCode)}`);

  return response.data;
};

export const fetchStudentAssignedReciter = async (loginCode) => {
  const response = await apiClient.get(`/students/by-login/${encodeURIComponent(loginCode)}/assigned-reciter`);

  return response.data;
};

export const toggleStudentPart = async ({ studentId, partNumber, reciterId, shouldMarkComplete }) => {
  await apiClient.put(`/students/${encodeURIComponent(studentId)}/parts/${partNumber}`, {
    reciterId,
    shouldMarkComplete,
  });
};

export const deleteReciter = async (loginCode) => {
  await apiClient.delete(`/reciters/by-login/${encodeURIComponent(loginCode)}`);
};

export const fetchActivityLogs = async () => {
  const response = await apiClient.get('/dashboard/activity-logs');

  return response.data;
};

export const createActivityLog = async (payload) => {
  const response = await apiClient.post('/dashboard/activity-logs', payload);

  return response.data;
};

export const fetchNotifications = async () => {
  const response = await apiClient.get('/dashboard/notifications');

  return response.data;
};

export const createNotification = async (payload) => {
  const response = await apiClient.post('/dashboard/notifications', payload);

  return response.data;
};

export const deleteNotification = async (notificationId) => {
  await apiClient.delete(`/dashboard/notifications/${notificationId}`);
};

export const createTrainingMaterial = async ({ title, description, branchId, attachments }) => {
  const formData = new FormData();

  formData.append('title', title);

  if (description) {
    formData.append('description', description);
  }

  if (branchId) {
    formData.append('branchId', branchId);
  }

  (attachments || []).forEach((attachment, index) => {
    if (attachment?.label) {
      formData.append(`attachments[${index}][label]`, attachment.label);
    }

    if (attachment?.file) {
      formData.append(`attachments[${index}][file]`, attachment.file);
    }

    if (attachment?.url) {
      formData.append(`attachments[${index}][url]`, attachment.url);
    }
  });

  const response = await apiClient.post('/dashboard/training-materials', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });

  return response.data;
};

export const updateTrainingMaterial = async (materialId, { title, description, branchId, attachments }) => {
  const formData = new FormData();

  formData.append('_method', 'PUT');
  formData.append('title', title);

  if (description) {
    formData.append('description', description);
  }

  if (branchId) {
    formData.append('branchId', branchId);
  }

  (attachments || []).forEach((attachment, index) => {
    if (attachment?.id) {
      formData.append(`attachments[${index}][id]`, attachment.id);
    }

    if (attachment?.label) {
      formData.append(`attachments[${index}][label]`, attachment.label);
    }

    if (attachment?.file) {
      formData.append(`attachments[${index}][file]`, attachment.file);
    }

    if (attachment?.url) {
      formData.append(`attachments[${index}][url]`, attachment.url);
    }
  });

  const response = await apiClient.post(`/dashboard/training-materials/${encodeURIComponent(materialId)}`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });

  return response.data;
};

export const deleteTrainingMaterial = async (materialId) => {
  await apiClient.delete(`/dashboard/training-materials/${encodeURIComponent(materialId)}`);
};

export const setRolePermission = async ({ role, key, isEnabled }) => {
  await apiClient.put('/dashboard/role-permissions', {
    role,
    key,
    isEnabled,
  });
};

export const fetchRegistrationDashboardData = async () => {
  const response = await apiClient.get('/dashboard/registration');

  return response.data;
};

export const updateRegistrationSettings = async (isOpen) => {
  await apiClient.put('/dashboard/registration/settings', { isOpen });
};

export const updateRegistrationFields = async (fields) => {
  const response = await apiClient.put('/dashboard/registration/fields', { fields });

  return response.data;
};

export const acceptRegistrationRequest = async (requestId, branchId) => {
  const payload = branchId ? { branchId } : {};
  const response = await apiClient.post(`/dashboard/registration-requests/${encodeURIComponent(requestId)}/accept`, payload);

  return response.data;
};

export const rejectRegistrationRequest = async (requestId, reason = '') => {
  const response = await apiClient.post(`/dashboard/registration-requests/${encodeURIComponent(requestId)}/reject`, { reason });

  return response.data;
};

export const markRegistrationRequestAccepted = async (requestId) => {
  const response = await apiClient.post(`/dashboard/registration-requests/${encodeURIComponent(requestId)}/mark-accepted`);

  return response.data;
};

export const fetchPublicRegistrationStatus = async () => {
  const response = await publicApiClient.get('/public/registration');

  return response.data;
};

export const submitPublicRegistrationRequest = async (payload) => {
  const response = await publicApiClient.post('/public/registration-requests', payload);

  return response.data;
};
