<?php
/**
 * TH_Shortcode
 *
 * @package   TH_Framework
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL-2.0+
 * @link      http://www.github.com/thuijssoon/th-framework/
 * @copyright 2014 Thijs Huijssoon
 */

/**
 * TH_Shortcode class.
 *
 * @package TH_Location
 * @version 0.0.1
 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
 */

// don't allow this file to be loaded directly
if ( __FILE__ == $_SERVER['SCRIPT_FILENAME'] ) {
	die( 'Access denied.' );
}

// Only create a class that doesn't exist...
if ( !class_exists( 'TH_Shortcode' ) ) {

	class TH_Shortcode {

		protected $shortcode_name = '',
		$shortcode_called = false,
		$use_cache = true,
		$required_scripts = array(),
		$required_styles = array(),
		$default_attributes = array();

		public function __construct() {
			add_shortcode( $this->shortcode_name, array( $this, 'process_shortcode' ) );

			if ( count( $this->required_scripts ) || count( $this->required_styles ) ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'process_frontend_enqueues' ) );
			}
		}

		/**
		 * Gets all of the shortcodes in the current post
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param string  $content
		 * @return mixed false | array
		 */
		protected function get_shortcodes( $content ) {
			$matches = array();

			preg_match_all( '/'. get_shortcode_regex() .'/s', $content, $matches );
			if ( !is_array( $matches ) || !array_key_exists( 2, $matches ) )
				return false;

			return $matches;
		}

		/**
		 * Validates and cleans the map shortcode attributes
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param array
		 * @return array
		 */
		protected function clean_attributes( $attributes ) {
			return $attributes;
		}

		/**
		 * Checks the current post to see if they contain the map shortcode
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 * @link http://wordpress.org/support/topic/plugin-basic-google-maps-placemarks-can-i-use-the-shortcode-on-any-php-without-assign-it-in-functionphp
		 * @return bool
		 */
		protected function shortcode_called() {
			global $post;

			$this->shortcode_called = apply_filters( $this->shortcode_name .'-shortcode-called', $this->shortcode_called );

			if ( $this->shortcode_called )
				return true;

			if ( !$post )  // note: this needs to run after the above code, so that templates can call do_shortcode(...) from templates that don't have $post, like 404.php. See link in phpDoc @link for background.
				return false;

			$shortcodes = $this->get_shortcodes( $post->post_content );  // note: don't use setup_postdata/get_the_content() in this instance -- http://lists.automattic.com/pipermail/wp-hackers/2013-January/045053.html

			for ( $i = 0; $i < count( $shortcodes[ 2 ] ); $i++ ) {
				if ( $this->shortcode_name == $shortcodes[ 2 ][ $i ] ) {
					return true;
				}
			}

			return false;
		}

		public function process_shortcode( $attributes, $content = null ) {
			// Check that all required resources have been enqueued.
			$enqueue_requirements_met = true;
			foreach ( $this->required_scripts as $required_script ) {
				$enqueue_requirements_met = $enqueue_requirements_met && wp_script_is( $required_script, 'queue' );
			}
			foreach ( $this->required_styles as $required_style ) {
				$enqueue_requirements_met = $enqueue_requirements_met && wp_style_is( $required_style, 'queue' );
			}

			if ( !$enqueue_requirements_met ) {
				$error = sprintf(
					__( '<p class="error">%s error: JavaScript and/or CSS files aren\'t loaded. If you\'re using <code>do_shortcode()</code> you need to add the <code>%s</code> filter to your theme first.', 'th-framework' ),
					$this->shortcode_name,
					$this->shortcode_name .'-shortcode-called'
				);
				return $error;
			}

			$default_attributes = apply_filters( $this->shortcode_name . '-default-shortcode-attributes', $this->default_attributes );
			$attributes = shortcode_atts( $default_attributes, $attributes );
			$attributes = apply_filters( $this->shortcode_name . '-shortcode-attributes', $attributes );
			$attributes = $this->clean_attributes( $attributes );

			// Form a cache key from the attributes
			$cache_key = $this->shortcode_name . ':' . md5( serialize( $attributes ) );

			if ( $this->use_cache ) {
				$cache = wp_cache_get( $cache_key );

				if ( false !== $cache ) {
					$cache = apply_filters( $this->shortcode_name . '-shortcode-output', $cache );
					return $cache;
				}
			}

			ob_start();
			do_action( $this->shortcode_name . '-before-shortcode' );
			$this->generate_shortcode_content( $attributes, $content = null );
			do_action( $this->shortcode_name . '-after-shortcode' );
			$output = ob_get_clean();

			if ( $this->use_cache ) {
				wp_cache_add( $cache_key, $output, 24*60*60 );
			}

			$output = apply_filters( $this->shortcode_name . '-shortcode-output', $output );

			return $output;
		}

		public function process_frontend_enqueues() {
			if ( 1 !== did_action( 'wp_enqueue_scripts' ) || !$this->shortcode_called() ) {
				return;
			}

			$this->enqueue_frontend_resources();
		}

		protected function enqueue_frontend_resources() {}

		protected function generate_shortcode_content( $attributes, $content ) {}
	}
}
