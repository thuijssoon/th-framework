<?php

/**
 * Class adding functionality to add checkbox field
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Checkbox' ) ) {

	class TH_Meta_Field_Checkbox extends TH_Meta_Field {

		private static $script_included = false;

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

			$this->type = 'checkbox';

			add_action( 'admin_footer', array( $this, 'print_trigger_save_script'));
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
			$attributes[] = 'type="checkbox"';

			// Name
			$attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';
			$attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';
			
			// Checked
			$attributes[] = checked( $value, true, false );

			// Class
			if ( !empty( $this->properties['class'] ) ) {
				$attributes[] = 'class="' . sanitize_html_class( $this->properties['class'] ) . '"';
			}

			// Disabled
			if ( isset( $this->properties['disabled'] ) && $this->properties['disabled'] ) {
				$attributes[] = 'disabled="disabled"';
			}

			// Read only
			if ( isset( $this->properties['read_only'] ) && $this->properties['read_only'] ) {
				$attributes[] = 'readonly="readonly"';
			}

			// Build input field
			$output .= '<fieldset><legend class="screen-reader-text"><span>' . esc_html( $this->properties['label'] ) . '</span></legend>';
			$output .= '<label for="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '">';
			$output .= '<input ' . implode( ' ', $attributes ) . '/> ';
			$output .= wp_kses_post( $this->properties['description'] );
			$output .= '</label></fieldset>';

			// Error
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( !empty( $error ) ) {
				$output .= '<p class="errormessage">' . wp_kses_post( $error ) . '</p>';
			}

			return apply_filters( 'th_field_' . $this->type . '_field', $output, $value, $error, $this->properties );
		}

		public function validate( &$errors ) {
			$name = $this->namespace . '-' . $this->get_slug();

			// If the field isn't set (checkbox), use empty string (default for get_post_meta()).
			$value = isset( $_POST[$name] );

			$error_messages = apply_filters( 'th_field_' . $this->type . '_error_messages',
				array(
					'required'         => sprintf( __( '%s must be checked', 'text_domain' ), esc_html( $this->properties['label'] ) ),
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
			$('.th_checkbox_cell input').removeAttr("checked");
		}
	});
} );
</script>
<?php
			self::$script_included = true;
		}
	}
}
