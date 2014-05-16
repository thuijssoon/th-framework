<?php

/**
 * Class adding functionality to add file field
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_File' ) ) {
	class TH_Meta_Field_File extends TH_Meta_Field {

		public function __construct( $namespace, $properties ) {
			if ( !isset( $this->default_properties ) ) {
				$this->default_properties = array();
			}

			$this->default_properties = array_merge(
				array(
					'title'              => __( 'Insert File', 'text_domain' ),
					'button'             => __( 'Insert File', 'text_domain' ),
					'button-tooltip'     => __( 'Click Here to Open the Media Manager', 'text_domain' ),
					'dialog-button'      => __( 'Insert File', 'text_domain' ),
					'clear-link-tooltip' => __( 'Click this link to remove the file', 'text_domain' ),
					'clear-link'         => __( 'Remove file', 'text_domain' ),
					'filter'             => 'image',
				),
				$this->default_properties
			);

			parent::__construct( $namespace, $properties );

			$this->type = 'file';
		}

		/**
		 * Returns markup for input field
		 *
		 * @return string
		 */
		public function render_field( $value, $error = false ) {
			if ( !is_array( $value ) ) {
				(array) $value;
			}

			$output = '';

			// Setup attributes
			$attributes = array();
			$hidden_attributes = array();
			$file_info = '';

			// Name
			$attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-url"';
			$attributes[] = 'readonly="readonly"';
			$hidden_attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';
			$hidden_attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-id"';

			// Value
			if ( !empty( $value ) ) {
				$attachment = get_post( $value );
				$link       = wp_get_attachment_url( $value );
				$is_image   = wp_attachment_is_image( $value );
				$full       = wp_get_attachment_image_src( $value, 'full', false );
				if ( $is_image ) {
					$icon                = wp_get_attachment_image_src( $value, 'medium', false );
				} else {
					$icon                = wp_get_attachment_image_src( $value, 'thumbnail', true );
				}

				$date                = date_i18n( get_option( 'date_format' ) , strtotime( $attachment->post_date ) );
				$filename            = basename( get_attached_file( $value ) );
				$title               = sprintf( __( 'View %s in new window', 'text_domain' ), $filename );
				$mime                = get_post_mime_type( $value );
				$file_info           = '<div class="thumbnail"><img src="' . $icon[0] . '" class="icon" draggable="false" /></div>';
				$file_info          .= '<div class="details"><div class="filename"><a href="' . $link . '" target="_blank" title="' . $title . '">' . $filename . '</a></div>';
				$file_info          .= '<div class="uploaded">' . $date . '</div>';
				if ( $is_image ) {
					$file_info          .= '<div class="dimension">' . $full[1] . ' x ' . $full[2] . '</div>';
				}
				$file_info          .= '<div class="mimetype">' . $mime . '</div>';
				$file_info          .= '</div>';

				$attributes[]        = 'value="' . esc_url( $link ) . '"';
				$hidden_attributes[] = 'value="' . absint( $value ) . '"';
			}
			// Class
			if ( !empty( $this->properties['class'] ) ) {
				$attributes[] = 'class="text regular-text ' . sanitize_html_class( $this->properties['class'] ) . '"';
			}
			else {
				$attributes[] = 'class="text regular-text"';
			}

			// Disabled
			if ( isset( $this->properties['disabled'] ) && $this->properties['disabled'] ) {
				$attributes[] = 'disabled="disabled"';
			}

			// Placeholder
			if ( isset( $this->properties['placeholder'] ) ) {
				$attributes[] = 'placeholder="' . esc_attr( $this->properties['placeholder'] ) . '"';
			}

			// Add input field
			$output .= '<input type="text" ' . implode( ' ', $attributes ) . '>';

			$output .='<input type="hidden" '. implode( ' ', $hidden_attributes ) . '>';
			// Add the button
			$output .= '&nbsp;<a id="' . $this->namespace . '-' . $this->properties['slug']. '-button" href="#" class="th-open-media button" title="' . esc_attr( $this->properties['button-tooltip'] ) . '" data-button="' . esc_attr( $this->properties['dialog-button'] ) .
				'" data-filter="' . esc_attr( $this->properties['filter'] ) . '"  data-title="' . esc_attr( $this->properties['title'] ) . '">' . esc_html( $this->properties['button'] ) . '</a>';

			$output .= '&nbsp;&nbsp;<a id="' . $this->namespace . '-' . $this->properties['slug']. '-clear" href="#" class="th-clear-media" title="' . esc_attr( $this->properties['clear-link-tooltip'] ) . '">' . esc_html( $this->properties['clear-link'] ) . '</a>';

			// Error
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( !empty( $error ) ) {
				$output .= '<p class="errormessage">' . wp_kses_post( $error ) . '</p>';
			}

			// File info
			$output .= '<div class="attachment-info">' . $file_info . '</div>';

			// Description
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( isset( $this->properties['description'] ) ) {
				$output .= '<p class="description">' . wp_kses_post( $this->properties['description'] ) . '</p>';
			}

			return $output;
		}

		public function validate( &$errors ) {

			$name = $this->namespace . '-' . $this->get_slug();

			// If the field isn't set (checkbox), use empty string (default for get_post_meta()).
			$value = isset( $_POST[$name] ) ? $_POST[$name] : '';

			$error_messages = apply_filters( 'th_field_' . $this->type . '_error_messages',
				array(
					'required'          => sprintf( __( '%s cannot be empty', 'text_domain' ), esc_html( $this->properties['label'] ) ),
					'technical_failure' => __( 'Sorry, technical failure, please contact your administrator. Return value is not an array.', 'text_domain' ),
				),
				$this->properties
			);

			if ( $this->is_required() && empty( $value ) ) {
				$errors[$this->get_slug()] = array(
					'slug'        => $this->get_slug(),
					'title'       => esc_html( $this->properties['label'] ),
					'message'     => $error_messages['required']
				);
			}

			if ( !empty( $value ) ) {
				$value = absint( $value );
			}


			return $value;
		}

		public function get_column_value( $value ) {
			if ( empty( $value ) ) {
				$att = wp_get_attachment_image_src( 0, array( 80, 60 ), true );
				return '<img src="' . $att[0] . '" width="' . $att[1] . '" height="' . $att[2] . '" />';
			} elseif ( wp_attachment_is_image( $value ) ) {
				return wp_get_attachment_image( $value, array( 80, 60 ), true );
			} else {
				$filename = basename( get_attached_file( $value ) );
				$link     = wp_get_attachment_url( $value );
				$title    = sprintf( __( 'View %s in new window', 'text_domain' ), $filename );
				return '<a href="' . $link . '" target="_blank" title="' . $title . '">' . $filename . '</a>';
			}
		}

		public static function enqueue_js() {
			wp_enqueue_media();
			wp_enqueue_script( 'th-meta-field-file', plugins_url( 'js/th-meta-field-file.js' , dirname( __FILE__ ) ), array( 'jquery' ), '1.0.0', true );
			$args = array( 'link_title' => __( 'Open %s in new window.', 'text_domain' ) );
			wp_localize_script( 'th-meta-field-file', 'th_meta_field_file_args', $args );
		}

		public function get_source_clonable_value( $value ) {
			$bapi = TH_Multisite_Broadcast_API::get_instance();
			$attachment = get_post( $value );
			if ( !is_null( $attachment ) ) {
				$broadcast_data = $bapi->create_post_broadcast_data( $attachment );
				return $broadcast_data;
			}
		}

		public function get_destination_clonable_value( $value ) {
			$bapi = TH_Multisite_Broadcast_API::get_instance();
			$id = $bapi->publish_attachment_data_to_blog( $value, array( 'insert_only' => true ) );
			return $id;
		}

		public function get_cloned_value( $source_blog_id, $item_id, $new_item_id, $meta_value ) {
			// Get the source post
			$current_blog_id = get_current_blog_id();
			switch_to_blog( $source_blog_id );
			$source_post = get_post( $meta_value );
			switch_to_blog( $current_blog_id );

			if ( empty( $source_post ) )
				return '';

			// Let's try to find the post with the same slug
			global $wpdb;
			$source_post_name     = $source_post->post_name;
			$destination_post_ids = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_name = '$source_post_name' " );

			if ( empty( $destination_post_ids ) ) {

				// There's no speaker created, let's copy it
				$copier = Multisite_Content_Copier_Factory::get_copier( 'post', $source_blog_id, array( $item_id ), array() );
				$attachment_id = $copier->copy_single_image( $source_blog_id, $meta_value );

				$my_post = array(
					'ID'          => $attachment_id,
					'post_parent' => $new_item_id
				);

				// Update the post into the database
				wp_update_post( $my_post );

				$new_meta_value = $attachment_id;
			}
			else {
				// The speaker already exists, let's assign it
				$new_meta_value = $destination_post_ids[0]->ID;
			}

			return $new_meta_value;
		}

	}
}
