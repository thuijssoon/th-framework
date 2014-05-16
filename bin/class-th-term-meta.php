<?php
/**
 * WordPress Custom Post Types
 *
 * Contains the TH_Term_Meta class. Requires WordPress version 3.5 or greater.
 *
 * @version   0.1.0
 * @package   TH CPT
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL 2.0+ - http://www.gnu.org/licenses/gpl.txt
 * @link      https://github.com/thuijssoon/
 * @copyright Copyright (c) 2013 - Thijs Huijssoon
 */

require_once 'class-th-meta.php';

if ( !class_exists( 'TH_Term_Meta' ) ) {

	/*
	 * WordPress Custom Post Meta Class
	 *
	 * @package TH CPT
	 */
	class TH_Term_Meta extends TH_Meta {

		/**
		 * Create a TH_Meta object.
		 *
		 * @param array   $meta Array of meta definitions
		 */
		public function __construct( $meta ) {
			if(!is_admin()) {
				return;
			}

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			If (!class_exists('Taxonomy_Metadata')) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Taxonomy metadata plugin is not active.</strong> You\'ll need to install and activate it in order to use this file. If you need help installing the plugin you can refer to <a href="http://wordpress.org/plugins/taxonomy-metadata/">the Taxonomy Metadata plugin in the WordPress Plugin Directory</a>.', 'text_domain' ) );
				}
				return;				
			}			

			if ( !isset( $this->default_meta ) ) {
				$this->default_meta = array();
			}

			$this->default_meta = array_merge(
				array(
					'taxonomies' => array( 'category', ),
				),
				$this->default_meta
			);

			parent::__construct( $meta );

			// Register with the error object
			WPTP_Error::get_instance()
			->add_taxonomy_support( $this->meta['taxonomies'] );

			// Hook into
			foreach ($this->meta['taxonomies'] as $taxonomy) {
				add_action( $taxonomy . '_add_form_fields', array( $this, 'render' ) );
				add_action( $taxonomy . '_edit_form_fields', array( $this, 'render' ) );
				add_action( 'created_' . $taxonomy, array( $this, 'save' ) );
				add_action( 'edited_' . $taxonomy, array( $this, 'save' ) );  
				add_filter( 'manage_edit-' . $taxonomy . '_columns', array( $this, 'add_column' ) );
				add_filter( 'manage_' . $taxonomy . '_custom_column', array( $this, 'populate_column' ), 10, 3 );
			}

			// Setup hooks for broadcasting
			// add_filter( 'th_mba_create_term_meta_broadcast_data', array( $this, 'pre_process_cloned_meta' ), 10, 2 );
			// add_filter( 'th_mba_term_publish_meta', array( $this, 'post_process_cloned_meta' ), 10, 2 );

			$this->admin_pages = array('edit-tags.php');

			add_action( 'mcc_term_copied', array( $this, 'handle_mcc_term_copied'), 10, 3 );
			add_action( 'mcc_before_copy_post', array( $this, 'suppress_hooks_before_copy') );
		}

		public function render() {
			global $tag;

			// Set the nonce
			$output  = "";
			$output .= wp_nonce_field( 'TH_Term_Meta-save-' . $this->meta['id'] . '-for-' . get_current_screen()->taxonomy, 'TH_Term_Meta_' . $this->meta['id'] . '_meta_box_nonce', true, false );

			if(get_current_screen()->taxonomy . '_add_form_fields' == current_filter()) {
				// Loop through the fields and render them
				foreach ( $this->get_field_objects() as $field ) {
					$output .= '<div class="form-field ' . $field->get_class() . '">';
					$output .= $field->render_label();
					$output .= $field->render_field( '', false );
					$output .= '</div>';
				}
			} else {
				$errors  = WPTP_Error::get_instance()
					->get_saved_errors();

				if('single' == $this->meta['save_mode']) {
					$values   = array();
					$values   = get_term_meta( $tag->term_id, $this->meta['id'], true );
				}

				// Loop through the fields and render them
				foreach ( $this->get_field_objects() as $field ) {
					if('single' == $this->meta['save_mode']) {
						$value = isset($values[$field->get_slug()]) ? $values[$field->get_slug()] : '' ;
					} else {
						$value = '';
						$value = get_term_meta( $tag->term_id, $field->get_slug(), true );
					}
					$class   = 'form-field th-meta';
					$class  .= isset( $errors[$field->get_slug()] ) ? ' th_error' : '';
					$class  .= $field->is_required() ? ' form-required' : '';
					$output .= '<tr class="' . $class . '">';
					$output .= '<th scope="row" valign="top">' . $field->render_label() . '</th>';
					$output .= '<td class="'. $field->get_class() . '">' . $field->render_field( $value, $this->get_error_message($field->get_slug()) ) . '</td>';
					$output .= '</tr>';
				}
			}

			// Echo the output to the screen
			echo $output;
		}

		public function save( $term_id ) {
			// Do nothing if hooks are suppressed by other plugin
			if( self::$suppress_hooks ) {
				return $term_id;
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$tax_name = $_POST['taxonomy'];
				$tax_obj  = get_taxonomy($tax_name);
			} else {
				$tax_name = get_current_screen()->taxonomy;
				$tax_obj  = get_taxonomy($tax_name);
			}

			if(!$tax_obj) {
				return $term_id;
			}

			// Check precondition: capability
			if ( !current_user_can( $tax_obj->cap->edit_terms ) )
				return $term_id;

			// Check precondition: nonce
			// this will also filter out the posts with no post meta
			$nonce_action  = 'TH_Term_Meta-save-' . $this->meta['id'] . '-for-' . $tax_name;
			$nonce_name = 'TH_Term_Meta_' . $this->meta['id'] . '_meta_box_nonce';

			if ( !isset( $_POST[$nonce_name] ) || !wp_verify_nonce( $_POST[$nonce_name], $nonce_action ) )
				return $post_id;

			// Validate submitted values
			$values = $this->validate();

			$sanitised_values = $values['meta'];

			foreach ($values['tax'] as $slug => $tax) {
				// array( $slug => $tax['value'] );
				$sanitised_values = array_merge($sanitised_values, array( $slug => $tax['value'] ));
			}

			// Save the meta values
			if ( 'single' == $this->meta['save_mode'] && !empty( $sanitised_values ) ) {

				update_term_meta( $term_id, $this->meta['id'], $sanitised_values );

			} elseif ( 'single' == $this->meta['save_mode'] && empty( $sanitised_values ) ) {

				delete_term_meta( $term_id, $this->meta['id'] );

			} elseif ( 'individual' == $this->meta['save_mode'] ) {

				foreach ( $sanitised_values as $slug => $value ) {
					update_term_meta( $term_id, $slug, $value );
				}

			}
		}

		public function add_column( $columns ) {
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
						$found = array_search( 'name', array_keys( $columns ) );
					}

					$found++;

					$new_columns = array_slice( $columns, 0, $found );
					$new_columns[$this->meta['id'] . '-' . $field->get_slug()] = $column_args['label'];
					$columns = array_merge( $new_columns, array_slice( $columns, $found ) );
				}
			}
			return $columns;
		}

		public function populate_column( $row, $slug, $term_id ) {

			$slug = substr( $slug, strlen( $this->meta['id'] ) + 1 );
			// Do return if the column slug isn't among the fields (i.e. other plugin)
			if ( !$slug || !array_key_exists( $slug, $this->get_field_objects() ) ) return;

			$fields = $this->get_field_objects();
			$field  = $fields[$slug];

			$value = $this->get_value($slug, $term_id);
			echo $field->get_column_value($value);
		}

		public function suppress_hooks_before_copy() {
			self::suppress_hooks();
			WPTP_Error::suppress_hooks();
		}

		public function handle_mcc_term_copied($source_term_id, $destination_term, $source_blog_id ) {
			// Get the source post
			$current_blog_id = get_current_blog_id();
			switch_to_blog( $source_blog_id );
			$source_term_meta = maybe_unserialize(get_term_meta( $source_term_id ));
			switch_to_blog( $current_blog_id );

			foreach ($source_term_meta as $meta_key => $meta_values) {
				if( !$this->is_our_meta( $meta_key ) ) {
					continue;
				}

				foreach ($meta_values as $meta_value) {
					$meta_value = maybe_unserialize( $meta_value);

					// Get the new value
					remove_action( 'mcc_term_copied', array( $this, 'handle_mcc_term_copied' ), 10, 3 );
					$new_meta_value = $this->process_cloned_meta( $source_blog_id, $source_term_id, $destination_term['term_id'], $meta_key, $meta_value );
					add_action( 'mcc_term_copied', array( $this, 'handle_mcc_term_copied' ), 10, 3 );

					// Save the meta
					update_term_meta( $destination_term['term_id'], $meta_key, $new_meta_value );
				}
			}
		}

		// public function pre_process_cloned_meta( $term_meta_broadcast_data, $term ) {
		// 	return $this->process_cloned_meta( $term_meta_broadcast_data, $term, true );
		// }

		// public function post_process_cloned_meta( $term_meta_broadcast_data, $term ) {
		// 	return $this->process_cloned_meta( $term_meta_broadcast_data, $term, false );
		// }

		// private function process_cloned_meta( $term_meta_broadcast_data, $term, $pre = true ) {

		// 		// Loop through the fields and render them
		// 		$fo   = $this->get_field_objects();
		// 		$tmbd = $term_meta_broadcast_data;

		// 		if ( 'single' === $this->meta['save_mode'] ) {
		// 			$temp_meta = array();
		// 			if(isset($term_meta_broadcast_data[$this->meta['id']])) {
		// 				$meta = $term_meta_broadcast_data[$this->meta['id']];
		// 				foreach ($meta as $meta_key => $meta_value) {
		// 					if( isset( $fo[$meta_key] ) ) {
		// 						if( $pre ) {
		// 							$temp_meta[$meta_key] = $fo[$meta_key]->get_source_clonable_value( $meta_value );
		// 						} else {
		// 							$temp_meta[$meta_key] = $fo[$meta_key]->get_destination_clonable_value( $meta_value );
		// 						}
		// 					}
		// 				}
		// 			}
		// 			$tmbd[$this->meta['id']] = $temp_meta;
		// 		} else {
		// 			foreach ($term_meta_broadcast_data as $meta_key => $meta_value) {
		// 				if( isset( $fo[$meta_key] ) ) {
		// 					if( $pre ) {
		// 						$tmbd[$key] = $fo[$meta_key]->get_source_clonable_value( $meta_value );
		// 					} else {
		// 						$tmbd[$key] = $fo[$meta_key]->get_destination_clonable_value( $meta_value );
		// 					}
		// 				}
		// 			}
		// 		}

		// 		return $tmbd;
		// }

		private function get_value( $slug, $term_id ) {
			if ( 'single' == $this->meta['save_mode'] ) {
				$values = get_term_meta( $term_id, $this->meta['id'], true );
				if(isset($values[$slug])) {
					return $values[$slug];
				} else {
					return '';
				}
			} else {
				return get_term_meta( $term_id, $this->meta['id'] . '-' . $slug, true );
			}		
		}

	}

}
