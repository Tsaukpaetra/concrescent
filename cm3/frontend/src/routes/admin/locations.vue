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
                            <v-select v-model="gridLocations" :items="locations" item-text="name" item-value="id"
                                return-object clearable open-on-clear @click:append-outer="gridLocationSelectDefaults"
                                append-outer-icon="mdi-cached" chips single-line hide-details
                                label="Select locations to begin" multiple filled>
                                <template v-slot:selection="{ item }">
                                    <v-btn readonly outlined small>
                                        <v-chip label small>{{ item.short_code }}</v-chip>
                                        {{ item.name }}
                                    </v-btn>
                                </template>
                                <template v-slot:item="{ item, on, attrs }">
                                    <v-list-item v-bind="attrs" v-on="on">
                                        <v-list-item-action>
                                            <v-chip label small>{{ item.short_code }}</v-chip>
                                        </v-list-item-action>
                                        <v-list-item-content>
                                            <v-list-item-title :class="attrs.inputValue ? 'primary--text' : ''">
                                                {{ item.name }}
                                            </v-list-item-title>
                                        </v-list-item-content>
                                    </v-list-item>
                                </template>

                            </v-select>
                            <v-spacer></v-spacer>

                            <v-select v-model="gridCategories" :items="locationCategories" item-text="name"
                                item-value="id" filled chips single-line hide-details label="Showing all Categories"
                                multiple>
                                <template v-slot:selection="{ item }">
                                    <v-chip :color="item.color">
                                        {{ item.name }}
                                    </v-chip>
                                </template>
                                <template v-slot:item="{ item, on, attrs }">
                                    <v-list-item v-on="on">
                                        <v-list-item-action>
                                            <v-simple-checkbox :value="attrs.inputValue" v-on="on" :color="item.color"
                                                :ripple="false"></v-simple-checkbox>
                                        </v-list-item-action>
                                        <v-list-item-content>
                                            <v-list-item-title :class="attrs.inputValue ? 'primary--text' : ''">
                                                {{ item.name }}
                                            </v-list-item-title>
                                        </v-list-item-content>
                                    </v-list-item>
                                </template>
                            </v-select>
                        </v-toolbar>
                    </v-sheet>
                    <v-sheet style="overflow-y: scroll">
                        <v-calendar ref="grids" v-for="day in eventDates" :key="day" :value="day" type="category"
                            category-show-all :categories="gridLocations" category-text="id" :events="gridEvents"
                            :weekday-format="() => ''"
                            :day-format="function (d) { return (new Date(d.date)).toDateString() }"
                            :event-color="getEventColor" event-category="location_id" event-name="display_name"
                            event-start="start_time" event-end="end_time" v-scroll.self="gridScrolled">
                            <template v-slot:category="{ category }">
                                <div class="text-center">
                                    <v-tooltip bottom>
                                        <template v-slot:activator="{ on, attrs }">
                                            <span v-bind="attrs" v-on="on">
                                                <v-chip label small>{{ category.short_code }}</v-chip>
                                                {{ category.name }}
                                            </span>
                                        </template>
                                        <span>{{ category.description }}</span>
                                    </v-tooltip>
                                </div>
                            </template>
                        </v-calendar>
                    </v-sheet>
                </v-col>
            </v-row>
        </v-tab-item>

        <v-tab-item value="Categories">

            <v-item-group v-model="locationCategorySelectedIx">
                <v-container>
                    <v-row>
                        <v-col v-for="item in locationCategories" :key="item.id" cols="12" md="4">
                            <v-item v-slot="{ active, toggle }">
                                <v-card min-height="200"
                                    :elevation="active ? 20 : (locationCategorySelectedIx != undefined ? 1 : 3)"
                                    :ripple="!active" :disabled="locationCategorySelectedIx != undefined && !active"
                                    @click="!active ? toggle() : null">
                                    <v-col>
                                        <v-toolbar flat>

                                            <v-checkbox v-if="active" v-model="locationCategorySelected.active"
                                                hide-details :true-value="1" :false-value="0" on-icon="mdi-eye"
                                                off-icon="mdi-eye-off" class=""></v-checkbox>
                                            <v-checkbox v-else v-model="item.active" hide-details :true-value="1"
                                                :false-value="0" on-icon="mdi-eye" off-icon="mdi-eye-off" readonly
                                                class=""></v-checkbox>
                                            <v-toolbar-title>

                                                <v-text-field v-if="active" v-model="locationCategorySelected.name"
                                                    class="text-h6" hide-details filled></v-text-field>
                                                <div v-else class="pa-3">{{ item.name }}</div>
                                            </v-toolbar-title>
                                            <v-spacer></v-spacer>
                                            <v-menu v-if="active" v-model="item.ColorEditing" left
                                                :close-on-content-click="false" :nudge-width="200">
                                                <template v-slot:activator="{ on, attrs }">
                                                    <v-btn :color="locationCategorySelected.color" elevation="5"
                                                        height="40" width="40" min-width="40" v-bind="attrs" v-on="on">
                                                    </v-btn>
                                                </template>

                                                <v-card>
                                                    <v-color-picker dot-size="22" mode="rgba"
                                                        v-model="locationCategorySelected.color"></v-color-picker>
                                                    <v-card-actions>
                                                        <v-spacer></v-spacer>
                                                        <v-btn color="primary" @click="item.ColorEditing = false">
                                                            Ok
                                                        </v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </v-menu>
                                            <v-list-item-avatar v-else tile size="40"
                                                :color="item.color"></v-list-item-avatar>
                                        </v-toolbar>
                                    </v-col>
                                    <v-col>
                                        <v-textarea v-if="active" v-model="locationCategorySelected.description"
                                            auto-grow rows="1" filled></v-textarea>
                                        <div v-else class="pa-3">{{ item.description }}</div>

                                    </v-col>
                                    <v-scroll-y-transition>
                                        <v-card-actions v-if="active">
                                            <v-btn outlined text @click.stop="locationCategorySelectedIx = undefined">
                                                Cancel
                                            </v-btn>
                                            <v-spacer></v-spacer>

                                            <v-btn color="primary" @click.stop="saveLocationCategory">
                                                Save
                                            </v-btn>
                                        </v-card-actions>
                                    </v-scroll-y-transition>
                                </v-card>
                            </v-item>
                        </v-col>
                        <!-- and the same now for Create New-->
                        <v-col cols="12" md="4">
                            <v-item v-if="locationCategorySelectedIx != -1">

                                <v-card height="200" :disabled="locationCategorySelectedIx > -1"
                                    @click="createLocationCategory">

                                    <v-col>
                                        <v-toolbar flat>

                                            <v-checkbox value="0" hide-details :true-value="1" :false-value="0"
                                                on-icon="mdi-eye" off-icon="mdi-eye-off" readonly class=""></v-checkbox>
                                            <v-toolbar-title>

                                                <div class="pa-3">(Create New)</div>
                                            </v-toolbar-title>
                                            <v-spacer></v-spacer>
                                            <v-list-item-avatar tile size="40" color="#2196F3"></v-list-item-avatar>
                                        </v-toolbar>
                                    </v-col>
                                </v-card>
                            </v-item>

                            <v-card min-height="200" v-else-if="locationCategorySelected" :elevation="20">
                                <v-col>
                                    <v-toolbar flat>

                                        <v-checkbox v-model="locationCategorySelected.active" hide-details
                                            :true-value="1" :false-value="0" on-icon="mdi-eye" off-icon="mdi-eye-off"
                                            class=""></v-checkbox>
                                        <v-toolbar-title>

                                            <v-text-field v-model="locationCategorySelected.name" class="text-h6"
                                                hide-details filled></v-text-field>
                                        </v-toolbar-title>
                                        <v-spacer></v-spacer>
                                        <v-menu v-model="locationCategorySelectedNewColorEditing" left
                                            :close-on-content-click="false" :nudge-width="200">
                                            <template v-slot:activator="{ on, attrs }">
                                                <v-btn :color="locationCategorySelected.color" elevation="5" height="40"
                                                    width="40" min-width="40" v-bind="attrs" v-on="on">
                                                </v-btn>
                                            </template>

                                            <v-card>
                                                <v-color-picker dot-size="22" mode="rgba"
                                                    v-model="locationCategorySelected.color"></v-color-picker>
                                                <v-card-actions>
                                                    <v-spacer></v-spacer>
                                                    <v-btn color="primary"
                                                        @click="locationCategorySelectedNewColorEditing = false">
                                                        Ok
                                                    </v-btn>
                                                </v-card-actions>
                                            </v-card>
                                        </v-menu>
                                    </v-toolbar>
                                </v-col>
                                <v-col>
                                    <v-textarea v-model="locationCategorySelected.description" auto-grow rows="1"
                                        filled></v-textarea>

                                </v-col>
                                <v-scroll-y-transition>
                                    <v-card-actions>
                                        <v-btn outlined text @click.stop="locationCategorySelectedIx = undefined">
                                            Cancel
                                        </v-btn>
                                        <v-spacer></v-spacer>

                                        <v-btn color="primary" @click.stop="saveLocationCategory">
                                            Save
                                        </v-btn>
                                    </v-card-actions>
                                </v-scroll-y-transition>
                            </v-card>
                        </v-col>
                    </v-row>
                </v-container>
            </v-item-group>
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
    mapActions, mapGetters
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
        gridLocations: [],
        gridCategories: [],

        ///end Grid tab
        locationCategorySelectedIx: undefined,
        locationCategorySelected: {},
        locationCategorySelectedNewColorEditing: false,

    }),
    computed: {
        authToken: function () {
            return this.$store.getters['mydata/getAuthToken'];
        },
        ...mapGetters('products', {
            'selectedEvent': 'selectedEvent',
            'locations': 'locations',
            'locationCategories': 'locationCategories',
            'locationEvents': 'locationEvents'
        }),
        eventDates: function () {
            const dates = [];
            let currentDate = new Date(this.selectedEvent.date_start);
            let endDate = new Date(this.selectedEvent.date_end);

            while (currentDate <= endDate && dates.length < 100) {
                dates.push(currentDate.toISOString().split('T')[0]);
                currentDate.setDate(currentDate.getDate() + 1);
            }
            return dates;
        },
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
        },
        gridEvents: function () {

            return this.locationEvents
                .filter(e => this.gridCategories.length == 0 || (this.gridCategories.findIndex(c => c == e.category_id) > -1))
                .map((e) => this.fixAssnForEvent(e, false));
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
                this.lSelected.id = editBt.id;
                this.$store.commit('products/updateLocation', this.lSelected);
                //Process assignment updates
                if (editBt.AssnResults.Added)
                    editBt.AssnResults.Added.forEach(e => this.$store.commit('products/updateLocationEvent', e))
                if (editBt.AssnResults.Updated)
                    editBt.AssnResults.Updated.forEach(e => this.$store.commit('products/updateLocationEvent', this.lSelected.Assignments.find(a => a.id == e)))
                if (editBt.AssnResults.Deleted)
                    editBt.AssnResults.Deleted.forEach(e => this.$store.commit('products/deleteLocationEvent', this.lSelected.Assignments.find(a => a.id == e)))

                this.loading = false;
                this.lEdit = false;
            }, (err) => {
                console.log(err)
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
            var category = this.locationCategories.find(x => x.id == event.category_id) || {
                //Default blue if we don't have that category loaded yet
                color: '#2196F3'
            }
            return category.color;
        },
        gridScrolled(e) {
            console.log('scrolled grid', e.target.scrollLeft)
            // this.gridScrollY = e.target.scrollLeft;
            this.$refs.grids.forEach((grid) => grid.$el.scrollLeft = e.target.scrollLeft);
        },

        fixAssnForEvent(assn, editable) {

            var timed = true;
            var start = new Date(assn.start_time || '');
            var end = new Date(assn.end_time);

            if (isNaN(start.valueOf()) || start.valueOf() == 0) {
                start = this.selectedEvent.date_start
                end = this.selectedEvent.date_end
                timed = false;
            }
            //Clone to avoid side effects!
            var result = structuredClone(assn);
            result.start_time = start;
            result.end_time = end;
            result.timed = timed;
            result.editable = editable;
            return result;
        },
        gridLocationSelectDefaults() {
            var result = [...(new Set(this.locationEvents.map(e => {
                var start = new Date(e.start_time || '');
                if (isNaN(start.valueOf()) || start.valueOf() == 0) {
                    return undefined
                } else {
                    return e.location_id
                }
            })))].filter(x => x).map(l => this.locations.find(f => f.id == l));
            //Order by short code
            result.sort((a, b) => (a.short_code > b.short_code) ? 1 : ((b.short_code > a.short_code) ? -1 : 0));
            // console.log('grid location defaults', result, this.gridLocations)
            this.gridLocations = result;
        },

        ///end grid tab
        createLocationCategory() {
            console.log('creating location category')
            this.locationCategorySelectedIx = -1;
        },
        saveLocationCategory() {

            var url = 'LocationCategory';

            if (this.locationCategorySelected.id != null)
                url = url + '/' + this.locationCategorySelected.id;
            console.log("Saving LocationCategory", this.locationCategorySelected)
            this.loading = true;
            admin.genericPost(this.authToken, url, this.locationCategorySelected, (result) => {
                //For some reason the API returns the new ID as a string?
                this.locationCategorySelected.id = parseInt(result.id);
                this.$store.commit('products/updateLocationCategory', this.locationCategorySelected);

                this.locationCategorySelectedIx = undefined;
                this.loading = false;
            }, () => {
                this.loading = false;
            })
        },

    },
    watch: {
        locationCategorySelectedIx(ix) {
            if (ix != undefined) {
                this.locationCategorySelected = structuredClone(this.locationCategories[ix]) || {
                    id: null,
                    active: 1,
                    name: '',
                    description: '',
                    color: '#2196F3'
                };
            } else {
                this.locationCategorySelected = {
                    id: null,
                    active: 1,
                    name: '',
                    description: '',
                    color: '#2196F3'
                };
            }
        },
        $route() {
            this.$nextTick(this.checkPermission);
        },
        async 'selectedEvent'() {
            //This should only happen if we started on this page
            await this.$store.dispatch('products/getLocations');
            await this.$store.dispatch('products/getLocationCategories');
            await this.$store.dispatch('products/getLocationEvents');
            this.gridLocationSelectDefaults();
        },
    },
    async created() {
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
        {
            key: 'Categories',
            text: 'Categories',
            title: 'Categories'
        },
        ]);
        //Skip doing this if we're not ready yet
        if (this.selectedEvent.id) {
            await this.$store.dispatch('products/getLocations');
            await this.$store.dispatch('products/getLocationCategories');
            await this.$store.dispatch('products/getLocationEvents');
            this.gridLocationSelectDefaults();
        }


    }
};
</script>

<style scoped lang="scss">
.v-event-draggable {
    padding-left: 6px;
    height: 100%;
    border-style: dashed;
    user-select: none;
    -webkit-user-select: none;
}

.v-event-readonly {
    padding-left: 6px;
}

.v-event-timed {
    user-select: none;
    -webkit-user-select: none;
}

.v-event-drag-bottom {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0px;
    height: 14px;
    cursor: ns-resize;

    &::after {
        display: none;
        position: absolute;
        left: 50%;
        height: 4px;
        border-top: 1px solid white;
        border-bottom: 1px solid white;
        width: 26px;
        margin-left: -8px;
        opacity: 0.8;
        content: '';
    }

    &:hover::after {
        display: block;
    }
}
</style>