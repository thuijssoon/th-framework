<?php

/**
 * Class adding functionality to add text field
 *
 * @todo update error reporting
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Text' ) ) {

	class TH_Meta_Field_Text extends TH_Meta_Field {

		private static $script_included = false;

		public function __construct( $namespace, $properties ) {
			if ( !isset( $this->default_properties ) ) {
				$this->default_properties = array();
			}

			$this->default_properties = array_merge(
				array(
					'validation' => 'no_html',
					'pattern'    => //'[\+]\d{2}[\(]\d{2}[\)]\d{4}[\-]\d{4}', // Used for the tel type
					"/^  
            (1[-\s.])?  # optional '1-', '1.' or '1' 
            ( \( )?     # optional opening parenthesis 
            \d{3}       # the area code 
            (?(2) \) )  # if there was opening parenthesis, close it 
            [-\s.]?     # followed by '-' or '.' or space 
            \d{3}       # first 3 digits 
            [-\s.]?     # followed by '-' or '.' or space 
            \d{4}       # last 4 digits  
            $/x",
					'min'        => 0,
					'max'        => 100,
					'step'       => 1,
				),
				$this->default_properties
			);

			parent::__construct( $namespace, $properties );

			add_action( 'admin_footer', array( $this, 'print_trigger_save_script'));

			$this->type = 'text';
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
			if ( in_array( $this->properties['validation'], array( 'email', 'url', 'number', 'tel' ) ) ) {
				$attributes[] = 'type="' . esc_attr( $this->properties['validation'] ) . '"';
			} else {
				$attributes[] = 'type="text"';
			}

			// Name
			$attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';
			$attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';

			// Value
			if ( empty( $value ) && isset( $this->properties['default'] ) ) {
				$value = $this->properties['default'];
			}

			if ( 'url' == $this->properties['validation'] ) {
				$attributes[] = 'value="' . esc_url( $value ) . '"';
			} else {
				$attributes[] = 'value="' . esc_attr( $value ) . '"';
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

			// Read only
			if ( isset( $this->properties['read_only'] ) && $this->properties['read_only'] ) {
				$attributes[] = 'readonly="readonly"';
			}

			// Placeholder
			if ( isset( $this->properties['placeholder'] ) ) {
				$attributes[] = 'placeholder="' . esc_attr( $this->properties['placeholder'] ) . '"';
			}

			// Min, max & step if number
			if ( 'number' == $this->properties['validation'] ) {
				$attributes[] = 'min="' . esc_attr( $this->properties['min'] ) . '"';
				$attributes[] = 'max="' . esc_attr( $this->properties['max'] ) . '"';
				$attributes[] = 'step="' . esc_attr( $this->properties['step'] ) . '"';
			}

			// Build input field
			$output .= '<input ' . implode( ' ', $attributes ) . '/>';

			// Error
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( !empty( $error ) ) {
				$output .= '<p class="errormessage">' . wp_kses_post( $error ) . '</p>';
			}

			// Description
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( isset( $this->properties['description'] ) ) {
				$output .= '<p class="description">' . wp_kses_post( $this->properties['description'] ) . '</p>';
			}

			return apply_filters( 'th_field_' . $this->type . '_field', $output, $value, $error, $this->properties );
		}

		public function validate( &$errors ) {
			$name = $this->namespace . '-' . $this->get_slug();

			// If the field isn't set (checkbox), use empty string (default for get_post_meta()).
			$value = isset( $_POST[$name] ) ? $_POST[$name] : '';

			$error_messages = apply_filters( 'th_field_' . $this->type . '_error_messages',
				array(
					'required'         => sprintf( __( '%s cannot be empty', 'text_domain' ), esc_html( $this->properties['label'] ) ),
					'not_url'          => sprintf( __( '%s is not a well formed url', 'text_domain' ), esc_html( $value ) ),
					'not_email'        => sprintf( __( '%s is not a well formed email address', 'text_domain' ), esc_html( $value ) ),
					'not_tel'          => sprintf( __( '%s is not a well formed telephone number', 'text_domain' ), esc_html( $value ) ),
					'not_number'       => sprintf( __( '%s is not a number', 'text_domain' ), esc_html( $value ) ),
					'number_too_big'   => sprintf( __( '%s exceeds the maximum value of %s', 'text_domain' ), esc_html( $value ), esc_html( $this->properties['max'] ) ),
					'number_too_small' => sprintf( __( '%s is less than the minimum value of %s', 'text_domain' ), esc_html( $value ), esc_html( $this->properties['min'] ) ),
					'not_twitter_id'   => sprintf( __( '%s is not a well formed Twitter username. Twitter usernames must have between 1 and 15 characters and may only contain the following characters: A to Z, a to z, 0 to 9 and _', 'text_domain' ), esc_html( $value ) ),
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

			switch ( $this->properties['validation'] ) {
			case 'url':
				$clean_url = esc_url( $value );
				if ( $clean_url !== $value ) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['not_url']
					);
					$value =  $clean_url;
				}
				break;

			case 'email':
				if ( !is_email( $value ) ) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['not_email']
					);
					$value =  sanitize_email( $value );
				}
				break;

			case 'tel':
				if ( !preg_match( $this->properties['pattern'], $value ) ) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['not_tel']
					);
					// Strip out everything that not: 0-9, +, (), # or a single space
					$value =  preg_replace( "/[^0-9\(\)\+\#\s]/", '', $value );
				}
				break;

			case 'twitter':
				// Remove the @ sign from the start if present
				$value = ltrim($value, '@');
				$len   = strlen( $value );

				// Check the length
				if( $len < 1 || $len > 15 ) {
					// Truncate string
					$value = substr($value, 0, 15);
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['not_twitter_id']
					);
				}

				// Check allowed characters
				if( !preg_match('/^[A-Za-z0-9_]{1,15}$/', $value) ) {
					$value = preg_replace("/[^A-Za-z0-9_]/", "", $value);
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['not_twitter_id']
					);
				}
				break;

			case 'number':
				if ( !is_numeric( $value ) ) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['not_number']
					);
					$value = '';
				} elseif ( isset( $this->properties['min'] ) && $value < $this->properties['min'] ) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['number_too_small']
					);
					$value = $this->properties['min'];
				} elseif ( isset( $this->properties['max'] ) && $value > $this->properties['max'] ) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['number_too_large']
					);
					$value = $this->properties['max'];
				}
				break;

			case 'html_post':
				$value = wp_kses_post( $value );
				break;

			case 'html_data':
				$value = wp_kses_data( $value );
				break;

			case 'no_html':
			default:
				$value = sanitize_text_field( $value );
				break;
			}

			return $value;
		}

		public function print_trigger_save_script() {
			// Only include script once:
			if(self::$script_included) {
				return;
			}
			// Only continue if adding new tag
			if('edit-tags' !== get_current_screen()->base || isset($_GET['tag_ID'])) {
				return;
			}
?>
<script type="text/javascript">
jQuery( document ).ready( function($) {
	$(document).ajaxSuccess(function(event, xhr, settings) {
		if ( settings.data.indexOf("action=add-tag") !== -1 ) {
			$('.th_text_cell input').val('');
		}
	});
} );
</script>
<?php
			self::$script_included = true;
		}

	}
}
