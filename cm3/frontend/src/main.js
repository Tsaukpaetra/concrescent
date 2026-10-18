import Vue from 'vue'
import App from './App.vue'
import vuetify from './plugins/vuetify';
import VuetifyGoogleAutocomplete from 'vuetify-google-autocomplete';
import {
    currency
} from './plugins/currency'
import {
    subname,
    badgeDisplayName
} from './plugins/subname'
import {
    split_carriagereturn
} from './plugins/split_carriagereturn'

import { debounce } from './plugins/debounce';

import vRouter from 'vue-router';
import routes from './router'
Vue.use(vRouter);
var router = new vRouter({
    // Auto-substitutes during compile
    base: process.env.VUE_APP_PUBLIC_PATH || '/',
    mode: process.env.VUE_APP_HASHMODE === 'true' ? 'hash' : 'history',
    //history: global.config.hashMode ? vRouter.createWebHashHistoy() : vRouter.createWebHistory(),
    routes: routes
})

import store from './storage'

//Phat editor, creates the component v-md-editor
import VueMarkdownEditor from '@kangc/v-md-editor';
import VMdPreview from '@kangc/v-md-editor/lib/preview';
import '@kangc/v-md-editor/lib/style/base-editor.css';
import kancenUS from '@kangc/v-md-editor/lib/lang/en-US';
VueMarkdownEditor.lang.use('en-US', kancenUS);
import vuepressTheme from '@kangc/v-md-editor/lib/theme/vuepress.js';
VueMarkdownEditor.use(vuepressTheme, {
    config: {
        'disabled-menus': ['save', 'toc']
    },
    extend(md) {
        // Override the core highlight function
        md.options.highlight = function (str, lang) {
        // Returning an empty string tells markdown-it to skip formatting 
        // and output the block as plain text using its internal safe HTML escaping.
        return ''; 
        };

    }
});
VMdPreview.use(vuepressTheme, {});
Vue.use(VueMarkdownEditor)
Vue.use(VMdPreview)

//JSON Editor
import JsonEditorVue from 'json-editor-vue'

Vue.use(JsonEditorVue, {
    // global props & attrs (one-way data flow)
})

//interact components
import VueInteractJs from "vue-interactjs";
Vue.use(VueInteractJs);

//template engine
import { compileTemplate } from './plugins/template_engine.js';
Vue.prototype.$compileTemplate = compileTemplate;

Vue.config.productionTip = false
Vue.filter('currency', currency)
Vue.filter('subname', subname)
Vue.filter('badgeDisplayName', badgeDisplayName)
Vue.filter('split_carriagereturn', split_carriagereturn)

//Don't use the  autocomplete if no api key was given
if (window.CM3_CONFIG && window.CM3_CONFIG.GoogleAutoCompleteAPIKey) {
    Vue.use(VuetifyGoogleAutocomplete, {
        apiKey: window.CM3_CONFIG.GoogleAutoCompleteAPIKey
    });
}

// Create a single channel instance for the entire application
const appChannel = new BroadcastChannel('vue_app_channel');
// Expose it globally to all components
Vue.prototype.$broadcast = appChannel;

const persistedStores = ['cart','mydata','station'];

new Vue({
    vuetify,
    router,
    store,
    beforeCreate() {
        //Pre-load the stores
        persistedStores.forEach((key) => {
            this.$store.commit(key + '/initialiseData')
        });
    },
    render: h => h(App)
}).$mount('#app')

const debouncedSave = debounce((key,state) => {
    try {
        localStorage.setItem(key, JSON.stringify(state[key]));
    } catch (e) {
        console.error('[Storage] Debounced save failed for ' + key, e);
    }
}, 500);

//Set a trigger whenever the cart changes
store.subscribe((mutation, state) => {
    //Check if the mutation is not our own init
    if(mutation.type.endsWith('/initialiseData')) return;

    //If it's a store we're persisting
    persistedStores.forEach((key) => {
        if(mutation.type.startsWith(key+'/')){
            debouncedSave(key,state);
        }
    });
});
window.addEventListener('storage', async (event) => {
    // Check if the specific key we use for our state changed
    if (persistedStores.find(key => event.key == key)){
        try {
            //Just trigger the load
            store.commit(event.key + '/initialiseData')
        } catch (e) {
            console.error('[Storage] Error parsing storage data:', e);
        }
    }
});