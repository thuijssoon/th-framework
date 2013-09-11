<?php

/**
 * Class adding functionality to add text field
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Select' ) ) {
	class TH_Meta_Field_Select extends TH_Meta_Field {

		protected static $use_select2 = false;

		private static $script_included = false;

		protected $missing_options_message;

		public function __construct( $namespace, $properties ) {
			$this->handle_defaults(
				array(
					'multiple' => false,
					'options'  => array(),
					'select2'  => false,
					'sortable' => false,
				)
			);

			parent::__construct( $namespace, $properties );

			if ( $this->properties['sortable'] ) {

				$this->properties['select2']  = true;
				$this->properties['multiple'] = true;
				if ( isset( $this->properties['class'] ) ) {
					$this->properties['class'] .= ' th-select2-sortable';
				} else {
					$this->properties['class'] = 'th-select2-sortable';
				}
				self::$use_select2 = true;

			} elseif ( $this->properties['select2'] ) {

				if ( isset( $this->properties['class'] ) ) {
					$this->properties['class'] .= ' th-select2';
				} else {
					$this->properties['class'] = 'th-select2';
				}
				self::$use_select2 = true;

			}

			$this->type = 'select';

			add_action( 'admin_footer', array( $this, 'print_trigger_save_script'));
		}

		/**
		 * Returns markup for input field
		 *
		 * @return string
		 */
		public function render_field( $value, $error = false ) {

			if ( !empty( $this->properties['multiple'] ) &&
				!is_array( $value )
			) {
				$value = array();
			}

			$output = '';

			// Setup attributes
			$attributes = array();

			// Name
			if ( !empty( $this->properties['multiple'] ) ) {
				$attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[]"';
			} else {
				$attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';
			}
			$attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';

			// Class
			if ( !empty( $this->properties['class'] ) ) {
				$classes = explode(' ', $this->properties['class']);
				$classes = array_map('sanitize_html_class', $classes);
				$classes = implode(' ', $classes);
				$attributes[] = 'class="text widefat regular-text ' . $classes . '"';
			}
			else {
				$attributes[] = 'class="text widefat regular-text"';
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

			// Multiple
			if ( !empty( $this->properties['multiple'] ) ) {
				$attributes[] = 'multiple="multiple"';
			}

			$opt = $this->get_options();

			if ( !$opt ) { // check for errors
				// TODO: Is there a better way to go except by using wp_kses_post?
				// $output .= wp_filter_post_kses( $this->missing_options_message );
				$output .= $this->missing_options_message;
			} else {
				// Build input field
				$output .= '<select ' . implode( ' ', $attributes ) . '>';

				if ( isset( $this->properties['placeholder'] ) &&
					!$this->properties['select2']  &&
					!$this->properties['multiple']
				) {
					$output .= '<option value="th-placeholder">' . $this->properties['placeholder'] . '</option>';
				}

				if ( $this->properties['sortable'] ) {
					foreach ( $value as $v  ) {
						if ( isset( $opt[$v] ) ) {
							$output .= '<option selected="selected"' .  ' value="' . esc_attr( $v ) . '">' . esc_html( $opt[$v] ) . '</option>';
						}
						unset( $opt[$v] );
					}
				}

				foreach ( $opt as $val => $label ) {
					if ( !empty( $this->properties['multiple'] ) ) {
						$output .= '<option  value="' . esc_attr( $val ) . '"'. selected( in_array( $val, $value ), true, false ) . '>' . esc_html( $label ) . '</option>';
					} else {
						$output .= '<option  value="' . esc_attr( $val ) . '"'. selected( $value, $val, false ) . '>' . esc_html( $label ) . '</option>';
					}
				}

				$output .= '</select>';

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

			}

			return apply_filters( 'th_field_' . $this->type . '_field', $output, $value, $error, $this->properties );
		}

		public function validate( &$errors ) {
			// Don't validate if no options defined
			if ( !$this->get_options() )
				return '';

			$name = $this->namespace . '-' . $this->get_slug();

			// If the field isn't set use empty string (default for get_post_meta()).
			$value = isset( $_POST[$name] ) ? $_POST[$name] : '';

			if ( $this->properties['multiple'] && !is_array( $value ) ) {
				$value = (array) $value;
			}

			// Remove th-placeholder from $value
			if ( is_array( $value ) ) {
				$value = array_diff( $value, array( 'th-placeholder' ) );
			} elseif ( 'th-placeholder' == $value ) {
				$value = '';
			}

			$error_messages = apply_filters( 'th_field_' . $this->type . '_error_messages',
				array(
					'required'         => sprintf( __( '%s cannot be empty', 'text_domain' ), esc_html( $this->properties['label'] ) ),
					'only_one'         => __( 'Only one option as allowed', 'text_domain' ),
					'invalid_option'   => __( 'Please select only valid options', 'text_domain' ),
				),
				$this->properties
			);

			// False and 0 could be valid options
			if (
				$this->is_required() && empty( $value ) &&
				'false' !== $value && '0' !== $value
			) {
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['required']
				);
				return '';
			}

			if ( !empty( $value ) && is_array( $value ) && !$this->properties['multiple'] ) {
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['only_one']
				);
				$value = $value[0];
			}

			$valid = array_keys( $this->get_options() );
			if ( is_array( $value ) ) {
				foreach ( $value as $val ) {
					if ( !in_array( $val, $valid ) ) {
						unset( $value[$val] );
						if ( !isset( $errors[$this->get_slug()] ) ) {
							$errors[$this->get_slug()] = array(
									'slug'        => $this->get_slug(),
									'title'       => esc_html( $this->properties['label'] ),
									'message'     => $error_messages['invalid_option']
							);
						}
					}
				}
			} else {
				if ( !in_array( $value, $valid ) ) {
					$value = '';
					if ( !isset( $errors[$this->get_slug()] ) ) {
						$errors[$this->get_slug()] = array(
								'slug'        => $this->get_slug(),
								'title'       => esc_html( $this->properties['label'] ),
								'message'     => $error_messages['invalid_option']
						);
					}
				}
			}

			return $value;
		}

		protected function get_options() {
			if ( empty( $this->properties['options'] ) ) {
				$this->missing_options_message = apply_filters(
					'th_field_' . $this->type . '_missing_options_message',
					__( '<p>No options defined</p>', 'text_domain' )
				);
				return false;
			}
			return $this->properties['options'];
		}

		public static function enqueue_js() {
			if ( self::$use_select2 ) {
				wp_enqueue_script( 'th-select2', plugins_url( 'lib/select2/select2.min.js' , dirname( dirname( __FILE__ ) ) ), array( 'jquery' ), '3.4.0', true );
				wp_enqueue_script( 'th-select2-sortable', plugins_url( 'lib/select2-sortable/select2.sortable.js' , dirname( dirname( __FILE__ ) ) ), array( 'jquery', 'th-select2' ), '3.4.0', true );
				wp_enqueue_script( 'th-meta-field-select', plugins_url( 'js/th-meta-field-select.js' , dirname( __FILE__ ) ), array( 'th-select2-sortable' ), '1.0.0', true );
			}
		}

		public static function enqueue_css() {
			if ( self::$use_select2 ) {
				wp_enqueue_style( 'th-select2', plugins_url( 'lib/select2/select2.css' , dirname( dirname( __FILE__ ) ) ), array(), '3.4.0' );
				wp_enqueue_style( 'th-select2-wordpress', plugins_url( 'select2-wordpress/select2-wordpress.css' , dirname( __FILE__ ) ), array(), '3.4.0' );
			}
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
			$('.th_select_cell select > option').removeAttr("selected");
			$('.th_select_cell select > option').trigger("change");
		}
	});
} );
</script>
<?php
			self::$script_included = true;
		}
	}
}
