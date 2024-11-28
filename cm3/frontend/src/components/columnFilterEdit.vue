<template>
<v-container>
    <v-sheet v-if="!editing">
        <v-row >
            <v-col cols="12">
                <v-list outlined>
                    <v-subheader>
                        <h3>{{  columnDisplayName }}</h3>
                        &nbsp;
                        <v-select :items="operationTypes"
                                v-model="result.operation"
                                label="Operation"
                                dense
                                hide-details="true"></v-select>
                        <v-spacer />
                        <v-btn @click="addValue"
                               small>
                            <v-icon>mdi-plus</v-icon>
                        </v-btn>
                    </v-subheader>
                    <v-list-item v-for="(item,i) in result.values"
                                 :key="i">
                        <v-list-item-content>

                            <v-text-field v-model="result.values[i]"
                                          hide-details="true"
                                          append-outer-icon="mdi-close"
                                          @click:append-outer="removeValue(i)" />
                        </v-list-item-content>
                    </v-list-item>
                </v-list>
            </v-col>
        </v-row>
    </v-sheet>
    <v-sheet v-else>
        
    </v-sheet>
</v-container>
</template>

<script>
export default {
    components: {
    },
    props: ['columnDisplay', 'filterDefinition', 'value', 'editing'],
    data: () => ({
        result: {
            columnName: '',
            operation: 0,
            values: [],
        },
        operationTypes: [{
            value: 'like',
            text: "Contains (like)",
            multiValue: true
        }, {
            value: '=',
            text: "Equals (=)",
            multiValue: false
        }, {
            value: 'in',
            text: "One of (in)",
            multiValue: true
        }, {
            value: '>',
            text: "Greater than (>)",
            multiValue: true
        }, {
            value: '>=',
            text: "Greater than or equal (>=)",
            multiValue: true
        }, {
            value: '<',
            text: "Less than (<>>)",
            multiValue: true
        }, {
            value: '<=',
            text: "Less than or equal (<=)",
            multiValue: true
        }, {
            value: '!=',
            text: "Not equal (!=)",
            multiValue: true
        }, 
        ],
    }),
    computed: {
        columnDisplayName(){
            return this.columnDisplay.text || this.result.columnName;
        }
    },
    methods: {
        addValue() {
            this.result.values.push("");
        },
        removeValue(ix) {
            this.result.values.splice(ix, 1);
            if(this.result.values.length == 0){
                this.$emit("delete",this.result.columnName);
            }
        },
        fromRaw(value){
            var result = {};
            var termparts = value.split(String.fromCharCode(29));
            result.columnName = termparts[0];
            if(termparts.length < 2) termparts.push("");
            var filterComponents = termparts[1].split(String.fromCharCode(30));
            switch (filterComponents.length) {
                case 1:
                    result.operation = "=";
                    result.values = [filterComponents[0]];
                    break;
                case 0:
                    result.operation = "like";
                    result.values = ['']
                    break;
                default:
                    var operationName = filterComponents.shift();
                    result.operation = operationName;
                    result.values = filterComponents;
                    break;
            }
            return result;
        },
        toRaw() {
            var result = this.result.columnName + String.fromCharCode(29);
            var values = this.result.values.join(String.fromCharCode(30));
            if(this.result.operation == 0) {
                return result + values;
            } else {
                return result + this.result.operation + String.fromCharCode(30) + values;
            }
        }
    },
    watch: {
        result : { handler(newData) {
            var result = this.toRaw(newData);
            if(result != this.value)
            this.$emit('input', result);
        }, deep:true},
        value(newValue) {
            //Splat the input into the form
            this.result = this.fromRaw(newValue);
        }
    },
    created() {
        this.result = this.fromRaw(this.value);
    }

};
</script>
