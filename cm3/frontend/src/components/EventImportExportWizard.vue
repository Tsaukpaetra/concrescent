<template>
  <v-card>
    <v-card-title class="headline">{{ isImport ? 'Import' : 'Export' }} Event Data</v-card-title>
    <v-divider class="my-2"></v-divider>

    <v-stepper alt-labels v-model="step" flat>
      <v-stepper-header>
        <template v-if="!isImport">
          <v-stepper-step step="1" complete>Data Types</v-stepper-step>
          <v-divider></v-divider>
          <v-stepper-step step="2" complete>Context Codes</v-stepper-step>
          <v-divider></v-divider>
          <v-stepper-step step="3" complete>Preview</v-stepper-step>
          <v-divider></v-divider>
          <v-stepper-step step="4" complete>Download</v-stepper-step>
        </template>
        <template v-else>
          <v-stepper-step step="1" complete>Upload</v-stepper-step>
          <v-divider></v-divider>
          <v-stepper-step step="2" complete>Context Codes</v-stepper-step>
          <v-divider></v-divider>
          <v-stepper-step step="3" complete>Review</v-stepper-step>
          <v-divider></v-divider>
          <v-stepper-step step="4" complete>Options</v-stepper-step>
          <v-divider></v-divider>
          <v-stepper-step step="5" complete>Result</v-stepper-step>
        </template>
      </v-stepper-header>

      <v-stepper-items>

        <!-- ======================== EXPORT STEPS ======================== -->
        <template v-if="!isImport">
          <!-- Step 1: Data Types -->
          <v-stepper-content step="1">
            <p class="subtitle-1 mb-4">Choose which data types to export:</p>
            <v-row>
              <v-col
                v-for="dt in dataTypeOptions"
                :key="dt.value"
                cols="12"
                sm="6"
                md="4"
              >
                <v-checkbox
                  v-model="exportInclude"
                  :value="dt.value"
                  :label="dt.label"
                  :hint="dt.hint"
                  persistent-hint
                  dense
                ></v-checkbox>
              </v-col>
            </v-row>
            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn color="primary" :disabled="exportInclude.length === 0" @click="step = 2">Next</v-btn>
            </v-card-actions>
          </v-stepper-content>

          <!-- Step 2: Context Codes -->
          <v-stepper-content step="2">
            <p class="subtitle-1 mb-4">
              Filter by context codes (leave empty for all):
            </p>
            <v-row>
              <v-col cols="10">
                <v-select
                  v-model="exportContextCodes"
                  :items="contextCodes"
                  return-object
                  chips
                  multiple
                  outlined
                  label="Context Codes"
                  clearable
                >
                  <template v-slot:selection="{ item }">
                    <v-chip small>
                      {{ item.context_code }} — {{ item.name }}
                    </v-chip>
                  </template>
                  <template v-slot:item="{ item }">
                    {{ item.context_code }} — {{ item.name }}
                  </template>
                </v-select>
              </v-col>
            </v-row>
            <v-alert type="info" text dense class="mt-2">
              Only groups, badge types, addons, questions, and templates will be filtered by context code.
            </v-alert>
            <v-card-actions>
              <v-btn @click="step = 1">Previous</v-btn>
              <v-spacer></v-spacer>
              <v-btn color="primary" @click="doExportPreview">Next</v-btn>
            </v-card-actions>
          </v-stepper-content>

          <!-- Step 3: Preview -->
          <v-stepper-content step="3">
            <p class="subtitle-1 mb-4">Preview of data to export:</p>

            <!-- Summary card -->
            <v-card outlined class="pa-3 mb-3">
              <v-row dense>
                <v-col cols="4">
                  <strong>Context Codes:</strong>
                  <v-chip-group v-if="exportContextCodes.length" active-class="primary--text" small wrap>
                    <v-chip
                      v-for="cc in exportContextCodes"
                      :key="cc.context_code"
                    >{{ cc.context_code }} — {{ cc.name }}</v-chip>
                  </v-chip-group>
                  <span v-else class="text--secondary">All contexts</span>
                </v-col>
                <v-col cols="8">
                  <strong>Data types:</strong>
                  <v-chip-group small wrap class="mt-1">
                    <v-chip
                      v-for="dt in exportInclude"
                      :key="dt"
                      class="mr-1"
                    >{{ dataTypeLabels[dt] || dt }}</v-chip>
                  </v-chip-group>
                </v-col>
              </v-row>
            </v-card>

            <!-- Hierarchical tree view when groups are included -->
            <EventHierarchicalPreview
              v-if="exportInclude.includes('groups') && exportPreviewGroups.length"
              :groups="exportPreviewGroups"
              class="mt-4"
            />

            <!-- Flat entity previews (no groups, or additional flat types) -->
            <LocalTable
              v-for="entity in exportFlatPreview"
              :key="entity.key"
              :title="entity.label"
              :items="entity.items"
              :headers="entity.key === 'formats' ? formatHeaders : undefined"
              class="mb-3 mt-4"
              dense
            >
              <template v-slot:[`item.layout`]="{item}">              
                <v-col cols="6">
                  <div class="subtitle-2 mb-1">Badge Preview</div>
                  <v-sheet color="white" elevation="2" class="pa-2 text-center">
                    <badgeFullRender
                      :format="typeof item.layout === 'string' ? JSON.parse(item.layout) : item.layout"
                    />
                  </v-sheet>
                </v-col>
              </template>
              <template v-slot:child="{ item }" v-if="entity.key === 'formats'">
                <tr @click.stop>
                  <td :colspan="colspan">
                    <v-card flat class="mt-2">
                      <v-card-text class="pa-0">
                        <v-row>
                          <v-col cols="12" v-if="item.maps && item.maps.length">
                            <div class="subtitle-2 mb-1">Format Maps</div>
                            <v-card outlined dense class="pa-2">
                              <div class="body-2 font-weight-medium mb-1">Badge Type References</div>
                              <v-chip
                                v-for="(map, ix) in item.maps"
                                :key="ix"
                                small
                                class="mr-1 mb-1"
                                color="primary"
                                dark
                              >
                                {{ map.context_code }} — {{ map.name }}
                              </v-chip>
                            </v-card>
                          </v-col>
                          <v-col cols="12" v-else>
                            <div class="body-2 text--secondary">No format maps for this format.</div>
                          </v-col>
                        </v-row>
                      </v-card-text>
                    </v-card>
                  </td>
                </tr>
              </template>
            </LocalTable>

            <v-card-actions>
              <v-btn @click="step = 2">Previous</v-btn>
              <v-spacer></v-spacer>
              <v-btn color="primary" @click="doExportDownload">Next — Download</v-btn>
            </v-card-actions>
          </v-stepper-content>

          <!-- Step 4: Download -->
          <v-stepper-content step="4">
            <v-alert type="success" prominent icon="mdi-check-decagram">
              Export data is ready for download.
            </v-alert>

            <v-row class="mt-4">
              <v-col cols="6">
                <v-btn color="primary" large block @click="doExportDownload">
                  <v-icon left>mdi-download</v-icon>
                  Download JSON
                </v-btn>
              </v-col>
              <v-col cols="6">
                <v-btn color="secondary" large block @click="resetExport">
                  <v-icon left>mdi-reload</v-icon>
                  New Export
                </v-btn>
              </v-col>
            </v-row>

            <v-card outlined class="pa-3 mt-4">
              <div class="body-2 text--secondary">
                File size: {{ exportBlob ? formatBytes(exportBlob.size) : '—' }}<br>
                Format: JSON<br>
                Includes: {{ exportInclude.map(dt => dataTypeLabels[dt] || dt).join(', ') || '—' }}
              </div>
            </v-card>
          </v-stepper-content>
        </template>

        <!-- ======================== IMPORT STEPS ======================== -->
        <template v-else>
          <!-- Step 1: Upload -->
          <v-stepper-content step="1">
            <v-row>
              <v-col cols="12">
                <v-file-input
                  label="Upload exported JSON file"
                  accept="application/json"
                  outlined
                  prepend-icon="mdi-file-delimited"
                  v-model="importFile"
                  :loading="loading"
                  show-size
                  truncate-length="60"
                ></v-file-input>
              </v-col>
            </v-row>

            <v-divider class="my-4"></v-divider>

            <p class="subtitle-2 text--secondary">— or paste JSON —</p>
            <v-row>
              <v-col cols="12">
                <v-textarea
                  v-model="importJsonText"
                  label="Paste JSON"
                  outlined
                  rows="6"
                  auto-grow
                  monospace
                  hide-details
                ></v-textarea>
              </v-col>
            </v-row>

            <v-alert type="info" text dense class="mt-2">
              Upload a JSON file previously exported from the Event Export wizard. The file should contain metadata with an exported_at timestamp.
            </v-alert>

            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn
                color="primary"
                :disabled="!canImportPreview"
                @click="doImportParse"
              >Next</v-btn>
            </v-card-actions>
          </v-stepper-content>

          <!-- Step 2: Context Codes (for import filtering) -->
          <v-stepper-content step="2">
            <p class="subtitle-1 mb-4">
              Filter which contexts to import (leave empty for all):
            </p>
            <v-row>
              <v-col cols="10">
                <v-select
                  v-model="importContextCodes"
                  :items="availableContextCodes"
                  return-object
                  chips
                  multiple
                  outlined
                  label="Context Codes"
                  clearable
                >
                  <template v-slot:selection="{ item }">
                    <v-chip small>{{ item.context_code }} — {{ item.name }}</v-chip>
                  </template>
                  <template v-slot:item="{ item }">
                    {{ item.context_code }} — {{ item.name }}
                  </template>
                </v-select>
              </v-col>
            </v-row>
            <v-alert type="info" text dense class="mt-2">
              Importing with fewer contexts will skip data from unselected contexts.
            </v-alert>
            <v-card-actions>
              <v-btn @click="step = 1">Previous</v-btn>
              <v-spacer></v-spacer>
              <v-btn color="primary" @click="step = 3">Next</v-btn>
            </v-card-actions>
          </v-stepper-content>

          <!-- Step 3: Review -->
          <v-stepper-content step="3">
            <p class="subtitle-1 mb-4">Review data from uploaded file:</p>

            <!-- Summary card -->
            <v-card outlined class="pa-3 mb-3">
              <v-row dense>
                <v-col cols="4">
                  <strong>Source Event ID:</strong> {{ importSourceEventId }}
                </v-col>
                <v-col cols="4">
                  <strong>Exported:</strong> {{ importExportedAt }}
                </v-col>
                <v-col cols="4">
                  <strong>Context Codes:</strong>
                  <v-chip-group small wrap>
                    <v-chip v-for="cc in availableContextCodes" :key="cc.context_code" small class="mr-1">
                      {{ cc.context_code }}
                    </v-chip>
                  </v-chip-group>
                </v-col>
              </v-row>
            </v-card>

            <!-- Hierarchical tree view -->
            <EventHierarchicalPreview
              v-if="importParsedData && importParsedData.groups && importParsedData.groups.length"
              :groups="importParsedData.groups"
              :format-maps="importFormatMaps"
              class="mt-2"
            />

            <!-- Flat entity previews (no groups, or additional flat types) -->
            <LocalTable
              v-for="entity in importFlatPreview"
              :key="entity.key"
              :title="entity.label"
              :items="entity.items"
              :headers="entity.key === 'formats' ? formatHeaders : undefined"
              class="mb-3 mt-4"
              dense
            >
              <template v-slot:child="{ item }" v-if="entity.key === 'formats'">
                <tr @click.stop>
                  <td :colspan="colspan">
                    <v-card flat class="mt-2">
                      <v-card-text class="pa-0">
                        <v-row>
                          <v-col cols="6">
                            <div class="subtitle-2 mb-1">Badge Preview</div>
                            <v-sheet color="white" elevation="2" class="pa-2 text-center">
                              <badgeFullRender
                                :format="typeof item.layout === 'string' ? JSON.parse(item.layout) : item.layout"
                              />
                            </v-sheet>
                          </v-col>
                          <v-col cols="6" v-if="item.maps && item.maps.length">
                            <div class="subtitle-2 mb-1">Format Maps</div>
                            <v-card outlined dense class="pa-2">
                              <div class="body-2 font-weight-medium mb-1">Badge Type References</div>
                              <v-chip
                                v-for="(map, ix) in item.maps"
                                :key="ix"
                                small
                                class="mr-1 mb-1"
                                color="primary"
                                dark
                              >
                                {{ map.context_code }} — {{ map.name }}
                              </v-chip>
                            </v-card>
                          </v-col>
                          <v-col cols="12" v-else>
                            <div class="body-2 text--secondary">No format maps for this format.</div>
                          </v-col>
                        </v-row>
                      </v-card-text>
                    </v-card>
                  </td>
                </tr>
              </template>
            </LocalTable>

            <v-card-actions>
              <v-btn @click="step = 2">Previous</v-btn>
              <v-spacer></v-spacer>
              <v-btn color="primary" @click="step = 4">Next — Options</v-btn>
            </v-card-actions>
          </v-stepper-content>

          <!-- Step 4: Options -->
          <v-stepper-content step="4">
            <p class="subtitle-1 mb-4">Import options:</p>

            <v-row>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="importMode"
                  :items="['create', 'upsert']"
                  label="Import Mode"
                  outlined
                  hint="Create: only insert new records. Upsert: insert new + update existing by ID."
                  persistent-hint
                ></v-select>
              </v-col>
              <v-col cols="12" sm="6">
                <v-switch
                  v-model="importDryRun"
                  color="orange"
                  label="Dry Run (preview only, no changes)"
                  hint="Preview what would change without actually importing."
                  persistent-hint
                ></v-switch>
              </v-col>
            </v-row>

            <v-alert type="warning" dense class="mt-2">
              <strong>Dry run recommended:</strong> Run a dry first to verify the import will produce the expected results.
            </v-alert>

            <v-card-actions>
              <v-btn @click="step = 3">Previous</v-btn>
              <v-spacer></v-spacer>
              <v-btn
                color="primary"
                :loading="loading"
                @click="doImportExecute"
              >Execute Import</v-btn>
            </v-card-actions>
          </v-stepper-content>

          <!-- Step 5: Result -->
          <v-stepper-content step="5">
            <v-alert
              :type="importResultErrors.length === 0 ? 'success' : 'error'"
              prominent
              :icon="importResultErrors.length === 0 ? 'mdi-check-decagram' : 'mdi-alert'"
            >
              {{ importResultDryRun ? 'Dry run complete. No changes were made.' : (importResultErrors.length === 0 ? 'Import completed successfully.' : 'Import completed with errors.') }}
            </v-alert>

            <v-data-table
              v-if="importResultSummary.length"
              :headers="importResultHeaders"
              :items="importResultSummary"
              :items-per-page="-1"
              class="mt-3"
              dense
              hide-default-footer
            >
              <template v-slot:[`item.imported`]="{ item }">
                <v-chip small :color="item.imported > 0 ? 'green' : 'grey'" dark text-color="white">
                  {{ item.imported }}
                </v-chip>
              </template>
              <template v-slot:[`item.errors`]="{ item }">
                <v-chip small :color="item.errors.length > 0 ? 'red' : 'grey'" text-color="white">
                  {{ item.errors.length }}
                  <v-tooltip activator="parent" v-if="item.errors.length">
                    {{ item.errors.join('; ') }}
                  </v-tooltip>
                </v-chip>
              </template>
            </v-data-table>

            <v-row class="mt-4">
              <v-col cols="6">
                <v-btn
                  color="secondary"
                  large
                  block
                  @click="resetImport"
                >
                  <v-icon left>mdi-reload</v-icon>
                  New Import
                </v-btn>
              </v-col>
              <v-col cols="6">
                <v-btn
                  color="primary"
                  large
                  block
                  @click="step = 1"
                >
                  <v-icon left>mdi-file-document-edit</v-icon>
                  Adjust Options
                </v-btn>
              </v-col>
            </v-row>
          </v-stepper-content>
        </template>

      </v-stepper-items>
    </v-stepper>

    <v-dialog v-model="errorDialog" max-width="600">
      <v-card>
        <v-card-title class="headline error--text">Error</v-card-title>
        <v-divider></v-divider>
        <v-card-text>
          {{ errorMessage }}
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="errorDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script>
import admin from '../api/admin';
import exportFromJSON from 'export-from-json';
import EventHierarchicalPreview from './EventHierarchicalPreview.vue';
import EventLocalTable from './EventLocalTable.vue';
import badgeFullRender from './badgeFullRender.vue';

const DATA_TYPE_OPTIONS = [
  { value: 'groups', label: 'Groups & Badge Types', hint: 'Application groups with context codes, badge types, and addons' },
  { value: 'promos', label: 'Promo Codes', hint: 'Promotional discount codes (scoped to groups)' },
  { value: 'questions', label: 'Form Questions', hint: 'Questions per context with badge type mappings and required flags' },
  { value: 'templates', label: 'Email Templates', hint: 'Notification templates per context' },
  { value: 'locations', label: 'Locations', hint: 'Venue locations' },
  { value: 'location_categories', label: 'Location Categories', hint: 'Categories for locations' },
  { value: 'format_maps', label: 'Format Maps', hint: 'Badge format-to-badgetype mappings' },
  { value: 'formats', label: 'Badge Formats', hint: 'Badge print layouts' },
];

const DATA_TYPE_LABELS = {};
DATA_TYPE_OPTIONS.forEach(dt => { DATA_TYPE_LABELS[dt.value] = dt.label; });

const ALL_DATA_TYPE_VALUES = DATA_TYPE_OPTIONS.map(dt => dt.value);

export default {
  name: 'EventImportExportWizard',

  components: {
    EventHierarchicalPreview,
    LocalTable: EventLocalTable,
    badgeFullRender,
  },

  props: {
    mode: {
      type: String,
      default: 'export', // 'export' or 'import'
    },
  },

  data: () => ({
    loading: false,
    step: 1,
    isImport: false,
    contextCodes: [],
    errorDialog: false,
    errorMessage: '',

    // Export state
    exportInclude: [...ALL_DATA_TYPE_VALUES],
    exportContextCodes: [],
    exportBlob: null,
    exportRawData: null,
    exportPreviewGroups: [],
    exportFlatPreview: [],

    // Import state
    importFile: null,
    importJsonText: '',
    importParsedData: null,
    importMode: 'create',
    importDryRun: true,
    importContextCodes: [],
    importResult: null,
  }),

  computed: {
    dataTypeOptions() {
      return DATA_TYPE_OPTIONS;
    },

    dataTypeLabels() {
      return DATA_TYPE_LABELS;
    },

    canImportPreview() {
      return this.importFile || (this.importJsonText && this.importJsonText.trim().length > 0);
    },

    availableContextCodes() {
      // Groups from the import data
      let codes = [];
      if (this.importParsedData && this.importParsedData.groups) {
        codes = this.importParsedData.groups.map(g => ({
          context_code: g.context_code,
          name: g.name,
          id: g.id || 0,
        }));
      }
      // Always include built-in Staff and Attendee
      codes.push({ context_code: 'S', name: 'Staff', id: 0 });
      codes.push({ context_code: 'A', name: 'Attendee', id: 0 });
      return codes;
    },

    importFlatPreview() {
      if (!this.importParsedData) return [];
      const keys = [
        { key: 'locations', label: 'Locations' },
        { key: 'location_categories', label: 'Location Categories' },
        { key: 'formats', label: 'Badge Formats' },
      ];
      return keys
        .map(({ key, label }) => ({
          key,
          label,
          items: (this.importParsedData[key] || [])
            .map((item, i) => ({ ...item, _key: i })),
        }))
        .filter(e => e.items.length > 0);
    },

    /** Format maps for nested display under HierarchicalPreview */
    importFormatMaps() {
      if (!this.importParsedData) return [];
      return Array.isArray(this.importParsedData.format_maps)
        ? this.importParsedData.format_maps
        : [];
    },

    importResultHeaders() {
      return [
        { text: 'Entity Type', value: 'label' },
        { text: 'Imported', value: 'imported' },
        { text: 'Errors', value: 'errors' },
      ];
    },

    importResultSummary() {
      if (!this.importResult) return [];
      const summary = this.importResult.summary || {};
      return Object.entries(summary).map(([key, val]) => ({
        label: DATA_TYPE_LABELS[key] || key,
        key,
        imported: val.imported || 0,
        errors: val.errors || [],
      }));
    },

    importResultErrors() {
      if (!this.importResult) return [];
      const errors = [];
      Object.values(this.importResult.summary || {}).forEach(s => {
        errors.push(...(s.errors || []));
      });
      return errors;
    },

    importResultDryRun() {
      return this.importResult ? this.importResult.dry_run : false;
    },

    importSourceEventId() {
      if (!this.importParsedData) return '—';
      const meta = this.importParsedData.metadata || {};
      return meta.event_id || '—';
    },

    importExportedAt() {
      if (!this.importParsedData) return '—';
      const meta = this.importParsedData.metadata || {};
      return meta.exported_at || '—';
    },

    /** Custom headers for formats table to show name + layout preview only */
    formatHeaders() {
      return [
        { text: 'Name', value: 'name' },
        { text: 'Custom Size', value: 'customSize'},
        { text: 'Layout', value: 'layout' },
      ];
    },

    /** Column count for expanded rows (headers + expand column) */
    colspan() {
      return this.formatHeaders.length + 1;
    },
  },

  watch: {
    mode(newMode) {
      this.isImport = newMode === 'import';
    },
    step(newStep) {
      // Load context codes when entering the context step in export mode
      if (!this.isImport && newStep === 2 && this.contextCodes.length === 0) {
        this.loadContextCodes();
      }
    },
  },

  created() {
    this.isImport = this.mode === 'import';
  },

  methods: {
    /**
     * Load context codes for the export context-code selector.
     * Fetches groups and uses their context_code + name directly.
     * The API response doesn't include event_id (token-scoped implicitly),
     * so we show all groups without event filtering.
     */
    loadContextCodes() {
      admin.genericGetList('Group', { includeDefault: 'true' }, (results) => {
        // Build list from groups that have a real context_code
        // Filter out internal entries (id < 0 are built-in defaults)
        const groups = (results || []).filter(g => g.context_code);
        this.contextCodes = [
          ...groups,
          { context_code: 'S', name: 'Staff', id: 0 },
          { context_code: 'A', name: 'Attendee', id: 0 },
        ];
        // Deduplicate by context_code
        const seen = new Set();
        this.contextCodes = this.contextCodes.filter(c => {
          if (seen.has(c.context_code)) return false;
          seen.add(c.context_code);
          return true;
        });
      }, (err) => {
        console.error('Failed to load context codes:', err);
      });
    },

    // --- Export Methods ---

    async doExportPreview() {
      this.loading = true;
      try {
        const contextFilter = this.exportContextCodes.map(c => c.context_code);
        const data = await admin.exportEventData(
          this.exportInclude,
          contextFilter
        );

        // Store raw data for download
        this.exportRawData = data;
        this.exportBlob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });

        // Store groups for hierarchical preview
        this.exportPreviewGroups = Array.isArray(data.groups)
          ? data.groups
          : [];

        // Build flat entity previews (formats now have nested maps via child expansion)
        this.exportFlatPreview = [];
        const flatKeys = [
          { key: 'locations', label: 'Locations' },
          { key: 'location_categories', label: 'Location Categories' },
          { key: 'formats', label: 'Badge Formats' },
        ];
        for (const { key, label } of flatKeys) {
          const arr = data[key];
          if (Array.isArray(arr) && arr.length > 0) {
            this.exportFlatPreview.push({ key, label, items: arr });
          }
        }

        this.step = 3;
      } catch (err) {
        this.errorMessage = err.response?.data?.error || err.response?.data || 'Export failed';
        this.errorDialog = true;
      } finally {
        this.loading = false;
      }
    },

    doExportDownload() {
      if (!this.exportRawData) return;
      const eventInfo = Array.isArray(this.exportRawData.event) && this.exportRawData.event[0]
        ? this.exportRawData.event[0]
        : {};
      const fileName = (eventInfo.display_name || eventInfo.shortcode || 'event') + '-export';
      exportFromJSON({
        data: this.exportRawData,
        fileName,
        exportType: exportFromJSON.types.json,
        withBOM: true,
      });
    },

    resetExport() {
      this.exportInclude = [...ALL_DATA_TYPE_VALUES];
      this.exportContextCodes = [];
      this.exportBlob = null;
      this.exportRawData = null;
      this.exportPreviewGroups = [];
      this.exportFlatPreview = [];
      this.contextCodes = [];
      this.step = 1;
    },

    // --- Import Methods ---

    doImportParse() {
      this.loading = true;

      if (this.importFile) {
        const reader = new FileReader();
        reader.onload = () => {
          this.parseImportJson(reader.result);
        };
        reader.onerror = () => {
          this.handleImportError(reader.error);
        };
        reader.readAsText(this.importFile);
      } else if (this.importJsonText) {
        this.parseImportJson(this.importJsonText);
      }
    },

    parseImportJson(text) {
      try {
        const data = JSON.parse(text);
        this.importParsedData = data;
        this.step = 2;
      } catch (err) {
        this.errorMessage = 'Invalid JSON: ' + err.message;
        this.errorDialog = true;
      } finally {
        this.loading = false;
      }
    },

    async doImportExecute() {
      this.loading = true;
      try {
        const contextFilter = this.importContextCodes.map(c => c.context_code);
        const result = await admin.importEventData(this.importParsedData, {
          dry_run: this.importDryRun,
          mode: this.importMode,
          context_codes: contextFilter,
        });
        this.importResult = result;
        this.step = 5;
      } catch (err) {
        this.errorMessage = err.response?.data?.error || err.response?.data || 'Import failed';
        this.errorDialog = true;
      } finally {
        this.loading = false;
      }
    },

    handleImportError(err) {
      this.errorMessage = 'Failed to read file: ' + err.message;
      this.errorDialog = true;
      this.loading = false;
    },

    resetImport() {
      this.importFile = null;
      this.importJsonText = '';
      this.importParsedData = null;
      this.importMode = 'create';
      this.importDryRun = true;
      this.importContextCodes = [];
      this.importResult = null;
      this.step = 1;
    },

    // --- Utility ---

    formatBytes(bytes) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },
  },

  filters: {
    number(val) {
      return typeof val === 'number' ? val.toLocaleString() : val;
    },
    currency(val) {
      return '$' + (typeof val === 'number' ? val.toFixed(2) : val);
    },
  },
};
</script>
