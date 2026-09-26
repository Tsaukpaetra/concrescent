import shop from '../../api/shop'
import admin from '../../api/admin'
import Vue from 'vue'

// initial state
const state = {
    eventinfo: [],
    adminContextToken: '',
    selectedEventId: null,
    selectedEvent: null,
    override_code: '',
    badgecontexts: [],
    badgecontextselectedix: 0,
    badgecontextselected: null,
    badges: {},
    questions: {},
    addons: {},
    locations:[],
    locationCategories:[],
    locationEvents:[],
    allStaffPositions:[],
    gotEventInfo: false,
    gotBadgeContexts: false,
    gotBadges: {},
    gotQuestions: {},
    gotAddons: {},
    gotLocations: false,
    gotLocationCategories: false,
    gotLocationEvents: false,
    gotAllStaffPositions: false,
}

// getters
const getters = {

    events: (state) => {
        return state.eventinfo || [];
    },
    selectedEventId: (state) => {
        return state.selectedEventId || 0;
    },
    selectedEvent: (state) => {
        return state.selectedEvent || {
            "id": 0,
            "shortcode": "",
            "active": 0,
            "display_name": "Loading...",
            "date_start": "",
            "date_end": ""
        };
    },
    gotBadgeContexts: (state) => {
        return state.gotBadgeContexts || false;
    },
    badgeContexts: (state) => {
        return state.badgecontexts || [];
    },
    selectedbadgecontext: (state) => {
        return state.badgecontextselected || {
            "context_code": "",
            "name": "Loading..."
        };
    },
    contextBadges: (state) => {
        if (state.badgecontextselected == undefined) return [];
        return state.badges[state.badgecontextselected.context_code] || [];
    },
    contextQuestions: (state) => {
        if (state.badgecontextselected == undefined) return [];
        return state.questions[state.badgecontextselected.context_code] || [];
    },
    contextAddons: (state) => {
        if (state.badgecontextselected == undefined) return [];
        return state.addons[state.badgecontextselected.context_code] || [];
    },
    questions: (state) => {
        return state.questions || [];
    },
    locations: (state) => {
        return state.locations || [];
    },
    locationCategories: (state) => {
        return state.locationCategories || [];
    },
    locationEvents: (state) => {
        return state.locationEvents || [];
    },
    allStaffPositions: (state) => {
        return state.allStaffPositions || [];
    },
    /**
     * Returns the appropriate API client (admin or shop) and the associated token/context
     * based on the user's permissions in the root state.
     */
    apiClient: (state, getters, rootState) => {
        const isAdmin = rootState.mydata.permissions != null 
            && rootState.mydata.permissions != undefined
            && rootState.mydata.adminMode === true;

        return {
            client: isAdmin ? admin : shop,
            token: isAdmin ? rootState.mydata.token : null,
            isAdmin: isAdmin
        };
    }

}

// actions
const actions = {
    selectEventId({
        dispatch,
        commit
    }, event_id) {
        return new Promise(async (resolve) => {
            commit('selectEvent', event_id);
            await dispatch('getBadgeContexts');
            resolve();
        })
    },
    setOverrideCode({
        commit
    }, override) {
        return new Promise((resolve) => {
            commit('setOverrideCode', override);
            resolve();
        })
    },
    getEventInfo({
        commit,
        state,
        rootState,
        getters
    }, force) {

        return new Promise((resolve, reject) => {
            if (!state.gotEventInfo || force) {
                const { client, token, isAdmin } = getters.apiClient;

                if (isAdmin) {
                    client.getEventInfo()
                    .then(eventinfo => {
                        commit('setEventInfo', eventinfo);
                        if (state.selectedEventId == null)
                            commit('selectEvent', eventinfo[0].id);
                        else {
                            commit('selectEvent', state.selectedEventId);
                        }
                        resolve();
                    }).catch(err =>{
                        reject(err)
                    });
                } else {
                    client.getEventInfo(eventinfo => {
                        commit('setEventInfo', eventinfo);
                        if (state.selectedEventId == null)
                            commit('selectEvent', eventinfo[0].id);
                        else {
                            commit('selectEvent', state.selectedEventId);
                        }
                        resolve();
                    })
                }
            } else {
                resolve();
            }
        })
    },
    resetBadgeContexts({commit,state}){
        commit('selectEvent',state.selectedEventId);
    },
    getBadgeContexts({
        commit,
        state,
        rootState,
        getters
    }, force) {
        return new Promise((resolve, reject) => {
            if (state.selectedEventId == null)
                return reject('Unable to get context if the event ID is not known');

            if (!state.gotBadgeContexts || force) {
                const { client, token, isAdmin } = getters.apiClient;

                if (isAdmin) {
                    client.getBadgeContexts()
                    .then(contexts => {
                        commit('setBadgeContexts', contexts);
                        resolve();
                    }).catch(err =>{
                        reject(err)
                    });
                } else {
                    client.getBadgeContexts(state.selectedEventId, contexts => {
                        commit('setBadgeContexts', contexts);
                        resolve();
                    })
                }
            } else {
                resolve();
            }
        })
    },
    selectContext({
        dispatch,
        commit,
        state
    }, context_code) {
        return new Promise(async (resolve, reject) => {
            try {
                await dispatch('getBadgeContexts');
                commit('setBadgeContextSelected', context_code);
                if (state.badgecontextselected == undefined)
                    return reject('Context Code not found:' + context_code);
                await dispatch('getContextBadges', context_code);
                await dispatch('getContextQuestions', context_code);
                await dispatch('getContextAddons', context_code);
                console.log("products/selectContext completed",context_code)
                resolve()
            } catch (error) {
                console.log("products/selectContext error",error)
                reject(error);
            }
        })
    },
    getContextBadges({
        dispatch,
        commit,
        state,
        getters
    }, context_code) {
        return new Promise((resolve, reject) => {
            if (context_code == undefined)
                return reject('Context not selected!');
            if (state.gotBadges[context_code] != undefined)
                return resolve();

            commit('setContextBadges', {
                badges: [],
                context_code: context_code,
                success: false
            });

            const { client, token, isAdmin } = getters.apiClient;
            
            try {
                if (isAdmin) {
                    client.getBadges(context_code, state.override_code,
                    badges => {
                        commit('setContextBadges', {
                            badges: badges,
                            context_code: context_code,
                            success: true
                        });
                        resolve();
                    },
                    err => {
                        commit('setContextBadges', {
                            badges: [],
                            context_code: context_code,
                            success: false
                        });
                        resolve();
                    });
                } else {
                    client.getBadges(state.selectedEventId,
                        context_code, state.override_code,
                        badges => {
                            commit('setContextBadges', {
                                badges: badges,
                                context_code: context_code,
                                success: true
                            });
                            resolve();
                        },
                        error => {
                            commit('setContextBadges', {
                                badges: [],
                                context_code: context_code,
                                success: false
                            });
                            resolve()
                        })
                }
            } catch (error) {
                console.log('products/getContextBadges error',error)
                reject(error)
            }
        })
    },
    getContextQuestions({
        dispatch,
        commit,
        state,
        getters
    }, context_code) {
        return new Promise((resolve, reject) => {
            if (context_code == undefined)
                return reject('Context not selected!');
            if (state.gotQuestions[context_code] != undefined)
                return resolve();
            
            const { client, token, isAdmin } = getters.apiClient;

            if (isAdmin) {
                client.getQuestions(context_code,
                questions => {
                    commit('setContextQuestions', {
                        questions: questions,
                        context_code: context_code,
                        success: true
                    });
                    resolve();
                },
                error => {
                    commit('setContextQuestions', {
                        questions: [],
                        context_code: context_code,
                        success: false
                    });
                    resolve()
                })
            } else {
                
            client.getQuestions(state.selectedEventId,
                context_code,
                questions => {
                    commit('setContextQuestions', {
                        questions: questions,
                        context_code: context_code,
                        success: true
                    });
                    resolve();
                },
                error => {
                    commit('setContextQuestions', {
                        questions: [],
                        context_code: context_code,
                        success: false
                    });
                    resolve()
                })
            }
        })
    },
    getContextAddons({
        dispatch,
        commit,
        state,
        getters
    }, context_code) {
        return new Promise((resolve, reject) => {
            if (context_code == undefined)
                return reject('Context not selected!');
            if (state.gotAddons[context_code] != undefined)
                return resolve();

            const { client, token, isAdmin } = getters.apiClient;

            if (isAdmin) {
                client.getAddons(context_code, state.override_code,
                addons => {
                    commit('setContextAddons', {
                        addons: addons,
                        context_code: context_code,
                        success: true
                    });
                    resolve();
                },
                err => {
                    commit('setContextAddons', {
                        addons: [],
                        context_code: context_code,
                        success: false
                    });
                    resolve();
                });
            } else {
                client.getAddons(state.selectedEventId,
                    context_code, state.override_code,
                    addons => {
                        commit('setContextAddons', {
                            addons: addons,
                            context_code: context_code,
                            success: true
                        });
                        resolve();
                    },
                    error => {
                        commit('setContextAddons', {
                            addons: [],
                            context_code: context_code,
                            success: false
                        });
                        resolve();
                    })
                };
            })
        
    },
    getLocations({
        commit,
        state,
        rootState,
        getters
    }) {
        return new Promise((resolve, reject) => {
            if (state.selectedEventId == null)
                return reject('Unable to get locations if the event ID is not known');
            if (!state.gotLocations) {
                commit('setLocations', []);
                const { client, token, isAdmin } = getters.apiClient;

                if (isAdmin) {
                    client.getLocations()
                    .then(contexts => {
                        commit('setLocations', contexts);
                        resolve();
                    }).catch(err =>{
                        reject(err)
                    });
                } else {
                    client.getLocations(state.selectedEventId, contexts => {
                        commit('setLocations', contexts);
                        resolve();
                    })
                }
            } else {
                resolve();
            }
        })
    },
    getLocationCategories({
        commit,
        state,
        rootState
    }) {
        return new Promise((resolve, reject) => {
            if (state.selectedEventId == null)
                return reject('Unable to get location categories if the event ID is not known');            
            //Load only if necessary
            if (!state.gotLocationCategories) {
                commit('setLocationCategories', []);
                //Hack: Ask the mydata if we're an admin and use that to load contexts
                if(rootState.mydata.permissions != null 
                    && rootState.mydata.permissions != undefined
                    && rootState.mydata.adminMode == true){
                    admin.getLocationCategories(rootState.mydata.token)
                    .then(contexts => {
                        commit('setLocationCategories', contexts);
                        resolve();
                    }).catch(err =>{
                        reject(err)
                    });
                } else {

                    shop.getLocationCategories(state.selectedEventId, contexts => {
                        commit('setLocationCategories', contexts);
                        resolve();
                    })
                }
            } else {
                resolve();
            }
        })
    },
    getLocationEvents({
        commit,
        state,
        rootState
    }) {
        return new Promise((resolve, reject) => {
            if (state.selectedEventId == null)
                return reject('Unable to get location events if the event ID is not known');            
            //Load only if necessary
            if (!state.gotLocationEvents) {
                commit('setLocationEvents', []);
                //Hack: Ask the mydata if we're an admin and use that to load contexts
                if(rootState.mydata.permissions != null 
                    && rootState.mydata.permissions != undefined
                    && rootState.mydata.adminMode == true){
                    admin.getLocationEvents(rootState.mydata.token)
                    .then(contexts => {
                        commit('setLocationEvents', contexts);
                        resolve();
                    }).catch(err =>{
                        reject(err)
                    });
                } else {

                    shop.getLocationEvents(state.selectedEventId, contexts => {
                        commit('setLocationEvents', contexts);
                        resolve();
                    })
                }
            } else {
                resolve();
            }
        })
    },
    getAllStaffPositions({
        commit,
        state,
        rootState
    }) {
        return new Promise((resolve, reject) => {
            if (state.selectedEventId == null)
                return reject('Unable to get location events if the event ID is not known');            
            //Load only if necessary
            if (!state.gotAllStaffPositions) {
                commit('setAllStaffPositions', []);
                admin.getAllStaffPositions(rootState.mydata.token)
                .then(contexts => {
                    commit('setAllStaffPositions', contexts);
                    resolve();
                }).catch(err =>{
                    reject(err)
                });
                
            } else {
                resolve();
            }
        })
    },
}

// mutations
const mutations = {
    setEventInfo(state, eventinfo) {
        state.eventinfo = eventinfo;
        state.gotEventInfo = true;
        //Ask for a reset of the rest of the shop
        state.gotBadgeContexts = false;
        state.gotBadges = {};
        state.gotQuestions = {};
        state.gotAddons = {};
        state.gotLocations = false;
        state.gotLocationCategories = false;
    },
    selectEvent(state, eventid) {
        state.selectedEventId = eventid;
        state.selectedEvent = state.eventinfo.find(x => x.id == eventid);
        //Ask for a reset of the rest of the shop
        state.gotBadgeContexts = false;
        state.gotBadges = {};
        state.gotQuestions = {};
        state.gotAddons = {};
        state.gotLocations = false;
        state.gotLocationCategories = false;
    },
    setBadgeContexts(state, contexts) {
        state.badgecontexts = contexts;
        state.gotBadgeContexts = true;;
        state.gotBadges = {};
        state.gotQuestions = {};
        state.gotAddons = {};
    },
    setOverrideCode(state, override) {
        state.override_code = override;
        //Reset everything too
        state.gotBadgeContexts = false;
        state.gotBadges = {};
        state.gotQuestions = {};
        state.gotAddons = {};
    },
    setBadgeContextSelected(state, context) {
        state.badgecontextselected = state.badgecontexts.find(x => x.context_code == context);
    },
    setContextBadges(state, data) {
        Vue.set(state.badges, data.context_code, data.badges);
        Vue.set(state.gotBadges, data.context_code, data.success);
    },
    setContextQuestions(state, data) {
        Vue.set(state.questions, data.context_code, data.questions)
        Vue.set(state.gotQuestions, data.context_code, data.success);
    },
    setContextAddons(state, data) {
        Vue.set(state.addons, data.context_code, data.addons)
        Vue.set(state.gotAddons, data.context_code, data.success);
    },

    decrementProductQuantity(state, {
        id,
        context_code
    }) {
        const product = state.badges[context_code].find(product => product.id === id)
        if (product.quantity_remaining > 0) {
            product.quantity_remaining--
        }

    },
    setLocations(state, locations) {
        state.locations = locations;
        state.gotLocations = true;
    },
    updateLocation(state,location) {
        var existing = state.locations.findIndex(x=>x.id == location.id);
        if(existing > -1) {
            state.locations[existing] = location;
        } else {
            state.locations.push(location);
        }
    },
    setLocationCategories(state, categories) {
        state.locationCategories = categories;
        state.gotLocationCategories = true;
    },
    updateLocationCategory(state,category) {
        var existing = state.locationCategories.findIndex(x=>x.id == category.id);
        if(existing > -1) {
            state.locationCategories[existing] = category;
        } else {
            state.locationCategories.push(category);
        }
    },
    setLocationEvents(state, Events) {
        state.locationEvents = Events;
        state.gotLocationEvents = true;
    },
    updateLocationEvent(state,event) {
        var existing = state.locationEvents.findIndex(x=>x.id == event.id);
        if(existing > -1) {
            state.locationEvents[existing] = event;
        } else {
            state.locationEvents.push(event);
        }
    },
    deleteLocationEvent(state,event) {
        var existing = state.locationEvents.findIndex(x=>x.id == event.id);
        if(existing > -1) {
            state.locationEvents = state.locationEvents.splice(existing,1);
        } else {
            console.log('deleteLocationEvent failed, not found', event)
        }
    },
    setAllStaffPositions(state, allStaffPositions) {
        state.allStaffPositions = allStaffPositions;
        state.gotAllStaffPositions = true;
    },
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}