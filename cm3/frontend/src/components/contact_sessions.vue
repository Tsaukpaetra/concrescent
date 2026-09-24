<template>
    <div>
        <simple-list :api-path="apiBase + '/session'" :header-key="headerKey" :actions="actions" :add-headers="addHeaders"
            :show-export="true" :dense="true" :isEditingItem="(editDialog || revokeDialog)" @revoke="handleRevoke" @edit="handleEdit">
            <template v-slot:[`item.status`]="{ item }">
                <v-chip small :color="getStatusColor(item.status)" dark>
                    {{ item.status }}
                </v-chip>
            </template>

            <template v-slot:[`item.date_modified`]="{ item }">
                {{ formatDate(item.date_modified) }}
            </template>

            <template v-slot:[`item.ip_address`]="{ item }">
                <v-tooltip bottom max-width="400">
                    <template v-slot:activator="{ on, attrs }">
                        <span v-bind="attrs" v-on="on" class="cursor-pointer">
                            {{ formatIpAddress(item.ip_address) }}
                        </span>
                    </template>
                    <span>{{ item.ip_address }}</span>
                </v-tooltip>
            </template>
        </simple-list>


        <!-- Edit Session Dialog -->
        <v-dialog v-model="editDialog">
            <v-card>
                <v-card-title class="headline">Edit Session {{ editingSession.token_timestamp }}</v-card-title>
                <v-card-text>
                    <v-container>
                        <v-row>
                            <!-- Editable Fields -->
                            <v-col cols="12">
                                <v-text-field v-model="editingSession.description" label="Description"
                                    outlined></v-text-field>
                            </v-col>
                        </v-row>
                        <v-row>
                            <v-col cols="12">
                                <h3>Metadata</h3>
                                <JsonEditorVue v-model="editingSession.metadata" />
                            </v-col>
                        </v-row>
                        <v-row>
                            <!-- Display Only Fields -->
                            <v-col cols="6" sm="12">
                                <div class="text-subtitle-2">User Agent</div>
                                <div class="mb-2">{{ editingSession.user_agent }}</div>
                            </v-col>
                            <v-col cols="6" sm="12">
                                <div class="text-subtitle-2">IP Address</div>
                                <div class="mb-2">{{ editingSession.ip_address }}</div>
                            </v-col>
                        </v-row>
                        <v-row>
                            <v-col cols="2" sm="2" md="2">
                                <div class="text-subtitle-2">Session Type</div>
                                <div class="mb-2">{{ editingSession.session_type }}</div>
                            </v-col>
                            <v-col cols="2" sm="2" md="2">
                                <div class="text-subtitle-2">Date Created</div>
                                <div class="mb-2">{{ editingSession.date_created }}</div>
                            </v-col>
                            <v-col cols="2" sm="2" md="2">
                                <div class="text-subtitle-2">Last Refreshed</div>
                                <div class="mb-2">{{ editingSession.date_modified }}</div>
                            </v-col>
                        </v-row>

                    </v-container>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="blue darken-1" text @click="editDialog = false">Cancel</v-btn>
                    <v-btn color="blue darken-1" text :loading="loading" @click="saveSession">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Revoke Session Dialog -->
        <v-dialog v-model="revokeDialog" max-width="400px">
            <v-card>
                <v-card-title class="headline">Revoke Session</v-card-title>
                <v-card-text>
                    Are you sure you want to revoke session 
                    <strong>{{ revokingSession.token_timestamp }}</strong>?
                    This action cannot be undone.
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="blue darken-1" text @click="revokeDialog = false">Cancel</v-btn>
                    <v-btn color="error" text :loading="loading" @click="confirmRevoke">Revoke</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import admin from '../api/admin';
import SimpleList from '@/components/simpleList.vue';

export default {
    name: 'ContactSessions',
    components: {
        SimpleList
    },
    props: {
        contact_id: {
            type: [Number, String],
            default: null
        }
    },
    data() {
        return {
            headerKey: {
                text: 'Session ID',
                align: 'start',
                value: 'token_timestamp',
            },
            addHeaders: [
                { text: 'Description', value: 'description'},
                { text: 'IP Address', value: 'ip_address'},
                { text: 'Last Refreshed', value: 'date_modified'},
                { text: 'Session Type', value: 'session_type'},
            ],
            actions: [
                {
                    name: 'edit',
                    text: 'Edit',
                    icon: 'edit',
                    color: 'primary',
                    iconOnly: false
                },
                {
                    name: 'revoke',
                    text: 'Revoke',
                    icon: 'delete',
                    color: 'error',
                    iconOnly: false
                }
            ],
            editDialog: false,
            loading: false,
            editingSession: {
                "contact_id": 0,
                "token_timestamp": 0,
                "description": "",
                "user_agent": "",
                "ip_address": "",
                "session_type": "",
                "metadata": [],
                "date_created": "",
                "date_modified": ""
            },
            revokeDialog: false,
            revokingSession: 0,
        };
    },
    computed: {
        apiBase() {
            return this.contact_id ? '/Contact/' + this.contact_id : '/account';
        }
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return '';
            //TODO: This is server time, but we're assuming the server is UTC...
            const date = new Date(dateString + ' UTC');
            return date.toLocaleString();
        },
        formatIpAddress(ip) {
            if (!ip) return '';

            // Handle IPv6 Compression/Display
            if (ip.includes(':')) {
                // If the address is very long (full IPv6), truncate for display
                // We keep the first part and end with ellipsis to signal more data
                if (ip.length > 15) {
                    let compressed = ip.toLowerCase().replace(/(^|:)0+(?!\b)/g, '$1');
                    compressed = compressed.replace(/(?:^|:)0(?::0)+(?:\$|:)/, '::');

                    // 2. Length evaluation and truncation
                    if (compressed.length > 20) {
                        return `${compressed.slice(0, 12)}...${compressed.slice(-4)}`;
                    }
                    return compressed;
                }
                return ip;
            }

            return ip;
        },
        handleEdit(item) {
            this.loading = true;

            admin.genericGet(`${this.apiBase}/session/${item.token_timestamp}`, null, (result) => {
                this.editingSession = result
                this.editDialog = true;
                this.loading = false;
            }, (err) => {
                console.error('Failed to fetch session details', err);
                this.loading = false;
            });
        },
        async saveSession() {
            this.loading = true;
            await admin.genericPost(`${this.apiBase}/session/${this.editingSession.token_timestamp}`, {
                description: this.editingSession.description,
                metadata: this.editingSession.metadata
            }, () =>{
                this.editDialog = false;
                this.loading = false;
            }, (err) =>{
                console.error('Failed to save session', err);
                this.loading = false;
            });
        },
        handleRevoke(item) {
            this.revokingSession = item.token_timestamp;
            this.revokeDialog = true;
        },
        confirmRevoke() {
            this.loading = true;
            
            admin.genericDelete(`${this.apiBase}/session/${this.revokingSession}`, (result) => {
                this.revokeDialog = false;
                this.loading = false;
            }, (err) => {
                console.error('Failed to revoke session', err);
                this.loading = false;
            });
        }
    }
};
</script>