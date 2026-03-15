<template>
    <v-form ref="fForm" v-model="validForm">
        <v-container fluid>
            <v-row>
                <v-col cols="6" sm="8" md="6">
                    <v-text-field label="Event name" v-model="model.display_name" :rules="RulesRequired">
                    </v-text-field>
                </v-col>
                <v-col cols="3" sm="3" md="2">
                    <v-text-field label="Short Code" :rules="RulesRequired" counter="8" v-model="model.shortcode">
                    </v-text-field>
                </v-col>
                <v-col cols="2">
                    <v-checkbox dense hide-details v-model="model.active">
                        <template v-slot:label>
                            Active
                        </template>
                    </v-checkbox>
                </v-col>
            </v-row>
        <v-row>

            <v-col cols="12"
                   md="6">
                <v-menu ref="menuStartDate"
                        v-model="menuStartDate"
                        :close-on-content-click="false"
                        transition="scale-transition"
                        offset-y
                        min-width="290px">
                    <template v-slot:activator="{ on }">
                        <v-text-field v-model="model.date_start"
                                      :rules="RulesRequired"
                                      label="Event Starts on"
                                      placeholder="No start date"
                                      persistent-placeholder
                                      v-on="on"></v-text-field>
                    </template>
                    <!--TODO: Set this based on event end! :max="new Date().toISOString().substr(0, 10)"saveStartDate -->
                    <v-date-picker ref="pickerStartDate"
                                   v-model="model.date_start"
                                   min="2000-01-01"
                                   @change="saveStartDate"></v-date-picker>
                </v-menu>
            </v-col>
            <v-col cols="12"
                   md="6">
                <v-menu ref="menuEndDate"
                        v-model="menuEndDate"
                        :close-on-content-click="false"
                        transition="scale-transition"
                        offset-y
                        min-width="290px">
                    <template v-slot:activator="{ on }">
                        <v-text-field v-model="model.date_end"
                                      :rules="RulesRequired"
                                      placeholder="No end date"
                                      persistent-placeholder
                                      label="Event ends on"
                                      v-on="on"></v-text-field>
                    </template>
                    <!--TODO: Set this based on event end! :max="new Date().toISOString().substr(0, 10)"saveEndDate -->
                    <v-date-picker ref="pickerEndDate"
                                   v-model="model.date_end"
                                   min="2000-01-01"
                                   @change="saveEndDate"></v-date-picker>
                </v-menu>
            </v-col>
        </v-row>
        <v-row>

            <v-col cols="12"
                   md="6">
                <v-menu ref="menuStaffStartDate"
                        v-model="menuStaffStartDate"
                        :close-on-content-click="false"
                        transition="scale-transition"
                        offset-y
                        min-width="290px">
                    <template v-slot:activator="{ on }">
                        <v-text-field v-model="model.staff_start"
                                      :rules="RulesRequired"
                                      label="Staff starting"
                                      placeholder="No start date"
                                      persistent-placeholder
                                      v-on="on"></v-text-field>
                    </template>
                    <!--TODO: Set this based on event end! :max="new Date().toISOString().substr(0, 10)"saveStartDate -->
                    <v-date-picker ref="pickerStaffStartDate"
                                   v-model="model.staff_start"
                                   min="2000-01-01"
                                   @change="saveStaffStartDate"></v-date-picker>
                </v-menu>
            </v-col>
            <v-col cols="12"
                   md="6">
                <v-menu ref="menuStaffEndDate"
                        v-model="menuStaffEndDate"
                        :close-on-content-click="false"
                        transition="scale-transition"
                        offset-y
                        min-width="290px">
                    <template v-slot:activator="{ on }">
                        <v-text-field v-model="model.staff_end"
                                      :rules="RulesRequired"
                                      placeholder="No end date"
                                      persistent-placeholder
                                      label="Staff ending"
                                      v-on="on"></v-text-field>
                    </template>
                    <!--TODO: Set this based on event end! :max="new Date().toISOString().substr(0, 10)"saveEndDate -->
                    <v-date-picker ref="pickerStaffEndDate"
                                   v-model="model.staff_end"
                                   min="2000-01-01"
                                   @change="saveStaffEndDate"></v-date-picker>
                </v-menu>
            </v-col>
        </v-row>
            <v-row>
                <v-col>
                    <v-textarea label="Notes" v-model="model.notes" />
                </v-col>
            </v-row>

        </v-container>
    </v-form>
</template>

<script>
import admin from '../api/admin';
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
    },
    data() {
        return {
            skipEmitOnce: false,
            validForm:false,
            model: this.value,
            
            menuStartDate: false,
            menuEndDate: false,
            menuStaffStartDate: false,
            menuStaffEndDate: false,

            RulesRequired: [
                (v) => !!v || 'Required',
            ],
        };
    },
    computed: {
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
            'authToken': 'getAuthToken',
        }),
        ...mapGetters('products', {
            'badgeContexts': 'badgeContexts',
        }),
        result() {
            return {
                id:undefinedIfEmptyOrZero(this.model.id),
                "shortcode": this.model.shortcode,
                "active": this.model.active ? 1 : 0,
                "display_name": this.model.display_name,
                "date_start" : nullIfEmptyOrZero(this.model.date_start),
                "date_end" : nullIfEmptyOrZero(this.model.date_end),
                "staff_start" : nullIfEmptyOrZero(this.model.staff_start),
                "staff_end" : nullIfEmptyOrZero(this.model.staff_end),
                "notes": nullIfEmptyOrZero(this.model.notes),
            }
        },
    },
    methods: {

        saveStartDate(date) {
            this.$refs.menuStartDate.save(date);
            this.model.date_start = this.model.date_start;
        },
        saveEndDate(date) {
            this.$refs.menuEndDate.save(date);
            this.model.date_end = this.model.date_end;
        },
        saveStaffStartDate(date) {
            this.$refs.menuStaffStartDate.save(date);
            this.model.staff_start = this.model.staff_start;
        },
        saveStaffEndDate(date) {
            this.$refs.menuStaffEndDate.save(date);
            this.model.staff_end = this.model.staff_end;
        },
    },
    watch: {
        result(newData) {
            if (this.skipEmitOnce == true) {
                this.skipEmitOnce = false;
                return;
            }
            var isValid = this.$refs.fForm.validate();
            this.$emit('valid', isValid);
                // console.log('sending updated value', newData)
            this.$emit('input', newData);
        },
        value: {
            handler: function(newValue) {
                //Splat the input into the form
                this.skipEmitOnce = true;
                // console.log('new value received', newValue)
                this.model = {
                    ...newValue
                };
            },
        }
    },
    created() {
    }
};
</script>
