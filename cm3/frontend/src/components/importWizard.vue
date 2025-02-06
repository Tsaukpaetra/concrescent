<template>
  <v-card>
    <v-stepper alt-labels v-model="step">
      <v-stepper-header>
        <v-stepper-step step="1">
          Configure
        </v-stepper-step>
        <v-divider></v-divider>
        <v-stepper-step step="2">
          Preview
        </v-stepper-step>
        <v-divider></v-divider>
        <v-stepper-step step="3">
          Complete
        </v-stepper-step>
      </v-stepper-header>
      <v-stepper-items>
        <v-stepper-content step="1">
          <v-row>
            <v-col cols="3">
              <v-select :items="contextBadges" v-model="selectedBadgeTypeId" label="Badge Type" item-text="name"
                item-value="id" outlined hide-details="true"></v-select>
            </v-col>
            <v-col cols="3">
              <v-btn @click="generateTemplateAndDownload" :disabled="loading">Get template</v-btn>
            </v-col>
            <v-col cols="2">
              <v-switch v-model="useInternalNames" label="Use Internal Names"></v-switch>
            </v-col>
            <v-col cols="2">
              <v-switch v-model="immediatelyApproved" label="Immediately approve"></v-switch>
            </v-col>
            <v-col cols="2">
              <v-switch v-model="sendEmail" label="Send email"></v-switch>
            </v-col>
          </v-row>
          <v-row>
            <v-col>
              <v-expansion-panels focusable class="pa-2">
                <v-expansion-panel>
                  <v-expansion-panel-header color="grey lighten-4">Question Response Defaults</v-expansion-panel-header>
                  <v-expansion-panel-content>
                    Values set here will be used if the response is blank or missing
                    <formQuestions v-model="defaultformResponses" :questions="selectedBadgeQuestions"></formQuestions>
                  </v-expansion-panel-content>
                </v-expansion-panel>
              </v-expansion-panels>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="6">
              <v-file-input label="Select file (CSV)" accept="text/csv" show-size outlined truncate-length="50"
                :error-messages="importFileError" prepend-icon="mdi-file-delimited"
                v-model="fileDataSelection"></v-file-input>
            </v-col>
            <v-col cols="4">
              <v-badge :content="importColumnMapMissingRequired + ' Missing required'"
                :value="importColumnMapMissingRequired" color="red">
                <v-badge :content="importColumnMapFailedMatchCount + ' Extra'" :value="importColumnMapFailedMatchCount"
                  bottom color="blue">
                  <v-dialog v-model="importColumnMapEditing">

                    <template v-slot:activator="{ on, attrs }">
                      <v-btn v-bind="attrs" v-on="on">
                        Check column mapping</v-btn>
                    </template>
      <v-card>
        <v-toolbar
          dark
          color="primary"
        >
          <v-btn
            icon
            dark
            @click="importColumnMapEditing = false"
          >
            <v-icon>mdi-close</v-icon>
          </v-btn>
          <v-toolbar-title>Edit Column Mapping</v-toolbar-title>
          <v-spacer></v-spacer>
        </v-toolbar>
                    <importWizardColumnMapper v-model="importColumnMap" :leftColumns="baseColumnKeys"
                      :rightColumns="importColumnMapAvailable"></importWizardColumnMapper>
                      </v-card>
                  </v-dialog>
                </v-badge>
              </v-badge>
            </v-col>
            <v-col cols="2" v-if="importDataRaw.length">
              {{ importDataRaw.length }} records loaded
            </v-col>
          </v-row>
        </v-stepper-content>
        <v-stepper-content step="2">
          <v-data-table :headers="previewHeaders" :items="cartsInterrim" item-key="uuid"
            v-model="cartsSelectedForImport" show-select show-expand class="elevation-1" :loading="loading">
            <template v-slot:top>
              <v-toolbar flat>
                <v-toolbar-title>Carts preview</v-toolbar-title>
                <v-divider class="mx-4" inset vertical></v-divider>
                <v-spacer></v-spacer>
                <v-btn color="primary" :disabled="!importSimDataHasNoMissingContacts" @click="createMissingContacts">
                  Create missing contacts
                </v-btn>
              </v-toolbar>
            </template>
            <template v-slot:no-data>
              No items valid to import
            </template>

            <template v-slot:[`item.__badgeName`]="{ item}">
              <badgeName :badge="item.items[0]" />
            </template>
            <template v-slot:[`item.contact_email_address`]="{ item}">
              <div :style="{color:item.contact_id == null ? 'red': 'black'}">{{ item.contact_email_address }}</div>
            </template>
            <template v-slot:[`item.errors`]="{ item}">
              <div :style="{color:Object.keys(item.errors[0]).length > 0 ? 'red': 'black'}">{{ Object.keys(item.errors[0]).length }}</div>
            </template>

            <template v-slot:[`item.payment_txn_amt`]="{ item}">
              {{ item.payment_txn_amt | currency }}
            </template>
            <template v-slot:expanded-item="{ headers, item }">
              <td :colspan="headers.length">
                <v-container>
                  <cartItemCards :cart="item" />
                </v-container>

              </td>
            </template>
          </v-data-table>
        </v-stepper-content>
        <v-stepper-content step="3">
          <v-data-table :headers="resultsHeaders" :items="importResults" item-key="uuid"
            show-expand class="elevation-1">
            <template v-slot:top>
              <v-toolbar flat>
                <v-toolbar-title>Execute</v-toolbar-title>
                <v-divider class="mx-4" inset vertical></v-divider>
                <v-spacer></v-spacer>
                <v-btn color="primary" :loadingas="loading" @click="createPayments">
                  Go!
                </v-btn>
              </v-toolbar>
            </template>
            <template v-slot:no-data>
              No items imported yet.
            </template>

            <template v-slot:[`item.__badgeName`]="{ item}">
              <badgeName :badge="item.items[0]" />
            </template>
            <template v-slot:[`item.errors`]="{ item}">
              <div :style="{color:Object.keys(item.errors[0]).length > 0 ? 'red': 'black'}">{{ Object.keys(item.errors[0]).length }}</div>
            </template>

            <template v-slot:[`item.payment_txn_amt`]="{ item}">
              {{ item.payment_txn_amt | currency }}
            </template>
            <template v-slot:expanded-item="{ headers, item }">
              <td :colspan="headers.length">
                <v-container>
                  <cartItemCards :cart="item" />
                </v-container>

              </td>
            </template>
          </v-data-table>
        </v-stepper-content>
      </v-stepper-items>
    </v-stepper>
    <v-card-actions>
      <v-spacer></v-spacer>
      <v-btn @click="step = step - 1" :disabled="step<2">
        Previous
      </v-btn>
      <v-btn @click="step = step + 1" :disabled="!canContinue">
        Next
      </v-btn>
    </v-card-actions>
  </v-card>
</template>
<script>
import {
    mapActions,
    mapGetters
} from 'vuex';
import admin from '../api/admin';
import {
    debounce
} from '@/plugins/debounce';
import exportFromJSON from 'export-from-json';
import {parse as csvparse} from 'csv-parse/browser/esm/sync';

import formQuestions from './formQuestions.vue';
import badgeName from './datagridcell/badgeName.vue'
import cartItemCards from './cartItemCards.vue';
import importWizardColumnMapper from './importWizardColumnMapper.vue';
export default {
  components: {
    formQuestions,
    badgeName,
    cartItemCards,
    importWizardColumnMapper
  },
  props: [
  ],
  data: () => ({
    loading: false,
    step: 1,
    selectedBadgeTypeId: 0,
    useInternalNames: 0,
    immediatelyApproved: 0,
    sendEmail: 0,
    questions: [],
    questionMap: [],
    defaultformResponses: {},
    fileDataSelection: null,
    importFileError: [],
    importDataRaw: [],
    importColumnMapEditing: false,
    importColumnMapAvailable:[],
    importColumnMap: {},
    importContactMap:{},
    cartsInterrim:[],
    cartsSelectedForImport:[],
    importResults:[],
  }),
  computed: {
    ...mapGetters('products', {
      currentContext: 'selectedbadgecontext',
      contextBadges: 'contextBadges',
    }),
    authToken: function () {
      return this.$store.getters['mydata/getAuthToken'];
    },
    canContinue: function () {
      var result = !this.loading;
      switch (this.step) {
        case 1:
          result = result && this.fileDataSelection != null && this.importColumnMapMissingRequired == 0;
          break;
        case 2:
          result = result 
          && this.cartsSelectedForImport.length > 0
          //No selected item can have any errors
          && this.cartsSelectedForImport.findIndex(cart => 
            cart.contact_id == null
            || Object.keys(cart.errors[0]).length > 0
          ) < 0;
          break;
        default:
          result = false;
          break;
      }
      return result;
    },
    selectedBadgeType: function () {
      return this.contextBadges.find(x => x.id == this.selectedBadgeTypeId);
    },
    selectedBadgeQuestions: function () {
      return this.questionMap.map(map => { return { ...this.questions.find(q => q.id == map.question_id), required: map.required } })
    },
    baseColumnKeys: function () {
      //TODO: be aware of potential nice key collision...
      var result = [
        { internal: "real_name", nice: this.currentContext.application_name1 || "Real Name", required: true },
        { internal: "fandom_name", nice: this.currentContext.application_name2 || "Fandom Name", required: false },
        { internal: "name_on_badge", nice: this.currentContext.id > 0 ? "Display on badge" : "Name on Badge", required: true },
        { internal: "contact_email_address", nice: "Contact email address", required: true },
        { internal: "assignment_count", nice: "Assignment Count", required: true },
        { internal: "addons", nice: "Addon IDs", required: false },
      ];
      //Add the questions
      result.push(...this.selectedBadgeQuestions.map(question => {
        return {
          internal: "form_responses[" + question.id + "]",
          nice: question.title,
          question_id: question.id,
          required: question.required == 1 && ((this.defaultformResponses[question.id] || "") == "")
        }
      }))

      //Add subbadges
      for (let index = 1; index < this.selectedBadgeType.max_applicant_count; index++) {
        result.push(
          { internal: "subbadge[" + index + "]real_name", nice: "Subbadge [" + index + "] Real Name", required: false },
          { internal: "subbadge[" + index + "]fandom_name", nice: "Subbadge [" + index + "] Fandom Name", required: false },
          { internal: "subbadge[" + index + "]name_on_badge", nice: "Subbadge [" + index + "] Name on Badge", required: false },
          { internal: "subbadge[" + index + "]date_of_birth", nice: "Subbadge [" + index + "] Date of birth", required: false },
          { internal: "subbadge[" + index + "]notify_email", nice: "Subbadge [" + index + "] Notify email", required: false },
          { internal: "subbadge[" + index + "]can_transfer", nice: "Subbadge [" + index + "] Allow transfer", required: false },
          { internal: "subbadge[" + index + "]ice_name", nice: "Subbadge [" + index + "] ICE Contact Name", required: false },
          { internal: "subbadge[" + index + "]ice_relationship", nice: "Subbadge [" + index + "] ICE Relationship", required: false },
          { internal: "subbadge[" + index + "]ice_email_address", nice: "Subbadge [" + index + "] ICE email", required: false },
          { internal: "subbadge[" + index + "]ice_phone_number", nice: "Subbadge [" + index + "] ICE phone", required: false },
        )
      }

      return result;
    },
    importColumnMapExtras: function(){
      var mapInvert = Object.values(this.importColumnMap);
      return this.importColumnMapAvailable.filter(x=> !mapInvert.includes(x));
    },
    importColumnMapFailedMatchCount: function () {
      return this.importColumnMapExtras.length
    },
    importColumnMapMissingRequired: function () {
      //Short circuit the check until data has been imported
      if (this.importDataRaw.length == 0) return 0;
      return this.baseColumnKeys.filter(key => key.required).reduce((currentCount, keyCheck) =>
        currentCount + (this.importColumnMap.hasOwnProperty(keyCheck.internal) ? 0 : 1)
        , 0)
    },
    importSimData: function() {
      return this.importDataRaw.map(item => this.convertToItem(item));
    },
    importSimDataHasNoMissingContacts: function(){
      return this.importSimData.findIndex(x => x.contact_id == null) > -1;
    },
    previewHeaders:function(){
      return [
        {
          text: (this.currentContext.application_name1 || "Real Name") + ' / ' + (this.currentContext.application_name2 || "Fandom Name"),
          value: '__badgeName'
        },
        {
          text: "Contact",
          value: "contact_email_address"
        },
        {
          text: "Errors",
          value: "errors"
        },
        {
          text: "Cart total",
          value: "payment_txn_amt"
        },
        { text: '', value: 'data-table-expand' },
      ];
    },
    resultsHeaders:function(){
      return [
        {
          text: (this.currentContext.application_name1 || "Real Name") + ' / ' + (this.currentContext.application_name2 || "Fandom Name"),
          value: '__badgeName'
        },
        {
          text: "Transaction ID",
          value: "id"
        },
        {
          text: "Email sent",
          value: "sentEmail"
        },
        { text: '', value: 'data-table-expand' },
      ];
    },
  },
  methods: {
    //Form questions

    refreshBadgeTypeMap: function () {
      if (this.selectedBadgeTypeId == 0) {
        this.questionMap = [];
        return;
      }
      this.fileDataSelection = null;
      this.loading = true;
      admin.genericGetList(this.authToken, 'Form/Question/' + this.currentContext.context_code + '/' + this.selectedBadgeTypeId + '/Map', null, (results, total) => {
        this.questionMap = results;
        this.loading = false;
      })
    },
    refreshQuestions: function () {
      console.log('importWizard: refreshQuestions')
      this.loading = true;
      admin.genericGetList(this.authToken, 'Form/Question/' + this.currentContext.context_code, null, (results, total) => {
        this.questions = results;
        this.loading = false;
      })
    },
    clear: function(){      
      console.log('importizard:clearing')
      this.importFileError = [];
      this.importDataRaw = [];
      this.importColumnMap = {};      
      this.importColumnMapAvailable = [];
      this.importContactMap = {};
      this.cartsInterrim=[];
      this.cartsSelectedForImport=[];
      this.importResults = [];
    },
    generateTemplateAndDownload: function () {
      var result = [Object.fromEntries(
        this.baseColumnKeys.map(key =>
          [this.useInternalNames ? key.internal : key.nice,
          key.questionIx > -1 ? this.defaultformResponses[key.question_id] || "" : ""]
        )
      )];

      exportFromJSON({
        data: result,
        fileName: this.currentContext.name + " - " + this.selectedBadgeType.name,
        exportType: "csv",
        withBOM: true
      });
    },
    findContacts: function (createMissing = false) {
      return new Promise((resolve, reject) =>{
        admin.genericPost(this.authToken, 'Contact/get' + (createMissing ? 'orcreate' : '') + 'batch',
          this.importDataRaw.map(item => item[this.importColumnMap['contact_email_address']]),
          (contactMap) => {
            console.log('findcontacts', contactMap)
            this.importContactMap = contactMap;
            resolve();
          }, reject)
      })
    },
    createMissingContacts: async function() {
      this.loading = true;
      await this.findContacts(true);
      await this.simCreatePayments();
      this.loading = false;
    },
    convertToItem: function(row) {
      var tg = (internal) => {return row[this.importColumnMap[internal]];};
      //Transfer the base fields
      var result = [
        'real_name',
        'fandom_name',
        'name_on_badge',
        'contact_email_address',
        'assignment_count',
        'addons'
      ].reduce((cur, column) => (cur[column]=tg(column),cur),{});
      //Fix the addons array
      result['addons'] = result.addons.split(',').filter(x => x!='').map(addonid => {return {
        addon_id:addonid
      };});

      //Add in the questions
      result['form_responses'] = this.questionMap.reduce((cur,{question_id,required}) =>{
        var response = tg('form_responses[' + question_id + ']');
        if(response == '' && required)
          response = this.defaultformResponses[question_id];
        cur[question_id] = response;
        return cur;
      },{} );

      //Add in sub badges
      result['subbadges'] = Array.from({ length: this.selectedBadgeType.max_applicant_count }, (_, k) => k + 1)
        .map((ix) => {
          return [
            'real_name',
            'fandom_name',
            'name_on_badge',
            'date_of_birth',
            'notify_email',
            'can_transfer',
            'ice_name',
            'ice_relationship',
            'ice_email_address',
            'ice_phone_number',
          ].reduce((cur, column) => (cur[column]=tg('subbadge[' + ix + ']' + column),cur),{})
        }).filter(subbadge => subbadge.real_name != '' && subbadge.real_name != undefined);
        //Fixup name_on_badge
        console.log('babrba', this.selectedBadgeType)
        result['name_on_badge'] = this.fixupNameOnBadge(result['name_on_badge'],result['fandom_name'] != '',this.currentContext.application_name1,this.currentContext.application_name2);
        result['subbadges'] = result['subbadges'].map(badge => {
          return {...badge,name_on_badge:this.fixupNameOnBadge(badge['name_on_badge'],badge['fandom_name'] != '')}
        });
        //Add the badge info
        result['context_code'] = this.currentContext.context_code;
        result['badge_type_id'] = this.selectedBadgeType.id;
        //Add non-standard things
        result['contact_email_address'] = tg('contact_email_address');
        result['contact_id'] = this.importContactMap[tg('contact_email_address')];
        return result;
    },
    simCreatePayments: function(){  
      return new Promise((resolve, reject) =>{
        this.loading = true;
        admin.genericPost(this.authToken, 'Payment/simCreateBatch',
          this.importSimData,
          (results) => {
            console.log('simCreatePayments', results)
            this.cartsInterrim = results;
            this.loading = false;
            resolve();
          }, (error) => {this.loading = false; reject(error)})
      })
    },
    createPayments: function(){
      return new Promise((resolve, reject) =>{
        this.loading = true;
        //TODO: Maybe make this not do them all at once, in case email sending is rate-limited
        admin.genericPost(this.authToken, 'Payment/CreateBatch?immediateApprove=' + (this.immediatelyApproved ? "true":"false") + '&sendEmail=' +(this.sendEmail ? "true":"false"),
          this.cartsSelectedForImport,
          (results) => {
            console.log('CreatePayments', results)
            this.importResults = results;
            this.loading = false;
            resolve();
          }, (e) => {this.loading = false; reject(e)})
      })
    },
    fixupNameOnBadge: function(value, hasFandom,application_name1, application_name2){
      var result = hasFandom ? "Fandom Name Large, Real Name Small" : "Real Name Only";
      console.log('checking',value,""+application_name1+" Only")
      switch (value) {
        case "Fandom Name Large, Real Name Small":
        case ""+application_name2+" Large, "+application_name1+" Small":
          result = "Fandom Name Large, Real Name Small"
          break;
        case "Real Name Large, Fandom Name Small":
        case ""+application_name1+" Large, "+application_name2+" Small":
          result = "Real Name Large, Fandom Name Small"
          break;
        case "Fandom Name Only":
        case ""+application_name2+" Only":
          result = "Fandom Name Only"
          break;
        case "Real Name Only":
        case ""+application_name1+" Only":
          result = "Real Name Only"
          break;
        default:
          break;
      }
      return result;
    }
  },
  watch: {
    //Not sure if this is entirely necessary?
    currentContext: debounce(function (newSearch) {
      this.refreshQuestions();
    }, 500),
    selectedBadgeTypeId: debounce(function (newSearch) {
      this.refreshBadgeTypeMap();
    }, 200),
    step: async function(newStep) {
      switch (newStep) {
        case 2:
          if(Object.keys(this.importContactMap).length == 0){
            //We only want to do this once automatically
            await this.findContacts();
            await this.simCreatePayments();
          }
          
          break;
      
        default:
          break;
      }
    },
    
    //CSV data loader
    fileDataSelection: function() {
      console.log('prep file', this.fileDataSelection)
      //Clear out stuff in preparation for the load
      this.clear();

      if (!this.fileDataSelection) {
        return;
      }
      this.loading = true;
      var reader = new FileReader();

      // Use the javascript reader object to load the contents
      // of the file in the v-model prop
      reader.readAsText(this.fileDataSelection);
      reader.onload = () => {
        try {

          this.importDataRaw = csvparse(reader.result, {
            columns: true,
            skip_empty_lines: true,
            trim:true
          });
          //Trap if it looks like Excel did a dumb and converted the CSV to a TSV file
          if (Object.keys(this.importDataRaw[0]).length == 1) {
            console.log('Failed csv, trying with tab')
            this.importDataRaw = csvparse(reader.result, {
              columns: true,
              skip_empty_lines: true,
              trim:true,
              delimiter: '\t'
            });
          }
        } catch (err) {
          console.log(err)
          this.importFileError = ["Parse error. Is it a valid CSV file?"]
          this.loading = false;
          return;
        }
        // console.log('prep continue, read data', this.importDataRaw)
        //Try to do initial mapping
        var failedMapping = [];
        if (this.importDataRaw.length > 0) {
          //Should always be true but just in case...
          this.importColumnMapAvailable = Object.keys(this.importDataRaw[0]);
          var initmapping = Object.fromEntries(this.importColumnMapAvailable.map(key => {
            var found = this.baseColumnKeys.find(base => base.internal == key || base.nice == key);
            if (found == undefined) {
              failedMapping.push(key);
              return [null, null];
            }
            return [found.internal, key];
          }).filter(x => x[0] != null));
          // console.log("prep mapping: matched", initmapping)
          // console.log("prep mapping: failed", failedMapping)
          this.importColumnMap = initmapping;
        }
        this.loading = false;
      }
    }
  },
  created() {
    this.selectedBadgeTypeId = this.contextBadges[0].id;
    this.refreshQuestions();
    //this.doSearch();
  }
}
</script>