<?php

if ( ! defined( 'WPINC' ) ) die;

class _WP_Admin_Page {

	public $settings = null;

	protected $fields = [];
	protected $tabs = [];

	protected $hook_suffix = null;
	protected $setup_callbacks = [];

	protected $default_tab = null;
	protected $default_settings = [
		// required
		'id' => null, // Unique ID of the menu item
		'menu_name' => null, // Name of the menu item

		// optional
		'options_prefix' => null,
		'parent' => null, // id of parent, if blank, then this is a top level menu
		'capability' => 'manage_options', // User role
		'icon' => 'dashicons-admin-generic', // Menu icon for top level menus only http://melchoyce.github.io/dashicons/
		'position' => null, // Menu position. Can be used for both top and sub level menus
	];

	protected $enqueue_color_picker = false;
	protected $enqueue_ace = false;

	public function __construct ( $settings = [] ) {
		$this->settings = $this->validate_settings( $settings );

		$this->init();

		$this->hooks();
	}

	protected function validate_settings ( $settings ) {
		$result = array_merge( $this->default_settings, $settings );

		if ( empty( $result['id'] ) || empty( $result['menu_name'] ) ) {
			throw new Exception( 'An admin page requires an id and menu_name' );
		}

		return $result;
	}

	protected function init () {
		$this->set_tab( [
			'id'    => 'default',
			'name'  => ucfirst( $this->settings['menu_name'] ),
		] );
	}

	protected function hooks () {
		// Add settings page to menu
		$submenu_position = 10;
		if ( ! empty( $this->settings['parent'] ) && ! empty( $this->settings['position'] ) ) {
			$submenu_position = $this->settings['position'];
		}
		add_action( 'admin_menu' , array( $this, 'add_menu_item' ), $submenu_position );

		// Register plugin settings
		add_action( 'admin_init' , array( $this, 'register_settings' ) );

		// show admin notices
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	public function add_menu_item () {
		$menu_title     = $this->settings['menu_name'];
		$capability     = $this->settings['capability'];
		$slug           = $this->settings['id'];
		$parent         = $this->settings['parent'];
		$icon           = $this->settings['icon'];
		$position       = $this->settings['position'];
		$callback       = array( $this, 'render_page' );

		if ( empty( $parent ) ) {
			$this->hook_suffix = add_menu_page( $menu_title, $menu_title, $capability, $slug, $callback, $icon, $position );
		} else {
			$this->hook_suffix = add_submenu_page( $parent, $menu_title, $menu_title, $capability, $slug, $callback );
		}

		$this->do_setup();

		add_action( 'admin_print_styles-' . $this->hook_suffix, array( $this, 'enqueue_assets' ) );
	}

	public function register_settings () {
		$page_slug = $this->settings['id'];
		$current_section = null;

		if ( count( $this->fields ) > 0 ) {

			// Check posted/selected tab
			$current_tab = $this->get_current_tab();

			foreach ( $this->tabs as $tab_id => $tab_data ) {

				if ( $current_tab && $current_tab != $tab_id ) continue;

				$current_section = $current_tab;
				// Add section to page
				add_settings_section( $current_section, '', [ $this, 'render_section' ], $page_slug );

				foreach ( $tab_data['fields'] as $field ) {

					if ( $field['type'] === 'subtitle' ) {
						$current_section = $field['id'];
						add_settings_section( $current_section, '', [ $this, 'render_section' ], $page_slug );
						continue;
					}

					// Register field
					register_setting(
						$page_slug,
						$field['id']
					);

					if ( is_callable( $field['sanitize_callback'] ) ) {
						$field_id = $field['id'];
						$the_page = $this;
						$sanitize_callback = $field['sanitize_callback'];
						$args = [
							$field_id,
							$the_page,
							$sanitize_callback,
						];
						add_filter( 'sanitize_option_' . $field_id, function ( $value ) use ( $args ) {
							$sanitize_callback = array_pop( $args );
							array_unshift( $args, $value );
							return call_user_func_array( $sanitize_callback, $args );
						}, 10, 3 );
					}

					// Add field to page
					add_settings_field(
						$field['id'],
						$field['label'],
						[ $this, 'render_field' ],
						$page_slug,
						$current_section,
						[
							'field' => $field,
							'class' => ( $field['type'] === 'hidden' )
								? $field['wrapper_class'] . ' hidden_field'
								: $field['wrapper_class'],
						]
					);
				}

				if ( ! $current_tab ) break;
			}
			//$this->do_setup();
		}
	}

	public function admin_notices() {
		global $wp_settings_errors;
		$page_slug = $this->settings['id'];

		settings_errors();

		// prevent duplicates
		foreach ( (array) $wp_settings_errors as $key => $details ) {
			if ( 'general' === $details['setting'] ) {
				unset( $wp_settings_errors[ $key ] );
				break;
			}
		}
	}

	public function render_tabs () {
		$current_tab = $this->get_current_tab();
		$c = 0;

		echo '<h2 class="nav-tab-wrapper">';

		foreach ( $this->tabs as $tab_id => $tab_data ) {
			$class = 'nav-tab';

			if ( ! $current_tab ) {
				if ( 0 == $c ) {
					$class .= ' nav-tab-active';
				}
			} else {
				if ( ! empty( $current_tab ) && $tab_id == $current_tab ) {
					$class .= ' nav-tab-active';
				}
			}

			$tab_name = $tab_data['name'];
			$tab_link = add_query_arg( array( 'tab' => $tab_id ) );

			if ( isset( $_GET['settings-updated'] ) ) {
				$tab_link = remove_query_arg( 'settings-updated', $tab_link );
			}

			echo '<a href="' . $tab_link . '" class="' . esc_attr( $class ) . '">' . esc_html( $tab_name ) . '</a>';

			$c++;
		}

		echo '</h2>';
	}

	public function render_section ( $args ) {
		$id = $args['id'];
		$is_tab = false;
		$title = '';
		$desc = '';
		$title_tag = 'h2';

		if ( isset( $this->tabs[ $id ] ) ) {
			$data = $this->tabs[ $id ];
			$is_tab = true;
			$title_tag = 'h1';
			$title = $data['name'];
			$desc = $data['desc'];
		} else {
			$data = $this->fields[ $id ];
			$title = $data['label'];
			$desc = $data['desc'];
		}

		$html = '<header class="' . ( $is_tab ? 'tab' : 'section' ) . '-header">';
		$html .= '<' . $title_tag . ' class="title">' . esc_html( $title ) . '</' . $title_tag . '>';
		if ( ! empty( $desc ) ) {
			$html .= '<p>' . _WP_Field_Renderer::render_description( $desc, true, false ) . "</p>\n";
		}
		$html .= '</header>';
		echo $html;
	}

	public function render_page () {

		$page_slug = $this->settings['id'];
		$current_tab = $this->get_current_tab();
		$tab_data = $this->tabs[ $current_tab ];
		?>

		<div class="wrap" id="<?php echo esc_attr( $page_slug ); ?>">

			<?php do_action( 'better_wp_admin_api_before-' . $this->hook_suffix, $this ); ?>

			<?php if ( count( $this->tabs ) > 1 ) {
				$this->render_tabs();
			} ?>

			<form
				id="<?php echo esc_attr( $page_slug ); ?>-form"
				action="options.php"
				method="post"
				enctype="multipart/form-data"
			>

				<?php do_action( 'better_wp_admin_api_form_start-' . $this->hook_suffix, $this ); ?>

				<?php
				settings_fields( $page_slug );
				do_settings_sections( $page_slug );
				?>

				<input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>" />

				<?php do_action( 'better_wp_admin_api_form_end-' . $this->hook_suffix, $this ); ?>

				<?php submit_button( $tab_data['submit_label'] ); ?>

			</form>

			<?php do_action( 'better_wp_admin_api_after-' . $this->hook_suffix, $this ); ?>

		</div>

		<?php
	}

	public function render_field ( $args ) {
		// Get field info
		$field = $args['field'];
		$field_settings = [];

		// Get saved field value
		$field_settings['value'] = $this->get_field_value( $field['id'] );

		$field_settings = array_merge( $field, $field_settings );

		$field_type = $field['type'];
		$renderer = "_WP_Field_Renderer::render_${field_type}_field";

		if ( is_callable( $renderer ) ) {
			call_user_func( $renderer, $field_settings );
		} else {
			// used to made custom fields
			$field_settings = apply_filters( "better_wp_admin_api_field_${field_type}_settings", $field_settings );
			$field_output = apply_filters( "better_wp_admin_api_field_${field_type}_output", false, $field_settings );

			if ( $field_output !== false ) {
				// print a custom field
				echo $field_output;
			} else {
				// print an undefined field notice
				echo "<pre><code>Undefined \"${field_type}\" field type.</code></pre>";
			}
		}

		return;
		switch( $field['type'] ) {
			case 'text':
			case 'url':
			case 'email':
				_WP_Field_Renderer::render_text_field( $field_settings );
			break;

			case 'select':
				_WP_Field_Renderer::render_select_field( $field_settings );
			break;

			case 'checkbox':
				_WP_Field_Renderer::render_checkbox_field( $field_settings );
			break;

			case 'radio':
				_WP_Field_Renderer::render_radio_field( $field_settings );
			break;

			case 'hidden':
				_WP_Field_Renderer::render_hidden_field( $field_settings );
			break;

			case 'code':
				_WP_Field_Renderer::render_code_field( $field_settings );
			break;

			case 'color':
				_WP_Field_Renderer::render_color_field( $field_settings );
			break;

			case 'content':
				_WP_Field_Renderer::render_content_field( $field_settings );
			break;

			//case 'image':
				// TODO
				//_WP_Field_Renderer::render_image_field( $field_settings );
			//break;

			case 'html':
				_WP_Field_Renderer::render_html_field( $field_settings );
			break;

			case '__invalid__':
				_WP_Field_Renderer::render_invalid_field( $field_settings );
			break;

			default:
				echo '<pre><code>Undefined field type.</code></pre>';
			break;
		}
	}

	public function enqueue_assets () {
		// required for 'color' field type
		wp_enqueue_style( 'wp-color-picker' );

		// required for 'image' field type
		//wp_enqueue_media();

		// required for 'code' field type
		wp_enqueue_script(
			'ace-editor',
			plugins_url( 'assets/vendor/ace/src-min-noconflict/ace.js', BETTER_WP_ADMIN_API_FILE ),
			[],
			'1.2.9',
			true
		);

		// required for 'code' field type
		$deps = [ 'jquery' ];
		wp_enqueue_script(
			'better-wp-admin-api-fields',
			plugins_url( 'assets/js/admin/fields.js', BETTER_WP_ADMIN_API_FILE ),
			[
				'jquery',
				'ace-editor',
				'wp-color-picker',
			],
			BETTER_WP_ADMIN_API_VERSION,
			true
		);

		// fields css
		wp_enqueue_style(
			'better-wp-admin-api-fields',
			plugins_url( 'assets/css/admin/fields.css', BETTER_WP_ADMIN_API_FILE )
		);
	}

	protected function do_setup () {
		foreach ( $this->setup_callbacks as $callback ) {
			if ( is_callable( $callback ) ) {
				call_user_func( $callback, $this, $this->hook_suffix );
			}
		}
		unset( $this->setup_callbacks ); // free memory
	}

	public function on_setup ( $callback ) {
		$this->setup_callbacks[] = $callback;
	}

	public function add_field ( $data ) {
		$defaults = [
			'id'                => '',
			'sanitize_callback' => '',
			'tab'               => $this->default_tab,
			'default'           => false,
			'wrapper_class'     => '',
		];
		$data = array_merge( $defaults, $data );

		$data['unprefixed_id'] = $data['id'];

		if ( empty ( $data['id'] ) ) {
			$data['id'] = '__invalid__' . rand(0, 2147483647);
			$data['type'] = '__invalid__';
			$data['error_message'] = 'All fields requires a "id" parameter.';
		} else {
			$data['id'] = $this->prefix_field_name( $data['id'] );
		}

		if ( empty( $data['label'] ) ) {
			$data['label'] = $data['id'];
		}

		if ( empty( $this->tabs[ $data['tab'] ] ) ) {
			// create the field tab, if necessary
			$this->set_tab( [ 'id' => $data['tab'] ] );
		}

		$data['__PAGE__'] = $this;

		// store the field
		$this->fields[ $data['id'] ] = $data;
		$this->tabs[ $data['tab'] ]['fields'][] = $data;

		// return the prefixed input name
		// useful to use with hooks that requires the field id
		// e.g: sanitize_option_{$field_id} and default_option_{$field_id}
		return $data['id'];
	}

	public function add_subtitle ( $name, $desc = '', $tab = null ) {
		if ( empty( $name ) ) {
			throw new Exception( 'All subtitles requires a "name" parameter.' );
		}

		$tab = empty( $tab ) ? $this->default_tab : $tab;

		$field = $this->add_field( [
			'type'   => 'subtitle',
			'tab'    => $tab,
			'id'     => $name,
			'label'  => $name,
			'desc'   => $desc,
		] );
	}

	public function set_tab ( $data ) {
		$defaults = [
			'id'            => '',
			'name'          => '',
			'desc'          => '',
			'submit_label'  => __( 'Save Settings' , 'wordpress' ),
			'fields'        => [],
		];
		$data = array_merge( $defaults, $data );

		if ( empty( $data['id'] ) ) {
			throw new Exception( 'All tabs requires an "id" parameter.' );
		}

		if ( empty( $data['name'] ) ) {
			$data['name'] = ucfirst( $data['id'] );
		}

		$this->tabs[ $data['id'] ] = $data;

		if ( is_null( $this->default_tab ) ) {
			$this->default_tab = $data['id'];
		}

		return $data['id'];
	}

	public function get_hook_suffix () {
		return $this->hook_suffix;
	}

	public function get_field_value ( $field_id ) {
		$possible_ids = [ $field_id, $this->prefix_field_name( $field_id ) ];
		$field = null;
		$value = '';

		foreach ( $possible_ids as $_id ) {
			if ( isset( $this->fields[ $_id ] ) ) {
				$field = $this->fields[ $_id ];
				$field_id = $_id;
				break;
			}
		}

		if ( ! empty( $field ) ) {
			$value = get_option( $field_id, false );
			if ( $value === false ) {
				$value = apply_filters( 'better_wp_admin_api_field_default_value', $field['default'], $field, $this );
			} elseif ( empty( $value ) ) {
				$value = '';
			}
		} else {
			throw new Exception("Trying to get value of an undefined field with id: '$field_id'");
		}

		return $value;
	}

	public function prefix_field_name ( $field_id ) {
		return ( ! empty( $this->settings['options_prefix'] ) )
			? $this->settings['options_prefix'] . $field_id
			: $field_id;
	}

	public function get_current_tab () {
		$current = false;
		if ( isset( $_POST['tab'] ) && $_POST['tab'] ) {
			$current = $_POST['tab'];
		} else {
			if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
				$current = $_GET['tab'];
			}
		}
		if ( ! $current ) {
			$current = $this->default_tab;
		}
		return $current;
	}

	public function get_default_tab () {
		return $this->default_tab;
	}
}
