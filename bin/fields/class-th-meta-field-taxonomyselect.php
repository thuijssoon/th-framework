<?php

/**
 * Class adding functionality to add text field
 *
 * @author Thijs Huijssoon
 */

if(!class_exists('TH_Meta_Field_Select')) {
	require_once 'class-th-meta-field-select.php';
}

class TH_Meta_Field_Taxonomyselect extends TH_Meta_Field_Select {

	protected $result = null;

	public function __construct( $namespace, $properties ) {
		$this->handle_defaults(
			array(
				'taxonomy'     => 'catagory',
				'save_as_meta' => false,
				'id_or_slug'   => 'slug',
				'args'         => array(
					'hide_empty' => false, 
				)
			)
		);
		// Ensure we always select all fields
		$this->properties['args']['fields'] = 'all';

		if ( !isset( $properties['taxonomy'] ) ) {
			if ( WP_DEBUG ) {
				trigger_error( __( '<strong>Taxonomy must be defined.</strong>', 'text_domain' ) );
			}
			return;
		}

		parent::__construct( $namespace, $properties );

		$this->type = 'taxonomyselect';
	}

	protected function get_options() {
		if ( !is_null( $this->result) ) {
			return $this->result;
		}

		$this->result = false;
		$taxonomy     = get_taxonomy( $this->properties['taxonomy'] );
		$name         = !empty($taxonomy) ? $taxonomy->labels->name : '';
		$post_type    = get_current_screen()->post_type;

		$error_messages = apply_filters( 'th_field_' . $this->type . '_missing_options_messages',
			array(
				'no_such_taxonomy'          => sprintf( __( 'Taxonomy <strong>%s</strong> is not registered.', 'text_domain' ), esc_html( $this->properties['taxonomy'] ) ),
				'not_registered_for_object' => sprintf( __( 'Taxonomy <strong>%1$s</strong> is not attached to post type <strong>%2$s</strong>.', 'text_domain' ), esc_html( $this->properties['taxonomy'] ), esc_html( $post_type ) ),
				'no_terms_found'            => sprintf( __( 'No %1$s found. <a href="%2$s">Create some %1$s</a>.', 'text_domain' ), esc_html( $name ), admin_url( 'edit-tags.php?taxonomy=' . $this->properties['taxonomy'] ) ),
			),
			$this->properties
		);

		if(empty($taxonomy)) {
			$this->missing_options_message = $error_messages['no_such_taxonomy'];
			return $this->result;
		}

		if(!in_array($this->properties['taxonomy'], get_object_taxonomies( $post_type ))) {
			$this->missing_options_message = $error_messages['not_registered_for_object'];
			return $this->result;
		}

		$terms = get_terms( $this->properties['taxonomy'], $this->properties['args'] );
		
		$result = array();

		foreach ($terms as $term ) {
			if( 'slug' == $this->properties['id_or_slug']) {
				$result[$term->slug] = $term->name;
			} else {
				$result[$term->term_id] = $term->name;
			}
		}

		if ( empty( $result ) ) {
			$this->missing_options_message = $error_messages['no_terms_found'];
			return $this->result;
		}

		$this->result = $result;

		return $this->result;
	}

	public function get_column_value($value) {
		if( empty( $value ) ) {
			return '—';
		}
		$term = get_term_by( 'slug', $value, $this->properties['taxonomy'] );
		return esc_html( $term->name );
	}

	/**
	 * Overwrite function to allow for taxonomies to be saved as meta.
	 */
	public function is_taxonomy() {
		if( $this->properties['save_as_meta'] ) {
			return false;
		}
		return true;
	}
}