import axios from 'axios';
import store from '../storage';

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

    // 1. Inject Base URL
    if (window.CM3_CONFIG && window.CM3_CONFIG.apiHostURL) {
        const baseUrl = window.CM3_CONFIG.apiHostURL.replace(/\/$/, '');
        const path = config.url.replace(/^\//, '');
        config.url = `${baseUrl}/${path}`;
    }

    // 2. Inject Auth Token
    const token = store.getters['mydata/getAuthToken'];
    const shouldHaveAuth = !config.url.includes('public/');
    
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    } else if (shouldHaveAuth) {
        console.log('Calling without authentication', config.url);
    }

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

// You can export these to control rotation from your switchEvent logic
export const setTokenRefreshing = (status, error = null) => {
    isRefreshing = status;
    if (!status) {
        processQueue(error);
    }
};

export default customInstance;