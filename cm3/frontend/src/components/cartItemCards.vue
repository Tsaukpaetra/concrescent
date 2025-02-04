<template>
    <v-container fluid>
        <v-row>
            <v-col cols="12"
                    md="6"
                    lg="4"
                    xl="3"
                    v-for="(product, idx) in cart.items"
                    :key="product.cartIx">
                <v-card>
                    <badgeSampleRender :badge="product" />
                    <v-card-actions>
                        <div class="text-truncate">{{product.badge_type_name}}</div>
                        &nbsp;|&nbsp;
                        <v-badge :value="product.payment_promo_code != undefined && product.payment_promo_code.length > 0"
                                    color="cyan lighten-3"
                                    left floating>
                            <template v-slot:badge>
                                <v-icon @click.stop="promoAppliedDialogData = product">mdi-sale</v-icon>
                            </template>
                            <span>{{ product.payment_promo_price | currency }}&nbsp;</span>
                        </v-badge>
                        <v-spacer></v-spacer>
                        <v-tooltip bottom>
                            <template v-slot:activator="{ on, attrs }">
                                <v-btn color="error"
                                        small
                                        v-bind="attrs"     
                                        v-on="on"                                               
                                        v-if="!!badgeErrorCount(cart,idx)">
                                        {{ badgeErrorCount(cart,idx) }} <v-icon>mdi-alert-outline</v-icon>
                                    </v-btn>
                            </template> 
                            <span>
                                Problems:
                                <ul>
                                    <li v-for="(er, i) in cart.errors[idx]" :key="i">
                                        {{ badgeError(er) }}
                                    </li>
                                </ul>
                            </span>
                        </v-tooltip>
                        <v-btn icon
                            :disabled="!cart.canEdit"
                            @click.stop="editBadge(cart.id, idx)">
                            <v-icon>mdi-pencil</v-icon>
                        </v-btn>
                        <v-btn icon
                                :disabled="!cart.canEdit"
                                @click.stop="startRemoveBadge(cart.id, idx)">
                            <v-icon>mdi-delete</v-icon>
                        </v-btn>
                    </v-card-actions>
                    <v-card-actions v-for="addonid in filterAddons(product)"
                                    :key="addonid['addon_id']">
                        <v-icon>mdi-plus</v-icon>
                        <div class="text-truncate">
                            {{getAddonByID(product.context_code, product.badge_type_id, addonid['addon_id']) ? getAddonByID(product.context_code, product.badge_type_id, addonid['addon_id']).name : "Loading..."}}
                        </div>&nbsp;|&nbsp;
                        <span>{{ (getAddonByID(product.context_code, product.badge_type_id, addonid['addon_id']) ? getAddonByID(product.context_code, product.badge_type_id, addonid['addon_id']).price : "Loading" ) | currency }}&nbsp;</span>
                    </v-card-actions>
                    <v-card-actions v-for="(subbadge,ix) in product.subbadges"
                                    :key="ix">
                        <v-icon>mdi-account</v-icon>
                        <div class="text-truncate">
                            {{subbadge | badgeDisplayName(false)}}
                        </div>&nbsp;|&nbsp;
                        <span>{{ (subbadge.payment_price ? subbadge.payment_price: "Loading" ) | currency }}&nbsp;</span>

                    </v-card-actions>
                    <v-card-actions v-for="(assignment,ix) in product.assignment_count_charging"
                                    :key="'assn'+ix">
                        <v-icon>mdi-application</v-icon>
                        <div class="text-truncate pl-1">
                            Fee for assignment slot {{  assignment.slot }}
                        </div>&nbsp;|&nbsp;
                        <span>{{ assignment.price ? assignment.price  : assignment.prepaid ? 'Paid' : 'Included' | currency }}&nbsp;</span>

                    </v-card-actions>
                </v-card>
            </v-col>
            <v-col cols="12"
                    md="6"
                    lg="4"
                    xl="3"
                    v-if="!cart.RequiresApproval && cart.canEdit">
                <v-card class="fill-height ma-0"
                        justify="center">
                    <v-btn block
                            color="primary"
                            rounded
                            @click="addBadge(cart.id)"
                            align="center">Add
                        {{cart.items.length ? "another" : "a"}} badge
                    </v-btn>
                </v-card>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import {
    mapGetters,
    mapState,
    mapActions
} from 'vuex'
import badgeSampleRender from '@/components/badgeSampleRender.vue';
export default {
    components: {
        badgeSampleRender,
    },
    props: ['cart'],
    data() {
        return {

            promocodeDialog: false,
            promocode: '',
            promoCodeProcessing: false,
            promoAppliedDialog: false,
            promoAppliedDialogData: {},
            promoCodeErrors: [],
            processingCheckoutDialog: false,
            clearCartDialog: false,
            orderSteps: {
                'undefined': 'Processing order, please wait...',
                'ready': 'Directing to Merchant...',
                'AwaitingApproval': 'Confirming submission',
                'refused': 'Payment has been refused. That\'s all we know.',
                'confirm': 'Confirming payment...',
            },
            cartStateColor: {
                'NotReady': 'purple',
                'AwaitingApproval': 'yellow',
                'NotStarted': 'gray',
                'Incomplete': 'lime',
                'Cancelled': 'red',
                'Rejected': 'red',
                'Completed': 'green',
                'Refunded': 'indigo',
                'RefundedInPart': 'indigo'
            },
            cartState: 'undefined',
            cartLocked: '',


        };
    },
    computed: {
        ...mapState({
            checkoutStatus: state => state.cart.checkoutStatus,
            addons: state => state.products.addons,
            currentCartId: state => state.cart.cartId,
            kioskMode: state => state.station.kioskMode,
        }),
        ...mapGetters('mydata', {
            'isLoggedIn': 'getIsLoggedIn',
        }),
        ...mapGetters('cart', {
            products: 'cartProducts',
            total: 'cartTotalPrice',
            needsave: 'isDirty',
            canPayCart: 'canPay',
        }),
        removeBadgeModal: function() {
            return this.removeBadge > -1;
        },
        itemsHaveErrors: function() {
            if (this.checkoutStatus == null)
                return false;
            if (typeof this.checkoutStatus.errors == "undefined" || typeof this.checkoutStatus.errors == "object")
                return false;
            return this.checkoutStatus.errors.reduce((result, currentItem) => result | currentItem.length > 0, false);
        },
        isCartLocked: {
            get() {
                return this.cartLocked.length > 0;
            },
            set(newval) {
                this.cartLocked = newval ? "???" : "";
            }
        },
    },
    methods: {
        ...mapActions('cart', [
            'removePromoFromProduct',
            'removeProductFromCart',
            'loadCart',
            'saveCart',
            'switchCart',
            'checkoutCart',
            'checkoutCartByUUID',
            'clearCart'
        ]),
        badgeErrorCount: function(cart, ix) {
            if (cart == undefined) return 0;
            if (ix == undefined) return 0;
            if (cart.errors == undefined) return 0;
            if (cart.errors.length < ix) return 0;
            return Object.keys(cart.errors[ix]).length;
        },
        badgeError: function(er){
            var msgAr = er.split(' ');
            var preText = '';
            switch (msgAr[0]) {
                case 'badge_type_id':
                    preText = 'Selected badge';
                    break;
                case 'id':
                    preText = 'This';
                break;
                default:
                    preText = 'Question ' + msgAr[0];
                    break;
            }
            msgAr.splice(0,1,preText);
            return msgAr.join(' ');
        },
        cartStateTranslation: function(state) {
            return ({
                'NotReady': 'Not ready',
                'AwaitingApproval': 'Waiting for approval',
                'NotStarted': 'Ready to continue',
                'Incomplete': 'Waiting for payment',
                'Cancelled': 'Cancelled',
                'Rejected': 'Rejected',
                'Completed': 'Completed',
                'Refunded': 'Refunded',
                'RefundedInPart': 'Partially refunded'
            })[state] || state;
        },
        showpromocodeDialog: function(cartid) {
            this.promocodeDialog = true;
            this.cartIdSelected = cartid;
        },
        submitPromoCode: function() {
            this.promoCodeProcessing = true;
            this.saveCart(this.promocode);
        },
        promoRemove: function() {
            this.removePromoFromProduct(this.promoAppliedDialogData);
            this.saveCart();
            this.promoAppliedDialog = false;
        },
        async startRemoveBadge(cartId, badgeix) {
            await this.loadCart(cartId);
            this.cartIdSelected = cartId;
            this.removeBadge = badgeix;
        },
        confirmRemoveBadge: function() {
            this.removeProductFromCart(this.removeBadge);
            this.removeBadge = -1;
            this.saveCart();
        },
        showclearCartDialog: function(cartid) {
            this.clearCartDialog = true;
            this.cartIdSelected = cartid;
        },
        confirmClearCart: function() {
            this.clearCart().then(() => {

                this.clearCartDialog = false;
                this.cartLocked = "";
                this.$store.dispatch('mydata/fetchCarts', false).then((carts) => {
                    if (carts.length > 0)
                        this.cartIdSelected = carts[carts.length - 1].id;
                    else
                        this.cartIdSelected = 0;
                })
            })
        },
        async checkout(cartid) {
            this.processingCheckoutDialog = true;
            if (cartid != undefined)
                await this.loadCart(cartid);
            this.processingCheckoutDialog = true;
            //Fancy delays
            await new Promise(resolve => setTimeout(resolve, 3000));
            this.checkoutCart();
        },
        closepromo: function() {
            this.promoAppliedDialog = false;
            this.promocodeDialog = false;
        },
        closeerror: function() {
            this.createError = "";
            this.sentmagicmail = false;
            this.creatingAccount = false;
        },
        filterAddons(product) {
            if (product.addons == undefined) return [];
            if (product.editBadgePriorAddons == undefined)
                return product.addons;
            return product.addons.filter(addon => !product.editBadgePriorAddons.includes(addon['addon_id']));
        },
        getAddonByID(context_code, badge_type_id, id) {
            var result = {
                "id": 0,
                "display_order": 0,
                "name": "[Loading...]",
                "description": "Loading description, please wait",
                "rewards": null,
                "price": "",
                "payable_onsite": 0,
                "quantity": null,
                "start_date": null,
                "end_date": null,
                "min_age": null,
                "max_age": null,
                "dates_available": "forever to forever",
                "quantity_sold": 0,
                "quantity_remaining": null
            }
            if (undefined == this.addons[context_code])
                return result;
            if (undefined == this.addons[context_code][badge_type_id])
                return result;
            return this.addons[context_code][badge_type_id].find(addon => addon.id == id) || result;
        }

    },
    watch: {
        
        'checkoutStatus': function(newstatus) {
            this.cartState = newstatus ? newstatus.state : 'undefined';
            //The rest of this stuff is to react to submitting the cart, don't do it if we don't have a progress dialog box
            if(this.processingCheckoutDialog == false && this.promoCodeProcessing == false) {
                return;
            }
            if (newstatus) {
                switch (newstatus.state) {
                    case 'Incomplete':
                        //Direct to the checkout URL
                        var _this = this;
                        setTimeout(function() {
                            _this.processingCheckoutDialog = false;
                        }, 15000);
                        //TODO: This is a hack!
                        if (!this.kioskMode) {

                            if (newstatus.paymentURL != undefined) {
                                console.log('Will redirect to', newstatus.paymentURL);
                                window.location.href = newstatus.paymentURL;
                            } else if (!this.isLoggedIn) {
                                //Not able to complete and no redirect. Display message?
                                this.cartLocked = 'Still awaiting payment.'
                                this.processingCheckoutDialog = false;
                            }
                        } else {
                            this.processingCheckoutDialog = false;
                            this.kioskPay = true;
                            this.logout();
                        }
                        break;
                    case 'AwaitingApproval':
                        var _this = this;
                        setTimeout(function() {
                            if (_this.processingCheckoutDialog) {
                                _this.processingCheckoutDialog = false;
                                _this.AwaitingApprovalDialog = true;
                            }
                            if (_this.isLoggedIn)
                                _this.$store.dispatch('mydata/fetchCarts', false)
                        }, 1500);
                        //Clear the selected cart
                        console.log('awaiting approval clearing cart')
                        this.loadCart(null);
                        break;
                    case 'Completed':
                        //Determine if this was a normal Attendee or a group application
                        var path = (this.products[0].context_code == 'A' || this.products[0].context_code == 'S') ?
                            '/myBadges' : '/myApplications';

                        //Clear the cart and send them to retrieve their badges
                        this.loadCart(null);
                        this.$router.push({
                            path: path,
                            query: {
                                refresh: true
                            }
                        });
                        this.processingCheckoutDialog = false;
                        break;
                    default:
                        if (this.promoCodeProcessing) {

                            this.promoCodeProcessing = false;
                            this.promocode = "";
                            this.promocodeDialog = false;
                            this.processingCheckoutDialog = false;
                        }
                }
                //Always refresh the cart list if we're logged in
                // if (this.isLoggedIn)
                //     this.$store.dispatch('mydata/fetchCarts', false);
            }
        },
        'promoAppliedDialogData': function(newData) {
            this.promoAppliedDialog = newData != null;
        }
    },
};
</script>
