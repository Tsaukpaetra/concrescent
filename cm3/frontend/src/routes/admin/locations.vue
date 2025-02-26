<template>
    <v-tabs-items :value="subTabIx" touchless>
        <v-tab-item value="Location">
            <simpleList apiPath="Location" :AddHeaders="listAddHeaders" :RemoveHeaders="listRemoveHeaders"
                :isEditingItem="lEdit" :actions="listActions" :footerActions="listFooterActions" @edit="editLocation"
                @create="createLocation">


                <template v-slot:[`item.active`]="{ item }">
                    <cell-toggle v-model="item.active" @input="lSetActive(item.id, $event)"></cell-toggle>
                </template>
            </simpleList>

            <v-dialog v-model="lEdit" scrollable>
                <v-card tile v-if="lEdit">
                    <v-card-title class="headline">{{ lSelected.id ? 'Edit' : 'Create' }} Location</v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                        <!-- <editAdminLocation v-model="uSelected" /> -->
                        <editLocation v-model="lSelected"></editLocation>
                    </v-card-text>
                    <v-divider></v-divider>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="default" @click="lEdit = false">Cancel</v-btn>
                        <v-btn color="primary" @click="saveLocation">Save</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </v-tab-item>

        <v-tab-item value="Maps">
            <simpleList apiPath="LocationMap" :AddHeaders="mAddHeaders" :RemoveHeaders="listRemoveHeaders"
                :isEditingItem="mEdit" :actions="listActions" :footerActions="listFooterActions" @edit="editMap"
                @create="createMap" />

            <v-dialog v-model="mEdit" fullscreen scrollable hide-overlay>
                <v-card>
                    <v-card-title class="pa-0">
                        <v-toolbar dark flat color="primary">
                            <v-btn icon dark @click="mEdit = false">
                                <v-icon>mdi-close</v-icon>
                            </v-btn>
                            <v-toolbar-title>Edit Map</v-toolbar-title>
                            <v-spacer></v-spacer>
                            <v-toolbar-items>
                                <v-btn color="primary" dark @click="saveMap()">
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
        <v-tab-item value="Grid">
            <v-row class="fill-height">
                <v-col>
                    <v-sheet height="64">
                        <v-toolbar flat>
                            <v-btn fab text small color="grey darken-2" @click="gridCalendarprev">
                                <v-icon small>
                                    mdi-chevron-left
                                </v-icon>
                            </v-btn>
                            <v-btn fab text small color="grey darken-2" @click="gridCalendarnext">
                                <v-icon small>
                                    mdi-chevron-right
                                </v-icon>
                            </v-btn>
                            <v-toolbar-title v-if="$refs.calendar">
                                {{ $refs.gridCalendar.title }}
                            </v-toolbar-title>
                            {{ selectedEvent.date_start }} -> {{ selectedEvent.date_end }}
                            <v-spacer></v-spacer>
                        </v-toolbar>
                    </v-sheet>
                    <v-sheet height="600">
                        <v-calendar ref="gridCalendar" v-model="gridFocus" color="primary" type="category" category-show-all
                            :categories="gridCategories" category-text="name" 
                             :events="gridEvents" :event-color="getEventColor" event-category="category_id"
                            :start="selectedEvent.date_start" :end="selectedEvent.date_end"
                            event-name="display_name" event-start="start_time" event-end="end_time" 
                            
                            @change="fetchEvents"></v-calendar>
                    </v-sheet>
                </v-col>
            </v-row>
        </v-tab-item>
        <v-dialog v-model="loading" width="200" height="200" close-delay="1200" content-class="elevation-0" persistent>
            <v-card-text class="text-center overflow-hidden">
                <v-progress-circular :size="150" class="mb-0" indeterminate />
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
    mapActions,mapGetters
} from 'vuex';
import admin from '../../api/admin';
import {
    debounce
} from '@/plugins/debounce';
import simpleList from '@/components/simpleList.vue';
import simpleDropdown from '@/components/simpleDropdown.vue';
import editLocation from '@/components/editLocation.vue';
import locationMapEditor from '@/components/locationMapEditor.vue';
import cellToggle from '@/components/datagridcell/toggleValue.vue';

export default {
    components: {
        simpleList,
        // simpleDropdown,
        editLocation,
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
        mSelected: {},
        mEdit: false,
        loading: false,

        ///Grid tab

        gridFocus: '',
        gridEvents: [],
        gridCategories: [
            {
                "id": 3,
                "short_code": "S1",
                "name": "Mane Hall",
                "description": "Gran Peninsula D, E, F, and G on floor 1",
                "active": 1,
                "AssignmentCount": 0
            },
            {
                "id": 4,
                "short_code": "S2",
                "name": "Solar Hall",
                "description": "Grand Peninsula A on floor 1",
                "active": 1,
                "AssignmentCount": 2
            },
            {
                "id": 5,
                "short_code": "T1",
                "name": "Trixie's Tables",
                "description": "Sandpebble A, B, and C on floor 1",
                "active": 1,
                "AssignmentCount": 2
            },
            {
                "id": 6,
                "short_code": "T2",
                "name": "Kiddie Corner",
                "description": "Sandpebble D and E on floor 1",
                "active": 1,
                "AssignmentCount": 0
            },
            {
                "id": 7,
                "short_code": "S3",
                "name": "Lunar Hall",
                "description": "Grand Pinendicular",
                "active": 1,
                "AssignmentCount": 1
            }
        ],

        ///end Grid tab

    }),
    computed: {
        authToken: function () {
            return this.$store.getters['mydata/getAuthToken'];
        },
        ...mapGetters('products', {
            'selectedEvent': 'selectedEvent',
        }),
        listActions: function () {
            var result = [];
            result.push({
                name: 'edit',
                text: 'Edit',
                icon: 'edit-pencil'
            });
            return result;
        },
        listFooterActions: function () {
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
        editLocation: function (selectedLocation) {
            console.log("Edit Location", selectedLocation);
            this.loading = true;
            admin.genericGet(this.authToken, 'Location/' + selectedLocation.id, null, (editLocation) => {
                console.log('loaded Location', editLocation)
                this.lSelected = editLocation;
                this.loading = false;
                this.lEdit = true;
            }, function () {
                this.loading = false;
            })
        },
        createLocation: function () {
            this.lEdit = true;
            this.lSelected = {
                active: true
            };
        },
        lSetActive: function (id, active) {
            this.loading = true;
            var url = 'Location/' + id;
            console.log("Saving location active state", id, active)
            admin.genericPost(this.authToken, url, { active }, (result) => {
                console.log("Saved location active state", id, result)
                this.lEdit = false;
                this.loading = false;
            }, () => {

            })
        },
        saveLocation: function () {
            var url = 'Location';

            if (this.lSelected.id != null)
                url = url + '/' + this.lSelected.id;
            console.log("Saving Location", this.lSelected)
            this.loading = true;
            admin.genericPost(this.authToken, url, this.lSelected, (editBt) => {

                this.loading = false;
                this.lEdit = false;
            }, () => {
                this.loading = false;
            })
        },
        editMap: function (selectedLocationMap) {
            console.log("Edit LocationMap", selectedLocationMap);
            this.loading = true;
            admin.genericGet(this.authToken, 'LocationMap/' + selectedLocationMap.id, null, (editLocationMap) => {
                console.log('loaded LocationMap', editLocationMap)
                this.mSelected = editLocationMap;
                this.loading = false;
                this.mEdit = true;
            }, function () {
                this.loading = false;
            })
        },
        createMap: function () {
            this.mEdit = true;
            this.mSelected = {
                active: true
            };
        },
        mSetActive: function (id, active) {
            this.lEdit = true;
            var url = 'LocationMap/' + id;
            console.log("Saving LocationMap active state", id, active)
            admin.genericPost(this.authToken, url, { active }, () => {
                this.mEdit = false;
            }, function () {

            })
        },
        saveMap: function () {
            var url = 'LocationMap';

            if (this.lSelected.id != null)
                url = url + '/' + this.lSelected.id;
            console.log("Saving LocationMap", this.lSelected)
            this.loading = true;
            admin.genericPost(this.authToken, url, this.lSelected, (editBt) => {

                this.loading = false;
                this.mEdit = false;
            }, function () {
                this.loading = false;
            })
        },

        ///Grid tab        
        getEventColor(event) {
            var category = this.categoryList.find(x=>x.id == event.category_id) || {
                //Default blue if we don't have that category loaded yet
                color:  '#2196F3'
            }
            const rgb = parseInt(category.color.substring(1), 16)
            const r = (rgb >> 16) & 0xFF
            const g = (rgb >> 8) & 0xFF
            const b = (rgb >> 0) & 0xFF

            return (event === this.locationEvents.find(x => x.editable) && this.dragMode)
                ? `rgba(${r}, ${g}, ${b}, 0.7)`
                : category.color
        },
        gridCalendarprev () {
            this.$refs.gridCalendar.prev()
        },
        gridCalendarnext () {
            this.$refs.gridCalendar.next()
        },
        fetchEvents ({ start, end }) {
            //Actually get this lmao
            const events = 
            [
                {
                    "id": 6,
                    "application_id": 58,
                    "category_id": 1,
                    "start_time": "2036-04-15 01:35:00",
                    "end_time": "2036-04-16 02:40:00",
                    "real_name": "BUCK",
                    "fandom_name": "Brony UK Convention",
                    "name_on_badge": "Real Name Large, Fandom Name Small",
                    "application_status": "PendingAcceptance",
                    "display_name": "BUCK (Brony UK Convention)"
                }
            ]

            this.gridEvents = events
        },

        ///end grid tab
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
            key: 'Grid',
            text: 'Grid',
            title: 'Grid'
        },
        ]);
    }
};
</script>
