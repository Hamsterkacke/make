<?php
/**
 * @package Make
 */

/**
 * Class MAKE_Builder_UI_Setup
 *
 *
 *
 * @since 1.8.0.
 */
class MAKE_Builder_UI_Setup extends MAKE_Util_Modules implements MAKE_Util_HookInterface {
	/**
	 * An associative array of required modules.
	 *
	 * @since 1.8.0.
	 *
	 * @var array
	 */
	protected $dependencies = array(
		'scripts' => 'MAKE_Setup_ScriptsInterface',
		'builder' => 'MAKE_Builder_SetupInterface',
	);

	/**
	 * Indicator of whether the hook routine has been run.
	 *
	 * @since 1.8.0.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Hook into WordPress.
	 *
	 * @since 1.8.0.
	 *
	 * @return void
	 */
	public function hook() {
		if ( $this->is_hooked() ) {
			return;
		}

		// The Builder metabox
		add_action( 'add_meta_boxes', array( $this, 'add_builder_metabox' ) );

		// Styles and scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'prep_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 20 );

		// Templates
		add_action( 'admin_print_footer_scripts', array( $this, 'print_templates' ) );

		// Hooking has occurred.
		self::$hooked = true;
	}

	/**
	 * Check if the hook routine has been run.
	 *
	 * @since 1.8.0.
	 *
	 * @return bool
	 */
	public function is_hooked() {
		return self::$hooked;
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @hooked action add_meta_boxes
	 *
	 * @param string $post_type
	 *
	 * @return void
	 */
	public function add_builder_metabox( $post_type ) {
		// Make sure the Builder data has loaded.
		$this->builder();

		// Check post type support
		if ( post_type_supports( $post_type, 'make-builder' ) ) {
			$labels = get_post_type_labels( get_post_type_object( $post_type ) );
			$data = get_all_post_type_supports( $post_type );

			add_meta_box(
				'ttfmake-builder',
				sprintf(
					esc_html__( '%s Builder', 'make' ),
					esc_html( $labels->singular_name )
				),
				array( $this, 'render_builder' ),
				$post_type,
				'normal',
				'high',
				$data['make-builder']
			);
		}
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @param $object
	 * @param $box
	 *
	 * @return void
	 */
	public function render_builder( $object, $box ) {
		// Messages
		?>
		<div id="ttfmake-builder-message"></div>
	<?php
		// The menu
		?>
		<div id="ttfmake-menu" class="ttfmake-menu">
			<div class="ttfmake-menu-pane">
				<ul class="ttfmake-menu-list"></ul>
			</div>
		</div>
	<?php
		// The stage
		?>
		<div id="ttfmake-stage" class="ttfmake-stage ttfmake-stage-closed"></div>
	<?php
		// Other inputs
		?>
		<input type="text" id="ttfmake-section-order" name="ttfmake-section-order" value="" />
		<?php wp_nonce_field( 'save', 'ttfmake-builder-nonce' ); ?>
	<?php
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @return void
	 */
	public function prep_scripts() {
		global $pagenow, $typenow;

		// The initializer script
		wp_register_script(
			'make-builder-ui-init',
			$this->scripts()->get_js_directory_uri() . '/builder/ui/init.js',
			array(
				'jquery',
				'backbone',
				'underscore',
				'jquery-ui-sortable',
				'jquery-effects-core',
				'media-views',
				'wp-color-picker',
				'utils',
				'wp-util',
				'wplink',
			),
			TTFMAKE_VERSION,
			true
		);

		// Container for script data to pass to the initializer
		$script_data = array();

		/**
		 * Filter: Modify whether new pages/posts default to the Builder.
		 *
		 * @since 1.7.0.
		 *
		 * @param bool $is_default
		 */
		$is_default = apply_filters( 'make_builder_is_default', true );

		// Environment data
		$script_data['environment'] = array(
			'state'           => ( $is_default ) ? 'active' : 'inactive',
			'type'            => $typenow,
			'screenID'        => $pagenow,
			'defaultTemplate' => 'default',
			'builderTemplate' => 'template-builder.php',
		);

		// Other Builder script handles (the initializer will load these)
		// Naming convention defines path to script file
		$script_handles = array(
			'make-builder-ui-model-menu',
			'make-builder-ui-model-menuitem',
			'make-builder-ui-model-stage',
			'make-builder-ui-model-section',
			'make-builder-ui-collection-menuitems',
			'make-builder-ui-collection-sections',
			'make-builder-ui-view-menu',
			'make-builder-ui-view-menuitem',
			'make-builder-ui-view-stage',
			'make-builder-ui-view-section',
		);

		/**
		 * Filter: Modify the array of dependency handles for the Builder's JS.
		 *
		 * Each item in this array should be the handle for a registered script.
		 *
		 * Note that these scripts will be lazy loaded by the Builder app rather than enqueued.
		 * Third party libraries should not be added to this array.
		 *
		 * @since 1.2.3.
		 *
		 * @param array $dependencies    The array of dependency handles.
		 */
		$script_handles = apply_filters( 'make_builder_js_dependencies', $script_handles );

		// Register each script, based on the handle
		foreach ( $script_handles as $handle ) {
			// Check if the script is already registered (e.g. by a plugin)
			if ( $this->scripts()->is_registered( $handle, 'script' ) ) {
				continue;
			}

			// Get the URL for the script
			// Trim the prefix
			$str = $handle;
			$prefix = 'make-';
			if ( substr( $str, 0, strlen( $prefix ) ) == $prefix ) {
				$str = substr( $str , strlen( $prefix ) );
			}
			// Convert the name to the path
			$relative_url = str_replace( '-', '/', $str ) . '.js';
			// Check for a child theme version
			$absolute_url = $this->scripts()->get_located_file_url( array( $relative_url, 'js/' . $relative_url ) );

			// Register the script
			if ( $absolute_url ) {
				wp_register_script(
					$handle,
					$absolute_url,
					array(),
					TTFMAKE_VERSION,
					true
				);
			}
		}

		// Script URLs to pass to the initializer
		$script_data['scripts'] = array();
		foreach ( $script_handles as $handle ) {
			if ( $this->scripts()->is_registered( $handle, 'script' ) ) {
				$script_data['scripts'][] = $this->scripts()->get_url( $handle, 'script' );
			} else {
				$script_data['scripts'][] = 'Invalid script';
			}
		}

		// Builder data to pass to the initializer
		$script_data['data'] = array(
			'menu' => array(
				'items' => $this->builder()->get_top_level_section_types(),
			),
		);

		// Localization strings
		$script_data['l10n'] = array(
			'loading' => __( 'Loading', 'make' ),
			'loadFailure' => __( 'The Builder failed to load.', 'make' ),
		);

		// Add script data to initializer
		wp_localize_script(
			'make-builder-ui-init',
			'MakeBuilder',
			$script_data
		);
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @return void
	 */
	public function enqueue() {
		// Styles
		wp_enqueue_style(
			'make-builder-ui',
			$this->scripts()->get_css_directory_uri() . '/builder/ui/builder.css',
			array(),
			TTFMAKE_VERSION,
			'screen'
		);

		// Scripts
		wp_enqueue_script( 'make-builder-ui-init' );
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @return array
	 */
	private function get_section_ui_templates() {
		$section_types = $this->builder()->get_all_section_types();
		$section_ui_templates = array();

		foreach ( $section_types as $section_type ) {
			$section_ui_templates[ $section_type->type ] = $section_type->create_ui_template();
		}

		return $section_ui_templates;
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @return void
	 */
	public function print_templates() {
		// Menu item
		?>
		<script type="text/html" id="tmpl-make-builder-menuitem">
			<a href="#" title="{{ data.description }}" class="ttfmake-menu-list-item-link" id="ttfmake-menu-list-item-link-{{ data.type }}" data-section="{{ data.type }}">
				<div class="ttfmake-menu-list-item">
					<div class="ttfmake-menu-list-item-link-icon-wrapper clear" style="background-image: url({{ data.icon_url }});">
						<span class="ttfmake-menu-list-item-link-icon"></span>
						<div class="section-type-description">
							<h4>{{{ data.label }}}</h4>
						</div>
					</div>
				</div>
			</a>
		</script>
	<?php
		// Sections
		$section_templates = $this->get_section_ui_templates();
		foreach ( $section_templates as $section_type => $section_template ) {
			?>
			<script type="text/html" id="tmpl-make-builder-<?php echo esc_attr( $section_type ); ?>">
				<?php $section_template->render(); ?>
			</script>
		<?php
		}
	}
}