<template >
    <v-switch 
    v-model="currentValue"
            :color="color"
            :loading="loading"
            :true-value="1"
            :false-value="0"
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
            loading:null,
        };
    },
    computed: {

    },
    methods: {

    },
    watch: {
        currentValue: function(newValue){
            if (this.skipEmitOnce == true) {
                this.skipEmitOnce = false;
                return;
            }
            //this.loading = true;
            this.$emit('input',newValue);
        },
        value: {
            handler: function(newValue) {
                //Splat the input into the form
                this.skipEmitOnce = true;
                // console.log('new value received', newValue)
                // this.payment_id = newValue.payment_id;
                this.currentValue = newValue;
                //this.loading= null;
            },
        }
    },
};
</script>
