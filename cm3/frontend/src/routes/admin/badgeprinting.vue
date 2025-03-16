<template>
    <v-tabs-items :value="subTabIx" touchless>
        <v-tab-item value="BadgeFormats">
            <simpleList apiPath="Badge/Format" :AddHeaders="listAddHeaders" :RemoveHeaders="listRemoveHeaders"
                :isEditingItem="fEdit" :actions="listActions" :footerActions="btFooterActions" @edit="editBadgeFormat"
                @create="createBadgeFormat" />

            <v-dialog v-model="fEdit" fullscreen scrollable hide-overlay>
                <v-card>
                    <v-card-title class="pa-0">
                        <v-toolbar dark flat color="primary">
                            <v-btn icon dark @click="fEdit = false">
                                <v-icon>mdi-close</v-icon>
                            </v-btn>
                            <v-toolbar-title>Edit Badge Format</v-toolbar-title>
                            <v-spacer></v-spacer>
                            <v-toolbar-items>
                                <v-btn color="primary" dark @click="saveBadgeFormat()">
                                    <v-icon>mdi-content-save</v-icon>
                                </v-btn>
                            </v-toolbar-items>
                        </v-toolbar>
                    </v-card-title>
                    <v-card-text class="pa-0">
                        <badgeFormatEditor v-model="fSelected" />
                    </v-card-text>
                </v-card>
            </v-dialog>
        </v-tab-item>
        <v-tab-item value="Print">

            <v-stepper non-linear v-model="printStage">
                <v-stepper-header>
                    <v-stepper-step step="1" :complete='fSelected.id != undefined' edit-icon="$vuetify.icons.complete"
                        editable>
                        Select format
                    </v-stepper-step>

                    <v-divider></v-divider>

                    <v-stepper-step step="2" edit-icon="$vuetify.icons.complete" :editable='fSelected.id != undefined' :complete="printSelected.length > 0">
                        Select badges
                    </v-stepper-step>

                    <v-divider></v-divider>

                    <v-stepper-step step="3" editable>
                        Print {{ printSelected.length ? printSelected.length + ' badges' : '' }}
                    </v-stepper-step>
                </v-stepper-header>

                <v-stepper-items>
                    <v-stepper-content step="1">
                        <simpleList apiPath="Badge/Format" :AddHeaders="listAddHeaders"
                            :RemoveHeaders="listRemoveHeaders" :actions="printActions" @select="selectBadgeFormat" />
                    </v-stepper-content>
                    <v-stepper-content step="2">
                        <v-toolbar>
                            <v-switch v-model="badgeSelectParams.includePrinted"
                                :label="`${badgeSelectParams.includePrinted ? 'I' : 'Not i'}ncluding already-printed`"></v-switch>
                            <v-switch v-model="badgeSelectParams.allowUnpaid"
                                :label="`${badgeSelectParams.allowUnpaid ? 'A' : 'Not a'}llowing unpaid`"></v-switch>
                            <v-spacer></v-spacer>
                            <v-btn :disabled="!printSelected.length" @click="printStage = 3" color="primary">
                                Continue
                            </v-btn>
                        </v-toolbar>
                        <badgeSearchList v-if="fSelected.id" show-select
                            :apiPath="'Badge/Format/' + fSelected.id + '/Badges'" internalKey="uuid"
                            :apiAddParams="badgeSelectParams" :AddHeaders="badgeSelectHeaders"
                            :RemoveHeaders="badgeSelectRemoveHeaders" :actions="ptActions"
                            :footerActions="ptFooterActions" v-model="printSelected" @update:results="receiveResults"
                            @selectAllOfType="selectAllOfType" />
                    </v-stepper-content>
                    <v-stepper-content step="3">
                        <v-container>
                            <v-row v-show="!printLocalDone">
                                <v-col cols="3">
                                    <v-text-field placeholder="This station" label="Printing to station"
                                        v-model="printToStation" clearable persistent-placeholder></v-text-field>
                                </v-col>
                                <v-col cols="7">
                                </v-col>
                                <v-col cols="2">
                                    <v-btn @click="enqueueBadgePrintingBatch">Start Printing</v-btn>

                                </v-col>
                            </v-row>
                            <v-row v-show="printLocalDone">
                                <v-col cols="3">
                                    Select any that failed to print
                                </v-col>
                                <v-col cols="7">
                                    <v-btn @click="printPanel = true">Retry Printing {{ printSelectedAgain.length ? 'Selected' : 'All' }}</v-btn>
                                </v-col>
                                <v-col cols="2">
                                    <v-btn @click="completePrintingBatch">Complete Printing</v-btn>

                                </v-col>
                            </v-row>
                            <v-row class="printing printHeaderHidden">
                                <v-col>
                                    <v-item-group v-model="printSelectedAgain" multiple  class="d-flex flex-wrap justify-center">
                                        <v-item v-slot="{ active, toggle }"  v-for="selectedBadge in printSelected" :key="selectedBadge.uuid">
                                            
                                            <v-sheet color="white" class="ma-2" elevation="2"  @click="toggle"
                                            >
                                            <v-container>
                                                <v-row>
                                                    Status: {{ badgePrintJobStatus(selectedBadge) }}
                                                    <v-spacer></v-spacer>
                                                    <v-btn icon v-show="printLocalDone">
                                                        <v-icon>
                                                            {{ active ? 'mdi-refresh' : 'mdi-checkbox-blank-outline' }}
                                                        </v-icon>
                                                    </v-btn>
                                                </v-row>
                                                    
                                                <v-sheet color="white" class="ma-2" elevation="4">
                                                    <badgeFullRender :format="fSelected" :badge="selectedBadge" />
                                                </v-sheet>
                                            </v-container>
                                            
                                            </v-sheet>

                                        </v-item>
                                    </v-item-group>
                                </v-col>
                            </v-row>
                        </v-container>
                        <v-dialog v-model="printPanel" eager style="position:absolute" fullscreen scrollable
                            transition="none">
                            <v-card v-if="printPanel" :class="{ 'printing': printPanel, printHeaderHidden: true }">
                                <v-sheet color="white" class="mx-auto page-break" elevation="4"
                                    v-for="selectedBadge in badgesForPrint" :key="selectedBadge.uuid">
                                    <badgeFullRender :format="fSelected" :badge="selectedBadge" />
                                </v-sheet>
                            </v-card>
                        </v-dialog>
                    </v-stepper-content>
                </v-stepper-items>
            </v-stepper>
        </v-tab-item>
        <v-tab-item value="Queue">
            <v-container>
                <v-row>
                    <v-col>
                        <v-select v-model="queueStateSearch" :items="queueStates" label="Jobs with Status" />
                    </v-col>
                </v-row>
            </v-container>
            <simpleList apiPath="Badge/PrintJob" :apiAddParams="{ expandMeta: true, state: queueStateSearch }"
                :AddHeaders="queueAddHeaders" :RemoveHeaders="queueRemoveHeaders" :isEditingItem="jEdit"
                :actions="listActions" @edit="editPrintJob" />

            <v-dialog v-model="jEdit" fullscreen scrollable hide-overlay>
                <v-card v-if="jEdit">
                    <v-card-title class="pa-0">
                        <v-toolbar dark flat color="primary">
                            <v-btn icon dark @click="jEdit = false">
                                <v-icon>mdi-close</v-icon>
                            </v-btn>
                            <v-toolbar-title>Edit Print Job</v-toolbar-title>
                            <v-spacer></v-spacer>
                            <v-toolbar-items>
                                <v-btn color="primary" dark @click="savePrintJob()">
                                    <v-icon>mdi-content-save</v-icon>
                                </v-btn>
                            </v-toolbar-items>
                        </v-toolbar>
                    </v-card-title>
                    <v-card-text class="pa-0">
                        <v-select v-model="jSelected.state" :items="jPrintStates" label="Status" />
                        <v-card height="200">
                            <scaleToParent>
                                <v-sheet color="white" class="mx-auto mt-3" elevation="4">
                                    <badgeFullRender :format="jSelected.format" :badge="jSelected.data" />
                                </v-sheet>
                            </scaleToParent>
                        </v-card>
                    </v-card-text>
                </v-card>
            </v-dialog>
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
    mapActions
} from 'vuex';
import admin from '../../api/admin';
import {
    debounce
} from '@/plugins/debounce';
import badgeSearchList from '@/components/badgeSearchList.vue';
import simpleList from '@/components/simpleList.vue';
import badgeFormatEditor from '@/components/badgeFormatEditor.vue';
import badgeFullRender from '@/components/badgeFullRender.vue';
import scaleToParent from '@/components/formatpieces/scaleToParent.vue';

export default {
    components: {
        badgeSearchList,
        simpleList,
        badgeFormatEditor,
        badgeFullRender,
        scaleToParent
    },
    props: [
        'subTabIx'
    ],
    data: () => ({
        listRemoveHeaders: [
            'time_checked_in'
        ],
        listAddHeaders: [{
            text: 'Format Name',
            value: 'name'
        }, {
            text: 'Size',
            value: 'customSize'
        }, {
            text: 'Background',
            value: 'bgImageID'
        }],
        fSelected: {},
        fEdit: false,

        printStage: 1,
        badgeSelectParams: {
            includePrinted: false,
            allowUnpaid: false
        },
        badgeSelectHeaders: [

        ],
        badgeSelectRemoveHeaders: [
            'contact_email_address',
            'time_checked_in'
        ],
        printTypes: [],
        printAvailable: [],
        printSelected: [],
        printJobStatuses:{},
        printSelectedAgain: [],
        printToStation: '',
        printPanel: false,
        printLocalDone: false,
        printQueuePollTimer: 0,

        queueStateSearch: '',
        queueRemoveHeaders: [
            'time_checked_in'
        ],
        queueAddHeaders: [{
            text: 'Format Name',
            value: 'name'
        }, {
            text: 'Station Name',
            value: 'stationName'
        }, {
            text: 'Status',
            value: 'state'
        }],
        jfSelected: {},
        jEdit: false,
        jPrintStates: [
            'Queued',
            'Held',
            'Reserved',
            'InProgress',
            'Completed',
            'Batch',
            'Cancelling',
            'Cancelled'
        ],

        loading: false,
        createError: '',
    }),
    computed: {
        authToken: function () {
            return this.$store.getters['mydata/getAuthToken'];
        },
        listActions: function () {
            var result = [];
            //TODO: Detect permissions
            result.push({
                name: "edit",
                text: "Edit"
            });
            return result;
        },
        btActions: function () {
            var result = [];
            result.push({
                name: 'edit',
                text: 'Edit',
                icon: 'edit-pencil'
            });
            return result;
        },
        btFooterActions: function () {
            var result = [];
            result.push({
                name: 'create',
                text: 'Add',
                icon: 'plus'
            });
            return result;
        },
        isCreateError: {
            get() {
                return this.createError.length > 0;
            },
            set(newval) {
                this.createError = newval ? "???" : "";
            }
        },
        printActions: function () {
            var result = [];
            //TODO: Detect permissions
            result.push({
                name: "select",
                text: "Select"
            });
            return result;
        },
        ptActions: function () {
            var result = [];
            //TODO: Detect permissions
            result.push({
                name: "selectAllOfType",
                text: "Select this Badge Type"
            });
            return result;
        },
        ptFooterActions: function () {
            var result = [];
            result.push({
                name: 'addAll',
                text: 'Select All',
                icon: 'select-all'
            }, {
                name: 'addUnprinted',
                text: 'Select All Unprinted',
                icon: 'selection-multiple'
            });
            return result;
        },
        badgesForPrint: function(){
            if(!this.printLocalDone || this.printSelectedAgain.length ==  0) return this.printSelected;
            return this.printSelectedAgain.map(x=> this.printSelected[x]);
        },
        queueStates: function () {
            return ['', ...this.jPrintStates];
        }
    },
    methods: {
        checkPermission: () => {
            console.log('Hey! Listen!');
        },
        editBadgeFormat: function (selectedFormat) {
            console.log('edit badge selected from grid', selectedFormat);
            let that = this;
            that.loading = true;
            that.fSelected = {};
            admin.genericGet(this.authToken, 'Badge/Format/' + selectedFormat.id, null, function (editFormat) {
                that.fSelected = editFormat;
                that.loading = false;
                that.fEdit = true;
                that.$nextTick(() => {
                    that.fModified = false;
                })

            }, function () {
                that.loading = false;
            })
        },
        saveBadgeFormat: function () {
            console.log('saving badge', this.fSelected);
            var url = 'Badge/Format';
            if (this.fSelected.id != undefined)
                url = url + '/' + this.fSelected.id;
            let that = this;
            that.loading = true;
            admin.genericPost(this.authToken, url, this.fSelected, function (SavedDetails) {
                that.fSelected = {};
                that.loading = false;
                that.fEdit = false;
                that.fSaved = true;
                that.fSavedDetails = SavedDetails;
                that.$nextTick(() => {
                    that.bModified = false;
                })

            }, function () {
                that.loading = false;
            })
        },
        createBadgeFormat: function () {
            this.fEdit = true;
            this.fSelected = {};
        },

        selectBadgeFormat: function (selectedFormat) {
            console.log('selected badge for print from grid', selectedFormat);
            this.loading = true;
            this.fSelected = {};
            console.log('fetching format', selectedFormat)
            admin.genericGet(this.authToken, 'Badge/Format/' + selectedFormat.id, null, (editFormat) => {
                this.fSelected = editFormat;
                this.loading = false;
                this.printStage = 2;

            }, function () {
                this.loading = false;
            })
        },
        receiveResults: function (badges) {
            this.printAvailable = badges;
        },
        selectAllOfType: function (badgeData) {
            //Fetch existing keys
            var existingIDs = this.printSelected.map(x => x.uuid);
            //Add anything that isn't there but also matches the badgeData
            this.printSelected.push(...this.printAvailable.filter(x =>
                existingIDs.findIndex(p => p == x.uuid) == -1
                && x.context_code == badgeData.context_code
                && x.badge_type_id == badgeData.badge_type_id
            ))

        },
        enqueueBadgePrintingBatch: function () {
            console.log('enqueue selected badges for batch print', structuredClone(this.printSelected));
            this.loading = true;
            admin.genericPost(this.authToken, 'Badge/Format/' + this.fSelected.id + '/Badges/BatchPrint', {
                overridePaymentRequirement: this.badgeSelectParams.allowUnpaid,
                meta: {
                    stationName: this.printToStation || this.$store.state.station.servicePrintJobsAs || 'Batch'
                },
                badges: this.printSelected.map(x => {return { uuid : x.uuid, context_code : x.context_code, id: x.id }})
            },
                (result) => {
                    for (var ix = 0; ix < this.printSelected.length; ix++) {
                        var cur = this.printSelected[ix];
                        if (result['failed'][cur.uuid]) {
                            console.log('noting batch submit failure', cur.uuid, result['failed'][cur.uuid])
                            this.$set(cur, 'failed', result['failed'][cur.uuid])
                        } else {
                            this.$set(this.printSelected, ix, result['enqueued'][cur.uuid])
                            this.$set(this.printJobStatuses, result['enqueued'][cur.uuid].printjob_id,'Queued')
                        }
                    }

                    //If we're doing the printing, trigger the print panel
                    if (this.printToStation == '')
                        this.printPanel = true;
                    else
                        this.printLocalDone = true;

                    this.loading = false;
                }, (err) => {
                    this.loading = false;
                    console.log('failed enqueue', err)
                })
        },
        updateBadgePrintingBatch: function(newState, result){
            console.log('enqueue selected badges for batch print', structuredClone(this.printSelected));
            this.loading = true;
            admin.genericPatch(this.authToken, 'Badge/Format/' + this.fSelected.id + '/Badges/BatchPrint', {
                badges: this.badgesForPrint.map(x => {return { uuid : x.uuid, context_code : x.context_code, id: x.id ,
                    printjob_id: x.printjob_id,
                    printjob_state: newState,
                    printjob_result: result
                }}),
            }, (result) => {
                this.loading = false;

            }, (err) =>  {
                this.loading = false;

            });
        },
        retryPrintingBatch: function(){
            
            //If we're doing the printing, just trigger the print panel
            if (this.printToStation == '') {                
                this.printPanel = true;
            } else {
                //Otherwise, just update all the selected ones to be Queued (again)
                this.updateBadgePrintingBatch('Queued');
            }
        },
        completePrintingBatch: function(){
            //Signal completion if we're local. Remote automatically completes the jobs
             if(this.printToStation == '')
                this.updateBadgePrintingBatch('Completed', 'Printed locally')
            //Clean up everything and go back to the badge selection step
            this.printLocalDone = false;
            this.printSelectedAgain = [];
            this.printSelected = [];
            this.printJobStatuses = {};
            this.printStage = 2;

        },
        badgePrintGetJobStatus: function(){

            admin.genericPost(this.authToken, 'Badge/Format/' + this.fSelected.id + '/Badges/BatchPrintRefresh', 
                this.badgesForPrint.map(x => x.printjob_id)
            , (result) => {
                result.forEach(job => {
                    this.$set(this.printJobStatuses,job.id, job.state)
                });
                
            }, (err) =>  {
                console.log('Error refreshing jobs', err)
                
            });
        },
        badgePrintJobStatus: function(badge){
            if (badge.printjob_id == undefined){
                return 'Not Queued';                    
            }
            var result = 'Print job ' + badge.printjob_id + ': '  + this.printJobStatuses[badge.printjob_id];
            return result;
        },
        enqueueBadgeForPrinting: function (selectedBadge) {
            console.log('enqueue single badge for batch print from grid', selectedBadge);
            this.loading = true;
            admin.genericPost(this.authToken, 'Badge/Format/' + this.fSelected.id + '/Badges/' + selectedBadge.context_code + '/' + selectedBadge.id, {
                state: 'Batch',
                meta: {
                    stationName: this.$store.state.station.servicePrintJobsAs || 'Batch'
                }
            },
                (result) => {
                    this.loading = false;
                }, (err) => {
                    this.loading = false;
                })
        },

        editPrintJob: function (selectedFormat) {
            console.log('edit print job from grid', selectedFormat);
            this.loading = true;
            this.jSelected = {};
            admin.genericGet(this.authToken, 'Badge/PrintJob/' + selectedFormat.id, {
                includeFormat: true
            }, (editPrintJob) => {
                this.jSelected = editPrintJob;
                this.loading = false;
                this.jEdit = true;
            }, () => {
                this.loading = false;
            })
        },

        savePrintJob: function () {
            console.log('saving badge', this.jSelected);
            var url = 'Badge/PrintJob';
            if (this.jSelected.id != undefined)
                url = url + '/' + this.jSelected.id;
            let that = this;
            that.loading = true;
            admin.genericPost(this.authToken, url, this.jSelected, function (SavedDetails) {
                that.jSelected = {};
                that.loading = false;
                that.jEdit = false;
            }, function () {
                that.loading = false;
            })
        },

    },
    watch: {
        $route() {
            this.$nextTick(this.checkPermission);
        },
        printPanel: async function (shown) {
            //This should only happen when locally printing anyways
            if (shown == false) {
                this.printLocalDone = true;
                this.printSelectedAgain = [];
            } else {
                await this.$nextTick();
                //Print and close
                setTimeout(() => {
                    window.print();
                    this.printPanel = false;
                }, 430);
                //Also inform the batched that w're printing
                this.updateBadgePrintingBatch('InProgress');
            }
        },
        printLocalDone: function(isDone){
            if(isDone){
                //Start up the queue watcher
                this.printQueuePollTimer = setInterval(() => this.badgePrintGetJobStatus(), 5000);

            } else {
                //Stop it
                clearInterval(this.printQueuePollTimer);
                this.printQueuePollTimer = 0;
            }
        }

    },
    created() {
        this.checkPermission();
        //this.doSearch();
        this.$emit('updateSubTabs', [{
            key: 'BadgeFormats',
            text: 'Badge Formats',
            title: 'Badge Formats'
        },
        {
            key: 'Print',
            text: 'Pre-Printing',
            title: 'Badge Pre-Printing'
        },
        {
            key: 'Queue',
            text: 'Printing Queue',
            title: 'Printing Queue'
        },
        ]);
    },
    beforeDestroy: function(){        
        console.log('Shutting down local Batch Print Daemon')
        clearInterval(this.printQueuePollTimer);
        this.printPanel = false;
    }
};
</script>
