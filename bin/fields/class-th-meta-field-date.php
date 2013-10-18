<?php

/**
 * Class adding functionality to add date field
 *
 * @todo update error reporting
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Date' ) ) {

	class TH_Meta_Field_Date extends TH_Meta_Field {

		private static $script_included = false;

		public function __construct( $namespace, $properties ) {
			if ( !isset( $this->default_properties ) ) {
				$this->default_properties = array();
			}

			$this->default_properties = array_merge(
				array(
					'minDateField' => false,
					'maxDateField' => false,
				),
				$this->default_properties
			);

			parent::__construct( $namespace, $properties );

			add_action( 'admin_footer', array( $this, 'print_trigger_save_script' ) );

			$this->type = 'date';
		}

		/**
		 * Returns markup for input field
		 *
		 * @return string
		 */
		public function render_field( $value, $error = false ) {

			$value = TH_Util::gmt_to_local( $value );
			$value = date('Y-m-d', $value);

			$output = '';

			// Setup attributes
			$attributes = array();

			// Type
			$attributes[] = 'type="hidden"';

			// Name
			$attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';
			$attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '"';

			// Value
			if ( empty( $value ) && isset( $this->properties['default'] ) ) {
				$value = $this->properties['default'];
			}

			$attributes[] = 'value="' . esc_attr( $value ) . '"';

			// Class
			if ( !empty( $this->properties['class'] ) ) {
				$attributes[] = 'class="text text_small' . sanitize_html_class( $this->properties['class'] ) . '"';
			}
			else {
				$attributes[] = 'class="text text_small"';
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
			
			$data_attr = '';
			if($this->properties['minDateField']) {
				$data_attr .= 'data-min="' . esc_attr( $this->namespace . '-' . $this->properties['minDateField'] . '-cal') . '"';
			}
			if($this->properties['maxDateField']) {
				$data_attr .= 'data-max="' . esc_attr( $this->namespace . '-' . $this->properties['maxDateField'] . '-cal') . '"';
			}
			$output .= '<div class="th-date" id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] . '-cal' ) . '" data-input="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '" ' . $data_attr . ' ></div>';

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
					'not_date'       => sprintf( __( '%s is not a date', 'text_domain' ), esc_html( $value ) ),
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

			$value = strtotime( $value );
			$value = TH_Util::local_to_gmt($value);

			return $value;
		}

		public static function enqueue_js() {
			wp_enqueue_script( 'th-meta-field-date', plugins_url( 'js/th-meta-field-date.js' , dirname( __FILE__ ) ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker' ), '1.0.0', true );
		}

		public static function enqueue_css() {
			if ( 'classic' == get_user_option( 'admin_color' ) )
				wp_enqueue_style ( 'jquery-ui-css', plugins_url( 'lib/jquery-ui-wordpress/jquery-ui-classic.css' , dirname( dirname( __FILE__ ) ) ) );
			else
				wp_enqueue_style ( 'jquery-ui-css', plugins_url( 'lib/jquery-ui-wordpress/jquery-ui-fresh.css' , dirname( dirname( __FILE__ ) ) ) );
		}

		public function print_trigger_save_script() {
			// Only include script once:
			if ( self::$script_included ) {
				return;
			}
			// Only continue if adding new tag
			if ( 'edit-tags' !== get_current_screen()->base || isset( $_GET['tag_ID'] ) ) {
				return;
			}
?>
<script type="text/javascript">
jQuery( document ).ready( function($) {
	$(document).ajaxSuccess(function(event, xhr, settings) {
		if ( settings.data.indexOf("action=add-tag") !== -1 ) {
			$('.th_date_cell input').val('');
		}
	});
} );
</script>
<?php
			self::$script_included = true;
		}

	}
}
