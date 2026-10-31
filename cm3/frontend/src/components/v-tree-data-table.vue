/**
* This component handles rendering a tree view in a data grid
https://github.com/portablemind/vuetify_tree_data_table/blob/master/src/v-tree-data-table.vue
*/

<template>
<div class="v-tree-data-table">
    <v-data-table ref="dataTable"
                  :show-select="showSelect"
                  :headers="computedHeaders"
                  :items="flattenedNodes"
                  show-expand
                  :loading="loading"
                  :footer-props="pageFooterProps">
        <slot v-for="(_, name) in $slots"
              :name="name"
              :slot="name" />
        <template slot="no-data">
            <slot name="no-data">
                <div class="text-center grey--text mt-4">No matching records found?</div>
            </slot>
        </template>

        <template slot="headers"
                  slot-scope="props">
            <slot name="headers"
                  :props="props">
                <tr>
                    <th v-if="showSelect"
                        width="50px">
                        <v-checkbox :input-value="props.all"
                                    :indeterminate="props.indeterminate"
                                    primary
                                    hide-details
                                    @click.native="toggleSelectAll"></v-checkbox>
                    </th>
                    <template v-for="header in props.headers">
                        <th v-if="header.sortable"
                            :key="header.text"
                            :class="['column sortable', options.descending ? 'desc' : 'asc', header.value === options.sortBy ? 'active' : '', `text-xs-${header.align || 'left'}`]"
                            @click="changeSort(header.value)">
                            <v-icon small>mdi-arrow-up</v-icon>
                            {{ header.text }}
                        </th>
                        <th v-else
                            :key="header.text"
                            :class="`text-xs-${header.align || 'left'}`">
                            {{ header.text }}
                        </th>
                    </template>
                </tr>
            </slot>
        </template>

        <template slot="item"
                  slot-scope="{ item: propsItem, headers: propsHeaders }">
            <tr :id="propsItem.id"
                :key="propsItem[itemIDname]"
                :class="[propsItem.leaf ? 'leaf' : 'folder', propsItem._hidden ? 'hidden' : '']"
                :style="propsItem._hidden ? 'display:none;' : null">
                <td class="expandable-node"
                    style="width: 0.1%"
                    :class="propsItem.leaf ? 'text-xs-left' : 'text-xs-center'"
                    :style="nodeStyle(propsItem)">
                    <v-icon v-if="propsItem.leaf" class="grey--text text--default">mdi-arrow-right</v-icon>
                    <v-icon v-else @click.stop="toggleNode(propsItem)" class="expand-icon">mdi-{{expandableIcon(propsItem)}}</v-icon>
                </td>
                <template v-for="header in propsHeaders">
                    <td :key="header.value" v-if="header.value != 'data-table-expand'"
                        :class="`text-xs-${header.align || 'left'}`">
                        <slot :name="`item.${header.value}`"
                              :item="propsItem"
                              :value="propsItem[header.value]"></slot>
                    </td>
                </template>
            </tr>
        </template>
    </v-data-table>
</div>
</template>

<script>
//import nodeHelper from "./nodeHelper.js";

export default {
    props: {
        headers: {
            type: Array,
            default: () => {
                []
            },
            required: true
        },
        items: {
            type: Array,
            default: () => {
                []
            },
            required: true
        },
        itemIDname: {
            type: String,
            default: () => "id",
            required: false,
        },
        itemParentIDname: {
            type: String,
            required: false,
            default: () => "parent_id"
        },
        options: {
            page: 'number',
            itemsPerPage: 'number',
            sortBy: 'string[]',
            sortDesc: 'boolean[]',
            groupBy: 'string[]',
            groupDesc: 'boolean[]',
            multiSort: 'boolean',
            mustSort: 'boolean',
            default: () => {
                return {
                    "page": 1,
                    "itemsPerPage": 10,
                    "sortBy": [],
                    "sortDesc": [false],
                    "groupBy": [],
                    "groupDesc": [],
                    "mustSort": false,
                    "multiSort": true
                }
            },

        },
        serverItemsLength: {
            type: Number,
            required: false
        },
        loading: {
            type: Boolean,
            required: true
        },
        footerProps: {
            type: Object,
            default: () => {
                {}
            }
        },
        showSelect: {
            type: [Boolean, String],
            default: undefined
        },
        value: {
            type: Array,
            default: undefined
        },
        validDrop: {
            type: Function,
            default: undefined
        }
    },

    data: function() {
        return {
            rawItems: this.items,
            flattenedNodes: [],
            expandedItems: {},
        };
    },

    watch: {
        items: {
            handler(newItems) {
                if (!newItems || newItems.length === 0) return;
                // Prevent infinite loop: only re-process if data actually changed.
                // JSON stringify comparison catches structural changes while avoiding
                // reference issues when the parent recreates arrays on every render.
                // Hash ALL data fields (except tree-computed ones) so that when the
                // parent updates display data without changing structure, we still
                // re-run flattenNodes and pick up the new display values.
                var currentHash = JSON.stringify(newItems.map(function(n) {
                    var h = {};
                    h[this.itemIDname] = n[this.itemIDname];
                    h[this.itemParentIDname] = n[this.itemParentIDname];
                    h.leaf = n.leaf;
                    h.depth = n.depth;
                    // Include all data fields except tree-computed properties
                    // so display changes (context names, badge names, etc.)
                    // are detected and trigger reprocessing.
                    for (var k in n) {
                        if (n.hasOwnProperty(k) && k !== 'parentNode' && k !== '_children') {
                            h[k] = n[k];
                        }
                    }
                    return h;
                }.bind(this)));
                if (currentHash === this._prevItemsHash) return;
                this._prevItemsHash = currentHash;
                this.flattenNodes(newItems);
            },
            deep: true,
        },
    },

    mounted: function() {
        if (this.rawItems && this.rawItems.length > 0) {
            this.flattenNodes(this.rawItems);
        }
    },

    computed: {
        computedHeaders() {
            return this.headers.slice();
        },

        /**
         * Footer props that hide pagination but keep the footer visible.
         */
        pageFooterProps() {
            return {
                'items-per-page-options': [-1],
                'items-per-page': -1,
                'show-first-last-links': false,
                'first-text': '',
                'prev-text': '',
                'next-text': '',
                'last-text': '',
            };
        },
    },

    methods: {
        /**
         * Check if a node is expanded (uses persistent Map)
         * @param {Object} node The Node
         */
        isExpanded(node) {
            if (!node || !node[this.itemIDname]) return false;
            return this.expandedItems[node[this.itemIDname]] === true;
        },

        /**
         * Get the icon for the node
         * @param {Object} node The Node
         */
        expandableIcon(node) {
            if (this.isExpanded(node)) {
                return "folder-open";
            } else {
                return "folder";
            }
        },

        /**
         * Get the style padding for the node
         * @param {Object} node The Node
         */
        nodeStyle(node) {
            return {
                paddingLeft: 8 + 12 * node.depth + "px"
            };
        },

        /**
         * Flatten nested nodes.
         * One-time setup: depth, parentNode, leaf.
         * Visibility (_hidden) is computed reactively in renderNodes.
         */
        flattenNodes(nodes) {
            var itemsToProcess = [];
            const idn = this.itemIDname;
            const idnp = this.itemParentIDname;

            // Build a lookup map by id
            var nodeById = {};
            nodes.forEach(function(item) {
                nodeById[item[idn]] = item;
            });

            //Find the top-level items and assign depth 1
            var depthMap = new Map(nodes.filter(function(item) {
                var parentID = item[idnp] || null;
                var isTop = parentID == null || parentID == '' || parentID == undefined;
                if (!isTop)
                    itemsToProcess.push(item);
                return isTop;
            }).map(function(item) {
                return [item[idn], 1];
            }));

            //Find depths for the rest
            while (itemsToProcess.length > 0) {
                const priorCount = itemsToProcess.length;
                itemsToProcess = itemsToProcess.filter((item) => {
                    parent = depthMap.get(item[idnp]);
                    if (parent == undefined)
                        return true;
                    depthMap.set(item[idn], parent + 1);
                    return false;
                });
                //if we completely failed to make any progress, just shove the rest as depth=0
                if (itemsToProcess.length == priorCount) {
                    itemsToProcess.forEach((item) => {
                        depthMap.set(item[idn], 0);
                    });
                    itemsToProcess = [];
                }
            }

            // Build parent-child chains
            nodes.forEach(function(item) {
                var parentID = item[idnp];
                if (parentID != null && parentID != '' && nodeById[parentID]) {
                    if (!nodeById[parentID].children) {
                        nodeById[parentID].children = [];
                    }
                    nodeById[parentID].children.push(item);
                }
            });

            //Finally, add the depths, parentNode, and initial _hidden as properties
            var vm = this;
            this.flattenedNodes = nodes.map((item) => {
                var depth = depthMap.get(item[idn]);
                var parentID = item[idnp];
                var parentNode = (parentID != null && parentID != '' && nodeById[parentID])
                    ? nodeById[parentID]
                    : null;
                // Respect explicit leaf property from data, fall back to depth heuristic
                var explicitLeaf = 'leaf' in item ? item.leaf : (depth > 1);
                // Initial _hidden: hidden if parent exists and is not expanded
                var parentNodeExpanded = parentNode ? vm.isExpanded(parentNode) : true;
                var itemHidden = !parentNode || parentNodeExpanded ? null : true;
                return {
                    ...item,
                    depth: depth,
                    parentNode: parentNode,
                    leaf: explicitLeaf,
                    _hidden: itemHidden,
                }
            });

            if (process.env.NODE_ENV === 'development') {
                console.log('[v-tree-data-table] flattenNodes:', this.flattenedNodes.map(function(n) {
                    return { id: n[idn], parent_id: n[idnp], depth: n.depth, leaf: n.leaf, hasParentRef: !!n.parentNode };
                }));
            }
        },

        /**
         * Toggle node from expanded
         * @param {Object} node The Node
         */
        toggleNode(node) {
            var key = node[this.itemIDname];
            var isNowExpanded = !this.isExpanded(node);
            this.$set(this.expandedItems, key, isNowExpanded);

            // Directly update _hidden so template re-renders immediately
            this.updateHiddenState();

            this.$emit("node-toggle", node);
        },

        /**
         * Recalculate _hidden for all nodes.
         */
        updateHiddenState() {
            var vm = this;
            this.flattenedNodes.forEach(function(item) {
                if (!item.parentNode) {
                    item._hidden = null;
                } else {
                    item._hidden = vm.isExpanded(item.parentNode) ? null : true;
                }
            });
        },
    }
};
</script>

<style>
.v-tree-data-table .folder {
    cursor: pointer;
}

.v-tree-data-table .expand-icon {
    cursor: pointer;
}
</style>
