<?php

/**
 * Class adding functionality to add images field
 *
 * @todo update error reporting
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Images' ) ) {

	class TH_Meta_Field_Images extends TH_Meta_Field {

		private static $script_included = false;

		public function __construct( $namespace, $properties ) {
			if ( !isset( $this->default_properties ) ) {
				$this->default_properties = array();
			}

			$this->default_properties = array_merge(
				array(
					'min'        => 0,
					'max'        => 4,
				),
				$this->default_properties
			);

			parent::__construct( $namespace, $properties );

			add_action( 'admin_footer', array( $this, 'print_trigger_save_script'));

			$this->type = 'images';
		}

		/**
		 * Returns markup for input field
		 *
		 * @return string
		 */
		public function render_field( $value, $error = false ) {
			$output = '<ul class="esl-additional-images-container" data-esl-max-images="' . $this->properties['max'] . '">';
			$total_images = 0;

			if ( empty( $value ) ) {
				$value = array();
			}

			if ( !is_array( $value ) ) {
				(array) $value;
			}

			foreach ($value as $image_id) {
				$src = wp_get_attachment_image_src($image_id, 'medium');
				$output .= '<li class="esl-additional-images-picker esl-has-image"';
				$output .= 'data-esl-image-name="' . esc_attr( __( 'Additional location images', 'th_framework' ) );
				$output .= 'data-esl-image-select="' . esc_attr( __( 'Add image', 'th_framework' ) );
				$output .= 'data-esl-image-title="' . esc_attr( __( 'Add image', 'th_framework' ) ) . '">';
				$output .= '<input type="hidden" class="esl-image-input" name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[]" value="' . esc_attr( intval( $image_id ) ) .'">';
				$output .= '<a href="#" class="esl-image-preview"><img src="' . $src[0] . '" /></a>';
				$output .= '<p><a class="esl-image-remove" href="#">Remove image</a>';
				$output .= '<a class="esl-image-add" href="#">Add image</a></p>';
				$output .= '<div class="esl-image-drag-handle wp-ui-highlight dashicons dashicons-leftright"></div></li>';
				$total_images += 1;
			}
	
			if ($total_images < intval($this->properties['max'])) {
				$output .= '<li class="esl-additional-images-picker esl-no-image"';
				$output .= 'data-esl-image-name="' . esc_attr( __( 'Additional location images', 'th_framework' ) );
				$output .= 'data-esl-image-select="' . esc_attr( __( 'Add image', 'th_framework' ) );
				$output .= 'data-esl-image-title="' . esc_attr( __( 'Add image', 'th_framework' ) ) . '">';
				$output .= '<input type="hidden" class="esl-image-input" name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[]" value="">';
				$output .= '<a href="#" class="esl-image-preview"></a>';
				$output .= '<p><a class="esl-image-remove" href="#">Remove image</a>';
				$output .= '<a class="esl-image-add" href="#">Add image</a></p>';
				$output .= '<div class="esl-image-drag-handle wp-ui-highlight dashicons dashicons-leftright"></div></li>';
			}

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

			return $value;
		}

		public static function enqueue_js() {
			wp_enqueue_media();
			wp_enqueue_script( 'th-meta-field-images', plugins_url( 'js/th-meta-field-images.js' , dirname( __FILE__ ) ), array( 'jquery' ), '1.0.0', true );
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
