const inquirer = require('inquirer');
const fs = require('fs').promises;
const path = require('path');

const POST_TYPES_FILE = path.join(__dirname, 'develop', 'functions', 'post-types.php');

async function readPostTypesFile() {
    return await fs.readFile(POST_TYPES_FILE, 'utf8');
}

async function writePostTypesFile(content) {
    await fs.writeFile(POST_TYPES_FILE, content, 'utf8');
}

function getExistingPostTypes(content) {
    const regex = /function create_(\w+)_cpt\(\)/g;
    const matches = [...content.matchAll(regex)];
    return matches.map(match => match[1]);
}

async function promptForPostTypeDetails(existingPostType = null) {
    const questions = [
        {
            type: 'input',
            name: 'name',
            message: 'Enter the post type name (lowercase, no spaces):',
            default: existingPostType,
            validate: input => /^[a-z0-9_]+$/.test(input) || 'Please enter a valid post type name',
        },
        {
            type: 'input',
            name: 'label',
            message: 'Enter the label for the post type:',
            default: existingPostType ? existingPostType.charAt(0).toUpperCase() + existingPostType.slice(1) : '',
        },
        {
            type: 'input',
            name: 'description',
            message: 'Enter a description for the post type:',
        },
        {
            type: 'checkbox',
            name: 'supports',
            message: 'Select the features this post type supports:',
            choices: ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author', 'post-formats'],
            default: ['title', 'editor', 'excerpt', 'thumbnail'],
        },
        {
            type: 'confirm',
            name: 'public',
            message: 'Should this post type be public?',
            default: true,
        },
        {
            type: 'confirm',
            name: 'has_archive',
            message: 'Should this post type have an archive page?',
            default: true,
        },
        {
            type: 'input',
            name: 'menu_icon',
            message: 'Enter a Dashicons class for the menu icon (e.g., dashicons-admin-post):',
            default: 'dashicons-admin-post',
        },
    ];

    return inquirer.prompt(questions);
}

function generatePostTypeCode(details) {
    return `
// Register Custom Post Type ${details.label}
function create_${details.name}_cpt() {
    $labels = array(
        'name'                  => _x( '${details.label}s', 'Post Type General Name', 'textdomain' ),
        'singular_name'         => _x( '${details.label}', 'Post Type Singular Name', 'textdomain' ),
        'menu_name'             => _x( '${details.label}s', 'Admin Menu text', 'textdomain' ),
        'name_admin_bar'        => _x( '${details.label}', 'Add New on Toolbar', 'textdomain' ),
        'archives'              => __( '${details.label} Archives', 'textdomain' ),
        'attributes'            => __( '${details.label} Attributes', 'textdomain' ),
        'parent_item_colon'     => __( 'Parent ${details.label}:', 'textdomain' ),
        'all_items'             => __( 'All ${details.label}s', 'textdomain' ),
        'add_new_item'          => __( 'Add New ${details.label}', 'textdomain' ),
        'add_new'               => __( 'Add New', 'textdomain' ),
        'new_item'              => __( 'New ${details.label}', 'textdomain' ),
        'edit_item'             => __( 'Edit ${details.label}', 'textdomain' ),
        'update_item'           => __( 'Update ${details.label}', 'textdomain' ),
        'view_item'             => __( 'View ${details.label}', 'textdomain' ),
        'view_items'            => __( 'View ${details.label}s', 'textdomain' ),
        'search_items'          => __( 'Search ${details.label}', 'textdomain' ),
        'not_found'             => __( 'Not found', 'textdomain' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'textdomain' ),
        'featured_image'        => __( 'Featured Image', 'textdomain' ),
        'set_featured_image'    => __( 'Set featured image', 'textdomain' ),
        'remove_featured_image' => __( 'Remove featured image', 'textdomain' ),
        'use_featured_image'    => __( 'Use as featured image', 'textdomain' ),
        'insert_into_item'      => __( 'Insert into ${details.label}', 'textdomain' ),
        'uploaded_to_this_item' => __( 'Uploaded to this ${details.label}', 'textdomain' ),
        'items_list'            => __( '${details.label}s list', 'textdomain' ),
        'items_list_navigation' => __( '${details.label}s list navigation', 'textdomain' ),
        'filter_items_list'     => __( 'Filter ${details.label}s list', 'textdomain' ),
    );
    $args = array(
        'label'                 => __( '${details.label}', 'textdomain' ),
        'description'           => __( '${details.description}', 'textdomain' ),
        'labels'                => $labels,
        'menu_icon'             => '${details.menu_icon}',
        'supports'              => array(${details.supports.map(s => `'${s}'`).join(', ')}),
        'taxonomies'            => array(),
        'public'                => ${details.public},
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => ${details.has_archive},
        'hierarchical'          => false,
        'exclude_from_search'   => false,
        'show_in_rest'          => true,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    );
    register_post_type( '${details.name}', $args );
}
add_action( 'init', 'create_${details.name}_cpt', 0 );
`;
}

async function addOrEditPostType() {
    const content = await readPostTypesFile();
    const existingPostTypes = getExistingPostTypes(content);
    
    const { action } = await inquirer.prompt({
        type: 'list',
        name: 'action',
        message: 'What would you like to do?',
        choices: ['Add new post type', 'Edit existing post type', 'Exit'],
    });

    if (action === 'Exit') {
        console.log('Goodbye!');
        return false;
    }

    let postTypeDetails;
    let existingPostType = null;

    if (action === 'Edit existing post type') {
        const { postType } = await inquirer.prompt({
            type: 'list',
            name: 'postType',
            message: 'Select a post type to edit:',
            choices: existingPostTypes,
        });
        existingPostType = postType;
    }

    postTypeDetails = await promptForPostTypeDetails(existingPostType);

    const newCode = generatePostTypeCode(postTypeDetails);
    let updatedContent = content;

    if (existingPostType) {
        const regex = new RegExp(`function create_${existingPostType}_cpt\\(\\)[\\s\\S]*?add_action\\([^;]+;`, 'g');
        updatedContent = updatedContent.replace(regex, newCode.trim());
    } else {
        updatedContent += '\n' + newCode;
    }

    await writePostTypesFile(updatedContent);
    console.log(`Post type '${postTypeDetails.name}' has been ${existingPostType ? 'updated' : 'added'}.`);
    return true;
}

async function main() {
    let shouldContinue = true;
    while (shouldContinue) {
        shouldContinue = await addOrEditPostType();
        if (shouldContinue) {
            const { continue: userWantsToContinue } = await inquirer.prompt({
                type: 'confirm',
                name: 'continue',
                message: 'Do you want to add or edit another post type?',
                default: false,
            });
            shouldContinue = userWantsToContinue;
        }
    }
}

main().catch(console.error);