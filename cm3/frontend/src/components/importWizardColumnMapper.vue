<template>
    <v-container fluid>
        <v-row>
            <v-col cols="6">
                <v-simple-table fixed-header height="70vh">
                    <template v-slot:default>
                        <thead>
                            <tr>
                                <th class="text-left">
                                    Source Column
                                </th>
                                <th class="text-left">
                                    Import file Column
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in leftColumns" :key="item.internal">
                                <td>{{ item.nice }}</td>
                                <td>
                                    <v-chip v-if="value[item.internal]" close
                                        @click:close="targetClearMappedCol(item.internal)">{{ value[item.internal]
                                        }}</v-chip>
                                    <v-sheet v-else :color="item.required ? 'red' : 'grey lighten-3'" elevation="5"
                                        @dragenter="targetDropCheck" @dragover="targetDropCheck"
                                        @drop="targetDrop($event, item.internal)">&nbsp;</v-sheet>
                                </td>
                            </tr>
                        </tbody>
                    </template>
                </v-simple-table>
            </v-col>
            <v-col cols="6">
                <h5>Available columns from import:</h5>
                <div v-if="availableColumns.length">
                    <v-chip v-for="(col, ix) in availableColumns" :key="ix" draggable class="ma-2"
                        @dragstart="availableColStartDrag($event, col)" @dragend="availableColEndDrag">
                        {{ col }}
                    </v-chip>
                </div>
                <div v-else>All available columns mapped! 🎉</div>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import VInput from 'vuetify/lib/components/VInput/VInput.js';

import {
    debounce
} from '@/plugins/debounce';
import {
    mapGetters
} from 'vuex'
export default {
    extends: VInput,
    components: {},
    props: {
        'value': Object,
        'readonly': Boolean,
        'leftColumns': Array,
        'rightColumns': Array,
    },
    data() {
        return {
            draggingCol:''
        };
    },
    computed: {
        availableColumns: function(){
            return this.rightColumns.filter(x => !this.mappedColumns.includes(x));
        },
        mappedColumns: function(){
            return Object.values(this.value);
        },
    },
    methods: {
        availableColStartDrag: function(e,col){
            e.dataTransfer.effectAllowed = "move";
            this.draggingCol = col;
        },
        availableColEndDrag: function(e,col){
            console.log('end drag',e)
            this.draggingCol = '';
        },
        targetDropCheck: function(ev){
            if (this.draggingCol != '') {
                ev.preventDefault();
                ev.dataTransfer.dropEffect  = "move";
            }
        },
        targetDrop: function (ev, col) {
            console.log('dropping', this.draggingCol)
            ev.preventDefault();
            this.$emit('input', { ...this.value, [col]: this.draggingCol });
            
        },
        targetClearMappedCol: function(col){
            let {[col]:_, ...result  } = this.value;
            this.$emit('input', result);
        }
    },
    watch: {
    },
};
</script>
