import shop from '../../api/shop'

// initial state
const state = {
    token: "",
    username: "",
    permissions: null,
    preferences: {},
    adminMode: false,
    ownedbadges: [],
    applications: [],
    BadgeRetrievalStatus: false,
    BadgeRetrievalResult: '',
    contactInfo: {
        "allow_marketing": 0,
        "email_address": "",
        "real_name": "",
        "phone_number": "",
        "address_1": "",
        "address_2": "",
        "city": "",
        "state": "",
        "zip_code": "",
        "country": ""
    },
    activeCarts: [],
    allCarts: null,
}


// getters
const getters = {
    ownedBadgeCount: (state) => {
        return state.ownedbadges == undefined ? 0 : state.ownedbadges.length;
    },
    applicationCount: (state) => {
        return state.applications == undefined ? 0 : state.applications.length;
    },

    getBadgeAsCart: (state) =>
        (badgeIx) => {
            // var tempProduct = [
            //     state.ownedbadges[badgeIx]
            // ]
            // var result = shop.transformPOSTData(tempProduct, true)[0];
            var result = state.ownedbadges[badgeIx];
            if (result.addonsSelected != undefined && result.addonsSelected.length > 0)
                result.editBadgePriorAddons = [...result.addonsSelected];
            return result;
        },
    getApplicationAsCart: (state) =>
        (badgeIx) => {
            // var tempProduct = [
            //     state.ownedbadges[badgeIx]
            // ]
            // var result = shop.transformPOSTData(tempProduct, true)[0];
            var result = state.applications[badgeIx];
            if (result.addonsSelected != undefined && result.addonsSelected.length > 0)
                result.editBadgePriorAddons = [...result.addonsSelected];
            return result;
        },
    getAuthToken: (state) => {
        return state.token;
    },
    getUsername: (state) => {
        return state.username;
    },
    getIsLoggedIn: (state) => {
        return state.token != "" && state.contactInfo != undefined && state.contactInfo.id != undefined;
    },
    getAdminMode: (state) => {
        return state.token != "" && state.adminMode;
    },
    getPreferences: (state) => {
        return state.preferences;
    },
    getPerms: (state) => {
        return state.permissions;
    },
    hasPerms: (state) => {
        return state.permissions != null && state.permissions != undefined;
    },
    hasEventPerm: (state) => (permNames) => {
        if (state.permissions == null || state.permissions == undefined) return false;
        //Make sure we're doing an array.
        if (!Array.isArray(permNames))
            permNames = [permNames];
        return state.permissions.EventPerms.findIndex((perm) => permNames.find((il) => il == perm) || perm == 'GlobalAdmin') > -1;
    },
    hasGroupPerm: (state, getters) => (groupId, permNames) => {
        if (state.permissions == null || state.permissions == undefined) return false;
        //Check if they're a GlobalAdmin
        if (getters.hasEventPerm("GlobalAdmin")) return true;
        if (state.permissions.GroupPerms[groupId] == undefined) return false;
        //Make sure we're doing an array.
        if (!Array.isArray(permNames))
            permNames = [permNames];
        return state.permissions.GroupPerms[groupId].findIndex((perm) => permNames.find((il) => il == perm) || perm == 'GlobalAdmin') > -1;
    },
    getContactInfo: (state) => {
        return state.contactInfo;
    },
    getLoggedInName: (state) => {
        if (state.contactInfo != undefined) {
            if (state.contactInfo.real_name.length > 0)
                return state.contactInfo.real_name;
            if (state.contactInfo.email_address.length > 0)
                return state.contactInfo.email_address;
        }
        return "Guest";
    }
}

// actions
const actions = {
    createAccount({
        dispatch,
        commit
    }, accountInfo) {
        return new Promise((resolve, reject) => {
            shop.createAccount(accountInfo,
                (token) => {
                    resolve(dispatch('loginToken', token));
                }, (error) => {
                    reject(error);
                });

        })
    },
    loginToken({
        dispatch,
        commit,
        rootState
    }, token) {
        return new Promise((resolve) => {
            shop.switchEvent(token, rootState.products.selectedEventId, (data) => {
                    commit('setToken', data.token);
                    commit('setPermissions', data.permissions);
                    commit('setUsername', data.username);
                    commit('setPreferences', data.preferences);
                    commit('setAdminMode', false)
                    dispatch('products/selectEventId', data.event_id, {
                        root: true
                    });
                    dispatch('refreshContactInfo');
                    commit('setOwnedBadges', []);
                    dispatch('retrieveBadges');
                    dispatch('retrieveApplications');
                    resolve(true);
                },
                (error) => {
                    dispatch('logout');
                    resolve(error || "Failed, maybe the link expired?")
                }
            );

        })
    },
    RefreshToken({
        state,
        dispatch
    }) {
        return dispatch('loginToken', state.token);
    },
    loginPassword({
        dispatch,
        commit,
        state
    }, credentials) {
        return new Promise((resolve) => {
            shop.loginAccount(credentials, (data) => {
                commit('setToken', data.token);
                commit('setPermissions', data.permissions);
                commit('setUsername', data.username);
                commit('setPreferences', data.preferences);
                commit('setAdminMode', data.permissions != undefined)
                dispatch('products/selectEventId', data.event_id, {
                    root: true
                });
                dispatch('refreshContactInfo');
                resolve(true);
            }, (error) => {
                resolve(error.error.message);
            });
        })
    },
    logout({
        commit,
        dispatch
    }) {
        commit('setPermissions', null);
        commit('setUsername', "");
        commit('setPreferences', {});
        commit('setContactInfo', {
            "allow_marketing": 0,
            "email_address": "",
            "real_name": "",
            "phone_number": "",
            "address_1": "",
            "address_2": "",
            "city": "",
            "state": "",
            "zip_code": "",
            "country": ""
        });
        commit('setAdminMode', false);
        commit('setOwnedBadges', []);
        commit('setApplications', []);
        commit('setCarts', {
            include_all: true,
            carts: []
        });
        commit('setCarts', {
            include_all: false,
            carts: []
        });

        dispatch('cart/loadCart', null, {
            root: true
        });
        dispatch('cart/clearCart', null, {
            root: true
        });
        commit('setToken', "");
    },
    setAdminMode({
        commit
    }, newAdminMode) {
        commit('setAdminMode', newAdminMode);
    },
    refreshContactInfo({
        commit,
        state
    }) {
        return new Promise((resolve) => {
            shop.getContactInfo(state.token, (data) => {
                commit('setContactInfo', data);
            })
        })
    },
    fetchCarts({
        commit,
        state
    }, include_all) {
        return new Promise((resolve, reject) => {
            if (state.token.length < 1) {
                reject({
                    error: {
                        message: 'not logged in'
                    }
                });
                return;
            }
            shop.getCarts(state.token, include_all, (carts) => {
                commit('setCarts', {
                    carts,
                    include_all,
                    success: true
                });
                resolve(carts);
            }, (error) => {
                commit('setCarts', {
                    carts: null,
                    include_all,
                    success: false
                });
                reject(error);
            })
        })
    },
    updateContactInfo({
        commit,
        state
    }, newData) {
        return new Promise((resolve) => {
            shop.setContactInfo(state.token, newData, (data) => {
                commit('setContactInfo', data);
                resolve(true);
            })
        }, (error) => {
            resolve(error.error.message);
        });
    },
    updateSettings({
        commit,
        state
    }, newData) {
        return new Promise((resolve) => {
            shop.setAccountSettings(state.token, newData, (data) => {
                commit('setUsername', newData.username);
                commit('setPreferences', newData.preferences);
                resolve(true);
            })
        }, (error) => {
            resolve(error.error.message);
        });
    },
    sendRetrieveBadgeEmail({
        commit
    }, email_data) {
        return new Promise((resolve, reject) => {
            commit('setBadgeRetrievalStatus', true)
            shop.sentEmailRetrieveBadges(email_data, (result) => {
                    commit('setBadgeRetrievalStatus', false);
                    resolve(result);
                },
                function(data) {
                    //Error?
                    commit('setBadgeRetrievalResult', "Error requesting badge retrieval email. " + data);
                    //console.log(data);
                    reject(data);
                });

        });
    },
    retrieveBadges({
        commit,
        state
    }) {
        shop.getMyBadges(state.token, (data) => {

            var updatedBadges = state.ownedbadges.map(badge => {
                var found = data.find(d => badge.uuid == d.uuid);
                if (found != undefined)
                    return found;
                return badge;
            });
            //Add any that didn't exist before
            data.forEach((item, i) => {
                if (-1 == updatedBadges.findIndex(badge => badge.uuid == item.uuid))
                    updatedBadges.push(item);
            });
            commit('setOwnedBadges', updatedBadges);

        })
    },
    retrieveSpecificBadge({
        commit,
        state
    }, {
        context_code,
        id,
        uuid
    }) {
        shop.getSpecificBadge(context_code, id, uuid, (data) => {

            var updatedBadges = state.ownedbadges.map(badge => {
                if (badge.uuid == data.uuid)
                    return data;
                return badge;
            });
            //Check that we have it
            if (-1 == updatedBadges.findIndex(badge => badge.uuid == data.uuid))
                updatedBadges.push(data);
            commit('setOwnedBadges', updatedBadges);
        })
    },
    retrieveTransactionBadges({
        commit,
        state
    }, submitted) {
        return;
        shop.getMyBadgesByTransaction(
            submitted.gid, submitted.tid,
            (data) => {
                var newdata = {};
                //First get the existing ones
                Object.keys(state.ownedbadges).forEach(item => newdata[state.ownedbadges[item]['id-string']] = state.ownedbadges[item]);
                data.forEach(item => newdata[item['id-string']] = item);

                commit('setOwnedBadges', {
                    items: newdata
                });
                commit('setBadgeRetrievalResult', "Retrieved " + data.length + " badges.");
            },
            function(data) {
                //Error?
                commit('setBadgeRetrievalResult', "Error retrieving badges." + data);
                //console.log(data);
            }
        )
    },
    retrieveApplications({
        commit,
        state
    }) {
        shop.getMyApplications(state.token, (data) => {

            commit('setApplications', data);

        })
    },
    clearBadgeRetrievalResult({
        commit
    }) {
        commit('setBadgeRetrievalResult', "");
    }
}

// mutations
const mutations = {
    initialiseData(state) {
        // Check if the ID exists
        if (localStorage.getItem('mydata')) {
            // Replace the state object with the stored item
            //this.replaceState(
            Object.assign(state, JSON.parse(localStorage.getItem('mydata')))
            //);
        }
    },

    setToken(state, newtoken) {
        state.token = newtoken;
    },
    setPermissions(state, newPermissions) {
        state.permissions = newPermissions;
    },
    setPreferences(state, newpreferences) {
        state.preferences = newpreferences;
    },
    setUsername(state, newusername) {
        state.username = newusername;
    },
    setAdminMode(state, newAdminMode) {
        state.adminMode = newAdminMode;
    },
    setContactInfo(state, newContactInfo) {
        state.contactInfo = newContactInfo;
    },

    setOwnedBadges(state, items) {
        if (items != undefined)
            state.ownedbadges = items;
        else
            state.ownedbadges = [];
    },
    setBadgeRetrievalStatus(state, newState) {
        state.BadgeRetrievalStatus = newState;
    },
    setBadgeRetrievalResult(state, newState) {
        state.BadgeRetrievalResult = newState;
    },
    setApplications(state, items) {
        if (items != undefined)
            state.applications = items;
        else
            state.applications = [];
    },
    setCarts(state, cartsInfo) {
        //      {carts:null,include_all:include_all,success:false}
        if (cartsInfo.include_all) {
            state.allCarts = cartsInfo.carts;
        } else {
            state.activeCarts = cartsInfo.carts;
        }
    },
    updateActiveCart(state, cartdata) {
        //Find the cart item (if it exists)
        var ix = state.activeCarts.findIndex(cart => cart.id == cartdata.id);
        //upsert
        state.activeCarts.splice(ix, ix == -1 ? 0 : 1, cartdata);
    }

}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}