import Vue from 'vue';
import '@fortawesome/fontawesome-free/css/all.min.css';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import App from './App.vue';
import router from './router';
import store from './store';
import vuetify from './plugins/vuetify';
import './plugins/validation';
import './styles/main.css';

Vue.config.productionTip = false;
Vue.use(Toast, {
  position: 'top-center',
  timeout: 3000,
  closeButton: false,
  hideProgressBar: false,
  newestOnTop: true,
  maxToasts: 4,
  pauseOnHover: false,
  pauseOnFocusLoss: false,
  draggable: false,
  rtl: true,
  toastClassName: 'app-toast',
  bodyClassName: 'app-toast__body',
  containerClassName: 'app-toast-container',
  container: () => document.getElementById('app') || document.body,
});

const toastApi = Vue.prototype.$toast;

if (toastApi) {
  ['success', 'error', 'info', 'warning'].forEach((methodName) => {
    if (typeof toastApi[methodName] !== 'function') {
      return;
    }

    const originalMethod = toastApi[methodName].bind(toastApi);

    toastApi[methodName] = (message, options = {}) => originalMethod(message, {
      timeout: 3000,
      hideProgressBar: false,
      pauseOnHover: false,
      pauseOnFocusLoss: false,
      draggable: false,
      ...options,
    });
  });
}

Vue.prototype.$dropdownMenuProps = Object.freeze({
  offsetY: true,
  maxHeight: 420,
  contentClass: 'app-dropdown-menu',
  closeOnContentClick: true,
});

const publicAssetBaseUrl = process.env.BASE_URL || '/';
Vue.prototype.$publicAsset = (path) => `${publicAssetBaseUrl}${String(path || '').replace(/^\/+/, '')}`;

new Vue({
  router,
  store,
  vuetify,
  render: (h) => h(App),
}).$mount('#app');
