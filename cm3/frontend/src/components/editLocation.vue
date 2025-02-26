<template>
    <v-container fluid>
        <v-row>
            <v-col cols="2" sm="2" md="2">
                <v-text-field label="Short Code" v-model="model.short_code">
                </v-text-field>
            </v-col>
            <v-col cols="4" sm="8" md="4">
                <v-text-field label="Name" v-model="model.name">
                </v-text-field>
            </v-col>
            <v-col cols="2" sm="6" md="3">
                <v-checkbox dense hide-details v-model="model.active">
                    <template v-slot:label>
                        Active
                    </template>
                </v-checkbox>
            </v-col>
            <v-col cols="6">
                <v-textarea label="Public Description" v-model="model.description" />
            </v-col>
            <v-col cols="6">
                <v-textarea label="Notes" v-model="model.notes" />
            </v-col>
        </v-row>
        <v-row>
            <v-col cols="12">
                Assignments
                <v-simple-table>
                    <template v-slot:default>
                        <thead>
                            <tr>
                                <th class="text-left">
                                    Name
                                </th>
                                <th class="text-left">
                                    Start
                                </th>
                                <th class="text-left">
                                    End
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, ix) in model.Assignments" :key="item.id">
                                <td>
                                    <badgeName :badge="item" />
                                </td>
                                <td>{{ item.start_time }}</td>
                                <td>{{ item.end_time }}</td>
                                <td>
                                    <v-btn dark @click="editAssignment = ix">Edit</v-btn>
                                    <v-btn dark @click="model.Assignments.splice(ix,1)">Delete</v-btn>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                </v-simple-table>
                <v-btn @click="addAssignment">Add</v-btn>
            </v-col>
        </v-row>
        <v-dialog scrollable :value="editAssignment != null" @input="editAssignment = null">

            <v-card>
                <v-card-title class="text-h5 grey lighten-2">
                    Edit Assignment
                </v-card-title>

                <v-card-text>
                    <editApplicationAssignment v-if="editAssignment != null" v-model="model.Assignments[editAssignment]"
                        :location="model"></editApplicationAssignment>
                </v-card-text>

                <v-divider></v-divider>

                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" text @click="editAssignment = null">Ok
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script>
import admin from '../api/admin';
import editApplicationAssignment from './editApplicationAssignment.vue';
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
    components: { badgeName, editApplicationAssignment },
    props: ['value'],
    data() {
        return {
            editAssignment: null,
            skipEmitOnce: false,
            validbadgeTypeInfo: false,
            model: {},
            currentDepartments: [],

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
    },
    methods: {

        addAssignment() {
            this.$set(this.model.Assignments, this.model.Assignments.length, {
                id: Math.floor(-10000*Math.random()),
                "application_id": 0,
                "location_id": this.model.id,
                "category_id": 0,
                "start_time": null,
                "end_time": null,
                "real_name": "[Select application]",
                "fandom_name": "",
                "name_on_badge": "Real Name Only",
                "application_status": "PendingAcceptance",
                "display_name": "[Select Application]"

            });
            this.editAssignment = this.model.Assignments.length - 1;


        },
        // removePosition(ix) {
        //     this.$delete(this.model.positions, ix);
        // },
        // refreshCurrentDepartments() {
        //     //TODO: This should be handled by the store...
        //     var that = this;
        //     admin.genericGet(this.authToken, 'Staff/Department', null, function(departments) {

        //         that.currentDepartments = departments.filter(department => department.id != that.model.id);
        //         that.currentDepartments.unshift({
        //             id: null,
        //             parent_id: null,
        //             name: "[[Top level]]",
        //             email_primary: ""
        //         })
        //     }, function() {
        //         //Whoops
        //     });

        // },
    },
    watch: {
        model: {
            handler(newData) {
                // console.log('emitting location data')
                newData.id = undefinedIfEmptyOrZero(newData.id);
                newData.short_code = nullIfEmptyOrZero(newData.short_code);
                newData.active = newData.active ? 1 : 0;
                newData.name = newData.name || "";
                newData.description = nullIfEmptyOrZero(newData.description);
                newData.notes = nullIfEmptyOrZero(newData.notes);
                newData.Assignments = newData.Assignments || [];

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
        }
    },
    created() {
        // this.refreshCurrentDepartments();
    }
};
</script>
