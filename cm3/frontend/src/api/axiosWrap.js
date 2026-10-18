import axios from 'axios';
import store from '../storage';


const frontendOrigin = window.location.origin 
const frontendPath = process.env.VUE_APP_PUBLIC_PATH ?? '/'
const isHashMode = process.env.VUE_APP_HASHMODE === 'true'

// If hash mode is on, inject the '#/', otherwise just use the normal path
const backendLinkPrefix = `${frontendOrigin}${frontendPath}` + (isHashMode  ? '#/': '')

// State for managing token rotation
let isRefreshing = false;
let refreshQueue = [];

// Helper to process the queue
const processQueue = (error = null) => {
    refreshQueue.forEach((prom) => {
        if (error) {
            // If rotation failed, reject the pending requests since they probably won't succeed anyways
            prom.reject(error);
        } else {
            // If rotation succeeded, transform and resolve the config
            const transformedConfig = applyConfigTransformations(prom.config);
            prom.resolve(transformedConfig);
        }
    });
    refreshQueue = [];
};

const applyConfigTransformations = (config) => {
    if (!store || !store.getters) {
        console.error("Axios Interceptor: Store is not yet initialized!");
        return config;
    }

    const path = config.url.replace(/^\//, '');
    // 1. Inject Base URL if set
    if (window.CM3_CONFIG && window.CM3_CONFIG.apiHostURL) {
        const baseUrl = window.CM3_CONFIG.apiHostURL.replace(/\/$/, '');
        config.url = `${baseUrl}/${path}`;
    } else {
        // Fall back to a traditional, self-hosted relative route pointing at /api
        config.url = `/api/${path}`;
    }

    // 2. Inject Auth Token
    const token = store.getters['mydata/getAuthToken'];
    const shouldHaveAuth = !config.url.includes('public/');
    
    if (token && (config.headers?.Authorization || '') == '') {
        config.headers.Authorization = `Bearer ${token}`;
    } else if (shouldHaveAuth) {
        console.log('Calling without authentication', config.url);
    }

    // 3. Inject our return path for the API to generate URLs
    config.headers['X-Frontend-Base-URL'] = backendLinkPrefix

    return config;
};

const customInstance = axios.create({});

customInstance.interceptors.request.use(
    (config) => {
        // Queue the request if we're doing something that could invalidate the current token
        if (isRefreshing && !config.bypassQueue) {
            return new Promise((resolve, reject) => {
                refreshQueue.push({ resolve, reject, config });
            });
        }

        // Otherwise process normally
         return applyConfigTransformations(config);
    },
    (error) => Promise.reject(error)
);

customInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            // Capture metadata for the user or helpdesk
            console.log('unauthorized returned!',error.response)
            store.dispatch('products/setAuthError', {
                hasError: true,
                message: error.response.data?.error?.message || error.response.data?.message || "Unauthorized",
                statusCode: error.response.status,
                endpoint: error.config.url
            });
        }
        return Promise.reject(error);
    }
);

// Export command to control whether we're queueing or not
export const setTokenRefreshing = (status, error = null) => {
    isRefreshing = status;
    if (!status) {
        processQueue(error);
    }
};

export default customInstance;