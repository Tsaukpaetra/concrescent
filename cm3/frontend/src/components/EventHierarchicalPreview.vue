<template>
  <v-card outlined>
    <v-card-title class="headline grey--text text--darken-2">
      <v-icon left color="primary">mdi-cluster-outline</v-icon>
      Hierarchical Data Preview
      <v-spacer></v-spacer>
      <v-chip small>{{ groups.length }} group{{ groups.length !== 1 ? 's' : '' }}</v-chip>
    </v-card-title>

    <v-expansion-panels
      v-model="expandedGroups"
      accordion
      multiple
      focusable
    >
      <v-expansion-panel
        v-for="(group, gIdx) in groups"
        :key="group.context_code + '_' + gIdx"
        :disable="!hasChildren(group)"
      >
        <v-expansion-panel-header >
          <v-row dense align="center">
            <v-col cols="4">
              <div class="d-flex justify-space-between align-center w-100 body-1 font-weight-medium">
                <span><v-icon>mdi-{{group.context_code == 'S' ? 'account-hard-hat' : group.context_code == 'A' ? 'badge-account-horizontal' : group.menu_icon || 'newspaper'}}</v-icon> {{ group.name }}</span>
                <v-chip label color="primary" dark>{{ group.context_code }}</v-chip>
              </div>
            </v-col>
            <v-col cols="8">
              <v-chip-group wrap small>
                <v-chip v-if="group.badgetypes && group.badgetypes.length"
                  color="purple" dark text-color="white">
                  <v-icon left small>mdi-tag</v-icon>
                  {{ group.badgetypes.length }} badge type{{ group.badgetypes.length !== 1 ? 's' : '' }}
                </v-chip>
                <v-chip v-if="group.addons && group.addons.length"
                  color="teal" dark text-color="white">
                  <v-icon left small>mdi-plus-box</v-icon>
                  {{ group.addons.length }} addon{{ group.addons.length !== 1 ? 's' : '' }}
                </v-chip>
                <v-chip v-if="group.promos && group.promos.length"
                  color="amber" dark text-color="black">
                  <v-icon left small>mdi-percent</v-icon>
                  {{ group.promos.length }} promo{{ group.promos.length !== 1 ? 's' : '' }}
                </v-chip>
                <v-chip v-if="group.questions && group.questions.length"
                  color="deep-purple" dark text-color="white">
                  <v-icon left small>mdi-help-circle</v-icon>
                  {{ group.questions.length }} question{{ group.questions.length !== 1 ? 's' : '' }}
                </v-chip>
                <v-chip v-if="group.templates && group.templates.length"
                  color="indigo" dark text-color="white">
                  <v-icon left small>mdi-email</v-icon>
                  {{ group.templates.length }} template{{ group.templates.length !== 1 ? 's' : '' }}
                </v-chip>
              </v-chip-group>
            </v-col>
          </v-row>
        </v-expansion-panel-header>

        <v-expansion-panel-content>
          <v-row>
            <v-col cols="12" v-if="group.badgetypes && group.badgetypes.length">
              <LocalTable title="Badge Types" :items="group.badgetypes" :dense="true" />
            </v-col>
            <v-col cols="12" v-if="group.addons && group.addons.length">
              <LocalTable title="Addons" :items="group.addons" :dense="true" />
            </v-col>
            <v-col cols="12" v-if="group.promos && group.promos.length">
              <LocalTable title="Promo Codes" :items="group.promos" :dense="true" />
            </v-col>
            <v-col cols="12" v-if="group.questions && group.questions.length">
              <LocalTable title="Form Questions" :items="group.questions" :dense="true" />
            </v-col>
            <v-col cols="12" v-if="group.templates && group.templates.length">
              <LocalTable title="Email Templates" :items="group.templates" :dense="true" />
            </v-col>
          </v-row>
        </v-expansion-panel-content>
      </v-expansion-panel>
    </v-expansion-panels>

    <!-- Flat format maps section (not nested under groups) -->
    <LocalTable
      v-if="formatMaps && formatMaps.length"
      title="Format Maps"
      :items="formatMaps"
      :dense="true"
      class="mt-4"
    >
    </LocalTable>
  </v-card>
</template>

<script>
import EventLocalTable from './EventLocalTable.vue';

/**
 * Hierarchical preview for group-nested data.
 * Renders an expansion-panel tree: each group is a panel with
 * badge types, addons, promos, questions, and templates shown
 * in LocalTable instances inside the panel content.
 */
export default {
  name: 'EventHierarchicalPreview',
  components: { LocalTable: EventLocalTable },
  props: {
    groups: {
      type: Array,
      default: () => [],
    },
    formatMaps: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      expandedGroups: [],
    };
  },
  watch: {
    // Reset expand state when groups data changes (e.g. new preview loaded)
    groups() {
      this.expandedGroups = [];
    },
  },
  methods: {
    hasChildren(group) {
      return !!(
        (group.badgetypes && group.badgetypes.length) ||
        (group.addons && group.addons.length) ||
        (group.promos && group.promos.length) ||
        (group.questions && group.questions.length) ||
        (group.templates && group.templates.length)
      );
    },
  },
};
</script>
