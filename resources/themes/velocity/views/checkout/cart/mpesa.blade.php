@if ($cart)
    <script type="text/x-template" id="coupon-component-template">
        <div class="coupon-container">
            <div class="discount-control">
                <form class="coupon-form" method="post" @submit.prevent="sendMpesaStkPush">
                    <div class="control-group" :class="[errorMessage ? 'has-error' : '']">
                        <input required type="text" class="control" v-model="couponCode" name="phone" placeholder="{{ __('shop::app.checkout.onepage.enter-mpesa-number') }}">

                        <div class="control-error">@{{ errorMessage }}</div>
                    </div>

                    <button class="btn btn-lg btn-black" :disabled="disableButton">{{ __('shop::app.checkout.onepage.apply-coupon') }}</button>
                </form>
            </div>

            <div class="applied-coupon-details" v-if="appliedCoupon">
                <label>We sent a prompt to your phone.Enter pin to verify payments</label>
                {{-- <label>{{ __('shop::app.checkout.total.coupon-applied') }}</label> --}}

                <label class="right" style="display: inline-flex; align-items: center;">
                    <b>@{{ appliedCoupon }}</b>

                    <span class="icon cross-icon" title="{{ __('shop::app.checkout.total.remove-coupon') }}" v-on:click="removeCoupon"></span>
                </label>
            </div>
        </div>
    </script>

    <script>
        Vue.component('coupon-component', {
            template: '#coupon-component-template',

            inject: ['$validator'],

            data: function() {
                return {
                    couponCode: '',

                    appliedCoupon: "{{ $cart->coupon_code }}",

                    errorMessage: '',

                    routeName: "{{ request()->route()->getName() }}",

                    disableButton: false,

                    removeIconEnabled: true
                }
            },

            watch: {
                couponCode: function (value) {
                    if (value != '') {
                        this.errorMessage = '';
                    }
                }
            },

            methods: {
                sendMpesaStkPush: function() {
                    let self = this;

                    if (! this.couponCode.length) {
                        this.errorMessage = '{{ __('shop::app.checkout.total.invalid-coupon') }}';

                        return;
                    }

                    self.errorMessage = null;

                    self.disableButton = true;


                    axios.post('{{ route('shop.checkout.cart.mpesa.apply') }}', {code: self.couponCode})
                        .then(function(response) {
                        console.log("resultcode"+response.data.response.ResponseCode);
                            self.$root.showLoader();
                            if (response.data.response.ResponseCode == 0) {
                                self.$emit('onApplyCoupon');

                                self.appliedCoupon = self.couponCode;

                                self.couponCode = '';

                                window.flashMessages = [{
                                    'type': 'alert-success', 
                                    'message': "We sent a prompt to your phone.Enter pin to verify payments"
                                }];

                                self.$root.addFlashMessages();
                                console.log("cart_id: " + response.data.cart_id);
                                console.log("MerchantRequestID: " + response.data.response.MerchantRequestID);
                                
                                confirmPayments(response.data.response.MerchantRequestID, self);
                                                          

                                self.redirectIfCartPage();


                            } else {
                             
                                self.errorMessage = response.data.response.CustomerMessage;
                            }

                            self.disableButton = false;
                        })
                        .catch(function(error) {
                            self.$root.hideLoader();
                            self.errorMessage ="issue inititating payment";

                            self.disableButton = false;
                        });
                },

                      

                removeCoupon: function () {
                    let self = this;
                    
                    if (self.removeIconEnabled) { 
                        self.removeIconEnabled = false;

                        axios.delete('{{ route('shop.checkout.coupon.remove.coupon') }}')
                        .then(function(response) {
                            self.$emit('onRemoveCoupon')

                            self.appliedCoupon = '';

                            self.removeIconEnabled = true;

                            window.flashMessages = [{'type': 'alert-success', 'message': response.data.message}];

                            self.$root.addFlashMessages();

                            self.redirectIfCartPage();
                        })
                        .catch(function(error) {
                            window.flashMessages = [{'type': 'alert-error', 'message': error.response.data.message}];

                            self.$root.addFlashMessages();

                            self.removeIconEnabled = true;
                        });
                    }
                },

                redirectIfCartPage: function() {
                    if (this.routeName != 'shop.checkout.cart.index') return;

                    setTimeout(function() {
                        window.location.reload();
                    }, 700);
                }
            }
        });

        function confirmPayments(MerchantRequestID, self) {
        axios.post('{{ route('shop.checkout.cart.confirm-mpesa') }}', { merchant_request_id: MerchantRequestID })
            .then(function(response) {
                // console.log("resultcode: " + response.data.resultcode);
                if (response.data.resultcode == 0) {
                    self.$root.hideLoader();
                    placeOrder(self);
                    // console.log("resultcode: " + response.data.resultcode);
                } else if (response.data.resultcode == null) {
                    // Call the function again after 5 seconds
                    console.log("resultcode: " + response.data.resultcode);
                    setTimeout(function() {
                        confirmPayments(MerchantRequestID, self);
                    }, 5000);
                } else {
                self.$root.hideLoader();
                console.log("resultcode: " + response.data.resultcode);
                self.errorMessage = response.data.message || 'Error occurred during payment confirmation';
                self.disableButton = false;
                }
            })
            .catch(function(error) {
            self.$root.hideLoader();
            self.errorMessage = error.response?.data?.message || 'Error occurred during payment confirmation';
            self.disableButton = false;
            });
    }

                async function  placeOrder(self) {
                        // if (self.isPlaceOrderEnabled) {
                            console.log('place order');
                            self.disable_button = false;
                            self.isPlaceOrderEnabled = false;

                            self.$root.showLoader();

                            self.$http.post("{{ route('shop.checkout.save_order') }}", {'_token': "{{ csrf_token() }}"})
                            .then(response => {
                                console.log("save order redirect url:"+response.data.redirect_url)
                                if (response.data.success) {
                                    // if (response.data.redirect_url) {
                                    //     self.$root.hideLoader();
                                    //     window.location.href = response.data.redirect_url;
                                    // } 
                                    // else 
                                    // {
                                        self.$root.hideLoader();
                                        window.location.href = "{{ route('shop.checkout.success') }}";
                                    // }
                                }
                            })
                            .catch(error => {
                                self.disable_button = true;
                                self.$root.hideLoader();

                                window.showAlert(`alert-danger`, self.__('shop.general.alert.danger'), error.response.data.message ? error.response.data.message : "{{ __('shop::app.common.error') }}");
                            })
                        // } else {
                        //     self.disable_button = true;
                        // }
                    }
    </script>
@endif