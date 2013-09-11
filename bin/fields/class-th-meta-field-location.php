<?php

/**
 * Class adding functionality to add a Google Maps powered
 * location field
 *
 * @todo update error reporting
 *
 * @author Thijs Huijssoon
 */

require_once 'class-th-meta-field.php';
if ( !class_exists( 'TH_Meta_Field_Location' ) ) {

	class TH_Meta_Field_Location extends TH_Meta_Field {

		public function __construct( $namespace, $properties ) {

			$this->handle_defaults(
				array(
					'insert-address-field'  => true,
					'linked-address-fields' => array(),
					'default'               => array(
						'address' => '',
						'lat'     => '',
						'lng'     => '',
						'column'  => '',
						'map'     => array(
							'zoom'    => '10',
							'center'  => array(
								'lat' => '47.592157',
								'lng' => '-122.324524'
							),
							'bounds'  => array(
								'ne' => array(
									'lat' => '',
									'lng' => ''
								),
								'sw' => array(
									'lat' => '',
									'lng' => ''
								),
							),
						),
					)
				)
			);

			parent::__construct( $namespace, $properties );

			$valid_fields = array( 'street_address', 'route', 'intersection', 'political', 'country', 'administrative_area_level_1', 'administrative_area_level_2', 'administrative_area_level_3', 'colloquial_area', 'locality', 'sublocality', 'sublocality_level_1', 'sublocality_level_2', 'sublocality_level_3', 'sublocality_level_4', 'sublocality_level_5', 'neighborhood', 'premise', 'subpremise', 'postal_code', 'natural_feature', 'airport', 'park' );

			foreach ( $this->properties['linked-address-fields'] as $key => $value ) {
				if ( !in_array( $key, $valid_fields ) ) {
					// Remove the field if not a valid Address Component Type
					if ( WP_DEBUG ) {
						trigger_error( sprintf( __( '<strong>%s is not a valid Address Component Types.</strong> To find out which components are valid visit the <a href="https://developers.google.com/maps/documentation/javascript/geocoding#GeocodingAddressTypes">Google Geocoding Service page</a>.', 'text_domain' ), $key ) );
					}
					unset( $this->properties['linked-address-fields'][$key] );
				} else {
					// Change the slug to the field id
					$this->properties['linked-address-fields'][$key] = esc_attr( $this->namespace . '-' . $value );
				}
			}
			if ( $this->properties['insert-address-field'] ) {
				$this->properties['linked-address-fields']['formatted_address'] = esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-address';
			}

			$this->type = 'location';
		}

		/**
		 * Returns markup for input field
		 *
		 * @return string
		 */
		public function render_field( $value, $error = false ) {
			$output = '';

			// Setup attributes
			$lat_attributes = array();
			$lng_attributes = array();
			$zoom_attributes = array();

			// Type
			$lat_attributes[]           = 'type="hidden"';
			$lng_attributes[]           = 'type="hidden"';
			$zoom_attributes[]          = 'type="hidden"';
			$center_lat_attributes[]    = 'type="hidden"';
			$center_lng_attributes[]    = 'type="hidden"';
			$zoom_attributes[]          = 'type="hidden"';
			$column_attributes[]        = 'type="hidden"';
			$bounds_ne_lat_attributes[] = 'type="hidden"';
			$bounds_ne_lng_attributes[] = 'type="hidden"';
			$bounds_sw_lat_attributes[] = 'type="hidden"';
			$bounds_sw_lng_attributes[] = 'type="hidden"';

			// Name
			$lat_attributes[]           = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[lat]"';
			$lat_attributes[]           = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-lat"';
			$lng_attributes[]           = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[lng]"';
			$lng_attributes[]           = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-lng"';
			$zoom_attributes[]          = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[map][zoom]"';
			$zoom_attributes[]          = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-map-zoom"';
			$center_lat_attributes[]    = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[map][center][lat]"';
			$center_lat_attributes[]    = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-map-center-lat"';
			$center_lng_attributes[]    = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[map][center][lng]"';
			$center_lng_attributes[]    = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-map-center-lng"';
			$column_attributes[]        = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[column]"';
			$column_attributes[]        = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-column"';
			$bounds_ne_lat_attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[map][bounds][ne][lat]"';
			$bounds_ne_lat_attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-map-bounds-sw-lat"';
			$bounds_ne_lng_attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[map][bounds][ne][lng]"';
			$bounds_ne_lng_attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-map-bounds-sw-lng"';
			$bounds_sw_lat_attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[map][bounds][sw][lat]"';
			$bounds_sw_lat_attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-map-bounds-sw-lat"';
			$bounds_sw_lng_attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[map][bounds][sw][lng]"';
			$bounds_sw_lng_attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-map-bounds-sw-lng"';

			// Value
			if ( ( empty( $value )|| !is_array( $value ) ) && isset( $this->properties['default'] ) ) {
				$value = $this->properties['default'];
			}

			$lat_attributes[]           = 'value="' . esc_attr( $value['lat'] ) . '"';
			$lng_attributes[]           = 'value="' . esc_attr( $value['lng'] ) . '"';
			$column_attributes[]        = 'value="' . esc_attr( $value['column'] ) . '"';
			$zoom_attributes[]          = 'value="' . esc_attr( $value['map']['zoom'] ) . '"';
			$center_lat_attributes[]    = 'value="' . esc_attr( $value['map']['center']['lat'] ) . '"';
			$center_lng_attributes[]    = 'value="' . esc_attr( $value['map']['center']['lng'] ) . '"';
			$bounds_ne_lat_attributes[] = 'value="' . esc_attr( $value['map']['bounds']['ne']['lat'] ) . '"';
			$bounds_ne_lng_attributes[] = 'value="' . esc_attr( $value['map']['bounds']['ne']['lng'] ) . '"';
			$bounds_sw_lat_attributes[] = 'value="' . esc_attr( $value['map']['bounds']['sw']['lat'] ) . '"';
			$bounds_sw_lng_attributes[] = 'value="' . esc_attr( $value['map']['bounds']['sw']['lng'] ) . '"';

			// Class
			$lat_attributes[]           = 'class="th-lat"';
			$lng_attributes[]           = 'class="th-lng"';
			$zoom_attributes[]          = 'class="th-zoom"';
			$column_attributes[]        = 'class="th-column"';
			$center_lat_attributes[]    = 'class="th-center-lat"';
			$center_lng_attributes[]    = 'class="th-center-lng"';
			$bounds_ne_lat_attributes[] = 'class="th-bounds-ne-lat"';
			$bounds_ne_lng_attributes[] = 'class="th-bounds-ne-lng"';
			$bounds_sw_lat_attributes[] = 'class="th-bounds-sw-lat"';
			$bounds_sw_lng_attributes[] = 'class="th-bounds-sw-lng"';

			// Build input field
			$output .= '<input ' . implode( ' ', $lat_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $lng_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $column_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $zoom_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $center_lat_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $center_lng_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $bounds_ne_lat_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $bounds_ne_lng_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $bounds_sw_lat_attributes ) . '/>';
			$output .= '<input ' . implode( ' ', $bounds_sw_lng_attributes ) . '/>';

			if ( $this->properties['insert-address-field'] ) {
				$attributes   = array();
				$attributes[] = 'type="text"';
				$attributes[] = 'name="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '[address]"';
				$attributes[] = 'id="' . esc_attr( $this->namespace . '-' . $this->properties['slug'] ) . '-address"';
				$attributes[] = 'value="' . esc_attr( $value['address'] ) . '"';

				if ( !empty( $this->properties['class'] ) ) {
					$attributes[] = 'class="th-addr text regular-text ' . sanitize_html_class( $this->properties['class'] ) . '"';
				}
				else {
					$attributes[] = 'class="th-addr text regular-text"';
				}

				// Disabled
				if ( isset( $this->properties['disabled'] ) && $this->properties['disabled'] ) {
					$attributes[] = 'disabled="disabled"';
				}

				// Read only
				if ( isset( $this->properties['read_only'] ) && $this->properties['read_only'] ) {
					$attributes[] = 'readonly="readonly"';
				}

				// Placeholder
				if ( isset( $this->properties['placeholder'] ) ) {
					$attributes[] = 'placeholder="' . esc_attr( $this->properties['placeholder'] ) . '"';
				}

				$output .= '<input ' . implode( ' ', $attributes ) . '/>';
			}

			// Error
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( !empty( $error ) ) {
				$output .= '<p class="errormessage">' . wp_kses_post( $error ) . '</p>';
			}

			$json    = json_encode( $this->properties['linked-address-fields'] );
			// $output .= '<div class="th-map-camvas" style="width:300px; height:200px;" data-fields=\'' . esc_attr( $json ) . '\'></div>';
			$output .= '<div class="th-map-camvas-container"><div class="th-map-camvas" data-fields=\'' . esc_attr( $json ) . '\'></div></div>';

			// Description
			// TODO: Is there a better way to go except by using wp_kses_post?
			if ( isset( $this->properties['description'] ) ) {
				$output .= '<p class="description">' . wp_kses_post( $this->properties['description'] ) . '</p>';
			}

			return apply_filters( 'th_field_' . $this->type . '_field', $output, $value, $error, $this->properties );
		}

		/**
		 *
		 *
		 * @todo   do geocoding serverside if no lat lng found.
		 * @param [type]  $errors [description]
		 * @return [type]         [description]
		 */
		public function validate( &$errors ) {
			$name = $this->namespace . '-' . $this->get_slug();

			// If the field isn't set (checkbox), use empty string (default for get_post_meta()).
			$value = isset( $_POST[$name] ) ? $_POST[$name] : '';

			$error_messages = apply_filters( 'th_field_' . $this->type . '_error_messages',
				array(
					'required'         => sprintf( __( '%s cannot be empty', 'text_domain' ), esc_html( $this->properties['label'] ) ),
					'geocode'          => __( 'There is a technical failure. Please contact your administrator with message: Geocode error.', 'text_domain' ),
				),
				$this->properties
			);

			if ( $this->is_required() && ( !is_array( $value ) || empty( $value ) ) ) {
				$errors[$this->get_slug()] = array(
					'slug'        => $this->get_slug(),
					'title'       => esc_html( $this->properties['label'] ),
					'message'     => $error_messages['required']
				);
				return '';
			}

			if (
				(
					$this->properties['insert-address-field'] &&
					empty( $value['address'] )
				) ||
				empty( $value['lat'] ) ||
				empty( $value['lng'] ) ||
				empty( $value['column'] ) ||
				empty( $value['map'] ) ||
				!is_array( $value['map'] ) ||
				empty( $value['map']['zoom'] ) ||
				empty( $value['map']['center']['lat'] ) ||
				empty( $value['map']['center']['lng'] ) ||
				empty( $value['map']['bounds']['ne']['lat'] ) ||
				empty( $value['map']['bounds']['ne']['lng'] ) ||
				empty( $value['map']['bounds']['sw']['lat'] ) ||
				empty( $value['map']['bounds']['sw']['lng'] )
			) {
				$errors[$this->get_slug()] = array(
					'slug'        => $this->get_slug(),
					'title'       => esc_html( $this->properties['label'] ),
					'message'     => $error_messages['geocode']
				);
				return '';
			}

			$value['lat'] = floatval( $value['lat'] );
			$value['lng'] = floatval( $value['lng'] );
			$value['column'] = wp_kses_post( $value['column'] );
			$value['map']['zoom'] = floatval( $value['map']['zoom'] );
			$value['map']['center']['lat'] = floatval( $value['map']['center']['lat'] );
			$value['map']['center']['lng'] = floatval( $value['map']['center']['lng'] );
			$value['map']['bounds']['ne']['lat'] = floatval( $value['map']['bounds']['ne']['lat'] );
			$value['map']['bounds']['ne']['lng'] = floatval( $value['map']['bounds']['ne']['lng'] );
			$value['map']['bounds']['sw']['lat'] = floatval( $value['map']['bounds']['sw']['lat'] );
			$value['map']['bounds']['sw']['lng'] = floatval( $value['map']['bounds']['sw']['lng'] );

			return $value;
		}

		public function get_column_value($value) {
			return $value['column'];
		}

		public static function enqueue_js() {
			$prot = is_ssl() ? 'https' : 'http';
			wp_enqueue_script( 'th-google-maps', $prot . '://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false', array( ), '3', true );
			wp_enqueue_script( 'th-meta-field-location', plugins_url( 'js/th-meta-field-location.js' , dirname( __FILE__ ) ), array( 'th-google-maps', 'jquery' ), '1.0.0', true );
		}

	}
}
