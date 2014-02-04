<?php
/**
 * WordPress Custom Taxonomies
 *
 * Contains the TH_CT class. Requires PHP version 5+ and WordPress version 3.5 or greater.
 *
 * @version   0.1.0
 * @package   TH CPT
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL 2.0+ - http://www.gnu.org/licenses/gpl.txt
 * @link      https://github.com/thuijssoon/
 * @copyright Copyright (c) 2013 - Thijs Huijssoon
 */

if ( !class_exists( 'TH_CT' ) ) {

	/*
	 * WordPress Custom Taxonomy Class
	 *
	 * A class that handles the registration of WordPress custom taxonomies and takes care of all the
	 * dirty work for you.
	 *
	 * @package TH CPT
	 */
	class TH_CT {

		/**
		 * Array of TH_CT objects for easy access
		 * and to avoid polluting the global namespace.
		 *
		 * @var array
		 */
		private static $instances = array();

		/**
		 * Custom Taxonomy (max. 32 characters, can not
		 * contain capital letters or spaces)
		 *
		 * @var string
		 */
		private $taxonomy_name;

		/**
		 * Array of arguments passed to register_post_type
		 *
		 * @var array
		 */
		private $taxonomy_args;

		/**
		 * Array of post types to add this taxonomy to
		 *
		 * @var array
		 */
		private $post_types;

		/**
		 * Admin configuration
		 *
		 * @var array
		 */
		private $admin_args;

		/**
		 * For which types should we show a column
		 *
		 * @var array
		 */
		private $show_column_for_types = array();

		/**
		 * For which types should we show a filter
		 *
		 * @var array
		 */
		private $show_filter_for_types = array();

		/**
		 * Which types must have at least one term associated
		 *
		 * @var array
		 */
		private $required_for_types = array();

		/**
		 * For which types must we show a metabox
		 *
		 * @var array
		 */
		private $show_metabox_for_types = array();


		// ======================================
		// Constructor
		// ======================================

		/**
		 * Create a new custom taxonomy.
		 *
		 * @param string|array $post_types    The post type to register the taxonomy for
		 * @param string  $taxonomy_name The taxonomy name
		 * @param string  $singular      The singular name
		 * @param string  $plural        The plural name
		 * @param array   $taxonomy_args Arguments passed to the register_taxonomy function
		 * @param array   $admin_args    The post type specific admin args: show_column, show_filter, show_metabox, required, show_in_content_table, default, sortable
		 * @link    http://codex.wordpress.org/Function_Reference/register_taxonomy
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function __construct( $post_types, $taxonomy_name, $singular, $plural, $taxonomy_args = array(), $admin_args = array() ) {
			global $wp_version;
			// Let's motivate people to upgrade
			if ( version_compare( $wp_version, '3.5', '<' ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>%1$s requires WordPress 3.5 in order to work.</strong> You\'re running WordPress %2$s. You\'ll need to upgrade in order to use this file. If you need help upgrading WordPress you can refer to <a href="http://codex.wordpress.org/Upgrading_WordPress">the WordPress Codex</a>.', 'text_domain' ), __CLASS__, $wp_version ) );
				}
				return;
			}

			if ( isset( self::$instances[$taxonomy_name] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>%1$s has already been defined.</strong>', 'text_domain' ), $taxonomy_name ) );
				}
				return;
			}

			if ( empty( $post_types ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Post type cannot be empty.</strong>', 'text_domain' ) );
				}
				return;
			}

			// TODO: should we do some checking of builtin post types?
			// http://codex.wordpress.org/Function_Reference/get_post_types#Notes
			// $builtin_post_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );

			if ( !is_array( $post_types ) ) {
				$post_types = (array) $post_types;
			}
			$this->post_types = $post_types;

			// Reserved terms that WordPress wont let us use as taxonomy ID.
			// The list can be found here: http://codex.wordpress.org/Function_Reference/register_taxonomy#Reserved_Terms
			$reserved = array( 'attachment', 'attachment_id', 'author', 'author_name', 'calendar', 'cat', 'category', 'category__and', 'category__in', 'category__not_in', 'category_name', 'comments_per_page', 'comments_popup', 'customize_messenger_channel', 'customized', 'cpage', 'day', 'debug', 'error', 'exact', 'feed', 'hour', 'link_category', 'm', 'minute', 'monthnum', 'more', 'name', 'nav_menu', 'nonce', 'nopaging', 'offset', 'order', 'orderby', 'p', 'page', 'page_id', 'paged', 'pagename', 'pb', 'perm', 'post', 'post__in', 'post__not_in', 'post_format', 'post_mime_type', 'post_status', 'post_tag', 'post_type', 'posts', 'posts_per_archive_page', 'posts_per_page', 'preview', 'robots', 's', 'search', 'second', 'sentence', 'showposts', 'static', 'subpost', 'subpost_id', 'tag', 'tag__and', 'tag__in', 'tag__not_in', 'tag_id', 'tag_slug__and', 'tag_slug__in', 'taxonomy', 'tb', 'term', 'theme', 'type', 'w', 'withcomments', 'withoutcomments', 'year' );

			if ( in_array( $taxonomy_name, $reserved ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>%1$s is a reserved word and cannot be used as ID for a custom taxonomy.</strong> If you want to know which are the reserved words, please refer to <a href="http://codex.wordpress.org/Function_Reference/register_taxonomy#Reserved_Terms">the WordPress Codex</a>', 'text_domain' ), $taxonomy_name ) );
				}
				return;
			}

			// According to the DB schema of WordPress, $taxonomy_name can't be longer than 32 characters
			// http://codex.wordpress.org/Function_Reference/register_taxonomy#Parameters
			if ( strlen( $taxonomy_name ) > 32 ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>"%1$s" (length: %2$d) is longer than the allowed 20 characters for a custom taxonomy name.</strong> If you want to know more about taxonomy naming best practices, please refer to <a href="http://codex.wordpress.org/Function_Reference/register_taxonomy#Parameters">the WordPress Codex</a>', 'text_domain' ), $taxonomy_name, strlen( $taxonomy_name ) ) );
				}
				return;
			}

			$this->taxonomy_name = $taxonomy_name;

			$this->init_args( $singular, $plural, $taxonomy_args, $admin_args );

			add_action( 'init', array( $this, 'acb_init_taxonomy_init' ), 0 );

			// Replace the description field with an editor
			add_action( 'admin_print_footer_scripts', array( $this, 'acb_admin_print_footer_scripts_hide_description' ), 0 );
			add_action( $taxonomy_name . '_add_form_fields', array( $this, 'acb_replace_description_with_wysiwyg' ), 0 );
			add_action( $taxonomy_name . '_edit_form_fields', array( $this, 'acb_replace_description_with_wysiwyg_table' ), 0 );
			remove_filter( 'pre_term_description', 'wp_filter_kses' );
			remove_filter( 'term_description', 'wp_kses_data' );
			add_filter( 'pre_term_description', 'wp_filter_post_kses' );
			add_filter( 'term_description', 'wp_kses_post' );
			
			// Store $this in static instances array for easy access
			// without polluting the global namespace.
			self::$instances[ $taxonomy_name ] = $this;
		}


		// ======================================
		// Public chainable methods
		// ======================================

		/**
		 * Add the CT name and count to the Right Now content table
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

			// Priority 11, so below post types
			add_action( 'right_now_content_table_end', array( $this, 'acb_right_now_content_table_end_add_ct' ), 11 );
			return $this;
		}

		/**
		 * Show a taxonomy column for this taxonomy on the 'All ...' screen
		 * given post type.
		 * 
		 * @param   array|string   $post_types   The post types for which to display the column
		 * @param   array|boolean  $column_args  Array with keys: display_after, label & sortable.
		 * @return  $this                        This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function show_column_for_types( $post_types, $column_args = true ) {
			if ( empty( $post_types ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Post type cannot be empty.</strong>', 'text_domain' ) );
				}
				return;
			}

			if ( !is_array( $post_types ) ) {
				$post_types = (array) $post_types;
			}

			$default_column_args = apply_filters(
				'th_ct_default_column_args',
				array(
					'display_after' => 'default',
					'label'         => $this->taxonomy_args['labels']['singular_name'],
					'sortable'      => true,
				)
			);

			if ( true === $column_args ) {
				// Use the default values
				$column_args = $default_column_args;
			} else {
				$column_args = wp_parse_args( $column_args, $default_column_args );
			}

			foreach ( $post_types as $post_type ) {
				if ( !isset( $this->show_column_for_types[$post_type] ) ) {
					$this->show_column_for_types[$post_type] = $column_args;
				}
			}

			return $this;
		}

		/**
		 * Show a taxonomy filter for this taxonomy on the 'All ...' screen
		 * given post type.
		 * 
		 * @param   array|string   $post_types   The post types for which to display the filter
		 * @param   string         $name         Used in the dropdown text 'View all $name'
		 * @return  $this                        This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function show_filter_for_types( $post_types, $name ) {
			if ( empty( $post_types ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Post type cannot be empty.</strong>', 'text_domain' ) );
				}
				return;
			}

			if ( empty( $name ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Name cannot be empty.</strong>', 'text_domain' ) );
				}
				return;
			}

			if ( !is_array( $post_types ) ) {
				$post_types = (array) $post_types;
			}

			foreach ( $post_types as $post_type ) {
				if ( !isset( $this->show_filter_for_types[$post_type] ) ) {
					$this->show_filter_for_types[$post_type] = $name;
				}
			}

			return $this;
		}

		/**
		 * Make this texonomy required for the given post types.
		 * If not present the default term will be assigned once
		 * the post type is published.
		 * 
		 * @param   array|string   $post_types   The post types for which to display the filter
		 * @return  $this                        This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function required_for_types( $post_types ) {

			if ( empty( $post_types ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Post type cannot be empty.</strong>', 'text_domain' ) );
				}
				return;
			}

			if ( !is_array( $post_types ) ) {
				$post_types = (array) $post_types;
			}

			$this->required_for_types = array_unique(
				array_merge(
					$this->required_for_types,
					$post_types
				)
			);

			return $this;
		}

		/**
		 * Show a taxonomy meta box for the given post types.
		 * If sortable is set to true a select2 metabox will be used.
		 * 
		 * @param   array|string   $post_types   The post types for which to display the filter
		 * @return  $this                        This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function show_metabox_for_types( $post_types ) {

			if ( empty( $post_types ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Post type cannot be empty.</strong>', 'text_domain' ) );
				}
				return;
			}

			if ( !is_array( $post_types ) ) {
				$post_types = (array) $post_types;
			}

			$this->show_metabox_for_types = array_unique(
				array_merge(
					$this->show_metabox_for_types,
					$post_types
				)
			);

			return $this;
		}

		/**
		 * Add this taxonomy to a given post type.
		 * 
		 * @param   array|string   $post_types   The post types for which to display the filter
		 * @param   array          $admin_args   The post type specific admin args: show_column, show_filter, show_metabox, required, show_in_content_table, default, sortable
		 * @return  $this                        This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function add_taxonomy_to_post_type( $post_type, $admin_args ) {
			if ( !isset( $this->post_types ) ) {
				$this->post_types = array( $post_type );
			} elseif ( !in_array( $post_type, $this->post_types ) ) {
				$this->post_types[] = $post_type;
			} else {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>Taxonomy %1$s is already connected with post type %2$s.</strong>', 'text_domain' ), $this->taxonomy_name, $post_type ) );
				}
				return $this;
			}

			if ( is_admin() ) {

				$default_admin_args = array(
					'show_column'           => true,
					'show_filter'           => $this->taxonomy_args['labels']['name'],
					'show_metabox'          => true,
					'required'              => false,
				);
				$admin_args = wp_parse_args(
					$admin_args,
					apply_filters( 'th_ct_default_admin_args', $default_admin_args, $this->taxonomy_name )
				);

				if ( $admin_args['show_column'] ) {
					$this->show_column_for_types( $post_type, $admin_args['show_column'] );
				}
				if ( false !== $admin_args['show_filter'] ) {
					$this->show_filter_for_types( $post_type, $admin_args['show_filter'] );
				}
				if ( $admin_args['show_metabox'] ) {
					$this->show_metabox_for_types( $post_type );
				}
				if ( $admin_args['required'] ) {
					$this->required_for_types( $post_type );
				}
			}

			return $this;
		}


		// ======================================
		// Static methods
		// ======================================

		/**
		 * Retrieve the TH_CT instance for the taxonomy
		 *
		 * @param string  $taxonomy_name The name of the custom taxonomy
		 * @return TH_CT                 The associated TH_CT instance
		 */
		public static function get_instance( $taxonomy_name ) {
			if ( empty( $taxonomy_name ) || !isset( self::$instances[ $taxonomy_name ] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>Custom taxonomy %1$s is not recognised.</strong>', 'text_domain' ), $taxonomy_name ) );
				}
				return false;
			}

			return self::$instances[ $taxonomy_name ];
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
		 * register_activation_hook( __FILE__, array( 'TH_CT', 'activate' ) );
		 *
		 * In themes use:
		 * add_action( 'after_switch_theme', array( 'TH_CT', 'activate' ) );
		 *
		 * Make sure that you have created your CTS BEFORE these actions run,
		 * otherwise nothing will happen.
		 */
		public static function activate( $taxonomy_names ) {
			// First register all the CTs if not already done
			// and create default terms if needed
			foreach ( $taxonomy_names as $taxonomy_name ) {
				$instance = self::$instances[$taxonomy_name];
				self::insert_default_term( $instance );
			}
			// rewrite rules can't be flushed during switch to blog
			delete_option( 'rewrite_rules' );
		}

		/**
		 * Flushes rewrite rules on deactivation to remove rules associated with this post type.
		 *
		 * In plugins use:
		 * register_deactivation_hook( __FILE__, array( 'TH_CT', 'deactivate' ) );
		 *
		 * In themes use:
		 * add_action( 'switch_theme', array( 'TH_CT', 'deactivate' ) );
		 *
		 * Make sure that you have NOT created your CTs before these actions run,
		 * otherwise nothing will happen.
		 *
		 * @see activate
		 *
		 */
		public static function deactivate( $taxonomy_names = array() ) {
			// rewrite rules can't be flushed during switch to blog
			delete_option( 'rewrite_rules' );
		}

		/**
		 *
		 *
		 * @todo   Remove default term options?
		 * @return [type] [description]
		 */
		public static function uninstall( $taxonomy_names = array() ) {

		}


		// ======================================
		// Action & filter callbacks
		// ======================================

		/**
		 * Action callback to register the taxonomy and
		 * add required filters & actions.
		 *
		 * @wp-hook  init
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */		
		public function acb_init_taxonomy_init() {
			if ( taxonomy_exists( $this->taxonomy_name ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( '<strong>%1$s has already been defined.</strong>', 'text_domain' ), $this->taxonomy_name ) );
				}
				return;
			}
			if ( 0 == count( $this->post_types ) ) {
				if ( WP_DEBUG ) {
					// TODO: update message once decided how to connecr post types.
					trigger_error( sprintf( __( '<strong>%1$s has not been assigned to any post types.</strong>', 'text_domain' ), $this->taxonomy_name ) );
				}
				return;
			}
			register_taxonomy( $this->taxonomy_name, $this->post_types, $this->taxonomy_args );

			// Interconnect taxonomies and custome post types. Better be safe than sorry:
			// http://codex.wordpress.org/Function_Reference/register_taxonomy#Usage
			foreach ( $this->post_types as $post_type ) {
				register_taxonomy_for_object_type( $this->taxonomy_name, $post_type );
			}

			if ( is_admin() ) {
				if ( count( $this->show_column_for_types ) ) {
					foreach ( $this->show_column_for_types as $type => $column_args ) {
						add_filter( 'manage_' . $type . '_posts_columns', array( $this, 'fcb_manage_post_columns_add_taxonomy' ) );
						add_filter( 'manage_edit-' . $type . '_sortable_columns', array( $this, 'fcb_manage_edit_sortable_columns_add_taxonomy' ) );
						add_action( 'manage_' . $type . '_posts_custom_column', array( $this, 'acb_manage_posts_custom_column_add_taxonomy' ), 10, 2 );
					}
					add_filter( 'posts_clauses', array( $this, 'fcb_posts_clauses_sort_taxonomy_column' ), 10, 2 );
				}
				if ( count( $this->show_filter_for_types ) ) {
					// Add actions for filtering
					add_action( 'restrict_manage_posts', array( $this, 'acb_restrict_manage_posts_add_taxonomy' ) );
				}
				if ( count( $this->required_for_types ) ) {
					// Add save action just before error handling
					add_action( 'save_post', array( $this, 'acb_transition_post_status_add_term' ), 998, 2 );
				}
				if ( $this->taxonomy_args['sort'] ) {
					add_action( 'add_meta_boxes', array( $this, 'acb_add_meta_boxes_select2' ) );
					add_action( 'admin_enqueue_scripts', array( $this, 'acb_admin_enqueue_scripts_select2' ) );
					add_action( 'save_post', array( $this, 'acb_save_post_meta_boxes_select2' ) );
				}
				add_action( 'add_meta_boxes', array( $this, 'acb_add_meta_boxes_remove' ) );
			}
		}

		/**
		 * Action callback to add CT to the Right Now table on the dashboard.
		 *
		 * @wp-hook  right_now_content_table_end
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_right_now_content_table_end_add_ct() {
			$num_terms = wp_count_terms( $this->taxonomy_name );
			$num = number_format_i18n( $num_terms );
			$text = _n( $this->taxonomy_args['labels']['singular_name'], $this->taxonomy_args['labels']['name'],  $num );

			// TODO: maybe change permission if custom roles / capabilities added.
			if ( current_user_can( 'manage_categories' ) ) {
				$num = '<a href="edit-tags.php?taxonomy=' . $this->taxonomy_name . '">' . $num . '</a>';
				$text = '<a href="edit-tags.php?taxonomy=' . $this->taxonomy_name . '">' . $text . '</a>';
			}
			echo '<tr>';
			echo '<td class="first b b-'. $this->taxonomy_name . '">' . $num . '</td>';
			echo '<td class="t ' . $this->taxonomy_name . '">' . $text . '</td>';
			echo '</tr>';
		}

		/**
		 * Register the taxonomy column.
		 *
		 * @wp-hook  manage_post_columns
		 * @param    array   $columns Current columns
		 * @return   array            New columns including taxonomy if so desired
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_manage_post_columns_add_taxonomy( $columns ) {

			$post_type = get_current_screen()->post_type;

			// Cater for ajax requests following quick edit update.
			if(empty($post_type)) {
				$post_type = $_POST['post_type'];
			}

			$column_args = $this->show_column_for_types[$post_type];

			// Insert the column after the last taxonomy column
			// or if none exists after the title column
			$found = -1;
			$current = 0;

			if ( 'default' != $column_args['display_after'] ) {
				$found = array_search( $column_args['display_after'], array_keys( $columns ) );
			}

			if ( -1 == $found ) {
				foreach ( $columns as $key => $value ) {
					if ( 'tax' == substr( $key, 0, 3 ) ) { // $key starts with 'tax'
						$found = $current;
					}
					$current++;
				}
				if ( -1 == $found ) {
					$found = array_search( 'title', array_keys( $columns ) );
				}
			}

			$found++;

			$new_columns = array_slice( $columns, 0, $found );
			$new_columns['tax-' . $this->taxonomy_name] = $column_args['label'];
			$new_columns = array_merge( $new_columns, array_slice( $columns, $found ) );

			return $new_columns;
		}

		/**
		 * Render the taxonomy column, showing the assigned taxonomy terms.
		 *
		 * @wp-hook  manage_posts_custom_column
		 * @param    string  $column  The current column
		 * @param    int     $post_id The current post id
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_manage_posts_custom_column_add_taxonomy( $column, $post_id ) {
			if ( 'tax-' . $this->taxonomy_name == $column ) {
				$screen  = get_current_screen();
				$type    = get_post_type( $post_id );

				$terms   = wp_get_object_terms( $post_id, $this->taxonomy_name, array( 'orderby' => 'term_order',  'fields' => 'all' ) );
				$label   = get_post_type_object( $type )->labels->name;

				if ( $terms ) {
					foreach ( $terms as $term ) {
						if ( isset( $this->show_filter_for_types[$type] ) ) {
							$title  = sprintf(
								__( 'Filter %1$s by %2$s: %3$s', 'text_domain' ),
								strtolower( $label ),
								strtolower( $this->taxonomy_args['labels']['singular_name'] ),
								strtolower( $term->name )
							);
							$name[] = '<a href="' . esc_url( 'edit.php?post_type='. $type . '&'. $this->taxonomy_name . '=' . $term->slug ) . '" title="'. esc_attr( $title ) . '">' . esc_html( $term->name ) . '</a>';
						} else {
							$name[] = esc_html( $term->name );
						}
					}
					echo implode( ', ', $name );
				} else {
					echo '—';
				}
			}
		}

		/**
		 * Make the taxonomy column sortable if so required.
		 * 
		 * @wp-hook  manage_edit_sortable_columns
		 * @param    array   $columns Current columns
		 * @return   array            New columns including taxonomy if so desired
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_manage_edit_sortable_columns_add_taxonomy( $columns ) {
			$post_type = get_current_screen()->post_type;
			$column_args = $this->show_column_for_types[$post_type];

			if ( $column_args['sortable'] ) {
				$columns['tax-' . $this->taxonomy_name] = 'tax-' . $this->taxonomy_name;
			}

			return $columns;
		}

		/**
		 * Sorting on taxonomy admin columns.
		 *
		 * Thanks goes to Scribu: http://scribu.net/wordpress/sortable-taxonomy-columns.html
		 * as well as to the commenter "jessica" on StackExchange for bug fixing:
		 * http://wordpress.stackexchange.com/questions/8811/sortable-admin-columns-when-data-isnt-coming-from-post-meta#comment70352_11256
		 * 
		 * @wp-hook  posts_clauses
		 * @param    array     $clauses   The clauses to be filtered
		 * @param    WP_Query  $wp_query  The current WP_Query object
		 * @return   array                The filtered clauses
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_posts_clauses_sort_taxonomy_column( $clauses, $wp_query ) {
			global $wpdb;

			// Check if we are filtering by a know taxonomy
			$filters_used = false;
			foreach ( self::$instances as $key => $value ) {
				$filters_used = $filters_used ||
					(
					isset( $wp_query->query[$key] ) &&
					'0' <>  $wp_query->query[$key]
				);
			}

			if (
				isset( $wp_query->query['orderby'] ) &&
				'tax-' . $this->taxonomy_name == $wp_query->query['orderby'] &&
				!$filters_used
			) {
				$clauses['groupby']  = 'ID';
				$clauses['orderby']  = 'GROUP_CONCAT(' . $wpdb->terms . '.name ORDER BY name ASC) ';
				$clauses['orderby'] .= ( 'ASC' == strtoupper( $wp_query->get( 'order' ) ) ) ? 'ASC' : 'DESC';
				$clauses['join']    .= <<<SQL
LEFT OUTER JOIN {$wpdb->term_relationships} ON {$wpdb->posts}.ID={$wpdb->term_relationships}.object_id
LEFT OUTER JOIN {$wpdb->term_taxonomy} ON ({$wpdb->term_relationships}.term_taxonomy_id={$wpdb->term_taxonomy}.term_taxonomy_id) AND (taxonomy = '{$this->taxonomy_name}' OR taxonomy IS NULL)
LEFT OUTER JOIN {$wpdb->terms} USING (term_id)
SQL;
			}

			return $clauses;
		}

		/**
		 * Adds a drop down list to the filter section of the 'All ...'
		 * screen for a given custom post type to enable filtering
		 * by taxonomy term.
		 * 
		 * @wp-hook  restrict_manage_posts
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_restrict_manage_posts_add_taxonomy() {
			$screen      = get_current_screen();
			$post_type   = $screen->post_type;

			if ( in_array( $post_type, array_keys( $this->show_filter_for_types ) ) ) {
				wp_dropdown_categories( array(
						'walker'          => new TH_Walker_TaxonomyDropdown(),
						'show_option_all' => sprintf( __( 'View all %1$s', 'text_domain' ), strtolower( $this->show_filter_for_types[$post_type] ) ),
						'taxonomy'        => $this->taxonomy_args['query_var'],
						'name'            => $this->taxonomy_args['query_var'],
						'value'           => 'slug',
						'orderby'         => 'name',
						'selected'        => isset( $_GET[$this->taxonomy_name] ) ? $_GET[$this->taxonomy_name] : '',
						'hierarchical'    => $this->taxonomy_args['hierarchical'],
						'show_count'      => false,
						'hide_empty'      => false
					)
				);
			}
		}

		/**
		 * Add the default term for this taxonomy to a post type
		 * if this taxonomy is indicated as required and no terms
		 * are associated at present.
		 * 
		 * @wp-hook  transition_post_status
		 * @param    string  $new_status  The new post status
		 * @param    string  $old_status  The old post status
		 * @param    object  $post        The current post object
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_transition_post_status_add_term( $post_id, $post ) {
			if ( 'publish' != $post->post_status || !in_array( $post->post_type, $this->required_for_types ) ) {
				return;
			}

			$terms = wp_get_post_terms( $post->ID, $this->taxonomy_name );
			$dt = get_option( 'default_' . $this->taxonomy_name );
			if ( $dt && empty( $terms ) ) {
				wp_set_object_terms( $post->ID, intval($dt), $this->taxonomy_name );
			}
		}

		/**
		 * Removes the standard taxonomy meta box and
		 * adds a select2 style meta box to add terms to
		 * post type.
		 * 
		 * @wp-hook  add_meta_boxes
		 * @uses     select2_meta_box_display
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_add_meta_boxes_select2() {
			$screen = get_current_screen();
			$post_type = $screen->post_type;

			if (
				in_array( $post_type, $this->post_types ) &&
				in_array( $post_type, $this->show_metabox_for_types )
			) {

				// Remove the default taxonomy metabox
				if ( $this->taxonomy_args['hierarchical'] ) {
					remove_meta_box( $this->taxonomy_name . 'div', $post_type, 'side' );
				} else {
					remove_meta_box( 'tagsdiv-' . $this->taxonomy_name, $post_type, 'side' );
				}

				if ( in_array( $post_type, $this->show_metabox_for_types ) ) {
				}
				$taxonomy = get_taxonomy( $this->taxonomy_name );
				if ( current_user_can( $taxonomy->cap->assign_terms ) ) {
					add_meta_box( 'th-tax-box-'. $this->taxonomy_name, $this->taxonomy_args['labels']['name'], array( $this, 'select2_meta_box_display' ), $post_type, 'side', 'default' );
				}
			}
		}

		/**
		 * Removes the standard taxonomy meta box from the
		 * edit post type screen. Useful if you add a meta box
		 * that includes a taxonomy controll.
		 * 
		 * @wp-hook  add_meta_boxes
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_add_meta_boxes_remove() {
			$screen = get_current_screen();
			$post_type = $screen->post_type;

			if ( !in_array( $post_type, $this->post_types ) ) {
				return;
			}

			if ( !in_array( $post_type, $this->show_metabox_for_types ) ) {
				// Remove the default taxonomy metabox
				if ( $this->taxonomy_args['hierarchical'] ) {
					remove_meta_box( $this->taxonomy_name . 'div', $post_type, 'side' );
				} else {
					remove_meta_box( 'tagsdiv-' . $this->taxonomy_name, $post_type, 'side' );
				}
			}
		}

		/**
		 * Render the select2 meta box.
		 * 
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function select2_meta_box_display() {
			$screen = get_current_screen();
			$post_type = $screen->post_type;

			// add more args if you want (e.g. orderby)
			$terms = get_terms( $this->taxonomy_name, array( 'hide_empty' => 0 ) );
			$current_terms = wp_get_object_terms ( get_the_ID(), $this->taxonomy_name,  array( 'orderby' => 'term_order', 'order' => 'ASC', 'fields' => 'all' ) );
			$current_term_ids = array();

			// category needs a special treatment for the input name
			if ( 'category' == $this->taxonomy_name )
				$name = 'post_category';
			else
				$name = 'tax_input[' . $this->taxonomy_name . ']';

			wp_nonce_field( 'th_ct-save-' . $this->taxonomy_name . '-for-' . $post_type . '-' . get_the_ID(), 'th_ct_' . $this->taxonomy_name . '_meta_box_nonce' );
			$output = '';
			$output .= '<label class="screen-reader-text" for="' . $this->taxonomy_name .'">' . sprintf( __( 'Add or remove %s', 'text_domain' ), strtolower( $this->taxonomy_args['labels']['name'] ) ) . '</label>';
			$output .= '<p><select name="' . $name . '[]" class="th_ct-select widefat" data-placeholder="' . sprintf( __( 'Add or remove %s', 'text_domain' ), strtolower( $this->taxonomy_args['labels']['name'] ) ) . '" multiple="multiple">';
			foreach ( $current_terms as $current_term ) {
				// hierarchical taxonomies save by IDs, whereas non save by slugs
				if ( $this->taxonomy_args['hierarchical'] ) {
					$value = $current_term->term_id;
				} else {
					$value = $current_term->slug;
				}
				$output .= '<option value="' . $value . '" selected="selected">' . $current_term->name . '</option>';
				$current_term_ids[] = $current_term->term_id;
			}
			foreach ( $terms as $term ) {
				if ( !in_array( $term->term_id, $current_term_ids ) ) {
					// hierarchical taxonomies save by IDs, whereas non save by slugs
					if ( $this->taxonomy_args['hierarchical'] ) {
						$value = $term->term_id;
					} else {
						$value = $term->slug;
					}
					$output .= '<option value="' . $value . '" >' . $term->name . '</option>';
				}
			}
			$output .= '</select></p>';

			echo $output;
		}

		/**
		 * Save the select2 taxonomy meta box data.
		 * 
		 * @wp-hook  save_post
		 * @param    int  $post_id  The post id of the current post
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_save_post_meta_boxes_select2( $post_id ) {
			// check autosave
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			$post_type   = get_post_type( $post_id );
			$nonce_name  = 'th_ct_' . $this->taxonomy_name . '_meta_box_nonce';
			$nonce_value = 'th_ct-save-' . $this->taxonomy_name . '-for-' . $post_type . '-' . $post_id;

			// verify nonce
			if (
				!isset( $_POST[$nonce_name] ) ||
				!wp_verify_nonce( $_POST[$nonce_name], $nonce_value )
			) {
				return;
			}

			// once again, category gets special treatment
			if ( 'category' === $this->taxonomy_name ) {
				$input = isset( $_POST['post_category'] ) ? $_POST['post_category'] : '';
			}
			else {
				$input = isset( $_POST['tax_input'][$this->taxonomy_name] ) ? $_POST['tax_input'][$this->taxonomy_name] : '';
			}

			if ( empty( $input ) ) {
				$taxonomy = get_taxonomy( $this->taxonomy_name );
				if ( $taxonomy && current_user_can( $taxonomy->cap->assign_terms ) ) {
					wp_set_object_terms( $post_id, '', $this->taxonomy_name );
				}
			}
		}

		/**
		 * Enqueue the select2 meta box scripts and style.
		 *
		 * @todo     add relative non plugin specific url
		 * 
		 * @wp-hook  admin_enqueue_scripts
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_admin_enqueue_scripts_select2() {
			$screen = get_current_screen();

			if ( 'post' !== $screen->base || ! in_array( $screen->post_type, $this->post_types ) ) {
				return;
			}

			wp_enqueue_script(  'select2', plugins_url( '/lib/select2/select2.min.js' , dirname( __FILE__ ) ), array( 'jquery' ), '1.0' );
			wp_enqueue_script(  'select2-sortable', plugins_url( '/lib/select2-sortable/select2.sortable.js', dirname( __FILE__ ) ), array( 'jquery', 'jquery-ui-sortable', 'select2' ), '1.0' );
			wp_enqueue_script(  'th-ct-admin-post', plugins_url( '/js/th-ct-admin-post.js' ,  __FILE__  ), array( 'select2-sortable' ), '1.0' );
			wp_enqueue_style( 'select2', plugins_url( '/lib/select2/select2.css' , dirname( __FILE__ )  ) );
		}

		public function acb_replace_description_with_wysiwyg() {
?>
<div class="form-field">
	<label for="tag-description"><?php _ex('Description', 'Taxonomy Description'); ?></label>
	<?php wp_editor( '', 'tag-description', array('textarea_name' => 'description', 'textarea_rows' => 5, 'teeny' => true ) ); ?> 
	<p><?php _e('The description is not prominent by default; however, some themes may show it.'); ?></p>
</div>
<?php			
		}

		public function acb_replace_description_with_wysiwyg_table() {
			global $tag;
?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="description"><?php _ex('Description', 'Taxonomy Description'); ?></label></th>
			<td><?php wp_editor( htmlspecialchars_decode( $tag->description ), 'description', array('textarea_name' => 'description', 'textarea_rows' => 5, 'teeny' => true ) ); ?><br />
			<span class="description"><?php _e('The description is not prominent by default; however, some themes may show it.'); ?></span></td>
		</tr>
<?php			
		}

		public function acb_admin_print_footer_scripts_hide_description() {
			$screen = get_current_screen();

			if ( 'edit-tags' !== $screen->base || $screen->taxonomy !== $this->taxonomy_name ) {
				return;
			}

			if( isset( $_GET['tag_ID'] ) ) {
?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('#description').parent().parent().remove();
				});
			</script>
<?php
			} else {
?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('#submit').mousedown( function() {
					    tinyMCE.triggerSave();
				    });
					$('#tag-description').parent().remove();
					$(document).ajaxSuccess(function(event, xhr, settings) {
						if ( settings.data.indexOf("action=add-tag") !== -1 ) {
							// var length = tinymce.editors.length;
							for (i=0; i < tinymce.editors.length; i++){
								tinymce.editors[i].setContent(''); // get the content
							}
						}
					});
				});
			</script>
<?php
			}
		}


		// ======================================
		// Private methods
		// ======================================

		/**
		 * Create default labels based on singular & plural and merge args
		 * with defaults.
		 *
		 * @param    string  $singular post type singular name
		 * @param    string  $plural   post type plural name
		 * @param    array   $args     post type arguments
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		private function init_args( $singular, $plural, $args, $admin_args ) {
			$uc_singular = ucfirst( $singular );
			$uc_plural   = ucfirst( $plural );
			$def_labels  = array(
				'name'                       => _x( $uc_plural, 'taxonomy general name', 'text_domain' ),
				'singular_name'              => _x( $uc_singular, 'taxonomy singular name', 'text_domain' ),
				'menu_name'                  => __( $uc_plural, 'text_domain' ),
				'all_items'                  => sprintf( __( 'All %s', 'text_domain' ), $uc_plural ),
				'edit_item'                  => sprintf( __( 'Edit %s', 'text_domain' ), $uc_singular ),
				'view_item'                  => sprintf( __( 'View %s', 'text_domain' ), $uc_singular ),
				'update_item'                => sprintf( __( 'Update %s', 'text_domain' ), $uc_singular ),
				'add_new_item'               => sprintf( __( 'Add New %s', 'text_domain' ), $uc_singular ),
				'new_item_name'              => sprintf( __( 'New %s Name', 'text_domain' ), $uc_singular ),
				'parent_item'                => sprintf( __( 'Parent %s', 'text_domain' ), $uc_singular ),
				'parent_item_colon'          => sprintf( __( 'Parent %s:', 'text_domain' ), $uc_singular ),
				'search_items'               => sprintf( __( 'Search %s', 'text_domain' ), $uc_plural ),
				'popular_items'              => sprintf( __( 'Popular %s', 'text_domain' ), $uc_singular ),
				'separate_items_with_commas' => sprintf( __( 'Separate %s with commas', 'text_domain' ), $plural ),
				'add_or_remove_items'        => sprintf( __( 'Add or remove %s', 'text_domain' ), $plural ),
				'choose_from_most_used'      => sprintf( __( 'Choose from the most used %s', 'text_domain' ), $plural ),
				'not_found'                  => sprintf( __( 'No %s found', 'text_domain' ), $plural ),
			);

			$def_args = array(
				'label'                 => __( $uc_plural, 'text_domain' ),
				'public'                => true,
				'show_ui'               => true,
				'show_in_nav_menus'     => true,
				'show_tagcloud'         => true,
				// 'show_admin_column'   => false, // not included as columns cannot be sorted
				'hierarchical'          => false,
				'update_count_callback' => '',
				'query_var'             => $this->taxonomy_name,
				'rewrite'               => true,
				'capabilities'          => array(),
				'sort'                  => false
			);

			// wp_parse_args is not recursive, so extract labels
			// from $args and merge with defaults.
			$labels = isset( $args['labels'] ) ? $arg['labels'] : array();
			$labels = wp_parse_args(
				$labels,
				apply_filters( 'th_ct_default_taxonomy_labels', $def_labels, $singular, $plural )
			);

			$args = wp_parse_args(
				$args,
				apply_filters( 'th_ct_default_taxonomy_args', $def_args, $singular, $plural )
			);

			$args['labels'] = $labels;

			$default_admin_args = array(
				'show_column'           => true,
				'show_filter'           => $args['labels']['name'],
				'show_metabox'          => true,
				'required'              => false,
				'show_in_content_table' => true,
				'default'               => false,
				'sortable'              => false
			);
			$admin_args = wp_parse_args(
				$admin_args,
				apply_filters( 'th_ct_default_admin_args', $default_admin_args, $this->taxonomy_name )
			);

			if ( is_array( $admin_args['default'] ) ) {
				$d = array(
					'title'       => sprintf( __( 'Default %s', 'text_domain' ), $uc_singular ),
					'description' => sprintf( __( 'The default %s', 'text_domain' ), $singular ),
					'slug'        => sprintf( __( 'default-%s', 'text_domain' ), $singular )
				);
				$default = wp_parse_args(
					$admin_args['default'],
					apply_filters( 'th_ct_default_admin_args_default', $d, $this->taxonomy_name )
				);
				$admin_args['default'] = $default;
			}

			if ( $admin_args['sortable'] ) {
				$args['sort'] = true;
			}

			$this->admin_args = $admin_args;
			$this->taxonomy_args = $args;

			if ( true === $admin_args['show_column'] || is_array( $admin_args['show_column'] ) ) {
				$this->show_column_for_types( $this->post_types, $admin_args['show_column'] );
			}
			if ( false !== $admin_args['show_filter'] ) {
				$this->show_filter_for_types( $this->post_types, $admin_args['show_filter'] );
			}
			if ( $admin_args['show_metabox'] ) {
				$this->show_metabox_for_types( $this->post_types );
			}
			if ( $admin_args['required'] ) {
				$this->required_for_types( $this->post_types );
			}
			if ( $admin_args['show_in_content_table'] ) {
				$this->show_in_content_table();
			}

		}

		/**
		 * Insert a default term for the given taxonomy.
		 * 
		 * @param    TH_CT   $instance  The TH_CT object
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		private static function insert_default_term( $instance ) {
			// Is a default term requested
			if ( !$instance->admin_args['default'] ) {
				return;
			}

			$dt = get_option( 'default_' . $instance->taxonomy_name );
			if ( !$dt ) {
				$term = wp_insert_term(
					$instance->admin_args['default']['title'],
					$instance->taxonomy_name,
					array(
						'description' => $instance->admin_args['default']['description'],
						'slug'        => $instance->admin_args['default']['slug']
					)
				);
				if(!is_wp_error( $term )) {
					update_option(  'default_' . $instance->taxonomy_name , $term['term_id'] );
				}
			}
		}

	}

}

/**
 * A walker class to use that extends wp_dropdown_categories and allows it to use the term's slug as a value rather than ID.
 *
 * See http://core.trac.wordpress.org/ticket/13258
 *
 * Usage, as normal:
 * wp_dropdown_categories($args);
 *
 * But specify the custom walker class, and (optionally) a 'id' or 'slug' for the 'value' parameter:
 * $args=array('walker'=> new SH_Walker_TaxonomyDropdown(), 'value'=>'slug', .... );
 * wp_dropdown_categories($args);
 *
 * If the 'value' parameter is not set it will use term ID for categories, and the term's slug for other taxonomies in the value attribute of the term's <option>.
 */

class TH_Walker_TaxonomyDropdown extends Walker_CategoryDropdown {
	function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 )  {
		$pad = str_repeat( '&nbsp;', $depth * 3 );
		$cat_name = apply_filters( 'list_cats', $object->name, $object );

		if ( !isset( $args['value'] ) ) {
			$args['value'] = ( $object->taxonomy != 'category' ? 'slug' : 'id' );
		}

		$value = ( $args['value']=='slug' ? $object->slug : $object->term_id );

		$output .= "\t<option class=\"level-$depth\" value=\"".$value."\"";
		if ( $value === (string) $args['selected'] ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad.$cat_name;
		if ( $args['show_count'] )
			$output .= '&nbsp;&nbsp;('. $object->count .')';

		$output .= "</option>\n";
	}

}
