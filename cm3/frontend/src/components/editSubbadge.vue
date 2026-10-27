<template>
    <v-form ref="fGenInfo" v-model="validGenInfo">

        <v-container>
            <badgeGenInfo v-model="editedItem" />

            <h3>Notify email</h3>
            <v-row>
                <v-col cols="12" sm="6" md="6">
                    <v-text-field label="Email address to send badge to" v-model="editedItem.notify_email"
                        :rules="RulesEmail"></v-text-field>
                </v-col>
                <v-col cols="12" sm="6" md="6">
                    <v-checkbox dense hide-details v-model="editedItem.can_transfer" true-value="1" false-value="0">
                        <template v-slot:label>
                            <small>Allow badge transfer to the owner of this email.</small>
                        </template>
                    </v-checkbox>
                </v-col>
            </v-row>

            <h3>In case of Emergency</h3>
            <v-row>

                <v-col cols="12" sm="6" md="3">
                    <v-text-field label="Emergency Contact Name" v-model="editedItem.ice_name"></v-text-field>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-text-field label="Relationship" v-model="editedItem.ice_relationship"></v-text-field>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-text-field label="Email address" v-model="editedItem.ice_email_address"
                        :rules="RulesEmail"></v-text-field>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-text-field label="Phone Number" v-model="editedItem.ice_phone_number"
                        :rules="RulesPhone"></v-text-field>
                </v-col>
            </v-row>
        </v-container>
    </v-form>
</template>

<script>
import badgeGenInfo from '@/components/badgeGenInfo.vue';
export default {
    components: {
        badgeGenInfo,
    },
    props: {
        'value': {
            type: Object
        },
        'readonly': {
            type: String
        },
    },
    data() {
        return {

            skipEmitOnce: false,
            validGenInfo: false,
            editedItem: this.value || {
                real_name: '',
                fandom_name: '',
                name_on_badge: 'Real Name Only',
                date_of_birth: '',
                notify_email: '',
                can_transfer: 1,
                ice_name: '',
                ice_relationship: '',
                ice_email_address: '',
                ice_phone_number: '',
            },
        RulesEmail: [
            (v) => !v || /.+@.+\..+/.test(v) || 'E-mail must be valid',
        ],
        RulesPhone: [
            (v) => !v || v.length > 6 || 'Phone number too short',
            /* v =>  !v || /^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/.test(v) || 'Phone number should be valid' */
        ],

        };
    },
    computed: {
    },
    methods: {

    },
    watch: {
        validGenInfo(isValid) {
            this.$emit('valid', isValid);
        },
        editedItem: {
            handler(newData) {
                this.$emit('input', newData);
            },
            deep: true,
            immediate: true
        },
        value: {
            handler(newValue) {
                this.editedItem = newValue;
            },
            deep: true,
            immediate: true
        },
    },
};
</script>
