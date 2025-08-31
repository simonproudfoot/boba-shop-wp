<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('-', true, ''); ?></title>
    <?php wp_head(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DynaPuff:wght@400..700&family=PT+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
</head>

<body style="background-image: url('<?php echo esc_url(get_theme_file_uri('assets/img/back.webp')); ?>'); background-size: 700px;  background-repeat: repeat;">
    <div id="app" class="max-w-full overflow-x-hidden ">

        <nav class=" absolute w-full z-50 top-0 start-0">
            <div class="max-w-8xl flex flex-wrap items-center justify-between mx-auto px-6 md:px-12 py-3">

                <div class="flex gap-x-4 md:order-2 space-x-3 md:space-x-0 rtl:space-x-reverse items-center">
                    <carticon inline-template></carticon>
                    <a href="https://boba-bao.co.uk/" class="text-yellow-900 hidden md:block uppercase bg-yellow-200 hover:bg-yellow-500 focus:ring-4 focus:outline-none focus:ring-yellow-200  rounded-sm text-xs px-4 py-2 text-center dark:bg-yellow-500 dark:hover:bg-yellow-600 font-body dark:focus:ring-yellow-300">boba-bao.co.uk
                    </a>
                    <!-- Leave page link icon -->


                </div>
                <a href="<?php echo site_url(); ?>" class="flex items-center space-x-3 rtl:space-x-reverse">

                    <img src="<?php echo esc_url(get_theme_file_uri('assets/img/small.png')); ?>" alt="Boba & Bao" class="h-8 md:h-[56px] w-auto" />
                </a>
            </div>
        </nav>