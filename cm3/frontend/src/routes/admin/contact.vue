<template>
<v-tabs-items :value="subTabIx"
              touchless>
    <v-tab-item value="Contacts">
        <simpleList apiPath="Contact"
                         :AddHeaders="listAddHeaders"
                         :RemoveHeaders="listRemoveHeaders"
                         :isEditingItem="cEdit"
                         :actions="listActions"
                         showExport
                         @edit="editContact" />

    </v-tab-item>
    <v-dialog v-model="cEdit"
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
                           @click="cEdit = false">
                        <v-icon>mdi-close</v-icon>
                    </v-btn>
                    <v-toolbar-title>Edit Contact</v-toolbar-title>
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
                                <v-list-item @click="saveContact(true)">
                                    <v-list-item-title>
                                        Save and send status email
                                    </v-list-item-title>
                                </v-list-item>
                                <v-list-item @click="saveContact(false)">
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
                <profileForm v-model="cSelected" />
                <h3>Sessions</h3>
                <contact_sessions :contact_id="cSelected.id"></contact_sessions>
            </v-card-text>
        </v-card>
    </v-dialog>
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
import orderableList from '@/components/orderableList.vue';
import simpleList from '@/components/simpleList.vue';
import badgeTypeForm from '@/components/badgeTypeForm.vue';
import promoCodeForm from '@/components/promoCodeForm.vue';
import addonTypeForm from '@/components/addonTypeForm.vue';
import formQuestionEditList from '@/components/formQuestionEditList.vue';
import profileForm from '@/components/profileForm.vue';
import contact_sessions from '@/components/contact_sessions.vue';

export default {
    components: {
        simpleList,
        profileForm,
        contact_sessions
    },
    mixins: [subTabEditorByQueryIdMixin],
    props: [
        'subTabIx'
    ],
    data: () => ({
        subTabs:[
            {
                key: 'Contacts',
                text: 'Contacts',
                title: 'Contacts',
                editor: {
                    editObject: 'cSelected',
                    editFunction: 'editContact',
                    dialogToggle: 'cEdit'
                }
            },

        ],
        listRemoveHeaders: [
        ],
        listAddHeaders: [{
            text: 'Name',
            value: 'real_name'
        },{
            text: 'Email',
            value: 'email_address'
        },{
            text: 'Marketing',
            value: 'allow_marketing'
        }],
        cSelected: {},
        cEdit: false,

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
                text: "Edit"
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
                icon: 'edit-pencil'
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
                icon: 'edit-pencil'
            });
            return result;
        },
    },
    methods: {
        checkPermission() {
            console.log('Hey! Listen!');
        },
        editContact: function(selectedBadge) {
            console.log(selectedBadge);
            this.loading = false;
            admin.genericGet('Contact/' + selectedBadge.id, null, (editContact) => {
                console.log('loaded contact', editContact)
                this.cSelected = editContact;
                this.loading = false;
                this.cEdit = true;
            }, () => {
                this.loading = false;
            })
        },
        saveContact: function(sendStatus) {
            console.log('saving contact', this.cSelected);
            var url = 'Contact';
            if (this.cSelected.id != undefined)
                url = url + '/' + this.cSelected.id;
            this.loading = true;
            admin.genericPost(url ,this.cSelected, (editContact) => {
                this.cSelected = {};
                this.loading = false;
                this.cEdit = false;
                this.$nextTick(() => {
                    this.bModified = false;
                })

            }, () => {
                this.loading = false;
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
