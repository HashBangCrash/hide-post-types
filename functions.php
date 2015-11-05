<?php
/**
 * Created by PhpStorm.
 * User: Schrauger
 * Date: 2015-11-05
 * Time: 3:46 PM
 */

/*
Plugin Name: Hide Post Types
Plugin URI: https://github.com/schrauger/hide-post-types
Description: Hide or disable any post type, whether built-in or custom.
Version: 0.1
Author: Stephen Schrauger
Author URI: https://www.schrauger.com/
License: GPLv2 or later
*/

/**
 * Settings|config page for plugin
 */
class hide_post_types_settings {

	const option_group_name = 'hide-post-types-settings-group';
	const page_title        = 'Hide Post Types Settings'; //
	const menu_title        = 'Hide Post Types Settings';
	const capability        = 'manage_options'; // user capability required to view the page
	const page_slug         = 'hide-post-types-settings'; // unique page name, also called menu_slug

	/*	const javascript_handle = 'ucf_com_shortcodes_js'; // just a unique handle for WordPress
		const javascript_var    = 'ucf_com_shortcodes_tinymce'; // global javascript variable that holds tinymce menu structure*/

	private $posttypes_wp_builtin = array('post','page','attachment','revision','nav_menu_item'); // built-in (since WP 3.0)

	public function __construct() {
		register_activation_hook( __FILE__, array(
			$this,
			'on_activation'
		) ); //call the 'on_activation' function when plugin is first activated
		register_deactivation_hook( __FILE__, array(
			$this,
			'on_deactivation'
		) ); //call the 'on_deactivation' function when plugin is deactivated
		register_uninstall_hook( __FILE__, array(
			$this,
			'on_uninstall'
		) ); //call the 'uninstall' function when plugin is uninstalled completely

		// Register the 'settings' page
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'init', array( $this, 'page_init' ) );

		// Add a link from the plugin page to this plugin's settings page
		add_filter( 'plugin_row_meta', array( $this, 'plugin_action_links' ), 10, 2 );

	}

	/**
	 * Function that is run when the plugin is activated via the plugins page
	 */
	public function on_activation() {
		// stub
	}

	public function on_deactivation() {
		// stub
	}

	public function on_uninstall() {
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
	public function plugin_action_links( $links, $file ) {
		if ( strpos( __FILE__, $file ) !== false ) {
			$links = array_merge(
				$links,
				array(
					'settings' => '<a href="' . admin_url( 'options-general.php?page=' . self::page_slug ) . '">' . __( 'Settings', self::page_slug ) . '</a>'
				)
			);
		}

		return $links;
	}

	/**
	 * Tells WordPress about a new page and what function to call to create it
	 */
	public function add_plugin_page() {
		// This page will be under "Settings" menu. add_options_page is merely a WP wrapper for add_submenu_page specifying the 'options-general' menu as parent
		add_options_page(
			self::page_title,
			self::menu_title,
			self::capability,
			self::page_slug,
			array(
				$this,
				'create_settings_page'
			) // since we are putting settings on our own page, we also have to define how to print out the settings
		);
	}

	/**
	 * Get all post types and create settings for them
	 */
	public function admin_init() {

		$this->posttypes = ;
		foreach ($this->posttypes as $posttype) {
			$this->add_setting(
				$posttype->slug,// ID used to identify the field throughout the theme
				'PlayerID (deprecated)',                           // The label to the left of the option interface element
				'PlayerID as defined by your Brightcove account. This has been replaced by the playerKey field.'
			);
		}
	}

	/**
	 * Adds an input field to save settings. The $setting_id can be referenced
	 * by the shortcode replacement function. Generally, this is used to set
	 * defaults for optional fields the user can define.
	 *
	 * @param string $setting_id          This must be unique. Prepend with shortcode name.
	 * @param string $setting_description Optional - A description of the input.
	 * @param string $setting_label       Optional - A text label (<label> element) linked to the input.
	 */
	public function add_setting( $setting_id, $setting_description = "", $setting_label = "" ) {
		add_settings_field(
			$setting_id,                      // ID used to identify the field throughout the theme
			$setting_description,                           // The label to the left of the option interface element
			array(
				$this,
				'settings_input_text'
			),   // The name of the function responsible for rendering the option interface
			$this->get_page_slug(),                         // The page on which this option will be displayed
			$this->get_section_name(),         // The name of the section to which this field belongs
			array(   // The array of arguments to pass to the callback.
			         'id'      => $setting_id, // copy/paste id here
			         'label'   => $setting_label,
			         'section' => $this->get_section_name(),
			         'value'   => $this->get_database_settings_value( $setting_id )
			)
		);

	}

	/**
	 * Add the settings section for this shortcode to the plugin settings page.
	 * @return mixed
	 */
	public function add_settings_section() {

		register_setting(
			$this->option_group_name,
			$this->get_option_database_key()
		//array( $this, 'sanitize' ) // sanitize function
		);

		add_settings_section(
			$this->get_section_name(),
			$this->get_section_title(), // start of section text shown to user
			array( $this, 'print_section_description' ),
			$this->get_page_slug()
		);
	}

	/**
	 * Adds the appropriate settings for the plugin settings page.
	 */
	public function init_shortcode_settings() {
		$this->add_settings();
		$this->add_settings_section();

	}

	/**
	 * Creates the HTML code that is printed for each input on the UCF COM Shortcodes options page under this
	 * shortcode's section.
	 *
	 * @param $args
	 */
	public function settings_input_text( $args ) {
		// Note the ID and the name attribute of the element should match that of the ID in the call to add_settings_field.
		// Because we only call register_setting once, all the options are stored in an array in the database. So we
		// have to name our inputs with the name of an array. ex <input type="text" id=option_key name="option_group_name[option_key]" />.
		// WordPress will automatically serialize the inputs that are in this array form and store it under
		// the option_group_name field. Then get_option will automatically unserialize and grab the value already set and pass it in via the $args as the 'value' parameter.
		$html = '<input type="text" id="' . $args[ 'id' ] . '" name="' . $args[ 'section' ] . '[' . $args[ 'id' ] . ']" value="' . ( $args[ 'value' ] ) . '"/>';

		// Here, we will take the first argument of the array and add it to a label next to the input
		$html .= '<label for="' . $args[ 'id' ] . '"> ' . $args[ 'label' ] . '</label>';
		echo $html;
	}

	/**
	 * On every page load, disable the post types specified.
	 */
	public function page_init() {
		$this->posttypes_custom = ;
		foreach (  ) {
			// get setting
			// if set to hide, then hide
			if (/* is hidden */) {
				$this->unregister_post_type($posttype->);
			}
		}
	}

	/**
	 * Removes the specified post type
	 */
	function unregister_post_type( $post_type_slug ) {
		//$post_type = 'post';
		global $wp_post_types;
		if ( isset( $wp_post_types[ $post_type_slug ] ) ) {
			unset( $wp_post_types[ $post_type_slug ] );
			return true;
		}
		return false;
	}

	/**
	 * Tells WordPress how to output the page
	 */
	public function create_settings_page() {
		?>
		<div class="wrap" >

			<h2 ><?php echo self::page_title ?></h2 >

			<form method="post" action="options.php" >
				<?php
				// This prints out all hidden setting fields
				settings_fields( self::option_group_name );
				do_settings_sections( self::page_slug );
				submit_button();
				?>
			</form >
		</div >
	<?php
	}




}

new hide_post_types_settings();