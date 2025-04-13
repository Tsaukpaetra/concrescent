<template>
    <v-card>
        <v-app-bar style="position: fixed; z-index:2">

            <v-tabs grow background-color="indigo" v-model="step" show-arrows center-active dark>
                <v-tab>
                    Badge Information
                </v-tab>
                <v-tab>
                    Addons
                </v-tab>
                <v-tab>
                    Contact Information
                </v-tab>
                <v-tab>
                    Additional Information
                </v-tab>
                <v-tab>
                    Transactions
                </v-tab>
            </v-tabs>
        </v-app-bar>
        <v-toolbar />
        <v-tabs-items v-model="step">
            <v-container>
                <v-tab-item :value="0">
                    <badgeGenInfo v-model="badgeGenInfoData" :application_name1="currentContext.application_name1"
                        :application_name2="currentContext.application_name2" @valid="setValidGenInfo"
                        :hide_dob="isGroupApp" />
                    <badgeTypeSelector v-model="selectedbadge" :badges="badges"
                        no-data-text="No badges currently available!"
                        :editBadgePriorBadgeId="model.editBadgePriorBadgeId" />
                    <v-expansion-panels>
                        <v-expansion-panel v-if="selectedbadge != null">
                            <v-expansion-panel-header>
                                Selected Badge info:
                                {{ badges[selectedbadge] ? badges[selectedbadge].name : "Nothing yet!" }}
                                {{ isProbablyDowngrading ? "Warning: Possible downgrade!" : "" }}
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <badgePerksRender
                                    :description="badges[selectedbadge] ? badges[selectedbadge].description : ''"
                                    :rewardlist="rewardlist"></badgePerksRender>
                            </v-expansion-panel-content>
                        </v-expansion-panel>
                    </v-expansion-panels>

                    <subBadgeListEditor v-if="isGroupApp" v-model="model.subbadges" />

                    <v-row>
                        <v-col cols="12" sm="6" md="4">
                            <v-text-field label="Display ID" v-model="model.display_id"></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6" md="4">
                            <v-text-field label="time_printed" v-model="model.time_printed"></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6" md="4">
                            <v-text-field label="time_checked_in" v-model="model.time_checked_in"></v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-textarea label="Notes" v-model="model.notes">
                            </v-textarea>
                        </v-col>
                    </v-row>
                </v-tab-item>
                <v-tab-item :value="1">
                    <v-expansion-panels v-model="addonDisplayState" multiple v-if="badgeAddons.length">
                        <h3>Addons currently available for the selected badge:</h3>
                        <v-expansion-panel v-for="addon in badgeAddons" v-bind:key="addon.id">
                            <v-expansion-panel-header>
                                <v-checkbox hide-details multiple :value="addon['id']" v-model="addonsSelected"
                                    :disabled="badgeAddonPriorSelected(addon['id']) || addon.quantity_remaining == 0">
                                    <template slot="label">
                                        <h3 class="black--text">{{ addon.name }}</h3>
                                    </template>
                                </v-checkbox>
                                <template slot="actions">
                                    <h4 text v-if="addon.quantity_remaining">Only
                                        {{ addon.quantity_remaining }}
                                        left!
                                    </h4>
                                    <h4 v-else-if="addon.quantity_remaining == 0">Sold out!</h4>
                                    <v-btn class="ml-5" color="green" dark>{{ addon.price | currency }}</v-btn>
                                    <v-icon class="px-3" color="primary">$expand</v-icon>
                                </template>
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <badgePerksRender :description="addon.description" :rewardlist="addon.rewards">
                                </badgePerksRender>
                            </v-expansion-panel-content>
                        </v-expansion-panel>

                    </v-expansion-panels>
                    <div v-else>
                        <h3>No addons are currently available for the selected badge type. Check back later if they
                            become available!</h3>
                    </div>

                </v-tab-item>

                <v-tab-item :value="2">

                    <v-form ref="fContactInfo" v-model="validContactInfo">
                        <h3>Badge Owner</h3>
                        <v-row>
                            <v-col v-if="hasEventPerm('Contact_Full')">
                                <profileForm v-model="model.contact" readonly />
                            </v-col>
                            <v-col v-else>
                                Not available with current permissions
                            </v-col>
                        </v-row>
                        <div v-if="!isGroupApp">
                            <h3>Notify email</h3>
                            <v-row>
                                <v-col cols="12" sm="6" md="6">
                                    <v-text-field label="Additional Email address to send confirmation to"
                                        v-model="model.notify_email" :rules="RulesEmail"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="6">
                                    <v-checkbox dense hide-details v-model="model.can_transfer">
                                        <template v-slot:label>
                                            <small>Allow badge transfer to the owner of this email.</small>
                                        </template>
                                    </v-checkbox>
                                </v-col>
                            </v-row>

                            <h3>In case of Emergency</h3>
                            <v-row v-if="hasEventPerm('Badge_Ice')">

                                <v-col cols="12" sm="6" md="3">
                                    <v-text-field label="Emergency Contact Name"
                                        v-model="model.ice_name"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <v-text-field label="Relationship" v-model="model.ice_relationship"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <v-text-field label="Email address" v-model="model.ice_email_address"
                                        :rules="RulesEmail"></v-text-field>
                                </v-col>
                                <v-col cols="12" sm="6" md="3">
                                    <v-text-field label="Phone Number" v-model="model.ice_phone_number"
                                        :rules="RulesPhone"></v-text-field>
                                </v-col>
                            </v-row>
                            <v-row v-else>
                                Not available with current permissions
                            </v-row>
                        </div>
                    </v-form>
                </v-tab-item>


                <v-tab-item :value="3">
                    <v-form ref="fAdditionalInfo" v-model="validAdditionalInfo">
                        <formQuestions v-model="model.form_responses" :questions="badgeQuestions"
                            no-data-text="No questions enabled for this badge type." />
                    </v-form>

                </v-tab-item>
                <v-tab-item :value="4">
                    <paymentItemView v-model="model" />

                </v-tab-item>

            </v-container>
        </v-tabs-items>

        <v-dialog v-model="reviewDialog" :fullscreen="$vuetify.breakpoint.xsOnly" scrollable>
            <template v-slot:activator="{ on, attrs }">
                <v-btn :color="applicationStatusData.color" fixed bottom right v-show="model.application_status" faba
                    v-bind="attrs" v-on="on">
                    Review
                    <v-icon>
                        mdi-check-decagram-outline
                    </v-icon>
                </v-btn>
            </template>
            <v-card>
                <v-card-title>Application Review</v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    <v-row v-if="model.context_code == 'S'">
                        <v-col cols="12">
                            <editBadgeApplicationStaffPosition v-model="model.assigned_positions" />
                        </v-col>
                    </v-row>
                    <v-row v-if="isGroupApp && badgeTypeMaxAssignments > 0">
                        <v-col cols="4">
                            <v-text-field label="Assignment Slots approved for" v-model.number="model.assignment_count"
                                min="0" :max="badgeTypeMaxAssignments" type="number"></v-text-field>
                        </v-col>

                        <v-col cols="8">
                            Assignments
                            <v-simple-table>
                                <template v-slot:default>
                                    <thead>
                                        <tr>
                                            <th class="text-left">
                                                Short Code
                                            </th>
                                            <th class="text-left">
                                                Name
                                            </th>
                                            <th class="text-left">
                                                Start
                                            </th>
                                            <th class="text-left">
                                                End
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, ix) in model.assignments" :key="item.id">
                                            <td>{{ item.short_code }}</td>
                                            <td>{{ item.name }}</td>
                                            <td>{{ item.start_time }}</td>
                                            <td>{{ item.end_time }}</td>
                                            <td>
                                                <v-btn dark @click="editAssignment = ix">Edit</v-btn>
                                                <v-btn dark @click="model.assignments.splice(ix, 1)">Delete</v-btn>
                                            </td>
                                        </tr>
                                    </tbody>
                                </template>
                            </v-simple-table>
                            <v-btn @click="addAssignment">Add</v-btn>
                        </v-col>
                    </v-row>
                    <v-dialog scrollable :value="editAssignment != null" @input="editAssignment = null">

                        <v-card>
                            <v-card-title class="text-h5 grey lighten-2">
                                Edit Assignment
                            </v-card-title>

                            <v-card-text>
                                <editApplicationAssignment v-if="editAssignment != null"
                                    v-model="model.assignments[editAssignment]" :application="model">
                                </editApplicationAssignment>
                            </v-card-text>

                            <v-divider></v-divider>

                            <v-card-actions>
                                <v-spacer></v-spacer>
                                <v-btn color="primary" text @click="editAssignment = null">Ok
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-dialog>
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-checkbox v-model="sendUpdate" label="Send update" />
                    <v-spacer />
                    <v-select v-model="newApplication_status" :items="appStatusNextList" :hint="appStatusNext.Text"
                        item-text="actionText" label="Action to take">
                    </v-select>
                    <v-btn :color="appStatusNext.color" @click="submitReview(newApplication_status)">
                        Go
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-card>
</template>

<script>
import {
    mapState,
    mapGetters,
    mapActions
} from 'vuex';
function nullIfEmptyOrZero(inValue) {
    if (inValue == 0 || inValue == '' || inValue == null) return null;
    return inValue;
}


import badgeGenInfo from '@/components/badgeGenInfo.vue';
import formQuestions from '@/components/formQuestions.vue';
import badgeTypeSelector from '@/components/badgeTypeSelector.vue';
import subBadgeListEditor from '@/components/subBadgeListEditor.vue';
import badgePerksRender from '@/components/badgePerksRender.vue';
import profileForm from '@/components/profileForm.vue';
import paymentItemView from '@/components/paymentItemView.vue';
import editBadgeApplicationStaffPosition from '@/components/editBadgeApplicationStaffPosition.vue';
import editApplicationAssignment from './editApplicationAssignment.vue';

export default {
    props: ['value'],
    data() {
        return {
            step: 0,
            loading: false,
            reviewDialog: false,
            editAssignment: null,
            validGenInfo: false,
            validContactInfo: false,
            validAdditionalInfo: false,
            sendUpdate: false,
            badgeGenInfoData: {
                real_name: null,
                fandom_name: null,
                name_on_badge: null,
                date_of_birth: null,
            },
            model: {
            },
            newApplication_status: null,
            addonsSelected: [],
            selectedbadge: null,
            modelString: '',

            RulesRequired: [
                (v) => !!v || 'Required',
            ],
            RulesName: [
                (v) => !!v || 'Name is required',
                (v) => (v && v.length <= 500) || 'Name must be less than 500 characters',
            ],
            RulesNameFandom: [

                (v) => (v == '' || (v && v.length <= 255)) || 'Name must be less than 255 characters',
            ],
            RulesNameDisplay: [
                (v) => ((this.fandom_name.length < 1) || (this.fandom_name.length > 0 && v != '')) || 'Please select a display type',
            ],
            RulesEmail: [
                (v) => !v || /.+@.+\..+/.test(v) || 'E-mail must be valid',
            ],
            RulesPhone: [
                (v) => !v || v.length > 6 || 'Phone number too short',
                /* v =>  !v || /^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/.test(v) || 'Phone number should be valid' */
            ],

            addonDisplayState: [],
        };
    },
    computed: {
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
            'LoggedInName': 'getLoggedInName',
            'hasEventPerm': 'hasEventPerm'
        }),
        ...mapGetters('products', {
            badgeContexts: 'badgeContexts',
            currentContext: 'selectedbadgecontext',
            products: 'contextBadges',
            questions: 'contextQuestions',
            addonsAvailable: 'contextAddons',
        }),
        rewardlist() {
            // return this.$options.filters.split_carriagereturn(this.badges[this.selectedBadge].rewards);
            return this.badges[this.selectedbadge] ? this.badges[this.selectedbadge].rewards : '';
        },
        badges() {
            // Crude clone
            if (this.products == undefined) return [];
            let badges = structuredClone(this.products);
            // // First, do we have a date_of_birth?
            // const bday = new Date(this.badgeGenInfoData.date_of_birth);
            // if (this.badgeGenInfoData.date_of_birth && bday) {
            //     badges = badges.filter((badge) => {
            //         if (!(
            //                 (badge['min_birthdate'] != null && bday < new Date(badge['min_birthdate'])) ||
            //                 (badge['max_birthdate'] != null && bday > new Date(badge['max_birthdate']))
            //             )) {
            //             return badge;
            //         }
            //     });
            // }

            // Are we editing a badge?
            if (this.id > -1) {
                const oldBadge = badges.find((badge) => badge.id == this.model.editBadgePriorBadgeId);
                if (oldBadge != undefined) {
                    const oldPrice = parseFloat(oldBadge.price);
                    // Determine price difference
                    badges.forEach((badge) => {
                        badge.originalprice = badge.price;
                        badge.price = Math.max(parseFloat(badge.price) - oldPrice, 0).toFixed(2);
                    });
                }
            }

            badges.sort((a, b) => a.order - b.order);
            return badges;
        },
        context_code() {
            return this.model.context_code;
        },
        badgeOk() {
            return this.validGenInfo &&
                this.validContactInfo &&
                this.validAdditionalInfo
        },
        isUpdatingItem() {
            return (this.cartIx != null && this.cartIx > -1) || (this.id != null && this.id > -1);
        },
        isProbablyDowngrading() {
            if (!this.isUpdatingItem) {
                return false;
            }

            const oldBadge = this.badges.find((badge) => badge.id == this.model.editBadgePriorBadgeId);
            const selectedBadge = this.badges[this.selectedbadge];
            return typeof oldBadge !== 'undefined' &&
                typeof selectedBadge !== 'undefined' &&
                parseFloat(oldBadge.originalprice) > parseFloat(selectedBadge.originalprice);
        },
        badgeQuestions() {
            // Todo: Filter by badge context
            const badgeId = typeof this.badges[this.selectedbadge] === 'undefined' ? '' : this.badges[this.selectedbadge].id.toString();
            if (!(badgeId in this.questions)) return {};
            // Filter out the ones that don't apply to this badge
            var result = this.questions[badgeId];

            // Sort it out
            result.sort((a, b) => a.order - b.order);
            return result;
        },
        badgeAddons() {
            // Todo: Filter by badge context
            const badgeId = typeof this.badges[this.selectedbadge] === 'undefined' ? '' : this.badges[this.selectedbadge].id.toString();
            // Do we have questions at all for this badge?
            if (!(badgeId in this.addonsAvailable)) return {};
            // Filter out the ones that don't apply to this badge
            let result = this.addonsAvailable[badgeId];

            // First, do we have a date_of_birth?
            // const bday = new Date(this.date_of_birth);
            // if (this.date_of_birth && bday) {
            //     result = result.filter((badge) => {
            //         if (!(
            //                 (badge['min-birthdate'] != null && bday < new Date(badge['min-birthdate'])) ||
            //                 (badge['max-birthdate'] != null && bday > new Date(badge['max-birthdate']))
            //             )) {
            //             return badge;
            //         }
            //     });
            // }
            /// /Apply logic to required
            // result.forEach(function(question){
            //  question.isRequired = question.required == '*' || question.required.includes(badgeId)
            // })

            // Sort it out
            result.sort((a, b) => a.order - b.order);
            return result;
        },
        badgeTypeMaxAssignments() {
            const selectedBadge = this.badges[this.selectedbadge];
            if (selectedBadge) {
                return selectedBadge.max_assignment_count
            }
            return 0;
        },
        applicationStatusMap() {
            if (this.isGroupApp) {
                return {
                    'InProgress': {
                        value: 'InProgress',
                        color: 'indigo',
                        text: 'Draft',
                        actionText: 'Revert to Draft',
                        nextStatus: [
                            'Submitted',
                            'Cancelled',
                            'Rejected',
                            'PendingAcceptance',
                            'Accepted',
                            'Waitlisted',
                        ]
                    },
                    'Submitted': {
                        value: 'Submitted',
                        color: 'purple accent-2',
                        text: 'Newly submitted',
                        actionText: 'Revert to Submitted',
                        nextStatus: [
                            'Cancelled',
                            'Rejected',
                            'Waitlisted',
                            'PendingAcceptance',
                        ]
                    },
                    'Cancelled': {
                        value: 'Cancelled',
                        color: 'red',
                        text: 'Applicant self-cancelled',
                        actionText: 'Cancel',
                        nextStatus: [
                            'Submitted',
                        ]
                    },
                    'Rejected': {
                        value: 'Rejected',
                        color: 'red',
                        text: 'Rejected',
                        actionText: 'Reject',
                        nextStatus: [
                            'Submitted',
                        ]
                    },
                    'PendingAcceptance': {
                        value: 'PendingAcceptance',
                        color: 'yellow',
                        text: 'Accepted, waiting for them to confirm',
                        actionText: 'Accept',
                        nextStatus: [
                            'Cancelled',
                            'Waitlisted',
                            'Accepted',
                            'Submitted'
                        ]
                    },
                    'Waitlisted': {
                        value: 'Waitlisted',
                        color: 'gray',
                        text: 'Waitlisted for consideration',
                        actionText: 'Waitlist',
                        nextStatus: [
                            'Rejected',
                            'PendingAcceptance',
                        ]
                    },
                }

            } else {

                return {
                    'InProgress': {
                        value: 'InProgress',
                        color: 'indigo',
                        text: 'Draft',
                        actionText: 'Revert to Draft',
                        nextStatus: [
                            'Submitted',
                            'Cancelled',
                            'Rejected',
                            'PendingAcceptance',
                            'Waitlisted',
                            'Onboarding',
                            'Active',
                            'Terminated',
                        ]
                    },
                    'Submitted': {
                        value: 'Submitted',
                        color: 'purple accent-2',
                        text: 'Newly submitted',
                        actionText: 'Revert to Submitted',
                        nextStatus: [
                            'Cancelled',
                            'Rejected',
                            'Waitlisted',
                            'PendingAcceptance',
                        ]
                    },
                    'Cancelled': {
                        value: 'Cancelled',
                        color: 'red',
                        text: 'Applicant self-cancelled',
                        actionText: 'Cancel',
                        nextStatus: [
                            'Submitted',
                        ]
                    },
                    'Rejected': {
                        value: 'Rejected',
                        color: 'red',
                        text: 'Rejected',
                        actionText: 'Reject',
                        nextStatus: [
                            'Submitted',
                        ]
                    },
                    'PendingAcceptance': {
                        value: 'PendingAcceptance',
                        color: 'yellow',
                        text: 'Accepted, waiting for them to confirm',
                        actionText: 'Accept',
                        nextStatus: [
                            'Cancelled',
                            'Waitlisted',
                            'Onboarding',
                            'Submitted'
                        ]
                    },
                    'Waitlisted': {
                        value: 'Waitlisted',
                        color: 'gray',
                        text: 'Waitlisted for consideration',
                        actionText: 'Waitlist',
                        nextStatus: [
                            'Rejected',
                            'PendingAcceptance',
                        ]
                    },
                    'Onboarding': {
                        value: 'Onboarding',
                        color: 'blue',
                        text: 'Accepted, onboarding in progress',
                        actionText: 'Begin Onboarding',
                        nextStatus: [
                            'Rejected',
                            'Terminated',
                            'Active',
                        ]
                    },
                    'Active': {
                        value: 'Active',
                        color: 'green',
                        text: 'Accepted, active staff',
                        actionText: 'Mark Active',
                        nextStatus: [
                            'Terminated',
                        ]
                    },
                    'Terminated': {
                        value: 'Terminated',
                        color: 'black',
                        text: 'No longer welcome here',
                        actionText: 'Terminate',
                        nextStatus: []
                    },
                }
            }
        },
        applicationStatusList() {
            return Object.values(this.applicationStatusMap);
        },
        applicationStatusData() {
            return this.applicationStatusMap[this.model.application_status] || {};
        },
        appStatusNextList() {
            if (this.applicationStatusData != undefined && this.applicationStatusData.nextStatus != undefined) {

                var result = this.applicationStatusData.nextStatus
                    .map((statusKey) => this.applicationStatusMap[statusKey]);

                result.unshift({
                    value: null,
                    color: 'primary',
                    text: 'Just save',
                    actionText: 'Keep the same status',
                    nextStatus: []
                });
                return result;
            }
            return [];
        },
        appStatusNext() {
            if (this.newApplication_status && this.applicationStatusMap[this.newApplication_status]) {
                return this.applicationStatusMap[this.newApplication_status];
            }
            return {
                value: null,
                color: 'primary',
                text: 'Just save',
                actionText: 'Keep the same status',
                nextStatus: []
            }
        },
        isGroupApp() {
            if (this.currentContext == undefined) return true;
            return this.currentContext.id > 0;
        },
        hasSubBadges() {
            if (this.selectedBadge != undefined) {
                return this.selectedBadge.max_applicant_count > 0
            }
            return false;
        },
    },
    watch: {
        selectedbadge(val) {
            //Check if we can
            if(this.loading){
                return;
            }
            var newId = typeof this.badges[val] === 'undefined' ? this.model.badge_type_id : this.badges[val].id;
            if (newId != null) {
                this.model.badge_type_id = newId;
            }

        },

        model: {
            handler(newData) {
                if(this.loading){
                    return;
                }
                newData.display_id = nullIfEmptyOrZero(newData.display_id);
                // console.log('emitting model', structuredClone(newData));
                this.$emit('input', newData);
            },
            deep: true,
            // immediate: true
        },
        value: {
            async handler(newValue) {
                this.loading = true;
                this.model = newValue;
                //If we're being reset, just stop here
                if (newValue.context_code == undefined) {
                    return;
                }
                this.badgeGenInfoData.real_name = newValue.real_name;
                this.badgeGenInfoData.fandom_name = newValue.fandom_name;
                this.badgeGenInfoData.name_on_badge = newValue.name_on_badge;
                this.badgeGenInfoData.date_of_birth = newValue.date_of_birth;
                await this.$store.dispatch('products/selectContext', newValue.context_code);
                //Determine what index the badge currently is
                this.selectedbadge = this.badges.findIndex((badge) => badge.id == newValue.badge_type_id);

                this.checkBadge();
                if (newValue.addons)
                    this.addonsSelected = newValue.addons.map(addon => addon['addon_id']);
                this.loading = false;
            },
            deep: true,
            immediate: true
        },
        addonsSelected(newSelects) {
            var check = newSelects.map(id => {
                return {
                    'addon_id': id
                }
            });
            //stupid hack
            if(JSON.stringify(check) != JSON.stringify(this.model.addons)){
                this.model.addons = check;
            }
        },
        badgeGenInfoData: {
            handler(newData) {
                Object.keys(newData).forEach((key) => {
                        this.model[key] = newData[key];
                    }
                );
                this.checkBadge();
            },
            deep: true
        },
        newApplication_status(newStatus) {
            if (newStatus != null)
                this.sendUpdate = true;
        },
        'model.assigned_positions': function (newPositions) {
            if (newPositions != null)
                this.sendUpdate = true;
        },
    },
    methods: {

        saveBDay(date) {
            this.$refs.menuBDay.save(date);
            this.date_of_birth = this.date_of_birth;
        },
        checkBadge() {
            // Ensure only applicable badges are selected!
            if (this.badges.length > 0) {
                const bid = this.model.badge_type_id;
                let badge = this.badges.findIndex((badge) => badge.id == bid);
                if (badge == -1) {
                    badge = 0;
                }
                this.selectedbadge = badge;
            }

            // Ensure only applicable badge addons are selected!
            const {
                badgeAddons
            } = this;
            if (badgeAddons.length > 0) {
                if (typeof this.addonsSelected.filter === 'function') {
                    this.addonsSelected = this.addonsSelected.filter((aid) => undefined != badgeAddons[aid]);
                }
            }
        },
        badgeAddonPriorSelected(addonid) {
            if (this.model.editBadgePriorAddons == undefined) return false;
            return this.model.editBadgePriorAddons.indexOf(addonid) != -1;
        },
        setValidGenInfo(isValid) {
            this.validGenInfo = isValid;
        },
        submitReview: function (newStatus) {
            if (newStatus != null)
                this.model.application_status = newStatus;
            console.log('submitting review', this.model.application_status);
            var that = this;
            this.$nextTick(function () {
                that.$emit('save', that.sendUpdate);
            })
        },

        addAssignment() {
            this.$set(this.model.assignments, this.model.assignments.length, {
                id: Math.floor(-10000 * Math.random()),
                "application_id": this.model.id,
                "location_id": 0,
                "category_id": 0,
                "start_time": null,
                "end_time": null,
                "real_name": this.model.real_name,
                "fandom_name": this.model.fandom_name,
                "name_on_badge": this.model.name_on_badge,
                "application_status": this.model.application_status,
                "display_name": this.model.display_name

            });
            this.editAssignment = this.model.assignments.length - 1;


        },
    },
    components: {
        badgeGenInfo,
        badgeTypeSelector,
        formQuestions,
        badgePerksRender,
        profileForm,
        paymentItemView,
        editBadgeApplicationStaffPosition,
        subBadgeListEditor,
        editApplicationAssignment
    },
    created() {
        // this.loadBadge(this.value);
    },
};
</script>
