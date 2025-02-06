<template>
<v-tabs-items :value="subTabIx"
              touchless>
    <v-tab-item value="Location">
        <simpleList apiPath="Location"
                    :AddHeaders="listAddHeaders"
                    :RemoveHeaders="listRemoveHeaders"
                    :isEditingItem="lEdit"
                    :actions="listActions"
                    :footerActions="listFooterActions"
                    @edit="editLocation"
                    @create="createLocation" >
                    
                    
                <template v-slot:[`item.active`]="{ item }">
                    <cell-toggle v-model="item.active" @input="lSetActive(item.id, $event)"></cell-toggle>
                </template>
                </simpleList>

        <v-dialog v-model="lEdit"
                  scrollable>
            <v-card tile
                    v-if="lEdit">
                <v-card-title class="headline">{{lSelected.id ? 'Edit' : 'Create'}} Location</v-card-title>
                <v-divider></v-divider>
                <v-card-text>
                    Location editor
                    <!-- <editAdminLocation v-model="uSelected" /> -->
                </v-card-text>
                <v-divider></v-divider>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="default"
                           @click="lEdit = false">Cancel</v-btn>
                    <v-btn color="primary"
                           @click="saveLocation">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-tab-item>
    
    <v-tab-item value="Maps">
        <simpleList apiPath="LocationMap"
                    :AddHeaders="mAddHeaders"
                    :RemoveHeaders="listRemoveHeaders"
                    :isEditingItem="mEdit"
                    :actions="listActions"
                    :footerActions="listFooterActions"
                    @edit="editMap"
                    @create="createMap" />

        <v-dialog v-model="mEdit"
                  fullscreen
                  scrollable
                  hide-overlay>
            <v-card>
                <v-card-title class="pa-0">
                    <v-toolbar dark
                               flat
                               color="primary">
                        <v-btn icon
                               dark
                               @click="mEdit = false">
                            <v-icon>mdi-close</v-icon>
                        </v-btn>
                        <v-toolbar-title>Edit Map</v-toolbar-title>
                        <v-spacer></v-spacer>
                        <v-toolbar-items>
                            <v-btn color="primary"
                                   dark
                                   @click="saveMap()">
                                <v-icon>mdi-content-save</v-icon>
                            </v-btn>
                        </v-toolbar-items>
                    </v-toolbar>
                </v-card-title>
                <v-card-text class="pa-0">
                    <locationMapEditor v-model="mSelected" />
                </v-card-text>
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
import locationMapEditor from '@/components/locationMapEditor.vue';
import cellToggle from '@/components/datagridcell/toggleValue.vue';

export default {
    components: {
        simpleList,
        // simpleDropdown,
        locationMapEditor,
        cellToggle
    },
    props: [
        'subTabIx'
    ],
    data: () => ({
        listRemoveHeaders: [
            'id'
        ],
        listAddHeaders: [{
            text: 'Short Code',
            value: 'short_code'
        }, {
            text: 'Name',
            value: 'name'
        }, {
            text: 'Description',
            value: 'description'
        }, {
            text: 'Assignments',
            value: 'AssignmentCount'
        }, {
            text: 'Listed',
            value: 'active'
        }],
        mAddHeaders: [{
            text: 'Name',
            value: 'name'
        }, {
            text: 'Description',
            value: 'description'
        }, {
            text: 'Locations',
            value: 'CoordCount'
        }, {
            text: 'Listed',
            value: 'active'
        }, {
            text: 'Image',
            value: 'bgImageID'
        }],
        lSelected: {},
        lEdit: false,
        lCreate: false,
        uNew_contact_id: null,
        mSelected:{},
        mEdit:false,
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
        editLocation: function(selectedLocation) {
            console.log("Edit Location", selectedLocation);
            this.loading = true;
            admin.genericGet(this.authToken, 'Location/' + selectedLocation.id, null, (editLocation) => {
                console.log('loaded Location', editLocation)
                this.lSelected = editLocation;
                this.loading = false;
                this.lEdit = true;
            }, function() {
                this.loading = false;
            })
        },
        createLocation: function() {
            this.lEdit = true;
            this.lSelected = {
                active: true
            };
        },
        lSetActive: function(id,active) {
            this.lEdit = true;
            var url = 'Location/' + id;
            console.log("Saving location active state", id,active)
            admin.genericPost(this.authToken, url, {active}, () => {
                this.lEdit = false;
            }, function() {
                
            })
        },
        saveLocation: function() {
            var url = 'Location';
            
            if (this.lSelected.id != null)
                url = url + '/' + this.lSelected.id;
            console.log("Saving Location", this.lSelected)
            this.loading = true;
            admin.genericPost(this.authToken, url,this.lSelected,(editBt) => {

                this.loading = false;
                this.uCreate = false;
                this.uEdit = false;
            }, function() {
                this.loading = false;
            })
        },
        editMap: function(selectedLocationMap) {
            console.log("Edit LocationMap", selectedLocationMap);
            this.loading = true;
            admin.genericGet(this.authToken, 'LocationMap/' + selectedLocationMap.id, null, (editLocationMap) => {
                console.log('loaded LocationMap', editLocationMap)
                this.mSelected = editLocationMap;
                this.loading = false;
                this.mEdit = true;
            }, function() {
                this.loading = false;
            })
        },
        createMap: function() {
            this.mEdit = true;
            this.mSelected = {
                active: true
            };
        },
        mSetActive: function(id,active) {
            this.lEdit = true;
            var url = 'LocationMap/' + id;
            console.log("Saving LocationMap active state", id,active)
            admin.genericPost(this.authToken, url, {active}, () => {
                this.mEdit = false;
            }, function() {
                
            })
        },
        saveMap: function() {
            var url = 'LocationMap';
            
            if (this.lSelected.id != null)
                url = url + '/' + this.lSelected.id;
            console.log("Saving LocationMap", this.lSelected)
            this.loading = true;
            admin.genericPost(this.authToken, url,this.lSelected,(editBt) => {

                this.loading = false;
                this.mEdit = false;
            }, function() {
                this.loading = false;
            })
        }
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
                key: 'Location',
                text: 'Locations',
                title: 'Locations'
            },
            {
                key: 'Maps',
                text: 'Maps',
                title: 'Maps'
            },
            {
                key: 'Assignments',
                text: 'Assignments',
                title: 'Assignments'
            },
        ]);
    }
};
</script>
