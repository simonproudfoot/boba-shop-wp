<?php
/**
 * Shop Filters Component
 * Reusable component for filtering and sorting shop products
 */
?>

<button @click="showMobileFilter = !showMobileFilter" class="flex md:hidden gap-x-2 mb-3">
    <img src="<?php echo esc_url(get_theme_file_uri('assets/img/filter.svg')); ?>" alt="Filter" class="w-5" />
    <label class="text-black">Filter</label>
</button>

<div v-show="showFilters" id="shop-filters" class="md:flex pt-4 justify-between items-center fixed md:relative top-0 left-0 right-0 bg-white md:bg-transparent z-50 md:p-0 min-h-screen md:min-h-0">
    <!-- Category filters -->
    <div class="mb-4 md:mb-0">
        <div @click="showMobileFilter = false" class="md:hidden ml-auto block w-fit m-3">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-10 w-10 cursor-pointer">
                <path fill-rule="evenodd" d="M6.225 4.811a.75.75 0 011.06 0L12 9.525l4.715-4.714a.75.75 0 111.06 1.06L13.06 10.586l4.714 4.715a.75.75 0 01-1.06 1.06L12 11.646l-4.715 4.715a.75.75 0 01-1.06-1.06l4.714-4.715-4.714-4.714a.75.75 0 010-1.06z" clip-rule="evenodd" />
            </svg>
        </div>

        <!-- Category buttons -->
        <div class="flex flex-col md:flex-row flex-wrap gap-2 border-t border-b border-black md:border-none mx-4 md:mx-0 mt-4 py-4">
            <button @click="showMobileFilterCategorys = !showMobileFilterCategorys" class="flex items-center text-left md:hidden">
                <svg :class="showMobileFilterCategorys && '-rotate-180'" class="w-3" id="b" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 493.8 275.2">
                    <g id="c">
                        <path d="M442.5,13.2c.8,6.1,4.3,12.2,10.7,18.2,6.3,6.1,11.9,10.5,16.6,13.1,3.9,8.1,7.9,15.5,11.8,22.3s7.1,13.3,9.5,19.7,3.2,12.8,2.4,19.2c-.8,6.4-3.6,13-8.3,19.7-6.3,2-15.2,6.6-26.7,13.7-11.5,7.1-26.3,18-44.5,32.9-8.7,6.8-19,15.3-30.8,25.8-11.9,10.5-24.9,20.8-39.1,30.9-14.2,10.1-28.9,19.4-43.9,27.8s-29.6,14-43.9,16.7l-16.6,2c-15.8-4-31.2-10.3-46.2-18.7s-29.6-17.7-43.9-27.8c-14.2-10.1-27.3-20.4-39.1-30.9-11.9-10.5-22.1-19-30.8-25.8-18.2-14.8-33-25.8-44.5-32.9s-20.4-11.6-26.7-13.7c-4.8-6.7-7.5-13.3-8.3-19.7-.8-6.4,0-12.8,2.4-19.2s5.5-13,9.5-19.7,7.9-14.2,11.9-22.3c4.7-2.7,10.3-7.1,16.6-13.1,6.3-6.1,9.9-12.1,10.7-18.2,3.2,1.4,7.7.3,13.6-3,5.9-3.4,9.7-6.8,11.3-10.1,7.1,3.4,13.6,7.9,19.6,13.7,5.9,5.7,11.8,11.3,17.8,16.7,5.9,5.4,11.8,10.3,17.8,14.7,5.9,4.4,12,6.9,18.4,7.6,15.8,16.2,32.2,32,49.2,47.6,17,15.5,33,30.7,48,45.5,15-14.8,31-30,48-45.5,17-15.5,33.4-31.4,49.2-47.6,6.3-.7,12.4-3.2,18.4-7.6,5.9-4.4,11.8-9.3,17.8-14.7,5.9-5.4,11.8-11,17.8-16.7,5.9-5.7,12.4-10.3,19.6-13.7,1.6,3.4,5.3,6.8,11.3,10.1,5.9,3.4,10.5,4.4,13.6,3Z" />
                    </g>
                </svg>
                <span class="md:hidden text-xl font-bold px-3 font-heading text-left">Filters</span>
            </button>

            <?php
            $categories = get_terms(array(
                'taxonomy' => 'product_category',
                'hide_empty' => true,
            ));
            if (!empty($categories) && !is_wp_error($categories)) :
                foreach ($categories as $category) : ?>
                    <button
                        v-show="showMobileFilterCategorysMobile"
                        @click="toggleCategory({
                            slug: '<?php echo esc_attr($category->slug); ?>', 
                            name: '<?php echo esc_attr($category->name); ?>'
                        })"
                        :class="['px-5 md:px-3 py-1 justify-between rounded-md text-sm transition-colors flex items-center', 
                                isCategorySelected('<?php echo esc_attr($category->slug); ?>') ? 
                                '<?php echo $filter_style === 'yellow' ? 'bg-yellow text-yellowDark' : 'bg-gray-400 text-white'; ?> font-light' : 
                                '<?php echo $filter_style === 'yellow' ? 'bg-cream text-black border border-yellow' : 'bg-cream text-black border border-yellowDark'; ?>']">
                        <?php echo esc_html($category->name); ?>
                        <span
                            v-if="isCategorySelected('<?php echo esc_attr($category->slug); ?>')"
                            class="ml-2 text-white hover:text-red-600">
                            &times;
                        </span>
                    </button>
                <?php endforeach;
            else : ?>
                <p class="text-gray-500">No product categories found.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sorting options -->
    <div class="px-4 md:px-0">
        <button @click="showMobileFilterSort = !showMobileFilterSort" class="flex items-center text-left md:hidden">
            <svg :class="showMobileFilterSort && '-rotate-180'" class="w-3" id="b" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 493.8 275.2">
                <g id="c">
                    <path d="M442.5,13.2c.8,6.1,4.3,12.2,10.7,18.2,6.3,6.1,11.9,10.5,16.6,13.1,3.9,8.1,7.9,15.5,11.8,22.3s7.1,13.3,9.5,19.7,3.2,12.8,2.4,19.2c-.8,6.4-3.6,13-8.3,19.7-6.3,2-15.2,6.6-26.7,13.7-11.5,7.1-26.3,18-44.5,32.9-8.7,6.8-19,15.3-30.8,25.8-11.9,10.5-24.9,20.8-39.1,30.9-14.2,10.1-28.9,19.4-43.9,27.8s-29.6,14-43.9,16.7l-16.6,2c-15.8-4-31.2-10.3-46.2-18.7s-29.6-17.7-43.9-27.8c-14.2-10.1-27.3-20.4-39.1-30.9-11.9-10.5-22.1-19-30.8-25.8-18.2-14.8-33-25.8-44.5-32.9s-20.4-11.6-26.7-13.7c-4.8-6.7-7.5-13.3-8.3-19.7-.8-6.4,0-12.8,2.4-19.2s5.5-13,9.5-19.7,7.9-14.2,11.9-22.3c4.7-2.7,10.3-7.1,16.6-13.1,6.3-6.1,9.9-12.1,10.7-18.2,3.2,1.4,7.7.3,13.6-3,5.9-3.4,9.7-6.8,11.3-10.1,7.1,3.4,13.6,7.9,19.6,13.7,5.9,5.7,11.8,11.3,17.8,16.7,5.9,5.4,11.8,10.3,17.8,14.7,5.9,4.4,12,6.9,18.4,7.6,15.8,16.2,32.2,32,49.2,47.6,17,15.5,33,30.7,48,45.5,15-14.8,31-30,48-45.5,17-15.5,33.4-31.4,49.2-47.6,6.3-.7,12.4-3.2,18.4-7.6,5.9-4.4,11.8-9.3,17.8-14.7,5.9-5.4,11.8-11,17.8-16.7,5.9-5.7,12.4-10.3,19.6-13.7,1.6,3.4,5.3,6.8,11.3,10.1,5.9,3.4,10.5,4.4,13.6,3Z" />
                </g>
            </svg>
            <span class="md:hidden text-xl font-bold px-3 font-heading text-left">Sort</span>
        </button>
        
        <!-- Sort buttons section with same styling as categories -->
        <div v-show="showMobileFilterSortMobile" class="flex flex-col md:flex-row flex-wrap gap-2 border-t border-b border-black md:border-none md:mx-0 mt-4 py-4">
            <button
                @click="setSortOrder('newest')"
                :class="['px-5 md:px-3 py-1 justify-between rounded-md text-sm transition-colors flex items-center', 
                        sortOrder === 'newest' ? 
                        '<?php echo $filter_style === 'yellow' ? 'bg-yellow text-yellowDark' : 'bg-gray-400 text-white'; ?> font-light' : 
                        '<?php echo $filter_style === 'yellow' ? 'bg-cream text-black border border-yellowDark' : 'bg-cream text-black border border-yellowDark'; ?>']">
                Newest
            </button>
            <button
                @click="setSortOrder('oldest')"
                :class="['px-5 md:px-3 py-1 justify-between rounded-md text-sm transition-colors flex items-center', 
                        sortOrder === 'oldest' ? 
                        '<?php echo $filter_style === 'yellow' ? 'bg-yellow text-yellowDark' : 'bg-gray-400 text-white'; ?> font-light' : 
                        '<?php echo $filter_style === 'yellow' ? 'bg-cream text-black border border-yellow' : 'bg-cream text-black border border-yellow'; ?>']">
                Oldest
            </button>
        </div>
    </div>
</div>
