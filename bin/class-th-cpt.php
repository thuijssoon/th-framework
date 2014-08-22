<?php
/**
 * WordPress Custom Post Types
 *
 * Contains the TH_CPT class. Requires WordPress version 3.5 or greater.
 *
 * @todo      Improve filtered values.
 * @todo      Add custom roles & capabilities.
 * @todo      Change text domain.
 *
 * @version   0.1.1
 * @package   TH CPT
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL 2.0+ - http://www.gnu.org/licenses/gpl.txt
 * @link      https://github.com/thuijssoon/
 * @copyright Copyright (c) 2013 - Thijs Huijssoon
 */

if ( !class_exists( 'TH_CPT' ) ) {

	/*
	 * WordPress Custom Post Type Class
	 *
	 * A class that handles the registration of WordPress custom post types and
	 * enables you to deeply integrate into the wordpress admin area.
	 *
	 * @package TH CPT
	 */
	class TH_CPT {

		/**
		 * Array of TH_CPT objects for easy access
		 * and to avoid polluting the global namespace.
		 *
		 * @var    array
		 * @since  0.1.0
		 */
		private static $instances = array();

		/**
		 * Post type (max. 20 characters, can not
		 * contain capital letters or spaces)
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $post_type;

		/**
		 * Array of arguments passed to register_post_type
		 *
		 * @var    array
		 * @since  0.1.0
		 */
		private $post_type_args;

		/**
		 * Placeholder text for title element in add/edit post screen
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $title_placeholder;

		/**
		 * Label for title element in add/edit post screen
		 * Only used during error reporting
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $title_label;

		/**
		 * Default text for title element in add/edit post screen
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $default_title;

		/**
		 * Default text for edit element in add/edit post screen
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $default_content;

		/**
		 * Label for editor element in add/edit post screen
		 * Only used during error reporting
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $content_label;

		/**
		 * Title for featured image metabox
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $featured_image_title;

		/**
		 * Link add text for featured image metabox
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $featured_image_link_add;

		/**
		 * Link remove text for featured image metabox
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $featured_image_link_remove;

		/**
		 * Array of tab arrays for contextual help
		 *
		 * @var    array
		 * @since  0.1.0
		 */
		private $help_tabs;

		/**
		 * String containing contextual help sidebar
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $help_sidebar;

		/**
		 * Path to screen icon image
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $screen_icon;

		/**
		 * Path to menu icon image
		 *
		 * @var    string
		 * @since  0.1.0
		 */
		private $menu_icon;

		/**
		 * Menu icon character code, used in at a glance items.
		 *
		 * @var    string
		 * @since  0.1.1
		 */
		private $character_code;

		/**
		 * Fields that must be completed in order for
		 * the CPT to be published.
		 *
		 * @var    array
		 * @since  0.1.0
		 */
		private $required_fields;

		/**
		 * The error messages that will be displayed
		 * when a required field is missing.
		 *
		 * @var    array
		 * @since  0.1.0
		 */
		private $error_messages;

		/**
		 * Locations of the override CPT templates
		 * for this post type.
		 *
		 * @var    array
		 * @since  0.1.0
		 */
		private $post_templates;


		// ======================================
		// Constructor
		// ======================================

		/**
		 * Class contructor.
		 *
		 * @param string  $post_type The post type you would like to register.
		 * @param string  $singular  The post type singular name.
		 * @param string  $plural    The post type plural name.
		 * @param array   $args      The arguments passed to register_post_type
		 * @link    http://codex.wordpress.org/Function_Reference/register_post_type
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function __construct( $post_type, $singular, $plural, $args = array() ) {
			// Let's motivate people to upgrade
			global $wp_version;
			if ( version_compare( $wp_version, '3.5', '<' ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>%1$s requires WordPress 3.5 in order to work.</strong> You\'re running WordPress %2$s. You\'ll need to upgrade in order to use this file. If you need help upgrading WordPress you can refer to <a href="http://codex.wordpress.org/Upgrading_WordPress">the WordPress Codex</a>.', 'text_domain' ), __CLASS__, $wp_version ) );
				}
				return;
			}

			// Cannot register post type twice
			if ( isset( self::$instances[$post_type] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>%1$s has already been defined.</strong>', 'text_domain' ), $post_type ) );
				}
				return;
			}

			// Reserved terms that WordPress wont let us use as custom post type ID.
			// The list can be found here: http://codex.wordpress.org/Function_Reference/register_post_type#Reserved_Post_Types
			$reserved = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );
			if ( in_array( $post_type, $reserved ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>%1$s is a reserved word and cannot be used as ID for a custom post type.</strong> If you want to know which are the reserved words, please refer to <a href="http://codex.wordpress.org/Function_Reference/register_post_type#Reserved_Post_Types">the WordPress Codex</a>', 'text_domain' ), $post_type ) );
				}
				return;
			}

			// According to the DB schema of WordPress, $post_type can't be longer than 20 characters
			// http://codex.wordpress.org/Post_Types#Naming_Best_Practices
			if ( strlen( $post_type ) > 20 ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>"%1$s" (length: %2$d) is longer than the allowed 20 characters for a custom post type name.</strong> If you want to know more about post type naming best practices, please refer to <a href="http://codex.wordpress.org/Post_Types#Naming_Best_Practices">the WordPress Codex</a>', 'text_domain' ), $post_type, strlen( $post_type ) ) );
				}
				return;
			}

			$this->post_type = $post_type;

			$this->init_args( $singular, $plural, $args );

			add_action( 'init', array( $this, 'acb_init_register_post_type' ), 0 );

			if ( is_admin() ) {
				// Filter the media strings to reflect the CPT
				add_filter( 'media_view_strings', array( $this, 'fcb_media_view_strings_featured_image_link' ), 10, 2 );

				// Filter the post updated messages
				add_filter( 'post_updated_messages', array( $this, 'fcb_post_updated_messages' ) );
			} else {
				// Filter the body class and add classes for this post type
				add_filter( 'body_class', array( $this, 'fcb_body_class_add_cpt_class' ) );
			}

			// Store $this in static instances array for easy access
			// without polluting the global namespace.
			self::$instances[ $post_type ] = $this;
		}


		// ======================================
		// Public chainable methods
		// ======================================

		/**
		 * Change the placeholder text of the title form element
		 * in the add/edit post screen of this CPT.
		 *
		 * @param string  $title_placeholder Placeholder text
		 * @param string  $title_label       Label for title, used in error reporting
		 * @return  $this                       This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function set_title_placeholder( $title_placeholder, $title_label = 'Title' ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			$this->ensure_cpt_supports( array( 'title' ) );
			$this->title_placeholder = strip_tags( $title_placeholder );
			$this->title_label = strip_tags( $title_label );
			add_filter( 'enter_title_here', array( $this, 'fcb_enter_title_here_placeholder' ), 10, 2 );
			return $this;
		}

		/**
		 * Set a default title in the
		 * add post screen of this CPT.
		 *
		 * @param string  $default_title Default title
		 * @return  $this                   This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function set_default_title( $default_title ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			$this->ensure_cpt_supports( array( 'title' ) );
			$this->default_title = sanitize_text_field( $default_title );
			add_filter( 'default_title', array( $this, 'fcb_default_title_set' ), 10, 2 );
			return $this;
		}

		/**
		 * Set default content in the post editor
		 * on the add post screen of this CPT.
		 *
		 * @param string  $default_content Default content
		 * @param string  $content_label   Label for editor, used in error reporting
		 * @return  $this                     This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function set_default_content( $default_content, $content_label = 'content' ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			$this->ensure_cpt_supports( array( 'editor' ) );
			$this->default_content = wp_kses_post( $default_content );
			$this->content_label = strip_tags( $content_label );
			add_filter( 'default_content', array( $this, 'fcb_default_content_set' ), 10, 2 );
			return $this;
		}

		/**
		 * Enable the get shortlink button in the add/edit post screen
		 * for this CPT.
		 *
		 * @return  $this  This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function enable_shortlink() {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			add_filter( 'pre_get_shortlink', array( $this, 'fcb_pre_get_shortlink_enable' ), 10, 4 );
			return $this;
		}

		/**
		 * Customize the title and link text of the featured image meta box.
		 *
		 * @param string  $title       Title text
		 * @param string  $link_add    Link text
		 * @param string  $link_remove Link remove text
		 * @return  $this                 This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function set_featured_image_text( $title, $link_add, $link_remove ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			$this->ensure_cpt_supports( array( 'thumbnail' ) );
			$this->featured_image_title        = strip_tags( $title );
			$this->featured_image_link_add     = strip_tags( $link_add );
			$this->featured_image_link_remove  = strip_tags( $link_remove );
			add_action( 'do_meta_boxes', array( $this, 'acb_do_meta_boxes_rename_featured_image_title' ) );
			add_filter( 'admin_post_thumbnail_html', array( $this, 'fcb_admin_post_thumbnail_html_rename_featured_image_link' ) );
			return $this;
		}

		/**
		 * Adds multiple help tabs to the screens for this CPT.
		 *
		 * @param array   $tabs The tabs to add
		 * @return  $this          This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function add_help_tabs( array $tabs ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			foreach ( $tabs as $tab ) {
				$this->add_help_tab( $tab );
			}
			return $this;
		}

		/**
		 * Adds a help tab to the screens for this CPT.
		 * For information on the $tab argument, see documentation for WP_Screen::add_help_tab():
		 *
		 * @param array   $tab Settings for the tab
		 * @return  $this        This object to enable function chaining
		 * @link    http://codex.wordpress.org/Function_Reference/add_help_tab
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function add_help_tab( array $tab ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			if ( is_null( $this->help_tabs ) ) {
				add_action( 'current_screen', array( $this, 'acb_current_screen_add_help_to_screen' ), 10, 3 );
				// add_action( 'load-' . $GLOBALS['pagenow'], array( $this, 'acb_load_add_help_to_screen' ), 10, 3 );
				$this->help_tabs = array();
			}

			$this->help_tabs[] = $tab;
			return $this;
		}

		/**
		 * Sets the content for the help sidebar for this CPT
		 *
		 * @param string  $content HTML markup for the help sidebar
		 * @return  $this             This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function set_help_sidebar( $content ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			if ( is_null( $this->help_sidebar ) ) {
				add_action( 'current_screen', array( $this, 'acb_current_screen_add_help_to_screen' ), 10, 3 );
				// add_action( 'load-' . $GLOBALS['pagenow'], array( $this, 'acb_load_add_help_to_screen' ), 10, 3 );
			}

			$this->help_sidebar = $content;
			return $this;
		}

		/**
		 * Add the CPT name and count to the Right Now content table
		 * on the WordPress Dashboard.
		 *
		 * @return  $this           This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function show_in_content_table() {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			add_action( 'right_now_content_table_end', array( $this, 'acb_right_now_content_table_end_add_cpt' ) );

			add_action( 'dashboard_glance_items', array( $this, 'acb_dashboard_glance_items_add_cpt' ) );
			add_action( 'admin_head', array( $this, 'acb_admin_head_at_a_glance_icons' ) );
			
			return $this;
		}

		/**
		 * Set the screen icon for this CPT.
		 * The image should be a png image of 32x32 px.
		 *
		 * @param string  $screen_icon http://path/to/screen/icon.png
		 * @return  $this                 This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function set_screen_icon( $screen_icon ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			$this->screen_icon = $screen_icon;
			add_action( 'admin_head', array( $this, 'acb_admin_head_screen_icon_css' ) );
			return $this;
		}

		/**
		 * Set the menu icon for this CPT.
		 * The image should be a png image of 16x40 px.
		 *
		 * @param string  $screen_icon http://path/to/screen/icon.png
		 * @return  $this                 This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function set_menu_icon( $menu_icon, $character_code = false ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			// Are we using the new dashicons ?
			if (0 === strpos($menu_icon, 'dashicons-')) {
				$this->post_type_args['menu_icon'] = $menu_icon;
				$this->character_code = $character_code;
			} else { // fallback for previous versions
				$this->menu_icon = $menu_icon;
				// remove menu icon from $post_type_args if present
				// so we can override it with css
				$this->post_type_args['menu_icon'] = '';
				add_action( 'admin_head', array( $this, 'acb_admin_head_menu_icon_css' ) );
			}

			return $this;
		}

		/**
		 * Allow this CPT to be used as front page
		 *
		 * @return  $this  This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function allow_as_front_page() {
			// Add post type to the query object
			add_action( 'pre_get_posts', array( $this, 'acb_pre_get_posts_enable_front_page_cpt' ) );

			if ( is_admin() ) {
				// Add posts of this post type to the Front page drow-down menu on the
				// reading settings page.
				add_filter( 'get_pages', array( $this, 'fcb_get_pages_add_cpt_to_dropdown' ), 10, 2 );
			}

			return $this;
		}

		/**
		 * Mark fields as required. The CPT cannot be published
		 * if these fields are left empty.
		 *
		 * @param array   $required_fields Required fields
		 * @return  $this                    This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function require_fields( array $required_fields ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			// Only proceed if there are any required fields
			if ( !count( $required_fields ) ) {
				return $this;
			}

			$this->ensure_cpt_supports(array_keys( $required_fields ) );

			if(!class_exists('WPTP_Error')) {
				require_once 'class-wptp-error.php';
			}

			WPTP_Error::get_instance()
			->add_post_type_support($this->post_type, $required_fields );

			return $this;
		}

		/**
		 * Add a post thumbnail admin column to the All post_type screen.
		 *
		 * @return  $this  This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function show_thumbnail_in_admin_table() {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			$this->ensure_cpt_supports( array( 'thumbnail' ) );
			add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'fcb_manage_cpt_posts_columns_add_thumbnail' ), 0 );
			add_action( "manage_posts_custom_column",  array( $this, 'acb_manage_posts_custom_column_show_thumbnail' ), 10, 2 );
			return $this;
		}

		/**
		 * Add cononical links for this post type.
		 *
		 * @return  $this  This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function add_canonical_link() {
			if ( !is_admin() ) {
				// Add canonical link to head
				add_action( 'wp_head', array( $this, 'acb_wp_head_add_canonical_link' ) );
			}
			return $this;
		}

		/**
		 * Enable template override functionality.
		 * If files single-post_type.php & archive-post_type.php are not
		 * found in the current theme, their replacements as provided by this
		 * class will be used.
		 *
		 * Fileter: th_cpt_default_cpt_templates
		 *
		 * @param array   $templates Array with keys 'single' & 'archive' and associated paths to the templates.
		 * @return  $this              This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function add_template_overrides( $templates = array() ) {
			$directory = trailingslashit( dirname( __FILE__ ) );
			$default_templates = array(
				'single'   => $directory . 'templates/single-' . $this->post_type . '.php' ,
				'archive'  => $directory . 'templates/archive-' . $this->post_type . '.php'
			);

			$default_templates = apply_filters(
				'th_cpt_default_cpt_templates',
				$default_templates,
				$this->post_type
			);

			$this->post_templates = wp_parse_args(
				$templates,
				$default_templates
			);

			// Change how templates are pulled for this post type
			add_filter( 'template_include', array( $this, 'fcb_template_include_template_override' ) );

			return $this;
		}

		/**
		 * Create a new TH_CT taxonomy object and add it to this CPT.
		 *
		 * @todo    Should TH_CPT & TH_CT be more disconnected?
		 *
		 * @uses    TH_CT
		 * @link    http://codex.wordpress.org/Function_Reference/register_taxonomy
		 * @param string  $taxonomy_name Taxonomy name
		 * @param string  $singular      Singular name
		 * @param string  $plural        Plural name
		 * @param array   $args          Arguments passed to register_taxonomy
		 * @param array   $admin_args    admin_args @see TH_CT
		 * @return  $this                   This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function add_taxonomy( $taxonomy_name, $singular, $plural, $args = array(), $admin_args = array() ) {
			// Delegate to TH_CT class
			if ( !class_exists( 'TH_CT' ) ) {
				require_once 'class-th-ct.php';
			}
			new TH_CT( $this->post_type, $taxonomy_name, $singular, $plural, $args, $admin_args );

			$this->add_taxonomy_to_post_type_args( $taxonomy_name );

			return $this;
		}

		/**
		 * Add an existing TH_CT taxonomy object to this CPT.
		 *
		 * @todo    Should TH_CPT & TH_CT be more disconnected?
		 *
		 * @uses    TH_CT
		 * @param string  $taxonomy_name Taxonomy name
		 * @param array   $admin_args    admin_args @see TH_CT
		 * @return  $this                   This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function add_existing_taxonomy( $taxonomy_name, $admin_args = array() ) {
			$built_in = array( 'category', 'post_tag' );

			if ( !in_array( $taxonomy_name, $built_in ) ) {
				if ( !class_exists( 'TH_CT' ) ) {
					require_once 'class-th-ct.php';
				}
				$tax = TH_CT::get_instance( $taxonomy_name );
				if ( $tax ) {
					$tax->add_taxonomy_to_post_type( $this->post_type, $admin_args );
				}
			}

			$this->add_taxonomy_to_post_type_args( $taxonomy_name );

			return $this;
		}


		// ======================================
		// Static methods
		// ======================================

		/**
		 * Retrieve the TH_CPT instance for the post type
		 *
		 * @param string  $post_type The name of the post type
		 * @return  TH_CPT              The associated TH_CPT instance
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public static function get_instance( $post_type ) {
			if ( empty( $post_type ) || !isset( self::$instances[ $post_type ] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>Post type %1$s is not recognised..</strong>', 'text_domain' ), $post_type ) );
				}
				return;
			}

			return self::$instances[ $post_type ];
		}

		/**
		 * Flushes rewrite rules on activation to prevent 404 errors for this post type.
		 *
		 * You should only flush rewrite rules on plugin activation, not on init or any other hook that gets run
		 * frequently as this can severely impact the performance of the site.
		 *
		 * Failure to flush rewrite rules immediately after the post type is registered will result in 'page not found'
		 * errors when visiting pages related to this post type.  Visiting the permalinks page in WordPress will flush
		 * the rewrite rules and fix this issue.
		 *
		 * In plugins use:
		 * register_activation_hook( __FILE__, array( 'TH_CPT', 'activate' ) );
		 *
		 * In themes use:
		 * add_action( 'after_switch_theme', array( 'TH_CPT', 'activate' ) );
		 *
		 * Make sure that you have created your CPTS BEFORE these actions run,
		 * otherwise nothing will happen.
		 *
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public static function activate( $post_type = '' ) {
			// rewrite rules can't be flushed during switch to blog
			delete_option( 'rewrite_rules' );
		}

		/**
		 * Flushes rewrite rules on deactivation to remove rules associated with this post type.
		 *
		 * In plugins use:
		 * register_deactivation_hook( __FILE__, array( 'TH_CPT', 'deactivate' ) );
		 *
		 * In themes use:
		 * add_action( 'switch_theme', array( 'TH_CPT', 'deactivate' ) );
		 *
		 * Make sure that you have NOT created your CPTS before these actions run,
		 * otherwise nothing will happen.
		 *
		 * @see activate
		 *
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public static function deactivate( $post_type = '' ) {
			// rewrite rules can't be flushed during switch to blog
			delete_option( 'rewrite_rules' );
		}

		/**
		 * Re-assign posts with status th_error to draft.
		 *
		 * Should be called only on uninstall.
		 *
		 * @uses WPTP_Error
		 *
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public static function uninstall( $post_type = '' ) {
			if ( class_exists( 'WPTP_Error' ) ) {
				WPTP_Error::re_assign_status( $post_type );
			}
		}


		// ======================================
		// Action callbacks
		// ======================================

		/**
		 * Action callback to register the post type.
		 *
		 * @wp-hook  init
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_init_register_post_type() {
			if ( post_type_exists( $this->post_type ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>%1$s has already been defined.</strong>', 'text_domain' ), $this->post_type ) );
				}
				return;
			}
			register_post_type( $this->post_type, $this->post_type_args );
		}

		/**
		 * Action callback to remove postimagediv meta_box
		 * and re-register it to change title.
		 *
		 * @wp-hook  do_meta_boxes
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_do_meta_boxes_rename_featured_image_title() {
			remove_meta_box( 'postimagediv', $this->post_type, 'side' );
			add_meta_box( 'postimagediv', $this->featured_image_title, 'post_thumbnail_meta_box', $this->post_type, 'side', 'default' );
		}

		/**
		 * Action callback to add help tabs and help sidebar.
		 * Adds help to all screens for the post type.
		 *
		 * @wp-hook  current_screen
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_current_screen_add_help_to_screen() {
			$screen = get_current_screen();
			if ( $this->post_type != $screen->post_type ) {
				return;
			}

			if ( is_array( $this->help_tabs ) ) {
				foreach ( $this->help_tabs as $tab ) {
					$screen->add_help_tab( $tab );
				}
			}

			if ( !is_null( $this->help_sidebar ) ) {
				$screen->set_help_sidebar( $this->help_sidebar );
			}
		}

		/**
		 * Action callback to add CPT to the Right Now table on the dashboard.
		 *
		 * @wp-hook  right_now_content_table_end
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_right_now_content_table_end_add_cpt() {
			$num_posts = wp_count_posts( $this->post_type );
			$num       = number_format_i18n( $num_posts->publish );
			$text      = _n( $this->post_type_args['labels']['singular_name'], $this->post_type_args['labels']['name'], $num );
			$type      = get_post_type_object( $this->post_type );

			if ( current_user_can( $type->cap->edit_posts ) ) {
				$num = '<a href="edit.php?post_type=' . $this->post_type . '">' . $num . '</a>';
				$text = '<a href="edit.php?post_type=' . $this->post_type . '">' . $text . '</a>';
			}

			echo '<tr>';
			echo '<td class="first b b-'. $this->post_type . '">' . $num . '</td>';
			echo '<td class="t ' . $this->post_type . '">' . $text . '</td>';
			echo '</tr>';
		}

		/**
		 * Action callback to add CPT to the At a Glance table on the dashboard.
		 *
		 * @wp-hook  dashboard_glance_items
		 * @return   void
		 * @since    0.1.1
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_dashboard_glance_items_add_cpt() {
			$post_type_object = get_post_type_object( $this->post_type );
			$num_posts = wp_count_posts( $this->post_type );
			$num = number_format_i18n( $num_posts->publish );
			$text = _n( $post_type_object->labels->singular_name, $post_type_object->labels->name, intval( $num_posts->publish ) );
			if ( current_user_can( $post_type_object->cap->edit_posts ) ) {
				$output = '<a href="edit.php?post_type=' . $post_type_object->name . '">' . $num . ' ' . $text . '</a>';
				echo '<li class="post-count ' . $post_type_object->name . '-count">' . $output . '</li>';
			}
		}

		/**
		 * Action callback to add CPT icons to the At a Glance table on the dashboard.
		 *
		 * @wp-hook  admin_head
		 * @return   void
		 * @since    0.1.1
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_admin_head_at_a_glance_icons() {
			if ( $this->character_code ) {
				echo '<style type="text/css">.' . $this->post_type . '-count a:before {content: "' . $this->character_code . '"!important;}</style>';
			}
		}

		/**
		 * Add inline css to replace the default
		 * screen icon for this CPT.
		 *
		 * @wp-hook  admin_head
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_admin_head_screen_icon_css() {
			$post_type = get_current_screen()->post_type;
			if ( $this->post_type != $post_type ) {
				return;
			}

			$class = sanitize_html_class( $this->post_type );
?>
		<style type="text/css">
			.icon32.icon32-posts-<?php echo $class; ?> {
				background: url(<?php echo esc_url( $this->screen_icon ); ?>) !important;
				background-repeat: no-repeat !important;
			}
		</style>
<?php
		}

		/**
		 * Add inline css to replace the default
		 * menu icon for this CPT enabling a hover state.
		 *
		 * @wp-hook  admin_head
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_admin_head_menu_icon_css() {
			$class = sanitize_html_class( $this->post_type );
?>
		<style type="text/css">
			#adminmenu #menu-posts-<?php echo $class; ?> div.wp-menu-image {
				background: transparent url(<?php echo esc_url( $this->menu_icon ); ?>) no-repeat 6px -17px;
			}
			#adminmenu #menu-posts-<?php echo $class; ?>:hover div.wp-menu-image,
			#adminmenu #menu-posts-<?php echo $class; ?>.wp-has-current-submenu div.wp-menu-image {
				background-position: 6px 7px;
			}
		</style>
<?php
		}

		/**
		 * Adds this post type to the front page query
		 * when showing the front page and when the setting
		 * show page on front is true.
		 *
		 * @wp-hook  pre_get_posts
		 * @param object  $query The current WP_Query
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_pre_get_posts_enable_front_page_cpt( $query ) {
			if (
				$query->is_main_query() &&
				'page' == get_option( 'show_on_front' ) &&
				get_option( 'page_on_front' ) &&
				get_option( 'page_on_front' ) == $query->query_vars['page_id']
			) {
				if ( !isset( $query->query_vars['post_type'] ) ) {
					$query->query_vars['post_type'] = array( 'page' );
				}
				$query->query_vars['post_type'] = array_unique(
					array_merge(
						array( $this->post_type ),
						$query->query_vars['post_type']
					)
				);
			}
		}

		/**
		 * Render the icon column, showing the post thumbnail.
		 *
		 * @wp-hook  manage_posts_custom_column
		 * @param string  $column  The current column
		 * @param int     $post_id The current post id
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_manage_posts_custom_column_show_thumbnail( $column, $post_id ) {
			if ( get_post_type($post_id) !== $this->post_type ) {
				return;
			}

			if ( 'icon' === $column ) {
				$thumb = wp_get_attachment_image( get_post_thumbnail_id( $post_id ), array( 80, 60 ), true );
				if ( $thumb ) {
					if (
						( isset( $_REQUEST['post_status'] ) && 'trash' == $_REQUEST['post_status'] )||
						! current_user_can( 'edit_post', $post_id )
					) {
						echo $thumb;
					} else {
?>
					<a href="<?php echo get_edit_post_link( $post_id, true ); ?>" title="<?php echo esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), _draft_or_post_title() ) ); ?>">
						<?php echo $thumb; ?>
					</a>
<?php
					}
				}

			}
		}

		/**
		 * Echo a link element with rel="cannonical" with an
		 * url to the post archieve on archieve pages.
		 *
		 * @todo     Review logic.
		 *
		 * @wp-hook  wp_head
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_wp_head_add_canonical_link() {
			if ( get_query_var( 'post_type' ) == $this->post_type && !is_single() ) {
				echo '<link rel="canonical" href="'.get_bloginfo( 'url' ).'/'.$this->slug.'/"/>';
			}
		}


		// ======================================
		// Filter callbacks
		// ======================================

		/**
		 * Filter the enter title here placeholder for the
		 * current CPT.
		 *
		 * @wp-hook  enter_title_here
		 * @param string  $label The current title placeholder text
		 * @param object  $post  The post being edited
		 * @return   string          The filtered title placeholder text
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_enter_title_here_placeholder( $label, $post ) {
			if ( $post->post_type == $this->post_type ) {
				$label = $this->title_placeholder;
			}
			return $label;
		}

		/**
		 * Create a shortlink for this post type.
		 *
		 * @wp-hook  pre_get_shortlink
		 * @param string  $shortlink   The shortlink to be filtered
		 * @param int     $id          The id of the current object
		 * @param string  $context     The context (can be post, blog, meta id or query)
		 * @param boolean $allow_slugs Whether slugs are allowed in the shortlink - not used
		 * @return   string                 The new shortlink or $shortlink  if not this->post_type
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_pre_get_shortlink_enable( $shortlink, $id, $context, $allow_slugs ) {
			$post_id = 0;

			if ( 'query' == $context && is_singular( $this->post_type ) ) {
				// If context is query use current queried object for ID
				$post_id = get_queried_object_id();
			} elseif ( 'post' == $context ) {
				// If context is post use the passed $id
				$post = get_post( $id );
				$post_id = $post->ID;
			}

			// Only do something if the post is of this post type
			if ( $this->post_type == get_post_type( $post_id ) ) {
				$shortlink = home_url( '?p=' . $post_id );
			}

			return $shortlink;
		}

		/**
		 * Filter the featured image meta box to change the
		 * featured image link.
		 *
		 * @wp-hook  admin_post_thumbnail_html
		 * @param string  $content The featured image meta box content
		 * @return   string          The filtered content
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_admin_post_thumbnail_html_rename_featured_image_link( $content ) {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$post = get_post( absint( $_REQUEST['post_id'] ) );
				$post_type = $post->post_type;
			} else {
				$post_type = get_current_screen()->post_type;
			}

			if ( $post_type == $this->post_type ) {
				$content = str_replace( __( 'Set featured image' ), $this->featured_image_link_add, $content );
				$content = str_replace( __( 'Remove featured image' ), $this->featured_image_link_remove, $content );
			}
			return $content;
		}

		/**
		 * Change the media uploader strings to reflect our post type
		 *
		 * Filter th_cpt_default_media_view_strings
		 * Filter th_cpt_media_view_strings-$post_type
		 *
		 * @wp-hook  admin_post_thumbnail_html
		 * @param array   $strings The media view strings
		 * @param object  $post    The current post
		 * @return   array             The filtered media view strings
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_media_view_strings_featured_image_link( $strings,  $post ) {
			$screen = get_current_screen();
			if ( $this->post_type == $screen->post_type ) {
				$custom_messages                          = array();
				$custom_messages['setFeaturedImageTitle'] = __( 'Set Featured Image' );
				$custom_messages['setFeaturedImage']      = __( 'Set featured image' );
				$custom_messages['insertIntoPost']        = sprintf( __( 'Insert into %s', 'text_domain' ), $this->post_type_args['label'] );
				$custom_messages['uploadedToThisPost']    = sprintf( __(  'Uploaded to this %s', 'text_domain' ), $this->post_type_args['label'] );

				$custom_messages = apply_filters( 'th_cpt_default_media_view_strings', $custom_messages, $this->post_type_args['labels']['singular_name'], $this->post_type_args['labels']['name'] );

				if ( !empty( $this->featured_image_link ) ) {
					$custom_messages['setFeaturedImageTitle'] = ucwords( $this->featured_image_link );
					$custom_messages['setFeaturedImage']      = $this->featured_image_link;
				}

				$custom_messages = apply_filters( 'th_cpt_media_view_strings-' . $this->post_type, $custom_messages, $this->post_type_args['labels']['singular_name'], $this->post_type_args['labels']['name'] );

				$strings = wp_parse_args( $custom_messages, $strings );
			}

			return $strings;
		}

		/**
		 * Change the post updated messages to reflect our post type
		 *
		 * Filter th_cpt_default_post_updated_messages
		 * Filter th_cpt_post_updated_messages-$post_type
		 *
		 * @wp-hook  post_updated_messages
		 * @param array   $messages The post updated messages
		 * @return   array             The filtered post updated messages
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_post_updated_messages( $messages ) {
			global $post, $post_ID;
			$uc_singular = $this->post_type_args['labels']['singular_name'];
			$uc_plural   = $this->post_type_args['labels']['name'];
			$singular    = strtolower( $uc_singular );
			$plural      = strtolower( $uc_plural );

			$cpt_messages = array(
				0 => '', // Unused. Messages start at index 1.
				1 => sprintf( __( '%1s updated. <a href="%2s">View %3s</a>', 'text_domain' ), $uc_singular, esc_url( get_permalink( $post_ID ) ), $singular ),
				2 => __( 'Custom field updated.', 'text_domain' ),
				3 => __( 'Custom field deleted.', 'text_domain' ),
				4 => sprintf( __( '%1$s updated.', 'text_domain' ), $uc_singular ),
				/* translators: %s: date and time of the revision */
				5 => isset( $_GET['revision'] ) ? sprintf( __( '%s$1 restored to revision from %s$2', 'text_domain' ), $uc_singular, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => sprintf( __( '%1$s published. <a href="%2$s">View %3$s</a>', 'text_domain' ), $uc_singular, esc_url( get_permalink( $post_ID ) ), $singular ),
				7 => sprintf( __( '%1$s saved.', 'your_text_domain' ), $uc_singular ),
				8 => sprintf( __( '%1$s submitted. <a target="_blank" href="%2$s">Preview %3$s</a>', 'text_domain' ), $uc_singular, esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ), $singular ),
				9 => sprintf( __( '%1$s scheduled for: <strong>%2$s</strong>. <a target="_blank" href="%3$s">Preview %4$s</a>', 'text_domain' ),
					// translators: Publish box date format, see http://php.net/date
					$uc_singular, date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ), $singular ),
				10 => sprintf( __( '%1$s draft updated. <a target="_blank" href="%2$s">Preview %3$s</a>', 'text_domain' ), $uc_singular, esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ), $singular ),
			);

			$cpt_messages = apply_filters( 'th_cpt_default_post_updated_messages', $cpt_messages, $singular, $plural, $post, $post_ID );
			$cpt_messages = apply_filters( 'th_cpt_post_updated_messages-' . $this->post_type, $cpt_messages, $singular, $plural, $post, $post_ID );

			$messages[$this->post_type] = $cpt_messages;

			return $messages;
		}

		/**
		 * Set the default post title.
		 *
		 * @wp-hook  default_title
		 * @param string  $title The current default title
		 * @param object  $post  The current post
		 * @return   string          The new default title
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_default_title_set( $title, $post ) {
			if ( $this->post_type == $post->post_type ) {
				$title = $this->default_title;
			}

			return $title;
		}

		/**
		 * Set the default post content.
		 *
		 * @wp-hook  default_content
		 * @param string  $content The current default content
		 * @param object  $post    The current post
		 * @return   string            The new default content
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_default_content_set( $content, $post ) {
			if ( $this->post_type == $post->post_type ) {
				$content = $this->default_content;
			}

			return $content;
		}

		/**
		 * Add posts from this CPT to the page_on_front dropdown.
		 *
		 * @wp-hook  get_pages
		 * @param array   $pages Current pages to be displayed in the dropdown
		 * @param array   $r     The dropdown config
		 * @return   array          Current pages plus the posts from this cpt to be displayed in the dropdown
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_get_pages_add_cpt_to_dropdown( $pages, $r ) {
			if ( isset( $r['name'] ) && 'page_on_front' == $r['name'] ) {
				$args = array(
					'post_type' => $this->post_type
				);
				$cpts = get_posts( $args );
				$pages = array_merge( $pages, $cpts );
			}

			return $pages;
		}

		/**
		 * Adds post type related classes to the <body> element when
		 * a frontpage is of our CPT.
		 *
		 * Filter th_cpt_body_class
		 *
		 * @wp-hook  body_class
		 * @param array   $classes An array of classes passed to this filter by WordPress.
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_body_class_add_cpt_class( $classes ) {
			// If our post type is being called, add our classes to the <body> element
			if (
				get_query_var( 'post_type' ) === $this->post_type ||
				(
					// Add class to body if our CPT is used as static homepage
					'page' == get_option( 'show_on_front' ) &&
					get_option( 'page_on_front' ) &&
					get_option( 'page_on_front' ) == get_query_var( 'page_id' ) &&
					get_post_type( get_option( 'page_on_front' ) ) == $this->post_type
				)
			) {
				$cpt_classes = array(
					sanitize_html_class( $this->post_type ),
					sanitize_html_class( 'type-' . $this->post_type )
				);
				$cpt_classes = apply_filters( 'th_cpt_body_class', $cpt_classes, $this->post_type );

				// Remove class page from array. This will only be there if
				// this CPT is set as custom front page.
				$classes = array_merge( array_diff( $classes, array( 'page' ) ) );

				$classes = array_merge( $classes, $cpt_classes );
			}
			return $classes;
		}

		/**
		 * Register the post thumbnail column.
		 *
		 * @wp-hook  manage_cpt_posts_columns
		 * @param array   $columns Current columns
		 * @return   array            New columns including icon
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_manage_cpt_posts_columns_add_thumbnail( $columns ) {
			// insert thumbnail column after checkbox column with no header text
			$res = array_slice( $columns, 0, 1, true ) +
				array( 'icon' => '' ) +
				array_slice( $columns, 1, count( $columns ) - 1, true );
			return $res;
		}

		/**
		 * Include cpt templates from within plugin if not
		 * exist in theme.
		 *
		 * @wp-hook  template_include
		 * @param string  $template The requested template
		 * @return   string             The requested template if it exists in the theme else the plugin template
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_template_include_template_override( $template ) {
			// If our post type is being called, customize which template is included.
			if ( is_singular( $this->post_type ) ) { // single
				$single = locate_template( array( 'single-' . $this->post_type . '.php' ) );
				if ( !$single ) {
					$template =  $this->post_templates['single'];
				}
			} elseif ( is_post_type_archive( $this->post_type ) ) { // archive
				$archive = locate_template( array( 'archive-' . $this->post_type . '.php' ) );
				if ( !$archive ) {
					$template =  $this->post_templates['archive'];
				}
			}
			return $template;
		}


		// ======================================
		// Private methods
		// ======================================

		/**
		 * Create default labels based on singular & plural and merge args
		 * with defaults.
		 *
		 * Filter    th_cpt_default_cpt_labels
		 * Filter    th_cpt_default_cpt_args
		 *
		 * @param string  $singular post type singular name
		 * @param string  $plural   post type plural name
		 * @param array   $args     post type arguments
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		private function init_args( $singular, $plural, $args ) {
			$uc_singular = ucfirst( $singular );
			$uc_plural   = ucfirst( $plural );
			$def_labels  = array(
				'name'                => _x( $uc_plural, 'post type general name', 'text_domain' ),
				'singular_name'       => _x( $uc_singular, 'post type singular name', 'text_domain' ),
				'add_new'             => sprintf( __( 'New %s', 'text_domain' ), $uc_singular ),
				'add_new_item'        => sprintf( __( 'Add New %s', 'text_domain' ), $uc_singular ),
				'edit_item'           => sprintf( __( 'Edit %s', 'text_domain' ), $uc_singular ),
				'new_item'            => sprintf( __( 'New %s', 'text_domain' ), $uc_singular ),
				'view_item'           => sprintf( __( 'View %s', 'text_domain' ), $uc_singular ),
				'menu_name'           => __( $uc_plural, 'text_domain' ),
				'all_items'           => sprintf( __( 'All %s', 'text_domain' ), $uc_plural ),
				'search_items'        => sprintf( __( 'Search %s', 'text_domain' ), $plural ),
				'not_found'           => sprintf( __( 'No %s found', 'text_domain' ), $plural ),
				'not_found_in_trash'  => sprintf( __( 'No %s found in Trash', 'text_domain' ), $plural ),
				'parent_item_colon'   => sprintf( __( 'Parent %s:', 'text_domain' ), $uc_singular ),
			);

			$def_args = array(
				'label'                => __( $uc_plural, 'text_domain' ),
				'description'          => sprintf( __( '%s information pages', 'text_domain' ), $uc_singular ),
				'publicly_queryable'   => true,
				'exclude_from_search'  => false,
				'capability_type'      => 'post',
				'capabilities'         => array(),
				'map_meta_cap'         => true,
				'hierarchical'         => false,
				'public'               => true,
				'rewrite'              => true,
				'has_archive'          => true,
				'query_var'            => true,
				'supports'             => array( 'title', 'editor' ),
				'register_meta_box_cb' => null,
				'taxonomies'           => array(),
				'show_ui'              => true,
				'menu_position'        => 5,
				'menu_icon'            => null,
				'can_export'           => true,
				'show_in_nav_menus'    => true,
				'show_in_menu'         => true,
				'show_in_admin_bar'    => true,
				'delete_with_user'     => false,
			);

			// wp_parse_args is not recursive, so extract labels
			// from $args and merge with defaults.
			$labels = isset( $args['labels'] ) ? $arg['labels'] : array();
			$labels = wp_parse_args(
				$labels,
				apply_filters( 'th_cpt_default_cpt_labels', $def_labels, $singular, $plural )
			);

			$args = wp_parse_args(
				$args,
				apply_filters( 'th_cpt_default_cpt_args', $def_args, $singular, $plural )
			);

			$args['labels'] = $labels;

			$this->post_type_args = $args;

			$this->maybe_add_thumbnail_theme_support();

			// Error messages
			$this->error_messages = apply_filters(
				'th_cpt_error_messages',
				array(
					'title'     => __( '%s cannot be empty', 'text_domain' ),
					'editor'    => __( '%s cannot be empty', 'text_domain' ),
					'thumbnail' => __( '%1$s must have a %2$s', 'text_domain' ),
					'excerpt'   => __( '%s cannot be empty', 'text_domain' )
				)
			);
		}

		/**
		 * Make sure the features are supported by the CPT.
		 *
		 * @param array   $features Features to be supported
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		private function ensure_cpt_supports( array $features ) {
			$allowed = array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'page-attributes', 'post-formats' );
			$features = array_intersect( $features, $allowed );

			// Make sure featured image is supported by this CPT
			$supports = $this->post_type_args['supports'];
			if ( !is_array( $supports ) ) {
				$supports = $features;
			} else {
				$supports = array_unique( array_merge( $features, $supports ) );
			}
			$this->post_type_args['supports'] = $supports;

			$this->maybe_add_thumbnail_theme_support();
		}

		/**
		 * Add theme support for post thumbnails if required.
		 *
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		private function maybe_add_thumbnail_theme_support() {
			if ( is_array( $this->post_type_args['supports'] )
				&& in_array( 'thumbnail', $this->post_type_args['supports'] )
				&& !current_theme_supports( 'post-thumbnails' ) ) {
				add_theme_support( 'post-thumbnails' );
			}
		}

		/**
		 * Add a taxonomy to the post_type_args if it not already exists.
		 *
		 * @param string  $taxonomy_name The taxonomy name to be added
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		private function add_taxonomy_to_post_type_args( $taxonomy_name ) {
			// Add the taxonomy to the post_type_args
			if ( !is_array( $this->post_type_args['taxonomies'] ) ) {
				if ( empty( $this->post_type_args['taxonomies'] ) ) {
					$this->post_type_args['taxonomies'] = array( $taxonomy_name );
				} else {
					$this->post_type_args['taxonomies'] = array( $this->post_type_args['taxonomies'] );
					if ( !in_array( $taxonomy_name, $this->post_type_args['taxonomies'] ) ) {
						$this->post_type_args['taxonomies'][] = $taxonomy_name;
					}
				}
			} elseif ( !in_array( $taxonomy_name, $this->post_type_args['taxonomies'] ) ) {
				$this->post_type_args['taxonomies'][] = $taxonomy_name;
			}
		}

	} // end of class TH_CPT

} // end of if ( !class_exists( 'TH_CPT' ) )
