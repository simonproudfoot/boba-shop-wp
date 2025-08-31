Vue.component("productpage", {
    props: {
        image: {
            type: String,
            default: ''
        },
        category: {
            type: String,
            default: ''
        },
        price: {
            type: Number,
            default: 0
        },
        title: {
            type: String,
            default: ''
        },
        description: {
            type: String,
            default: ''
        },
        stockLevel: {
            type: Number,
            default: 0
        },
        sold_out: {
            type: Boolean,
            default: false
        },
        productId: {
            type: Number,
            required: true
        },
        colorVariations: {
            type: Array,
            default: () => []
        },
        sizes: {
            type: Array,
            default: () => []
        },
        hasVariants: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            chosenCount: 1,
            isAddingToCart: false,
            addToCartSuccess: false,
            addToCartError: '',
            selectedColor: null,
            selectedSize: null,
            currentImage: this.image
        };
    },

    watch: {
        image(newImage) {
            if (!this.selectedColor) {
                this.currentImage = newImage;
            }
        }
    },

    computed: {
        random() {
            return Math.floor(Math.random() * 2) + 1;
        },
        uniqueColors() {
            // Get unique colors from variants
            const seen = new Set();
            return this.colorVariations.filter(v => {
                if (!v.color || seen.has(v.color)) return false;
                seen.add(v.color);
                return true;
            });
        },
        availableSizesForColor() {
            if (!this.selectedColor) return [];
            // Get all sizes for the selected color from variants
            return this.colorVariations
                .filter(v => v.color === this.selectedColor.color)
                .map(v => v.size)
                .filter((v, i, arr) => v && arr.indexOf(v) === i);
        },
        selectedVariant() {
            if (!this.selectedColor || !this.selectedSize) return null;
            return this.colorVariations.find(v => v.color === this.selectedColor.color && v.size === this.selectedSize);
        },
        effectiveStockLevel() {
            if (this.hasVariants && this.selectedVariant) {
                return this.selectedVariant.stock;
            } else if (!this.hasVariants) {
                return this.stockLevel;
            }
            return 0;
        },
        imageSrc() {
            if (typeof this.image === 'string') {
                return this.image || '';
            }
            if (this.image && typeof this.image === 'object') {
                if (this.isLarge && this.image.thumb_2200) {
                    return this.image.thumb_2200;
                }
                if (this.image.thumb_1280) {
                    return this.image.thumb_1280;
                }
                if (this.image.full) {
                    return this.image.full;
                }
            }
            return '';
        },
        imageSrcset() {
            if (typeof this.image === 'string') {
                return null;
            }
            if (this.image && typeof this.image === 'object') {
                const srcset = [];
                if (this.image.thumb_1280) {
                    srcset.push(`${this.image.thumb_1280} 1280w`);
                }
                if (this.image.thumb_2200 && this.image.thumb_2200 !== this.image.thumb_1280) {
                    srcset.push(`${this.image.thumb_2200} 2200w`);
                }
                if (this.image.full && this.image.full !== this.image.thumb_1280 && this.image.full !== this.image.thumb_2200) {
                    srcset.push(`${this.image.full} 1920w`);
                }
                return srcset.length ? srcset.join(', ') : null;
            }
            return null;
        },
        imageSizes() {
            return '(max-width: 1280px) 1280px, 2200px';
        },
        cartUrl() {
            return window.location.origin + '/?checkout=1';
        },
        showMainPrice() {
            return !this.hasVariants;
        },
        showMainStock() {
            return !this.hasVariants;
        },
        canSelectQuantity() {
            if (this.hasVariants) {
                return this.selectedVariant && this.selectedVariant.stock > 0;
            } else {
                return this.stockLevel > 0;
            }
        }
    },
    methods: {
        selectColor(colorVariant) {
            this.selectedColor = colorVariant;
            this.currentImage = colorVariant.image;
            this.selectedSize = null;
            this.chosenCount = 1;
        },
        selectSize(size) {
            if (!this.selectedColor) return;
            const variant = this.colorVariations.find(v => v.color === this.selectedColor.color && v.size === size);
            if (!variant || variant.stock <= 0) return;
            this.selectedSize = size;
            this.currentImage = variant.image;
            this.chosenCount = 1;
        },
        isColorOutOfStock(color) {
            // If all variants for this color are out of stock
            return this.colorVariations.filter(v => v.color === color).every(v => v.stock <= 0);
        },
        isSizeOutOfStock(color, size) {
            const variant = this.colorVariations.find(v => v.color === color && v.size === size);
            return !variant || variant.stock <= 0;
        },
        async addToCart() {
            if (this.isAddingToCart || this.effectiveStockLevel <= 0) {
                return;
            }

            this.isAddingToCart = true;
            this.addToCartSuccess = false;
            this.addToCartError = '';

            let cartData = {
                product_id: this.productId
            };

            if (this.selectedColor) {
                cartData.variation_color = this.selectedColor.color;
                cartData.variation_color_name = this.selectedColor.color_name;
            }
            if (this.selectedSize) {
                cartData.variation_size = this.selectedSize;
            }
            if (this.selectedVariant) {
                cartData.variant_id = this.selectedVariant.color + '-' + this.selectedVariant.size;
            }

            try {
                const response = await this.$root.addToCart(cartData);
                if (response.success) {
                    if (this.chosenCount > 1) {
                        await this.updateQuantity(response);
                    } else {
                        this.finishCartOperation(response);
                    }
                } else {
                    this.isAddingToCart = false;
                    this.addToCartError = 'Failed to add to cart.';
                }
            } catch (error) {
                this.isAddingToCart = false;
                this.addToCartError = 'An error occurred: ' + error;
            }
        },
        async updateQuantity(previousResponse) {
            let updateData = {
                product_id: this.productId,
                quantity: this.chosenCount
            };
            if (this.selectedColor) {
                updateData.variation_color = this.selectedColor.color;
                updateData.variation_color_name = this.selectedColor.color_name;
            }
            if (this.selectedSize) {
                updateData.variation_size = this.selectedSize;
            }
            if (this.selectedVariant) {
                updateData.variant_id = this.selectedVariant.color + '-' + this.selectedVariant.size;
            }
            try {
                const response = await this.$root.updateCartQuantity(updateData);
                if (response.success) {
                    this.finishCartOperation(response);
                } else {
                    this.finishCartOperation(previousResponse);
                }
            } catch (error) {
                this.finishCartOperation(previousResponse);
            }
        },

        finishCartOperation(response) {
            this.isAddingToCart = false;
            this.addToCartSuccess = true;

            console.log('Finishing cart operation with response:', response); // Debug log

            // Calculate total cart count
            const cartCount = this.calculateCartQuantity(response.data);
            console.log('Calculated cart count:', cartCount); // Debug log

            // Update all cart count references
            window.cartCount = cartCount;

            // Update root Vue instance cartQuantity
            if (this.$root) {
                this.$root.cartQuantity = cartCount;

                // Force reactivity update
                this.$root.$forceUpdate();

                // Call the menu update method if it exists
                if (typeof this.$root.updateMenuCartDisplay === 'function') {
                    this.$root.updateMenuCartDisplay();
                }

                console.log('Updated root cart quantity to:', this.$root.cartQuantity); // Debug log
            }

            // Reset success message after delay
            setTimeout(() => {
                this.addToCartSuccess = false;
            }, 3000);
        },

        // Also update the calculateCartQuantity method in product-page.js to match:
        calculateCartQuantity(cartData) {
            if (!cartData) return 0;

            console.log('Product page calculating cart quantity for:', cartData); // Debug log

            // Handle different cart data structures - same logic as vue.js
            return Object.values(cartData).reduce((sum, item) => {
                let qty = 0;

                if (typeof item === 'number') {
                    qty = item;
                } else if (typeof item === 'object' && item !== null) {
                    // Handle object with quantity property
                    qty = item.quantity || item.qty || 0;
                } else if (typeof item === 'string') {
                    qty = parseInt(item, 10) || 0;
                }

                return sum + qty;
            }, 0);
        },
        buyNow() {
            // First add to cart
            if (this.isAddingToCart || this.effectiveStockLevel <= 0) {
                return;
            }

            this.addToCart();

            // Then redirect to checkout
            setTimeout(() => {
                if (this.addToCartSuccess) {
                    window.location.href = this.cartUrl;
                }
            }, 800); // Slightly longer delay to ensure cart is updated
        }
    },
    mounted() {

        // Preselect first color and first available size
        if (this.uniqueColors && this.uniqueColors.length > 0) {
            this.selectColor(this.uniqueColors[0]);
            // Preselect first available size for that color
            const sizes = this.availableSizesForColor;
            if (sizes && sizes.length > 0) {
                this.selectSize(sizes[0]);
            }
        }
    },
    template: `
        <div class=" md:py-12 px-4 md:px-8 font-body">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row gap-8 lg:gap-16">
                    <!-- Product Image Section -->
                    <div class="md:w-1/2 relative group">
                        <div class="relative "> 
                           
                            <img class="rounded-lg z-40 relative mx-auto " :src="currentImage" :alt="title" /> 
                        </div>
                        
                        <!-- Color Variations -->
                        <div v-if="uniqueColors && uniqueColors.length > 0" class="mt-4">
                            <h5 class="text-sm font-medium mb-2">Color <span v-if="selectedColor">: {{ selectedColor.color_name }}</span></h5>
                            <div class="flex flex-wrap gap-2">
                                <div v-for="color in uniqueColors" :key="color.color"
                                    @click="!isColorOutOfStock(color.color) && selectColor(color)"
                                    class="w-8 h-8 rounded-full cursor-pointer border-2 transition-all"
                                    :class="[
                                        selectedColor && selectedColor.color === color.color ? 'border-zinc-800 scale-110' : 'border-transparent',
                                        isColorOutOfStock(color.color) ? 'opacity-40 pointer-events-none cursor-not-allowed' : ''
                                    ]"
                                    :style="{ backgroundColor: color.color }">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Product Details Section -->
                    <div class="md:w-1/2 max-w-md">
                        <div class="flex flex-col gap-y-4">
                            <div>
                                <h4 v-if="category" class="text-sm text-yellow-500 font-light mb-4 opacity-50">{{category}}</h4>
                                <h1 class="text-3xl md:text-5xl font-heading mb-2 uppercase text-balance"  v-html="title"></h1>
                                <span v-if="showMainPrice && price" class="text-2xl font-bold block mb-4">£{{ price }}</span>
                                <div class="stock-status mb-4" v-if="showMainStock">
                                    <span class="text-sm font-medium" :class="effectiveStockLevel > 0 ? 'text-green-600' : 'text-red-600'" v-html="effectiveStockLevel > 0 ? 'In stock - ' + effectiveStockLevel + ' available' : 'Out of stock'"></span>
                                </div>
                                <div class="product-description prose text-balance mt-4 prose-md mb-6" v-if="description" v-html="description"></div>
                            </div>
                            


                            <!-- Sizes -->
                            <div v-if="selectedColor && availableSizesForColor.length > 0" class="mb-4">
                                <h5 class="text-sm font-medium mb-2">Size</h5>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="size in availableSizesForColor" :key="size"
                                        v-if="colorVariations.find(v => v.color === selectedColor.color && v.size === size)"
                                        @click="!isSizeOutOfStock(selectedColor.color, size) && selectSize(size)"
                                        class="px-4 py-2 border rounded-sm transition-colors duration-200 capitalize"
                                        :class="[
                                            selectedSize === size ? 'bg-black text-white border-black' : 'bg-white text-black hover:bg-zinc-100',
                                            isSizeOutOfStock(selectedColor.color, size) ? 'opacity-40 pointer-events-none cursor-not-allowed' : ''
                                        ]">
                                        {{ size }}
                                    </button>
                                </div>
                            </div>

                            <!-- Status messages -->
                            <div v-if="addToCartSuccess" class="text-green-600 py-2 px-4 bg-green-50 rounded-sm mb-4">
                                ✓ Added to cart successfully!
                            </div>
                            <div v-if="addToCartError" class="text-red-600 py-2 px-4 bg-red-50 rounded-sm mb-4">
                                ✗ {{ addToCartError }}
                            </div>
                            
                            <!-- Purchase Actions -->
                            <div :class="['flex flex-col gap-y-4', canSelectQuantity ? '' : 'opacity-30 pointer-events-none']">
                                <div class="quantity-selector">
                                    <label class="block text-sm font-medium mb-2">Quantity</label>
                                    <div class="bg-white rounded-sm flex items-center justify-between border border-zinc-300 max-w-[140px]">
                                        <button @click="canSelectQuantity && chosenCount > 1 ? chosenCount-- : null" class="text-lg w-10 h-10 flex items-center justify-center hover:bg-zinc-100">-</button>
                                        <span class="font-medium">{{chosenCount}}</span>
                                        <button @click="canSelectQuantity && chosenCount < effectiveStockLevel ? chosenCount++ : null" class="text-lg w-10 h-10 flex items-center justify-center hover:bg-zinc-100">+</button>
                                    </div>
                                </div>
                                
                                <div class="buttons-container flex flex-col sm:flex-row gap-3 w-full border">
                                    <button 
                                        @click="buyNow" 
                                        class="duration-200 hover:bg-zinc-800 rounded-sm text-white text-center font-bold py-3 px-6 bg-black sm:flex-1"
                                        :disabled="isAddingToCart || !canSelectQuantity">
                                        {{ isAddingToCart ? 'Processing...' : 'Buy now' }}
                                    </button>
                                    <button 
                                        @click="addToCart" 
                                        class="duration-200 hover:bg-zinc-500 rounded-sm text-white text-center font-bold py-3 px-6 bg-zinc-400 sm:flex-1"
                                        :disabled="isAddingToCart || !canSelectQuantity">
                                        {{ isAddingToCart ? 'Adding...' : 'Add to basket' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `
});