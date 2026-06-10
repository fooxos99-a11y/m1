import Vue from 'vue';
import { ValidationProvider, ValidationObserver, extend } from 'vee-validate';

extend('required', {
  validate: (value) => {
    if (Array.isArray(value)) {
      return value.length > 0;
    }

    return value !== null && value !== undefined && String(value).trim().length > 0;
  },
  message: 'هذا الحقل مطلوب',
});

Vue.component('ValidationProvider', ValidationProvider);
Vue.component('ValidationObserver', ValidationObserver);