<template >
    <v-tooltip left>
        <template v-slot:activator="{ on, attrs }">
            <v-icon v-bind="attrs"
                    v-on="on"
                    :color="paymentStatusColor[payment_status]"
                    v-if="paymentStatusIcon[payment_status] != undefined">mdi-{{paymentStatusIcon[payment_status]}}</v-icon>
            <div v-else>{{payment_status}}</div>
        </template>
        <span>{{payment_status}}</span>
    </v-tooltip>
</template>

<script>
export default {
    components: {},
    props: ['value'],
    data() {
        return {
            'payment_status': this.value,
            paymentStatusIcon: {
                'NotReady': 'alert',
                'AwaitingApproval': 'clock-alert',
                'NotStarted': 'progress-question',
                'Incomplete': 'alert',
                'Cancelled': 'close-octagon',
                'Rejected': 'alert',
                'Completed': 'check-circle',
                'Refunded': 'close-octagon',
                'RefundedInPart': 'check-circle',
            },
            paymentStatusColor: {
                'NotReady': 'grey',
                'AwaitingApproval': '',
                'NotStarted': 'amber',
                'Incomplete': 'amber',
                'Cancelled': 'red',
                'Rejected': 'red',
                'Completed': 'green',
                'Refunded': 'red',
                'RefundedInPart': 'lime',
            },

        };
    },
    computed: {

    },
    methods: {

    },
    watch: {
        
        value: {
            handler: function(newValue) {
                //Splat the input into the form
                console.log('new value received', newValue)
                // this.payment_id = newValue.payment_id;
                this.payment_status = newValue;
            },
        }
    },
};
</script>
