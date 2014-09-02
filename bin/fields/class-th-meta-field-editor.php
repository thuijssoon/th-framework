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
					'validation' => 'html_post',
					'max-length' => '',
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
			$settings = array( 'textarea_name' => $this->namespace . '-' . $this->properties['slug'] );
			if ( isset( $this->properties['settings'] ) ) {
				$settings = $this->properties['settings'];

				if(!isset($settings['textarea_name'])) {
					$settings['textarea_name'] = $this->namespace . '-' . $this->properties['slug'];
				}
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

			// Max length
			if(!empty($this->properties['max-length'])) {
				add_filter( 'the_editor', array($this, 'add_props_to_textarea') );
			}

			// Catch output since wp_editor() echoes the result
			ob_start();
			wp_editor( $value, $this->sanitize_id( $this->namespace . '-' . $this->properties['slug'] ), $settings );
			$output .= ob_get_contents();
			ob_end_clean();

			remove_filter( 'the_editor', array($this, 'add_props_to_textarea') );

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

		public function add_props_to_textarea($html) {
			$pieces = explode('name=', $html);
			$insert = 'th-max-length="' . intval($this->properties['max-length']) . '" name=';
			return $pieces[0] . $insert . $pieces[1];
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

			case 'no_html':
				$value = wp_strip_all_tags( $value );
				break;

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

		public static function enqueue_js() {
?>
<script type="text/javascript">
/**
* `String.trim()` polyfill for non-supporting browsers. This is the
* recommended polyfill on MDN.
*
* @see     <http://goo.gl/uYveB>
* @see     <http://goo.gl/xjIxJ>
*
* @return  {String}  The original string with leading and trailing whitespace
*                    removed.
*/

if (!String.prototype.trim) {
	String.prototype.trim = function () {
		return this.replace(/^\s+|\s+$/g, '')
	}
}

function update_message(ed) {
	var $this = jQuery(ed.getElement()),
		max   = typeof undefined === typeof $this.attr('th-max-length') ? -1 : parseInt($this.attr('th-max-length')),
		type  = typeof undefined === typeof $this.attr('th-count') ? 'words' : $this.attr('th-count');

	if(0 < max) {
		$parent_td = $this.parents(".th_editor_cell");
		$error_msg = $parent_td.find('.errormessage:first');
		$descr_msg = $parent_td.find('.word-count:first');
		original   = ed.getContent().replace(/<\/?[a-z][^>]*>/gi, '');
		trimmed    = original.trim();
		wordcount  = trimmed ? (trimmed.replace(/['";:,.?¿\-!¡]+/g, '').match(/\S+/g) || []).length : 0

    	// wordcount = (editor_content.split(' ').length);

		if(wordcount > max) {
			$error_msg.text('You have exceeded ' + max + ' words.');
			$descr_msg.hide();
			$error_msg.show();
		} else {
			$error_msg.hide();					
			$descr_msg.text('You have ' + parseInt(max - wordcount) + ' words left.');
			$descr_msg.show();
		}
	}	
}

window.onload = function () {
	for (i=0; i < tinymce.editors.length; i++){
		$editor_el = jQuery(tinymce.editors[i].getElement());
		$parent_td = $editor_el.parents(".th_editor_cell");
		$error_msg = $parent_td.find('.errormessage:first');
		$descr_msg = jQuery('<p class="description word-count"></p>');
		$iframe    = $parent_td.find('iframe').get(0);

		$parent_td.append($descr_msg);
		$descr_msg.hide();


		if(!$error_msg.length) {
			$error_msg = jQuery('<p class="errormessage"></p>');
			$parent_td.append($error_msg);
			$error_msg.hide();
		}
		
		update_message(tinymce.editors[i]);

		tinymce.editors[i].on('keyup', function(e) {
			update_message(this);
        });

		// tinymce.editors[i].onKeyUp.add( function(ed, e) {
		// 	update_message(ed);
		// });	

	}
}
</script>
<?php
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

		private function sanitize_id( $id ) {
			$return = str_replace( '-', '_', $id);
			$return = str_replace( '[', '_', $return);
			$return = str_replace( ']', '_', $return);
			$return = strtolower($return);
			return $return;
		}

	}
}
