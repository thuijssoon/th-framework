<?php

/**
 * Class adding functionality to add editor field
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Editor' ) ) {
	class TH_Meta_Field_Editor extends TH_Meta_Field {

		private static $script_included = false;

		public function __construct( $namespace, $properties ) {
			if ( !isset( $this->default_properties ) ) {
				$this->default_properties = array();
			}

			$this->default_properties = array_merge(
				array(
					'validation' => 'html_post'
				),
				$this->default_properties
			);

			parent::__construct( $namespace, $properties );

			$this->type = 'editor';
			$this->label_for = false;

			add_action( 'admin_footer', array( $this, 'print_trigger_save_script'));
		}

		/**
		 * Returns markup for input field
		 *
		 * @return string
		 */
		public function render_field( $value, $error = false ) {
			$output = '';

			// Settings
			$settings = array();
			if ( isset( $this->properties['settings'] ) ) {
				$settings = $this->properties['settings'];
			}

			// Class
			if ( isset( $this->properties['class'] ) ) {
				if ( isset( $settings['editor_class'] ) ) {
					$settings['editor_class'] .= ' ' . sanitize_html_class( $this->properties['class'] );
				}
				else {
					$settings['editor_class'] = sanitize_html_class( $this->properties['class'] );
				}
			}

			// Value
			if ( empty( $value ) && isset( $this->properties['default'] ) ) {
				$value = $this->properties['default'];
			}

			// Catch output since wp_editor() echoes the result
			ob_start();
			wp_editor( $value, $this->namespace . '-' . $this->properties['slug'], $settings );
			$output .= ob_get_contents();
			ob_end_clean();

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

			case 'html_post':
				$value = wp_kses_post( $value );
				break;

			case 'html_data':
			default:
				$value = wp_kses_data( $value );
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
jQuery('#submit').mousedown( function() {
    tinyMCE.triggerSave();
    });
jQuery( document ).ready( function($) {
	$(document).ajaxSuccess(function(event, xhr, settings) {
		if ( settings.data.indexOf("action=add-tag") !== -1 ) {
			// var length = tinymce.editors.length;
			for (i=0; i < tinymce.editors.length; i++){
				tinymce.editors[i].setContent(''); // get the content
			}
		}
	});
} );
</script>
<?php
			self::$script_included = true;
		}

	}
}
