<?php
/**
 * class-th-settings-api.php
 *
 * This file contains the TH_Settings_API class.
 *
 * @author Thijs Huijssoon <thuijssoon@googlemail.com>
 * @link http://www.github.com/thuijssoon/th-framework/
 * @example th-settings-api-usage.php Sample declaration and usage of TH_Settings_API class.
 * @version 0.1
 *
 * @license GNU General Public License v3.0
 */

// don't allow this file to be loaded directly
if ( !function_exists( 'is_admin' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

// Only create a class that doesn't exist...
if ( !class_exists( "TH_Settings_API" ) ) {

	/**
	 * This is a wrapper for the WordPress Settings API. Its aim is to provide a standards
	 * compliant, best practice implementation for creating theme and plugin options pages
	 * that adhere to the WordPress look & feel.
	 *
	 * @package TH Framewrok
	 * @subpackage options
	 */
	class TH_Settings_API {
		/**
		 * true if this file is called from within a plugin context,
		 * false if called from within a theme context. Used to determine where
		 * to add the options page in the menu and where to find the settings
		 * definitions file.
		 *
		 * @var bool
		 */
		var $is_plugin;

		/**
		 * contains the the settings definitions array.
		 *
		 * @var array
		 */
		var $settings_definitions;

		/**
		 * contains the the settings definitions meta array.
		 *
		 * @var array
		 */
		var $settings_definitions_meta;

		/**
		 * contains the plugin base name if called from a plugin.
		 *
		 * @var string
		 */
		var $plugin_basename;

		/**
		 * contains the path to the settings definitions file.
		 *
		 * @var string
		 */
		var $settings_definitions_file_path;

		/**
		 * the key used the to store the options under.
		 *
		 * @var string
		 */
		var $option_key;

		/**
		 * array holding the sanitize setting values.
		 *
		 * @var array
		 */
		var $sanitized_settings;

		/**
		 * the title of the settings page.
		 *
		 * @var string
		 */
		var $page_title;

		/**
		 * the name of the settings page.
		 *
		 * @var string
		 */
		var $page_name;

		/**
		 * the menu location of the settings page, either theme or plugin.
		 *
		 * @var string
		 */
		var $menu_location;

		/**
		 * the admin page slug of the settings page. Used in display_settings_page_tabs.
		 *
		 * @var string
		 */
		var $admin_page;

		/**
		 * contains the descriptions for each settings section.
		 *
		 * @var array
		 */
		var $settings_section_description;

		/**
		 * contains possible settings errors.
		 *
		 * @var array
		 */
		var $settings_errors;


		public function __construct( $settings_definitions_file_path, $args = array() ) {
			// run script only in admin area:
			if ( !is_admin() ) return;

			// initialize arguments:
			$this->settings_definitions_file_path = $settings_definitions_file_path;
			$this->init_args( $args );
			$this->get_sanitized_settings();

			/* hooks and filters */

			// add settings page to menu:
			add_action( 'admin_menu', array( &$this, 'add_settings_page' ) );
			// initialize the Settings API
			add_action( 'admin_init', array( &$this, 'settings_api_init' ) );
			// add the admin notices:
			add_action( 'admin_notices', array( &$this, 'show_admin_notices' ) );
			// add settings link to plugin pag:
			if ( $this->is_plugin ) {
				add_filter( 'plugin_action_links', array( &$this, 'add_plugin_action_links' ), 10, 2 );
			}
		} // end of constructor function


		/**
		 * Add the settings page link to the relevant admin menu. The class detects whether it
		 * is loaded as part of a plugin or a theme and adds the page using add_options_page or
		 * add_theme_page respectively.
		 *
		 * @since 0.1
		 * @link http://codex.wordpress.org/Function_Reference/add_options_page Codex Reference: add_options_page()
		 * @link http://codex.wordpress.org/Function_Reference/add_theme_page Codex Reference: add_theme_page()
		 */
		public function add_settings_page() {
			$this->admin_page = null;

			switch ( $this->menu_location ) {
				case 'menu':
				$this->admin_page = add_menu_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);								
				break;
				case 'dashboard':
				$this->admin_page = add_dashboard_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);								
				break;
				case 'posts':
				$this->admin_page = add_posts_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);								
				break;
				case 'media':
				$this->admin_page = add_media_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);								
				break;
				case 'links':
				$this->admin_page = add_links_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);								
				break;
				case 'pages':
				$this->admin_page = add_pages_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);								
				break;
				case 'comments':
				$this->admin_page = add_comments_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);								
				break;
				case 'theme':
				$this->admin_page = add_theme_page(
					$this->page_title,
					$this->page_title,
					'edit_theme_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);
				break;
				case 'plugins':
				$this->admin_page = add_plugins_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);				
				break;
				case 'users':
				$this->admin_page = add_users_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);				
				break;
				case 'management':
				$this->admin_page = add_management_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);				
				break;
				case 'options':
				$this->admin_page = add_options_page(
					$this->page_title,
					$this->page_title,
					'manage_options',
					$this->page_name,
					array( &$this, 'display_settings_page' )
				);				
				break;
			}

			add_action( 'admin_print_scripts-' . $this->admin_page, array( &$this, 'add_scripts' ) );
			add_action( 'admin_print_styles-' . $this->admin_page, array( &$this, 'add_styles' ) );
		} // end of function add_settings_page


		/**
		 * Callback for add_options_page() or add_theme_page()
		 *
		 * Generic callback to echo out the setting page markup.
		 *
		 * @since 0.1
		 */
		public function display_settings_page() {
			echo '<div class="wrap">';
			$cur_tab = $this->display_settings_page_tabs();
			$tabs = $this->get_settings_definitions();

			//   settings_errors();

			echo '<form action="options.php" method="post">';
			settings_fields( $this->page_name );
			foreach ( $tabs as $tabname => $tabvalue ) {
				if ( $tabname === $cur_tab ) {
					echo "<div id='$tabname-container' class='tab-container tab-container-active'>";
				} else {
					echo "<div id='$tabname-container' class='tab-container'>";
				}
				$settings_section = 'th_' . $this->page_name . '_' . $tabname . '_tab';
				do_settings_sections( $settings_section );
				echo '<p class="submit">';
				echo '<input name="' . $this->option_key . '[submit-' . $tabname . ']" type="submit" class="button-primary" value="' . esc_attr__( 'Save Settings', 'th' ) . '" />';
				echo '<input name="' . $this->option_key . '[reset-' . $tabname . ']" type="submit" class="button-secondary" value="' . esc_attr__( 'Reset to Defaults', 'th' ) . '" />';
				echo "</div>";
			}
			echo '</p></form></div>';
		} // end of function display_settings_page


		function show_admin_notices() {
			// collect setting errors/notices: //http://codex.wordpress.org/Function_Reference/get_settings_errors
			$this->settings_errors = get_settings_errors();

			//display admin message only for the admin to see, only on our settings page and only when setting errors/notices are returned!
			if ( current_user_can ( 'manage_options' ) && !empty( $this->settings_errors ) ) {
				// have our settings succesfully been updated?
				if ( $this->settings_errors[0]['code'] == 'settings_updated' && isset( $_GET['settings-updated'] ) ) {
					echo '<div class="updated settings-error" id="setting-error-settings_updated">';
					echo '<p><strong>' . $this->settings_errors[0]['message'] . '</strong></p></div>';
					// have errors been found?
				}else {
					// there maybe more than one so run a foreach loop.
					echo '<div class="error settings-error" id="setting-error">';
					foreach ( $this->settings_errors as $set_error ) {
						// set the title attribute to match the error "setting title" - need this in js file
						echo '<p class="setting-error-message" title="' . $this->option_key . '_' . $set_error['setting'] . '"><strong>' . $set_error['message'] . '</strong></p>';
					}
					echo '</div>';
				}
			}
		} // end of function show_amdin_notices


		function add_plugin_action_links( $links, $file ) {
			if ( $file == $this->plugin_basename ) {
				// The "page" query string value must be equal to the slug
				// of the Settings admin page we defined earlier, which in
				// this case equals "myplugin-settings".
				$scheme = is_ssl() ? 'https' : 'http';
				$link = admin_url( 'options-general.php?page=' . $this->page_name, $scheme );
				$settings_link = '<a href="' . $link . '">Settings</a>';
				array_unshift( $links, $settings_link );
			}
			return $links;
		} // end of function add_plugin_action_links

		/**
		 * Callback for admin_print_scripts hook
		 *
		 * Generic callback to enqueue scripts for the setting page.
		 *
		 * @since 0.1
		 */
		public function add_scripts() {
			wp_enqueue_script( 'th-settings-api', plugins_url( 'bin/js/th-settings-api.js' , dirname(__FILE__) ), array( 'jquery' ), '1', true );
			wp_localize_script( 'th-settings-api', 'th_settings', array(
					'option_key' => $this->option_key
				) );
		} // end of function add_scripts


		/**
		 * Callback for admin_print_styles hook
		 *
		 * Generic callback to enqueue styles for the setting page.
		 *
		 * @since 0.1
		 */
		public function add_styles() {
			wp_enqueue_style( 'th-settings-api', plugins_url( 'bin/css/th-settings-api.css' , dirname(__FILE__) ) );
		} // end of function add_styles


		/**
		 * Parse the settings definition and register the setting, add the setting sections
		 * and fields.
		 *
		 * @since 0.1
		 * @link http://codex.wordpress.org/Function_Reference/register_setting Codex Reference: register_setting()
		 * @link http://codex.wordpress.org/Function_Reference/add_settings_section Codex Reference: add_settings_section()
		 * @link http://codex.wordpress.org/Function_Reference/add_settings_field Codex Reference: add_settings_field()
		 * @uses load_settings()
		 */
		public function settings_api_init() {
			/**
			 * Register Settings
			 *
			 * @link http://codex.wordpress.org/Function_Reference/register_setting Codex Reference: register_setting()
			 *
			 * @param string  $option_group      Unique Settings API identifier; passed to settings_fields() call
			 * @param string  $option_name       Name of the wp_options database table entry
			 * @param callback $sanitize_callback Name of the callback function in which user input data are sanitized
			 */
			register_setting(
				// $option_group
				$this->page_name,
				// $option_name
				$this->option_key,
				// $sanitize_callback
				array ( &$this, 'sanitize_settings' )
			);

			$set_def = $this->get_settings_definitions();
			foreach ( $set_def as $tab_name => $tab_def ) {
				$tab_sections = $tab_def['sections'];
				foreach ( $tab_sections as $section_name => $section_def ) {
					$section_title = $section_def['title'];
					/**
					 * Call add_settings_section() for each section
					 *
					 * Loop through each Theme Settings page tab, and add
					 * a new section to the Theme Settings page for each
					 * section specified for each tab.
					 *
					 * @link http://codex.wordpress.org/Function_Reference/add_settings_section Codex Reference: add_settings_section()
					 *
					 * @param string  $sectionid Unique Settings API identifier; passed to add_settings_field() call
					 * @param string  $title     Title of the Settings page section
					 * @param callback $callback  Name of the callback function in which section text is output
					 * @param string  $pageid    Name of the Settings page to which to add the section; passed to do_settings_sections()
					 */
					add_settings_section(
						// $sectionid
						'th_' . $section_name . '_section',
						// $title
						$section_title,
						// $callback
						array ( &$this, 'display_settings_section' ),
						// $pageid
						'th_' . $this->page_name . '_' . $tab_name . '_tab'
					);

					$this->settings_section_description['th_' . $section_name . '_section'] = $section_def['description'];

					foreach ( $section_def['settings'] as $setting_name => $setting_def ) {
						// set the field id and name:
						$setting_def['id'] = $this->option_key . '_' . $setting_name;
						$setting_def['field_name'] = $this->option_key . '[' . $setting_name . ']';
						$setting_def['name'] = $setting_name;
						// set the label_for value:
						if ( 'radio' != $setting_def['type'] && 'checkbox' != $setting_def['type']
							&& 'multicheck' != $setting_def['type'] && 'wysiwyg' != $setting_def['type']
							&& 'upload' != $setting_def['type'] ) {
							$setting_def['label_for'] = $setting_def['id'];
						}
						// set the class:
						if ( !isset( $setting_def['class'] ) ) {
							if ( isset( $setting_def['sanitize'] ) && 'numeric' == $setting_def['sanitize'] )
								$setting_def['class'] = 'small-text';
							elseif ( 'text' == $setting_def['type'] || 'password' == $setting_def['type'] )
								$setting_def['class'] = 'regular-text';
							elseif ( 'textarea' == $setting_def['type'] )
								$setting_def['class'] = 'large-text';
							elseif ( 'color' == $setting_def['type'] )
								$setting_def['class'] = 'code color';
							elseif ( 'upload' == $setting_def['type'] )
								$setting_def['class'] = 'regular-text code upload';
							else
								$setting_def['class'] = '';

							if ( ( 'text' == $setting_def['type'] || 'textarea' == $setting_def['type'] )
								&& isset( $setting_def['sanitize'] ) && 'html' == $setting_def['sanitize'] )
								$setting_def['class'] .= ' code';

						}


						/**
						 * Call add_settings_field() for each Setting Field
						 *
						 * @link http://codex.wordpress.org/Function_Reference/add_settings_field Codex Reference: add_settings_field()
						 *
						 * @param string  $settingid Unique Settings API identifier; passed to the callback function
						 * @param string  $title     Title of the setting field
						 * @param callback $callback  Name of the callback function in which setting field markup is output
						 * @param string  $pageid    Name of the Settings page to which to add the setting field; passed from add_settings_section()
						 * @param string  $sectionid ID of the Settings page section to which to add the setting field; passed from add_settings_section()
						 * @param array   $args      Array of arguments to pass to the callback function
						 */
						add_settings_field(
							// $settingid
							'th_setting_' . $setting_name,
							// $title
							$setting_def['title'],
							// $callback
							array ( &$this, 'display_settings_field' ),
							// $pageid
							'th_' . $this->page_name . '_' . $tab_name . '_tab',
							// $sectionid
							'th_' . $section_name . '_section',
							// $args
							$setting_def
						);
					} // end of foreach ( $section_def['settings'] as $setting_name => $setting_def )
				} // end of foreach ( $tab_sections as $section_name => $section_def )
			} // end of foreach ( $set_def as $tab_name => $tab_def )
		} // end of function settings_api_init


		/**
		 * Callback for add_settings_section()
		 *
		 * Generic callback to echo out the section text for each Plugin settings section.
		 *
		 * @since 0.1
		 * @param array   $section Array passed from add_settings_section()
		 */
		public function display_settings_section( $section ) {
			echo '<p>' . wp_kses_post( $this->settings_section_description[$section['id']] ) . '</p>';
		} // end of function display_settings_section


		/**
		 * Callback for add_settings_field()
		 *
		 * Generic callback to echo out the setting markup for each Plugin settings field.
		 *
		 * @since 0.1
		 * @param array   $field Array passed from add_settings_field()
		 */
		public function display_settings_field( $field ) {
			$sanitized = $this->get_sanitized_settings();
			$class_name = empty( $field['class'] ) ? '' : "class='" . esc_attr( $field['class'] ) . "' ";

			switch ( $field['type'] ) {
			case 'checkbox':
				echo "<fieldset><legend class='screen-reader-text'><span>" . esc_html( $field['title'] ) . "</span></legend>";
				echo "<label for='" . esc_attr( $field['id'] ) . "'><input type='checkbox' name='" . esc_attr( $field['field_name'] ) . "' id='" . esc_attr( $field['id'] ) . "' " . $class_name . " " . checked( $sanitized[$field['name']], true, false ) . " /> " . $field['description'] . "</label>";
				echo "</fieldset>";
				break;
			case 'multicheck':
				$valid_options = $this->get_valid_options($field);
				echo "<fieldset><legend class='screen-reader-text'><span>" . esc_html( $field['title'] ) . "</span></legend>";
				$count = 0;
				foreach ( $valid_options as $name => $value ) {
					if ( $count > 0 )
						echo "<br />";
					echo "<label for='" . esc_attr( $field['id'] ) . "-$count'><input type='checkbox' name='" . esc_attr( $field['field_name'] ) . "[]' id='" . esc_attr( $field['id'] ) . "-$count' " . $class_name . " " . checked( $sanitized[$field['name']][$name], true, false ) . " value='" . esc_attr( $name ) ."' /> " . esc_html( $value ) . "</label>";
					$count += 1;
				}
				echo "</fieldset>";
				$description = $field['description'];
				if ( !empty( $description ) )
					echo " <span class='description'>" . $description . "</span>";
				break;
			case 'radio':
				$valid_options = $this->get_valid_options($field);
				echo "<fieldset><legend class='screen-reader-text'><span>" . esc_html( $field['title'] ) . "</span></legend>";
				$count = 0;
				foreach ( $valid_options as $name => $value ) {
					if ( $count > 0 )
						echo "<br />";
					echo "<input type='radio' name='" . esc_attr( $field['field_name'] ) . "' id='" . esc_attr( $field['id'] ) . "-$count' " . $class_name . " " . checked( $name, $sanitized[$field['name']], false ) . " value='" . esc_attr( $name ) ."' /> ";
					echo "<label for='" . esc_attr( $field['id'] ) . "-$count'>" . esc_html( $value ) . "</label>";
					$count += 1;
				}
				echo "</fieldset>";
				$description = $field['description'];
				if ( !empty( $description ) )
					echo " <span class='description'>" . $description . "</span>";
				break;
			case 'postselect':
			case 'gfselect':
			case 'taxonomyselect':
			case 'select':
				$valid_options = $this->get_valid_options($field);
				echo "<select name='" . esc_attr( $field['field_name'] ) . "' id='" . esc_attr( $field['id'] ) . "' " . $class_name . " >";
				foreach ( $valid_options as $name => $value ) {
					echo "<option " . selected( $name, $sanitized[$field['name']], false ). " value='" . esc_attr( $name ) . "'>" . esc_html( $value ) . "</option>";
				}
				echo "</select>";
				$description = $field['description'];
				if ( !empty( $description ) )
					echo " <span class='description'>" . $description . "</span>";
				break;
			case 'textarea':
				$output = stripslashes( $sanitized[$field['name']] );
				$output = esc_html( $output );

				echo "<textarea name='" . esc_attr( $field['field_name'] ) . "' id='" . esc_attr( $field['id'] ) . "' rows='10' cols='50' " . $class_name . ">" . $output . "</textarea>";
				$description = $field['description'];
				if ( !empty( $description ) )
					echo "<br /><span class='description'>" . $description . "</span>";
				break;
			case 'editor':
				$editor_settings = array( 'textarea_name' => $field['field_name'] );
				$editor_id = $this->sanitize_id( $field['field_name'] );
				wp_editor( $sanitized[$field['name']], $editor_id, $editor_settings );
				$description = $field['description'];
				if ( !empty( $description ) )
					echo "<br /><span class='description'>" . $description . "</span>";
				break;
			case 'password':
				echo "<input type='password' name='" . esc_attr( $field['field_name'] ) . "' id='" . esc_attr( $field['id'] ) . "' " . $class_name . " value='" . esc_attr( $sanitized[$field['name']] ) . "' />";
				$description = $field['description'];
				if ( !empty( $description ) )
					echo " <span class='description'>" . $description . "</span>";
				break;
			case 'text':
			default:
				$output = stripslashes( $sanitized[$field['name']] );
				$output = esc_attr( $output );

				echo "<input type='text' name='" . esc_attr( $field['field_name'] ) . "' id='" . esc_attr( $field['id'] ) . "' " . $class_name . " value='" . $output . "' />";
				$description = $field['description'];
				if ( !empty( $description ) )
					echo " <span class='description'>" . $description . "</span>";
				break;
			}
		} // end of function display_settings_field


		/**
		 * register_setting() sanitize callback
		 *
		 * Validate and whitelist user-input data before updating
		 * Options in the database. Only whitelisted options are passed
		 * back to the database, and user-input data for all whitelisted
		 * options are sanitized.
		 *
		 * @since 0.1
		 * @link http://codex.wordpress.org/Data_Validation Codex Reference: Data Validation
		 * @link http://codex.wordpress.org/Function_Reference/add_settings_error Codex Reference: add_settings_error()
		 *
		 * @param array   $raw_input Raw user-input data submitted via the Settings page
		 * @return array $sanitized_input Sanitized user-input data passed to the database
		 */
		public function sanitize_settings( $raw_input ) {
			$tabs = $this->get_settings_definitions();
			$submit_type = '';
			$submit_tab  = '';

			// Determine what type of submit was input
			foreach ( $tabs as $tab_name => $tab_value ) {
				if ( !empty( $raw_input['submit-'.$tab_name] ) ) {
					if ( !empty( $raw_input['using_ajax'] ) ) {
						set_transient( 'TH_Settings_API_submitted_tab', $tab_name );
						$tab_name = '_all';
					}
					$sanitized_options = $this->sanitize_input_against_definitions(
						$raw_input,
						$this->get_settings_definitions_by_tab( $tab_name )
					);
					do_action( 'TH_Settings_API_submit_' . $this->page_name, $tab_name );

					return $sanitized_options;
					break;
				} elseif ( !empty( $raw_input['reset-'.$tab_name] ) ) {
					if ( !empty( $raw_input['using_ajax'] ) ) {
						set_transient( 'TH_Settings_API_submitted_tab', $tab_name );
						$tab_name = '_all';
					}
					$default_options = $this->get_settings_definitions_by_tab( $tab_name );
					$sanitized_options = $this->get_sanitized_settings();
					foreach ( $default_options as $name => $default_option ) {
						$optionname = $name;
						$optiondefault = isset( $default_option['default'] ) ? $default_option['default'] : '';
						$sanitized_options[$optionname] = $optiondefault;
					}
					$message = __( 'Settings have been reset to their default values.', 'th' );
					add_settings_error( 'general', 'settings_updated', $message, 'updated' );

					do_action( 'TH_Settings_API_reset_' . $this->page_name, $tab_name );

					return $sanitized_options;
					break;
				}
			}
		} // end of function sanitize_setting


		/**
		 * Handle all arguments passed to the constructor and set default values.
		 *
		 * @since 0.1
		 * @param array   $args The arguments passed to the constructor
		 */
		private function init_args( $args ) {
			/* get the arguments and compare them to default values */
			$defaults = array (
				'option_key' => 'th-settings',
				'page_title' => 'TH Settings',
				'page_name'  => 'th-settings',
				'menu_location' => 'menu',
				'plugin_file' => ''
			);
			$args = wp_parse_args( $args, $defaults );
			
			$allowed_locations = array( 'menu', 'dashboard', 'posts', 'media', 'links', 'pages', 'comments', 'theme', 'plugins', 'users', 'management', 'options');

			if ( !in_array( $args['menu_location'], $allowed_locations ) ) {
				$args['menu_location'] = 'menu';
			}

			if( !empty( $args['plugin_file'] ) ) {
				$this->is_plugin = true;
				$this->plugin_basename = plugin_basename( $args['plugin_file'] );
			} else {
				$this->is_plugin = false;
			}

			$args = (object) $args;

			$this->option_key = $args->option_key;
			$this->page_title = $args->page_title;
			$this->page_name  = $args->page_name;
			$this->menu_location = $args->menu_location;
		} // end of function init_args


		/**
		 * Echo out the settings page tabs markup.
		 *
		 * @since 0.1
		 * @link`http://www.onedesigns.com/tutorials/separate-multiple-theme-options-pages-using-tabs Daniel Tara
		 * @return string|false String containing the current tab name or false if there are no tabs
		 */
		private function display_settings_page_tabs() {
			// get the current tab and the settings definitions:
			$current_tab = $this->get_current_settings_page_tab();
			$tabs = $this->get_settings_definitions();

			$links = array();

			// loop through the definitions and populate the link array:
			foreach ( $tabs as $tabname => $tabvalue ) {
				$tabtitle = $tabvalue['title'];
				if ( $tabname == $current_tab )
					$links[] = "<a class='nav-tab nav-tab-active' href='?page=$this->page_name&tab=$tabname'>$tabtitle</a>";
				else
					$links[] = "<a class='nav-tab' href='?page=$this->page_name&tab=$tabname'>$tabtitle</a>";
			}

			// echo out the screen icon and tabs:
			// screen_icon();
			// echo '<h2>' . $this->page_title . '</h2>';
			// echo '<h3 class="nav-tab-wrapper">';
			// echo implode( '', $links );
			// echo '</h3>';

			echo '<h2 class="nav-tab-wrapper">' . $this->page_title . '&nbsp; &nbsp;';
			// echo '<h2>' . $this->page_title . '</h2>';
			echo implode( '', $links );
			echo '</h2>';

			return $current_tab;
		} // end of function display_settings_page_tabs


		/**
		 * Returns the name of the current settings page tab.
		 *
		 * @since 0.1
		 * @return string|false String containing the current tab name or false if there are no tabs
		 */
		private function get_current_settings_page_tab() {
			$tabs = $this->get_settings_definitions();

			$tab = get_transient( 'TH_Settings_API_submitted_tab' );
			delete_transient( 'TH_Settings_API_submitted_tab' );
			
			if ( !$tab ) {
				$tab = isset ( $_GET['tab'] ) ? $_GET['tab'] : '';
			}

			// check whether a valid 'tab' has been set in the request and if so return it:
			if ( array_key_exists( $tab, $tabs ) )
				return $tab;

			// no valid tab found in request, so return the first valid tab from the settings definitions:
			reset( $tabs );
			$key = key( $tabs );
			$key = empty( $key ) ? false : $key;
			return $key;
		} // end of function get_current_settings_page_tab


		/**
		 * Loads settings definitions from external file and populates the private settings array.
		 *
		 * @since 0.1
		 * @return array containing the settings definitions.
		 */
		private function get_settings_definitions() {
			// if settings definitions have already been loaded, return these:
			if ( $this->settings_definitions )
				return $this->settings_definitions;

			// define some defaults of no settings definitions file is present:
			$default =  array(
				'meta' => array(
					'version' => '0.1'
				),
				'tabs' => array(
					'no_settings' => array(
						'title' => 'No Settings Defined',
						'sections' => array(
							'section_one' => array(
								'title' => 'Warning!',
								'description' => 'We could not find the file called: <code>' . $this->settings_definitions_file_path . '</code>',
								'settings' => array()
							)
						)
					)
				)
			);

			// check for the existence of the settings definitions file and load the contents:
			$filename = $this->settings_definitions_file_path;
			if ( is_readable( $filename ) ) {
				include_once $filename;

				if ( isset( $settings_definitions ) && !empty( $settings_definitions ) ) {
					$this->settings_definitions_meta = $settings_definitions['meta'];
					$this->settings_definitions = $settings_definitions['tabs'];
				} else {
					$this->settings_definitions_meta = $default['meta'];
					$this->settings_definitions = $default['tabs'];
				}
			} else {
				$this->settings_definitions_meta = $default['meta'];
				$this->settings_definitions = $default['tabs'];
			}

			// return the definitions:
			return $this->settings_definitions;
		} // end of function get_settings_definitions


		/**
		 * Gets the settings definitions associated with the given tab.
		 *
		 * @since 0.1
		 * @param array   $tab The tab name for which to return associated definitions, use '_all' to return all.
		 * @return array|false The array of settings definitions
		 */
		private function get_settings_definitions_by_tab( $tab ) {
			// get the definitions:
			$set_def = $this->get_settings_definitions();
			// check if the requested tab exists:
			if ( empty( $tab ) )
				return false;

			$set_def_by_tab = array();
			if ( '_all' == $tab ) {
				foreach ( $set_def as $tab_name => $tab_def ) {
					foreach ( $tab_def['sections'] as $section ) {
						foreach ( $section['settings'] as $k => $v ) {
							$set_def_by_tab[$k] = $v;
						}
					}
				}
			} else {
				if ( !array_key_exists( $tab, $set_def ) )
					return false;

				// loop through all sections and extract settings:
				foreach ( $set_def[$tab]['sections'] as $section ) {
					foreach ( $section['settings'] as $k => $v ) {
						$set_def_by_tab[$k] = $v;
					}
				}
			}
			return $set_def_by_tab;
		} // end of function get_settings_definitions_by_tab


		/**
		 * Loads settings definitions meta from external file and populates the private settings meta array.
		 *
		 * @since 0.1
		 * @return array containing the settings definitions meta.
		 */
		private function get_settings_definitions_meta() {
			// if settings definitions meta values have already been loaded, return these:
			if ( $this->settings_definitions_meta )
				return $this->settings_definitions_meta;

			$this->get_settings_definitions();
			return $this->settings_definitions_meta;
		} // end of function get_settings_definitions_meta


		/**
		 * Get a list of sanitized settings that are saved in the database. The default
		 * value of newly added settings not yet stored will be added to the database
		 * and also included.
		 *
		 * @since 0.1
		 * @return array The array of sanitized settings
		 */
		private function get_sanitized_settings() {
			if ( $this->sanitized_settings )
				return $this->sanitized_settings;

			// set options equal to defaults
			$this->sanitized_settings = get_option( $this->option_key, false );
			$default_options = $this->get_settings_definitions_by_tab( '_all' );
			$meta = $this->get_settings_definitions_meta();

			if ( false === $this->sanitized_settings ) {
				$this->sanitized_settings = array();
				foreach ( $default_options as $name => $default_option ) {
					$optionname = $name;
					$optiondefault = isset( $default_option['default'] ) ? $default_option['default'] : '';
					$this->sanitized_settings[$optionname] = $optiondefault;
				}
				$this->sanitized_settings['th_version'] = $meta['version'];
				add_option( $this->option_key, $this->sanitized_settings );
			} else if ( $meta['version'] > $this->sanitized_settings['th_version'] ) {
					foreach ( $default_options as $name => $default_option ) {
						if ( $default_option['since'] > $this->sanitized_settings['th_version'] ) {
							$optionname = $name;
							$optiondefault = isset( $default_option['default'] ) ? $default_option['default'] : '';
							$this->sanitized_settings[$optionname] = $optiondefault;
						}
					}
					$this->sanitized_settings['th_version'] = $meta['version'];
					update_option( $this->option_key, $this->sanitized_settings );
				}
			return $this->sanitized_settings;
		} // end of function get_sanitized_settings


		/**
		 * Validate and whitelist user-input data before updating
		 * Options in the database. Only whitelisted options are passed
		 * back to the database, and user-input data for all whitelisted
		 * options are sanitized.
		 *
		 * @since 0.1
		 * @link http://codex.wordpress.org/Data_Validation Codex Reference: Data Validation
		 * @link http://codex.wordpress.org/Function_Reference/add_settings_error Codex Reference: add_settings_error()
		 *
		 * @param array   $raw_input   Raw user-input data submitted via the Settings page
		 * @param array   $definitions Pre-defined Settings definitions
		 * @return array $sanitized_input Sanitized user-input data passed to the database
		 */
		private function sanitize_input_against_definitions( $raw_input, $definitions ) {
			$sanitized = $this->get_sanitized_settings();
			foreach ( $definitions as $name => $def ) {
				switch ( $def['type'] ) {
				case 'checkbox':
					// if input value is set and is true, return true; otherwise return false:
					$sanitized[$name] = ( ( isset( $raw_input[$name] ) && true == $raw_input[$name] ) ? true : false );
					break;
				case 'multicheck':
					// get the list of valid options:
					$valid_options = $this->get_valid_options($def);
					// set all valid options to false:
					foreach ( $valid_options as $key => $value ) {
						$sanitized[$name][$key] = false;
					}
					// set the options to true if the value is present in the request and is a valid option:
					if ( !empty( $raw_input[$name] ) ) {
						foreach ( $raw_input[$name] as $value ) {
							if ( array_key_exists( $value, $valid_options ) ) {
								$sanitized[$name][$value] = true;
							}
						}
					}
					break;
				case 'radio':
				case 'postselect':
				case 'gfselect':
				case 'taxonomyselect':
				case 'select':
					// get the list of valid options:
					$valid_options = $this->get_valid_options($def);
					// only update setting if input value is in the list of valid options:
					if ( isset( $raw_input[$name] ) )
						$sanitized[$name] = ( array_key_exists( $raw_input[$name], $valid_options ) ? $raw_input[$name] : $sanitized[$name] );
					else
						$sanitized[$name] = NULL;
					break;
				case 'password':
					$sanitized[$name] = $raw_input[$name];
					break;
				case 'text':
					if ( !isset( $def['sanitize'] ) )
						$def['sanitize'] = '';

					$value   = $raw_input[$name];

					//switch validation based on the class!
					switch ( $def['sanitize'] ) {
					case 'numeric':
						//accept the input only when numeric!
						$value   = trim( $value ); // trim whitespace
						if ( is_numeric( $value ) )
							$sanitized[$name] = $value;
						else
							$sanitized[$name] = $def['default'];

						// register error
						if ( !is_numeric( $value ) ) {
							$message = __( 'Setting: "' . $def['title'] . '" must be a number.', 'th' );
							add_settings_error( $name, $name, $message, 'error' );
						}
						break;
					case 'url':
						//accept the input only when the url has been sanited for database usage with esc_url_raw()
						$value   = trim( $value ); // trim whitespace
						$sanitized[$name] = esc_url_raw( $value );
						break;
						//for email
					case 'email':
						//accept the input only after the email has been validated
						$value   = trim( $value ); // trim whitespace
						if ( $value != '' ) {
							$sanitized[$name] = ( is_email( $value ) !== FALSE ) ? $value : __( 'Invalid email! Please re-enter!', 'th' );
						}elseif ( $value == '' ) {
							$sanitized[$name] = __( 'This setting field cannot be empty! Please enter a valid email address.', 'th' );
						}

						// register error
						if ( is_email( $value )== FALSE || $value == '' ) {
							$message = __( 'Setting: "' . $def['title'] . '" must be a valid email address.', 'th' );
							add_settings_error( $name, $name, $message, 'error' );
						}
						break;
						//for no html
					case 'nohtml':
						//accept the input only after stripping out all html, extra white space etc!
						$value   = sanitize_text_field( $value ); // need to add slashes still before sending to the database
						$sanitized[$name] = addslashes( $value );
						break;
						//for only inline html
					case 'inlinehtml':
					default:
						// accept only a few inline html elements
						$allowed_html = array(
							'a' => array( 'href' => array (), 'title' => array () ),
							'b' => array(),
							'em' => array (),
							'i' => array (),
							'strong' => array()
						);

						$value   = trim( $value ); // trim whitespace
						$value   = force_balance_tags( $value ); // find incorrectly nested or missing closing tags and fix markup
						$value   = wp_kses( $value, $allowed_html ); // need to add slashes still before sending to the database
						$sanitized[$name] = addslashes( $value );
						break;
					}
					break;
				case 'editor':
				case 'textarea':
					if ( !isset( $def['sanitize'] ) )
						$def['sanitize'] = '';

					$value   = $raw_input[$name];

					//switch validation based on the class!
					switch ( $def['sanitize'] ) {
						//for only inline html
					case 'inlinehtml':
						// accept only a few inline html elements
						$allowed_html = array(
							'a' => array( 'href' => array (), 'title' => array () ),
							'b' => array(),
							'em' => array (),
							'i' => array (),
							'strong' => array()
						);

						$value   = trim( $value ); // trim whitespace
						$value   = force_balance_tags( $value ); // find incorrectly nested or missing closing tags and fix markup
						$value   = wp_kses( $value, $allowed_html ); // need to add slashes still before sending to the database
						$sanitized[$name] = addslashes( $value );
						break;
						//for no html
					case 'nohtml':
						//accept the input only after stripping out all html, extra white space etc!
						$value   = sanitize_text_field( $value ); // need to add slashes still before sending to the database
						$sanitized[$name] = addslashes( $value );
						break;

						//for allowlinebreaks
					case 'allowlinebreaks':
						//accept the input only after stripping out all html, extra white space etc!
						$value   = wp_strip_all_tags( $input[$option['id']] ); // need to add slashes still before sending to the database
						$sanitized[$name] = addslashes( $value );
						break;

						// a "cover-all" fall-back when the class argument is not set
					default:
						$sanitized[$name] = wp_kses_post( $value );
						break;
					}
					break;
				}

				/*if ( $sanitized[$name] != $raw_input[$name] ) {
					$message = __( 'Setting: "' . $def['title'] . '" was sanitized as disallowed content was entered.', 'th' );
					add_settings_error( $name, 'settings_error_' . $name, $message, 'error' );
				}*/
			}
			if ( !count( get_settings_errors() ) ) {
				$message = __( 'Settings have been saved.', 'th' );
				add_settings_error( 'general', 'settings_updated', $message, 'updated' );
			}
			return $sanitized;
		} // end of function sanitize_input_against_definitions

		private function get_valid_options( $def ) {
			$result = array();
			switch ( $def['type'] ) {
				case 'taxonomyselect':
					$taxonomy = !empty( $def['taxonomy'] ) ? $def['taxonomy'] : 'category';
					$id_or_slug = !empty( $def['id_or_slug'] ) ? $def['id_or_slug'] : 'slug';
					$args = !empty( $def['args'] ) ? $def['args'] : array( 'hide_empty' => false );
					$terms = get_terms( $taxonomy, $args );

					foreach ($terms as $term ) {
						if( 'slug' == $id_or_slug) {
							$result[$term->slug] = $term->name;
						} else {
							$result[$term->term_id] = $term->name;
						}
					}
					break;

				case 'postselect':
					$post_type = !empty( $def['post_type'] ) ? $def['post_type'] : 'post';
					$meta_key = !empty( $def['meta_key'] ) ? $def['meta_key'] : '';
					$meta_value = !empty( $def['meta_value'] ) ? $def['meta_value'] : '';
					$id_or_slug = !empty( $def['id_or_slug'] ) ? $def['id_or_slug'] : 'slug';

					$posts = get_posts(
						array(
							'post_type'      => $post_type,
							'posts_per_page' => -1,
							'orderby'        => 'name',
							'order'          => 'ASC',
							'meta_key'       => $meta_key,
							'meta_value'     => $meta_value,
						)
					);

					foreach ( $posts as $post ) {
						if ( 'slug' == $id_or_slug ) {
							$result[$post->post_name] = $post->post_title;
						} else {
							$result[$post->ID] = $post->post_title;
						}
					}
					break;

				case 'gfselect':
					if ( class_exists( 'RGFormsModel' ) ) {
						$forms = RGFormsModel::get_forms( null, 'title' );
						foreach ( $forms as $form ) {
							$result[$form->id] = $form->title;
						}
						if(!count($result)) {
							$result[0] = 'No forms available';
						}
					} else {
						$result[0] = 'Gravity Forms not installed';
					}

					break;
				
				default:
					$result = $def['valid_options'];
					break;
			}
			return $result;
		} // end of function get_valid_options

		private function sanitize_id( $id ) {
			$return = str_replace( '-', '_', $id);
			$return = str_replace( '[', '_', $return);
			$return = str_replace( ']', '_', $return);
			$return = strtolower($return);
			return $return;
		}

	} // end of class TH_Settings_API

}
