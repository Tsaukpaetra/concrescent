const axios = require('axios').default;

export default {

    getEventInfo(cb, errorCb) {
        axios.get(global.config.apiHostURL + "public")
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                console.log(error)
                if (typeof errorCb == "function")
                    errorCb(error);
            })
    },
    getBadgeContexts(event_id, cb, errorCb) {
        axios.get(global.config.apiHostURL + "public/" + event_id + '/badges')
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                console.log(error)
                if (typeof errorCb == "function")
                    errorCb(error);
            })
    },
    getLocations(event_id, cb, errorCb) {
        axios.get(global.config.apiHostURL + "public/" + event_id + '/locations')
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                console.log(error)
                if (typeof errorCb == "function")
                    errorCb(error);
            })
    },
    getLocationCategories(event_id, cb, errorCb) {
        axios.get(global.config.apiHostURL + "public/" + event_id + '/locationcategories')
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                console.log(error)
                if (typeof errorCb == "function")
                    errorCb(error);
            })
    },
    getLocationEvents(event_id, cb, errorCb) {
        axios.get(global.config.apiHostURL + "public/" + event_id + '/locationevents')
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                console.log(error)
                if (typeof errorCb == "function")
                    errorCb(error);
            })
    },
    getBadges(event_id, context, override_code, cb, errorCb) {
        const override = (override_code ?? '').replace(/[^a-z0-9]/gi, '').toUpperCase();
        var query = override != '' ? '?override=' + override : '';
        axios.get(global.config.apiHostURL + "public/" + event_id + '/badges/' + context + query)
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                console.log(error)
                if (typeof errorCb == "function")
                    errorCb(error);
            })
    },

    getQuestions(event_id, context, cb, errorCb) {
        axios.get(global.config.apiHostURL + "public/" + event_id + '/questions/' + context)
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                console.log(error)
                if (typeof errorCb == "function")
                    errorCb(error);
            })
    },

    getAddons(event_id, context, override_code, cb, errorCb) {
        const override = (override_code ?? '').replace(/[^a-z0-9]/gi, '').toUpperCase();
        var query = override != '' ? '?override=' + override : '';
        axios.get(global.config.apiHostURL + "public/" + event_id + '/badges/' + context + '/addons' + query)
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                console.log(error)
                if (typeof errorCb == "function")
                    errorCb(error);
            })
    },

    getCarts(token, include_all, cb, errorCb) {
        axios.get(global.config.apiHostURL + "account/cart?include_all=" + include_all, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                console.log(response)
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },

    checkEmailAddress(token, email_address, cb, errorCb) {
        headers = {};
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        axios.post(global.config.apiHostURL + "public/checkemail", { email_address: email_address }, {
            headers: headers
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (er) {
                if (typeof errorCb == "function")
                    errorCb(er.response.data);
            });
    },
    //Response should be a token
    createAccount(accountInfo, cb, errorCb) {
        axios.post(global.config.apiHostURL + "public/createaccount", accountInfo)
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },
    loginAccount(accountCreds, cb, errorCb) {
        axios.post(global.config.apiHostURL + "public/login", accountCreds)
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },
    getContactInfo(token, cb, errorCb) {
        axios.get(global.config.apiHostURL + "account", {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },
    setContactInfo(token, data, cb, errorCb) {
        axios.post(global.config.apiHostURL + "account", data, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },
    switchEvent(token, event_id, cb, errorCb) {
        axios.post(global.config.apiHostURL + "account/switchevent", {
            "event_id": event_id
        }, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb != "undefined")
                    errorCb(response.response.data);
            });
    },
    setAccountSettings(token, settings, cb, errorCb) {
        axios.post(global.config.apiHostURL + "account/settings", settings, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb != "undefined")
                    errorCb(response.response.data);
            });
    },

    loadCart(token, cartId, cb, errorCb) {
        axios.get(global.config.apiHostURL + "account/cart/" + cartId, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (er) {
                if (typeof errorCb == "function")
                    errorCb(er.response.data);
            });
    },
    saveCart(token, cart, cb, errorCb) {
        axios.post(global.config.apiHostURL + "account/cart", cart, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (er) {
                if (typeof errorCb == "function")
                    errorCb(er.response.data);
            });
    },
    deleteCart(token, cartId, cb, errorCb) {
        console.log("yo delete dis", cartId)
        axios.delete(global.config.apiHostURL + "account/cart/" + cartId, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (er) {
                if (typeof errorCb == "function")
                    errorCb(er.response.data);
            });
    },
    buyProducts(token, cartId, payment_system, cb, errorCb) {
        axios.post(global.config.apiHostURL + `account/cart/${cartId}/checkout`, {
            payment_system: payment_system
        }, {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },
    checkoutCartUUID(cartUUID, cb, errorCb) {
        axios.post(global.config.apiHostURL + `public/checkoutcartuuid`, {
            uuid: cartUUID
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },

    applyPromo(products, promo, cb, errorCb) {
        axios.post(global.config.apiHostURL + "cart.php", {
            action: 'applypromo',
            code: promo,
            badges: products
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (response) {
                if (typeof errorCb == "function")
                    errorCb(response.response.data);
            });
    },

    getMyBadgesByTransaction(gid, tid, cb, errorCb) {
        axios.post(global.config.apiHostURL + "mybadges.php", {
            gid: gid,
            tid: tid
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                if (typeof errorCb == "function")
                    errorCb(error.response.data);
            })
    },
    getMyBadges(token, cb, errorCb) {
        axios.get(global.config.apiHostURL + "account/badges", {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                if (typeof errorCb == "function")
                    errorCb(error.response.data);
            })
    },
    getSpecificBadge(context_code, id, uuid, cb, errorCb) {
        axios.get(global.config.apiHostURL + "public/getspecificbadge?context_code=" +
            context_code + "&id=" + id + "&uuid=" + uuid)
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                if (typeof errorCb == "function")
                    errorCb(error.response.data);
            })
    },
    getMyApplications(token, cb, errorCb) {
        axios.get(global.config.apiHostURL + "account/applications", {
            headers: {
                Authorization: `Bearer ${token}`
            }
        })
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                if (typeof errorCb == "function")
                    errorCb(error.response.data);
            })
    },
    sentEmailRetrieveBadges(email_data, cb, errorCb) {
        if (typeof email_data == 'string')
            email_data = {
                email_address: email_data
            };
        axios.post(global.config.apiHostURL + "public/requestmagic", email_data)
            .then(function (response) {
                cb(response.data);
            })
            .catch(function (error) {
                if (typeof errorCb == "function")
                    errorCb(error.response.data);
            })
    },
}