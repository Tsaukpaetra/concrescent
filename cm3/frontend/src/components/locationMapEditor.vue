<template>
<v-container fluid
             class="pa-0">
    <v-toolbar :color="'blue'"
               dark
               style="position: sticky; top: 0; z-index: 1;">
        <v-app-bar-nav-icon @click.stop="mainPropsForm = !mainPropsForm" />
        <v-toolbar-title>{{model.name}}</v-toolbar-title>
        <v-spacer></v-spacer>

        <template v-if="!fieldIsSelected">
            <v-menu offset-y>
                <template v-slot:activator="{ on, attrs }">
                    <v-btn color="primary"
                           v-bind="attrs"
                           v-on="on">
                        <v-icon>mdi-plus</v-icon>
                    </v-btn>
                </template>
                <v-list>
                    <v-list-item v-for="(fieldType, index) in fieldTypes"
                                 :key="index"
                                 @click="addField(fieldType.name)">
                        <v-list-item-title>{{ fieldType.title }}</v-list-item-title>
                    </v-list-item>
                </v-list>
            </v-menu>
        </template>
        <template v-else>
            <fieldEditToolbar :format.sync="model.layout[fieldSelectedIx]" />

            <v-btn color="primary"
                   @click="delField(fieldSelectedIx)">
                <v-icon>mdi-delete</v-icon>
            </v-btn>
        </template>
    </v-toolbar>
    <v-sheet color="white"
             class="mx-auto ma-3"
             elevation="4"
             :style="sStyle">
        <fieldPositioner v-for="(item,ix) in model.layout"
                         :key="ix"
                         :format.sync="model.layout[ix]"
                         :value="badge"
                         :readOnly="preview"
                         :edit="ix == fieldSelectedIx"
                         :order="ix"
                         @click="toggleSelected(ix)"
                         @move="selectField(ix)" />
    </v-sheet>
    <v-navigation-drawer v-model="mainPropsForm"
                         absolute
                         width="400"
                         temporary>
                         <fileStoreDropdown v-model="model.bgImageID"
                         @selected="setbackground"/>
        <formatPropEditForm  v-model="model"
                            @selectLayout="selectField" />

    </v-navigation-drawer>
    <v-speed-dial v-model="fab"
                  bottom
                  right
                  style="position:absolute;">
        <template v-slot:activator>
            <v-btn v-model="fab"
                   color="blue darken-2"
                   dark
                   fab>
                <v-icon v-if="fab">
                    mdi-close
                </v-icon>
                <v-icon v-else>
                    mdi-magnify
                </v-icon>
            </v-btn>
        </template>
        <div @click.stop=""
             style="width:350px; align-self: end;">
            <v-card>
                <v-tooltip top>
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn color="primary"
                               v-bind="attrs"
                               v-on="on"
                               :outlined="preview"
                               @click="preview = !preview">
                            <v-icon>mdi-file-find</v-icon>
                        </v-btn>
                    </template>
                    <span>Preview</span>
                </v-tooltip>
                <v-tooltip top>
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn color="primary"
                               v-bind="attrs"
                               v-on="on"
                               :outlined="preview"
                               @click="editBadgeData">
                            <v-icon>mdi-script-text</v-icon>
                        </v-btn>
                    </template>
                    <span>Preview Data editor</span>
                </v-tooltip>
                <v-tooltip top>
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn color="primary"
                               v-bind="attrs"
                               v-on="on"
                               :outlined="preview"
                               @click="loadBadgeDataDialog = true">
                            <v-icon>mdi-briefcase-upload</v-icon>
                        </v-btn>
                    </template>
                    <span>Load Preview Data</span>
                </v-tooltip>
                <v-tooltip top>
                        <template v-slot:activator="{ on, attrs }">                        
                            <v-btn color="primary"
                                        v-bind="attrs"
                                        v-on="on"
                                   :outlined="preview"
                                @click="ExecutePrint">
                                        <v-icon>mdi-printer-eye</v-icon>
                            </v-btn>
                        </template>
                        <span>Print</span>
                    </v-tooltip>
            </v-card>
        </div>
    </v-speed-dial>

    
    <v-dialog v-model="printPanel"
                            fullscreen
                            transition="none">
        <v-card :class="{ 'printing': printPanel }">
        </v-card>
    </v-dialog>
</v-container>
</template>

<script >
import admin from '../api/admin';
import Vue from "vue";
import interact from "interactjs";
import fileStoreDropdown from '@/components/fileStoreDropdown.vue';
import formatPropEditForm from '@/components/formatpieces/formatPropEditForm.vue';
import fieldPositioner from '@/components/formatpieces/fieldPositioner.vue';
import fieldEditToolbar from '@/components/formatpieces/fieldEditToolbar.vue';
import scaleToParent from '@/components/formatpieces/scaleToParent.vue';
import badgeFullRender from '@/components/badgeFullRender.vue';
import {
    mapGetters
} from 'vuex'
export default Vue.extend({
    components: {
        fileStoreDropdown,
        formatPropEditForm,
        fieldPositioner,
        fieldEditToolbar,
        //scaleToParent
    },
    props: ['value'],
    data: function() {
        var v = this.value || {};
        return {
            preview: false,
            mainPropsForm: false,
            fab: false,
            bgImageURL:'',
            zoom: 1,
            printPanel: false,
            model: {
                id: v.id,
                name: v.name || 'New Badge Format',
                customSize: v.customSize || '5in*3in',
                bgImageID: v.bgImageID,
                layoutPosition: v.layoutPosition || null,
                layout: v.layout || []
            },
            fieldTypes: [{
                    name: 'debug',
                    title: 'Debug field'
                },
                {
                    name: 'simpletext',
                    title: 'Template Text'
                },
                {
                    name: 'text',
                    title: 'Markdown text'
                },
                {
                    name: 'image',
                    title: 'Image'
                },
                {
                    name: 'unknown',
                    title: 'Unknown'
                },
            ],
            fieldSelected: null,
            fieldSelectedIx: -1,
            fieldSelectedFromMove: false,
        };
    },
    computed: {
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
            'authToken': 'getAuthToken',
        }),
        ...mapGetters('products', {
            'badgeContexts': 'badgeContexts',
        }),
        sSizeArray() {
            //TODO: Retrieve default size somewhere else and inject it here?
            return (this.model.customSize || '5in*3in').split('*');
        },
        sWidth() {
            if (this.sSizeArray.length > 0)
                return this.sSizeArray[0];
            return '5in';
        },
        sHeight() {
            if (this.sSizeArray.length > 1)
                return this.sSizeArray[1];
            return '3in';
        },
        sStyle() {
            var v = {
                height: this.sHeight,
                width: this.sWidth,
                position: 'relative',
                'z-index': 0,
                backgroundImage:this.bgImageURL.length > 2 ?(  "url('" + this.bgImageURL + "')" ): 'none',
                backgroundSize:'contain, cover'
            };
            return v;
        },
        fieldIsSelected() {
            return this.fieldSelectedIx > -1;
        },
    },
    methods: {
        setbackground(bgData){
            this.bgImageURL = global.config.apiHostURL + 'Filestore/' + bgData.id + '/' + encodeURI(bgData.name) + '?auth=' + this.authToken
        },
        addField(fieldType) {
            console.log('Adding new field', fieldType)
            this.model.layout.push({
                type: fieldType,
                text: 'New Item'
            })
        },
        delField(ix) {
            console.log('Deleting field', ix)
            this.model.layout.splice(ix, 1);
            this.fieldSelectedIx = -1;
        },
        toggleSelected(ix) {
            if (this.fieldSelectedIx == ix && !this.fieldSelectedFromMove) {
                console.log('toggle: deselecting field', ix)
                this.fieldSelectedIx = -1;
                return;
            }
            this.fieldSelectedFromMove = false;
            console.log('toggle: selecting field', ix)
            this.fieldSelectedIx = ix;
        },
        selectField(ix) {
            this.fieldSelectedFromMove = true;
            if (this.fieldSelectedIx == ix)
                return;
            console.log('direct: selecting field', {
                new: ix,
                prior: this.fieldSelectedIx
            })
            this.fieldSelectedIx = ix;
            this.mainPropsForm = false;
        },
        editBadgeData() {
            this.editBadgeDataDialog = true;
        },
        loadBadgeData() {

            admin.genericGet(this.authToken, 'Badge/CheckIn/' + this.loadBadgeDataContext + '/' + this.loadBadgeDataID, null, (badgeData) => {

                this.badgeData = badgeData;
                this.loadBadgeDataDialog = false;
            }, function() {
                //Whoops
            })
        },
        zoomIn() {

        },
        zoomOut() {

        },
        ExecutePrint: async function() {

            this.printPanel = true;
            await this.$nextTick();
            //Print and close
            (function(app) {
                setTimeout(() => {
                    window.print();
                }, 430);
            }(this));
        },
        printLocalStart: function() {
            if(this.$el.clientHeight == 0){
                console.log('Ignoring print because format editor is not visible')
                return;
            }
            console.log('Prep printing')
            this.printPanel = true;

        },
        printLocalEnd: function() {
            console.log('Done printing')
            this.printPanel = false;

        },
    },
    watch: {
        model(newData) {
            // if (this.skipEmitOnce == true) {
            //     this.skipEmitOnce = false;
            //     return;
            // }
            this.$emit('input', newData);
        },
        value(newValue) {
            console.log('a new value',newValue)
            //Splat the input into the form
            // this.skipEmitOnce = true;
            if (!newValue)
                this.fieldSelectedIx = -1;
            // this.model = {
            //     name: 'New Badge Format',
            //     customSize: '5in*3in',
            //     bgImageID: null,
            //     layoutPosition: null,
            //     layout: [],
            //     ...newValue,
            // };
            this.model = newValue;
        },
    },
    mounted: function() {
        //Hook the printing events
        window.addEventListener('beforeprint', this.printLocalStart);
        window.addEventListener('afterprint', this.printLocalEnd);
        console.log('format editor watching for print preview')

    },
    created() {
        this.model = this.value;
    },
  beforeDestroy() {
    console.log(`format editor no longer watching for print preview.`)
        //Un-hook the printing events
        window.removeEventListener('beforeprint', this.printLocalStart);
        window.removeEventListener('afterprint', this.printLocalEnd);
  }
    
});
</script>
