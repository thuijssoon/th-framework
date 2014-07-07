<?php

/**
 * Class adding functionality to add text field
 *
 * @author Thijs Huijssoon
 */

if ( !class_exists( 'TH_Meta_Field_Select' ) ) {
	require_once 'class-th-meta-field-select.php';
}

if ( !class_exists( 'TH_Meta_Field_Gfselect' ) ) {
	class TH_Meta_Field_Gfselect extends TH_Meta_Field_Select {

		protected $result = null;

		public function __construct( $namespace, $properties ) {
			$this->handle_defaults(
				array()
			);

			parent::__construct( $namespace, $properties );

			$this->type = 'gfselect';
		}

		protected function get_options() {
			if ( !is_null( $this->result ) ) {
				return $this->result;
			}


			$error_messages = apply_filters( 'th_field_' . $this->type . '_missing_options_messages',
				array(
					'no_such_post_type' => __( 'Gravity Forms is not active', 'text_domain' ),
					'no_posts_found'    => sprintf( __( 'No forms found. <a href="%1$s">Create some forms</a>.', 'text_domain' ), admin_url( 'admin.php?page=gf_new_form' ) ),
				),
				$this->properties
			);

			if ( !class_exists( 'RGFormsModel' ) ) {
				$this->missing_options_message = $error_messages['no_such_post_type'];
				$this->result = false;
				return $this->result;
			}

			$forms = RGFormsModel::get_forms( null, 'title' );

			$result = array();

			foreach ( $forms as $form ) {
				$result[$form->id] = $form->title;
			}

			$this->result = $result;

			if ( empty( $this->result ) ) {
				$this->missing_options_message = $error_messages['no_posts_found'];
				$this->result = false;
				return $this->result;
			}

			return $this->result;
		}

	}
}
