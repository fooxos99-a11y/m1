import Vue from 'vue';
import Vuetify from 'vuetify';
import ar from 'vuetify/es5/locale/ar';
import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/dist/vuetify.min.css';

Vue.use(Vuetify);

export default new Vuetify({
  icons: {
    iconfont: 'mdi',
  },
  rtl: true,
  lang: {
    current: 'ar',
    locales: {
      ar: {
        ...ar,
        input: {
          ...ar.input,
          appendAction: 'إجراء إضافي',
          prependAction: 'إجراء إضافي',
        },
        noDataText: 'لاتوجد بيانات',
      },
    },
  },
  theme: {
    themes: {
      light: {
        primary: '#1f6f96',
        secondary: '#164e63',
        accent: '#ea580c',
      },
    },
  },
});
