<template>
  <v-data-table
    :headers="computedHeaders"
    :items="localItems"
    :items-per-page="-1"
    :dense="dense"
    item-key="_key"
    class="elevation-1"
    hide-default-footer
    show-expand
  >
    <template v-slot:top>
      <v-toolbar flat color="grey lighten-4" dense>
        <v-toolbar-title class="body-2 font-weight-medium">{{ title }}</v-toolbar-title>
        <v-spacer></v-spacer>
        <v-chip small>{{ items.length }} record{{ items.length !== 1 ? 's' : '' }}</v-chip>
      </v-toolbar>
    </template>

    <!-- Expanded row child content slot.
         Receives { headers, item } from Vuetify's expanded-item scope.
         Use this to render sub-items (e.g. format maps under a badge format). -->
    <template v-slot:expanded-item="{ headers, item }">
      <slot name="child" :item="item" :headers="headers">
        <tr @click.stop>
          <td :colspan="colspan">
            <v-alert text dense type="info" class="ml-4 mt-1">
              No child data available.
            </v-alert>
          </td>
        </tr>
      </slot>
    </template>
    <!--courtesy https://gist.github.com/loilo/73c55ed04917ecf5d682ec70a2a1b8e2 -->
    <slot v-for="(_, name) in $slots"
          :name="name"
          :slot="name" />
    <template v-for="(_, name) in $scopedSlots"
              :slot="name"
              slot-scope="slotData">
        <slot :name="name"
              v-bind="slotData" />
    </template>
  </v-data-table>
</template>

<script>
/**
 * A local-data v-data-table wrapper for showing flat entity lists
 * in the event import/export preview. Supports expandable rows
 * for showing child data alongside parent records.
 *
 * Usage:
 *   <LocalTable
 *     title="Badge Formats"
 *     :items="localDataArray"
 *     dense
 *   >
 *     <template v-slot:child="{ item }">
 *       <v-data-table :items="item.formats" :headers="formatHeaders" />
 *     </template>
 *   </LocalTable>
 */
export default {
  name: 'EventLocalTable',
  props: {
    title: {
      type: String,
      default: 'Data',
    },
    items: {
      type: Array,
      default: () => [],
    },
    dense: {
      type: Boolean,
      default: false,
    },
    /** Custom Vuetify-style headers: [{ text, value }, ...]
     * When provided, used instead of auto-detected headers. */
    headers: {
      type: Array,
      default: null,
    },
  },
  data() {
    return {};
  },
  computed: {
    localItems() {
      // Inject _key so v-data-table can track expand state.
      // Vuetify matches expanded items by itemKey value (not reference),
      // so spread copies are safe. Items already having _key
      // (injected by caller) are passed through unchanged.
      const items = this.items || [];
      if (items.length === 0) return [];
      if (items[0] && '_key' in items[0]) return items;
      return items.map((item, i) => ({ ...item, _key: `${this.title}_${i}` }));
    },
    computedHeaders() {
      if (this.items.length === 0) {
        return [];
      }
      if (this.headers !== null) {
        return this.headers;
      }
      return Object.keys(this.items[0]).map(k => ({
        text: this.formatHeader(k),
        value: k,
      }));
    },
    colspan() {
      // headers + expand column
      return this.computedHeaders.length + 1;
    },
  },
  methods: {
    formatHeader(key) {
      return key
        .replace(/_/g, ' ')
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, s => s.toUpperCase())
        .trim();
    },
  },
};
</script>
