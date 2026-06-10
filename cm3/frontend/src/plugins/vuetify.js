import '@mdi/font/css/materialdesignicons.css'; // Ensure you are using css-loader
import Vue from 'vue';
import Vuetify, {

    VApp,
    VToolbar,
    VCard,
    VBtn,
    VIcon,
    VProgressCircular,
    VList,
    VAvatar,
    VListGroup,
    VTextField,
    VTreeview,
} from 'vuetify/lib';

const config = window.CM3_CONFIG || {
    //Dummy, fallback in case the config is missing
    themeLight: {
        appbar: '#1976D2',
        appbaradmin: '#283593',
        backgroundcolor: '#4a5664'
    },
    themeDark: {}
};

Vue.use(Vuetify, {
    components: {
        VApp,
        VToolbar,
        VCard,
        VBtn,
        VIcon,
        VProgressCircular,
        VList,
        VAvatar,
        VListGroup,
        VTextField,
        VTreeview,
    },
});

export default new Vuetify({
    icons: {
        iconfont: 'mdi',
    },
    theme: {
        themes: {
            light: config.themeLight,
            dark: config.themeDark,
        },
    },
});