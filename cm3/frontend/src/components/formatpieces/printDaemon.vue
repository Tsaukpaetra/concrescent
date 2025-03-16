<template>
    <div>
        <v-tooltip bottom>
            <template v-slot:activator="{ on, attrs }">
                <v-btn icon @click="TogglePause" v-bind="attrs" v-on="on">
                    <v-badge :value="queueTotal" :content="queueTotal">
                        <v-icon>mdi-{{ printIcon }}</v-icon>
                    </v-badge>
                </v-btn>
            </template>
            <v-card>
                Status: {{ runState }}
                <v-list dense>
                    <v-list-item v-for="(q, i) in queue" :key="i">
                        <v-list-item-icon>{{ q.id }}</v-list-item-icon>
                        <v-list-item-content>{{ formatName(q.format_id) }}</v-list-item-content>
                    </v-list-item>
                </v-list>
            </v-card>

        </v-tooltip>
        <v-dialog v-model="printPanel" eager fullscreen transition="none">
            <v-card v-if="printPanel" :class="{ 'printing': printPanel, printHeaderHidden: true }">
                <v-sheet color="white" class="mx-auto page-break">
                    <badgeFullRender :format="selectedBadgeFormat" :badge="selectedBadge" />
                </v-sheet>
            </v-card>
        </v-dialog>
        <v-snackbar v-model="prepPrint" :timeout="printDelay" top>
            {{ userPaused ? 'Printing is paused!' : 'Preparting to print' }}
            <template v-slot:action="{ attrs }">
                <v-btn v-if="!userPaused" color="blue" text v-bind="attrs" @click="TogglePause">
                    Pause
                </v-btn>
            </template>
        </v-snackbar>
        <v-snackbar v-model="hiMessage" :timeout="3000" top>
            {{ 'Remote printing as "' + printerName + '"' }}
            <template v-slot:action="{ attrs }">
                <v-btn v-if="!userPaused" color="blue" text v-bind="attrs" @click="TogglePause">
                    Pause
                </v-btn>
            </template>
        </v-snackbar>
    </div>
</template>

<script>
import admin from '../../api/admin';
import badgeFullRender from '@/components/badgeFullRender.vue';
import {
    debounce
} from '@/plugins/debounce';
import {
    mapState,
    mapGetters,
    mapActions
} from 'vuex';
export default {
    components: {
        badgeFullRender
    },
    props: {},
    data() {
        return {
            queue: [],
            queueTotal: 0,
            hiMessage: true,
            //Can be Ready, Polling, Printing, Paused, Error
            runState: 'Ready',
            userPaused: false,
            PollTimer: null,
            prepPrint: null,
            printDelay: 3200,
            printPanel: false,
            selectedBadge: {},
            selectedBadgeFormat: {},
            cachedFormats: []
        };
    },
    methods: {
        TogglePause: function () {
            console.log('Print daemon toggle pause, currently', this.userPaused)
            if (this.userPaused) {
                this.userPaused = false;
                if (this.cJob != undefined) {
                    this.PrintNextJob();
                } else {
                    this.runState = 'Ready';
                }
            } else {
                this.userPaused = true;
                if (this.runState == 'Ready')
                    this.runState = 'Paused';
            }

        },
        PollJobs: async function () {
            //this.printPanel = !this.printPanel;
            //Prevent re-entry
            if (this.runState != 'Ready' && this.runState != 'Paused')
                return;
            this.runState = 'Polling'

            admin.genericGetList(this.authToken, 'Badge/PrintJob', {
                full: true,
                state: 'Queued',
                stationName: this.printerName,
                itemsPerPage: 10
            },
                (queue, queueTotal) => {
                    this.queue = queue;
                    this.queueTotal = queueTotal;
                    this.runState = this.userPaused ? 'Paused' : queueTotal > 0 ? 'Printing' : 'Ready';
                    setTimeout(() => {
                        this.FetchFormat();
                    }, 100);
                    if (queueTotal && !this.userPaused) {
                        //Detect if we are in the station config and skip the countdown if so
                        if (this.$route.name == 'config') {
                            this.PrintNextJob()
                        } else {
                            this.prepPrint = true;
                        }
                    }
                }, (err) => {
                    //Shrug?
                    console.log('Print Daemon error', err)
                    this.runState = 'Ready';
                })
        },
        PrintNextJob: function () {
            if (this.userPaused) {
                this.runState = 'Paused'
                this.printPanel = false;
                return;
            }
            if (this.cJob == undefined) {
                this.runState = 'Ready';
                this.printPanel = false;
                console.log('Daemon: Done printing')
                return;
            }

            if (this.cBadgeFormat == undefined) {
                this.FetchFormat();
                return;
            }
            this.printPanel = true;
            this.selectedBadge = this.cJob.data;
            this.selectedBadgeFormat = this.cBadgeFormat;

            //Print and close
            setTimeout(() => {
                window.print();
                this.PostPrint();
            }, 130);

        },
        PostPrint: function (completedLocally) {

            admin.genericPost(this.authToken, "Badge/PrintJob/" + this.cJob.id, {
                state: 'Completed',
            }, (printJob) => {
                this.queue.shift();
                this.queueTotal--;

                if (this.cJob == undefined) {
                    this.runState = 'Ready';
                    this.printPanel = false;
                    console.log('Daemon: Done printing')
                    return;
                } else {
                    //Set up to go again!
                    setTimeout(() => {
                        this.PrintNextJob();
                    }, this.printConfig.cycleDelay);
                }
            }, (err) => {

                this.runState = 'Error';
                this.printPanel = false;
                console.log('Daemon: Error printing', err);
            })
        },
        FetchFormat: function () {
            if (this.cBadgeFormat != undefined)
                return;
            if (this.cJob == undefined) return;
            console.log('Daemon: Fetching format', this.cJob.format_id)

            admin.genericGet(this.authToken, 'Badge/Format/' + this.cJob.format_id, null, (format) => {
                console.log('Received format map', format)
                this.cachedFormats.push(format);
                console.log('cachedFormats', this.cachedFormats)
                this.PrintNextJob()
            }, (err) => {
                console.log('Could not load badge format', err)
                this.runState = 'Error'
            })
        },
        formatName: function (id) {
            var format = this.cachedFormats.find((i) => i.id == id);
            return format?.name || '[[Loading]]';
        },
    },
    watch: {
        prepPrint(shown) {
            if (!shown) {
                //Is now hidden, print now
                this.PrintNextJob();
            }
        },
        printConfig: {
            handler(newConfig) {
                clearInterval(this.PollTimer);
                this.PollTimer = setInterval(() => this.PollJobs(), newConfig.pollDelay);
            

            }, deep: true
        }
    },
    computed: {
        ...mapState({
            printConfig: (state) => state.station.printConfig,
            printerName: (state) => state.station.servicePrintJobsAs,
        }),
        authToken: function () {
            return this.$store.getters['mydata/getAuthToken'];
        },
        printIcon: function () {
            let result = 'printer-pos';
            switch (this.runState) {
                case 'Ready':
                    //nothing
                    break;
                case 'Polling':
                    result += '-refresh';
                    break;
                case 'Printing':
                    result += '-play';
                    break;
                case 'Paused':
                    result += '-pause';
                    break;
            }
            return result;
        },
        cJob: function () {
            let a = this.queue;
            return a[0];
        },
        cBadgeFormat() {
            if (this.cJob == undefined) return undefined;
            return this.cachedFormats.find((i) => i.id == this.cJob.format_id);
        },
        // selectedBadge() {
        //     if (this.cJob == undefined) return {};
        //     return this.cJob.data;
        //
        // },
    },
    mounted() {
        console.log('Running Print Daemon')
        //If we're in the config route, poll immediately, otherwise set it after a few seconds
        setTimeout(()=>{
            this.PollJobs();
            this.PollTimer = setInterval(() => this.PollJobs(), this.printConfig.pollDelay);
        }, this.$route.name == 'config' ? 200 : 3000)


    },
    beforeDestroy: function () {
        console.log('Shutting down Print Daemon')
        clearInterval(this.PollTimer);
        this.printPanel = false;

    },

};
</script>

<style scoped></style>
