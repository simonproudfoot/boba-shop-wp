Vue.component("checkout", {
    props: {
        stripePublishableKey: {
            type: String,
            required: true
        },
        deliveryNonce: {
            type: String,
            required: true
        },
        createOrderNonce: {
            type: String,
            required: true
        },
        createStripeSessionNonce: {
            type: String,
            required: true
        },
        adminAjaxUrl: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            stripe: null,
            addressSaved: false,
            isSubmitting: false,
            isCreatingSession: false
        };
    },
    computed: {
        // Computed properties can be added here if needed
    },
    watch: {
        // Watchers can be added here if needed
        adminAjaxUrl: {
            handler(newVal, oldVal) {
                console.log('adminAjaxUrl changed:', { old: oldVal, new: newVal });
            },
            immediate: true
        }
    },
            mounted() {
            console.log('Checkout component mounted, initializing...');
            console.log('Initial Vue component state:', {
                addressSaved: this.addressSaved,
                isSubmitting: this.isSubmitting,
                isCreatingSession: this.isCreatingSession,
                stripePublishableKey: this.stripePublishableKey,
                adminAjaxUrl: this.adminAjaxUrl
            });
            
            // Test if component is working
        
            
            // Initialize Stripe
            if (typeof Stripe !== 'undefined') {
                this.stripe = Stripe(this.stripePublishableKey);
                console.log('Stripe initialized successfully');
            } else {
                console.error('Stripe library not loaded');
            }
            
            // Initialize remove item functionality
            this.initializeRemoveItems();
            
            console.log('Checkout component initialization complete');
            
            // Check if checkout actions div exists
            const checkoutActions = document.getElementById('checkout-actions');
            console.log('Checkout actions div found:', checkoutActions);
            if (checkoutActions) {
                console.log('Checkout actions div HTML:', checkoutActions.outerHTML);
            }
        },
    methods: {
        // Handle delivery address form submission
        async handleDeliveryAddressSubmission(event) {
            event.preventDefault();
            
            if (this.isSubmitting) return;
            
            console.log('Form submitted, preparing AJAX request...');
            this.isSubmitting = true;
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'delivery_address_submission');
            formData.append('nonce', this.deliveryNonce);
            
            // Log all form data
            console.log('All form data:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            console.log('Form data prepared:', {
                action: 'delivery_address_submission',
                nonce: this.deliveryNonce,
                customer_name: formData.get('customer_name'),
                customer_email: formData.get('customer_email')
            });
            
            const saveButton = form.querySelector('button[type="submit"]');
            const originalText = saveButton.innerHTML;
            saveButton.innerHTML = 'Saving...';
            saveButton.disabled = true;
            
            console.log('Sending AJAX request to:', this.adminAjaxUrl);
            
            try {
                const response = await fetch(this.adminAjaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response received:', response);
                const data = await response.json();
                console.log('Response data:', data);
                
                if (data.success) {
                    console.log('Setting addressSaved to true...');
                    this.addressSaved = true;
                    console.log('addressSaved is now:', this.addressSaved);
                    
                    saveButton.innerHTML = 'Address Saved ✓';
                    saveButton.classList.remove('bg-green-600', 'hover:bg-green-700');
                    saveButton.classList.add('bg-gray-500', 'cursor-default');
                    
                    console.log('Address saved successfully, checkout button should now be visible');
                    console.log('Vue component state:', {
                        addressSaved: this.addressSaved,
                        isSubmitting: this.isSubmitting,
                        isCreatingSession: this.isCreatingSession
                    });
                    
                    // Force Vue to re-render
                    this.$forceUpdate();
                } else {
                    console.error('Failed to save address:', data.data);
                    alert(data.data || 'Failed to save address');
                    saveButton.innerHTML = originalText;
                    saveButton.disabled = false;
                }
            } catch (error) {
                console.error('Error during AJAX request:', error);
                alert('An error occurred while saving your address');
                saveButton.innerHTML = originalText;
                saveButton.disabled = false;
            } finally {
                this.isSubmitting = false;
            }
        },
        
        // Create order function
        async createOrder() {
            console.log('Creating order...');
            const formData = new FormData();
            formData.append('action', 'create_order');
            formData.append('nonce', this.createOrderNonce);
            
            console.log('Sending order creation request...');
            
            try {
                const response = await fetch(this.adminAjaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Order creation response received:', response);
                const data = await response.json();
                console.log('Order creation data:', data);
                
                if (data.success) {
                    console.log('Order created successfully:', data.data);
                    return data.data;
                } else {
                    console.error('Failed to create order:', data.data);
                    throw new Error(data.data || 'Failed to create order');
                }
            } catch (error) {
                console.error('Error creating order:', error);
                throw error;
            }
        },
        
        // Handle checkout button click
        async handleCheckoutClick() {
            if (!this.addressSaved) {
                alert('Please save your delivery address first');
                return;
            }
            
            if (this.isCreatingSession) return;
            
            console.log('Checkout button clicked, creating order first...');
            
            try {
                // First create the order
                await this.createOrder();
                
                // Then create Stripe checkout session
                await this.createStripeSession();
            } catch (error) {
                console.error('Error during checkout process:', error);
                alert('An error occurred during checkout: ' + error.message);
            }
        },
        
        // Create Stripe session function
        async createStripeSession() {
            if (this.isCreatingSession) return;
            
            this.isCreatingSession = true;
            const formData = new FormData();
            formData.append('action', 'create_stripe_session');
            formData.append('nonce', this.createStripeSessionNonce);
            
            console.log('Creating Stripe session...');
            
            try {
                const response = await fetch(this.adminAjaxUrl, {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Stripe session response received:', response);
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                
                // Check content type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.warn('Response is not JSON, content-type:', contentType);
                }
                
                const text = await response.text();
                console.log('Response text:', text);
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    console.error('Raw response:', text);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
                
                console.log('Stripe session data:', data);
                
                if (data.success) {
                    // Redirect to Stripe Checkout
                    if (this.stripe) {
                        const result = await this.stripe.redirectToCheckout({
                            sessionId: data.data.session_id
                        });
                        
                        if (result.error) {
                            alert(result.error.message);
                        }
                    } else {
                        throw new Error('Stripe not initialized');
                    }
                } else {
                    alert(data.data || 'Failed to create payment session');
                }
            } catch (error) {
                console.error('Error creating Stripe session:', error);
                alert('An error occurred while creating payment session: ' + error.message);
            } finally {
                this.isCreatingSession = false;
            }
        },
        
        // Reset checkout button to normal state
        resetCheckoutButton() {
            // This method is no longer needed as we're using Vue reactive data
            // The button state is managed by Vue directives
        },
        
        // Initialize remove item functionality
        initializeRemoveItems() {
            console.log('Initializing remove item functionality...');
            // Handle remove item buttons
            const removeButtons = document.querySelectorAll('.remove-item');
            console.log('Found remove buttons:', removeButtons.length);
            
            removeButtons.forEach(button => {
                button.addEventListener('click', (event) => {
                    const productId = button.getAttribute('data-product-id');
                    const variantId = button.getAttribute('data-variant-id');
                    const row = button.closest('tr');
                    
                    if (confirm('Are you sure you want to remove this item from your cart?')) {
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', this.adminAjaxUrl, true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4 && xhr.status === 200) {
                                // Remove the row from the table
                                row.remove();
                                // Reload the page to update totals
                                window.location.reload();
                            }
                        };
                        xhr.send('action=remove_from_cart&product_id=' + productId + '&variant_id=' + variantId);
                    }
                });
            });
        },
        
        // Debug method to check component state
        debugComponent() {
            console.log('=== Checkout Component Debug Info ===');
            console.log('Vue Component State:');
            console.log('- addressSaved:', this.addressSaved);
            console.log('- isSubmitting:', this.isSubmitting);
            console.log('- isCreatingSession:', this.isCreatingSession);
            console.log('- stripe:', this.stripe);
            console.log('- adminAjaxUrl:', this.adminAjaxUrl);
            console.log('- stripePublishableKey:', this.stripePublishableKey);
            
            console.log('\nDOM Elements:');
            const checkoutActions = document.getElementById('checkout-actions');
            console.log('- checkout-actions div exists:', !!checkoutActions);
            if (checkoutActions) {
                console.log('- checkout-actions div HTML:', checkoutActions.outerHTML);
                console.log('- checkout-actions div computed style display:', window.getComputedStyle(checkoutActions).display);
                console.log('- checkout-actions div computed style visibility:', window.getComputedStyle(checkoutActions).visibility);
            }
            
            console.log('\nVue Component Instance:');
            console.log('- $el:', this.$el);
            console.log('- $data:', this.$data);
            console.log('- $props:', this.$props);
            
            console.log('=== End Debug Info ===');
        },
        
        // Test Stripe connection
        async testStripeConnection() {
            try {
                console.log('Testing Stripe connection...');
                
                const response = await fetch(this.adminAjaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'test_stripe_connection',
                        nonce: this.createStripeSessionNonce
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('Stripe connection test successful!');
                    console.log('Stripe test result:', data.data);
                } else {
                    alert('Stripe connection test failed: ' + (data.data || 'Unknown error'));
                    console.error('Stripe test failed:', data.data);
                }
                
            } catch (error) {
                console.error('Error testing Stripe connection:', error);
                alert('Error testing Stripe connection: ' + error.message);
            }
        }
    }
});