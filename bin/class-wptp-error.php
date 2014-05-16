<?php

if ( !class_exists( 'WPTP_Error' ) ) {

	class WPTP_Error {

		private static $instance       = null;
		private static $suppress_hooks = false;
		private $post_types            = array();
		private $errors                = null;
		private $saved_errors          = null;
		private $error_messages        = null;
		private $taxonomies            = array();

		private function __construct() {
			add_action( 'init', array( $this, 'acb_register_post_status' ) );

			if ( is_admin() ) {
				// General actions
				add_filter( 'admin_body_class', array( $this, 'fcb_admin_body_class_add_errors' ), 11 );
				add_action( 'admin_notices', array( $this, 'acb_admin_notices_show_error_messages' ) );

				// Post Type actions
				add_action( 'save_post', array( $this, 'acb_save_post' ), 500, 2 );

				add_filter( 'display_post_states', array( $this, 'fcb_display_post_states' ) );
				add_action( 'admin_footer-post.php', array( $this, 'acb_admin_footer_extend_submitdiv_post_status' ) );
				add_action( 'admin_footer-post-new.php', array( $this, 'acb_admin_footer_extend_submitdiv_post_status' ) );

				add_action( 'edit_form_after_title', array( $this, 'acb_edit_form_after_title_show_error' ) );
				add_action( 'edit_form_after_editor', array( $this, 'acb_edit_form_after_editor_show_error' ) );
				add_action( 'add_meta_boxes', array( $this, 'acb_add_meta_boxes_handle_excerpt' ) );
				add_filter( 'admin_post_thumbnail_html', array( $this, 'fcb_admin_post_thumbnail_html_show_errors' ) );

				add_action( 'admin_head-edit.php', array( $this, 'acb_admin_head_add_css' ) );
				add_action( 'admin_head-post.php', array( $this, 'acb_admin_head_add_post_screen_css' ) );

				// Taxonomy actions

			}
		}

		/**
		 * Return an instance of this class.
		 *
		 * @since     0.1.0
		 *
		 * @return    object    A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Add an error to the error object.
		 *
		 * @param array   $error The information an error. Keys are: object (post, taxonomy or user), object_type (post_type, taxonomy_type or user), slug (the field slug), title (the human readable name of the field) & message (the error message).
		 * @return  $this          This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function add_error( array $error ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			// Required fields
			$required_fields = array(
				// 'object_type',
				'slug',
				'title',
				'message'
			);
			foreach ( $required_fields as $field ) {
				if ( !isset( $error[$field] ) ) {
					if ( WP_DEBUG ) {
						trigger_error( sprintf( __( '<strong>%s</strong> must be set.', 'text_domain' ), $field ) );
					}
					return $this;
				}
			}

			$default_error_args = array(
				'slug'        => '',        // the field slug
				'title'       => '',        // the title of the field, e.g.: 'Age'
				'message'     => '',        // the error message
			);

			$error = wp_parse_args(
				$error,
				apply_filters( 'wptp_error_default_error_args', $default_error_args )
			);

			// If the errors haven't been set, set it now.
			if ( null == $this->errors ) {
				$this->errors = array();
			}

			// Only one error per slug.
			if ( !in_array( $error['slug'], array_keys( $this->errors ) ) ) {
				$this->errors[$error['slug']] = $error;
			}

			return $this;
		}

		/**
		 * Remove an error from the error object.
		 *
		 * @param array|string $error The error to remove. If an array is passed it must have a slug property.
		 * @return  $this                 This object to enable function chaining
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function remove_error( $error ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			if ( is_array( $error ) && empty( $error['slug'] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( __( '<strong>slug</strong> must be set.', 'text_domain' ), $field );
				}
				return $this;
			} elseif ( is_array( $error ) ) {
				$error = $error['slug'];
			}

			if ( !isset( $this->errors[$error] ) ) {
				if ( WP_DEBUG ) {
					trigger_error( sprintf( __( 'Error with slug <strong>%s</strong> not found.', 'text_domain' ), $error ) );
				}
				return $this;
			}

			unset( $this->errors[$error] );

			return $this;
		}

		/**
		 * Get the saved errors.
		 *
		 * @return  array  The saved errors.
		 * @since   0.1.0
		 * @author  Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function get_saved_errors( $object_id = '', $object_type = '' ) {
			// Admin only functionality
			if ( !is_admin() ) {
				return $this;
			}

			// If the errors haven't been set, set it now.
			if ( null == $this->saved_errors ) {
				$object_type = empty( $object_type ) ? $this->in_context() : $object_type;
				switch ( $object_type ) {
				case 'post':
					if ( !empty( $object_id ) ) {
						$post   = get_post( $object_id );
					} else {
						$post   = get_post();
					}
					$errors = get_post_meta( $post->ID, '_th_errors', true );
					break;

				case 'taxonomy':
					if ( isset( $GLOBALS['tag'] ) ) {
						$tag    = $GLOBALS['tag'];
						$errors = get_term_meta( $tag->term_id, '_th_errors', true );
					} else {
						$errors = '';
					}
					break;

				case 'user':
					$errors = get_user_meta( $object_id, '_th_errors', true );
					break;

				default:
					$errors = '';
					break;
				}

				if ( !is_array( $errors ) ) {
					$this->saved_errors = array();
				} else {
					$this->saved_errors = $errors;
				}
			}

			return $this->saved_errors;
		}


		protected function in_context() {
			if ( !is_admin() ) {
				return false;
			}
			return apply_filters( 'wptp-error-in-context', false );
		}

		public function add_post_type_support( $post_types, $required_fields = array() ) {
			if ( !is_admin() ) {
				return;
			}

			if ( !is_array( $post_types ) ) {
				$post_types = explode( ',', $post_types );
			}
			$post_types = array_map( 'trim', $post_types );

			if ( !is_array( $required_fields ) ) {
				$required_fields = explode( ',', $required_fields );
			}
			$required_fields = array_map( 'trim', $required_fields );

			$defaults  = array(
				'title'     => 'title',
				'editor'    => 'content',
				'thumbnail' => 'thumbnail',
				'excerpt'   => 'excerpt'
			);

			$required_fields = $this->parse_required_fields($defaults, $required_fields);

			if ( 0 === count( $this->post_types ) ) {
				add_filter( 'wptp-error-in-context', array( $this, 'in_post_type_context' ) );
			}

			foreach ( $post_types as $post_type ) {
				$required_fields_prev         = isset( $this->post_types[$post_type] ) ? $this->post_types[$post_type] : array();
				$this->post_types[$post_type] = array_unique( array_merge( $required_fields_prev, $required_fields ) );
			}
		}

		public function in_post_type_context( $context ) {
			global $post, $pagenow;

			if (
				isset( $post ) &&
				in_array( $post->post_type, array_keys( $this->post_types ) ) &&
				'edit.php' !== $pagenow && // we don't want to show on the all post_type screen
				!isset( $_GET['revision'] )  // we don't want to show on the revision screen
			) {
				$context = 'post';
			}

			return $context;
		}

		public function add_taxonomy_support( $taxonomies, $required_fields = array() ) {
			if ( !is_admin() ) {
				return;
			}

			if ( !is_array( $taxonomies ) ) {
				$taxonomies = explode( ',', $taxonomies );
			}
			$taxonomies = array_map( 'trim', $taxonomies );

			if ( !is_array( $required_fields ) ) {
				$required_fields = explode( ',', $required_fields );
			}
			$required_fields = array_map( 'trim', $required_fields );

			$defaults  = array(
				'description' => 'title',
			);
			$required_fields = $this->parse_required_fields($defaults, $required_fields);

			if ( 0 === count( $this->taxonomies ) ) {
				add_filter( 'wptp-error-in-context', array( $this, 'in_taxonomy_context' ) );
				add_action( 'init', array( $this, 'acb_init_tax_hooks' ) );
			}

			foreach ( $taxonomies as $taxonomy ) {
				$required_fields_prev        = isset( $this->taxonomies[$taxonomy] ) ? $this->taxonomies[$taxonomy] : array();
				$this->taxonomies[$taxonomy] = array_unique( array_merge( $required_fields_prev, $required_fields ) );
			}
		}

		public function in_taxonomy_context( $context ) {
			global $pagenow;

			if (
				'edit-tags.php' === $pagenow &&
				isset( $_REQUEST['action'] ) &&
				( 'editedtag' === $_REQUEST['action'] || 'edit' === $_REQUEST['action'] ) &&
				isset( $_REQUEST['taxonomy'] ) &&
				in_array( $_REQUEST['taxonomy'], array_keys( $this->taxonomies ) )
			) {
				$context = 'taxonomy';
			}

			return $context;
		}

		/*------------------------------------------------*
		   Action Callbacks
		 *------------------------------------------------*/

		public function acb_save_post( $post_id ) {
			// Do nothing if hooks are suppressed by other plugin
			if( self::$suppress_hooks ) {
				return $post_id;
			}

			$ignored_actions = array( 'trash', 'untrash', 'restore' );

			// Filter out actions on which to do nothing
			if ( isset( $_GET[ 'action' ] ) && in_array( $_GET[ 'action' ], $ignored_actions ) ) {
				return $post_id;
			}

			$post = get_post( $post_id );

			if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || $post->post_status == 'auto-draft' ) {
				return $post_id;
			}

			// Check that we are in the right context
			if ( 'post' !== $this->in_context() ) {
				return $post_id;
			}

			// Check user permissions
			$post_type_object = get_post_type_object( $post->post_type );
			if ( !current_user_can( $post_type_object->cap->edit_posts ) ) {
				return $post_id;
			}

			if ( !isset( $this->post_types[$post->post_type] ) ) {
				return $post_id;
			}

			// We're ok, lets do something

			// Loop through the fields and check them
			$required_fields = $this->post_types[$post->post_type];

			$this->init_error_messages( $post->post_type );

			foreach ( array_keys( $required_fields ) as $field ) {
				switch ( $field ) {
				case 'title':
					if ( empty( $post->post_title ) ) {
						$this->add_error(
							$this->error_messages['title']
						);
					}
					break;
				case 'editor':
					if ( empty( $post->post_content ) ) {
						$this->add_error(
							$this->error_messages['editor']
						);
					}
					break;
				case 'thumbnail':
					if ( !has_post_thumbnail( $post->ID ) ) {
						$this->add_error(
							$this->error_messages['thumbnail']
						);
					}
					break;
				case 'excerpt':
					if ( empty( $post->post_excerpt ) ) {
						$this->add_error(
							$this->error_messages['excerpt']
						);
					}
					break;
				}
			}

			if ( count( $this->errors ) ) {

				update_post_meta( $post->ID, '_th_errors', $this->errors );

				// Supress "post published" message upon validation failure
				add_filter( 'redirect_post_location', array( $this, 'acb_supress_default_message' ), 11 );

				// Remove action hook and set post status to draft
				remove_action( 'save_post', array( $this, 'acb_save_post' ), 500, 2 );
				wp_update_post(
					array(
						'ID'          => $post->ID,
						'post_status' => 'th_error',
					)
				);
				add_action( 'save_post', array( $this, 'acb_save_post' ), 500, 2 );

			} else {

				delete_post_meta( $post->ID, '_th_errors' );

			}
		}

		/**
		 * Supresses the standard 'post published' message in case a required
		 * field is missing
		 */
		public function acb_supress_default_message( $location ) {
			$errors = $this->get_saved_errors();

			if ( is_array( $errors ) && count( $errors ) > 0 ) {
				$location = remove_query_arg( 'message', $location );
			}

			return $location;
		}

		/**
		 * Prints message if there are errors.
		 *
		 * @todo     update message.
		 *
		 * @wp-hook  admin_notices
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_admin_notices_show_error_messages() {
			if ( !$this->in_context() ) {
				return;
			}

			$errors = $this->get_saved_errors();

			if ( is_array( $errors ) && count( $errors ) > 0 ) {
				$errors = array_values( $errors );
				echo '<div class="error"><p><strong>';
				if ( count( $errors ) == 1 ) {
					echo sprintf( __( 'The following field contains errors: %s', 'text_domain' ),  $errors[0]['title']  );
				} else {
					$error_fields = array();
					foreach ( $errors as $e ) {
						$error_fields[] = $e['title'];
					}

					$errors = implode( ', ', $error_fields );
					$pos = strrpos( $errors, ',' );
					if ( $pos !== false ) {
						$errors = substr_replace( $errors, ' &', $pos, 1 );
					}

					echo sprintf( __( 'The following fields contain errors: %s', 'text_domain' ), $errors );
				}
				echo '</strong></p></div>';
			}
		}

		public function acb_register_post_status() {
			$label_def  = array(
				'label'       => '',
				'label_count' => '',
			);

			$label_args = wp_parse_args(
				apply_filters(
					'wptp_error_post_status_labels',
					array(
						'label'       => _x( 'Incomplete', 'Status General Name', 'text_domain' ),
						'label_count' => _n_noop( 'Incomplete <span class="count">(%s)</span>', 'Incomplete <span class="count">(%s)</span>', 'text_domain' ),
					)
				),
				$label_def
			);

			$args = array(
				'public'                    => true,
				'protected'                 => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'exclude_from_search'       => true,
			);

			$args = array_merge( $args, $label_args );

			register_post_status( 'th_error', $args );
		}

		/**
		 * Adds jQuery to the bottom of the page to
		 * replace the post status.
		 *
		 * @wp-hook  admin_footer
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_admin_footer_extend_submitdiv_post_status() {
			global $post, $wp_post_statuses;

			if (
				'post'     === $this->in_context() &&
				'th_error' === $post->post_status
			) {
?>
		<script type="text/javascript">
			jQuery( document ).ready( function($) {
				$( '#post-status-display' ).html( '<?php echo $wp_post_statuses['th_error']->label; ?>' );
			} );
		</script>
<?php
			}
		}

		/**
		 * Add the th_error state to the post states array.
		 *
		 * @wp-hook  display_post_states
		 * @param array   $post_states The post states array
		 * @return                        The new post states array including the incomplete state
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_display_post_states( $post_states ) {
			global $post;

			if ( !in_array( $post->post_type, array_keys( $this->post_types ) ) ) {
				return $post_states;
			}

			if ( isset( $_REQUEST['post_status'] ) ) {
				$post_status = $_REQUEST['post_status'];
			} else {
				$post_status = '';
			}
			if ( 'th_error' === $post->post_status && 'th_error' !== $post_status ) {
				$status_obj = get_post_status_object( 'th_error' );
				$post_states['th_error'] = $status_obj->label;
			}
			return $post_states;
		}

		public function acb_admin_head_add_css() {
			echo '<style type="text/css">tr.status-th_error {background-color: #ffebe8 !important;} tr.status-th_error span.post-state {color: #c00 !important;}</style>';
		}

		public function acb_admin_head_add_post_screen_css() {
			if ( count( $this->get_saved_errors() ) ) {
				echo '<style type="text/css">';
				echo '    #post-status-display, p.wptp_errormessage {color: #c00 !important;}';
				echo '    .error-title #titlediv input {border-color: #c00 !important;}';
				echo '    .error-editor #wp-content-editor-container, .error-editor #wp-content-editor-tools>a {border-top-color:#c00 !important; border-left-color:#c00 !important; border-right-color:#c00 !important;}';
				echo '    .error-editor #post-status-info {border-bottom-color:#c00 !important; border-left-color:#c00 !important; border-right-color:#c00 !important;}';
				echo '    .error-editor .tmce-active #content-html, .error-editor .html-active #content-tmce {border-bottom-color:#c00 !important;}';
				echo '    .error-excerpt	 #excerpt {border-color: #c00 !important;}';
				echo '</style>';
			}
		}

		/**
		 * Show an error message after the title div if there is
		 * an error in the title.
		 *
		 * @wp-hook  edit_form_after_title
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_edit_form_after_title_show_error() {
			$errors = $this->get_saved_errors();
			if ( isset( $errors['title'] ) ) {
				echo '<p class="wptp_errormessage">' . $errors['title']['message'] . '</p>';
			}
		}

		/**
		 * Show an error message after the title div if there is
		 * an error in the title.
		 *
		 * @wp-hook  edit_form_after_title
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_edit_form_after_editor_show_error() {
			$errors = $this->get_saved_errors();
			if ( isset( $errors['editor'] ) ) {
				echo '<p class="wptp_errormessage">' . $errors['editor']['message'] . '</p>';
			}
		}

		/**
		 * Remove and re-add the post excerpt meta box
		 * to enable error reporting.
		 *
		 * @wp-hook  add_meta_boxes
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_add_meta_boxes_handle_excerpt() {
			$screen = get_current_screen();

			if(post_type_supports( $screen->post_type, 'excerpt' )) {
				remove_meta_box( 'postexcerpt', $screen->post_type, 'normal', 'core' );
				add_meta_box( 'th_cpt_postexcerpt', __( 'Excerpt' ), array( $this, 'acb_post_excerpt_meta_box' ), $screen->post_type, 'normal', 'core' );
			}
		}

		/**
		 * Callback for the post_excerpt meta box.
		 *
		 * @param object  $post The current post object
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_post_excerpt_meta_box( $post ) {
			$errors = $this->get_saved_errors();
			post_excerpt_meta_box( $post );
			if ( isset( $errors['excerpt'] ) ) {
				echo '<p class="wptp_errormessage">' . $errors['excerpt']['message'] . '</p>';
			}
		}

		/**
		 * Filter the featured image meta box to show errors.
		 *
		 * @wp-hook  admin_post_thumbnail_html
		 * @param string  $content The featured image meta box content
		 * @return   string          The filtered content
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_admin_post_thumbnail_html_show_errors( $content ) {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$errors    = array();
				$post      = get_post( absint( $_REQUEST['post_id'] ) );
				$post_type = $post->post_type;
				if (
					in_array( $post_type, array_keys( $this->post_types ) ) &&
					in_array( 'thumbnail', array_keys( $this->post_types[$post_type] ) ) &&
					false === strpos( $content, '<img' )
				) {
					$this->init_error_messages( $post_type );
					$errors['thumbnail'] = $this->error_messages['thumbnail'];
				}
			} else {
				$errors = $this->get_saved_errors();
			}

			if ( isset( $errors['thumbnail'] ) ) {
				$error_msg = '<p class="hide-if-no-js"><p class="wptp_errormessage">' . $errors['thumbnail']['message'] . '</p>';
				$content = str_replace( __( '<p class="hide-if-no-js">' ), $error_msg, $content );
			}

			return $content;
		}

		/**
		 * Add error classes to the admin body class.
		 *
		 * @wp-hook  display_post_states
		 * @param string  $classes Current admin body classes
		 * @return   string            New admin body classes including error classes
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function fcb_admin_body_class_add_errors( $classes ) {
			if ( $this->in_context() ) {
				$errors = $this->get_saved_errors();
				if ( is_array( $errors ) ) {
					$class_array = explode( ' ', $classes );
					$errors = array_keys( $errors );
					foreach ( $errors as $error ) {
						$class_array[] = 'error-' . $error;
					}
					$classes = implode( ' ', array_map( 'sanitize_html_class', array_unique( $class_array ) ) );
				}
			}
			return $classes;
		}

		private function init_error_messages( $post_type ) {
			// Loop through the fields and check them
			$required_fields = $this->post_types[$post_type];
			$post_type_object = get_post_type_object( $post_type );

			$messages = wp_parse_args(
				$required_fields,
				array(
					'title'     => 'title',
					'editor'    => 'content',
					'thumbnail' => 'thumbnail',
					'excerpt'   => 'excerpt'
				)
			);

			$defaults  = array(
				'title'     => 'title',
				'editor'    => 'content',
				'thumbnail' => 'thumbnail',
				'excerpt'   => 'excerpt'
			);

			$this->error_messages = apply_filters(
				'wptp_error_messages',
				array(
					'title' => array(
						'slug'        => 'title',
						'title'       => $messages['title'],
						'message'     => sprintf( __( '%s cannot be empty', 'text_domain' ), $messages['title'] )
					),
					'editor' =>  array(
						'slug'        => 'editor',
						'title'       => $messages['editor'],
						'message'     => sprintf( __( '%s cannot be empty', 'text_domain' ), $messages['editor'] )
					),
					'thumbnail' =>  array(
						'slug'        => 'thumbnail',
						'title'       => $messages['thumbnail'],
						'message'     => sprintf( __( '%1$s must have a %2$s', 'text_domain' ), $post_type_object->labels->singular_name, $messages['thumbnail'] )
					),
					'excerpt' => array(
						'slug'        => 'excerpt',
						'title'       => $messages['excerpt'],
						'message'     => sprintf( __( '%s cannot be empty', 'text_domain' ), $messages['excerpt'] )
					)
				)
			);
		}

		/**
		 * Add hooks based on the context.
		 *
		 * @wp-hook  wp
		 * @return   void
		 * @since    0.1.0
		 * @author   Thijs Huijssoon <thuijssoon@googlemail.com>
		 */
		public function acb_init_tax_hooks() {
			foreach ( array_keys( $this->taxonomies ) as $taxonomy ) {
				add_action( 'created_' . $taxonomy, array( $this, 'acb_created_or_edited_taxonomy_save_errors' ) );
				add_action( 'edited_' . $taxonomy, array( $this, 'acb_created_or_edited_taxonomy_save_errors' ) );
			}
		}

		public function acb_created_or_edited_taxonomy_save_errors( $term_id ) {
			// Do nothing if hooks are suppressed by other plugin
			if( self::$suppress_hooks ) {
				return $term_id;
			}

			$screen  = get_current_screen();
			$tax     = '';
			if(!isset($screen)) {
				$tax = $_REQUEST['taxonomy'];
			} else {
				$tax = get_current_screen()->taxonomy;
			}
			$tax_obj = get_taxonomy( $tax );
			// Check precondition: capability
			if ( !current_user_can( $tax_obj->cap->edit_terms ) )
				return $term_id;

			if ( count( $this->errors ) ) {
				update_term_meta( $term_id, '_th_errors', $this->errors );
			} else {
				delete_term_meta( $term_id, '_th_errors' );
			}
			// Supress "post published" message upon validation failure
			add_filter( 'wp_redirect', array( $this, 'acb_wp_redirect_back_to_edit' ), 11, 2 );
		}

		public function acb_wp_redirect_back_to_edit( $location, $status ) {
			$errors = $this->get_saved_errors();
			$url = parse_url( sanitize_text_field( $_REQUEST['_wp_original_http_referer'] ) );
			parse_str( $url['query'], $post_type );
			$post_type = isset($post_type['post_type']) ? $post_type['post_type'] : 'post';

			// Need to redirect either way as wp uses the referer, which may have
			// been modified by a previous redirect when there are errors.
			if ( empty( $errors ) ) {
				$url = admin_url( 'edit-tags.php?taxonomy=' . get_current_screen()->taxonomy . '&post_type=' . $post_type );
			} else {
				$term = $GLOBALS['tag'];
				$term_id = $term->term_id;
				$url = admin_url( 'edit-tags.php?action=edit&taxonomy=' . get_current_screen()->taxonomy . '&tag_ID=' . $term_id . '&post_type=' . $post_type );
			}
			return $url;
		}

		/**
		 * Re-assign the post status of posts with status 'th_error'
		 * to 'draft'. Usefull on uninstall.
		 *
		 * @since     0.1.0
		 */
		public static function re_assign_status( $post_type ) {
			global $wpdb;

			$wpdb->update(
				$wpdb->posts,
				array( // data
					'post_status' => 'draft',
				),
				array( // where
					'post_status' => 'th_error',
					'post_type'   => $post_type,
				),
				array( '%s' ) , // data format
				array( '%s', '%s' )   // where format
			);
		}

		public static function suppress_hooks( $suppress = true ) {
			self::$suppress_hooks = $suppress;
		}

		private function parse_required_fields($defaults, $required_fields) {
			$new_required_fields = array();
			// Check if array is associative and fill in the blanks
			foreach ($required_fields as $key => $value) {
				if(!is_int($key)) {
					if(array_key_exists($key, $defaults)) {
						$new_required_fields[$key] = $required_fields[$key];
					}
				} else {
					if(array_key_exists($value, $defaults)) {
						$new_required_fields[$value] = $defaults[$value];
					}
				}
			}
			return $new_required_fields;
		}
	}
}
