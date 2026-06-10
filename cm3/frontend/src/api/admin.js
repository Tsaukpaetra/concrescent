const axios = require('axios').default;

export default {
    contextToPrefix(context_code) {
        switch (context_code) {
            case 'A':
                return 'Attendee';
            case 'S':
                return 'Staff';
            default:
                return 'Application/' + context_code;
        }
    },
    genericGet(token, path, params, cb, errorCb) {
        var qparams = new URLSearchParams({
            ...params
        }).toString();
        if (qparams.length > 0)
            qparams = '?' + qparams
        axios.get(window.CM3_CONFIG.apiHostURL + path + qparams, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },

    genericPost(token, path, data, cb, errorCb) {
        axios.post(window.CM3_CONFIG.apiHostURL + path, data, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    genericPut(token, path, data, cb, errorCb) {
        axios.put(window.CM3_CONFIG.apiHostURL + path, data, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    genericPatch(token, path, data, cb, errorCb) {
        axios.patch(window.CM3_CONFIG.apiHostURL + path, data, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    genericDelete(token, path, cb, errorCb) {
        axios.delete(window.CM3_CONFIG.apiHostURL + path, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    genericGetList(token, path, params, cb, errorCb) {
        var qparams = new URLSearchParams({
            ...params
        }).toString();
        if (qparams.length > 0)
            qparams = '?' + qparams
        axios.get(window.CM3_CONFIG.apiHostURL + path + qparams, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data, response.headers['x-total-rows'] != undefined ? parseInt(response.headers['x-total-rows']) : undefined);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    badgeCheckinSearch(token, searchText, pageOptions, cb, errorCb) {
        var params = new URLSearchParams({
            "find": searchText,
            ...pageOptions
        }).toString();
        axios.get(window.CM3_CONFIG.apiHostURL + "Badge/CheckIn?" + params, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data, parseInt(response.headers['x-total-rows']));
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    badgeCheckinFetch(token, context, id, cb, errorCb) {
        axios.get(window.CM3_CONFIG.apiHostURL + "Badge/CheckIn/" + context + "/" + id, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    badgeCheckinSave(token, badgeData, cb, errorCb) {
        axios.post(window.CM3_CONFIG.apiHostURL + "Badge/CheckIn/" + badgeData.context_code + "/" + badgeData.id + "/Update", badgeData, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    badgeCheckinGetPayment(token, context, id, cb, errorCb) {
        axios.get(window.CM3_CONFIG.apiHostURL + "Badge/CheckIn/" + context + "/" + id + "/GetPayment", {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    badgeCheckinConfirmPayment(token, context, id, payData, cb, errorCb) {
        axios.post(window.CM3_CONFIG.apiHostURL + "Badge/CheckIn/" + context + "/" + id + "/PostPayment", payData, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    badgeCheckinFinish(token, context, id, cb, errorCb) {
        axios.post(window.CM3_CONFIG.apiHostURL + "Badge/CheckIn/" + context + "/" + id + "/Finish", null, {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                cb(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb != "undefined")
                    errorCb(error.response.data);
            })
    },
    //Breaking from the callback mold...
    getEventInfo(token) {
        return new Promise((resolve,reject) =>{
            axios.get(window.CM3_CONFIG.apiHostURL + 'EventInfo', {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                resolve(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb == "function")
                    reject(error.response.data);
            })
        })
    },
    getBadgeContexts(token) {
        return new Promise((resolve,reject) =>{
            axios.get(window.CM3_CONFIG.apiHostURL + 'Group?includeDefault=true', {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                resolve(response.data);
            })
            .catch(function(error) {
                if (typeof errorCb == "function")
                    reject(error.response.data);
            })
        })
    },
    getLocations(token) {
        return new Promise((resolve,reject) =>{
            axios.get(window.CM3_CONFIG.apiHostURL + 'Location', {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                resolve(response.data);
            })
            .catch(function(error) {
                reject(error.response.data);
            })
        })
    },
    getLocationCategories(token) {
        return new Promise((resolve,reject) =>{
            axios.get(window.CM3_CONFIG.apiHostURL + 'LocationCategory', {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                resolve(response.data);
            })
            .catch(function(error) {
                reject(error.response.data);
            })
        })
    },
    getLocationEvents(token) {
        return new Promise((resolve,reject) =>{
            axios.get(window.CM3_CONFIG.apiHostURL + 'Location/Assignments', {
                headers: {
                    Authorization: `Bearer ${token}`
                }
            })
            .then(function(response) {
                resolve(response.data);
            })
            .catch(function(error) {
                reject(error.response.data);
            })
        })
    },


}