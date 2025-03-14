<template>
<v-container class="pa-6"
             fluid>
    <v-row>
        <v-col v-for="card in cards"
               :key="card.title"
               v-bind="{ [`xs${card.flex}`]: true }"
               v-show="card.show == null || card.show()">
            <v-card :to="card.route">
                <v-icon class="ma-8"
                        size=200>{{card.icon}}</v-icon>
                <v-card-title class="fill-height align-end">{{card.label}}</v-card-title>

                <v-card-actions v-show="card.actiontext != undefined">
                    <v-spacer></v-spacer>
                    <v-badge :value="card.badge != null && card.badge() != null"
                             left>
                        <template v-slot:badge>
                            {{card.badge == null ? null : card.badge()}}
                        </template>
                        <v-btn>{{card.actiontext}}</v-btn>
                    </v-badge>
                </v-card-actions>
            </v-card>
        </v-col>
    </v-row>
</v-container>
</template>

<script>
// @ is an alias to /src
// import HelloWorld from '@/components/HelloWorld.vue'

import {
    mapGetters
} from 'vuex'
export default {
    data: () => ({}),
    computed: {
        cards: function() {
            var items = [];
            if (this.adminMode != undefined && this.adminMode === true) {
                //Some general event permissions

                items.push({
                    route: "/Admin/Badge_Checkin",
                    icon: "mdi-qrcode-scan",
                    label: "Badge Check-in",
                    show: () => {
                        return this.hasEventPerm('Badge_Checkin');
                    },
                    flex: 12,
                }, {
                    route: "/Admin/Contact",
                    icon: "mdi-account-details",
                    label: "Contacts",
                    show: () => {
                        return true;
                    },
                    flex: 4,
                }, {
                    route: "/Admin/Attendee",
                    icon: "mdi-badge-account-horizontal",
                    label: "Attendees",
                    show: () => {
                        return this.hasEventPerm('Attendee_View');
                    },
                    flex: 4,
                }, );

                //The groups
                this.badgeContexts.forEach((group, i) => {
                    if (group.id > 0) {
                        items.push({
                            route: "/Admin/Application/" + group.context_code,
                            icon: "mdi-" + (group.menu_icon != null ? group.menu_icon : "newspaper"),
                            label: group.name,
                            show: () => {
                                return this.hasGroupPerm(group.id, 'Submission_View');
                            },
                            flex: 4,

                        })
                    }
                });

                //Staff and management

                items.push({
                    route: "/Admin/Staff",
                    icon: "mdi-account-hard-hat",
                    label: "Staff",
                    show: () => {
                        return this.hasEventPerm(['Staff_View', 'Staff_Review']);
                    },
                    flex: 4,
                }, {
                    route: "/Admin/BadgeStats",
                    icon: "mdi-chart-bell-curve-cumulative",
                    label: "Badge Stats",
                    show: () => {
                        return this.hasEventPerm('Badge_Stats');
                    },
                    flex: 4,
                }, {
                    route: "/Admin/Users",
                    icon: "mdi-badge-account",
                    label: "Admin Users",
                    show: () => {
                        return this.hasEventPerm('Manage_Users');
                    },
                    flex: 4,
                }, {
                    route: "/Admin/Printing",
                    icon: "mdi-printer",
                    label: "Badge Printing",
                    show: () => {
                        return this.hasEventPerm('Badge_Print');
                    },
                    flex: 4,
                }, {
                    route: "/Admin/Payments",
                    icon: "mdi-credit-card",
                    label: "Payments",
                    show: () => {
                        return this.hasEventPerm('Payment_View');
                    },
                    flex: 4,
                }, );

            } else {
                items.push({
                    route: '/addbadge',
                    icon: 'mdi-cart-plus',
                    label: 'Register',
                    actiontext: 'Add Badge',
                    flex: 12,
                }, {
                    route: "/myBadges",
                    icon: "mdi-badge-account-horizontal",
                    label: "My badges",
                    actiontext: 'Access your badges',
                    show: () => {
                        return this.ownedBadgeCount > 0;
                    },
                    badge: () => {
                        return this.ownedBadgeCount;
                    },
                    flex: 6,
                }, {
                    route: "/myApplications",
                    icon: "mdi-account-box-multiple",
                    label: "My Applications",
                    actiontext: 'Access your applications',
                    show: () => {
                        return this.applicationCount > 0;
                    },
                    badge: () => {
                        return this.applicationCount;
                    },
                    flex: 6,
                }, {
                    route: "/cart",
                    icon: "mdi-cart",
                    label: "View cart",
                    actiontext: 'Checkout',
                    badge: () => {
                        return this.cartCount > 0 ? this.cartCount : null;
                    },
                    flex: 6,
                });
                if (!this.isLoggedIn) {
                    items.push({
                        route: "/login",
                        icon: "mdi-login",
                        label: "Login",
                        actiontext: 'Access your account',
                        flex: 6,
                    });
                }
            }

            return items;
        },
        ...mapGetters('cart', {
            'cartCount': 'cartCount'
        }),
        ...mapGetters('mydata', {
            'ownedBadgeCount': 'ownedBadgeCount',
            'applicationCount': 'applicationCount',
            'isLoggedIn': 'getIsLoggedIn',
            'isAdmin': 'hasPerms',
            'adminMode': 'getAdminMode',
            'hasEventPerm': 'hasEventPerm',
            'hasGroupPerm': 'hasGroupPerm',
            'getLoggedInName': 'getLoggedInName'
        }),
        ...mapGetters('products', {
            'events': 'events',
            'badgeContexts': 'badgeContexts',
            'productselectedEventId': 'selectedEventId',
            'productselectedEvent': 'selectedEvent'
        }),
    },
    components: {
        //    HelloWorld
    },
};
</script>
