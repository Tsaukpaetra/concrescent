<template>
<v-tabs-items :value="subTabIx"
              touchless>
    <v-tab-item value="Badges">
        <badgeSearchList apiPath="Attendee/Badge"
                         context_code="A"
                         :AddHeaders="listAddHeaders"
                         :RemoveHeaders="listRemoveHeaders"
                         :isEditingItem="bEdit || bPrint"
                         :actions="listActions"
                         showExport
                         @edit="editBadge" />

    </v-tab-item>
    <v-dialog v-model="bEdit"
              fullscreen
              scrollable
              hide-overlay>
        <v-card tile>
            <v-card-title class="pa-0">
                <v-toolbar dark
                           flat
                           color="primary">
                    <v-btn icon
                           dark
                           @click="bEdit = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                    <v-toolbar-title>Edit Badge</v-toolbar-title>
                    <v-spacer></v-spacer>
                    <v-toolbar-items>
                        <v-menu offset-y
                                open-on-hover>
                            <template v-slot:activator="{ on, attrs }">
                                <v-btn :color="bModified ? 'green' : 'primary'"
                                       dark
                                       v-bind="attrs"
                                       v-on="on">
                                    <v-icon>mdi-content-save</v-icon>
                                </v-btn>
                            </template>
                            <v-list>
                                <v-list-item @click="saveBadge(true)">
                                    <v-list-item-title>
                                        Save and send status email
                                    </v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="saveBadge(false)">
                                    <v-list-item-title>
                                        Save only
                                    </v-list-item-title>
                                </v-list-item>
                            </v-list>
                        </v-menu>
                    </v-toolbar-items>
                </v-toolbar>

            </v-card-title>
            <v-card-text class="pa-0">
                <editBadgeAdmin v-model="bSelected" />
            </v-card-text>
        </v-card>
    </v-dialog>
    <v-tab-item value="Types">
        <orderableList apiPath="Attendee/BadgeType"
                       :AddHeaders="btAddHeaders"
                       :actions="btActions"
                       :footerActions="btFooterActions"
                       :isEditingItem="btDialog"
                       @edit="editBadgeType"
                       @create="createBadgeType" >
            <template v-slot:[`item.active`]="{ item }">
                <cell-toggle v-model="item.active" @input="tSetActive(item.id, $event)"></cell-toggle>
            </template>
        </orderableList>

        <v-dialog v-model="btDialog"
                  scrollable
                  persistent>

            <v-card>
                <v-card-title class="headline">Edit Badge Type</v-card-title>
                <v-divider></v-divider>
                <v-card-text>

                    <badgeTypeForm v-model="btSelected" />
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="default"
                           @click="btDialog = false">Cancel</v-btn>
                    <v-btn color="primary"
                           @click="saveBadgeType">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-tab-item>
    <v-tab-item value="Questions">
        <formQuestionEditList context_code="A" />
    </v-tab-item>
    <v-tab-item value="Promos">

        <simpleList apiPath="Attendee/PromoCode"
                    :isEditingItem="pEdit"
                    :AddHeaders="pAddHeaders"
                    :actions="btActions"
                    :footerActions="btFooterActions"
                    show-expand
                    @edit="editPromoCode"
                    @create="createPromoCode">

            <template v-slot:[`item.discount`]="{ item }">
                {{item.is_percentage ? "":"$"}}
                {{item.discount}}
                {{item.is_percentage ? "%":""}}
            </template>
            <template v-slot:[`item.active`]="{ item }">
                <cell-toggle v-model="item.active" @input="pSetActive(item.id, $event)"></cell-toggle>
            </template>
            <template v-slot:expanded-item="{ headers, item }">
                <td :colspan="headers.length">
                    <v-container flex>
                        <simpleList :apiPath="'Attendee/PromoCode/'+ item.id + '/Purchase'"
                                    :headerKey="{
                                        text: 'ID',
                                        align: 'start',
                                        value: 'id',
                                    }"
                                    :AddHeaders="paAddHeaders"
                                    :RemoveHeaders="paRemoveHeaders"
                                    :actions="asActions"
                                    @edit="editBadge">
                            <template v-slot:[`item.id`]="{ item }">
                                <v-tooltip right>
                                    <template v-slot:activator="{ on, attrs }">
                                        <span v-bind="attrs"
                                            v-on="on">
                                            {{item.context_code}}{{item.display_id}}</span>
                                    </template>
                                    {{item.id}}
                                </v-tooltip>
                            </template>
                        </simpleList>
                    </v-container>
                </td>
            </template>
        </simpleList>
        <v-dialog v-model="pEdit"
                  scrollable>

            <v-card>
                <v-card-title class="headline">Edit Promo Code</v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    <promoCodeForm v-model="pSelected"
                                   :badge_types="contextBadges" />
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="default"
                           @click="pEdit = false">Cancel</v-btn>
                    <v-btn color="primary"
                           @click="savePromoCode">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-tab-item>

    <v-tab-item value="Addons">

        <orderableList apiPath="Attendee/Addon"
                    :isEditingItem="dEdit"
                    :AddHeaders="dAddHeaders"
                    :actions="btActions"
                    :footerActions="btFooterActions"
                    internalKey="id"
                    show-expand
                    @edit="editAddon"
                    @create="createAddon">

            <template v-slot:[`item.discount`]="{ item }">
                {{item.is_percentage ? "":"$"}}
                {{item.discount}}
                {{item.is_percentage ? "%":""}}
            </template>
            <template v-slot:[`item.active`]="{ item }">
                <cell-toggle v-model="item.active" @input="aSetActive(item.id, $event)"></cell-toggle>
            </template>
            <template v-slot:expanded-item="{ headers, item }">
                <td :colspan="headers.length">
                    <v-container flex>
                        <simpleList :apiPath="'Attendee/Addon/'+ item.id + '/Purchase'"
                                    :headerKey="{
                                        text: 'ID',
                                        align: 'start',
                                        value: 'attendee_id',
                                    }"
                                    :AddHeaders="asAddHeaders"
                                    :RemoveHeaders="asRemoveHeaders"
                                    :actions="asActions"
                                    @edit="editBadgeFromAddon">
                        </simpleList>
                    </v-container>
                </td>
            </template>
        </orderableList>
        <v-dialog v-model="dEdit"
                  scrollable>

            <v-card>
                <v-card-title class="headline">Edit Addon</v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    <addonTypeForm v-model="dSelected"
                                   :badge_types="contextBadges" />
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="default"
                           @click="dEdit = false">Cancel</v-btn>
                    <v-btn color="primary"
                           @click="saveAddon">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-tab-item>
    <v-tab-item value="Notifications">

        <simpleList apiPath="Mail/Template/A"
                    :isEditingItem="eEdit"
                    :headerKey="{text:'Name',align:'start',value:'name'}"                    
                    internalKey="name"
                    :AddHeaders="eAddHeaders"
                    :actions="eActions"
                    :footerActions="btFooterActions"
                    @edit="editEmailTemplate"
                    @create="createEmailTemplate"
                    @delete="deleteEmailTemplate"
                    @update:results="eExistingTemplates = $event">

            <template v-slot:[`item.name`]="{ item }">
                <v-tooltip right>
                        <template v-slot:activator="{ on, attrs }">
                            <span v-bind="attrs" v-on="on">
                            {{  eNameTranslation[item.name]?.text ?? item.name }}</span>

                        </template>
                        {{ eNameTranslation[item.name]?.tip ?? 'Custom email' }}
                    </v-tooltip>
                </template>
                <template v-slot:[`item.active`]="{ item }">
                <cell-toggle v-model="item.active" @input="eSetActive(item.name, $event)"></cell-toggle>
            </template>
        </simpleList>
        <v-dialog v-model="eEdit"
                  scrollable>

            <v-card>
                <v-card-title class="headline">Edit Email template</v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    <EmailTemplateEditor v-model="eSelected"
                                   :suggestedNames="eNameTranslation" 
                                   :allowSetName="eIsNew" 
                                   :existingNames="eExistingTemplateNames"
                                   :templateData="bSelected"/>
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-btn color="error"
                           @click="deleteEmailTemplate">Reset</v-btn>
                    <v-spacer></v-spacer>
                    <v-tooltip top>
                        <template v-slot:activator="{ on, attrs }">
                            <v-btn color="primary"
                                v-bind="attrs"
                                v-on="on"
                                @click="eloadBadgeDataDialog = true">
                                <v-icon>mdi-briefcase-upload</v-icon>
                            </v-btn>
                        </template>
                        <span>Load Preview Data</span>
                    </v-tooltip>
                    <v-spacer></v-spacer>
                    <v-btn color="default"
                           @click="eEdit = false">Cancel</v-btn>
                    <v-btn color="primary"
                           @click="saveEmailTemplate">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="eDelete" max-width="500" persistent>
            <v-card>
                <v-card-title class="headline"> Confirm Reset Action </v-card-title>

                <v-card-text>
                    <v-alert type="warning" prominent class="mb-4">
                        Resetting does not delete prior messages based on the selected
                        template, but will impact the re-rendering of the recorded message
                        data.
                    </v-alert>
                    Are you sure you want to proceed with the reset?
                </v-card-text>

                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="darken-1" @click="eDelete = false"> Cancel </v-btn>
                    <v-btn color="warning darken-1" @click="eDeleteConfirmed">
                        Proceed
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog v-model="eloadBadgeDataDialog">
            <v-card>
                <v-card-title>Load Preview Data</v-card-title>
                <v-divider></v-divider>
                <v-card-text>

                    <v-row>
                        <v-col cols="12"
                            sm="6"
                            md="3">
                            <v-text-field label="ID"
                                        v-model="eloadBadgeDataID"></v-text-field>
                        </v-col>
                    </v-row>
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-spacer />
                    <v-btn color="blue darken-1"
                        @click="eloadBadgeDataDialog = false">
                        Cancel
                    </v-btn>
                    <v-btn color="blue darken-1"
                        @click="eloadBadgeData">
                        Load
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>


    </v-tab-item>
    <v-dialog v-model="loading"
              width="200"
              height="200"
              close-delay="1200"
              content-class="elevation-0"
              persistent>
        <v-card-text class="text-center overflow-hidden">
            <v-progress-circular :size="150"
                                 class="mb-0"
                                 indeterminate />
        </v-card-text>
    </v-dialog>
</v-tabs-items>
<!-- <v-container fluid
             fill-height>

    <v-row>
        <v-col align-self="start">
        </v-col>
    </v-row>
</v-container> -->
</template>
<script>
import {
    mapActions,
    mapGetters
} from 'vuex';
import admin from '../../api/admin';
import {
    debounce
} from '@/plugins/debounce';
import { subTabEditorByQueryIdMixin } from '@/plugins/subTabEditorByQueryId.js';
import badgeSearchList from '@/components/badgeSearchList.vue';
import orderableList from '@/components/orderableList.vue';
import simpleList from '@/components/simpleList.vue';
import badgeTypeForm from '@/components/badgeTypeForm.vue';
import promoCodeForm from '@/components/promoCodeForm.vue';
import addonTypeForm from '@/components/addonTypeForm.vue';
import formQuestionEditList from '@/components/formQuestionEditList.vue';
import editBadgeAdmin from '@/components/editBadgeAdmin.vue';
import cellToggle from '@/components/datagridcell/toggleValue.vue';
import EmailTemplateEditor from '../../components/EmailTemplateEditor.vue';

export default {
    components: {
        badgeSearchList,
        orderableList,
        simpleList,
        badgeTypeForm,
        promoCodeForm,
        addonTypeForm,
        formQuestionEditList,
        editBadgeAdmin,
        cellToggle,
        EmailTemplateEditor
    },
    mixins: [subTabEditorByQueryIdMixin],
    props: [
        'subTabIx'
    ],
    data: () => ({
        subTabs: [
            {
                key: 'Badges',
                text: 'Badges',
                title: 'Badges',
                editor: {
                    editObject: 'bSelected',
                    editFunction: 'editBadge',
                    dialogToggle: 'bEdit'
                }
            },
            {
                key: 'Types',
                text: 'Types',
                title: 'Types',
                editor: {
                    editObject: 'btSelected',
                    editFunction: 'editBadgeType',
                    dialogToggle: 'btDialog'
                }
            },
            {
                key: 'Questions',
                text: 'Questions',
                title: 'Questions'
            },
            {
                key: 'Promos',
                text: 'Promos',
                title: 'Promos',
                editor: {
                    editObject: 'pSelected',
                    editFunction: 'editPromoCode',
                    dialogToggle: 'pEdit'
                }
            },
            {
                key: 'Addons',
                text: 'Addons',
                title: 'Addons',
                editor: {
                    editObject: 'dSelected',
                    editFunction: 'editAddon',
                    dialogToggle: 'dEdit'
                }
            },
            {
                key: 'Notifications',
                text: 'Notifications',
                title: 'Notifications',
                editor: {
                    editObject: 'eSelected',
                    editKey: 'name',
                    editFunction: 'editEmailTemplate',
                    dialogToggle: 'eEdit'
                }
            }
        ],
        listRemoveHeaders: [
            'application_status',
            'time_checked_in',
            'time_printed'
        ],
        listAddHeaders: [{
            text: 'Secondary Email',
            value: 'notify_email'
        }],
        bSelected: {},
        bEdit: false,
        bPrint: false,
        btAddHeaders: [{
            text: 'Name',
            value: 'name'
        }, {
            text: 'Dates Available',
            value: 'dates_available'
        }, {
            text: 'Total Available',
            value: 'quantity'
        }, {
            text: 'Total Sold',
            value: 'quantity_sold'
        }, {
            text: 'Total Remaining',
            value: 'quantity_remaining'
        }, {
            text: 'Price',
            value: 'price'
        }, {
            text: 'Active',
            value: 'active'
        }],
        btSelected: {},
        btDialog: false,
        pAddHeaders: [{
            text: 'Code',
            value: 'code'
        }, {
            text: 'Dates Available',
            value: 'dates_available'
        }, {
            text: 'Total Available',
            value: 'quantity'
        }, {
            text: 'Discount',
            value: 'discount'
        }, {
            text: 'Active',
            value: 'active'
        }],
        pSelected: {},
        pEdit: false,
        paAddHeaders: [{
            text: 'Real Name',
            value: 'real_name',
        }, {
            text: 'Fandom Name',
            value: 'fandom_name',
        }, {
            text: 'Payment Status',
            value: 'payment_status',
        }, {
            text: 'Badge Type',
            value: 'badge_type_name',
        }, ],
        paRemoveHeaders: [
            'time_printed',
            'time_checked_in'
        ],
        dAddHeaders: [{
            text: 'Name',
            value: 'name'
        }, {
            text: 'Dates Available',
            value: 'dates_available'
        }, {
            text: 'Total Available',
            value: 'quantity'
        }, {
            text: 'Price',
            value: 'price'
        }, {
            text: 'Active',
            value: 'active'
        }],
        dSelected: {},
        dEdit: false,

        asAddHeaders: [{
            text: 'Real Name',
            value: 'real_name',
        }, {
            text: 'Fandom Name',
            value: 'fandom_name',
        }, {
            text: 'Payment Status',
            value: 'payment_status',
        }, ],
        asRemoveHeaders: [
            'badge_type_name',
            'time_printed',
            'time_checked_in'
        ],
        
        eAddHeaders: [{
            text: 'From',
            value: 'from'
        }, {
            text: 'CC',
            value: 'cc'
        }, {
            text: 'Subject',
            value: 'subject'
        }, {
            text: 'Active',
            value: 'active'
        }],
        eSelected: {},
        eEdit: false,
        eDelete: false,
        eIsNew: false,
        eExistingTemplates:[],
        eNameTranslation: {
            'payment-Completed': {
                text: 'Cart payment completed',
                tip: 'Transaction confirmation email'
            },
            'payment-Incomplete': {
                text: 'Cart payment incomplete',
                tip: 'Transaction reminder email'
            },
        },
        eloadBadgeDataDialog: false,
        eloadBadgeDataID: 0,


        loading: false,
        bModified: false,

    }),
    computed: {
        ...mapGetters('products', {
            contextBadges: 'contextBadges',
        }),
        listActions: function() {
            var result = [];
            //TODO: Detect permissions
            result.push({
                name: "edit",
                text: "Edit",
                icon: "pencil"
            });
            // result.push({
            //     name: "print",
            //     text: "Print"
            // });
            return result;
        },
        btActions: function() {
            var result = [];
            result.push({
                name: 'edit',
                text: 'Edit',
                icon: 'pencil'
            });
            return result;
        },
        btFooterActions: function() {
            var result = [];
            result.push({
                name: 'create',
                text: 'Add',
                icon: 'plus'
            });
            return result;
        },
        asActions: function() {
            var result = [];
            result.push({
                name: 'edit',
                text: 'Edit badge',
                icon: 'pencil'
            });
            return result;
        },
        eExistingTemplateNames() {
            return this.eExistingTemplates.map(item => item.name);
        },
        eActions: function() {
            var result = [];
            result.push({
                name: 'edit',
                text: 'Edit',
                icon: 'pencil'
            },);
            return result;
        },
    },
    methods: {
        checkPermission() {
            console.log('Hey! Listen!');
            this.$store.dispatch('products/selectContext', 'A');
        },
        editBadge: function(selectedBadge) {
            console.log(selectedBadge);
            this.loading = false;
            admin.genericGet('Attendee/Badge/' + selectedBadge.id, null, (editBadge) => {
                console.log('loaded badge', editBadge)
                this.bSelected = editBadge;
            
                this.loading = false;
                this.bEdit = true;
            }, () => {
                this.loading = false;
            })
        },
        saveBadge: function(sendStatus) {
            console.log('saving badge', this.bSelected);
            this.loading = true;
            admin.genericPost('Attendee/Badge/' + this.bSelected.id + "?sendupdate=" + (sendStatus ? "true" : "false"), this.bSelected, (editBadge) => {
                this.bSelected = {};
                this.loading = false;
                this.bEdit = false;
                this.$nextTick(() => {
                    this.bModified = false;
                })

            }, () => {
                this.loading = false;
            })
        },
        createBadgeType: function() {
            this.btDialog = true;
            this.btSelected = {};
        },
        editBadgeType: function(selectedBadgeType) {
            this.loading = true;
            admin.genericGet('Attendee/BadgeType/' + selectedBadgeType.id, null, (editBt) => {
                
                this.btSelected = editBt;
                this.loading = false;
                this.btDialog = true;
            }, () => {
                this.loading = false;
            })
        },
        saveBadgeType: function() {
            var url = 'Attendee/BadgeType';
            if (this.btSelected.id != undefined)
                url = url + '/' + this.btSelected.id;
            console.log("Saving badge type", this.btSelected)
            admin.genericPost(url, this.btSelected, (editBt) => {

                this.btSelected = editBt;
                this.loading = false;
                this.btDialog = false;
            }, () => {
                that.loading = false;
            })
        },
        tSetActive: function (id, active) {
            this.loading = true;
            var url = 'Attendee/BadgeType/' + id;
            console.log("Saving badge type active state", id, active)
            admin.genericPost(url, { active }, (result) => {
                this.btDialog = false;
                this.loading = false;
            }, () => {

            })
        },
        editPromoCode: function(selectedPromoCode) {
            console.log(selectedPromoCode);
            this.loading = true;
            admin.genericGet('Attendee/PromoCode/' + selectedPromoCode.id, null, (editPromoCode) => {
                console.log('loaded PromoCode', editPromoCode)
                this.pSelected = editPromoCode;
                this.loading = false;
                this.pEdit = true;
            }, () => {
                this.loading = false;
            })
        },
        savePromoCode: function() {
            var url = 'Attendee/PromoCode';
            if (this.pSelected.id != undefined)
                url = url + '/' + this.pSelected.id;
            console.log("Saving Promo Code", this.pSelected)
            admin.genericPost(url, this.pSelected, (editPC) => {

                this.pSelected = editPC;
                this.loading = false;
                this.pEdit = false;
            }, () => {
                this.loading = false;
            })
        },
        createPromoCode: function() {
            this.pEdit = true;
            this.pSelected = {};
        },
        pSetActive: function (id, active) {
            this.loading = true;
            var url = 'Attendee/PromoCode/' + id;
            console.log("Saving promocode active state", id, active)
            admin.genericPost(url, { active }, (result) => {
                this.pEdit = false;
                this.loading = false;
            }, () => {

            })
        },

        editAddon: function(selectedAddon) {
            console.log(selectedAddon);
            this.loading = true;
            admin.genericGet('Attendee/Addon/' + selectedAddon.id, null, (editAddon) => {
                console.log('loaded Addon', editAddon)
                this.dSelected = editAddon;
                this.loading = false;
                this.dEdit = true;
            }, (err) => {
                console.log('errAddasd', err)
                this.loading = false;
            })
        },
        saveAddon: function() {
            var url = 'Attendee/Addon';
            if (this.dSelected.id != undefined)
                url = url + '/' + this.dSelected.id;
            console.log("Saving Addon", this.dSelected)
            admin.genericPost(url, this.dSelected, (editA) => {

                this.dSelected = editA;
                this.loading = false;
                this.dEdit = false;
            }, () => {
                this.loading = false;
            })
        },
        createAddon: function() {
            this.dEdit = true;
            this.dSelected = {};
        },
        aSetActive: function (id, active) {
            this.loading = true;
            var url = 'Attendee/Addon/' + id;
            console.log("Saving addon active state", id, active)
            admin.genericPost(url, { active }, (result) => {
                this.aEdit = false;
                this.loading = false;
            }, () => {

            })
        },
        editBadgeFromAddon: function(selectedBadge) {
            console.log(selectedBadge);
            let that = this;
            that.loading = false;
            admin.genericGet('Attendee/Badge/' + selectedBadge.attendee_id, null, function(editBadge) {
                console.log('loaded badge', editBadge)
                that.bSelected = editBadge;
                that.loading = false;
                that.bEdit = true;
            }, () => {
                that.loading = false;
            })
        },

        editEmailTemplate: function(selectedEmailTemplate) {
            console.log(selectedEmailTemplate);
            this.loading = false;
            admin.genericGet('Mail/Template/A/' + selectedEmailTemplate.name, null, (editEmailTemplate) => {
                console.log('loaded EmailTemplate', editEmailTemplate)
                this.eSelected = editEmailTemplate;
                this.loading = false;
                this.eEdit = true;
                this.eIsNew = false;
            }, () => {
                this.loading = false;
            })
        },
        saveEmailTemplate: function() {
            var url = 'Mail/Template/A/' + this.eSelected.name;
            
            console.log("Saving Email Template", this.eSelected)
            admin.genericPut(url, this.eSelected, (editET) => {

                this.eSelected = editET;
                this.loading = false;
                this.eEdit = false;
            }, () => {
                this.loading = false;
            })
        },
        createEmailTemplate: function() {
            this.eEdit = true;
            this.eIsNew = true;
            this.eSelected = {};
        },
        eSetActive: function (name, active) {
            this.loading = true;
            var url = 'Mail/Template/A/' + name;
            console.log("Saving EmailTemplate active state", name, active)
            admin.genericPut(url, { active }, (result) => {
                this.eEdit = false;
                this.loading = false;
            }, () => {

            })
        },
        deleteEmailTemplate: function() {
            this.eDelete = true;
        },
        eDeleteConfirmed: function () {
            this.loading = true;
            var url = 'Mail/Template/A/' + this.eSelected.name;
            console.log("Deleting EmailTemplate", this.eSelected.name)
            admin.genericDelete(url, (result) => {
                this.eDelete = false;
                this.loading = false;
                this.eEdit = false;
            }, () => {

            })
        },
        eloadBadgeData() {

            admin.genericGet('Badge/CheckIn/A/' + this.eloadBadgeDataID, null, (badgeData) => {
                //Merge in the event info
                badgeData['event'] = this.$store.state.products.selectedEvent
                this.bSelected = badgeData;
                this.eloadBadgeDataDialog = false;
            }, function() {
                //Whoops
            })
        },

    },
    watch: {
        $route() {
            this.$nextTick(this.checkPermission);
        },
    },
    created() {
        this.checkPermission();
        //this.doSearch();
        this.$emit('updateSubTabs', this.subTabs);
    }
};
</script>
