<template>
    <v-container fluid fill-height>
        <v-layout align-center justify-center>
            <v-flex xs12 sm8 md4>
                <v-tabs v-model="state" show-arrows background-color="deep-purple accent-4" class="elevation-12"
                    icons-and-text dark grow>
                    <v-toolbar color="primary" dark flat>
                        <v-toolbar-title v-show="state === 0">OAuth Authorization</v-toolbar-title>
                        <v-toolbar-title v-show="state === 1">Success</v-toolbar-title>
                        <v-toolbar-title v-show="state === 2">Error</v-toolbar-title>
                    </v-toolbar>

                    <!-- State 0: Grant Form -->
                    <v-tab-item>
                        <v-card v-if="clientInfo" class="elevation-12 pa-4" rounded="lg">


                            <v-card-text class="text-center pt-6 pb-2">
                                <v-avatar color="primary" size="200" class="mb-4 elevation-5" tile>

                                    <v-img v-if="clientInfo.logo_url" :src="clientInfo.logo_url" max-width="200"
                                        class="mx-auto" />
                                    <!-- Replace with actual requesting app logo -->
                                    <v-icon v-else dark size="36">mdi-apps</v-icon>
                                </v-avatar>
                                <h1 class="text-h5 font-weight-bold black--text mb-1">
                                    {{ clientInfo.app_name }}
                                </h1>
                                <div class="text-body-2 text--secondary">
                                    wants access to your Account for
                                    <div v-if="!editingExpiresIn">
                                        <v-btn small @click="editingExpiresIn = !editingExpiresIn">
                                            <b>
                                                {{ expires_in_human }}</b>
                                        </v-btn>
                                    </div>
                                    <div v-else>
                                        <v-row>
                                            <v-col cols="9">

                                                <inputDuration v-model="expires_in"></inputDuration>
                                            </v-col><v-col cols="3">
                                                <v-btn small @click="editingExpiresIn = !editingExpiresIn">
                                                    <v-icon>mdi-check</v-icon>
                                                </v-btn>
                                            </v-col>
                                        </v-row>
                                    </div>.
                                </div>
                                <div class="text-caption grey lighten-3 pa-2 rounded text-truncate">
                                    Logged in as: <strong class="black--text">{{ getLoggedInName }}</strong>
                                </div>
                            </v-card-text>

                            <v-divider class="my-3"></v-divider>

                            <!-- Scope / Permissions List -->
                            <v-card-text class="py-0">
                                <p class="text-subtitle-2 font-weight-medium black--text mb-3">
                                    This application will be able to:
                                </p>

                                <v-list dense flat class="pa-0">
                                    <v-list-item v-for="(scope, index) in requestedScopesDescriptions" :key="index"
                                        class="px-0 align-start mb-2">
                                        <v-list-item-icon class="mr-3 mt-1">
                                            <v-icon color="primary" size="20">mdi-check-circle-outline</v-icon>
                                        </v-list-item-icon>
                                        <v-list-item-content class="py-0">
                                            <v-list-item-title
                                                class="text-body-2 font-weight-medium black--text text-wrap">
                                                {{ scope.description }}
                                            </v-list-item-title>
                                            <v-list-item-subtitle class="text-caption text-wrap mt-1">
                                                {{ scope.id }}
                                            </v-list-item-subtitle>
                                        </v-list-item-content>
                                    </v-list-item>
                                </v-list>
                            </v-card-text>

                            <v-divider class="my-4"></v-divider>

                            <!-- Trust / Policy Disclaimers -->
                            <v-card-text class="pt-0 pb-4 text-caption text--secondary line-height-sm">
                                Make sure you trust {{ clientInfo.app_name }}. You may be sharing sensitive info with
                                this site or app. You can always view or remove access in your profile settings.
                            </v-card-text>

                            <!-- Action Buttons -->
                            <v-card-actions class="pb-4 px-4 justify-end" v-if="isLoggedIn">
                                <v-btn text :loading="loading" color="grey darken-1"
                                    class="text-none font-weight-bold px-4 mr-2" @click="handleOAuthDeny">
                                    Cancel
                                </v-btn>
                                <v-btn depressed :loading="loading" color="primary"
                                    class="text-none font-weight-bold px-6" @click="handleOAuthGrant">
                                    Allow
                                </v-btn>
                            </v-card-actions>
                            <v-card-actions class="pb-4 px-4 justify-end" v-else>
                                <v-btn depressed :loading="loading" color="primary"
                                    class="text-none font-weight-bold px-6"
                                    :to="{ path: '/login', query: { returnTo: $route.fullPath } }">
                                    Log in to Allow
                                </v-btn>
                            </v-card-actions>

                        </v-card>
                    </v-tab-item>

                    <!-- State 1: Success -->
                    <v-tab-item>
                        <v-card>
                            <v-card-text class="text-center">
                                <v-icon color="green" large>mdi-check-circle</v-icon>
                                <p class="mt-2">Authorization successful! Returrning to the application...</p>
                            </v-card-text>
                        </v-card>
                    </v-tab-item>

                    <!-- State 2: Failure -->
                    <v-tab-item>
                        <v-card>
                            <v-card-text class="text-center">
                                <v-icon color="red" large>mdi-alert-circle</v-icon>
                                <p class="mt-2">{{ errorReason }}</p>
                            </v-card-text>
                            <v-card-actions>
                                <v-spacer></v-spacer>
                                <v-btn color="green darken-1" @click="state = 0">Try Again</v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-tab-item>
                    <!-- State 3: Init error -->
                    <v-tab-item>
                        <v-card>
                            <v-card-text class="text-center">
                                <v-icon color="red" large>mdi-alert-circle</v-icon>
                                <p class="mt-2">Oauth parameter error: {{ getFriendlyMessage(validationErrorCode) }}</p>
                            </v-card-text>
                            <v-card-actions>
                                <v-spacer></v-spacer>
                                <v-btn color="green darken-1" @click="handleOuathInvalid">Return to {{
                                    clientInfo.app_name || 'Prior app' }}</v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-tab-item>
                </v-tabs>
            </v-flex>
        </v-layout>
    </v-container>
</template>

<script>
import shop from '@/api/shop';
import inputDuration from '@/components/inputDuration.vue'

import {
    mapState,
    mapGetters,
    mapActions
} from 'vuex'
export default {
    components: {
        inputDuration
    },
    data() {
        return {
            state: 0, // 0: Form, 1: Success, 2: Failure
            loading: false,
            clientInfo: {
                "active": 0,
                "app_name": "[[Loading]]",
                "logo_url": "",
                "developer_name": "",
                "developer_email": "",
                "developer_url": "",
                "redirect_uri": "",
                "client_id": ""
            },
            client_id: this.$route.query.client_id || '',
            redirect_uri: this.$route.query.redirect_uri || '',
            raw_scope: this.$route.query.scope || '',
            expires_in: this.$route.query.expires_in || 604800,
            editingExpiresIn: false,
            errorReason: ''
        };
    },
    computed: {
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
            'getLoggedInName': 'getLoggedInName',
        }),
        requestedScopesDescriptions() {
            const scopeMap = {
                'READ_ACCOUNT_BASIC': 'Read basic account info (Email address and name)',
                'READ_ACCOUNT_FULL': 'Read general account info and contact details',
                'WRITE_ACCOUNT': 'Update account info and contact details',
                'READ_BADGES': 'Read current badge info',
                'READ_CART': 'Read shopping carts',
                'WRITE_CART': 'Create and update shopping carts (does not allow purchasing)'
            };
            return this.raw_scope.split(' ')
                .map((s) => { return { id: s, description: scopeMap[s.trim()] || s }; })
        },
        pkce_data() {
            return (this.$route.query?.code_challenge + ':' + this.$route.query?.code_challenge_method) || '';
        },
        expires_in_human() {
            let totalSeconds = this.expires_in;
            if (totalSeconds < 60) {
                return "Less than a minute";
            }

            // Define time units in seconds, ordered from largest to smallest
            const units = [
                { name: "year", seconds: 31536000 }, // 365 days
                { name: "month", seconds: 2592000 },
                { name: "day", seconds: 86400 },
                { name: "hour", seconds: 3600 },
                { name: "minute", seconds: 60 }
            ];

            let remainingSeconds = totalSeconds;
            const parts = [];

            for (const unit of units) {
                const value = Math.floor(remainingSeconds / unit.seconds);

                if (value > 0) {
                    // Append an 's' if the number is greater than 1
                    const label = value === 1 ? unit.name : `${unit.name}s`;
                    parts.push(`${value} ${label}`);

                    // Keep track of the leftover seconds for the next smaller unit
                    remainingSeconds %= unit.seconds;
                }
            }

            // Join elements nicely: "A, B and C"
            if (parts.length === 1) return parts[0];

            const lastPart = parts.pop();
            return `${parts.join(", ")} and ${lastPart}`;
        },
        validationErrorCode() {
            const query = this.$route.query;

            // 1. Check for missing critical parameters
            if (!query.client_id) {
                return 'invalid_client_id'; // Missing application identifier
            }
            if (!query.redirect_uri) {
                return 'missing_redirect_uri'; // No callback URL provided
            }

            if (!this.isRedirectUriAllowed(query.redirect_uri)) {
                return 'invalid_redirect_uri'; // No callback URL provided

            }

            // 2. Validate standard OAuth protocol parameters
            if (!query.response_type) {
                return 'missing_response_type';
            }
            if (query.response_type !== 'code') {
                return 'unsupported_response_type'; // Your provider only supports 'code'
            }

            // 3. Validate PKCE parameters if present
            if (query.code_challenge || query.code_challenge_method) {
                if (!query.code_challenge) {
                    return 'missing_code_challenge'; // Method was sent, but challenge is missing
                }

                // If challenge is present but method is omitted, OAuth standard says default to 'plain'
                const method = query.code_challenge_method || 'plain';
                if (method !== 'S256' && method !== 'plain') {
                    return 'invalid_code_challenge_method'; // Reject unsupported hashes
                }
            }

            // 4. Validate scope format (should be space-separated, no commas)
            if (query.scope && query.scope.includes(',')) {
                return 'invalid_scope_format'; // Remind developer to use spaces instead of commas
            }

            // Everything is structurally valid on the client side!
            return null;
        },

    },
    async created() {
        if (this.validationErrorCode == null) {
            this.fetchClientInfo();
        } else {
            //Instant failed the basic details, go to the error screen
            this.clientInfo.app_name = 'Prior page'
            this.state = 3;
        }
    },
    methods: {
        fetchClientInfo() {
            this.loading = true;
            shop.getOauthClientInfo(this.client_id, (data) => {
                this.clientInfo = data;
                this.loading = false;
            }, (err) => {
                this.errorReason = 'Could not retrieve application details.';
                this.state = 2;
                this.loading = false;
            });
        },
        getFriendlyMessage(code) {
            const messages = {
                'invalid_client_id': 'The application identifier is missing or malformed.',
                'missing_redirect_uri': 'No return URL was specified by the application.',
                'invalid_redirect_uri': 'The redirect URL provided does not match the registered callbacks for this app.',
                'unsupported_response_type': 'This server only supports the Authorization Code flow.',
                'invalid_code_challenge_method': 'Unsupported PKCE encryption method. Use S256.',
                'invalid_scope_format': 'Developer error: Scopes must be space-separated, not comma-separated.'
            };
            return messages[code] || 'An insecure or invalid authorization request was detected.';
        },
        isRedirectUriAllowed(incomingUriString) {
            //If we can't check yet, assume it's fine
            if (!this.clientInfo || !this.clientInfo.redirect_uri) {
                return true;
            }

            // 1. Split the carriage-return separated string into a clean array
            const allowedUris = this.clientInfo.redirect_uri
                .split(/\r?\n/)
                .map(uri => uri.trim())
                .filter(uri => uri.length > 0);

            // 2. Quick check: Try an exact string match first
            if (allowedUris.includes(incomingUriString)) {
                return true;
            }

            // 3. Fallback: Exact match failed, check for a localhost loopback port variance
            try {
                const incomingUrl = new URL(incomingUriString);
                const incomingHost = incomingUrl.hostname;

                // Only allow port variance if the target is localhost or 127.0.0.1
                if (incomingHost === 'localhost' || incomingHost === '127.0.0.1') {
                    return allowedUris.some(registeredUriString => {
                        try {
                            const registeredUrl = new URL(registeredUriString);

                            // Schemes, hostnames, and paths must match exactly, ignoring the port
                            return incomingUrl.protocol === registeredUrl.protocol &&
                                incomingHost === registeredUrl.hostname &&
                                incomingUrl.pathname === registeredUrl.pathname;
                        } catch (e) {
                            return false; // Skip malformed database strings safely
                        }
                    });
                }
            } catch (e) {
                console.log('badurl', incomingUriString)
                return false; // Incoming URL is structurally unparsable
            }
            console.log('no match', incomingUrl)
            return false; // No matches found
        },
        handleOAuthGrant() {
            this.loading = true;

            shop.getOauthTokenCode(
                this.client_id,
                this.raw_scope,
                this.expires_in,
                this.redirect_uri,
                this.pkce_data,
                (data) => {
                    this.state = 1;
                    this.loading = false;
                    if (data.code == undefined) {
                        return;
                    }
                    // Create a native URL object to safely append query strings without messy string manipulation
                    const redirectUrl = new URL(this.redirect_uri);

                    // Always append the short-lived authorization code
                    redirectUrl.searchParams.set('code', data.code);

                    // CRUCIAL SECURITY STEP: If the client provided a 'state' in the initial request, 
                    // you MUST append it back exactly as it was received to protect them from CSRF.
                    if (this.$route.query.state) {
                        redirectUrl.searchParams.set('state', this.$route.query.state);
                    }

                    // Just go! Instantly send the user's browser back to the third-party application
                    console.log('Redirect!', redirectUrl.toString());
                    setTimeout(() => {
                        window.location.href = redirectUrl.toString();
                    }, 1000);
                },
                (error) => {
                    this.errorReason = error.error_description || 'OAuth grant failed.';
                    this.state = 2;
                    this.loading = false;
                }
            );
        },
        handleOAuthDeny() {
            const redirectUrl = new URL(this.redirect_uri);
            redirectUrl.searchParams.set('error', 'access_denied');
            if (this.$route.query.state) {
                redirectUrl.searchParams.set('state', this.$route.query.state);
            }
            window.location.href = redirectUrl.toString();
        },
        handleOuathInvalid() {
            // Create a native URL object to safely append query strings without messy string manipulation
            const redirectUrl = new URL(this.redirect_uri);
            // Always append the short-lived authorization code
            redirectUrl.searchParams.set('error', this.validationErrorCode);
            if (this.$route.query.state) {
                redirectUrl.searchParams.set('state', this.$route.query.state);
            }
            window.location.href = redirectUrl.toString();

        },
    }
};
</script>