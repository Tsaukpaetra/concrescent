<template>
<v-container :class="{'pa-2':true,'printing':!printingBadge}"
             fluid>
    <v-row>
        <p>
            <i v-if="!ownedbadgecount">You have no badges. Click on the link in your confirmation email, or Add one.</i>
        </p>
    </v-row>
    <v-row>
        <v-col cols="12"
               md="6"
               lg="4"
               xl="3"
               v-for="(badge, idx) in applications"
               :key="badge['uuid']">
            <v-card>
                <badgeSampleRender :badge="badge" />
                <v-card-actions>
                    {{badge['badge_type_name']}}

                    <v-spacer></v-spacer>
                    <v-badge left
                             :content="badge.application_status">
                    </v-badge>
                    <v-btn icon
                           @click.stop="displayBadge = idx">
                        <v-icon>mdi-information</v-icon>
                    </v-btn>
                    <v-btn icon
                            :disabled="badge.application_status!='Accepted'"
                           :to="{name:'editbadge', params: {editAppIx: idx}}">
                        <v-icon>mdi-pencil</v-icon>
                    </v-btn>
                </v-card-actions>

                <v-card-actions v-for="addon in badge.addons"
                                :key="addon['addon_id']">
                    <v-icon>mdi-plus</v-icon>
                    <div class="text-truncate pl-1">
                        {{addon['name']}}
                    </div>
                </v-card-actions>
                <v-card-actions v-for="(subbadge,ix) in badge.subbadges"
                                :key="ix">
                    <v-icon>mdi-account</v-icon>
                    <div class="text-truncate pl-1">
                        {{subbadge | badgeDisplayName(false)}}
                    </div>

                </v-card-actions>
                <v-card-actions v-if="badge.assignment_count">
                    <v-icon>mdi-application</v-icon>
                    <div class="text-truncate pl-1">
                        {{ badge.assignment_count }} Assignment slots
                    </div>
                </v-card-actions>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn 
                    v-if="badge.payment_status == 'Incomplete' && badge.application_status == 'PendingAcceptance'"
                    color="primary"
                    :to="{name:'cart', query: {id: badge.payment_id}}">Go to payment</v-btn>
                    <v-btn 
                    v-if="badge.payment_status == 'Incomplete' && badge.application_status != 'PendingAcceptance'"
                    :to="{name:'cart', query: {id: badge.payment_id}}">Go to cart</v-btn>
                </v-card-actions>
            </v-card>
        </v-col>
    </v-row>
    <v-row>
        <v-col>
        </v-col>
        <v-spacer></v-spacer>
    </v-row>

    <v-dialog v-model="displayBadgeModal"
              max-width="600"
              persistent
              :hide-overlay="printingBadge"
              :fullscreen="printingBadge">
        <v-card v-if="displayBadgeData">
            <v-card-actions class="d-print-none">
                <v-btn color="red lighten-1"
                       @click="removeBadge">
                    <v-icon>mdi-delete</v-icon>
                </v-btn>
                <v-spacer></v-spacer>
                <v-btn color="blue darken-1"
                       @click="printBadgeInfo">
                    <v-icon>mdi-printer</v-icon>
                </v-btn>
                <v-btn color="success"
                       @click="displayBadge = -1">
                    <v-icon>mdi-close</v-icon>
                </v-btn>
            </v-card-actions>
            <v-card-title class="headline">Application Info</v-card-title>
            <v-card-text>
                <v-sheet v-if="displayBadgeData['application_status'] != 'Accepted'"
                         rounded="lg"
                         class="pa-3"
                         elevation="4"
                         color="blue lighten-4">{{ displayBadgeData ? displayBadgeData['application_status'] : '' }}</v-sheet>
                <p v-else
                   class="text-center">
                    <v-btn height=266
                           width=266
                           elevation=4>
                        <qr-code :text="displayBadgeData ? displayBadgeData['qr_data'] : ''"></qr-code>
                    </v-btn>
                </p>
                <v-row>
                    <v-spacer></v-spacer>
                    <v-card-title>{{displayBadgeData | badgeDisplayName}}</v-card-title>
                    <v-spacer></v-spacer>
                </v-row>
                <v-row>
                    <v-spacer></v-spacer>
                    <h3>{{displayBadgeData | badgeDisplayName(true)}}&zwj;</h3>
                    <v-spacer></v-spacer>
                </v-row>
                <v-card-title class="title">{{displayBadgeData && displayBadgeData['badge-type-name']}}</v-card-title>
                <badgePerksRender :description="displayBadgeProduct ? displayBadgeProduct.description : null "
                                  :rewardlist="displayBadgeProduct ? displayBadgeProduct.rewards : null"></badgePerksRender>
                <v-card-title>Addons purchased:</v-card-title>

                <v-card v-for="addon in (displayBadgeProduct ? displayBadgeData.addons : null)"
                        v-bind:key="addon['id']">
                    <v-card-title>
                        <h3 class="black--text">{{getAddonByID(displayBadgeData.context_code,displayBadgeData.badge_type_id,addon['addon_id']).name}}</h3>
                    </v-card-title>
                    <v-card-text>
                        <badgePerksRender :description="getAddonByID(displayBadgeData.context_code,displayBadgeData.badge_type_id,addon['addon_id']).description"
                                          :rewardlist="getAddonByID(displayBadgeData.context_code,displayBadgeData.badge_type_id,addon['addon_id']).rewards"></badgePerksRender>
                    </v-card-text>
                </v-card>
                <p v-if="displayBadgeProduct && displayBadgeData.addons != undefined && displayBadgeData.addons.length == 0">
                    No addons purchased
                </p>
                <v-card-title>Question responses:</v-card-title>
                <formQuestionViewList :questions="displayBadgeQuestions" :responses="displayBadgeData.form_responses" />
            </v-card-text>
        </v-card>
    </v-dialog>
    <v-snackbar v-model="displayImportResult"
                :timeout="16000">
        {{ importResult }}
        <v-btn color="primary"
               text
               @click="clearBadgeRetrievalResult">
            Close
        </v-btn>
    </v-snackbar>
</v-container>
</template>


<script>
import {
    mapGetters,
    mapState,
    mapActions
} from 'vuex';

import VueQRCodeComponent from 'vue-qrcode-component';
import badgePerksRender from '@/components/badgePerksRender.vue';
import badgeSampleRender from '@/components/badgeSampleRender.vue';
import formQuestionViewList from '@/components/formQuestionViewList.vue';

export default {
    components: {
        'qr-code': VueQRCodeComponent,
        badgePerksRender,
        badgeSampleRender,
        formQuestionViewList
    },
    data: () => ({
        promocodeDialog: false,
        promoAppliedDialog: false,
        displayBadge: -1,
        printingBadge: false,
    }),
    computed: {
        ...mapState({
            applications: (state) => state.mydata.applications,
            badges: (state) => state.products.badges,
            questions: (state) => state.products.questions,
            addons: (state) => state.products.addons,
            importResult: (state) => state.mydata.BadgeRetrievalResult,
        }),
        ownedbadgecount() {
            return Object.keys(this.applications).length;
        },
        displayBadgeModal: {
            get() {
                return this.displayBadge != -1;
            },
            set(show) {
                this.displayBadge = show ? 0 : -1;
            }
        },
        displayBadgeData() {
            if (!this.displayBadgeModal) return null;
            return this.applications[this.displayBadge];
        },
        displayBadgeProduct() {
            if (!this.displayBadgeModal) return null;
            let badgeId = this.displayBadgeData.badge_type_id;
            if (this.badges[this.displayBadgeData.context_code] == undefined) {

                return null;
            }
            let result = this.badges[this.displayBadgeData.context_code].find((item) => {
                return item.id == badgeId
            });
            return result;
        },
        displayBadgeQuestions() {
            if (!this.displayBadgeModal) return null;
            let badgeId = this.displayBadgeData.badge_type_id;
            if (this.questions[this.displayBadgeData.context_code] == undefined) {

                return null;
            }
            let result = this.questions[this.displayBadgeData.context_code][badgeId]
            return result;
        },
        displayImportResult() {
            return this.importResult.length > 0;
        },
    },
    methods: {
        ...mapActions('mydata', [
            'retrieveBadges',
            'retrieveSpecificBadge',
            'retrieveTransactionBadges',
            'clearBadgeRetrievalResult',
        ]),
        ...mapActions('cart', [
            'clearCart',
        ]),
        ...mapActions('products', [
            'getContextBadges',
            'getContextQuestions',
            'getContextAddons',
        ]),
        removeBadge() {

        },
        printBadgeInfo() {
            this.printingBadge = true;
            if (this.printingBadge) {
                (function(app) {
                    setTimeout(() => {
                        window.print();
                        setTimeout(() => {
                            app.printingBadge = false;
                            // Also, spin up a function to zoom back out
                            let viewport = document.querySelector('meta[name="viewport"]');
                            let original = viewport.getAttribute('content');
                            let force_scale = `${original  }, maximum-scale=0.99`;
                            viewport.setAttribute('content', force_scale);
                            setTimeout(() => {
                                viewport.setAttribute('content', original);
                            }, 100);
                        }, 1000);
                    }, 30);
                }(this));
            }

        },
        getAddonByID(context_code, badge_type_id, id) {
            var result = {
                "id": 0,
                "display_order": 0,
                "name": "[Loading...]",
                "description": "Loading description, please wait",
                "rewards": null,
                "price": "",
                "payable_onsite": 0,
                "quantity": null,
                "start_date": null,
                "end_date": null,
                "min_age": null,
                "max_age": null,
                "dates_available": "forever to forever",
                "quantity_sold": 0,
                "quantity_remaining": null
            }
            if (undefined == this.addons[context_code])
                return result;
            if (undefined == this.addons[context_code][badge_type_id])
                return result;
            return this.addons[context_code][badge_type_id].find(addon => addon.id == id) || result;
        }
    },
    watch: {
        displayBadge: function(newBadgeId) {
            if (newBadgeId > -1) {
                //Make sure we have context for it
                this.getContextBadges(this.displayBadgeData.context_code);
                this.getContextQuestions(this.displayBadgeData.context_code);
                this.getContextAddons(this.displayBadgeData.context_code);
            }
        }
    },
    created() {
        let {
            query
        } = this.$route;
        if (query.gid != undefined) {
            this.retrieveTransactionBadges(query);
            // Presumably they're here from a Review Order link or the checkout summary page
            // Which *probably* means it was successful, so... clear the cart!
            this.$router.replace({
                ...this.$router.currentRoute,
                query: {}
            });
        }
        if (query.refresh) {
            this.retrieveBadges();
        }
        if (query.context_code) {
            //A specific badge was clicked, load it up
            this.retrieveSpecificBadge(query);
        }
    }
};
</script>
