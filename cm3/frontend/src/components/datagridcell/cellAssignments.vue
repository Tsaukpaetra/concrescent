<template>
    <v-toolbar dense flat color="transparent">
        <v-tooltip v-for="(id, ix) in location_ids" :key="id" left>
            <template v-slot:activator="{ on, attrs }">
                <v-chip v-bind="attrs" v-on="on" :color="getCategory(category_ids[ix]).color">{{
                    location(id).short_code}}</v-chip>
            </template>
            <v-card>
                <v-card-title>{{ location(id).name }}</v-card-title>
                <v-card-subtitle>{{ getCategory(category_ids[ix]).name }}</v-card-subtitle>
                <v-card-text>{{ location(id).description }}</v-card-text>
            </v-card>
        </v-tooltip>
        <!-- <v-spacer></v-spacer>
        <v-btn small icon><v-icon>mdi-pencil</v-icon></v-btn> -->
    </v-toolbar>
</template>

<script>
import {
    mapActions,
    mapGetters
} from 'vuex';
import admin from '../../api/admin';
export default {
    components: {},
    props: ['application', 'location_ids', 'category_ids'],
    data() {
        return {
            skipEmitOnce: false,
            currentValue: this.value,
            loading: false,
        };
    },
    computed: {
        ...mapGetters('products', {
            'locationListData': 'locations',
            'categoryList': 'locationCategories'
        }),
        authToken: function () {
            return this.$store.getters['mydata/getAuthToken'];
        },
        location() {
            return (id) => {
                return this.locationListData.find(x => x.id == id) || {
                    short_code: '[' + id + ']',
                    name: '[Loading]'
                };
            }
        },
        getCategory() {
            return (category_id) => {
                return this.categoryList.find(x => x.id == category_id) || {
                    //Default blue if we don't have that category loaded yet
                    color: '#2196F3',
                    name: '[Loading/Unknown]'
                };
            };
        },
    },
    methods: {
    },
    watch: {
        currentValue: function (newValue) {
            this.loading = true;
            this.$emit('input', newValue);
        },
        value: {
            handler: function (newValue) {
                this.currentValue = newValue;
                this.loading = false;
            },
        }
    },
    async created() {
        await this.$store.dispatch('products/getLocations', this.context_code);
        await this.$store.dispatch('products/getLocationCategories', this.context_code);
    },
};
</script>
