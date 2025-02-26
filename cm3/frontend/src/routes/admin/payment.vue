<template>
<v-tabs-items :value="subTabIx"
              touchless>
    <v-tab-item value="0">
        <simpleList apiPath="Payment"
                    :AddHeaders="listAddHeaders"
                    :RemoveHeaders="listRemoveHeaders"
                    :isEditingItem="uEdit"
                    :actions="listActions"
                    :footerActions="listFooterActions"
                    show-expand
                    @item-expanded="expandPaymentData"
                    @edit="editUser"
                    @create="createUser" >
                    
                    <template v-slot:[`item.payment_status`]="{ item }">
                        <payment_status_pill v-model="item.payment_status"></payment_status_pill>
                    </template>
                    
                <template v-slot:expanded-item="{ headers, item }">
                    <td :colspan="headers.length">
                        <v-container>

                            <cartItemCards :cart="item" />
                        </v-container>

                    </td>
                </template>
                </simpleList>

        <v-dialog v-model="uEdit"
                  scrollable>
            <v-card tile
                    v-if="uEdit">
                <v-card-title class="headline">Edit User</v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    <v-expansion-panels>
                        <v-expansion-panel>
                            <v-expansion-panel-header>
                                <v-list-item-title>{{uSelected.contact.real_name}}</v-list-item-title>
                                <v-list-item-subtitle>{{uSelected.contact.email_address}}</v-list-item-subtitle>
                            </v-expansion-panel-header>
                            <v-expansion-panel-content>
                                <profileForm v-model="uSelected.contact"
                                            readonly />
                            </v-expansion-panel-content>
                        </v-expansion-panel>
                    </v-expansion-panels>
                    <editAdminUser v-model="uSelected" />
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="default"
                           @click="uEdit = false">Cancel</v-btn>
                    <v-btn color="primary"
                           @click="saveUser">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="uCreate"
                  scrollable>
            <v-card tile>
                <v-card-title class="headline">Create User</v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    <simpleDropdown apiPath="Contact"
                                    valueDisplay="real_name"
                                    valueSubDisplay="email_address"
                                    label="Search contacts"
                                    v-model="uNew_contact_id" />
                    <editAdminUser v-model="uSelected" />
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="default"
                           @click="uCreate = false">Cancel</v-btn>
                    <v-btn color="primary"
                           @click="saveUser">Save</v-btn>
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
    mapActions
} from 'vuex';
import admin from '../../api/admin';
import {
    debounce
} from '@/plugins/debounce';
import simpleList from '@/components/simpleList.vue';
import simpleDropdown from '@/components/simpleDropdown.vue';
import editAdminUser from '@/components/editAdminUser.vue';
import profileForm from '@/components/profileForm.vue';
import cartItemCards from '../../components/cartItemCards.vue';
import payment_status_pill from '@/components/datagridcell/payment_status_pill.vue';

export default {
    components: {
        simpleList,
        simpleDropdown,
        editAdminUser,
        profileForm,
        payment_status_pill,
        cartItemCards
    },
    props: [
        'subTabIx'
    ],
    data: () => ({
        listRemoveHeaders: [
        ],
        listAddHeaders: [{
            text: 'Contact',
            value: 'contact_email_address'
        }, {
            text: 'Status',
            value: 'payment_status'
        }, {
            text: 'System',
            value: 'payment_system'
        }, {
            text: 'Date',
            value: 'payment_date'
        }, {
            text: 'Amount',
            value: 'payment_txn_amt'
        }, {
            text: 'Requested by',
            value: 'requested_by'
        },
        ],
        uSelected: {},
        uEdit: false,
        uCreate: false,
        uNew_contact_id: null,
        loading: false,

    }),
    computed: {
        authToken: function() {
            return this.$store.getters['mydata/getAuthToken'];
        },
        listActions: function() {
            var result = [];
            result.push({
                name: 'edit',
                text: 'Edit',
                icon: 'edit-pencil'
            });
            return result;
        },
        listFooterActions: function() {
            var result = [];
            result.push({
                name: 'create',
                text: 'Add',
                icon: 'plus'
            });
            return result;
        }
    },
    methods: {
        checkPermission: () => {
            console.log('Hey! Listen!');
        },
        expandPaymentData: function({item,value}) {
            if(value){
                console.log('expanding',item);

                let that = this;
                that.loading = false;
                admin.genericGet(this.authToken, 'Payment/' + item.id, null, function(payment) {
                    console.log('loaded payment', payment)
                    //Try to set the item's properties
                    for(const prop in payment){
                        that.$set(item,prop,payment[prop]);
                    }
                    that.loading = false;
                    
                }, function() {
                    that.loading = false;
                })
            }
        },
        editUser: function(selectedUser) {
            console.log("Edit user", selectedUser);
            let that = this;
            that.loading = false;
            admin.genericGet(this.authToken, 'AdminUser/' + selectedUser.contact_id, null, function(editUser) {
                console.log('loaded user', editUser)
                that.uSelected = editUser;
                that.loading = false;
                that.uEdit = true;
            }, function() {
                that.loading = false;
            })
        },
        createUser: function() {
            this.uCreate = true;
            this.uSelected = {
                active: true
            };
        },
        editBadgeType: function(selectedBadgeType) {
            this.loading = true;
            this.btDialog = true;
            var that = this;
            admin.genericGet(this.authToken, 'Staff/BadgeType/' + selectedBadgeType.id, null, function(editBt) {

                that.btSelected = editBt;
                that.loading = false;
            }, function() {
                that.loading = false;
            })
        },
        saveUser: function() {
            var url = 'AdminUser';
            var data = {
                ...this.uSelected,
                contact_id: this.uCreate ? this.uNew_contact_id : this.uSelected.contact_id,
            };
            if (this.uEdit)
                url = url + '/' + data.contact_id;
            console.log("Saving user", this.uSelected)
            this.loading = true;
            var that = this;
            admin.genericPost(this.authToken, url, data, function(editBt) {

                that.loading = false;
                that.uCreate = false;
                that.uEdit = false;
            }, function() {
                that.loading = false;
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
        this.$emit('updateSubTabs', [{
                key: '0',
                text: 'Users',
                title: 'Users'
            },
        ]);
    }
};
</script>
