import axios from 'axios';
import store from '../storage';

// Create a PRIVATE instance that is isolated from the global axios
const customInstance = axios.create({
    // You can even set a default base URL here if you want
});
customInstance.interceptors.request.use(
    (config) => {
        if (store && store.getters) {
            let shouldHaveAuth = true;
            // 1. Inject the Base URL from global config
            // This ensures all requests automatically prefix with the host
            if (window.CM3_CONFIG && window.CM3_CONFIG.apiHostURL) {
                // We ensure there is a trailing slash and the path doesn't have a leading slash 
                // to prevent "http://host//path" errors
                const baseUrl = window.CM3_CONFIG.apiHostURL.replace(/\/$/, '');
                const path = config.url.replace(/^\//, '');
                config.url = `${baseUrl}/${path}`;
                shouldHaveAuth = !path.startsWith('public/');
            }

            // 2. Inject the Auth Token from Vuex
            const token = store.getters['mydata/getAuthToken'];
            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            } else if(shouldHaveAuth){
                //Anything not in public should be authenticated
                console.log('Calling without authentication', config.url, store)
            
            }
        } else {
            // If we hit this, the app is attempting an API call 
            // before the Vuex store is even ready.
            console.error("Axios Interceptor: Store is not yet initialized!");
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

export default customInstance;