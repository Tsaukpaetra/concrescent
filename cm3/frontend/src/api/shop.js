import axios from './axiosWrap'; // Uses the interceptor for URL and Token
import { setTokenRefreshing } from './axiosWrap';

export default {
    _request(method, url, data = null, cb, errorCb) {
        axios({
            method,
            url,
            data
        })
            .then((response) => {
                if (typeof cb === 'function') {
                    cb(response.data);
                }
            })
            .catch((error) => {
                console.error(error);
                if (typeof errorCb === 'function') {
                    errorCb(error.response?.data || error);
                }
            });
    },

    // Helper for override logic
    _getOverrideQuery(override_code) {
        const override = (override_code ?? '').replace(/[^a-z0-9]/gi, '').toUpperCase();
        return override !== '' ? `?override=${override}` : '';
    },

    getEventInfo(cb, errorCb) {
        this._request('get', 'public', null, cb, errorCb);
    },

    getBadgeContexts(event_id, cb, errorCb) {
        this._request('get', `public/${event_id}/badges`, null, cb, errorCb);
    },

    getLocations(event_id, cb, errorCb) {
        this._request('get', `public/${event_id}/locations`, null, cb, errorCb);
    },

    getLocationCategories(event_id, cb, errorCb) {
        this._request('get', `public/${event_id}/locationcategories`, null, cb, errorCb);
    },

    getLocationEvents(event_id, cb, errorCb) {
        this._request('get', `public/${event_id}/locationevents`, null, cb, errorCb);
    },

    getBadges(event_id, context, override_code, cb, errorCb) {
        const query = this._getOverrideQuery(override_code);
        this._request('get', `public/${event_id}/badges/${context}${query}`, null, cb, errorCb);
    },

    getQuestions(event_id, context, cb, errorCb) {
        this._request('get', `public/${event_id}/questions/${context}`, null, cb, errorCb);
    },

    getAddons(event_id, context, override_code, cb, errorCb) {
        const query = this._getOverrideQuery(override_code);
        this._request('get', `public/${event_id}/badges/${context}/addons${query}`, null, cb, errorCb);
    },

    getCarts(include_all, cb, errorCb) {
        this._request('get', `account/cart?include_all=${include_all}`, null, cb, errorCb);
    },

    checkEmailAddress(email_address, cb, errorCb) {
        // Token is removed from arguments and handled by interceptor
        this._request('post', 'public/checkemail', { email_address: email_address }, cb, errorCb);
    },

    createAccount(accountInfo, cb, errorCb) {
        this._request('post', 'public/createaccount', accountInfo, cb, errorCb);
    },

    loginAccount(accountCreds, cb, errorCb) {
        //Just in case
        setTokenRefreshing(true);
        axios.post("public/login", accountCreds, {
            bypassQueue: true
        })
            .then(function (response) {
                cb(response.data);
                setTokenRefreshing(false);
            })
            .catch(function (response) {
                setTokenRefreshing(false, response);
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },

    getContactInfo(cb, errorCb) {
        this._request('get', 'account', null, cb, errorCb);
    },

    setContactInfo(data, cb, errorCb) {
        this._request('post', 'account', data, cb, errorCb);
    },

    switchEvent(event_id, cb, errorCb) {
        setTokenRefreshing(true);
        axios.post("account/switchevent", {
            "event_id": event_id
        }, {
            headers: {
                Authorization: `Bearer ${token}`
            },
            bypassQueue: true
        })
            .then(function (response) {
                cb(response.data);
                setTokenRefreshing(false);
            })
            .catch(function (response) {
                setTokenRefreshing(false, response);
                if (typeof errorCb != "undefined")
                    errorCb(response.response.data);
            });
    },

    setAccountSettings(settings, cb, errorCb) {
        this._request('post', 'account/settings', settings, cb, errorCb);
    },

    loadCart(cartId, cb, errorCb) {
        this._request('get', `account/cart/${cartId}`, null, cb, errorCb);
    },

    saveCart(cart, cb, errorCb) {
        this._request('post', 'account/cart', cart, cb, errorCb);
    },

    deleteCart(cartId, cb, errorCb) {
        this._request('delete', `account/cart/${cartId}`, null, cb, errorCb);
    },

    buyProducts(cartId, payment_system, cb, errorCb) {
        this._request('post', `account/cart/${cartId}/checkout`, { payment_system }, cb, errorCb);
    },

    checkoutCartUUID(cartUUID, cb, errorCb) {
        this._request('post', `public/checkoutcartuuid`, { uuid: cartUUID }, cb, errorCb);
    },

    applyPromo(products, promo, cb, errorCb) {
        this._request('post', 'cart.php', {
            action: 'applypromo',
            code: promo,
            badges: products
        }, cb, errorCb);
    },

    getMyBadgesByTransaction(gid, tid, cb, errorCb) {
        this._request('post', 'mybadges.php', { gid, tid }, cb, errorCb);
    },

    getMyBadges(cb, errorCb) {
        this._request('get', 'account/badges', null, cb, errorCb);
    },

    getSpecificBadge(context_code, id, uuid, cb, errorCb) {
        const url = `public/getspecificbadge?context_code=${context_code}&id=${id}&uuid=${uuid}`;
        this._request('get', url, null, cb, errorCb);
    },

    getSpecificApplication(context_code, id, uuid, cb, errorCb) {
        const url = `public/getspecificapplication?context_code=${context_code}&id=${id}&uuid=${uuid}`;
        this._request('get', url, null, cb, errorCb);
    },

    getMyApplications(cb, errorCb) {
        this._request('get', 'account/applications', null, cb, errorCb);
    },

    sentEmailRetrieveBadges(email_data, cb, errorCb) {
        const payload = typeof email_data === 'string' ? { email_address: email_data } : email_data;
        this._request('post', 'public/requestmagic', payload, cb, errorCb);
    },
};