<?php
/**
 * WordPress Custom Post Type Utils
 *
 * Contains the TH_Util class. Requires WordPress version 3.5 or greater.
 *
 * @version   0.1.0
 * @package   TH CPT
 * @author    Thijs Huijssoon <thuijssoon@googlemail.com>
 * @license   GPL 2.0+ - http://www.gnu.org/licenses/gpl.txt
 * @link      https://github.com/thuijssoon/
 * @copyright Copyright (c) 2013 - Thijs Huijssoon
 */

if ( !class_exists( 'TH_Util' ) ) {

	/*
	 * WordPress Custom Post Type Class
	 *
	 * A class that handles the registration of WordPress custom post types and
	 * enables you to deeply integrate into the wordpress admin area.
	 *
	 * @package TH CPT
	 */
	class TH_Util {

		private function __construct() {}

        /**
         * gmt_to_local function
         *
         * Returns the UNIX timestamp adjusted to the local timezone.
         *
         * @param int     $gmt_timestamp
         *
         * @return int
         * 
         * @since 0.1.0
         */
        public static function gmt_to_local( $gmt_timestamp ) {
            if ( !$timezone_string = get_option( 'timezone_string' ) ) {
                $gmt_offset = get_option( 'gmt_offset' );
                return $gmt_timestamp + ( $gmt_offset * 3600 );
            }

            @date_default_timezone_set( $timezone_string );
            $timezone_object = timezone_open( $timezone_string );
            $datetime_object = date_create( '@' . $gmt_timestamp );

            if ( false === $timezone_object || false === $datetime_object ) {
                $gmt_offset = get_option( 'gmt_offset' );
                return $gmt_timestamp + ( $gmt_offset * 3600 );
            }

            $return  = $gmt_timestamp + timezone_offset_get( $timezone_object, $datetime_object );
            @date_default_timezone_set( 'UTC' );

            return $return;
        }

        /**
         * local_to_gmt function
         *
         * Returns the UNIX timestamp adjusted from the local timezone to GMT.
         *
         * @param int     $local_timestamp
         *
         * @return int
         * 
         * @since 0.1.0
         */
        public static function local_to_gmt( $local_timestamp ) {
            if ( !$timezone_string = get_option( 'timezone_string' ) ) {
                $gmt_offset = get_option( 'gmt_offset' );
                return $local_timestamp - ( $gmt_offset * 3600 );
            }

            @date_default_timezone_set( $timezone_string );
            $timezone_object = timezone_open( $timezone_string );
            $datetime_object = date_create( '@' . $local_timestamp );

            if ( false === $timezone_object || false === $datetime_object ) {
                $gmt_offset = get_option( 'gmt_offset' );
                return $local_timestamp - ( $gmt_offset * 3600 );
            }

            $return  = $local_timestamp - timezone_offset_get( $timezone_object, $datetime_object );
            @date_default_timezone_set( 'UTC' );

            return $return;
        }

	}

}
