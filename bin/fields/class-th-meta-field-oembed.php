<?php

/**
 * Class adding functionality to add text field
 *
 * @author Thijs Huijssoon
 */
require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Oembed' ) ) {
	class TH_Meta_Field_Oembed extends TH_Meta_Field {

		public function __construct( $namespace, $properties ) {
			if ( !isset( $this->default_properties ) ) {
				$this->default_properties = array();
			}

			$this->default_properties = array_merge(
				array(
				),
				$this->default_properties
			);

			parent::__construct( $namespace, $properties );

			add_action( 'wp_ajax_th_oembed_handler', array( $this, 'oembed_ajax_results' ) );

			$this->type = 'oembed';
		}

		/**
		 * Returns markup for input field
		 *
		 * @return string
		 */
		public function render_field( $value, $error = false ) {
			$output = '';

			// Setup attributes
			$attributes = array();

			// Type
			$attributes[] = 'type="url"';

			// Name
			$attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';
			$attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';

			// Value
			if ( empty( $value ) && isset( $this->properties['default'] ) ) {
				$value = $this->properties['default'];
			}

			$attributes[] = 'value="' . esc_url( $value ) . '"';

			// Class
			if ( !empty( $this->properties['class'] ) ) {
				$attributes[] = 'class="text regular-text th-oembed ' . sanitize_html_class( $this->properties['class'] ) . '"';
			}
			else {
				$attributes[] = 'class="text regular-text th-oembed"';
			}

			// Disabled
			if ( isset( $this->properties['disabled'] ) && $this->properties['disabled'] ) {
				$attributes[] = 'disabled="disabled"';
			}

			// Read only
			if ( isset( $this->properties['read_only'] ) && $this->properties['read_only'] ) {
				$attributes[] = 'readonly="readonly"';
			}

			// Placeholder
			if ( isset( $this->properties['placeholder'] ) ) {
				$attributes[] = 'placeholder="' . esc_attr( $this->properties['placeholder'] ) . '"';
			}

			// Build input field
			$output .= '<input ' . implode( ' ', $attributes ) . '/>';

			// Description
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( isset( $this->properties['description'] ) ) {
				$output .= '<p class="description">' . wp_kses_post( $this->properties['description'] ) . '</p>';
			}

			$output .= '<div class="th-oembed-container">';
			// Error
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( !empty( $error ) ) {
				$output .= '<p class="errormessage">' . wp_kses_post( $error ) . '</p>';
			}
			if ( $value != '' && empty( $error ) ) {
				if(isset($GLOBALS['post'])) { // On post screen
					$check_embed = $GLOBALS['wp_embed']->run_shortcode( '[embed]'. esc_url( $value ) .'[/embed]' );
				} else { // On non post screen
					$check_embed = wp_oembed_get(esc_url( $value ));
				}
				if ( $check_embed ) {
					$output .= '<div class="embed_status"><div class="embed_content">'. $check_embed .'</div><p><a href="#" class="th_remove_file_button button" rel="'. esc_attr( $this->namespace . '-' . $this->properties['slug'] ) .'">'. __( 'Remove Embed', 'cmb' ) .'</a></p></div>';
				}
			}
			$output .= '</div>';

			return apply_filters( 'th_field_' . $this->type . '_field', $output, $value, $error, $this->properties );
		}

		public function render_label() {
			$output = '<label for="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '">' . esc_html( $this->properties['label'] ) . '</label>';
			return apply_filters( 'th_field_' . $this->type . '_label', $output, $this->properties );
		}

		public function validate( &$errors ) {
			$name = $this->namespace . '-' . $this->get_slug();

			// If the field isn't set (checkbox), use empty string (default for get_post_meta()).
			$value = isset( $_POST[$name] ) ? $_POST[$name] : '';

			$error_messages = apply_filters( 'th_field_' . $this->type . '_error_messages',
				array(
					'required'         => sprintf( __( '%s cannot be empty', 'text_domain' ), esc_html( $this->properties['label'] ) ),
					'not_url'          => sprintf( __( '%s is not a well formed url', 'text_domain' ), esc_html( $value ) ),
					'not_oembed'       => sprintf( __( '%s is not recognized as an oembed url', 'text_domain' ), esc_html( $value ) ),
				),
				$this->properties
			);

			if ( $this->is_required() && empty( $value ) ) {
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['required']
				);
				return '';
			}

			$clean_url = esc_url( $value );
			if ( $clean_url !== $value ) {
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['not_url']
				);
				$value =  $clean_url;
			} elseif (!empty($value)) { // only perform if value is passed
				global $wp_embed;
				if(isset($GLOBALS['post'])) { // On post screen
					$check_embed = $wp_embed->run_shortcode( '[embed]'. esc_url( $value ) .'[/embed]' );
				} else { // On non post screen
					$check_embed = wp_oembed_get(esc_url( $value ));
				}
				// fallback that WordPress creates when no oEmbed was found
				$fallback = $wp_embed->maybe_make_link( $value );

				if ( !$check_embed || $check_embed == $fallback ) {
					// $errors[$this->get_slug()] = array(
					// 		'slug'        => $this->get_slug(),
					// 		'title'       => esc_html( $this->properties['label'] ),
					// 		'message'     => $error_messages['not_oembed']
					// );
				}
			}

			return $value;
		}

		public static function enqueue_js() {
			wp_enqueue_script( 'th-meta-field-oembed', plugins_url( 'js/th-meta-field-oembed.js' , dirname( __FILE__ ) ), array( 'jquery' ), '1.0.0', true );
			isset( $GLOBALS['post'] ) ? $id = get_the_ID() : $id = false;
			wp_localize_script( 'th-meta-field-oembed', 'th_ajax_data', array( 'ajax_nonce' => wp_create_nonce( 'ajax_nonce' ), 'post_id' => $id ) );
		}

		/**
		 * Handles our oEmbed ajax request
		 */
		public function oembed_ajax_results() {
			// verify our nonce
			if ( ! ( isset( $_REQUEST['th_ajax_nonce'], $_REQUEST['oembed_url'] ) && wp_verify_nonce( $_REQUEST['th_ajax_nonce'], 'ajax_nonce' ) ) ) {
				die();
			}

			// sanitize our search string
			$oembed_string = sanitize_text_field( $_REQUEST['oembed_url'] );

			if ( empty( $oembed_string ) ) {
				$return = '<p class="errormessage">'. __( 'Please Try Again', 'text_domain' ) .'</p>';
				$found = 'not found';
			} else {

				global $wp_embed;

				$oembed_url = esc_url( $oembed_string );
				// Post ID is needed to check for embeds
				if ( isset( $_REQUEST['post_id'] ) && !empty($_REQUEST['post_id']) ) {
					$GLOBALS['post'] = get_post( $_REQUEST['post_id'] );
					// ping WordPress for an embed
					$check_embed = $wp_embed->run_shortcode( '[embed]'. $oembed_url .'[/embed]' );
				} else {
					// For term or user meta
					$check_embed = wp_oembed_get($oembed_url);
				}
				// fallback that WordPress creates when no oEmbed was found
				$fallback = $wp_embed->maybe_make_link( $oembed_url );

				if ( $check_embed && $check_embed != $fallback ) {
					// Embed data
					$return = '<div class="embed_status"><div class="embed_content">'. $check_embed .'</div><p><a href="#" class="th_remove_file_button button" rel="'. $_REQUEST['field_id'] .'">'. __( 'Remove Embed', 'text_domain' ) .'</a></p></div>';
					// set our response id
					$found = 'found';

				} else {
					// error info when no oEmbeds were found
					$return = '<p class="errormessage">'.sprintf( __( 'No oEmbed Results Found for %s. View more info at', 'text_domain' ), $fallback ) .' <a href="http://codex.wordpress.org/Embeds" target="_blank">codex.wordpress.org/Embeds</a>.</p>';
					// set our response id
					$found = 'not found';
				}
			}

			// send back our encoded data
			echo json_encode( array( 'result' => $return, 'id' => $found ) );
			die();
		}
	}
}
