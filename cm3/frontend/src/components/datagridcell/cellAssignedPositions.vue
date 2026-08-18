<template>
    <v-toolbar dense flat color="transparent">
        <v-tooltip v-for="(id, ix) in position_ids" :key="ix" left>
            <template v-slot:activator="{ on, attrs }">
                <v-chip v-bind="attrs" v-on="on" :color="onboarded[ix] ? 'primary' : 'secondary'">
                    <v-avatar left v-if="position(position_ids[ix]).Position_IsExec">
                        <v-icon>mdi-crown</v-icon>
                    </v-avatar>
                    {{position(position_ids[ix]).Department_Name}}: {{position(position_ids[ix]).Position_Name}}</v-chip>
            </template>
            <v-card>
                <v-card-title>{{ position(position_ids[ix]).Department_Name }}
                    <v-icon right  v-if="position(position_ids[ix]).Position_IsExec">
                        mdi-crown
                    </v-icon>
                </v-card-title>
                <v-card-subtitle>{{ position(position_ids[ix]).Position_Name }}</v-card-subtitle>
                <v-card-text>{{ position(position_ids[ix]).Position_Description }}</v-card-text>
            </v-card>
        </v-tooltip>
        <!-- <v-spacer></v-spacer>
        <v-btn small icon><v-icon>mdi-pencil</v-icon></v-btn> -->
    </v-toolbar>
</template>

<script>
import {
    mapActions,
    mapGetters
} from 'vuex';
import admin from '../../api/admin';
export default {
    components: {},
    props: ['position_ids', 'onboarded'],
    data() {
        return {
            skipEmitOnce: false,
            currentValue: this.value,
            loading: false,
        };
    },
    computed: {
        ...mapGetters('products', {
            'allStaffPositions': 'allStaffPositions',
            'categoryList': 'locationCategories'
        }),
        authToken: function () {
            return this.$store.getters['mydata/getAuthToken'];
        },
        position() {
            return (id) => {
                return this.allStaffPositions.find(x => x.Position_Id == id) || {
                    "Department_Id": 0,
                    "Department_Name": "[Loading]",
                    "Department_Description": null,
                    "Position_Id": id,
                    "Position_Name": "[Loading]",
                    "Position_Description": null,
                    "Position_IsExec": 0
                };
            }
        },
    },
    methods: {
    },
    watch: {
        currentValue: function (newValue) {
            this.loading = true;
            this.$emit('input', newValue);
        },
        value: {
            handler: function (newValue) {
                this.currentValue = newValue;
                this.loading = false;
            },
        }
    },
    async created() {
        await this.$store.dispatch('products/getAllStaffPositions');
    },
};
</script>
