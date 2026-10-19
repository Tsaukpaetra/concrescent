<template>
<v-container fluid>
    <v-row>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-text-field label="Client ID"
                          v-model="model.client_id"
                          readonly>
            </v-text-field>
        </v-col>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-text-field label="Client Secret"
                          append-icon="mdi-refresh"
                            :type="secretIsFocused ? 'text' : 'password'"
                            @focus="secretIsFocused = true"
                            @blur="secretIsFocused = false"
                          @click:append="rotateSecret"
                          v-model="model.client_secret">
            </v-text-field>
        </v-col>
        <v-col cols="12"
               sm="6"
               md="3">
            <v-checkbox dense
                        hide-details
                        v-model="model.active">
                <template v-slot:label>
                    Active
                </template>
            </v-checkbox>
        </v-col>
    </v-row>
    <v-row>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-text-field label="App Name" required
                          v-model="model.app_name"
                          >
            </v-text-field>
        </v-col>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-text-field label="Logo URL"
                          v-model="model.logo_url"
                          >
            </v-text-field>
        </v-col>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-textarea label="Redirect URIs" auto-grow required
          rows="1"
                          v-model="model.redirect_uri"
                          >
            </v-textarea>
        </v-col>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-text-field label="Developer Name" 
                          v-model="model.developer_name"
                          >
            </v-text-field>
        </v-col>
    </v-row>
    <v-row>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-text-field label="Developer URL"
                          v-model="model.developer_url"
                          >
            </v-text-field>
        </v-col>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-text-field label="Developer Email"
                          v-model="model.developer_email"
                          >
            </v-text-field>
        </v-col>
        <v-col cols="6"
               sm="8"
               md="6">
            <v-text-field label="Contact ID"
                          v-model="model.contact_id"
                          >
            </v-text-field>
        </v-col>
    </v-row>

</v-container>
</template>

<script>
import admin from '../api/admin';
import {
    mapGetters
} from 'vuex'

function nullIfEmptyOrZero(inValue) {
    if (inValue == 0 || inValue == '' || inValue == null) return null;
    return inValue;
}

function undefinedIfEmptyOrZero(inValue) {
    if (inValue == 0 || inValue == '' || inValue == null) return undefined;
    return inValue;
}
export default {
    components: {},
    props: {
        'value': {
            type: Object
        },
        'readonly_perms': {
            type: Boolean
        }
    },
    data() {
        return {
            secretIsFocused: false,
            skipEmitOnce: false,
            validbadgeTypeInfo: false,
            model:
            {
                client_id: this.value?.client_id || '',
                contact_id: this.value?.contact_id || 0,
                active: this.value?.active || 0,
                app_name: this.value?.app_name || '',
                logo_url: this.value?.logo_url || '',
                developer_name: this.value?.developer_name || '',
                developer_email: this.value?.developer_email || '',
                developer_url: this.value?.developer_url || '',
                redirect_uri: this.value?.redirect_uri || '',
                date_created: this.value?.date_created || '',
                date_token: this.value?.date_token || '',
                date_modified: this.value?.date_modified || '',
                client_secret: this.value?.client_secret || ''
            },

            RulesRequired: [
                (v) => !!v || 'Required',
            ],
        };
    },
    computed: {
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
        }),
        ...mapGetters('products', {
            'badgeContexts': 'badgeContexts',
        }),
        result() {
            return {
                
                client_id: undefinedIfEmptyOrZero(this.model.client_id) ,
                contact_id: this.model.contact_id || 0,
                active: this.model.active ? 1 : 0,
                app_name: this.model.app_name || '',
                logo_url: this.model.logo_url || '',
                developer_name: this.model.developer_name || '',
                developer_email: this.model.developer_email || '',
                developer_url: this.model.developer_url || '',
                redirect_uri: this.model.redirect_uri || '',
                date_created: this.model.date_created || '',
                date_token: this.model.date_token || '',
                date_modified: this.model.date_modified || '',
                client_secret: this.model.client_secret || '',

            }
        },
    },
    methods: {
        rotateSecret(){

        }
        // saveStartDate(date) {
        //     this.$refs.menuStartDate.save(date);
        //     this.model.start_date = this.model.start_date;
        // },
        // saveEndDate(date) {
        //     this.$refs.menuEndDate.save(date);
        //     this.model.end_date = this.model.end_date;
        // },
    },
    watch: {
        model: {
            deep: true,
            handler(newModel) {
                // We transform the data here and emit the result
                // This breaks the loop because we aren't watching the 'result' computed property
                this.$emit('input', this.result);
            }
        },
        // Watch the prop to update the internal model
        value: {
            handler(newValue) {
                if (newValue) {
                    // Merges newValue into the existing result object without changing the reference
                    Object.assign(this.model, newValue);
                }
            },
            deep: true
        }
    },
    created() {
        
    }
};
</script>
