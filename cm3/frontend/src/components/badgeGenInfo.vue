<template>
<v-form ref="fGenInfo"
        v-model="validGenInfo">
    <v-row>
        <v-col cols="12"
               md="6">
            <v-text-field v-model="model.real_name"
                          :counter="500"
                          :rules="RulesName"
                          :label="application_name1"
                          required></v-text-field>
        </v-col>

        <v-col cols="12"
               md="6">
            <v-text-field v-model="model.fandom_name"
                          :counter="255"
                          :rules="RulesNameFandom"
                          :label="application_name2 +' (Optional)'"></v-text-field>
        </v-col>
        <v-col cols="12"
               md="6">
            <v-select v-show="model.fandom_name"
                      v-model="model.name_on_badge"
                      :rules="RulesNameDisplay"
                      :items="name_on_badgeOptions"
                      label="Display on badge"></v-select>
        </v-col>
        <v-col cols="12"
               v-if="!hide_dob"
               md="6">
            <v-menu ref="menuBDay"
                    v-model="menuBDay"
                    :close-on-content-click="false"
                    transition="scale-transition"
                    offset-y
                    min-width="290px">
                <template v-slot:activator="{ on }">
                    <v-text-field v-model="model.date_of_birth"
                                  type="date"
                                  label="Date of Birth"
                                  v-on="on"
                                  :rules="RulesRequired"></v-text-field>
                </template>
                <v-date-picker ref="pickerBDay"
                               v-model="model.date_of_birth"
                               :max="new Date().toISOString().substr(0, 10)"
                               min="1920-01-01"
                               @change="saveBDay"
                               :active-picker.sync="bdayActivePicker"></v-date-picker>
            </v-menu>
        </v-col>
    </v-row>
</v-form>
</template>

<script>
export default {
    components: {},
    props: {
        'value': {
            type: Object
        },
        'readonly': {
            type: String
        },
        'application_name1': {
            type: String,
            default: 'Real Name'
        },
        'application_name2': {
            type: String,
            default: 'Fandom Name'
        },
        'hide_dob': {
            type: Boolean
        }
    },
    data() {
        return {

            skipEmitOnce: false,
            validGenInfo: false,
            model: this.value || {
                real_name: "",
                fandom_name: "",
                name_on_badge: "Real Name Only",
                date_of_birth: "",
            },

            menuBDay: false,
            bdayActivePicker: 'YEAR',

            RulesRequired: [
                (v) => !!v || 'Required',
            ],
            RulesName: [
                (v) => !!v || 'Name is required',
                (v) => (v && v.length <= 500) || 'Name must be less than 500 characters',
            ],
            RulesNameFandom: [

                (v) => (v == '' || (v && v.length <= 255)) || 'Name must be less than 255 characters',
            ],
            RulesNameDisplay: [
                (v) => (((this.model.fandom_name || '').length < 1) || (this.model.fandom_name.length > 0 && v != '')) || 'Please select a display type',
            ],
        };
    },
    computed: {
        name_on_badgeOptions() {
            return [{
                text: this.application_name2 + ' Large, ' + this.application_name1 + ' Small',
                value: 'Fandom Name Large, Real Name Small'
            }, {
                text: this.application_name1 + ' Large, ' + this.application_name2 + ' Small',
                value: 'Real Name Large, Fandom Name Small'
            }, {
                text: this.application_name1 + ' Only',
                value: 'Real Name Only'
            }, {
                text: this.application_name2 + ' Only',
                value: 'Fandom Name Only'
            }];
        },
    },
    methods: {

        saveBDay(date) {
            this.$refs.menuBDay.save(date);
            this.model.date_of_birth = this.model.date_of_birth;
        },
    },
    watch: {
        validGenInfo(isValid) {
            this.$emit('valid', isValid);
        },        
        model: {
            handler(newData) {
                newData.name_on_badge = (!!this.model.fandom_name) ? this.model.name_on_badge : 'Real Name Only';
                this.$emit('input', newData);
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
        menuBDay(val) {
            // Whenever opening the picker, always reset it back to start with the Year
            val && setTimeout(() => (this.bdayActivePicker = 'YEAR'));
        },
    },
};
</script>
