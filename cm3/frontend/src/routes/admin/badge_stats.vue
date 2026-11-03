<template>
    <v-card>
        <v-toolbar flat dense color="grey-lighten-4">
            <v-toolbar-title>Badge Statistics</v-toolbar-title>
        </v-toolbar>

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
                <span class="text-center">{{ item.paid_count }}<span class="grey--text text--accent-3">({{ item.paid_total }})</span></span>
            </template>

            <template v-slot:[`item.printed_count`]="{ item }">
                <span class="text-center">{{ item.printed_count }}<span class="grey--text text--accent-3">({{ item.printed_total }})</span></span>
            </template>

            <template v-slot:[`item.checked_in_count`]="{ item }">
                <span class="text-center">{{ item.checked_in_count }}<span class="grey--text text--accent-3">({{ item.checked_in_total }})</span></span>
            </template>

            <template v-slot:[`item.total_count`]="{ item }">
                <span class="text-center" :class="{'font-weight-bold': item.context_name, 'font-weight-medium': !item.context_name && item.badge_name}">{{ item.total_count }}</span>
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
import { mapActions, mapState } from 'vuex';

export default {
    components: { vTreeDataTable },
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
    }),
    computed: {
        ...mapState('products', ['badgecontexts', 'badges']),

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
                var ctxPaid = 0, ctxPrinted = 0, ctxCheckedIn = 0, ctxTotal = 0;
                var badgeTypeIds = Object.keys(contextRaw);

                for (var bi = 0; bi < badgeTypeIds.length; bi++) {
                    var badgeTypeId = badgeTypeIds[bi];
                    var periodRows = contextRaw[badgeTypeId];
                    if (!Array.isArray(periodRows)) continue;

                    for (var pi = 0; pi < periodRows.length; pi++) {
                        var row = periodRows[pi];
                        if (!row || !row.paid_count && !row.printed_count && !row.checked_in_count) continue;

                        ctxPaid += row.paid_count || 0;
                        ctxPrinted += row.printed_count || 0;
                        ctxCheckedIn += row.checked_in_count || 0;
                        ctxTotal += row.total_count || 0;
                    }
                }

                // Push context folder FIRST (before its children)
                result.push({
                    id: 'cx_' + contextCode,
                    parent_id: null,
                    context_name: contextName,
                    paid_count: ctxPaid,
                    paid_total: ctxPaid,
                    printed_count: ctxPrinted,
                    printed_total: ctxPrinted,
                    checked_in_count: ctxCheckedIn,
                    checked_in_total: ctxCheckedIn,
                    total_count: ctxTotal,
                    total_total: ctxTotal,
                    leaf: false,
                });

                // Push badge type folders and their period leaves
                for (var bi = 0; bi < badgeTypeIds.length; bi++) {
                    var badgeTypeId = badgeTypeIds[bi];
                    var periodRows = contextRaw[badgeTypeId];
                    if (!Array.isArray(periodRows) || periodRows.length === 0) continue;

                    // Sum totals from active periods
                    var bPaid = 0, bPrinted = 0, bCheckedIn = 0, bTotal = 0;
                    var badgePeriodLeaves = [];

                    for (var pi = 0; pi < periodRows.length; pi++) {
                        var row = periodRows[pi];
                        if (!row) continue;

                        var paidC = row.paid_count || 0;
                        var printedC = row.printed_count || 0;
                        var checkedInC = row.checked_in_count || 0;

                        if (!paidC && !printedC && !checkedInC && !row.total_count) continue; // skip all-zero rows

                        bPaid += paidC;
                        bPrinted += printedC;
                        bCheckedIn += checkedInC;
                        bTotal += row.total_count || 0;

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
                            leaf: true,
                        });
                    }

                    // Sort periods ascending
                    badgePeriodLeaves.sort(function(a, b) {
                        return a.period < b.period ? -1 : 1;
                    });

                    // If no active periods, skip this badge type
                    if (badgePeriodLeaves.length === 0) continue;

                    // Lookup badge name from Vuex
                    var badgeName = '';
                    if (contextBadgeList && contextBadgeList.length > 0) {
                        var badge = contextBadgeList.find(function(b) {
                            return b.id == badgeTypeId;
                        });
                        if (badge) badgeName = badge.name || '';
                    }

                    // Badge type folder node
                    var badgeNodeId = 'cx_' + contextCode + '|bt_' + badgeTypeId;
                    result.push({
                        id: badgeNodeId,
                        parent_id: 'cx_' + contextCode,
                        context_name: '',
                        badge_name: badgeName,
                        paid_count: bPaid,
                        paid_total: bPaid,
                        printed_count: bPrinted,
                        printed_total: bPrinted,
                        checked_in_count: bCheckedIn,
                        checked_in_total: bCheckedIn,
                        total_count: bTotal,
                        total_total: bTotal,
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
    },
    methods: {
        ...mapActions('products', ['getBadgeContexts', 'getContextBadges']),

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
            admin.genericGetList('Stats/Badge', {}, (results, total) => {
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
