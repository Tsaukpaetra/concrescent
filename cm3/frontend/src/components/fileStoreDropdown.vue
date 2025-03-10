<template>
<v-autocomplete v-model="model"
                :items="searchResults"
                :loading="loading"
                :search-input.sync="searchText"
                :filter="noFilter"
                hide-details
                hide-selected
                :item-value="valueKey"
                :label="label"
                solo>
    <template v-slot:no-data>
        <v-list-item>
            <v-list-item-title>
                Start typing...
            </v-list-item-title>
        </v-list-item>
    </template>
    <template v-slot:selection="{ item }">
        <v-list-item-title>{{item[valueDisplay]}}</v-list-item-title>
        <v-list-item-subtitle v-if="valueSubDisplay">{{item[valueSubDisplay]}}</v-list-item-subtitle>
    </template>
    <template v-slot:item="{ item }">
        <v-list-item-avatar color="indigo"
                            class="text-h5 font-weight-light white--text">
            {{ item[valueKey] }}
        </v-list-item-avatar>
        <v-list-item-content>
            <v-list-item-title>{{item[valueDisplay]}}</v-list-item-title>
            <v-list-item-subtitle v-if="valueSubDisplay">{{item[valueSubDisplay]}}</v-list-item-subtitle>
        </v-list-item-content>
        <v-list-item-action>
            <v-btn v-for="action in actions"
                   :key="action.name"
                   @click="doEmit(action.name, item)">{{action.text}}</v-btn>
        </v-list-item-action>
    </template>
    <template v-slot:[`footer.prepend`]>
        <v-btn v-for="action in footerActions"
               :key="action.name"
               :color="action.color"
               @click="doEmit(action.name)"
               class="ma-2">{{action.text}}</v-btn>
    </template>
</v-autocomplete>
</template>

<script>
import admin from '../api/admin';
import {
    debounce
} from '@/plugins/debounce';
export default {
    components: {},
    props: {
        'context': {
            type: String
        },
        'value':{
            type: null
        },
        'valueKey': {
            type: String,
            default: 'id'
        },
        'valueDisplay': {
            type: String,
            default: 'name'
        },
        'valueSubDisplay': {
            type: String,
            default: null
        },
        'label': {
            type: String,
            default: 'Select an item...'
        },
        'actions': {
            type: Array
        },
        'isEditingItem': {
            type: Boolean
        }
    },
    data: () => ({
        model: null,
        searchText: "",
        loading: false,
        deferredUpdate: false,
        tableOptions: {},
        searchResults: [],
        totalResults: 0,
        defHeaders: [{
            text: 'ID',
            align: 'start',
            value: 'id',
        }, {
            text: 'Actions',
            value: 'actions',
        }, ]
    }),
    computed: {
        authToken: function() {
            return this.$store.getters['mydata/getAuthToken'];
        },
        headers() {
            var result = this.defHeaders || [];
            var rmv = this.RemoveHeaders || [];
            var inc = this.AddHeaders || [];
            var that = this;
            result = result.filter(item => !rmv.includes(item.value)).concat(inc);
            //Ensure the "Actions" header is last
            var actionsIx = result.findIndex(item => item.value == 'actions');
            if (actionsIx > -1)
                result.push(result.splice(actionsIx, 1)[0]);
            return result;
        },
        isSorting() {
            return this.tableOptions.sortBy.length > 0;
        },
    },
    methods: {

        doSearch: function() {
            this.loading = true;
            // const pageOptions = [
            //     'sortBy',
            //     'sortDesc',
            //     'page',
            //     'itemsPerPage'
            // ].reduce((a, e) => (a[e] = this.tableOptions[e], a), {});;
            const pageOptions = {
                'sortBy': '',
                'sortDesc': '',
                'page': 1,
                'itemsPerPage': 10
            };
            admin.genericGetList(this.authToken, 'Filestore', {
                "context": this.context,
                "find": this.searchText,
                ...pageOptions
            }, (results, total) => {
                this.searchResults = results;
                this.loading = false;
                console.log('finished loading list, is model in it', this.model)
                //Only emit the full object if we know we have something
                if(results.length > 0){
                    var found = results.find(x => x[this.valueKey] == this.model)
                    if(found != undefined){
                        console.log('yes, it is',found)
                        this.$emit('selected',found)
                    } else {
                        console.log('no!')
                    }
                } else {
                    console.log('probably not, empty result')
                }
            })
        },
        noFilter: function() {
            return true;
        },
        doEmit: function(eventName, item) {
            this.$emit(eventName, item);
        }
    },
    watch: {
        model:{
            handler(newData) {
                console.log('emitting dropdown value', newData);
                this.$emit('input', newData);
                //Only emit the full object if we know we have something
                if(this.searchResults.length > 0){
                    var found = this.searchResults.find(x => x[this.valueKey] == newData)
                    if(found != undefined)
                        this.$emit('selected',found)
                } else {
                    this.deferredUpdate = true;
                }
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

        searchText: debounce(function(newSearch) {
            newSearch && newSearch !== this.model && this.doSearch();
        }, 500),
        isEditingItem: debounce(function(newEditing) {
            if (!newEditing)
                this.doSearch();
        }, 200),
        tableOptions: {
            handler() {
                this.doSearch()
            },
            deep: true,
        }
    },
    mounted(){
        this.doSearch();
    }
};
</script>
