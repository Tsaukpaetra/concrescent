<template >
    <v-switch 
    v-model="currentValue"
            :color="color"
            :loading="loading"
            :true-value="1"
            :false-value="0"
            @change="onchange"
            ></v-switch>
</template>

<script>
export default {
    components: {},
    props: ['value','color'],
    data() {
        return {
            skipEmitOnce: false,
            currentValue: this.value,
            loading:false,
        };
    },
    computed: {

    },
    methods: {

        onchange: function(newValue){
            this.loading = true;
            this.$emit('input',newValue);
        },
    },
    watch: {
        value: {
            handler: function(newValue) {
                this.currentValue = newValue;
                this.loading= false;
            },
        }
    },
};
</script>
