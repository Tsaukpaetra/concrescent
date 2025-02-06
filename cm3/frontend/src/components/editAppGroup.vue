<template>
    <v-form ref="fForm" v-model="validForm">
        <v-container fluid>
            <v-row>
                <v-col cols="6" sm="8" md="6">
                    <v-text-field label="name" v-model="model.name" :rules="RulesRequired">
                    </v-text-field>
                </v-col>
                <v-col cols="3" sm="3" md="2">
                    <v-text-field label="Context Code" :rules="RulesRequired" v-model="model.context_code">
                    </v-text-field>
                </v-col>
                <v-col cols="2">
                    <v-checkbox dense hide-details v-model="model.active">
                        <template v-slot:label>
                            Active
                        </template>
                    </v-checkbox>
                </v-col>
                <v-col cols="2">
                    <v-checkbox dense hide-details v-model="model.can_assign_slot">
                        <template v-slot:label>
                            Can assign slot
                        </template>
                    </v-checkbox>
                </v-col>
            </v-row>
            <v-row>
                <v-col cols="12" sm="6" md="3">
                    <v-text-field label="Primary Name" placeholder="Real Name" :rules="RulesRequired"
                        v-model="model.application_name1" />
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-text-field label="Secondary Name" placeholder="Fandom Name" v-model="model.application_name2" />
                </v-col>

                <v-col cols="12">
                    <v-textarea label="Public Description" v-model="model.description" />
                </v-col>
            </v-row>
            <v-row>
                <v-col cols="3">
                    <v-text-field label="Menu Icon" v-model="model.menu_icon" hint="" /><a target="_blank"
                        href='https://pictogrammers.com/library/mdi/'>Material Design Icon name</a>
                </v-col>
                <v-col>
                    Preview<br />
                    <v-card elevation="3">
                        <v-icon class="ma-8" size="200">mdi-{{ model.menu_icon }}</v-icon>
                    </v-card>
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
                id:nullIfEmptyOrZero(this.model.id),
                "context_code": this.model.context_code,
                "active": this.model.active ? 1 : 0,
                "can_assign_slot": this.model.can_assign_slot ? 1 : 0,
                "name":this.model.name,
                "menu_icon": this.model.menu_icon,
                "description": this.model.description,
                "application_name1": nullIfEmptyOrZero(this.model.application_name1),
                "application_name2": nullIfEmptyOrZero(this.model.application_name2),
                "notes": this.model.notes,
            }
        },
    },
    methods: {

        saveStartDate(date) {
            this.$refs.menuStartDate.save(date);
            this.model.start_date = this.model.start_date;
        },
        saveEndDate(date) {
            this.$refs.menuEndDate.save(date);
            this.model.end_date = this.model.end_date;
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
