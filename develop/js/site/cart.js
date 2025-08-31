Vue.component("carticon", {
    template: `
        <div class="cart-display">
            <a :href="cartUrl" class="flex items-center gap-2">
                <span class="cart-icon">🛒</span>
                <span class="cart-count">{{ cartQuantity }}</span>
               
            </a>
        </div>
    `,
    computed: {
        cartUrl() {
            return '/checkout';
        },
        cartQuantity() {
            return this.$root.cartQuantity || 0;
        },
        cartHasItems() {
            return this.cartQuantity > 0;
        },
        cartLabel() {
            const count = this.cartQuantity;
            return count === 1 ? 'item in cart' : 'items in cart';
        }
    },
    watch: {
        cartQuantity(newVal, oldVal) {
            console.log('Cart quantity changed from', oldVal, 'to', newVal); // Debug log
            // Force update when cart quantity changes
            this.$forceUpdate();
        }
    },
    mounted() {
        console.log('Cart icon mounted with quantity:', this.cartQuantity); // Debug log
        // Initial cart count load if needed
        if (this.$root && typeof this.$root.refreshCartCount === 'function') {
            this.$root.refreshCartCount();
        }
    }
});