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
    gotEventInfo: false,
    gotBadgeContexts: false,
    gotBadges: {},
    gotQuestions: {},
    gotAddons: {}
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
        state
    }) {

        return new Promise((resolve) => {
            //Load only if necessary
            if (!state.gotEventInfo) {
                shop.getEventInfo(eventinfo => {
                    commit('setEventInfo', eventinfo);
                    console.log('event stored info id', state.selectedEventId);
                    if (state.selectedEventId == null)
                        commit('selectEvent', eventinfo[0].id);
                    else {
                        commit('selectEvent', state.selectedEventId);
                    }
                    resolve();
                })
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
        rootState
    }) {
        return new Promise((resolve, reject) => {
            if (state.selectedEventId == null)
                return reject('Unable to get context if the event ID is not known');
            //Load only if necessary
            if (!state.gotBadgeContexts) {
                //Hack: Ask the mydata if we're an admin and use that to load contexts
                console.log('root state',rootState.mydata.permissions != null, rootState.mydata.permissions != undefined,rootState.mydata.adminMode)
                if(rootState.mydata.permissions != null 
                    && rootState.mydata.permissions != undefined
                    && rootState.mydata.adminMode == true){
                    admin.getBadgeContexts(rootState.mydata.token)
                    .then(contexts => {
                        commit('setBadgeContexts', contexts);
                        resolve();
                    }).catch(err =>{
                        reject(err)
                    });
                } else {

                    shop.getBadgeContexts(state.selectedEventId, contexts => {
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
                //Confirm we have a context to select that matches
                commit('setBadgeContextSelected', context_code);
                //Check that the desired context exists
                if (state.badgecontextselected == undefined)
                    return reject('Context Code not found:' + context_code);
                //Fetch all the things(if needed)!
                await dispatch('getContextBadges', state.badgecontextselected.context_code);
                await dispatch('getContextQuestions', state.badgecontextselected.context_code);
                await dispatch('getContextAddons', state.badgecontextselected.context_code);
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
        state
    }, context_code) {
        return new Promise((resolve, reject) => {
            //Prerequisite: We need a context
            if (context_code == undefined)
                return reject('Context not selected!');
            //Load only if necessary
            if (state.gotBadges[context_code] != undefined)
                return resolve();
            //Initialize to empty in case of failure
            commit('setContextBadges', {
                badges: [],
                context_code: context_code,
                success: false
            });
            try {
                shop.getBadges(state.selectedEventId,
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
                
            } catch (error) {
                
                console.log('products/getContextBadges error',error)
                reject(error)
            }
        })
    },
    getContextQuestions({
        dispatch,
        commit,
        state
    }, context_code) {
        return new Promise((resolve, reject) => {
            //Prerequisite: We need a context
            if (context_code == undefined)
                return reject('Context not selected!');
            //Load only if necessary
            if (state.gotQuestions[context_code] != undefined)
                return resolve();
            shop.getQuestions(state.selectedEventId,
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
        })
    },
    getContextAddons({
        dispatch,
        commit,
        state
    }, context_code) {
        return new Promise((resolve, reject) => {
            //Prerequisite: We need a context
            if (context_code == undefined)
                return reject('Context not selected!');
            //Load only if necessary
            if (state.gotAddons[context_code] != undefined)
                return resolve();
            shop.getAddons(state.selectedEventId,
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
                    resolve()
                })
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
    },
    selectEvent(state, eventid) {
        state.selectedEventId = eventid;
        state.selectedEvent = state.eventinfo.find(x => x.id == eventid);
        //Ask for a reset of the rest of the shop
        state.gotBadgeContexts = false;
        state.gotBadges = {};
        state.gotQuestions = {};
        state.gotAddons = {};
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

    }
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
}