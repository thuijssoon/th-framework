<?php
/**
 * WordPress Custom Post Types
 *
 * Contains the TH_Post_Meta class. Requires WordPress version 3.5 or greater.
 *
 * @version   0.1.0
 * @package   TH CPT
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL 2.0+ - http://www.gnu.org/licenses/gpl.txt
 * @link      https://github.com/thuijssoon/
 * @copyright Copyright (c) 2013 - Thijs Huijssoon
 */

require_once 'class-th-meta.php';

if ( !class_exists( 'TH_Post_Meta' ) ) {

	/*
	 * WordPress Custom Post Meta Class
	 *
	 * @package TH CPT
	 */
	class TH_Post_Meta extends TH_Meta {

		/**
		 * The values of the saved meta.
		 *
		 * @var array
		 */
		private $values = null;

		private static $suppress_mcc_hooks = false;

		/**
		 * Create a TH_Meta object.
		 *
		 * @param array   $meta Array of meta definitions
		 */
		public function __construct( $meta ) {
			if ( !isset( $this->default_meta ) ) {
				$this->default_meta = array();
			}

			$this->default_meta = array_merge(
				array(
					'post_types' => array( 'post', ),
					'context'    => 'normal',
					'priority'   => 'high',
				),
				$this->default_meta
			);

			parent::__construct( $meta );

			// Register with the error object
			WPTP_Error::get_instance()
			->add_post_type_support( $this->meta['post_types'] );

			// Hook into add_meta_box & save_post
			add_action( 'add_meta_boxes', array( $this, 'add' ) );
			add_action( 'save_post', array( $this, 'save' ) );

			foreach ( $this->meta['post_types'] as $post_type ) {
				// Add columns to the admin column
				add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_columns' ) );

				// Render its value
				add_filter( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'populate_column' ) );

				// Only allow column sorting for metaboxes where
				// values are stored individually
				if ( 'individual' === $this->meta['save_mode'] ) {
					// Register sortable columns
					add_filter( 'manage_edit-' . $post_type . '_sortable_columns', array( $this, 'register_sortable_columns' ) );

					// Add instructions for sorting meta values
					add_filter( 'pre_get_posts', array( $this, 'register_sortable_meta' ) );
				}

			}

			$this->admin_pages = array( 'post.php', 'post-new.php', 'page-new.php', 'page.php' );

			add_filter( 'th_post_meta_show_on', array( $this, 'add_for_post_type' ), 10, 2 );
			add_filter( 'th_post_meta_show_on', array( $this, 'add_for_page_template' ), 11, 2 );
			add_action( 'mcc_copy_post_meta', array( $this, 'handle_mcc_copy_post_meta' ), 10, 5 );
		}

		/**
		 * Add the meta box for each desired post type.
		 *
		 * @wp-hook  add_meta_boxes
		 */
		public function add() {
			$post_types = get_post_types();
			foreach ( $post_types as $post_type ) {
				if($this->is_visible()) {
					add_meta_box(
						$this->meta['id'],
						$this->meta['title'],
						array( $this, 'render' ),
						$post_type,
						$this->meta['context'],
						$this->meta['priority']
					);
				}
			}
		}

		public function render() {
			global $post;

			$errors = WPTP_Error::get_instance()
			->get_saved_errors();

			$output = "";

			// Set the nonce
			$output .= wp_nonce_field( 'th_post_meta-save-' . $this->meta['id'] . '-for-' . $post->post_type . '-' . $post->ID, 'th_post_meta_' . $this->meta['id'] . '_meta_box_nonce', true, false );

			$output .= '<table class="form-table th-meta">';

			if ( 'single' == $this->meta['save_mode'] ) {
				$values   = get_post_meta( $post->ID, $this->meta['id'], true );
			}

			// Loop through the fields and render them
			foreach ( $this->get_field_objects() as $field ) {
				if ( $field->is_taxonomy() ) {
					$value = wp_get_object_terms( $post->ID, $field->get_taxonomy(), array( 'orderby' => 'term_order', 'fields' => $field->id_or_slug() . 's' ) );
				} elseif ( 'single' == $this->meta['save_mode'] ) {
					$value = isset( $values[$field->get_slug()] ) ? $values[$field->get_slug()] : '' ;
				} else {
					$value = get_post_meta( $post->ID, $field->get_slug(), true );
				}
				$class   = isset( $errors[$field->get_slug()] ) ? 'class="th_error"' : '';
				$output .= '<tr '. $class  . '>';
				if ( $this->meta['show_names'] ) {
					$output .= '<th scope="row">';
					$output .= $field->render_label();
					$output .= '</th>';
				}
				$output .= '<td class="' . $field->get_class() . '">';
				$output .= $field->render_field( $value, $this->get_error_message( $field->get_slug() ) );
				$output .= '</td>';
			}

			$output .= "</table>";

			// Echo the output to the screen
			echo $output;
		}

		public function save( $post_id ) {
			// Do nothing if hooks are suppressed by other plugin
			if ( self::$suppress_hooks ) {
				return $post_id;
			}

			$post             = get_post( $post_id );
			$post_type_object = get_post_type_object( $post->post_type );
			$ignored_actions  = array( 'trash', 'untrash', 'restore' );

			// Check precondition: action
			if ( isset( $_GET[ 'action' ] ) && in_array( $_GET[ 'action' ], $ignored_actions ) )
				return $post_id;

			// Check precondition: post_type
			if ( !in_array( $post->post_type, $this->meta['post_types'] ) )
				return $post_id;

			// Check precondition: capability
			if ( !current_user_can( $post_type_object->cap->edit_posts ) )
				return $post_id;

			// Check precondition: AUTOSAVE
			if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' )
				return $post_id;

			// Check precondition: nonce
			// this will also filter out the posts with no post meta
			$nonce_action  = 'th_post_meta-save-' . $this->meta['id'] . '-for-' . $post->post_type . '-' . $post_id;
			$nonce_name = 'th_post_meta_' . $this->meta['id'] . '_meta_box_nonce';

			if ( !isset( $_POST[$nonce_name] ) || !wp_verify_nonce( $_POST[$nonce_name], $nonce_action ) )
				return $post_id;

			// Validate submitted values
			$sanitised_values = $this->validate();

			// Save the meta values
			if ( 'single' == $this->meta['save_mode'] && !empty( $sanitised_values['meta'] ) ) {

				update_post_meta( $post->ID, $this->meta['id'], $sanitised_values['meta'] );

			} elseif ( 'single' == $this->meta['save_mode'] && empty( $sanitised_values['meta'] ) ) {

				delete_post_meta( $post->ID, $this->meta['id'] );

			} elseif ( 'individual' == $this->meta['save_mode'] ) {

				foreach ( $sanitised_values['meta'] as $slug => $value ) {
					update_post_meta( $post->ID, $slug, $value );
				}

			}

			// Save the taxonomies
			foreach ( $sanitised_values['tax'] as $slug => $data ) {
				wp_set_object_terms( $post->ID, $data['value'], $data['taxonomy'] );
			}
		}

		public function add_columns( $columns ) {
			// Loop through the fields and render them
			foreach ( $this->get_field_objects() as $field ) {
				if ( $field->show_column() ) {
					$column_args = $field->get_column_args();

					// Insert the column after the last taxonomy column
					// or if none exists after the title column
					$found = -1;
					$current = 0;

					if ( 'default' != $column_args['display_after'] ) {
						$found = array_search( $column_args['display_after'], array_keys( $columns ) );
					}


					// If not found, display after the taxonomy columns
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
					$new_columns[$this->meta['id'] . '-' . $field->get_slug()] = $column_args['label'];
					$columns = array_merge( $new_columns, array_slice( $columns, $found ) );
				}
			}
			return $columns;
		}

		/**
		 * Populates admin column with meta data
		 *
		 * @param string  $column_name
		 * @param string  $post_id
		 * @return void
		 */
		public function populate_column( $slug ) {
			global $post;

			$slug = substr( $slug, strlen( $this->meta['id'] ) + 1 );
			// Do return if the column slug isn't among the fields (i.e. other plugin)
			if ( !$slug || !array_key_exists( $slug, $this->get_field_objects() ) ) return;

			$fields = $this->get_field_objects();
			$field  = $fields[$slug];

			echo $field->get_column_value( $this->get_value( $slug ) );
		}


		public function register_sortable_columns( $columns ) {
			// Loop through the fields and render them
			foreach ( $this->get_field_objects() as $field ) {
				if ( $field->show_column() ) {
					$column_args = $field->get_column_args();
					if ( $column_args['sortable'] ) {
						$columns[$this->meta['id'] . '-' . $field->get_slug()] = $field->get_slug();
					}
				}
			}
			return $columns;
		}

		/**
		 * Register instruction on how to order meta values
		 *
		 * @param array   Query vars
		 * @return array
		 */
		public function register_sortable_meta( $query ) {
			if ( ! is_admin() )
				return;

			$orderby = $query->get( 'orderby' );

			if ( array_key_exists( $orderby, $this->get_field_objects() ) ) {
				$query->set( 'meta_key', $orderby );
				$query->set( 'orderby', 'meta_value' );
			}

			return $query;
		}

		/**
		 * [add_for_post_type description]
		 *
		 * @param [type]  $visible [description]
		 * @param [type]  $meta    [description]
		 */
		public function add_for_post_type( $visible, $meta ) {
			$post_type = get_current_screen()->post_type;
			if ( 'any' !== $meta['post_types'] ) {
				$visible   = $visible && in_array( $post_type, $meta['post_types'] );
			}
			return $visible;
		}

		public function add_for_page_template( $visible, $meta ) {
			global $post;
			if ( isset($meta['page_template']) && count( $meta['page_template'] ) ) {
				$slug    = get_page_template_slug( $post->ID );

				if(empty($slug)) {
					return false;
				}

				$test    = in_array($slug, $meta['page_template']);
				$visible = $visible && $test;
				_log($visible);
			}
			return $visible;
		}

		public static function suppress_mcc_hooks( $suppress_hooks ) {
			self::$suppress_mcc_hooks = $suppress_hooks;
		}

		/**
		 * [handle_mcc_copy_post_meta description]
		 *
		 * @param [type]  $orig_blog_id            [description]
		 * @param [type]  $item_id                 [description]
		 * @param [type]  $new_item_id             [description]
		 * @param [type]  $meta_key                [description]
		 * @param [type]  $unserialized_meta_value [description]
		 * @return [type]                          [description]
		 */
		public function handle_mcc_copy_post_meta( $source_blog_id, $item_id, $new_item_id, $meta_key, $unserialized_meta_value ) {

			if ( !$this->is_our_meta( $meta_key ) ) {
				return;
			}

			$post             = get_post( $new_item_id );
			$post_type_object = get_post_type_object( $post->post_type );

			// Check precondition: post_type
			if ( !in_array( $post->post_type, $this->meta['post_types'] ) )
				return;

			// Check precondition: capability
			if ( !current_user_can( $post_type_object->cap->edit_posts ) )
				return;

			// This post type has meta and we have permission

			// Get the new value
			remove_action( 'mcc_copy_post_meta', array( $this, 'handle_mcc_copy_post_meta' ), 10, 5 );
			$new_meta_value = $this->process_cloned_meta( $source_blog_id, $item_id, $new_item_id, $meta_key, $unserialized_meta_value );
			add_action( 'mcc_copy_post_meta', array( $this, 'handle_mcc_copy_post_meta' ), 10, 5 );

			// Save the meta
			update_post_meta( $new_item_id, $meta_key, $new_meta_value );
		}

		private function get_value( $slug ) {
			global $post;

			if ( 'single' == $this->meta['save_mode'] ) {
				$values = get_post_meta( $post->ID, $this->meta['id'], true );
				if ( isset( $values[$slug] ) ) {
					return $values[$slug];
				} else {
					return '';
				}
			} else {
				return get_post_meta( $post->ID, $slug, true );
			}
		}

	}

}
