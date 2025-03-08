<template>
    <v-container fluid fill-height class="pa-0">
        <v-toolbar :color="'blue'" dark style="position: sticky; top: 0; z-index: 1;">
            <v-app-bar-nav-icon @click.stop="mainPropsForm = !mainPropsForm" />
            <v-toolbar-title>{{ model.name }}</v-toolbar-title>
            <v-spacer></v-spacer>

            <template v-if="!fieldIsSelected">
                <v-menu offset-y>
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn color="primary" v-bind="attrs" v-on="on">
                            <v-icon>mdi-plus</v-icon>
                        </v-btn>
                    </template>
                    <v-list>
                        <v-list-item v-for="(fieldType, index) in fieldTypes" :key="index" @click="addField(index)">
                            <v-list-item-title>{{ fieldType.title }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </template>
            <template v-else>

                <v-autocomplete dense hide-details v-model="model.coords[fieldSelectedIx].location_id"
                    :items="locationList" persistent-placeholder item-value="id" item-text="searchtext">
                    <template v-slot:label>
                        Location
                    </template>
                    <template v-slot:selection="data">
                        <v-chip label small>{{ data.item.short_code }}</v-chip>
                        {{ data.item.name }}
                    </template>
                    <template v-slot:item="{ item, on, attrs }">
                        <v-list-item v-bind="attrs" v-on="on">
                            <v-list-item-action>
                                <v-chip label small>{{ item.short_code }}</v-chip>
                            </v-list-item-action>
                            <v-list-item-content>
                                <v-list-item-title :class="attrs.inputValue ? 'primary--text' : ''">
                                    {{ item.name }}
                                </v-list-item-title>
                            </v-list-item-content>
                        </v-list-item>
                    </template>
                </v-autocomplete>

                <v-btn color="primary" @click="delField(fieldSelectedIx)">
                    <v-icon>mdi-delete</v-icon>
                </v-btn>
            </template>
        </v-toolbar>

        <v-container fluid>
            <v-spacer></v-spacer>
            <v-card>
                <v-sheet>

                    <v-img color="white" class="mx-auto ma-3" lazy-src="@/assets/logo.svg" :src="bgImageURL"
                        ref="hiddenImg" elevation="4" contain @load="bgLoaded" @loading="bgLoading">
                        <fieldPositioner v-for="(item, ix) in model.coords.filter(_ => bgImageLoaded)" :key="ix"
                            :format="coordFormat(ix)" @update:format="coordFormatdUpdate(ix, $event)"
                            :value="coordTemplateData(item)" :readOnly="!bgImageLoaded" :edit="ix == fieldSelectedIx"
                            :order="ix" @click="toggleSelected(ix)" @move="selectField(ix)" />
                    </v-img>
                </v-sheet>
            </v-card>
            <v-spacer></v-spacer>
        </v-container>
        <v-navigation-drawer v-model="mainPropsForm" absolute width="400" temporary>

            <v-list>
                <v-list-item>
                    <v-list-item-content>
                        <v-text-field v-model="model.name" label="Name" />
                    </v-list-item-content>
                </v-list-item>
                <v-list-item>
                    <v-list-item-content>
                        <v-checkbox dense hide-details v-model="model.active">
                            <template v-slot:label>
                                Active
                            </template>
                        </v-checkbox>
                    </v-list-item-content>
                </v-list-item>
                <v-list-item>
                    <v-list-item-content>
                        <fileStoreDropdown v-model="model.bgImageID" @selected="setbackground" />
                    </v-list-item-content>
                </v-list-item>
                <v-list-item>
                    <v-list-item-content>
                        <v-textarea label="Public Description" v-model="model.description" />
                    </v-list-item-content>
                </v-list-item>
                <v-list-item>
                    <v-list-item-content>
                        <v-textarea label="Notes" v-model="model.notes" />
                    </v-list-item-content>
                </v-list-item>
            </v-list>
        </v-navigation-drawer>
    </v-container>
</template>

<script>
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
        // formatPropEditForm,
        fieldPositioner,
        // fieldEditToolbar,
        // scaleToParents
        // Viewer,
        // Teleport
    },
    props: ['value'],
    data: function () {
        var v = this.value || {};
        return {
            preview: false,
            mainPropsForm: false,
            fab: false,
            bgImageURL: '',
            bgImageLoaded: false,
            zoom: 1,
            printPanel: false,
            model: {},
            fieldTypes: [{
                name: 'debug',
                title: 'Debug field'
            },
            {
                title: 'Short Code',
                templatetext: '[[short_code]]',
            },
            {
                title: 'Name',
                templatetext: '[[name]]',
            },
            {
                title: 'Short code and name',
                templatetext: '[ [[short_code]] ] [[name]]',
            },
            ],
            fieldSelected: null,
            fieldSelectedIx: -1,
            fieldSelectedFromMove: false,
            fieldTemplate: null,
            fieldFormatPainting: false,
        };
    },
    computed: {
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
            'authToken': 'getAuthToken',
        }),
        ...mapGetters('products', {
            'locations': 'locations',
            'locationCategories': 'locationCategories',
            'locationEvents': 'locationEvents',
        }),

        locationList() {
            var result = structuredClone(this.locations)
                .map(x => { return { ...x, searchtext: x.short_code + ' ' + x.name } });
            //Order by short code
            result.sort((a, b) => (a.short_code > b.short_code) ? 1 : ((b.short_code > a.short_code) ? -1 : 0));
            return result;
        },
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
                backgroundImage: this.bgImageURL.length > 2 ? ("url('" + this.bgImageURL + "')") : 'none',
                backgroundSize: 'contain, cover'
            };
            return v;
        },
        fieldIsSelected() {
            return this.fieldSelectedIx > -1;
        },
        coordTemplateData() {
            return (coord) => {
                //var coord = this.model.coords[ix];
                //Shouldn't happen, but just in case...
                if (coord == undefined) return {};

                return this.locations.find(x => x.id == coord.location_id) || {
                    Assignmentcount: 0,
                    description: '[Desc Loading...]',
                    name: '[Name Loading...]',
                    short_code: '...'
                };

            }
        },
    },
    methods: {
        setbackground({ id, name }) {
            this.bgImageURL = global.config.apiHostURL + 'Filestore/' + id + '/' + encodeURI(name) + '?auth=' + this.authToken
        },
        bgLoading() {
            console.log('bg loading')
            this.bgImageLoaded = false;
        },
        bgLoaded() {
            console.log('bg loaded')
            this.bgImageLoaded = true;

        },
        updateAspectRatio() {
            if (this.$refs.hiddenImg) {
                const img = this.$refs.hiddenImg;
                var newAR = img.image.naturalWidth / img.image.naturalHeight;
                console.log('new aspect ratio', newAR, img)
                this.bgImageAspectRatio = newAR;

            } else {
                console.log('attempting to update aspect ratio but the image does not exist?')
            }
        },
        inited(viewer) {
            this.$viewer = viewer
        },
        show() {
            this.$viewer.show()
        },
        addField(fieldTypeIx) {
            console.log('Adding new field', this.fieldTypes[fieldTypeIx].title.toString())
            var newCoord = {
                id: -1 * Math.ceil(Math.random() * 1000),
                coords: fieldTypeIx + ':0.2:0.2:0.2:0.2',
                location_id: null
            }
            this.model.coords.push(newCoord)
        },
        delField(ix) {
            console.log('Deleting field', ix)
            this.model.coords.splice(ix, 1);
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
        coordSelect(ix) {
            console.log('Selecting coord', ix)
            this.$emit('selectcoord', ix);
        },
        zoomIn() {

        },
        zoomOut() {

        },
        coordFormat(ix) {
            var c = this.model.coords[ix];
            var [typeId, left, top, width, height, ...extra] = c.coords.split(':');
            // console.log('decoded coords', c.coords, typeId, left, top, width, height)
            var result = {
                fieldTypeIx: typeId,
                type: typeId > 0 ? 'simpletext' : 'debug',
                text: this.fieldTypes[typeId]?.templatetext || '',
                left: parseFloat(left),
                top: parseFloat(top),
                width: parseFloat(width),
                height: parseFloat(height),
                fit: 'scale-down',
                style: {
                    'font-size': '36px',
                    'text-align': 'center',
                    'justify-content': 'center',
                    'align-content': 'center',
                    //Magic to take any extra values as kvp split on = and splat them into the style area
                    ...extra.map(e => e.split('=', 2)).map(([key, value]) => ({ [key]: value }))
                        .reduce((acc, curr) => ({ ...acc, ...curr }), {})
                }
            }
            // console.log('reult', (result))
            return result;
        },
        coordFormatdUpdate(ix, data) {
            // console.log('coord data update', ix, data)
            this.model.coords[ix].coords =
                [
                    data.fieldTypeIx,
                    data.left, data.top, data.width, data.height,
                    //Magic to take the extra values in the styles and kvp them
                    ...Object.keys(data.style)
                        .filter(f => [
                            'font-size',
                            'text-align',
                            'justify-content',
                            'align-content',
                        ].findIndex(j => f != j) == -1)
                        .map(m => m + "=" + data.style[m])
                ].join(':')
        }
    },
    watch: {
        model(newData) {
            // console.log('model updated', newData)
            this.$emit('input', newData);
        },
        value: {
            handler(newValue) {
                // console.log('a new value', newValue)
                //Splat the input into the form
                if (!newValue)
                    this.fieldSelectedIx = -1;
                this.model = newValue;
                this.setbackground({ id: newValue.bgImageID, name: newValue.bgFileName });
            },
            immediate: true
        },
    },
    mounted: function () {

    },
    created() {
        // this.model = this.value;
    },
    beforeDestroy() {
    }

});
</script>
