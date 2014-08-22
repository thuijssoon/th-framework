<?php
/**
 *
 *
 * @package     TH Framework
 * @author      Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license     GPL-2.0+
 * @link        https://github.com/thuijssoon/th-framework
 * @copyright   2013 Thijs Huijssoon
 *
 *
 * @todo        Transform this file into a class
 *
 *
 * @wordpress-plugin
 * Plugin Name: TH Framework
 * Plugin URI:  https://github.com/thuijssoon/th-framework
 * Description: Utility plugin to enable Custom Post Type, Custom Taxonomy & Custom Meta classes.
 * Version:     0.1.3 (22/08/2014)
 * Author:      Thijs Huijssoon <thuijssoon@googlemail.com>
 * Author URI:  https://github.com/thuijssoon/
 * Text Domain: th-framework
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /lang
 */

if ( $_SERVER['SCRIPT_FILENAME'] == __FILE__ )
	die();

load_plugin_textdomain( 'th-framework', false, basename( dirname( __FILE__ ) ) . '/lang' );

/**
 * Checks if the system requirements are met, which are:
 * - PHP Version 5.2
 * - WordPress Version 3.5
 *
 * @author Thijs Huijssoon <thuijssoon@googlemail.com>
 * @return bool True if system requirements are met, false if not
 */
function th_framework_requirements_met() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, '5.2', '<' ) )
		return false;

	if ( version_compare( $wp_version, '3.5', '<' ) )
		return false;

	return true;
}

/**
 * Prints an error that the system requirements weren't met.
 *
 * @author Thijs Huijssoon <thuijssoon@googlemail.com>
 */
function th_framework_requirements_not_met() {
	global $wp_version;

	$message = sprintf(
		__( '%s <strong>requires PHP %s</strong> and <strong>WordPress %s</strong> in order to work. You will also need to install the Taxonomy Metadata plugin. You\'re running PHP %s and WordPress %s. You\'ll need to upgrade in order to use this plugin. If you\'re not sure how to <a href="http://codex.wordpress.org/Switching_to_PHP5">upgrade to PHP 5</a> you can ask your hosting company for assistance, and if you need help upgrading WordPress you can refer to <a href="http://codex.wordpress.org/Upgrading_WordPress">the Codex</a>.', 'th-framework' ),
		'TH Framework',
		'5.2',
		'3.5',
		PHP_VERSION,
		esc_html( $wp_version )
	);

	echo '<div id="th_framework_message" class="error">' . $message . '</div>';
}

function th_framework_load() {
	// Check requirements and instantiate
	if ( th_framework_requirements_met() ) {
		// Load files:
		require_once 'bin/class-th-cpt.php';
		require_once 'bin/class-th-ct.php';
		require_once 'bin/class-th-meta.php';
		require_once 'bin/class-th-post-meta.php';
		require_once 'bin/class-th-term-meta.php';
		require_once 'bin/class-wptp-error.php';
		require_once 'bin/class-th-util.php';
		require_once 'bin/class-th-settings-api.php';
		require_once 'bin/class-th-shortcode.php';

		// Do action:
		do_action( 'th_framework_loaded' );
	}
	else {
		add_action( 'admin_notices', 'th_framework_requirements_not_met' );
	}
}

if ( !function_exists( '_log' ) ) {
	function _log( $var, $message = '' ) {
		if ( WP_DEBUG === true ) {
			if ( ! empty( $message ) ) {
				error_log( $message );
			}
			if ( is_array( $var ) || is_object( $var ) ) {
				error_log( print_r( $var, true ) );
			} else {
				error_log( $var );
			}
		}
	}
}

// Need to load late to enable other plugins to load first and attach hooks
add_action( 'plugins_loaded', 'th_framework_load', 1000 );
