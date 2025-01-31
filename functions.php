<?php
/**
 * Created by PhpStorm.
 * User: Schrauger
 * Date: 2015-11-05
 * Time: 3:46 PM
 */

/*
Plugin Name: Hide Post Types
Plugin URI: https://github.com/HashBangCrash/hide-post-types
Description: Hide or disable any post type, whether built-in or custom.
Version: 1.1.2
Author: Stephen Schrauger
Author URI: https://www.schrauger.com/
License: GPLv2 or later
*/

/**
 * Settings|config page for plugin
 */

namespace hide_post_types;


const option_group_name = 'hide-post-types-settings-group';

const section_builtin     = 'hide-post-types-builtin';
const section_custom      = 'hide-post-types-custom';

const page_title        = 'Hide Post Types Settings'; //
const menu_title        = 'Hide Post Types Settings';
const capability        = 'manage_options'; // user capability required to view the page
const page_slug         = 'hide-post-types-settings'; // unique page name, also called menu_slug

$posttypes = array();

register_activation_hook( __FILE__, __NAMESPACE__ .  '\\on_activation');
register_deactivation_hook( __FILE__, __NAMESPACE__ .  '\\on_deactivation');
register_uninstall_hook( __FILE__, __NAMESPACE__ .  '\\on_uninstall');


// Register the 'settings' page
add_action( 'admin_menu', __NAMESPACE__ . '\\add_plugin_page');
add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu_init');
add_action( 'admin_init', __NAMESPACE__ . '\\admin_init');
add_action( 'wp_loaded', __NAMESPACE__ . '\\page_init');

// Add a link from the plugin page to this plugin's settings page
add_filter( 'plugin_row_meta', __NAMESPACE__ . '\\plugin_action_links', 10, 2 );



/**
 * Function that is run when the plugin is activated via the plugins page
 */
function on_activation() {
    // stub
}

function on_deactivation() {
    // stub
}

function on_uninstall() {
    // stub
}

/**
 * Adds a link to this plugin's setting page directly on the WordPress plugin list page
 *
 * @param $links
 * @param $file
 *
 * @return array
 */
function plugin_action_links( $links, $file ) {
    if ( strpos( __FILE__, $file ) !== false ) {
        $links = array_merge(
            $links,
            array(
                'settings' => '<a href="' . admin_url( 'options-general.php?page=' . page_slug ) . '">' . __( 'Settings', page_slug ) . '</a>'
            )
        );
    }

    return $links;
}

/**
 * Tells WordPress about a new page and what function to call to create it
 */
function add_plugin_page() {
    // This page will be under "Settings" menu. add_options_page is merely a WP wrapper for add_submenu_page specifying the 'options-general' menu as parent
    add_options_page(
        page_title,
        menu_title,
        capability,
        page_slug,
        __NAMESPACE__ . '\\create_settings_page' // since we are putting settings on our own page, we also have to define how to print out the settings
    );
}

/**
 * Get all post types and create settings for them
 */
function admin_init() {

    add_settings_section();

    $posttypes = get_post_types('', 'objects');
    foreach ($posttypes as $post_object) {
        add_setting($post_object);
    }
}

/**
 * Adds a checkbox field for each post type
 *
 * @param string $post_object
 */
function add_setting( $post_object ) {
    // add setting, and register it

    $setting_id = unique_setting_id($post_object);
    add_settings_field(
        $setting_id,  // Unique ID used to identify the field
        $post_object->name,  // The label to the left of the option.
        __NAMESPACE__ . '\\settings_input_checkbox',   // The name of the function responsible for rendering the option interface
        page_slug,                         // The page on which this option will be displayed
        get_proper_section($post_object),         // The name of the section to which this field belongs
        array(   // The array of arguments to pass to the callback. These 4 are referenced in setting_input_checkbox.
            'id'      => $setting_id, // copy/paste id here
            'label'   => "Hide " . $post_object->label,
            'section' => get_proper_section($post_object),
            'value'   => get_database_settings_value( $post_object )
        )
    );
    register_setting(
        option_group_name,
        get_proper_section($post_object)
    //__NAMESPACE__ . '\\sanitize' // sanitize function
    );

}

/**
 * A unique identifier to save in the database. This uses the setting group plus the post slug.
 * @param $post_object
 *
 * @return string
 */
function unique_setting_id($post_object){
    return option_group_name . '-' . $post_object->name;
}

/**
 * Returns the correct section for a specific setting based on the post type slug.
 * Basically, if the $setting_slug is a built-in post type, it will return the "built-in"
 * section. Otherwise, it will return the "custom" section.
 *
 * @param $post_object
 *
 * @return string The section this preference should be sorted under.
 */
function get_proper_section($post_object){
    $posttypes_wp_builtin = array('post','page','attachment','revision','nav_menu_item'); // built-in (since WP 3.0)
    $posttypes_wp_builtin_exclude = array('revision','nav_menu_item'); // these two aren't normal types, and hiding does nothing.

    if (in_array($post_object->name, $posttypes_wp_builtin)) {
        if (in_array($post_object->name, $posttypes_wp_builtin_exclude)){
            return null; // a null section will cause the option to not show up in the preferences page.
        } else {
            return section_builtin;
        }
    } else {
        return section_custom;
    }
}

/**
 * Add the settings section for both built-in and custom post types (to distinguish between the two)
 * @return mixed
 */
function add_settings_section() {

    \add_settings_section(
        section_builtin,
        "Built-in WordPress Post Types", // start of section text shown to user
        function(){return "Caution";},
        page_slug
    );
    \add_settings_section(
        section_custom,
        "Custom Post Types",
        function(){return "No Caution";},
        page_slug
    );
}

/**
 * Creates the HTML code that is printed for each setting input
 *
 * @param $args
 */
function settings_input_checkbox( $args ) {
    // Note the ID and the name attribute of the element should match that of the ID in the call to add_settings_field.
    // Because we only call register_setting once, all the options are stored in an array in the database. So we
    // have to name our inputs with the name of an array. ex <input type="text" id=option_key name="option_group_name[option_key]" />.
    // WordPress will automatically serialize the inputs that are in this array form and store it under
    // the option_group_name field. Then get_option will automatically unserialize and grab the value already set and pass it in via the $args as the 'value' parameter.
    if ($args[ 'value' ]) {
        $checked = 'checked="checked"';
    } else {
        $checked = '';
    }

    $html = '';

    // create a hidden variable with the same name and no value. if the box is unchecked, the hidden value will be POSTed.
    // If the value is checked, only the checkbox will be sent.
    // This way, we don't have to uncheck everything server-side and then re-check the POSTed values.
    // This is particularly useful to prevent preferences from being deleted if a post type is removed from a theme's code.
    // If we just unchecked everything, old post types would lose their preferences; if they are later reactivated, the preference
    // would be gone. This way, the preference persists.
    $html .= '<input type="hidden"   id="' . $args[ 'id' ] . '" name="' . $args[ 'section' ] . '[' . $args[ 'id' ] . ']" value=""/>';
    $html .= '<input type="checkbox" id="' . $args[ 'id' ] . '" name="' . $args[ 'section' ] . '[' . $args[ 'id' ] . ']" value="' . ( $args[ 'id' ] ) . '" ' . $checked . '/>';

    // Here, we will take the first argument of the array and add it to a label next to the input
    $html .= '<label for="' . $args[ 'id' ] . '"> ' . $args[ 'label' ] . '</label>';
    echo $html;
}

/**
 * Grabs the database value for the $settings_id option. The value is stored in a serialized array in the database.
 * It returns the value after sanitizing it.
 *
 * @param $setting_object
 *
 * @return string|void
 */
function get_database_settings_value( $setting_object ) {
    $data = get_option( get_proper_section($setting_object) );

    return esc_attr( $data[ unique_setting_id($setting_object) ] ?? null);
}

/**
 * On every page load, disable the post types specified.
 */
function page_init() {
    $posttypes = get_post_types('', 'objects');
    foreach ($posttypes as $post_object) {
        // get setting
        // if set to hide, then hide
        if (get_database_settings_value( $post_object )) {
            unregister_post_type($post_object->name);
        }
    }
}

/**
 * On every page load, disable the post types specified.
 */
function admin_menu_init() {
    $posttypes = get_post_types('', 'objects');
    foreach ($posttypes as $post_object) {
        // get setting
        // if set to hide, then hide
        if (get_database_settings_value( $post_object )) {
            hide_post_menu($post_object->name);
        }
    }
}

/**
 * Removes the specified post type.
 * This function does not 'unset' the post type,
 * because then the preference page (which loads after this function in add_action order (unavoidable))
 * would not know about the post types. Thus leading to the inability
 * to restore the post type that is hidden.
 *
 * @param $post_type_slug
 *
 * @return bool
 */
function unregister_post_type( $post_type_slug ) {
    //$post_type = 'post';
    global $wp_post_types;
    if ( isset( $wp_post_types[ $post_type_slug ] ) ) {
//        $wp_post_types[ $post_type_slug ] = new \StdClass;
        $wp_post_types[ $post_type_slug ]->public = false;
        $wp_post_types[ $post_type_slug ]->exclude_from_search = true;
        $wp_post_types[ $post_type_slug ]->publicly_queryable = false;
        $wp_post_types[ $post_type_slug ]->show_in_nav_menus = false;
        $wp_post_types[ $post_type_slug ]->show_ui = false;
        $wp_post_types[ $post_type_slug ]->capabilities = new \StdClass;
        $wp_post_types[ $post_type_slug ]->capabilities->create_posts = false;
        $wp_post_types[ $post_type_slug ]->map_meta_cap = false;

        $wp_post_types[ $post_type_slug ]->show_ui = false;
        $wp_post_types[ $post_type_slug ]->show_in_nav_menus = false;
        $wp_post_types[ $post_type_slug ]->show_in_menu = false;
        $wp_post_types[ $post_type_slug ]->show_in_admin_bar = false;

        // built-in
        //if ($post_type_slug == 'post') {
        //remove_menu_page( 'edit.php' );
        //}
        //unset( $wp_post_types[ $post_type_slug ] );

        return true;
    }
    return false;
}

/**
 * Removes the "Add New..." post menu
 * @param $post_type_slug
 */
function hide_post_menu( $post_type_slug ) {
    global $submenu;
    //echo "Removing menu " . $post_type_slug . "<br />";
    //print_r($submenu);
    //unset($submenu['post-new.php?post_type=' . $post_type_slug][10]);
    if ($post_type_slug == 'post') {
        remove_menu_page( 'edit.php' );
    }
    if ($post_type_slug == 'attachment') {
        remove_menu_page( 'upload.php');
    }
    if ($post_type_slug == 'page') {
        remove_menu_page( 'edit.php?post_type=page');
    }
}

/**
 * Tells WordPress how to output the page
 */
function create_settings_page() {
    ?>
    <div class="wrap" >

        <h2 ><?php echo page_title ?></h2 >

        <form method="post" action="options.php" >
            <?php
            // This prints out all hidden setting fields
            settings_fields( option_group_name );
            do_settings_sections( page_slug );
            submit_button();
            ?>
        </form >
    </div >
    <?php
}
