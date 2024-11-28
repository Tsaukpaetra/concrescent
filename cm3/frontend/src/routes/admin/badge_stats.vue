<template>
<v-card>
    <v-sheet
        class="pa-3"
    >Pretty chart goes here
    <v-skeleton-loader
      class="mx-auto"
      max-width="300"
      type="card"
      loading
    >Pretty chart goes here</v-skeleton-loader>
  </v-sheet>
  <v-sheet>
    <v-data-table :options.sync="tableOptions"
              :server-items-length="totalResults"
              :loading="loading"
              :headers="headers"
              :items-per-page="-1"
              disable-sort
              group-by="context_name"
              :show-group-by="false"
              :items="tableResults"
              class="elevation-1 fill-height"
              :search="searchText">
                <!-- <template v-slot:item="{ item, index }">
                    <h3 v-if="contextIsDifferentFromPrior(item, index)">Type: {{ badgeContexts.find(cx => cx.context_code == item["context_code"])?.name }}</h3>
                    <tr>
                        <td v-for="(header, key) in headers" :key="key">{{ item[header.value] }}</td>
                    </tr>
                </template> -->
                <!-- <template v-slot:[`group.header`]="{ group }">
                    {{ badgeContexts.find(cx => cx.context_code == group)?.name }}
                </template> -->
                
      <template v-slot:[`group.header`]="{ group, isOpen, toggle }">
        <td
          class="grey lighten-2"
          dense
          :colspan="headers.length"
        >
            <v-btn
              icon
              rounded small
            @click="toggle"
            >
              <v-icon>mdi-{{  isOpen ? 'minus' : 'plus' }}</v-icon>
            </v-btn>
            {{ group }}
        </td>
      </template>
                <template v-slot:[`item.badge_type_id`]="{ item }">
                    {{ getBadge(item.context_code,item.badge_type_id)?.name }}
                </template>
    </v-data-table>
  </v-sheet>
    
    <v-dialog v-model="loading"
              width="200"
              height="200"
              close-delay="1200"
              content-class="elevation-0"
              persistent>
        <v-card-text class="text-center overflow-hidden">
            <v-progress-circular :size="150"
                                 class="mb-0"
                                 indeterminate />
        </v-card-text>
    </v-dialog>
</v-card>
</template>
<script>
import admin from '../../api/admin';
import {
    debounce
} from '@/plugins/debounce';

import {
    mapActions,
    mapState,
    mapGetters
} from 'vuex'

export default {
    components: {

    },
    props: [
        'subTabIx'
    ],
    data: () => ({
        searchText: '',
        loading: false,
        isExporting:false,
        tableOptions: {},
        rawResults: {},
        totalResults: 0,
        optionExportFormat:'csv',
        optionExportRawHeaders: false,
        apiAddParams:{},

        headers:[
            {
                align: 'start',
                text: 'Badge Type',
                value: 'badge_type_id',
            },
            {
                text: 'count',
                value: 'count',
            },
            {
                text: 'total',
                value: 'total',
            },
        ],

        detailsDialog: false,

    }),
    computed: {
        authToken: function() {
            return this.$store.getters['mydata/getAuthToken'];
        },
        ...mapGetters('products', {
            'badgeContexts': 'badgeContexts',
        }),
        ...mapState('products',{
            badges:'badges'
        }),
        pageOptionsForGet: function(){
            const pageOptions = [
                'sortBy',
                'sortDesc',
                'page',
                'itemsPerPage'
            ].reduce((a, e) => (a[e] = this.tableOptions[e], a),  {...this.apiAddParams});
            if (this.searchText) pageOptions['find'] = this.searchText;
            if (this.context_code) pageOptions['context_code'] = this.context_code;
            //If exporting, force pagination to all
            if(this.isExporting) {
                pageOptions['itemsPerPage'] = -1;
                pageOptions['page'] = 1;
            }
            return pageOptions;
        },
        tableResults: function() {
            var result = []
            //Loop the contexts
            for(var cx in this.rawResults){
                //loop the badge IDs
                for(var ix in this.rawResults[cx]){
                    //Splat the last item of the badge and add context code as a new property
                    result.push({"context_code":cx,
                    "context_name": this.badgeContexts.find(c => c.context_code == cx)?.name || "Context not yet loaded: " + cx,
                     ...this.rawResults[cx][ix].slice(-1)[0]});
                }
            }
            return result;
        }
    },
    methods: {
        checkPermission() {
            console.log('Hey! Listen!');
            //this.doSearch();
        },
        ...mapActions('products', [
            'getContextBadges',
        ]),
        doSearch: function() {
            this.loading = true;
            admin.genericGetList(this.authToken, 'Stats/Badge', {...this.pageOptionsForGet, 'badge_groups':'payment_status,printed,checked_in', 'range_start':'2036-01-01'}, (results, total) => {
                this.rawResults = results;
                this.totalResults = total;
                this.loading = false;
                
                //Make sure we have context for anything loaded
                for(var context in results){
                    console.log('ensure context', context)
                    this.getContextBadges(context);
                }
            })
        },
        doExport: function() {
            this.loading = true;
            console.log('doSearch pageOptions', this.pageOptionsForGet);
            admin.genericGetList(this.authToken, 'Stats/Badge', this.pageOptionsForGet, (results, total) => {
                this.loading = false;
                
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
        makeHeadersPretty(input) {
            if (typeof input !== 'object') return input;
            if (Array.isArray(input)) return input.map(this.makeHeadersPretty,this);
            var that = this;
            return Object.keys(input).reduce(function (newObj, key) {
                let val = input[key];
                let newVal = (typeof val === 'object') && val !== null ? that.makeHeadersPretty.call(that,val) : val;
                //find new key
                var newKeyObj = that.headers.find((header) => {
                    if (header==key) return true;
                    if(header.value == key) return true;
                })
                if(typeof newKeyObj == 'string') newKeyObj = {value:newKeyObj, text:newKeyObj};
                if(typeof newKeyObj == 'undefined') newKeyObj = {value:key, text:key};
                newObj[newKeyObj.text] = newVal;
                return newObj;
            }, {});
        },
        contextIsDifferentFromPrior(item,index) {
            // Check if the current item's property is different from the previous item's property
            if (index > 0) {
                const previousItem = this.tableResults[index - 1];
                return item.context_code !== previousItem.context_code;
            }
            return true;
        },
        getBadge(context_code,badge_type_id){
            var context = this.badges[context_code];
            if(context == undefined) return undefined;
            var badge = context.find(b => b.id == badge_type_id);
            return badge;
        }
    },
    watch: {
        $route() {
            this.$nextTick(this.checkPermission);
        },
        tableOptions: {
            handler() {
                this.doSearch()
            },
            deep: true,
        },
    },
    created() {
        this.checkPermission();
    }
};
</script>
