<template>
    <v-card>
        <v-toolbar flat dense color="grey-lighten-4">
            <v-toolbar-title>Badge Statistics</v-toolbar-title>
            <v-spacer></v-spacer>

            <!-- Contexts selector -->
            <v-autocomplete
                v-model="selectedContexts"
                :items="contextItems"
                item-value="context_code"
                item-text="name"
                chips
                chip
                multiple
                clearable
                dense
                outlined
                small-chips
                label="Contexts"
                placeholder="All contexts"
                hide-details
                class="ml-2"
                style="max-width: 240px;"
            />

            <!-- Badge type selector -->
            <v-select
                v-model="selectedBadgeTypes"
                :items="badgeSelectItems"
                item-text="name"
                item-value="id"
                chips
                chip
                multiple
                clearable
                dense
                outlined
                small-chips
                label="Badge types"
                placeholder="All badge types"
                hide-details
                class="ml-2"
                style="max-width: 280px;"
            />

            <!-- Column selector -->
            <v-select
                v-model="selectedColumn"
                :items="columnOptions"
                item-value="value"
                item-text="label"
                dense
                outlined
                label="Column"
                hide-details
                class="ml-2"
                style="max-width: 140px;"
            />

            <!-- Period selector -->
            <v-select
                v-model="period"
                :items="periodOptions"
                item-value="value"
                item-text="label"
                dense
                outlined
                label="Period"
                hide-details
                class="ml-2"
                style="max-width: 130px;"
            />

            <!-- Date range -->
            <v-menu
                v-model="startMenu"
                :close-on-content-click="false"
                transition="scale-transition"
                offset-y
                min-width="auto"
            >
                <template v-slot:activator="{ on, attrs }">
                    <v-text-field
                        v-model="rangeStart"
                        :label="rangeStart ? formatDate(rangeStart) : 'Start date'"
                        prepend-icon="mdi-calendar"
                        dense
                        outlined
                        clearable
                        hide-details
                        v-bind="attrs"
                        v-on="on"
                        class="ml-2"
                        style="max-width: 160px;"
                    />
                </template>
                <v-date-picker v-model="rangeStart" no-title @input="startMenu = false" />
            </v-menu>

            <v-menu
                v-model="endMenu"
                :close-on-content-click="false"
                transition="scale-transition"
                offset-y
                min-width="auto"
            >
                <template v-slot:activator="{ on, attrs }">
                    <v-text-field
                        v-model="rangeEnd"
                        :label="rangeEnd ? formatDate(rangeEnd) : 'End date'"
                        prepend-icon="mdi-calendar"
                        dense
                        outlined
                        clearable
                        hide-details
                        v-bind="attrs"
                        v-on="on"
                        class="ml-2"
                        style="max-width: 160px;"
                    />
                </template>
                <v-date-picker v-model="rangeEnd" no-title @input="endMenu = false" />
            </v-menu>

            <!-- Search button -->
            <v-btn color="primary" dark @click="doSearch" class="ml-2" small :disabled="loading">
                <v-icon left small>mdi-magnify</v-icon>
                Search
            </v-btn>
        </v-toolbar>

        <!-- Chart showing badge statistics -->
        <v-card-text class="pa-0" v-if="rawResults && Object.keys(rawResults).length > 0">
            <badgeChart :key="'chart-' + selectedColumn + '-' + (selectedBadgeTypes || []).join(',')" :chart-data="chartData" :loading="loading" :column="selectedColumn" />
        </v-card-text>

        <v-tree-data-table
            :headers="headers"
            :items="treeData"
            :loading="loading"
            itemIDname="id"
            itemParentIDname="parent_id"
            class="elevation-1 badge-stats-tree"
            dense
        >
            <template v-slot:[`item.context_name`]="{ item }">
                <span class="px-1 font-weight-bold" v-if="item.context_name">{{ item.context_name }}</span>
                <span class="px-1 font-weight-medium" v-else-if="item.badge_name">{{ item.badge_name }}</span>
                <span v-else-if="item.period">{{ item.period }}</span>
            </template>

            <template v-slot:[`item.paid_count`]="{ item }">
                <span class="text-center">{{ item.paid_count }}<template v-if="item.leaf"><span class="grey--text text--accent-3">({{ item.paid_total }})</span></template></span>
            </template>

            <template v-slot:[`item.printed_count`]="{ item }">
                <span class="text-center">{{ item.printed_count }}<template v-if="item.leaf"><span class="grey--text text--accent-3">({{ item.printed_total }})</span></template></span>
            </template>

            <template v-slot:[`item.checked_in_count`]="{ item }">
                <span class="text-center">{{ item.checked_in_count }}<template v-if="item.leaf"><span class="grey--text text--accent-3">({{ item.checked_in_total }})</span></template></span>
            </template>

            <template v-slot:[`item.total_count`]="{ item }">
                <span class="text-center" :class="{'font-weight-bold': item.context_name, 'font-weight-medium': !item.context_name && item.badge_name}">{{ item.total_count }}<template v-if="item.leaf"><span class="grey--text text--accent-3">({{ item.total_total }})</span></template></span>
            </template>

            <template v-slot:no-data>
                <div class="text-center grey--text mt-4">No data available</div>
            </template>

            <template v-slot:loading>
                <v-row align="center" justify="center" style="width: 100%;">
                    <v-progress-circular indeterminate size="48"></v-progress-circular>
                </v-row>
            </template>
        </v-tree-data-table>

        <v-dialog v-model="loading" width="200" persistent>
            <v-card-text class="text-center overflow-hidden">
                <v-progress-circular :size="150" indeterminate></v-progress-circular>
            </v-card-text>
        </v-dialog>
    </v-card>
</template>

<script>
import admin from '../../api/admin';
import vTreeDataTable from '../../components/v-tree-data-table.vue';
import badgeChart from '../../components/badgeChart.vue';
import { mapActions, mapState } from 'vuex';

export default {
    components: { vTreeDataTable, badgeChart },
    props: ['subTabIx'],
    data: () => ({
        loading: false,
        rawResults: {},

        headers: [
            { text: 'Context / Badge Type / Period', value: 'context_name', width: '200', align: 'left' },
            { text: 'Paid', value: 'paid_count', width: '75', align: 'center' },
            { text: 'Printed', value: 'printed_count', width: '75', align: 'center' },
            { text: 'Checked In', value: 'checked_in_count', width: '80', align: 'center' },
            { text: 'Total', value: 'total_count', width: '70', align: 'center' },
        ],

        // Filters
        selectedContexts: [],
        period: '%Y-%m-%d',
        rangeStart: null,
        rangeEnd: null,
        startMenu: false,
        endMenu: false,

        // Badge type selection
        selectedBadgeTypes: [],

        periodOptions: [
            { label: 'Hourly', value: '%Y-%m-%d %H' },
            { label: 'Daily', value: '%Y-%m-%d' },
            { label: 'Weekly', value: '%Y-%u' },
            { label: 'Monthly', value: '%Y-%m' },
            { label: 'Yearly', value: '%Y' },
        ],

        columnOptions: [
            { label: 'Paid', value: 'paid_count' },
            { label: 'Printed', value: 'printed_count' },
            { label: 'Checked In', value: 'checked_in_count' },
            { label: 'Total', value: 'total_count' },
        ],
        selectedColumn: 'total_count',
    }),
    computed: {
        ...mapState('products', ['badgecontexts', 'badges']),

        // Items for the context autocomplete
        contextItems() {
            return this.badgecontexts || [];
        },

        // Badge type items for the multi-select, grouped by context.
        // Uses a composite key 'contextCode:badgeTypeId' so badge types
        // with the same ID in different contexts are not merged.
        badgeSelectItems() {
            const map = {};
            for (const ctx in this.rawResults) {
                if (!this.rawResults.hasOwnProperty(ctx)) continue;
                const ctxRaw = this.rawResults[ctx];
                if (!ctxRaw || typeof ctxRaw !== 'object') continue;
                const badgeList = (this.badges && this.badges[ctx]) || [];
                for (const btId in ctxRaw) {
                    if (!ctxRaw.hasOwnProperty(btId)) continue;
                    const badge = badgeList.find(b => b.id == btId);
                    const name = badge ? badge.name : ('Badge ' + btId);
                    const ctxName = (this.badgeContextMap || {})[ctx] || ctx;
                    const key = ctx + ':' + btId;
                    if (!map[key]) {
                        map[key] = {
                            id: key,
                            name: ctxName + ' — ' + name,
                            contextCode: ctx,
                            badgeTypeId: btId,
                        };
                    }
                }
            }
            return Object.values(map);
        },

        badgeContextMap() {
            var map = {};
            var contexts = this.badgecontexts || [];
            for (var i = 0; i < contexts.length; i++) {
                map[contexts[i].context_code] = contexts[i].name || contexts[i].context_code;
            }
            return map;
        },

        // Build a flat array of tree nodes from nested rawResults.
        // rawResults structure: { [contextCode]: { [badgeTypeId]: [mergedRow, ...] } }
        // Each mergedRow has: period, paid_count, paid_total, printed_count,
        //   printed_total, checked_in_count, checked_in_total
        //
        // Tree levels:
        //   Level 1: Context folders (leaf: false)
        //   Level 2: Badge Type folders (leaf: false)
        //   Level 3: Period leaves (leaf: true, only rows with count > 0)
        //
        // Display logic for the tree column:
        //   - Context nodes show context_name (translated via badgeContextMap, fallback: "Context X")
        //   - Badge type nodes show badge_name (looked up from badges[contextCode])
        //   - Period leaves show period key (e.g. "24-01")
        //
        // Vue.set on state.badges[state.badgecontextselected.context_code]
        // replaces the array reference, so mapState computed properties
        // correctly notify Vue of changes.
        //
        // The hash comparison in v-tree-data-table prevents infinite loops
        // when this computed re-evaluates without actual data changes.
        treeData() {
            if (!this.rawResults || typeof this.rawResults !== 'object' || Object.keys(this.rawResults).length === 0) {
                return [];
            }

            // Use mapState mapped properties (badgecontexts, badges)
            var badgeContexts = this.badgecontexts || [];
            var badges = this.badges || {};

            // Build context name map inline
            var badgeContextMap = {};
            for (var ci = 0; ci < badgeContexts.length; ci++) {
                badgeContextMap[badgeContexts[ci].context_code] =
                    badgeContexts[ci].name || badgeContexts[ci].context_code;
            }

            var result = [];

            for (var contextCode in this.rawResults) {
                if (!this.rawResults.hasOwnProperty(contextCode)) continue;

                var contextRaw = this.rawResults[contextCode];
                // Skip if context has no data or data isn't an object
                if (!contextRaw || typeof contextRaw !== 'object') continue;

                var contextName = badgeContextMap[contextCode] || ('Context ' + contextCode);
                var contextBadgeList = (badges && badges[contextCode]) || [];

                // Compute context-level totals by summing all periods across all badge types
                // Folder nodes show cumulative totals for both count and total fields.
                var ctxPaidT = 0, ctxPrintedT = 0, ctxCheckedInT = 0, ctxTotalT = 0;
                var badgeTypeIds = Object.keys(contextRaw);

                for (var bi = 0; bi < badgeTypeIds.length; bi++) {
                    var badgeTypeId = badgeTypeIds[bi];
                    var periodRows = contextRaw[badgeTypeId];
                    if (!Array.isArray(periodRows)) continue;

                    // Find last period's cumulative total for this badge type
                    var lastRow = null;
                    for (var pi = 0; pi < periodRows.length; pi++) {
                        var row = periodRows[pi];
                        if (row && (row.paid_count || row.printed_count || row.checked_in_count || row.total_count)) {
                            lastRow = row;
                        }
                    }

                    if (lastRow) {
                        ctxPaidT += lastRow.paid_total || 0;
                        ctxPrintedT += lastRow.printed_total || 0;
                        ctxCheckedInT += lastRow.checked_in_total || 0;
                        ctxTotalT += lastRow.total_total || 0;
                    }
                }

                // Push context folder FIRST (before its children)
                // Count equals total for folder nodes
                result.push({
                    id: 'cx_' + contextCode,
                    parent_id: null,
                    context_name: contextName,
                    paid_count: ctxPaidT,
                    paid_total: ctxPaidT,
                    printed_count: ctxPrintedT,
                    printed_total: ctxPrintedT,
                    checked_in_count: ctxCheckedInT,
                    checked_in_total: ctxCheckedInT,
                    total_count: ctxTotalT,
                    total_total: ctxTotalT,
                    leaf: false,
                });

                // Push badge type folders and their period leaves
                for (var bi = 0; bi < badgeTypeIds.length; bi++) {
                    var badgeTypeId = badgeTypeIds[bi];
                    var periodRows = contextRaw[badgeTypeId];
                    if (!Array.isArray(periodRows) || periodRows.length === 0) continue;

                    // Build period leaf nodes
                    var badgePeriodLeaves = [];
                    var seenPeriods = {}; // deduplicate by period string
                    for (var pi = 0; pi < periodRows.length; pi++) {
                        var row = periodRows[pi];
                        if (!row) continue;

                        // Deduplicate: skip duplicate (context, badgeType, period) tuples
                        var periodKey = contextCode + '|' + badgeTypeId + '|' + row.period;
                        if (seenPeriods[periodKey]) continue;
                        seenPeriods[periodKey] = true;

                        var paidC = row.paid_count || 0;
                        var printedC = row.printed_count || 0;
                        var checkedInC = row.checked_in_count || 0;

                        if (!paidC && !printedC && !checkedInC && !row.total_count) continue; // skip all-zero rows

                        badgePeriodLeaves.push({
                            id: 'cx_' + contextCode + '|bt_' + badgeTypeId + '|p_' + row.period,
                            parent_id: 'cx_' + contextCode + '|bt_' + badgeTypeId,
                            context_name: '',
                            badge_name: '',
                            period: row.period,
                            paid_count: paidC,
                            paid_total: row.paid_total || 0,
                            printed_count: printedC,
                            printed_total: row.printed_total || 0,
                            checked_in_count: checkedInC,
                            checked_in_total: row.checked_in_total || 0,
                            total_count: row.total_count || 0,
                            total_total: row.total_total || 0,
                            leaf: true,
                        });
                    }

                    // Sort periods ascending
                    badgePeriodLeaves.sort(function(a, b) {
                        return a.period < b.period ? -1 : 1;
                    });

                    // If no active periods, skip this badge type
                    if (badgePeriodLeaves.length === 0) continue;

                    // Cumulative totals come from the last period — used for both count and total
                    var lastLeaf = badgePeriodLeaves[badgePeriodLeaves.length - 1];

                    // Lookup badge name from Vuex
                    var badgeName = '';
                    if (contextBadgeList && contextBadgeList.length > 0) {
                        var badge = contextBadgeList.find(function(b) {
                            return b.id == badgeTypeId;
                        });
                        if (badge) badgeName = badge.name || '';
                    }

                    // Badge type folder node: count equals total (cumulative)
                    result.push({
                        id: 'cx_' + contextCode + '|bt_' + badgeTypeId,
                        parent_id: 'cx_' + contextCode,
                        context_name: '',
                        badge_name: badgeName,
                        paid_count: lastLeaf.paid_total || 0,
                        paid_total: lastLeaf.paid_total || 0,
                        printed_count: lastLeaf.printed_total || 0,
                        printed_total: lastLeaf.printed_total || 0,
                        checked_in_count: lastLeaf.checked_in_total || 0,
                        checked_in_total: lastLeaf.checked_in_total || 0,
                        total_count: lastLeaf.total_total || 0,
                        total_total: lastLeaf.total_total || 0,
                        leaf: false,
                    });

                    // Push period leaf nodes into the flat result
                    for (var li = 0; li < badgePeriodLeaves.length; li++) {
                        result.push(badgePeriodLeaves[li]);
                    }
                }
            }

            return result;
        },

        /**
         * Build chart data from rawResults.
         * Format: one bar (column count) + one line (column cumulative total)
         * per badge type. X-axis is periods (timeline).
         */
        chartData() {
            // Access these via `this.` so Vue 2 tracks them as reactive
            // dependencies. Local copies are for use inside closures.
            var selectedColumn = this.selectedColumn;
            var selectedBadgeTypes = this.selectedBadgeTypes;
            var selectedContexts = this.selectedContexts;
            var results = this.rawResults;
            if (!results || typeof results !== 'object' || Object.keys(results).length === 0) {
                return { labels: [], datasets: [] };
            }

            // Apply context filter if needed
            if (Array.isArray(selectedContexts) && selectedContexts.length > 0) {
                var filtered = {};
                for (var i = 0; i < selectedContexts.length; i++) {
                    if (results[selectedContexts[i]]) {
                        filtered[selectedContexts[i]] = results[selectedContexts[i]];
                    }
                }
                results = filtered;
            }
            if (!results || typeof results !== 'object' || Object.keys(results).length === 0) {
                return { labels: [], datasets: [] };
            }

            var allBadgeTypes = {};
            var periodSet = {};

            for (var contextCode in results) {
                if (!results.hasOwnProperty(contextCode)) continue;
                var ctxRaw = results[contextCode];
                if (!ctxRaw || typeof ctxRaw !== 'object') continue;

                for (var badgeTypeId in ctxRaw) {
                    if (!ctxRaw.hasOwnProperty(badgeTypeId)) continue;
                    var rows = ctxRaw[badgeTypeId];
                    if (!Array.isArray(rows) || rows.length === 0) continue;

                    var key = contextCode + '|' + badgeTypeId;
                    allBadgeTypes[key] = {
                        contextCode: contextCode,
                        badgeTypeId: badgeTypeId,
                        name: this.badgeNameFor(contextCode, badgeTypeId),
                    };

                    for (var ri = 0; ri < rows.length; ri++) {
                        if (rows[ri] && rows[ri].period) {
                            periodSet[rows[ri].period] = true;
                        }
                    }
                }
            }

            var periods = Object.keys(periodSet).sort();
            if (periods.length === 0 || Object.keys(allBadgeTypes).length === 0) {
                return { labels: [], datasets: [] };
            }

            // Filter to selected badge types (empty = all)
            // selectedBadgeTypes contains composite keys like "A:1"
            // Build a lookup set for fast matching
            var selectedSet = {};
            if (selectedBadgeTypes && selectedBadgeTypes.length > 0) {
                for (var si = 0; si < selectedBadgeTypes.length; si++) {
                    selectedSet[selectedBadgeTypes[si]] = true;
                }
            }
            var filteredBtKeys = Object.keys(allBadgeTypes).filter(function(k) {
                if (Object.keys(selectedSet).length === 0) return true;
                var compositeKey = allBadgeTypes[k].contextCode + ':' + allBadgeTypes[k].badgeTypeId;
                return selectedSet[compositeKey];
            });

            var datasets = [];
            var colorIdx = 0;
            var COLORS = ['#3f51b5', '#009688', '#ff9800', '#f44336', '#9c27b0',
                          '#2196f3', '#4caf50', '#ff5722', '#795548', '#607d8b'];
            var countSuffix = '_count';
            var totalSuffix = '_total';
            var countField = selectedColumn;
            var totalField = selectedColumn.replace(countSuffix, totalSuffix);
            var colLabel = this.columnLabel(selectedColumn);

            for (var bi = 0; bi < filteredBtKeys.length; bi++) {
                var bt = allBadgeTypes[filteredBtKeys[bi]];
                var ctxRaw = results[bt.contextCode];
                var rows = ctxRaw[bt.badgeTypeId] || [];
                var rowsByPeriod = {};
                for (var ri = 0; ri < rows.length; ri++) {
                    if (rows[ri] && rows[ri].period) {
                        rowsByPeriod[rows[ri].period] = rows[ri];
                    }
                }

                var color = COLORS[colorIdx % COLORS.length];
                colorIdx++;

                // Bar data: selected column's period count
                var barData = periods.map(function(p) {
                    var row = rowsByPeriod[p];
                    return (row && row[countField]) || 0;
                });

                // Line data: running cumulative of the selected column's totals
                var runningTotal = 0;
                var lineData = periods.map(function(p) {
                    var row = rowsByPeriod[p];
                    if (row && row[totalField]) {
                        runningTotal = row[totalField];
                    }
                    return runningTotal;
                });

                datasets.push({
                    label: bt.name + ' (' + colLabel + ')',
                    type: 'bar',
                    data: barData,
                    backgroundColor: color,
                    yAxisID: 'y',
                    order: 2,
                    barPercentage: 0.85,
                    categoryPercentage: 0.8,
                    stack: 'count',
                });
                datasets.push({
                    label: bt.name + ' (Running total)',
                    type: 'line',
                    data: lineData,
                    borderColor: color,
                    pointBackgroundColor: color,
                    pointRadius: 3,
                    fill: false,
                    yAxisID: 'yCumulative',
                    order: 1,
                });
            }

            return { labels: periods, datasets: datasets };
        },
    },
    methods: {
        ...mapActions('products', ['getBadgeContexts', 'getContextBadges']),

        /**
         * Format badge name from Vuex badges store.
         */
        badgeNameFor(contextCode, badgeTypeId) {
            var list = (this.badges && this.badges[contextCode]) || [];
            var badge = list.find(function(b) { return b.id == badgeTypeId; });
            return badge ? badge.name : ('Badge ' + badgeTypeId);
        },

        columnLabel(metric) {
            var labels = {
                paid_count: 'Paid',
                printed_count: 'Printed',
                checked_in_count: 'Checked In',
                total_count: 'Total',
            };
            return labels[metric] || metric;
        },

        formatDate(date) {
            if (!date) return '';
            // Format as YYYY-MM-DD for display
            const d = new Date(date);
            if (isNaN(d)) return date;
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },

        async doLoad() {
            // Load badge contexts and badges for all contexts so names display correctly
            try {
                await this.getBadgeContexts();
                // Load badges for every available context
                var promises = [];
                for (var i = 0; i < this.badgecontexts.length; i++) {
                    promises.push(this.getContextBadges(this.badgecontexts[i].context_code));
                }
                await Promise.all(promises);
            } catch (err) {
                console.error('Badge context load failed', err);
            }
        },

        doSearch() {
            this.loading = true;
            const params = {};

            // Period format
            if (this.period) {
                params.period = this.period;
            }

            // Date range
            if (this.rangeStart) {
                const d = new Date(this.rangeStart);
                if (!isNaN(d)) {
                    params.range_start = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                }
            }
            if (this.rangeEnd) {
                const d = new Date(this.rangeEnd);
                if (!isNaN(d)) {
                    params.range_end = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                }
            }

            // Contexts — send as cx_<code> params
            if (Array.isArray(this.selectedContexts) && this.selectedContexts.length > 0) {
                for (const ctx of this.selectedContexts) {
                    params['cx_' + ctx] = '';
                }
            } else {
                // Empty = all contexts (no cx_ params needed — backend returns all)
            }

            admin.genericGetList('Stats/Badge', params, (results, total) => {
                this.rawResults = results || {};
                this.totalResults = total || 0;
                this.loading = false;
            }, (err) => {
                console.error('Badge stats fetch failed', err);
                this.loading = false;
            });
        },
    },
    watch: {
        $route() {
            this.doSearch();
        },
    },
    created() {
        this.doLoad().then(() => {
            this.doSearch();
        });
    },
};
</script>

<style scoped>
.badge-stats-tree .v-tree-data-table .expandable-node {
    padding: 0;
}
</style>
