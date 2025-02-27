<template>
    <v-container fluid>
        <v-row>
            <v-col cols="12" sm="4">
                <v-autocomplete dense hide-details v-model="model.application_id" :items="applicationList" 
                :readonly="lockApplication"  item-value="id" item-text="real_name"
                    persistent-placeholder>
                    <template v-slot:label>
                        Application
                    </template>
                    <template v-slot:selection="data">
                        <v-chip label small>{{ data.item.badge_id_display }}</v-chip>
                        {{ data.item.display_name }}
                    </template>
                </v-autocomplete>{{ lockApplication }}
            </v-col>
            <v-col cols="12" sm="4">
                <v-autocomplete dense hide-details v-model="model.location_id" :items="locationList"
                    :readonly="lockLocation" persistent-placeholder item-value="id" item-text="name">
                    <template v-slot:label>
                        Location
                    </template>
                    <template v-slot:selection="data">
                        <v-chip label small>{{ data.item.short_code }}</v-chip>
                        {{ data.item.name }}
                    </template>
                </v-autocomplete>
            </v-col>
            <v-col cols="12" sm="4">
                <v-autocomplete dense hide-details v-model="model.category_id" :items="categoryList"
                persistent-placeholder item-value="id" item-text="name">
                    <template v-slot:label>
                        Category
                    </template>
                </v-autocomplete>
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="12" sm="4">

                <v-row>

                    <v-col cols="12" sm="6">
                        <v-text-field v-model="model.start_time" clearable label="Assignment starts"
                            placeholder="Whole event" persistent-placeholder></v-text-field>
                    </v-col>
                    <v-col cols="12" sm="6">
                        <v-text-field v-model="model.end_time" clearable placeholder="Whole event"
                            :readonly="model.start_time == null" @click:clear="model.start_time = null"
                            persistent-placeholder label="Assignment ends"></v-text-field>
                    </v-col>
                    <v-col cols="12">
                        <v-textarea label="Notes" v-model="model.notes" />
                    </v-col>

                </v-row>
            </v-col>

            <v-col cols="12" sm="8">
                <!-- <v-sheet tile height="54" class="d-flex">
                    <v-btn icon class="ma-2" @click="$refs.calendar.prev()">
                        <v-icon>mdi-chevron-left</v-icon>
                    </v-btn>
                    <v-spacer></v-spacer>
                    <v-btn icon class="ma-2" @click="$refs.calendar.next()">
                        <v-icon>mdi-chevron-right</v-icon>
                    </v-btn>
                </v-sheet> -->
                <v-sheet height="55vh">
                    <v-calendar ref="calendar" v-model="eDisplayStart" color="primary" type="custom-daily"
                        :start="selectedEvent.date_start" :end="selectedEvent.date_end" :events="locationEvents"
                        :event-color="getEventColor" event-name="display_name" event-start="start_time"
                        event-end="end_time" :event-ripple="false" @change="getEvents" @mousedown:event="startDrag"
                        @mousedown:time="startTime" @mousemove:time="mouseMove" @mouseup:time="endDrag"
                        @mouseleave.native="cancelDrag">
                        <template v-slot:event="{ event, timed, eventSummary }">
                            <div :class="[event.editable ? 'v-event-draggable' : 'v-event-readonly']">
                                <component :is="{ render: eventSummary }"></component>
                            </div>
                            <div v-if="timed && event.editable" class="v-event-drag-bottom"
                                @mousedown.stop="extendBottom(event)"></div>
                        </template>
                    </v-calendar>
                </v-sheet>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import admin from '../api/admin';
import badgeName from './datagridcell/badgeName.vue';

import {
    mapGetters
} from 'vuex'

function nullIfEmptyOrZero(inValue) {
    if (inValue == 0 || inValue == '' || inValue == null) return null;
    return inValue;
}

function undefinedIfEmptyOrZero(inValue) {
    if (inValue == 0 || inValue == '' || inValue == null) return undefined;
    return inValue;
}
export default {
    components: {},
    props: {
        'value': {
            type: Object
        },
        'application': {
            type: Object,
            default: () => ({ application_id: 0 }),
        },
        'location': {
            type: Object,
            default: () => ({ location_id: 0 }),
        }

    },
    data() {
        return {
            validbadgeTypeInfo: false,
            model: {},
            menuStartDate: false,
            menuEndDate: false,
            applicationListData: [],
            // locationListData: [],
            // categoryList:[],

            RulesRequired: [
                (v) => !!v || 'Required',
            ],

            /// Begin  sample calendar
            eDisplayStart: '',
            dragMode: 0,
            createStart: null,
            locationEvents: [],

            /// end sample calendar
        };
    },
    computed: {
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
            'authToken': 'getAuthToken',
        }),
        ...mapGetters('products', {
            'selectedEvent': 'selectedEvent',
            'locationListData': 'locations',
            'categoryList': 'locationCategories'
        }),
        lockApplication() {
            return this.application.id > 0
        },
        lockLocation() {
            return this.location.id > 0
        },
        applicationList(){
            if(this.lockApplication){
                return [this.application];
            } else {
                //TODO: Fetch applications from store
                return this.applicationListData;
            }
        },
        locationList() {
            if (this.lockLocation) {
                return [this.location];
            } else {
                //TODO: Fetch locations
                return this.locationListData;
            }
        },

    },
    methods: {


        refreshApplications() {            
            //If we're provided with an application data already, use that only
            if (this.lockApplication) {
                console.log('using passed in application')
            } else {
                //Pull the location events from the selected location
                console.log('pulling applications')
    
                //TODO: This should be handled by the store...
                admin.genericGet(this.authToken, 'Location/AvailableApplications', null, (apps) => {
    
                    this.applicationListData = apps;
                }, function() {
                    //Whoops
                });
            }

        },
        refreshLocations() {            
            //If we're provided with an application data already, use that only
            if (this.lockLocation) {
                console.log('using passed in location')
            } else {
                //Pull the location events from the selected location
                console.log('pulling locations')
    
                //TODO: This should be handled by the store...
                admin.genericGet(this.authToken, 'Location', null, (locs) => {
    
                    this.locationListData = locs;
                }, function() {
                    //Whoops
                });
            }
        },
        refreshCategories() {
            //TODO: This should be handled by the store...
            admin.genericGet(this.authToken, 'LocationCategory', null, (categories) => {

                this.categoryList = categories;
            }, function() {
                //Whoops
            });

        },

        /// begin calendar example
        startDrag({ event, timed }) {
            if (event && /*timed &&*/ event.editable) {
                // console.log('starting drag')
                this.dragMode = 2
                this.dragTime = null
            }
        },
        startTime(tms) {
            const mouse = this.toTime(tms)
            const event = this.locationEvents.find(x => x.editable);

            if (this.dragMode == 2 && this.dragTime === null) {
                this.dragTime = mouse - event.start_time
            } else {
                this.createStart = this.roundTime(mouse)
                this.dragMode = 1;
                event.timed = true;

                this.model.start_time = this.formatDate(new Date(this.createStart));
                this.model.end_time = this.formatDate(new Date(this.createStart));

            }
        },
        extendBottom(event) {
            if (event && event.editable) {
                this.createStart = event.start_time
                this.dragMode = 1
            }
        },
        mouseMove(tms) {
            const mouse = this.toTime(tms)
            const event = this.locationEvents.find(x => x.editable);

            if (this.dragMode == 2 && this.dragTime !== null) {
                const start = event.start_time
                const end = event.end_time
                const duration = end - start
                const newStartTime = mouse - this.dragTime
                const newStart = this.roundTime(newStartTime)
                const newEnd = newStart + duration

                event.start_time = newStart
                event.end_time = newEnd
                this.model.start_time = this.formatDate(new Date(newStart));
                this.model.end_time = this.formatDate(new Date(newEnd));
            } else if (this.dragMode == 1 && this.createStart !== null) {
                const mouseRounded = this.roundTime(mouse, false)
                const min = Math.min(mouseRounded, this.createStart)
                const max = Math.max(mouseRounded, this.createStart)

                event.start_time = min
                event.end_time = max
                this.model.start_time = this.formatDate(new Date(min));
                this.model.end_time = this.formatDate(new Date(max));
            }
        },
        endDrag() {
            this.dragTime = null
            this.dragMode = 0
            this.createStart = null
        },
        cancelDrag() {
            
            this.createStart = null
            this.dragTime = null
            this.dragMode = 0
        },
        roundTime(time, down = true) {
            const roundTo = 5 // minutes
            const roundDownTime = roundTo * 60 * 1000

            return down
                ? time - time % roundDownTime
                : time + (roundDownTime - (time % roundDownTime))
        },
        toTime(tms) {
            return new Date(tms.year, tms.month - 1, tms.day, tms.hour, tms.minute).getTime()
        },
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
        getEvents() {
            var events = []
            console.log('getting events', JSON.parse(JSON.stringify(this.location)))

            //If we're provided with location data already, use that
            if (this.lockLocation) {
                events = [...this.location.Assignments].map(assn => ({ ...assn, editable: assn.id == this.model.id }))
                console.log('using passed in events', events)
            } else {
                //Pull the location events from the selected location
                console.log('pulling events for location', this.location_id)
            }

            //Fix event-wide events and insert location data
            events.forEach(assn => this.fixAssnForEvent(assn, false));

            //Check if we have ourself in the resultant list
            var editingIx = events.findIndex(assn => assn.id == this.model.id);
            if (editingIx < 0) {
                console.log('pushing default assignment data since it wasn\'t found')

                var timed = true;
                var start = new Date(this.model.start_time || '');
                var end = new Date(this.model.end_time);

                if (isNaN(start.valueOf()) || start.valueOf() == 0) {
                    start = new Date(this.selectedEvent.date_start)
                    end = new Date(this.selectedEvent.date_end).setSeconds(86399)
                    timed = false;
                }


                events.push({
                    timed: timed,

                    id: this.model.id,
                    application_id: this.model.application_id,
                    category_id: this.model.category_id,
                    real_name: this.model.real_name,
                    fandom_name: this.model.fandom_name,
                    name_on_badge: this.model.name_on_badge,
                    application_status: this.model.application_status,
                    display_name: this.model.display_name,

                    location_id: this.model.location_id,

                    start_time: timed ? start.valueOf() : this.selectedEvent.date_start,
                    end_time: timed ? end.valueOf() : this.selectedEvent.date_end,

                    editable: true
                })
            } else {
                // console.log('setting event ix as edit', editingIx)
                events[editingIx].editable = true;
            }

            this.locationEvents = events
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
            // console.log('assn times', {
            //     start_time: start,
            //     end_time: end,
            //     timed
            // })
            assn.start_time = start;
            assn.end_time = end;
            assn.timed = timed;
            assn.location_id = this.model.location_id;
            assn.editable = editable;
            return assn;
        },

        //TODO: Get rid of this hack please
        formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');

            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        },
    },
    watch: {
        model: {
            handler(newData) {
                console.log('model udpate')
                // console.log(' assignment data',JSON.stringify(newData))
                var skipEmit = Object.keys(newData).length < 1

                newData.id = undefinedIfEmptyOrZero(newData.id);
                newData.application_id = undefinedIfEmptyOrZero(newData.application_id);
                var app = this.applicationList.find(x=>x.id == newData.application_id);
                if(app){

                    // console.log('using application', app)
                    //Application nice  display supporting stuff                
                    newData.application_status = app?.application_status ;
                    newData.real_name = app?.real_name ;
                    newData.fandom_name = app?.fandom_name;
                    newData.name_on_badge = app?.name_on_badge;
                    newData.display_name = app?.display_name;
                }

                
                var event = this.locationEvents.find(x => x.editable);
                // console.log('application id modified',JSON.stringify(event))
                if(event){
                    event.application_id = this.model.application_id;
                    event.display_name = this.model.display_name;
                    event.real_name = this.model.real_name;
                    event.fandom_name = this.model.fandom_name;
                    event.name_on_badge = this.model.name_on_badge;

                }


                newData.location_id = undefinedIfEmptyOrZero(newData.location_id) || this.location.id;
                var loc = this.locationList.find(x=>x.id == newData.location_id);
                if(loc){
                    //location nice  display supporting stuff
                    newData.short_code = loc?.short_code ;
                    newData.name = loc?.name ;
                }
                
                newData.category_id = undefinedIfEmptyOrZero(newData.category_id) || this.categoryList[0]?.id || 0;

                newData.start_time = nullIfEmptyOrZero(newData.start_time);
                newData.end_time = newData.start_time == null ? null : nullIfEmptyOrZero(newData.end_time);


                newData.notes = nullIfEmptyOrZero(newData.notes);
                if (!skipEmit) {
                    // console.log('emitting', JSON.parse(JSON.stringify(newData)));
                    this.$emit('input', newData);
                    this.$refs.calendar.updateTimes();
                }
            },
            deep: true,
            immediate: true
        },
        value: {
            handler(newValue) {
                this.model = newValue;
            },
            deep: true,
            immediate: true
        },
        'model.start_time': function (start_time) {
            var start = new Date(start_time || '');
            // console.log('start time updated to', start_time)

            if (isNaN(start.valueOf()) || start.valueOf() == 0) {
                start = this.selectedEvent.date_start
            }
            this.locationEvents.find(x => x.editable).start_time = start;
        },
        'model.end_time': function (end_time) {

            var start = new Date(this.model.start_time || '');
            var end = new Date(end_time);

            if (isNaN(start.valueOf()) || start.valueOf() == 0) {
                end = this.selectedEvent.date_end
            }
            this.locationEvents.find(x => x.editable).end_time = end;
        },
        'model.application_id': function (application_id) {
            var event = this.locationEvents.find(x => x.editable);
            // console.log('application id modified',JSON.stringify(event))
            event.application_id = this.model.application_id;
            event.display_name = this.model.display_name;
            event.real_name = this.model.real_name;
            event.fandom_name = this.model.fandom_name;
            event.name_on_badge = this.model.name_on_badge;


            // console.log('editing event modified',JSON.stringify(event))
        },
        'model.category_id': function (category_id) {
            console.log('begin watch categoryid trigger')
            var event = this.locationEvents.find(x => x.editable);
            event.category_id = category_id;
            console.log('category changed', event)
        },
    },
    async created() {
        this.refreshApplications();
        // this.refreshLocations();
        // this.refreshCategories();
        await this.$store.dispatch('products/getLocations', this.context_code);
        await this.$store.dispatch('products/getLocationCategories');
        // this.getEvents();
    },
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