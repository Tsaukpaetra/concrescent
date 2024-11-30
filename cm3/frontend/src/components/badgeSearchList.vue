<template>
<v-data-table :options.sync="tableOptions"
              :server-items-length="totalResults"
              :loading="loading"
              :headers="headers"
              multi-sort
              :dense="dense"
              :items="tableResults"
              :item-key="internalKey"
              class="elevation-1 fill-height"
              :show-expand='showExpand'
              @item-expanded="doEmit('item-expanded',$event)"
              :disabled="isExporting"
              :footer-props="{
                itemsPerPageOptions: [5,10,15,25,50,100,-1]
                }"
              >

    <template v-slot:top="">
        <v-container fluid>
            <v-row style="flex-wrap: nowrap;"
                   no-gutters>
                <v-col cols="1"
                       class="flex-grow-1 flex-shrink-0"
                       style="min-width: 100px; max-width: 100%;">
                    <v-text-field v-model="searchText"
                                  label="Search"
                                  clearable
                                  append-outer-icon="mdi-refresh"
                                  @click:append-outer="doSearch"
                                  class="mx-4"></v-text-field>
                </v-col>
                <v-col cols="1">
                    <v-dialog v-model="showConfig"
                              scrollable>
                        <template v-slot:activator="{ on, attrs }">
                            <v-btn dark
                                   v-bind="attrs"
                                   v-on="on">
                                <v-icon>mdi-cog</v-icon>
                            </v-btn>
                        </template>

                        <v-card>
                            <v-card-title class="text-h5 grey lighten-2">
                                List configuration
                            </v-card-title>
                            <v-divider></v-divider>
                            <v-card-text>
                                
                                <v-container >
                                    
                                    <v-select
                                        v-model="displayedHeaders"
                                        :items="availableHeaders"
                                        attach
                                        label="Displayed Columns (Click to add filter)"
                                        placeholder="Not displaying anything..."
                                        persistent-placeholder
                                        hide-details
                                        :menu-props="{ offsetY: true }"
                                        multiple
                                    >
                                    
                                    <template v-slot:selection="{ item }">
                                        <v-chip label @click.stop="addColumnToFilters(item.value)">
                                        <span>{{ item.text }}</span>
                                        </v-chip>                                        
                                    </template>
                                    
                                    <template v-slot:prepend-item>
                                        <v-list-item
                                        ripple
                                        @mousedown.prevent
                                        @click="showAllHeaders = !showAllHeaders"
                                        >
                                        <v-list-item-action>
                                            <v-icon :color="showAllHeaders? 'indigo darken-4' : ''">
                                                mdi-checkbox-{{ showAllHeaders ? 'marked' : 'blank-outline' }}
                                            </v-icon>
                                        </v-list-item-action>
                                        <v-list-item-content>
                                            <v-list-item-title>
                                            Show all available headers
                                            </v-list-item-title>
                                        </v-list-item-content>
                                        </v-list-item>
                                        <v-divider class="mt-2"></v-divider>
                                    </template>
                                </v-select>
                                </v-container>
                                
                                <v-list>
                                    <v-subheader>Column Filters</v-subheader>
                                    <v-list-item-group
                                        v-model="columnFiltersEdit"
                                        >
                                        <v-list-item v-for="filter,ix in columnFiltersList"
                                        :key="ix">
                                        <ColumnFilterEdit v-model="columnFilters[ix]" :columnDisplay="columnDisplayName(filter[0])" @delete="removeColumnFromFilters"></ColumnFilterEdit>
                                    </v-list-item>
                                    </v-list-item-group>
                                </v-list>

                                <v-list subheader
                                        two-line
                                        flat>
                                    <v-subheader>Retrieved Form questions</v-subheader>
                                                    
                                    <v-list-item-group
                                            v-model="displayedQuestionsIx"
                                            multiple
                                            active-class=""
                                            >
                                            <v-list-item v-for="item in questions"
                                                            :key="item.id">
                                                            <template v-slot:default="{ active }">
                                                            
                                                <v-list-item-action>
                                                    <v-checkbox :input-value="active"></v-checkbox>
                                                </v-list-item-action>
                                                <v-list-item-content>
                                                    {{item.title}}
                                                </v-list-item-content>
                                                </template>
                                            </v-list-item>
                                    </v-list-item-group>
                                </v-list>

                            </v-card-text>

                            <v-divider></v-divider>

                            <v-card-actions>
                                <v-spacer></v-spacer>
                                <v-btn color="primary"
                                       @click="showConfig = false; doSearch()">
                                    Ok
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-dialog>
                </v-col>
            </v-row>
        </v-container>
    </template>
    
    <template v-slot:[`item.id`]="{ item }">
        <v-tooltip right>
            <template v-slot:activator="{ on, attrs }">
                <span v-bind="attrs"
                      v-on="on">
                    {{item.context_code}}{{item.display_id}}</span>
            </template>
            {{item.id}}
        </v-tooltip>
    </template>
    <template v-slot:[`item.__badgeName`]="{ item}">
        <badgeName :badge="item"/>
    </template>
    <template v-slot:[`item.application_status`]="{ item }">
        <v-tooltip left>
            <template v-slot:activator="{ on, attrs }">
                <v-icon v-bind="attrs"
                        v-on="on"
                        :color="applicationStatusColor[item.application_status]"
                        v-if="applicationStatusIcon[item.application_status] != undefined">mdi-{{applicationStatusIcon[item.application_status]}}</v-icon>
                <div v-else>{{item.application_status}}</div>
            </template>
            <span>{{item.application_status}}</span>
        </v-tooltip>
    </template>
    <template v-slot:[`item.payment_status`]="{ item }">
        <payment_status_pill v-model="item.payment_status"></payment_status_pill>
    </template>
    <template v-slot:[`item.time_printed`]="{ item }">
        <v-tooltip left>
            <template v-slot:activator="{ on, attrs }">
                <v-icon v-bind="attrs"
                        v-on="on"
                        v-show="item.time_printed != null">mdi-printer-check</v-icon>
            </template>
            <span>{{item.time_printed}}</span>
        </v-tooltip>
    </template>
    <template v-slot:[`item.time_checked_in`]="{ item }">
        <v-tooltip left>
            <template v-slot:activator="{ on, attrs }">
                <v-icon v-bind="attrs"
                        v-on="on"
                        v-show="item.time_checked_in != null">mdi-account-check</v-icon>
            </template>
            <span>{{item.time_checked_in}}</span>
        </v-tooltip>

    </template>
    <template v-slot:[`item.__actions`]="{ item }">
        <v-btn-toggle dense v-model="actionToggles" multiple>
        <v-btn v-for="action in actions"
               :small="dense"
               :key="action.name"
               @click="doEmit(action.name, item)">{{action.text}}</v-btn>
        </v-btn-toggle>
    </template>
    <template v-slot:[`footer.prepend`]>
        <v-btn v-for="action in footerActions"
               :key="action.name"
               :color="action.color"
               @click="doEmit(action.name)"
               class="ma-2">{{action.text}}</v-btn>
        <v-spacer/>
            <v-dialog
                v-model="isExporting"
                scrollable
                max-width="500"
                persistent
            >
              <template v-slot:activator="{ on, attrs }">
                <v-btn v-if="showExport"
                  color="blue lighten-2"
                  dark
                  v-bind="attrs"
                  v-on="on"
                  class="ma-2"
                >
                  Export
                </v-btn>
              </template>

              <v-card>
                    <v-card-title class="headline">Export List</v-card-title>
                    <v-divider></v-divider>
                    <v-card-text>
                    <v-container>
                        <v-row>
                            <v-col
                            cols="12"
                            sm="8">
                            <p>Format</p>

                            <v-btn-toggle v-model="optionExportFormat">
                                <v-btn value="json">
                                JSON
                                </v-btn>
                                <v-btn value="csv">
                                CSV
                                </v-btn>
                                <v-btn value="xls">
                                XLS (HTML)
                                </v-btn>
                                </v-btn-toggle>
                            </v-col>
                            <v-col
                            cols="12"
                            sm="4">
                            <p>Internal Header names</p>

                            <v-switch
                                v-model="optionExportRawHeaders"
                                ></v-switch>
                            </v-col>
                    </v-row>
                    </v-container>
                    </v-card-text>

                    <v-divider></v-divider>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="default"
                               @click="isExporting = false">Cancel</v-btn>
                        <v-btn color="primary"
                                :loading="loading"
                               @click="doExport">Export!</v-btn>
                    </v-card-actions>

              </v-card>
            </v-dialog>
               
    </template>
    <!--courtesy https://gist.github.com/loilo/73c55ed04917ecf5d682ec70a2a1b8e2 -->
    <slot v-for="(_, name) in $slots"
          :name="name"
          :slot="name" />
    <template v-for="(_, name) in $scopedSlots"
              :slot="name"
              slot-scope="slotData">
        <slot :name="name"
              v-bind="slotData" />
    </template>
</v-data-table>
</template>

<script>
import admin from '../api/admin';
import payment_status_pill from '@/components/datagridcell/payment_status_pill.vue';

import badgeName from '@/components/datagridcell/badgeName.vue';
import {
    debounce
} from '@/plugins/debounce';
import exportFromJSON from 'export-from-json';
import ColumnFilterEdit from './columnFilterEdit.vue';
export default {
    components: {
        payment_status_pill,
        ColumnFilterEdit,
        badgeName
    },
    props: {
        'apiPath': {
            type: String
        },
        'apiAddParams': {
            type: Object,
            default () {
                return {};
            }
        },
        'search': {
            type: String,
            default () {
                return '';
            }
        },
        'context_code': {
            type: String
        },
        'actions': {
            type: Array
        },
        'internalKey': {
            type: String,
            default () {
                return 'id';
            }
        },
        'headerKey': {
            type: Object,
            default () {
                return {
                    text: 'ID',
                    align: 'start',
                    value: 'id',
                };
            }
        },
        'headerFirst': {
            type: Object,
            default () {
                return {
                    text: 'Real Name',
                    value: 'real_name',
                };
            }
        },
        'headerSecond': {
            type: Object,
            default () {
                return {
                    text: 'Fandom Name',
                    value: 'fandom_name',
                };
            }
        },
        'AddHeaders': {
            type: Array
        },
        'RemoveHeaders': {
            type: Array
        },
        'footerActions': {
            type: Array
        },
        'isEditingItem': {
            type: Boolean
        },
        'showExpand': {
            type: Boolean
        },
        'showExport':{
            type: Boolean
        },
        'dense': {
            type: Boolean
        },
    },
    data() {
        return {
            searchText: this.search,
            loading: false,
            showConfig: false,
            isExporting:false,
            showAllHeaders:false,
            tableOptions: {},
            tableResults: [],
            totalResults: 0,
            questions: [],
            discoveredHeaders:[],
            displayedHeaders:[],
            actionToggles:[],
            displayedQuestionsIx: [],
            columnFilters:[],
            columnFiltersEdit:[],

            optionExportFormat:'csv',
            optionExportRawHeaders: false,

            //TEMP: Until application_Status is in its own component
            applicationStatusIcon: {
                'InProgress': 'alert',
                'Submitted': 'clock-alert',
                'Cancelled': 'close-octagon',
                'Rejected': 'close-octagon',
                'Terminated': 'close-octagon',
                'Waitlisted': 'progress-question',
                'Accepted': 'check-circle',
                'Onboarding': 'progress-check',
                'Active': 'check-circle',
                'PendingAcceptance': 'progress-check',
            },
            applicationStatusColor: {
                'InProgress': 'grey',
                'Submitted': '',
                'Cancelled': 'amber',
                'Rejected': 'red',
                'Terminated': 'red',
                'Waitlisted': 'amber',
                'Accepted': 'green',
                'Onboarding': 'green',
                'Active': 'green',
                'PendingAcceptance': 'green',
            },
        }
    },
    computed: {
        authToken: function() {
            return this.$store.getters['mydata/getAuthToken'];
        },
        availableHeaders() {
            var result = [
                this.headerKey,
                {
                    text: this.headerFirst.text + ' / ' + this.headerSecond.text,
                    value:'__badgeName'
                },
                {
                    text: 'Badge Type',
                    value: 'badge_type_name',
                },
                {
                    text: 'Contact Email',
                    value: 'contact_email_address',
                },
                {
                    text: 'Application Status',
                    value: 'application_status',
                },
                {
                    text: 'Payment Status',
                    value: 'payment_status',
                },
                {
                    text: 'Printed',
                    value: 'time_printed',
                },
                {
                    text: 'Checked-In',
                    value: 'time_checked_in',
                }
            ];
            var inc = this.AddHeaders || [];
            
            //result = result.filter(item => !rmv.includes(item.value)).concat(inc);
            result = result.concat(inc);
            //Add in any displayedQuestions
            this.displayedQuestions.forEach((item, i) => {
                result.push({
                    text: item.title,
                    value: 'form_responses[' + item.id + ']'
                })
            });

            //If toggled, add any headers present in the list as retrieved in the ray
            if(this.showAllHeaders)
            {
                this.discoveredHeaders.filter(header => -1 == result.findIndex(r => r.value == header)).forEach((headervalue) => {
                    console.log('all headers adding'. headervalue)
                    result.push({text: headervalue, value: headervalue})
                });
            }

            return result;
        },
        displayedQuestions(){
            return this.questions.filter((_, ix) => this.displayedQuestionsIx.includes(ix))
        },
        headers() {
            // var result = this.displayedHeaders;

            var result = this.displayedHeaders.map((item) => {
                var result = this.availableHeaders.find((header) => header.value == item);
                if(result == undefined){
                    console.log("failed to find header in availableHeaders",item)
                    result = {value:item, text:"{"+item+"}"}
                } 

                return result;
            },this)

            //Ensure the "Actions" header is last
            var actionsIx = result.findIndex(item => item.value == '__actions');
            if (actionsIx > -1){
                console.log("Actions __actions already exists?")
                result.push(result.splice(actionsIx, 1)[0]);
            } else {
                
                result.push({
                        text: 'Actions',
                        value: '__actions',
                        sortable: false
                    })
            }
             console.log("displayed headers",result)
            return result;
        },
        pageOptionsForGet: function(){
            const pageOptions = [
                'sortBy',
                'sortDesc',
                'page',
                'itemsPerPage'
            ].reduce((a, e) => (a[e] = this.tableOptions[e], a),  {...this.apiAddParams});
            //If attempting to sort by badge name, make it be sorted by real_name instead
            pageOptions['sortBy'] = pageOptions['sortBy'].map((item) => item == '__badgeName' ? 'real_name' : item);
            if (this.displayedQuestions.length) pageOptions['questions'] = this.displayedQuestions.map(x => x.id).join(',');
            if (this.searchText) pageOptions['find'] = this.searchText;
            if (this.context_code) pageOptions['context_code'] = this.context_code;
            if (this.columnFilters.length > 0) pageOptions['filter'] = this.columnFilters.join(String.fromCharCode(28));
            //If exporting, force pagination to all
            if(this.isExporting) {
                pageOptions['itemsPerPage'] = -1;
                pageOptions['page'] = 1;
            }
            return pageOptions;
        },
        columnFiltersList(){
            return this.columnFilters.map(x=> x.split(String.fromCharCode(29)[0]));
        }
    },
    methods: {

        doSearch: function() {
            this.loading = true;
            console.log('doSearch pageOptions', this.pageOptionsForGet);
            admin.genericGetList(this.authToken, this.apiPath, this.pageOptionsForGet, (results, total) => {
                this.tableResults = results;
                this.totalResults = total;
                this.loading = false;
                //If we haven't discovered available columns yet, throw it in
                if(this.discoveredHeaders.length == 0 && results.length >0){
                    this.discoveredHeaders = Object.keys(results[0])
                }
                
                //If they're on a page that apparently doesn't exist
                if(results.length == 0 && total != 0){
                    this.tableOptions['page'] = 1;
                }

                //If this looks like a badge scan, and we had exactly one result, emit it
                if (results.length == 1) {
                    let r = results[0];
                    let resultQR = 'CM*' + r.context_code + r.display_id + '*' + r.uuid;
                    console.log('Looking for code', resultQR)
                    if (this.searchText == resultQR) {
                        console.log('QR code match', r)
                        this.$emit('qrmatch', r);
                    }
                }
            })
        },
        doExport: function() {
            this.loading = true;
            console.log('doSearch pageOptions', this.pageOptionsForGet);
            admin.genericGetList(this.authToken, this.apiPath, this.pageOptionsForGet, (results, total) => {
                this.loading = false;
                
                //TODO: auto-generate name from context info?
                const fileName = 'Export';
                const exportType =  this.optionExportFormat;
                if(!this.optionExportRawHeaders){
                    results = this.makeHeadersPretty(results);
                }
                // Iterate through the results and replace explicit null values with an empty string
                results = results.map(obj => {
                    return Object.fromEntries(
                        Object.entries(obj).map(([key, value]) => [key, value === null ? '' : value])
                    );
                });

                exportFromJSON({
                    data:results,
                    fileName,
                    exportType,
                    withBOM:true
                    });
            })            
        },
        doEmit: function(eventName, item) {
            this.$emit(eventName, item);
        },
        doRefreshQuestions: function() {
            if (this.context_code == undefined) return;
            console.log('bsl refreshing questions', this.context_code);
            admin.genericGetList(this.authToken, 'Form/Question/' + this.context_code, null, (results, total) => {
                this.questions = results;
                //Generate initial displayed question indices and add them to the displayed indices
                this.displayedQuestionsIx = this.questions.map((item,ix) => item.listed ? ix :undefined).filter(x=>x!=undefined);
                this.doSearch();

                //TODO: Apply personal preferences
                //this.displayedQuestions = initialQuestions;
            })
        },
        makeHeadersPretty(input) {
            if (typeof input !== 'object') return input;
            if (Array.isArray(input)) return input.map(this.makeHeadersPretty,this);
            var that = this;
            return Object.keys(input).reduce(function (newObj, key) {
                let val = input[key];
                let newVal = (typeof val === 'object') && val !== null ? that.makeHeadersPretty.call(that,val) : val;
                //find new key
                var newKeyObj = that.availableHeaders.find((header) => {
                    if (header==key) return true;
                    if(header.value == key) return true;
                })
                if(typeof newKeyObj == 'string') newKeyObj = {value:newKeyObj, text:newKeyObj};
                if(typeof newKeyObj == 'undefined') newKeyObj = {value:key, text:key};
                newObj[newKeyObj.text] = newVal;
                return newObj;
            }, {});
        },
        addColumnToFilters(input){
            if(this.columnFilters.map(x => this.columnpart(x),this).findIndex(x=>x==input)<0)
            this.columnFilters.push(input);
        },
        removeColumnFromFilters(input){
            var at = this.columnFilters.map(x => this.columnpart(x),this).findIndex(x=>x==input);
            if(at > -1)
            {
                this.columnFilters.splice(at,1);
            }
        },
        columnpart(input) {
            return input.split(String.fromCharCode(29),1)[0]
        },
        columnDisplayName(input) {
            var value =input.split(String.fromCharCode(29),1)[0];
            return this.headers.find(x => x.value == value) || {
                value: value,
                text: "[" + value + "]"
            };
        },
    },
    watch: {
        search: function(newSearch) {
            //this.searchText = newSearch;
        },
        searchText: debounce(function(newSearch) {
            this.doSearch();
            console.log('searchText', newSearch)
            //this.$emit('update:search', newSearch);
        }, 500),
        // displayedQuestions: debounce(function(newSearch) {
        //     console.log("displayedQuestions updated", newSearch)
        //     this.doSearch();
        // }, 2500),
        displayedQuestionsIx(newQuestions, oldQuestions){
            //Column was checked or un-checked. Add/remove them from the displayed columns!
            var added = newQuestions.filter(x => !oldQuestions.includes(x));
            var removed = oldQuestions.filter(x => !newQuestions.includes(x));
            var ixToAdd = added.map(x => "form_responses[" + this.questions[x].id + "]");
            var ixToRemove = removed.map(x => "form_responses[" + this.questions[x].id + "]");
            //Add them to the displayed columns
            this.displayedHeaders.push(...ixToAdd);
            //Remove from the displayed columns and filters
            this.displayedHeaders = this.displayedHeaders.filter(x => !ixToRemove.includes(x));
            this.columnFilters = this.columnFilters.filter(x => !ixToRemove.includes(this.columnpart(x)))

        },
        isEditingItem: debounce(function(newEditing) {
            if (!newEditing)
                this.doSearch();
        }, 200),
        context_code: debounce(function(newCode) {
            this.doRefreshQuestions();
            this.doSearch();
        }, 20),
        tableOptions: {
            handler() {
                this.doSearch()
            },
            deep: true,
        },
        apiPath() {
            this.doRefreshQuestions();
            this.doSearch();
        },
        actionToggles(){
            if(this.actionToggles.length >0)
            this.actionToggles = [];
        }
    },
    mounted() {
        
        var rmv = this.RemoveHeaders || [];
        console.log('removing from default headers', rmv)        

        this.displayedHeaders = this.availableHeaders.map((item) => item.value).filter(item => !rmv.includes(item));
        this.doRefreshQuestions();
    }
};
</script>
