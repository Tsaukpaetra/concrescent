<template>
    <v-card>
        <v-card-title class="headline">
            Import / Export Event Data
        </v-card-title>
        <v-divider class="my-2"></v-divider>

        <v-card-text>
            <v-tabs v-model="activeTab" fixed-tabs class="mb-4">
                <v-tab href="#export">
                    <v-icon left>mdi-upload</v-icon>
                    Export
                </v-tab>
                <v-tab href="#import">
                    <v-icon left>mdi-download</v-icon>
                    Import
                </v-tab>
            </v-tabs>

            <v-tabs-items v-model="activeTab">
                <v-tab-item value="export">
                    <EventImportExportWizard mode="export" />
                </v-tab-item>
                <v-tab-item value="import">
                    <EventImportExportWizard mode="import" />
                </v-tab-item>
            </v-tabs-items>
        </v-card-text>
    </v-card>
</template>

<script>
import EventImportExportWizard from '@/components/EventImportExportWizard.vue';

export default {
    name: 'ImportExportPage',
    components: {
        EventImportExportWizard,
    },
    data: () => ({
        activeTab: 'export',
    }),
};
</script>
