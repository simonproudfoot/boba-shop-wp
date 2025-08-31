<?php get_header(); ?>
<main>
    <div class="max-w-4xl mx-auto py-24 md:py-52 px-6">
        <div class="gap-8">
            <h1 class="text-6xl font-heading mb-12 mx-auto text-center"><?php the_title(); ?></h1>

            <!-- Content section first on mobile -->
            <div class="mx-auto flex flex-col md:flex-row">
                <!-- Address section - last on mobile, first on desktop -->
                <div class="w-full md:w-1/3 order-2 md:order-1 mt-8 md:mt-0">
                    <h3 class="font-bold mb-4">Mail address</h3>
                    <address class="not-italic">
                        <span class="font-bold block">Creepy Hollow</span>
                        Studio 24 <br>
                        14 Feathers Place <br>
                        London <br>
                        SE10 9NE
                    </address>
                </div>

                <!-- Form section - first on mobile, second on desktop -->
                <div class="w-full md:w-2/3 order-1 md:order-2">
                    <p class="font-bold mb-4">Shove some contact details in here and let us know what's in that head of yours.</p>
                    <p class="font-bold"><?php echo the_content(); ?>

                    <form class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-10" action="">
                        <div class="flex flex-col gap-4">
                            <input type="text" placeholder="Pop your order reference here" class="w-full p-4 rounded-lg border-none appearence-none bg-gray-100 text-gray-500 font-bold placeholder:text-gray-400">
                            <input type="text" placeholder="Enter your name here...*" class="w-full p-4 rounded-lg border-none appearance-none bg-gray-100 text-gray-500 font-bold placeholder:text-gray-400" required>
                            <input type="email" placeholder="...and your email here*." class="w-full p-4 rounded-lg border-none appearance-none bg-gray-100 text-gray-500 font-bold placeholder:text-gray-400" required>
                        </div>
                        <div class="flex flex-col">
                            <textarea placeholder="Write your message here" class="w-full flex-grow p-4 rounded-lg bg-gray-100 border-none appearance-none text-gray-500 font-bold placeholder:text-gray-400" required></textarea>
                            <button type="submit" class="mt-4 p-2 bg-black text-white rounded-lg w-full font-bold">Send</button>
                        </div>
                    </form>

                    <div class="mt-6">
                        <div class="flex items-start">
                            <input type="checkbox" id="consent" class="mt-1 mr-3">
                            <label for="consent" class="text-sm text-light">Yes, I would like to receive email communication regarding your products, services and offers that might interest me.</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php get_footer(); ?>