<?php
/**
 * WordPress Custom Post Types
 *
 * Contains the TH_Meta class. Requires WordPress version 3.5 or greater.
 *
 * @version   0.1.0
 * @package   TH CPT
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL 2.0+ - http://www.gnu.org/licenses/gpl.txt
 * @link      https://github.com/thuijssoon/
 * @copyright Copyright (c) 2013 - Thijs Huijssoon
 */

if ( !class_exists( 'TH_Meta' ) ) {

	/*
	 * WordPress Custom Meta Class
	 *
	 * A base class for:
	 * -  TH_Post_Meta
	 * -  TH_Term_Meta
	 * -  TH_User_Meta
	 *
	 * @package TH CPT
	 */
	abstract class TH_Meta {

		/**
		 * Default meta properties.
		 *
		 * @var array
		 */
		protected $default_meta;

		/**
		 * Field properties.
		 *
		 * @var array
		 */
		protected $meta;

		/**
		 * Array of filed objects.
		 *
		 * @var array
		 */
		protected $field_objects = null;

		/**
		 * Array of field classes.
		 *
		 * @var array
		 */
		protected $field_classes = null;

		/**
		 * Is this meta visible.
		 *
		 * @var integer
		 */
		private $is_visible = -1;

		/**
		 * The admin page on which to enqueue the assets
		 *
		 * @var array
		 */
		protected $admin_pages;

		/**
		 * Suppress hook execution for save, edit & delete
		 * actions. Used when programatically copying or 
		 * manipulating terms or posts.
		 * 
		 * @var boolean
		 */
		protected static $suppress_hooks = false;

		/**
		 * Create a TH_Meta object.
		 *
		 * @param array   $meta Array of meta definitions
		 */
		public function __construct( $meta ) {

			// Check for errors
			if ( empty( $meta['id'] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>ID cannot be empty.</strong>', 'text_domain' ) );
				}
			}
			if ( empty( $meta['title'] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Title cannot be empty.</strong>', 'text_domain' ) );
				}
			}

			$this->default_meta = array_merge(
				array(
					'id'         => '',
					'title'      => '',
					'save_mode'  => 'single', // single or individual
					'show_names' => true,     // Show field names on the left
					'show_on'    => array(),  // Specific post IDs to display this metabox
					'fields'     => array()   // Empty fields array to enable filter later on
				),
				$this->default_meta
			);

			$this->meta = array_merge( $this->default_meta, $meta );

			// Load our custom assets
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			// Esure the error class is loaded
			if(!class_exists('WPTP_Error')) {
				require_once 'class-wptp-error.php';
			}

			// always init the fields if doing ajax
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$this->init_fields();
			}
		}

		public abstract function render();

		public abstract function save( $object_id );

		/**
		 * Enqueue required js & css files.
		 * Loops through the field classes and calls
		 * enqueue_js() & enqueue_css()
		 *
		 * @return void
		 */
		public function enqueue_assets( $hook ) {
			if ( !is_array( $this->admin_pages ) || !in_array( $hook, $this->admin_pages ) ) {
				return;
			}

			if ( !$this->is_visible() ) {
				return;
			}

			wp_enqueue_style( 'th-cpt', plugins_url( 'css/th-cpt.css' , __FILE__ ), array(), '0.1.0' );

			foreach ( $this->get_field_classes() as $class ) {
				$class::enqueue_js();
				$class::enqueue_css();
			}
		}

		public static function suppress_hooks( $suppress = true ) {
			self::$suppress_hooks = $suppress;
		}

		/**
		 * Is this meta visible?
		 *
		 * @return boolean True if visible, false if not
		 */
		protected function is_visible() {
			if ( -1 !== $this->is_visible ) {
				return $this->is_visible;
			}

			switch ( get_class( $this ) ) {
			case 'TH_Post_Meta':
				$tag = 'th_post_meta_show_on';
				break;

			case 'TH_Term_Meta':
				$tag = 'th_term_meta_show_on';
				break;

			case 'TH_User_Meta':
				$tag = 'th_user_meta_show_on';
				break;
			}

			$this->is_visible = apply_filters(
				$tag,
				true,
				$this->meta
			);

			return $this->is_visible;
		}

		/**
		 * Validate the fields, capture errors & return
		 * sanitised values.
		 *
		 * @todo error handling not prety.
		 *
		 * @return array Sanitised values
		 */
		protected function validate() {
			$validation_errors = array();
			$sanitised_values  = array( 'meta' => array(), 'tax' => array() );

			// Loop through the fields and validate them
			foreach ( $this->get_field_objects() as $field ) {
				$value = $field->validate( $validation_errors );
				if ( !empty( $validation_errors ) ) {
					WPTP_Error::get_instance()->add_error( $validation_errors[$field->get_slug()] );
					$validation_errors = array();
				}
				if ( $field->is_taxonomy() ) {
					$sanitised_values['tax'][$field->get_slug()]['value'] = $value;
					$sanitised_values['tax'][$field->get_slug()]['taxonomy'] = $field->get_taxonomy();
				} else {
					$sanitised_values['meta'][$field->get_slug()] = $value;
				}
			}

			return $sanitised_values;
		}

		/**
		 * Get the error messages associated with the slug.
		 *
		 * @uses   TH_Error
		 *
		 * @param string  $slug Field slug
		 * @return false|string         The error message or false if none
		 */
		protected function get_error_message( $slug ) {
			$errors = WPTP_Error::get_instance()->get_saved_errors();
			if ( isset( $errors[$slug] ) ) {
				return $errors[$slug]['message'];
			}
			return false;
		}

		/**
		 * Get the field objects. Calls init_fields if needed.
		 *
		 * @return array  The array of field objects
		 */
		protected function get_field_objects() {
			if ( is_array( $this->field_objects ) ) {
				return $this->field_objects;
			}

			$this->init_fields();

			return $this->field_objects;
		}

		/**
		 * Get the field classes. Calls init_fields if needed.
		 *
		 * @return array  The array of field classes
		 */
		protected function get_field_classes() {
			if ( is_array( $this->field_classes ) ) {
				return $this->field_classes;
			}

			$this->init_fields();

			return $this->field_classes;
		}

		protected function is_our_meta( $meta_key ) {
			if('single' === $this->meta['save_mode']) {
				return $meta_key === $this->meta['id'];
			}
			$field_objects = $this->get_field_objects();

			foreach ($field_objects as $key => $value) {
				if ( $key === $meta_key ) {
					return true;
				}
			}

			return false;
		}

		protected function process_cloned_meta( $source_blog_id, $item_id, $new_item_id, $meta_key, $unserialized_meta_value ) {

			// Loop through the fields and render them
			$field_objects = $this->get_field_objects();
			$temp_meta     = null;

			// Deal with meta boxes saved in one meta value
			if ( 'single' === $this->meta['save_mode'] ) {
				// Only deal with our meta
				if( $meta_key !== $this->meta['id']) {
					return $unserialized_meta_value;
				}

				$temp_meta = array();

				// It is our meta, so loop over the field objects
				foreach ( $field_objects as $field_object_key => $field_object ) {
					// Check for missing meta values and add empty strings if necessary
					$meta_value = (isset($unserialized_meta_value[$field_object_key])) ? $unserialized_meta_value[$field_object_key] : '';
					$temp_meta[$field_object_key] = $field_object->get_cloned_value( $source_blog_id, $item_id, $new_item_id, $meta_value );
				}

				// If it is not our meta, just return the raw value
				return $temp_meta;
			}

			// Handle individual values
			// Check if it is our meta
			if ( isset( $field_objects[$meta_key] ) ) {
				$temp_meta = $field_objects[$meta_key]->get_cloned_value( $source_blog_id, $item_id, $new_item_id, $unserialized_meta_value );

				return $temp_meta;
			}

			return $unserialized_meta_value;
		}		

		/**
		 * Initialise the fields.
		 * Loop through the definitions. Load the class if required
		 * and instantiate the object for each definition.
		 *
		 * @return void
		 */
		protected function init_fields() {
			$this->field_classes = array();
			$this->field_objects = array();

			$this->meta['fields'] = apply_filters( 
				'th-meta-' . $this->meta['id'] . '-fields', 
				$this->meta['fields'] 
			);

			if ( empty( $this->meta['fields'] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>Meta must have fields.</strong>', 'text_domain' ) );
				}
			}

			foreach ( $this->meta['fields'] as $slug => $field ) {
				$class_name = 'TH_Meta_Field_' . ucfirst( $field['type'] );
				$file_name = 'class-th-meta-field-' . strtolower( $field['type'] ) . '.php';
				if ( !class_exists( $class_name ) ) {
					if ( file_exists( dirname( __FILE__ ) . '/fields/' . $file_name ) ) {
						require_once 'fields/' . $file_name;
					} else {
						if ( WP_DEBUG ) {
							trigger_error( sprintf( __( '<strong>Could not load class %1$s:</strong> file %2$s not found.', 'text_domain' ), $class_name, $file_name ) );
						}
					}
				}
				$field['slug'] = $slug;
				$this->field_objects[$slug] = new $class_name( $this->meta['id'], $field );
				$this->field_classes[] = $class_name;
			}
			$this->field_classes = array_unique( $this->field_classes );
		}

	}

}
