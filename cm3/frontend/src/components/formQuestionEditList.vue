<template>
    <v-item-group active-class="light-grey" v-model="openQuestions" multiple>
        <v-container>
            <v-row>
                <v-col cols="12">
                    <h1>Badge Type</h1>
                    <v-select :items="contextBadgeTypes" v-model="selectedBadgeType" item-text="name" item-value="id"
                        clearable solo hide-details="true"></v-select>
                </v-col>
            </v-row>
        </v-container>
        <v-row>
            <v-col>
                <v-item v-slot="{active, toggle}" v-for="(item,ix) in questions" :key="item.id">
                    <v-card>
                        <v-card @click="toggle" v-if="!active">

                            <formQuestionRender v-if="bQuestionActive(item.id)"
                                :question="{...item, required : bQuestionRequired(item.id) }" />
                            <p v-else>Hidden: {{item.title}} </p>
                            <v-divider></v-divider>
                        </v-card>
                        <v-card v-if="active">
                            <formQuestionEdit v-model="eQuestion(item.id).question"
                                :preview="eQuestion(item.id).preview" />
                            <v-toolbar v-if="active" dense>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon @click="prepCancelEdit(ix)">
                                            <v-icon>mdi-cancel</v-icon>
                                        </v-btn>
                                    </template>
                                    Cancel Edit
                                </v-tooltip>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon @click="prepDestroyQuestion(ix)">
                                            <v-icon>mdi-delete</v-icon>
                                        </v-btn>
                                    </template>
                                    Delete question (not yet functional)
                                </v-tooltip>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon @click="toggleQuestionListed(item.id)">
                                            <v-icon>mdi-table-eye{{bQuestionListed(item.id) ? '' : '-off'}}</v-icon>
                                        </v-btn>
                                    </template>
                                    Add to badge list by default
                                </v-tooltip>
                                <v-spacer />
                                <i v-if="selectedBadgeType > 0">
                                    <v-tooltip top>
                                        <template v-slot:activator="{ on, attrs }">
                                            <v-btn v-bind="attrs" v-on="on" icon @click="toggleQuestionActive(item.id)">
                                                <v-icon>mdi-eye{{bQuestionActive(item.id) ? '' : '-off'}}</v-icon>
                                            </v-btn>
                                        </template>
                                        Visible for {{ contextBadgeTypes.find(it=> it.id == selectedBadgeType)?.name ||
                                        'Badge Type' }}
                                    </v-tooltip>
                                    <v-tooltip top>
                                        <template v-slot:activator="{ on, attrs }">
                                            <v-btn v-bind="attrs" v-on="on" icon :disabled="!bQuestionActive(item.id)"
                                                @click="toggleQuestionRequired(item.id)"
                                                :color="bQuestionRequired(item.id) ? 'red' : undefined ">
                                                <v-icon>mdi-asterisk</v-icon>
                                            </v-btn>
                                        </template>
                                        Question is required
                                    </v-tooltip>
                                </i>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon @click="moveQuestion(item.id,true)"
                                            color="primary">
                                            <v-icon>mdi-arrow-up</v-icon>
                                        </v-btn>
                                    </template>
                                    Move question up
                                </v-tooltip>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon @click="moveQuestion(item.id,false)"
                                            color="primary">
                                            <v-icon>mdi-arrow-down</v-icon>
                                        </v-btn>
                                    </template>
                                    Move question down
                                </v-tooltip>
                                <v-spacer />
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon @click="exportQuestion(item.id)">
                                            <v-icon>mdi-export</v-icon>
                                        </v-btn>
                                    </template>
                                    Export question
                                </v-tooltip>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon
                                            @click="eQuestion(item.id).preview = !eQuestion(item.id).preview">
                                            <v-icon>mdi-magnify{{eQuestion(item.id).preview ? '-close' :''}}</v-icon>
                                        </v-btn>
                                    </template>
                                    Preview question
                                </v-tooltip>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon :disabled="!bQuestionModified(item.id)"
                                            :loading="eQuestion(item.id).saving" @click="saveEdit(item.id)"
                                            color="primary">
                                            <v-icon>mdi-content-save</v-icon>
                                        </v-btn>
                                    </template>
                                    Save question
                                </v-tooltip>
                            </v-toolbar>
                            <v-divider></v-divider>
                        </v-card>
                    </v-card>
                </v-item>
                <v-item>
                    <v-card>
                        <v-card v-if="!newQuestionShow">

                            <v-container fluid class="text-center">
                                <v-row class="flex">
                                    <v-col cols="6">
                                        <v-btn @click="prepNewQuestion">
                                            Add new question
                                        </v-btn>
                                    </v-col>
                                    <v-col cols="6">
                                        <v-file-input prepend-icon="mdi-import" label="Import question(s)" placeholder="Import question(s)"
                                         multiple accept=".ccq" show-size dense solo
                                         v-model="importingQuestionsRawFiles"
                                         ></v-file-input>
                                    </v-col>
                                </v-row>
                            </v-container>
                        </v-card>
                        <v-card v-if="newQuestionShow">
                            <formQuestionEdit v-model="newQuestion" :preview="newQuestionPreview" />
                            <v-toolbar dense>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon @click="cancelNewQuestion()">
                                            <v-icon>mdi-cancel</v-icon>
                                        </v-btn>
                                    </template>
                                    Cancel adding question
                                </v-tooltip>
                                <v-spacer />
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon
                                            @click="newQuestionPreview = !newQuestionPreview">
                                            <v-icon>mdi-magnify{{newQuestionPreview ? '-close' :''}}</v-icon>
                                        </v-btn>
                                    </template>
                                    Preview question
                                </v-tooltip>
                                <v-tooltip top>
                                    <template v-slot:activator="{ on, attrs }">
                                        <v-btn v-bind="attrs" v-on="on" icon :disabled="newQuestion.title == ''"
                                            :loading="newQuestionSaving" @click="saveNewQuestion" color="primary">
                                            <v-icon>mdi-content-save</v-icon>
                                        </v-btn>
                                    </template>
                                    Save question
                                </v-tooltip>
                            </v-toolbar>
                            <v-divider></v-divider>
                        </v-card>
                    </v-card>
                </v-item>
            </v-col>

        </v-row>
        <v-dialog v-model="loading" width="200" height="200" close-delay="1200" content-class="elevation-0" persistent>
            <v-card-text class="text-center overflow-hidden">
                <v-progress-circular :size="150" class="mb-0" indeterminate />
            </v-card-text>
        </v-dialog>
        <v-dialog v-model="askCancelQuestionEdit" max-width="390">

            <v-card>
                <v-card-title class="headline">Question modified!</v-card-title>
                <v-card-text>You have unsaved changes, do you wish to save them?
                </v-card-text>
                <v-card-actions>
                    <v-btn color="default" @click="askCancelQuestionEdit = false">Cancel</v-btn>
                    <v-spacer></v-spacer>
                    <v-btn color="red darken-1" @click="cancelEdit(openQuestionToCancel)">Don't save</v-btn>
                    <v-btn color="primary" @click="cancelEdit(openQuestionToCancel)">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
        <v-dialog :value="importingQuestionsData.length > 0" @input="clearImportFiles" scrollable
        >
            <v-card>
                <v-card-title class="headline">Importing questions</v-card-title>
                <v-card-subtitle>Select the questions you want to import</v-card-subtitle>
                <v-card-text>
                    <v-data-table
                    :items="importingQuestionsData"
                    item-key="title"
                    show-select v-model="importingQuestionsSelected"
                    :headers="[{text:'Question',value:'question'}]"
                    >
                    <template v-slot:[`item.question`]="{ item}">                    
                        <formQuestionRender
                                :question="{...item, required : false }" />
                    </template>

                    </v-data-table>
                </v-card-text>
                <v-card-actions>
                    <v-btn color="default" @click="clearImportFiles">Cancel</v-btn>
                    <v-spacer></v-spacer>
                    <v-btn color="primary" @click="doImport"
                     :disabled="importingQuestionsSelected.length==0"
                     :loading="importLoading"
                     >Import {{ importingQuestionsSelected.length }} question{{ importingQuestionsSelected.length==1 ? '':'s' }}</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-item-group>
</template>

<script>
import admin from '../api/admin';
import {
    debounce
} from '@/plugins/debounce';
import exportFromJSON from 'export-from-json';
import formQuestionRender from '@/components/formQuestionRender.vue';
import formQuestionEdit from '@/components/formQuestionEdit.vue';
export default {
    components: {
        formQuestionEdit,
        formQuestionRender
    },
    props: ['context_code'],
    data: () => ({
        contextBadgeTypes: [],
        selectedBadgeType: 0,
        loading: false,
        questions: [],
        questionMap: [],
        selectedQuestions: [],
        editedQuestions: {},
        newQuestionShow: false,
        newQuestion: {},
        newQuestionPreview: false,
        newQuestionSaving: false,
        openQuestions: [],
        openQuestionToCancel: 0,
        askCancelQuestionEdit: false,
        importingQuestionsRawFiles:[],
        importingQuestionsData:[],
        importingQuestionsSelected:[],
        importLoading:false,
    }),
    computed: {
        authToken: function() {
            return this.$store.getters['mydata/getAuthToken'];
        },
        eQuestion: {
            get: state => function(id) {
                if (this.editedQuestions[id] == undefined) {
                    var question = this.questions.find(item => item.id == id);
                    this.$set(this.editedQuestions, id, {
                        preview: false,
                        saving: false,
                        question: {...question, required: this.bQuestionRequired(id) }
                    });
                }
                return this.editedQuestions[id];
            }
        },
        bQuestionRequired: {
            get: state => function(id) {
                var mapdata = this.questionMap.find(item => item.question_id == id);
                if (mapdata == undefined) return false;
                return mapdata.required;
            }
        },
    },
    methods: {

        refreshBadgeTypeMap: function() {
            if (this.selectedBadgeType == 0) {
                this.questionMap = [];
                return;
            }
            this.loading = true;
            admin.genericGetList(this.authToken, 'Form/Question/' + this.context_code + '/' + this.selectedBadgeType + '/Map', null, (results, total) => {
                this.questionMap = results;
                this.loading = false;
            })
        },
        refresh: function() {
            this.loading = true;
            admin.genericGetList(this.authToken, 'Form/Question/' + this.context_code, null, (results, total) => {
                this.questions = results;

                admin.genericGetList(this.authToken, admin.contextToPrefix(this.context_code) + '/BadgeType', null, (results, total) => {
                    results.unshift({
                        "id": 0,
                        "active": 0,
                        "display_order": 0,
                        "name": "Show all questions",
                        "price": "0.00",
                        "quantity": null,
                        "dates_available": "0000-00-00 to 0000-00-00"
                    })
                    this.contextBadgeTypes = results;
                    this.loading = false;
                })
            })
        },
        doEmit: function(eventName, item) {
            this.$emit(eventName, item);
        },
        bQuestionActive: function(id) {
            if (this.selectedBadgeType == 0) return true;
            return this.questionMap.find(item => item.question_id == id) != undefined;
        },
        bQuestionListed: function(id) {
            return this.questions.find(item => item.id == id).listed > 0;
        },
        toggleQuestionActive: function(id) {
            if (this.bQuestionActive(id)) {
                //Active, make it not so!
                admin.genericDelete(this.authToken, 'Form/Question/' + this.context_code + '/' + this.selectedBadgeType + '/Map/' + id, (result) => {
                    this.questionMap.splice(this.questionMap.findIndex(item => item.question_id == id), 1)
                })

            } else {
                //Not active, make it so!
                admin.genericPost(this.authToken, 'Form/Question/' + this.context_code + '/' + this.selectedBadgeType + '/Map/' + id, {
                    required: false
                }, (result) => {
                    this.questionMap.push({
                        question_id: id,
                        required: false
                    })
                })
            }

        },
        toggleQuestionListed: function(id) {
            var q = this.questions.findIndex(item => item.id == id);
            console.log("toggle listed", q)
            var question = this.questions[q];
            question.listed = question.listed == 0 ? 1 : 0;
            admin.genericPost(this.authToken, 'Form/Question/' + this.context_code + '/' + id, {
                id: id,
                listed: question.listed
            }, (result) => {
                //Update the state in the quetsions and editedQuestions
                this.$set(this.questions, q, question);
                console.log('edited question', this.editedQuestions[id])
                this.editedQuestions[id].question.listed = question.listed;
            })
        },
        toggleQuestionRequired: function(id) {
            console.log("toggle required", this.questionMap.find(item => item.question_id == id))
            if (this.bQuestionActive(id)) {
                admin.genericPost(this.authToken, 'Form/Question/' + this.context_code + '/' + this.selectedBadgeType + '/Map/' + id, {
                    required: this.questionMap.find(item => item.question_id == id).required == 0 ? 1 : 0
                }, (result) => {
                    this.$set(this.questionMap, this.questionMap.findIndex(item => item.question_id == id), {
                        question_id: id,
                        required: this.questionMap.find(item => item.question_id == id).required == 0 ? 1 : 0
                    });
                })
            } else {
                //Not active, they can't be required in any case
            }

        },
        moveQuestion: function(id,upwards) {
            var q = this.questions.findIndex(item => item.id == id);
            console.log("move", id, upwards ? 'up':'down');
            var question = this.questions[q];
            admin.genericPost(this.authToken, 'Form/Question/' + this.context_code + '/' + id + '/Move', {
                id: id,
                direction: upwards
            }, (results) => {
                // Re-sort the questions based on the result
                this.questions = this.questions.map(element => {
                    var updated = results.find(r => r['id'] == element['id'], this);
                    if (updated != undefined){
                        //Flag it for later
                        updated.__foundit = true;
                    } 
                    return { ...element, ...updated };
                }, this).sort((a, b) => a['order'] - b['order']);
                //Check if everything that changed is in our view
                var allfound = results.reduce(function(last, current) {
                    return last && current.__foundit
                }, true);
                if (!allfound) {
                    console.error('Elements returned that we don\'t know about?', results);
                }
            })
        },
        bQuestionModified: function(id) {
            var orig = JSON.stringify(this.questions.find(item => item.id == id));
            var edit = JSON.stringify(this.editedQuestions[id].question);
            return orig != edit;
        },
        saveEdit: function(id) {
            this.editedQuestions[id].saving = true;
            admin.genericPost(this.authToken, 'Form/Question/' + this.context_code + '/' + id,
                this.editedQuestions[id].question, (results, total) => {
                    this.$set(this.questions, this.questions.findIndex(item => item.id == id), this.editedQuestions[id].question);
                    this.editedQuestions[id].saving = false;

                    //un-activate the question
                    var ix = this.questions.findIndex(item => item.id == id);
                    this.openQuestions.splice(this.openQuestions.findIndex(item => item == ix), 1);
                })

        },
        prepNewQuestion: function() {
            this.newQuestion = {
                text: '',
                title: '',
                type: 'h1',
                values: [],
            };
            this.newQuestionShow = true;
        },
        cancelNewQuestion: function() {
            this.newQuestion = {};
            this.newQuestionShow = false;
        },
        saveNewQuestion: function() {

            this.newQuestionSaving = true;
            admin.genericPost(this.authToken, 'Form/Question/' + this.context_code,
                this.newQuestion, (results, total) => {
                    //Add the ID that we got to the question
                    this.newQuestion = {
                        ...this.newQuestion,
                        ...results
                    };
                    this.questions.push(this.newQuestion);
                    this.newQuestionSaving = false;
                    //reset
                    this.cancelNewQuestion();
                    //If we're viewing a badge, immediately toggle the active state
                    if (this.selectedBadgeType > 0) {
                        this.toggleQuestionActive(results.id);
                    }

                })
        },
        prepCancelEdit: function(ix) {
            if (this.bQuestionModified(this.questions[ix].id)) {
                //Modified, pop the dialog
                this.openQuestionToCancel = ix;
                this.askCancelQuestionEdit = true;
            } else {
                //Cancel it outright
                this.cancelEdit(ix);
            }
        },
        cancelEdit: function(ix) {
            //reset the editedQuestions entry
            //Note that this gets immediately recreated...?
            this.$delete(this.editedQuestions, this.questions[ix].id);

            //un-activate the question
            this.openQuestions.splice(this.openQuestions.findIndex(item => item == ix), 1);
            this.askCancelQuestionEdit = false;

        },
        prepDestroyQuestion: function(ix) {
            //Begin the question destruction process
        },
        exportQuestion: function(id) {
            var {id, order, required, ...question} = this.questions.find(item => item.id == id);
            exportFromJSON({
                data:question,
                fileName:question.title,
                exportType:'json',
                extension:'ccq'
            });
        },
        clearImportFiles: function(){
            this.importingQuestionsRawFiles = [];
        },
        doImport: async function () {
            this.importLoading = true;
            await Promise.all(this.importingQuestionsSelected.map(question =>
                new Promise((resolve, reject) => {
                    admin.genericPost(this.authToken, 'Form/Question/' + this.context_code,
                        question, (results) => {
                            //Add the ID that we got to the question
                            question = {
                                ...question,
                                ...results
                            };
                            this.questions.push(question);
                            //If we're viewing a badge, immediately toggle the active state
                            if (this.selectedBadgeType > 0) {
                                this.toggleQuestionActive(results.id);
                            }
                            resolve()
                        }, reject)
                })
            ));
            this.importLoading = false;
            this.clearImportFiles();
        }
    },
    watch: {

        context_code: debounce(function(newSearch) {
            this.refresh();
        }, 500),
        selectedBadgeType: debounce(function(newSearch) {
            this.refreshBadgeTypeMap();
        }, 500),
        importingQuestionsRawFiles: async function(){
            this.importingQuestionsData = await Promise.all( this.importingQuestionsRawFiles.map(async (file) => 
            new Promise((resolve,reject) =>
                {
                    var reader = new FileReader();
                    reader.onload = () => {
                        resolve(JSON.parse(reader.result));
                    }
                    reader.readAsText(file);
                })
            ));
        }
    },
    created() {
        this.refresh();
        //this.doSearch();
    }
};
</script>
