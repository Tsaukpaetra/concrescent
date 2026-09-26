import axios from './axiosWrap'; // Uses the interceptor for URL and Token

export default {
    /**
     * Default boilerplate function for standard requests
     */
    _request(method, path, data, cb, errorCb) {
        const request = axios({
            method: method,
            url: path,
            data: data
        });

        request
            .then((response) => {
                // If a callback exists, pass the data
                if (typeof cb === 'function') {
                    cb(response.data);
                }
            })
            .catch((error) => {
                // If an error callback exists, pass the response data (or error object)
                if (typeof errorCb === 'function') {
                    errorCb(error.response?.data || error);
                }
            });
    },

    contextToPrefix(context_code) {
        switch (context_code) {
            case 'A': return 'Attendee';
            case 'S': return 'Staff';
            default: return 'Application/' + context_code;
        }
    },

    // --- GENERIC METHODS ---

    genericGet(path, params, cb, errorCb) {
        const qparams = new URLSearchParams({...params}).toString();
        const url = qparams.length > 0 ? `${path}?${qparams}` : path;
        this._request('get', url, null, cb, errorCb);
    },

    genericPost(path, data, cb, errorCb) {
        this._request('post', path, data, cb, errorCb);
    },

    genericPut(path, data, cb, errorCb) {
        this._request('put', path, data, cb, errorCb);
    },

    genericPatch(path, data, cb, errorCb) {
        this._request('patch', path, data, cb, errorCb);
    },

    genericDelete(path, cb, errorCb) {
        this._request('delete', path, null, cb, errorCb);
    },

    genericGetList(path, params, cb, errorCb) {
        const qparams = new URLSearchParams({...params}).toString();
        const url = qparams.length > 0 ? `${path}?${qparams}` : path;
        
        // Special case for GetList because it returns extra header data (total rows)
        axios.get(url)
            .then(res => {
                const totalRows = res.headers['x-total-rows'] ? parseInt(res.headers['x-total-rows']) : undefined;
                if (typeof cb === 'function') cb(res.data, totalRows);
            })
            .catch(err => { if (typeof errorCb === 'function') errorCb(err.response?.data || err); });
    },

    // --- BADGE CHECKIN METHODS ---

    badgeCheckinSearch(searchText, pageOptions, cb, errorCb) {
        const params = new URLSearchParams({ "find": searchText, ...pageOptions }).toString();
        // Use specialized logic for search because it needs the x-total-rows header
        axios.get(`Badge/CheckIn?${params}`)
            .then(res => {
                const totalRows = parseInt(res.headers['x-total-rows']);
                if (typeof cb === 'function') cb(res.data, totalRows);
            })
            .catch(err => { if (typeof errorCb === 'function') errorCb(err.response?.data || err); });
    },

    badgeCheckinFetch(context, id, cb, errorCb) {
        this._request('get', `Badge/CheckIn/${context}/${id}`, null, cb, errorCb);
    },

    badgeCheckinSave(badgeData, cb, errorCb) {
        this._request('post', `Badge/CheckIn/${badgeData.context_code}/${badgeData.id}/Update`, badgeData, cb, errorCb);
    },

    badgeCheckinGetPayment(context, id, cb, errorCb) {
        this._request('get', `Badge/CheckIn/${context}/${id}/GetPayment`, null, cb, errorCb);
    },

    badgeCheckinConfirmPayment(context, id, payData, cb, errorCb) {
        this._request('post', `Badge/CheckIn/${context}/${id}/PostPayment`, payData, cb, errorCb);
    },

    badgeCheckinFinish(context, id, cb, errorCb) {
        this._request('post', `Badge/CheckIn/${context}/${id}/Finish`, null, cb, errorCb);
    },

    // --- ADMIN / PROMISE-BASED METHODS ---
    // Note: We use the standard axios directly here to return actual Promises to the caller

    getEventInfo() {
        return axios.get('EventInfo').then(res => res.data);
    },

    getBadgeContexts() {
        return axios.get('Group?includeDefault=true').then(res => res.data);
    },

    getBadges(context_code, override_code, cb, errorCb) {
        const path = `${this.contextToPrefix(context_code)}/BadgeTypeAll`;
        this._request('get', path, null, cb, errorCb);
    },

    getAddons(context_code, override_code, cb, errorCb) {
        const path = `${this.contextToPrefix(context_code)}/AddonAll`;
        this._request('get', path, null, cb, errorCb);
    },

    getQuestions(context_code,  cb, errorCb) {
        const path = `Form/Question/${context_code}/All`;
        this._request('get', path, null, cb, errorCb);
    },

    getLocations() {
        return axios.get('Location').then(res => res.data);
    },

    getLocationCategories() {
        return axios.get('LocationCategory').then(res => res.data);
    },

    getLocationEvents() {
        return axios.get('Location/Assignments').then(res => res.data);
    },

    getAllStaffPositions() {
        return axios.get('Staff/Department/AllPositions').then(res => res.data);
    }
};