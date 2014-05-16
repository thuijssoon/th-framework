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

		public function get_cloned_value( $source_blog_id, $item_id, $new_item_id, $meta_value ) {
			// Get the source post
			$current_blog_id = get_current_blog_id();
			switch_to_blog( $source_blog_id );
			if( 'slug' === $this->id_or_slug() ) {
				$source_post = null;
				$args=array(
					'name'        => $meta_value,
					'post_type'   => $this->properties['post_type'],
					'post_status' => 'any',
					'numberposts' => 1
				);
				$my_posts = get_posts($args);
				if( $my_posts ) {
					$source_post = $my_posts[0];
				}
			} else {
				$source_post = get_post( $meta_value );
			}
			switch_to_blog( $current_blog_id );

			if ( empty( $source_post ) )
				return '';

			// Let's try to find the post with the same slug
			global $wpdb;
			$source_post_name     = $source_post->post_name;
			$destination_post_ids = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_name = '$source_post_name' " );

			if ( empty( $destination_post_ids ) ) {
				// There's no speaker created, let's copy it
				$copier = Multisite_Content_Copier_Factory::get_copier( 'post', $source_blog_id, array( $source_post->ID ), array() );
				$destination_post = $copier->copy_item( $source_post->ID );

				if( 'slug' === $this->id_or_slug() ) {
					$post = get_post($destination_post['new_post_id']);
					$new_meta_value = $post->post_name;
				} else {
					$new_meta_value = $destination_post['new_post_id'];
				}
			}
			else {
				// The speaker already exists, let's assign it
				if( 'slug' === $this->id_or_slug() ) {
					$new_meta_value = $source_post_name;
				} else {
					$new_meta_value = $destination_post_ids[0];
				}
			}

			return $new_meta_value;
		}

	}
}
