<template>
<v-container fluid
             fill-height>
    <v-layout align-center
              justify-center>
        <v-flex xs12
                sm8
                md4>
            <v-tabs v-model="state"
                    show-arrows
                    background-color="deep-purple accent-4"
                    class="elevation-12"
                    icons-and-text
                    dark
                    grow>
                <v-toolbar color="primary"
                           dark
                           flat>
                    <v-toolbar-title v-show="state==0">Retrieve badges</v-toolbar-title>
                    <v-toolbar-title v-show="state==1">Login</v-toolbar-title>
                    <v-toolbar-title v-show="state==2">Email sent!</v-toolbar-title>
                    <v-toolbar-title v-show="state==3">Logged in!</v-toolbar-title>
                    <v-toolbar-title v-show="state==4">Failed!</v-toolbar-title>

                </v-toolbar>
                <v-tab-item>
                    <v-card class="px-4">
                        <v-form @submit.prevent="SendMagicLink"
                                v-model="formEMValid">
                            {{$route.params.message}}
                            <v-card-text>
                                <v-text-field label="Email used to register"
                                              v-model="email"
                                              name="email"
                                              prepend-icon="mdi-email"
                                              type="email"
                                              :rules="[rules.required]"></v-text-field>
                            </v-card-text>
                            <v-card-actions>
                                <v-btn color="default"
                                       @click="state=1"
                                       v-if="!loading">Use a password</v-btn>
                                <v-spacer></v-spacer>
                                <v-btn color="primary"
                                       type="submit"
                                       :disabled="!formEMValid || loading"
                                       :loading="loading">Send Magic Link</v-btn>
                            </v-card-actions>
                        </v-form>
                    </v-card>
                </v-tab-item>
                <v-tab-item>
                    <v-card class="px-4">
                        <v-form @submit.prevent="login"
                                v-model="formPWValid">
                            {{$route.params.message}}
                            <v-card-text>
                                <v-text-field id="username"
                                              label="Username"
                                              v-model="username"
                                              name="username"
                                              prepend-icon="mdi-account"
                                              type="text"
                                              :rules="[rules.required]"></v-text-field>
                                <v-text-field id="password"
                                              label="Password"
                                              v-model="password"
                                              name="password"
                                              prepend-icon="mdi-lock"
                                              :append-icon="showpassword ? 'mdi-eye' : 'mdi-eye-off'"
                                              :rules="[rules.required]"
                                              :type="showpassword ? 'text' : 'password'"></v-text-field>
                            </v-card-text>
                            <v-card-actions>
                                <v-btn color="default"
                                       @click="state=0"
                                       v-if="!loading">Send a magic link</v-btn>
                                <v-spacer></v-spacer>
                                <v-btn color="primary"
                                       type="submit"
                                       :disabled="!formPWValid || loading"
                                       :loading="loading">Login</v-btn>
                            </v-card-actions>
                        </v-form>
                    </v-card>
                </v-tab-item>
                <v-tab-item>
                    <v-card>
                        <v-card-text>If you have purchased any badges with the contact email <b>{{email}}</b>, you should receive the badge retrieval email shortly to confirm.</v-card-text>
                        <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn color="green darken-1"
                                   @click="state = 0">Try again</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-tab-item>
                <v-tab-item>
                    <v-card>
                        <v-card-text>Login Success!<br>
                            <div v-if="!(formEMValid || formPWValid)">You can return to the original tab or continue here.</div>
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn color="green darken-1"
                                   :to="returnTo">Continue</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-tab-item>
                <v-tab-item>
                    <v-card>
                        <v-card-text>{{loginFailReason}}</v-card-text>
                        <v-card-actions>
                            <v-spacer></v-spacer>
                            <v-btn color="green darken-1"
                                   @click="state = 1">Try again</v-btn>
                        </v-card-actions>
                    </v-card>
                </v-tab-item>
            </v-tabs>

        </v-flex>
    </v-layout>
</v-container>
</template>

<script>
import {
    mapActions
} from 'vuex';

export default {
    data: () => ({
        state: 0,
        loading: false,
        formEMValid: false,
        formPWValid: false,
        formValid: false,
        email: '',
        username: '',
        password: '',
        showpassword: false,
        loginFailReason: "???",

        rules: {
            required: value => !!value || 'Required.',
        },
    }),
    computed: {
        isAdmin: function() {
            return this.$store.getters['mydata/hasPerms'];
        },
        event_id: function() {
            return this.$store.getters['products/selectedEventId'];
        },
        loginReasonText() {
            const reason = this.$route.query.reason;
            if (reason === 'admin') {
                return 'You must be logged in to access the admin panel.';
            }
            return '';
        },
        loginTitle() {
            const reason = this.$route.query.reason;
            // Admin context — use admin-appropriate title for the login forms
            if (reason === 'admin' && this.state <= 1) {
                return 'Admin Login';
            }
            // Default titles based on current tab / flow step
            switch (this.state) {
                case 0: return 'Retrieve badges';
                case 1: return 'Login';
                case 2: return 'Email sent!';
                case 3: return 'Logged in!';
                case 4: return 'Failed!';
                default: return 'Login';
            }
        },
        returnTo: function() {
            const currentRoute = this.$router.currentRoute;
            if (currentRoute.query != undefined && currentRoute.query.returnTo != undefined)
                return currentRoute.query.returnTo;
            if (currentRoute.params != undefined && currentRoute.params.returnTo != undefined)
                return currentRoute.params.returnTo;

            if (this.isAdmin) return '/';

            return '/';
        }
    },
    methods: {
        ...mapActions('mydata', [
            'sendRetrieveBadgeEmail',
            'loginToken',
            'loginPassword',
        ]),
        ...mapActions('products', [
            'setAdminMode'
        ]),
        SendMagicLink() {
            this.loading = true;
            this.sendRetrieveBadgeEmail({
                    email_address: this.email,
                    returnTo: this.returnTo,
                    event_id: this.event_id
                }).then(() => {
                    this.state = 2;
                    this.loading = false;
                })
                .catch((errorresult) => {
                    this.state = 4;
                    this.loginFailReason = errorresult.error.message;
                    this.loading = false;
                });
        },
        login() {
            this.loading = true;
            this.loginPassword({
                username: this.username,
                password: this.password,
                event_id: this.event_id
            }).then((success) => {
                if (success === true) {
                    // Set admin mode if redirecting to an admin route (consistent with token flow)
                    if (this.returnTo.startsWith("/Admin/") && this.isAdmin) {
                        this.setAdminMode(true);
                    }
                    this.$router.push(this.returnTo);
                } else {
                    this.loginFailReason = success;
                    this.state = 4;
                }
                this.loading = false;
            });
        },
        completeLogin() {
            let {
                query
            } = this.$route;
            if (query.token != undefined) {
                this.loginToken(query.token).then((success) => {
                    if (success === true) {
                        this.state = 3;
                        //TODO: Invent a better way to detect if the route path is admin or not...
                        if(this.returnTo.startsWith("/Admin/") && this.isAdmin)  {
                            this.setAdminMode(true);
                        }
                        
                        // Send a message that we logged in
                        this.$broadcast.postMessage({
                            type: 'Logged-in'
                        });
                        // 2. Set a timeout: If no one responds in .2 second,
                        // we are the primary tab or nobody is also on the login screen.
                        // If we DO get a response, we halt and let them know their options
                        this.handoffTimeout = setTimeout(() => {
                            this.$router.push(this.returnTo);
                        }, 200);                    
                    } else {
                        this.loginFailReason = success.error.message;
                        this.state = 4;
                    }
                    this.loading = false;
                });
            }
        },
        handleGlobalMessage(event) {
            switch (event.data.type){
                case 'Logged-in': {
                    //Another tab just logged in and we're waiting for that
                    //Signal back that we're alive and will take it from here
                    this.$broadcast.postMessage({
                        type: 'Logged-in-thanks'
                    });
                    //Shove them on their way
                    console.log('Trying to focus again')
                    window.focus();
                    this.state = 3;
                } break;
                case 'Logged-in-thanks':{
                    //another tab is sitting at the login screen and should be trying to focus itself
                    // Clear the timeout so we don't redirect
                    clearTimeout(this.handoffTimeout);
                } break;

            }
        },
    },
    watch: {
        $route() {
            this.$nextTick(this.completeLogin);
        },
    },
    created() {
        // If redirected from an admin route, jump straight to the password form
        if (this.$route.query.reason === 'admin') {
            this.state = 1;
        }
        this.$broadcast.addEventListener('message', this.handleGlobalMessage);
        this.completeLogin();
    },
    beforeDestroy() {
        // IMPORTANT: Unbind the listener when the component is destroyed
        this.$broadcast.removeEventListener('message', this.handleGlobalMessage);
    }
};
</script>
