<template>
<v-container :class="{'pa-2':true,'printing':!printingBadge}"
             fluid>
    <v-row>
        <p>
            <i v-if="!ownedbadgecount">You have no applications. Log in, or submit an application.</i>
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
                    <v-btn icon v-if="isLoggedIn"
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
                <v-card-actions v-if="badge.assignments.length" class="flex-column align-start">
                        <v-btn text block class="justify-start px-0 text-none"
                            @click="toggleAssignmentExpansion(badge.id)">
                            <v-icon color="grey darken-1">mdi-application</v-icon>
                            <div class="text-truncate pl-1 text-body-2 text--primary">
                                <v-tooltip top open-on-hover>
                                    <template v-slot:activator="{ on, attrs }">
                                        <span v-bind="attrs" v-on="on">
                                            {{ getBadgeAssignmentCountActual(badge) }} Assignment slot{{ getBadgeAssignmentCountActual(badge)!= 1 ? 's':'' }}
                                        </span>
                                    </template>
                                    <span>{{ badge.assignment_count }} slots approved, {{ badge.assignments.length }} assigned</span>
                                </v-tooltip>
                            </div>
                            <v-spacer></v-spacer>
                            <v-icon color="grey darken-1">{{ expandedBadgeAssignments[badge.id] ? 'mdi-chevron-up' :
                                'mdi-chevron-down' }}</v-icon>
                        </v-btn>

                        <v-expand-transition>
                            <div v-show="expandedBadgeAssignments[badge.id]" class="w-full pl-7 pr-4 pb-2">
                                <div v-for="(assignment, i) in badge.assignments" :key="i"
                                    class="d-flex align-center py-2 text-caption border-bottom-light">
                                    <!-- 1. Category Color Pill with Tooltip -->
                                    <v-tooltip bottom open-on-hover>
                                        <template v-slot:activator="{ on, attrs }">
                                            <span v-bind="attrs" v-on="on"
                                                class="category-pill text-center font-weight-bold mr-2 px-1"
                                                :class="getTextContrastClass(assignment.category_color)"
                                                :style="{ backgroundColor: assignment.category_color }">
                                                {{ assignment.location_short_code }}
                                            </span>
                                        </template>
                                        <span>{{ assignment.category_name }} – {{ assignment.location_name }}</span>
                                    </v-tooltip>

                                    <!-- 2. Concise Date, Time, and Duration with Full-Date Tooltip -->
                                    <v-tooltip bottom open-on-hover>
                                        <template v-slot:activator="{ on, attrs }">
                                            <span v-bind="attrs" v-on="on" class="text--secondary">
                                                {{ formatConciseTime(assignment.start_time, assignment.end_time) }}
                                            </span>
                                        </template>
                                        <span>
                                            Start: {{ assignment.start_time }}<br>
                                            End: {{ assignment.end_time }}
                                        </span>
                                    </v-tooltip>
                                </div>
                            </div>

                        </v-expand-transition>
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
                <v-btn color="red lighten-1" v-if="isLoggedIn"
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
                <div v-if="isLoggedIn">
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

                <v-card-title>Assignment slots:</v-card-title>

                        <div class="row no-gutters font-weight-bold pb-2 text-caption text-uppercase text--secondary">
                            <v-col cols="6">Category & Location</v-col>
                            <v-col cols="4">Date & Time Interval</v-col>
                            <v-col cols="2" class="text-right">Duration</v-col>
                        </div>

                        <v-row v-for="(assignment, i) in displayBadgeData.assignments" :key="i" no-gutters
                            class="align-center py-2 printable-row text-body-2">
                            <!-- Column 1: Explicit Category Tag & Names -->
                            <v-col cols="12" sm="6" class="d-flex align-center pr-2 mb-1 mb-sm-0">
                                <span class="print-pill text-center font-weight-bold mr-2 px-2"
                                    :class="getTextContrastClass(assignment.category_color)"
                                    :style="{ backgroundColor: assignment.category_color }">
                                    {{ assignment.location_short_code }}
                                </span>
                                <div class="text">
                                    <span class="font-weight-medium d-block text-caption">{{ assignment.category_name
                                        }}</span>
                                    <span class="text--secondary text-caption d-block line-height-tight">{{
                                        assignment.location_name }}</span>
                                </div>
                            </v-col>

                            <!-- Column 2: Explicit Full Dates -->
                            <v-col cols="12" sm="4" class="pr-2 mb-1 mb-sm-0">
                                <div class="d-flex flex-column text-caption">
                                    <div><span class="text--secondary">Start:</span> {{ assignment.start_time }}</div>
                                    <div><span class="text--secondary">End:</span> {{ assignment.end_time }}</div>
                                </div>
                            </v-col>

                            <!-- Column 3: Concise Duration Segment -->
                            <v-col cols="12" sm="2" class="text-sm-right">
                                <v-chip label small outlined color="grey darken-1" class="font-weight-medium">
                                    {{ calculateDuration(assignment.start_time, assignment.end_time) }}
                                </v-chip>
                            </v-col>
                        </v-row>
                        <p v-if="getBadgeAssignmentCountActual(displayBadgeData) == 0">
                            No location slots assigned
                        </p>

      
                <v-card-title>Question responses:</v-card-title>
                <formQuestionViewList :questions="displayBadgeQuestions" :responses="displayBadgeData.form_responses" />
                </div>
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
    <v-snackbar :value="!isLoggedIn" left :timeout="-1">
        You're not logged in, information on this page may be incomplete or inaccurate.
        <v-btn color="primary" small :to="{ path: '/login', query: { returnTo: $route.fullPath } }">Log in</v-btn>
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
        expandedBadgeAssignments:{},
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
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
        }),
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
            'retrieveSpecificApplication',
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
        },
        toggleAssignmentExpansion(id) {
            // Explicitly toggle the state reactively using Vue.set / this.$set
            this.$set(this.expandedBadgeAssignments, id, !this.expandedBadgeAssignments[id]);
        },
        getBadgeAssignmentCountActual(badge){
            return Math.max(badge.assignment_count, badge.assignments.length);
        },
        formatConciseTime(startStr, endStr) {
            const start = new Date(startStr.replace(/-/g, '/')); // Safe cross-browser parse
            const end = new Date(endStr.replace(/-/g, '/'));
            
            // 1. Format concisely (e.g., "Apr 12, 1:30 AM - 5:30 AM")
            const dateOptions = { month: 'short', day: 'numeric' };
            const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
            
            const formattedDate = start.toLocaleDateString('en-US', dateOptions);
            const startTime = start.toLocaleTimeString('en-US', timeOptions);
            const endTime = end.toLocaleTimeString('en-US', timeOptions);
            
            // 2. Compute duration in hours
            const diffMs = end - start;
            const diffHours = Math.round((diffMs / (1000 * 60 * 60)) * 10) / 10; 
            
            return `${formattedDate} (${startTime} - ${endTime}) [${diffHours} hrs]`;
        },
        calculateDuration(startStr, endStr) {
            const start = new Date(startStr.replace(/-/g, '/'));
            const end = new Date(endStr.replace(/-/g, '/'));
            const diffMs = end - start;
            const diffHours = Math.round((diffMs / (1000 * 60 * 60)) * 10) / 10;
            return `${diffHours} ${diffHours === 1 ? 'Hour' : 'Hours'}`;
        },
        getTextContrastClass(hexColor) {
            if (!hexColor) return 'white--text';
            
            // Strip the '#' character if it exists
            const hex = hexColor.replace('#', '');
            
            // Convert hex values to RGB integers
            const r = parseInt(hex.substring(0, 2), 16);
            const g = parseInt(hex.substring(2, 4), 16);
            const b = parseInt(hex.substring(4, 6), 16);
            
            // Calculate brightness using standard YIQ luminance formula 
            // Threshold is 128 (middle of 0-255 range)
            const yiq = (r * 299 + g * 587 + b * 114) / 1000;
            //console.log('decision color', hexColor,yiq,yiq >= 128  )
            return yiq >= 128 ? 'black--text' : 'white--text';
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
            //A specific application was clicked, load it up
            this.retrieveSpecificApplication(query);
        }
    }
};
</script>
