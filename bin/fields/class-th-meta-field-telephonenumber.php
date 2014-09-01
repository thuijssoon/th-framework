<?php

/**
 * Class adding functionality to add text field
 *
 * @todo update error reporting
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Telephonenumber' ) ) {

	class TH_Meta_Field_Telephonenumber extends TH_Meta_Field {


		public function __construct( $namespace, $properties ) {
			if ( !isset( $this->default_properties ) ) {
				$this->default_properties = array();
			}

			$this->default_properties = array_merge(
				array(
					'country-code-required' => false,
					'hide-country-code'     => false,
					'hide-extension'        => false,
					'extension-required'    => false,
				),
				$this->default_properties
			);

			parent::__construct( $namespace, $properties );

			$this->type = 'telephonenumber';
		}

		/**
		 * Returns markup for input field
		 *
		 * @return string
		 */
		public function render_field( $value, $error = false ) {
			$output = '';
			$readonly = '';
			$disabled = '';

			if ( !is_array( $value ) ) {
				$value = array();
				$value['country-code'] = '';
				$value['area-code'] = '';
				$value['subscriber-number'] = '';
				$value['extension'] = '';
			}

			// Name
			$name = esc_attr( $this->namespace . '-' . $this->properties['slug'] );

			// Value
			if ( empty( $value ) && isset( $this->properties['default'] ) ) {
				$value = $this->properties['default'];
			}


			// Disabled
			if ( isset( $this->properties['disabled'] ) && $this->properties['disabled'] ) {
				$disabled = 'disabled="disabled"';
			}

			// Read only
			if ( isset( $this->properties['read_only'] ) && $this->properties['read_only'] ) {
				$readonly = 'readonly="readonly"';
			}

			// Build input field
			$output .= '<div class="th-tel"><div>';

			// Country code
			if(!$this->properties['hide-country-code']) {
				$output .= '<div>+</div><div><input type="text" name="' . $name . '[country-code]" id="' . $name . '-country-code" value="' . esc_attr( $value['country-code'] ) . '" class="text country-code" placeholder="1" maxlength="3" ' . $disabled . ' ' . $readonly . '></div>';
			}

			$output .= '<div><input type="text" name="' . $name . '[area-code]" id="' . $name . '-area-code" value="' . esc_attr( $value['area-code'] ) . '" class="text area-code" placeholder="12345" maxlength="14" ' . $disabled . ' ' . $readonly . '>&nbsp;</div>';
			$output .= '<div><input type="text" name="' . $name . '[subscriber-number]" id="' . $name . '-subscriber-number" value="' . esc_attr( $value['subscriber-number'] ) . '" class="text subscriber-number" placeholder="12345" maxlength="14" ' . $disabled . ' ' . $readonly . '></div>';

			if(!$this->properties['hide-extension']) {
				$output .= '<div><input type="text" name="' . $name . '[extension]" id="' . $name . '-extension" value="' . esc_attr( $value['extension'] ) . '" class="text extension" placeholder="1234" maxlength="5" ' . $disabled . ' ' . $readonly . '></div>';
			}

			$output .= '</div><div>';

			if(!$this->properties['hide-country-code']) {
				$output .= '<div>&nbsp;</div><div><p class="description">Country code&nbsp;&nbsp;</p></div>';
			}

			$output .= '<div><p class="description">Area code&nbsp;</p></div><div><p class="description">Subscriber number&nbsp;</p></div>';

			if(!$this->properties['hide-extension']) {
				$output .= '<div><p class="description">Extension&nbsp;</p></div>';
			}

			$output .= '</div></div>';

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

		/**
		 * Generates HTML markup for the label
		 *
		 * @return string
		 * @author Thijs Huijssoon
		 */
		public function render_label() {
			$lf = ( $this->label_for ) ? ' for="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] . '-country-code' ) . '" ' : '';
			$output = '<label'. $lf . '>' . esc_html( $this->properties['label'] ) . '</label>';
			return apply_filters( 'th_field_' . $this->type . '_label', $output, $this->properties );
		}

		public function validate( &$errors ) {
			$name = $this->namespace . '-' . $this->get_slug();

			// If the field isn't set (checkbox), use empty string (default for get_post_meta()).
			$value = isset( $_POST[$name] ) ? $_POST[$name] : '';

			$error_messages = apply_filters( 'th_field_' . $this->type . '_error_messages',
				array(
					'required'         => sprintf( __( '%s cannot be empty', 'text_domain' ), esc_html( $this->properties['label'] ) ),
					'not-a-number'     => sprintf( __( '%s may only contain digits', 'text_domain' ), esc_html( $this->properties['label'] ) ),
					'number-too-long'  => sprintf( __( '%s may not be longer than 15 digits', 'text_domain' ), esc_html( $this->properties['label'] ) )
				),
				$this->properties
			);

			if($this->properties['hide-country-code']) {
				$value['country-code'] = '';
			}

			if($this->properties['hide-extension']) {
				$value['extension'] = '';
			}

			if(!empty($value['country-code']) && !ctype_digit($value['country-code'])) {
				$value['country-code'] = '';
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['not-a-number']
				);
			}
			if(!empty($value['area-code']) && !ctype_digit($value['area-code'])) {
				$value['area-code'] = '';
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['not-a-number']
				);
			}
			if(!empty($value['subscriber-number']) && !ctype_digit($value['subscriber-number'])) {
				$value['subscriber-number'] = '';
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['not-a-number']
				);
			}
			if(!empty($value['extension']) && !ctype_digit($value['extension'])) {
				$value['extension'] = '';
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['not-a-number']
				);
			}

			if( isset( $errors[$this->get_slug()] ) ) {
				return $value;
			}

			if((strlen($value['country-code']) + strlen($value['area-code']) + strlen($value['subscriber-number'])) > 15 ) {
				$errors[$this->get_slug()] = array(
						'slug'        => $this->get_slug(),
						'title'       => esc_html( $this->properties['label'] ),
						'message'     => $error_messages['number-too-long']
				);
			}

			if( isset( $errors[$this->get_slug()] ) ) {
				return $value;
			}

			if ( $this->is_required() ) {
				if(empty($value['area-code']) || empty($value['subscriber-number'])) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['required']
					);
				} elseif (!$this->properties['hide-country-code'] && $this->properties['country-code-required']  && empty( $value['country-code'])) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['required']
					);
				} elseif (!$this->properties['hide-extension'] && $this->properties['extension-required']  && empty( $value['extension'])) {
					$errors[$this->get_slug()] = array(
							'slug'        => $this->get_slug(),
							'title'       => esc_html( $this->properties['label'] ),
							'message'     => $error_messages['required']
					);
				}
			}

			return $value;
		}

	}
}
