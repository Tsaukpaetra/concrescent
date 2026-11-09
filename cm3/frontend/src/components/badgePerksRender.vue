<template>
<div ref="wrapper" class="badge-perks-render">
    <p>
        <b v-if="description != null && description.length > 0">Description:</b>
        <v-md-preview v-if="description != null && description.length > 0"
                      :text="description" />
    </p>
    <h4 v-if="rewardlist != null && rewardlist.length > 0">Rewards:</h4>
    <v-md-preview v-if="rewardlist != null && rewardlist.length > 0"
                  :text="rewardlist" />
</div>
</template>

<script>
export default {
    components: {},
    props: ['description', 'rewardlist'],
    data: () => ({
        reflowScheduled: false,
    }),
    watch: {
        description() { this._scheduleReflow(); },
        rewardlist()  { this._scheduleReflow(); },
    },
    mounted() {
        this._scheduleReflow();
    },
    methods: {
        _scheduleReflow() {
            if (this.reflowScheduled) return;
            this.reflowScheduled = true;
            // Wait for the next animation frame so the browser has painted
            // v-md-preview's content, then force a layout recalculation
            // that the parent stepper will pick up.
            requestAnimationFrame(() => {
                void this.$el.offsetHeight;
                this.$emit('perk-height-ready');
                this.reflowScheduled = false;
            });
        },
    },
};
</script>
