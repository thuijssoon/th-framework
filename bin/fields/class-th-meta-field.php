<?php

/**
 * Abstract class adding common functionality for each input field
 *
 * @todo  Document further.
 *
 * @author Thijs Huijssoon
 */

if ( !class_exists( 'TH_Meta_Field' ) ) {

	abstract class TH_Meta_Field {

		/**
		 * Default field properties
		 *
		 * @var array
		 */
		protected $default_properties;

		/**
		 * Field properties
		 *
		 * @var array
		 */
		protected $properties;

		/**
		 * The namespace of the field
		 *
		 * @var string
		 */
		protected $namespace;

		/**
		 * The type of the field
		 * used for filter names
		 *
		 * @var string
		 */
		protected $type;

		/**
		 * Render the label_for attribute.
		 *
		 * @var boolean
		 */
		protected $label_for = true;


		public function __construct( $namespace, $properties ) {
			// Validate slug
			if ( !isset( $properties['slug'] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Slug must be defined.</strong>', 'text_domain' ) );
				}
				return;
			}

			// Validate label
			if ( !isset( $properties['label'] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Label must be defined.</strong>', 'text_domain' ) );
				}
				return;
			}

			$this->namespace = $namespace;

			// if ( !isset( $this->default_properties ) ) {
			//  $this->default_properties = array();
			// }

			// $this->default_properties = array_merge(
			//  array(
			//   'required'    => false,
			//   'disabled'    => false,
			//   'read_only'   => false,
			//   'default'     => '',
			//   'placeholder' => '',
			//   'class'       => '',
			//  ),
			//  $this->default_properties
			// );

			$this->handle_defaults(
				array(
					'required'    => false,
					'disabled'    => false,
					'read_only'   => false,
					'default'     => '',
					'placeholder' => '',
					'class'       => '',
					'show_column' => false,
				)
			);

			// $this->properties = array_merge( $this->default_properties, $properties );

			$this->handle_properties( $properties );

			if ( $this->show_column() ) {
				$column_args = $this->get_column_args();

				$default_column_args = apply_filters(
					'th_meta_default_column_args',
					array(
						'display_after' => 'default',
						'label'         => $this->properties['label'],
						'sortable'      => true,
					)
				);

				if ( true === $column_args ) {
					// Use the default values
					$column_args = $default_column_args;
				} else {
					$column_args = wp_parse_args( $column_args, $default_column_args );
				}

				$this->properties['show_column'] = $column_args;
			}
		}

		/**
		 * Generates HTML markup for the input field
		 *
		 * @return string
		 * @author Thijs Huijssoon
		 */
		abstract protected function render_field( $value, $error = false );

		/**
		 * Generates HTML markup for the label
		 *
		 * @return string
		 * @author Thijs Huijssoon
		 */
		public function render_label() {
			$lf = ( $this->label_for ) ? ' for="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '" ' : '';
			$output = '<label'. $lf . '>' . esc_html( $this->properties['label'] ) . '</label>';
			return apply_filters( 'th_field_' . $this->type . '_label', $output, $this->properties );
		}

		/**
		 * Generates HTML markup for input field
		 *
		 * @return mixed sanitized value
		 * @author Thijs Huijssoon
		 */
		abstract protected function validate( &$errors );

		/**
		 * Retrieves whether required
		 *
		 * @return boolean
		 * @author Thijs Huijssoon
		 */
		public function is_required() {
			return $this->properties['required'];
		}

		/**
		 * Retrieves the slug
		 *
		 * @return void
		 * @author Thijs Huijssoon
		 */
		public function get_slug() {
			if ( isset( $this->properties['slug'] ) ) {
				return $this->properties['slug'];
			}
			else {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>No slug defined.</strong>', 'text_domain' ) );
				}
				return false;
			}
		}

		public function get_class() {
			return sanitize_html_class( 'th_' . $this->type . '_cell' );
		}

		public static function enqueue_css() {
		}

		public static function enqueue_js() {
		}

		public function is_taxonomy() {
			return isset( $this->properties['taxonomy'] );
		}

		public function get_taxonomy() {
			return $this->properties['taxonomy'];
		}

		public function id_or_slug() {
			if ( isset( $this->properties['id_or_slug'] ) ) {
				return $this->properties['id_or_slug'];
			} else {
				return 'slug';
			}
		}

		public function show_column() {
			return (true === $this->properties['show_column'] || is_array( $this->properties['show_column'] ) );
		}

		public function get_column_args() {
			return $this->properties['show_column'];
		}

		public function get_column_value($value) {
			return esc_html($value);
		}

		public function get_source_clonable_value($value) {
			return $value;
		}

		public function get_destination_clonable_value($value) {
			return $value;
		}

		public function get_cloned_value( $source_blog_id, $item_id, $new_item_id, $meta_value ) {
			return $meta_value;
		}

		protected function handle_defaults( $defaults ) {
			if ( !isset( $this->default_properties ) ) {
				$this->default_properties = array();
			}
			$this->default_properties = self::parse_args_r( $this->default_properties, $defaults );
		}

		protected function handle_properties( $properties ) {
			$this->properties = self::parse_args_r( $properties, $this->default_properties );
		}

		/**
		 * A recursive sort of wp_parse_args
		 * lifted from @link https://gist.github.com/boonebgorges/5510970
		 *
		 * @param array   $a args
		 * @param array   $b defaults
		 * @return array     result
		 */
		private static function parse_args_r( &$a, $b ) {
			$a = (array) $a;
			$b = (array) $b;
			$r = $b;

			foreach ( $a as $k => &$v ) {
				if ( is_array( $v ) && isset( $r[ $k ] ) ) {
					$r[ $k ] = self::parse_args_r( $v, $r[ $k ] );
				} else {
					$r[ $k ] = $v;
				}
			}

			return $r;
		}
	}

}
