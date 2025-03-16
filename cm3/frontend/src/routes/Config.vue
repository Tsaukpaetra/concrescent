<template>
    <v-container fluid fill-height>
        <v-layout align-center justify-center>

            <v-card class="px-4">
                <v-card-text>
                    <v-container>
                        <v-row>
                            <v-col cols="12" md="8">
                                <v-switch v-model="kioskMode"
                                    :label="`This ${kioskMode ? 'is' : 'is not'} a Kiosk.`"></v-switch>
                            </v-col>
                        </v-row>
                        <v-row>
                            <v-col cols="12" md="6">
                                <v-switch v-model="remotePrinting"
                                    :label="`Printing ${remotePrinting ? 'to remote' : 'locally'}.`"></v-switch>
                            </v-col>

                            <v-col cols="12" md="6" v-if="remotePrinting">
                                <v-text-field v-model="preferredRemotePrinter"
                                    label="Remote printer name"></v-text-field>
                            </v-col>
                        </v-row>
                        <v-row>

                            <v-col cols="12" md="6">
                                <v-switch v-model="serviceRemoteJobs"
                                    :label="`${serviceRemoteJobs ? 'Servicing' : 'Not servicing'} remote jobs.`"></v-switch>
                            </v-col>

                            <v-col cols="12" md="6">
                                <v-text-field v-model="servicePrintJobsAs" label="Local printer's name"></v-text-field>
                            </v-col>
                        </v-row>
                        <v-row>
                            <v-expansion-panels>
                                <v-expansion-panel>
                                    <v-expansion-panel-header>
                                        Print Daemon advanced
                                    </v-expansion-panel-header>
                                    <v-expansion-panel-content>
                                        <v-row>

                                            <v-col cols="12" md="6">
                                                <v-switch v-model="printConfig.printFull"
                                                    persistent-hint hint="(Doesn't currently work)"
                                                    :label="`${printConfig.printFull ? 'P' : 'Not p'}riting background image.`"></v-switch>
                                            </v-col>
                                            <v-col cols="12" md="6">
                                                <v-switch v-model="printConfig.batchMode" 
                                                    persistent-hint hint="(Doesn't currently work)"
                                                    :label="`${printConfig.batchMode ? 'P' : 'Not p'}rinting in batches.`"></v-switch>
                                            </v-col>
                                            <v-col cols="12" md="6">
                                                <v-slider v-model="printConfig.cycleDelay" class="align-center"
                                                    hide-details label="Time between print cycles" :min="100"
                                                    :max="5000" step="50">
                                                    <template v-slot:append>
                                                        <v-text-field v-model="printConfig.cycleDelay" class="mt-0 pt-0"
                                                            hide-details single-line type="number" suffix="ms"
                                                            hide-spin-buttons style="width: 70px"></v-text-field>
                                                    </template></v-slider>
                                            </v-col>
                                            <v-col cols="12" md="6">
                                                <v-slider v-model="printConfig.pollDelay" class="align-center"
                                                    hide-details label="Time between queue polls" :min="3000"
                                                    :max="30000" step="2000">
                                                    <template v-slot:append>
                                                        <v-text-field v-model="printConfig.pollDelay" class="mt-0 pt-0"
                                                            hide-details single-line type="number" suffix="ms"
                                                            hide-spin-buttons style="width: 90px"></v-text-field>
                                                    </template></v-slider>
                                            </v-col>
                                        </v-row>
                                    </v-expansion-panel-content>
                                </v-expansion-panel>
                            </v-expansion-panels>
                        </v-row>
                    </v-container>
                </v-card-text>
                <v-card-actions>

                </v-card-actions>
            </v-card>
        </v-layout>
    </v-container>
</template>

<script>
// @ is an alias to /src
// import HelloWorld from '@/components/HelloWorld.vue'

import {
    mapGetters,
    mapActions
} from 'vuex'
export default {
    data: function () {
        return {
            printConfig: this.$store.state.station.printConfig
        }
    },
    computed: {
        'kioskMode': {
            set(newMode) {
                this.$store.commit('station/setKioskMode', newMode)
            },
            get() {
                return this.$store.state.station.kioskMode
            }
        },
        'remotePrinting': {
            set(newval) {
                this.$store.commit('station/setremotePrinting', newval)
            },
            get() {
                return this.$store.state.station.remotePrinting
            }
        },
        'preferredRemotePrinter': {
            set(newval) {
                this.$store.commit('station/setPreferredRemotePrinter', newval)
            },
            get() {
                return this.$store.state.station.preferredRemotePrinter
            }
        },
        'serviceRemoteJobs': {
            set(newval) {
                this.$store.commit('station/setServiceRemoteJobs', newval)
            },
            get() {
                return this.$store.state.station.serviceRemoteJobs
            }
        },
        'servicePrintJobsAs': {
            set(newval) {
                this.$store.commit('station/setServicePrintJobsAs', newval)
            },
            get() {
                return this.$store.state.station.servicePrintJobsAs
            }
        },
        // 'printConfig': {
        //     set(newval) {
        //         console.log('set config')
        //         this.$store.commit('station/setPrintConfig', newval)
        //     },
        //     get() {
        //         return this.$store.state.station.printConfig
        //     }
        // },
    },
    watch: {
        printConfig: {
            handler(newVal) {
                this.$store.commit('station/setPrintConfig', newVal)
            },
            deep: true
        },
        '$store.state.station.printConfig': {
            handler(newVal) {
                this.printConfig = newVal;
            },
            immediate: true
        }
    },
    components: {
        //    HelloWorld
    },
};
</script>
