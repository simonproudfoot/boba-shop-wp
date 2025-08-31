<?php 

add_filter('acf/settings/load_json', function($paths) {
    $paths[] = get_stylesheet_directory() . '/develop/acf-json';
    return $paths;
});

add_filter('acf/fields/flexible_content/layout_thumbnail', function($thumbnail, $field, $layout) {
    if ($thumbnail && strpos($thumbnail, 'develop/') === 0) {
        $thumbnail = get_stylesheet_directory_uri() . '/' . $thumbnail;
    }
    return '';
}, 10, 3);


