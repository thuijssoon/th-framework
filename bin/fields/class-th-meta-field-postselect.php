<?php

/**
 * Class adding functionality to add text field
 *
 * @author Thijs Huijssoon
 */

if ( !class_exists( 'TH_Meta_Field_Select' ) ) {
	require_once 'class-th-meta-field-select.php';
}

if ( !class_exists( 'TH_Meta_Field_Postselect' ) ) {
	class TH_Meta_Field_Postselect extends TH_Meta_Field_Select {

		protected $result = null;

		public function __construct( $namespace, $properties ) {
			$this->handle_defaults(
				array(
					'post_type'  => 'post',
					'meta_key'   => '',
					'meta_value' => '',
					'id_or_slug' => 'slug',
				)
			);

			parent::__construct( $namespace, $properties );

			$this->type = 'postselect';
		}

		protected function get_options() {
			if ( !is_null( $this->result ) ) {
				return $this->result;
			}

			$post_type = get_post_type_object( $this->properties['post_type'] );
			$name      = !empty( $post_type ) ? $post_type->labels->name : '';

			$error_messages = apply_filters( 'th_field_' . $this->type . '_missing_options_messages',
				array(
					'no_such_post_type' => sprintf( __( 'Post type <strong>%s</strong> is  not registered', 'text_domain' ), esc_html( $this->properties['post_type'] ) ),
					'no_posts_found'    => sprintf( __( 'No %1$s found. <a href="%2$s">Create some %1$s</a>.', 'text_domain' ), esc_html( $name ), admin_url( 'post-new.php?post_type=' . $this->properties['post_type'] ) ),
				),
				$this->properties
			);

			if ( empty( $post_type ) ) {
				$this->missing_options_message = $error_messages['no_such_post_type'];
				$this->result = false;
				return $this->result;
			}

			$posts = get_posts(
				array(
					'post_type'      => $this->properties['post_type'],
					'posts_per_page' => -1,
					'orderby'        => 'name',
					'order'          => 'ASC',
					'meta_key'       => $this->properties['meta_key'],
					'meta_value'     => $this->properties['meta_value'],
				)
			);

			$result = array();

			foreach ( $posts as $post ) {
				if ( 'slug' == $this->properties['id_or_slug'] ) {
					$result[$post->post_name] = $post->post_title;
				} else {
					$result[$post->ID] = $post->post_title;
				}
			}

			$this->result = $result;

			if ( empty( $this->result ) ) {
				$this->missing_options_message = $error_messages['no_posts_found'];
				$this->result = false;
				return $this->result;
			}

			return $this->result;
		}

		public function get_column_value( $value ) {
			if( empty( $value ) ) {
				return '—';
			}
			$post = get_page_by_path( $value, OBJECT, $this->properties['post_type'] );
			return esc_html( get_the_title( $post->ID ) );
		}

	}
}
