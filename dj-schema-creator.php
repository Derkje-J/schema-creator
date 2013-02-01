<?php
/*
Plugin Name: Schema Creator by Derk-Jan and Raven
Plugin URI: http://github.com/Derkje-J/schema-creator
Description: Insert schema.org microdata into posts and pages
Version: 1.0
Author: Derk-Jan Karrenbeld.info
Author URI: http://derk-jan.com/
License: GPL v2

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA


	Resources

	http://schema-creator.org/
	http://foolip.org/microdatajs/live/
	http://www.google.com/webmasters/tools/richsnippets
	
Actions Hooks:
	dj_sc_onactivate			: runs when plugin is activated
	dj_sc_default_settings		: runs when plugin is activated and settings defaults are set
	dj_sc_register_settings		: runs when the settings are registered
	dj_sc_options_validate		: runs when the settings are saved ( &array )
	dj_sc_options_form			: runs when the settings form is outputted
	dj_sc_metabox				: runs when the metabox is outputted
	dj_sc_save_metabox			: runs when the metabox is saved
	dj_sc_enqueue_schemapost	: runs when showing a post with a schema
	
Filters:
	dj_sc_default_settings		: gets default settings values
	dj_sc_admin_tooltip			: gets the tooltips for admin pages

*/

if ( !class_exists( "DJ_SchemaCreator" ) ) :

	define('DJ_SCHEMACREATOR_BASE', plugin_basename(__FILE__) );
	define('DJ_SCHEMACREATOR_VERSION', '1.0');

	class DJ_SchemaCreator
	{
		private static $singleton;
		public $debug = false;
		
		/**
		 * Gets a singleton of this class
		 *
		 * DJ_SchemaCreator::singleton() will always return the same instance during a
		 * PHP processing stack. This way actions will not be queued duplicately and 
		 * caching of processed values is not neccesary.
		 *
		 * @return DJ_SchemaCreator the singleton instance
		 */
		public static function singleton() {
			if ( empty( DJ_SchemaCreator::$singleton ) )
				DJ_SchemaCreator::$singleton = new DJ_SchemaCreator();
			return DJ_SchemaCreator::$singleton;
		}
		
		/**
		 * Creates a new instance of DJ_SchemaCreator
		 *
		 * @link DJ_SchemaCreator::singleton() use outside the class hieracrchy
		 */
		protected function __construct() {		
			// Text domain
			add_action( 'plugins_loaded', array( $this, 'plugin_textdomain' ) );
			
			// Edit Post Page ( Metabox/Media button )
			add_action( 'the_posts', array( $this, 'schema_loader' ) );
			add_action( 'do_meta_boxes', array( $this, 'metabox_schema' ), 10, 2 );
			add_action( 'save_post', array( $this, 'save_metabox' ) );
			add_filter( 'media_buttons', array( $this, 'schema_media_button' ), 31 );
			add_action( 'admin_footer',	array( $this, 'schema_media_form'	) );
			add_filter( 'tiny_mce_version', 'my_refresh_mce');
			
			// Plugins page
			add_filter( 'plugin_action_links', array( $this, 'quick_link' ), 10, 2 );
			
			// Settings Page
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'admin_menu', array( $this, 'add_pages' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );					
			add_filter( 'dj_sc_default_settings', array( $this, 'get_default_settings' ) );
			add_filter( 'dj_sc_admin_tooltip', array( $this, 'get_tooltips' ) );
			add_filter( 'admin_footer_text', array( $this, 'admin_footer_attribution' ) );
			register_activation_hook( __FILE__, array( $this, 'default_settings' ) );
			register_activation_hook( __FILE__, create_function( '', 'do_action(\'dj_sc_onactivate\');' ) );

			// Admin bar
			add_action( 'admin_bar_menu', array( $this, 'admin_bar_schema_test' ), 9999 );
			
			// Content
			add_filter( 'body_class', array( $this, 'body_class' ) );
			add_filter( 'the_content', array( $this, 'schema_wrapper' ) );
			add_shortcode( $this->get_option( 'shortcode' ) ?: 'schema' , array( $this, 'shortcode' ) );
			
			// Ajax actions
			add_action( 'wp_ajax_get_schema_types', array( $this, 'get_schema_types' ) );
			add_action( 'wp_ajax_get_schema_properties', array( $this, 'get_schema_properties' ) );
			add_action( 'wp_ajax_get_schema_datatypes', array( $this, 'get_schema_datatypes' ) );
			
		}
	
		/**
		 * Load textdomain for international goodness
		 */
		public function plugin_textdomain() {
			load_plugin_textdomain( 'schema', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	
		/**
		 * Shows the settings option on the plugins page
		 * 
		 * @param string[] $links current links for plugin
		 * @param string $file plugin file links being fetched for
		 *
		 * @return string[] the links for the plugin
		 */
		public function quick_link( $links, $file ) {
			static $this_plugin;
	
			if ( !$this_plugin ) {
				$this_plugin = plugin_basename( __FILE__ );
			}
	
			// check to make sure we are on the correct plugin
			if ( $file == $this_plugin ) {
				$settings_link	= '<a href="' . menu_page_url( 'dj-schema-creator', 0 ) . '">' . _x( 'Settings', 'link to page', 'schema' ) . '</a>';
				array_unshift( $links, $settings_link );
			}
	
			return $links;
		}
	
		/**
		 * Adds the `test schema` link to the admin toolbar
		 *
		 * @param object $wp_admin_bar the current admin bar
		 */
		public function admin_bar_schema_test( $wp_admin_bar ) {
			// No link on admin panel, only load on singles
			if ( is_admin() || !is_singular() )
				return;
	
			//get some variables
			global $post;
			$link = get_permalink( get_the_ID() );
	
			// set args for tab
			global $wp_admin_bar;
	
			$args = array(
				'parent'	=> 'top-secondary',
				'id'		=> 'schema-test',
				'title' 	=> _x('Test Schema', 'test the schema button title', 'schema'),
				'href'		=> esc_url( __( 'http://www.google.com/webmasters/tools/richsnippets/', 'schema' ) . 
										'?url=' . urlencode($link) . '&html=' ),
				'meta'		=> array(
					'class'		=> 'schema-test',
					'target'	=> '_blank'
					)
			);
	
			$wp_admin_bar->add_node($args);
		}
	
		/**
		 * Display metabox for schemas
		 *
		 * @param string $page current page hook
		 * @param string $context current metabox context
		 */
		public function metabox_schema( $page, $context ) {
			// only add on side
			if ('side' != $context)
				return;
				
			// check to see if they have options first
			$schema_options	= $this->get_options();
	
			// they haven't enabled this? THEN YOU LEAVE NOW
			if( empty( $dj_schema_options['body'] ) && empty( $dj_schema_options['post'] ) )
				return;
	
			// get custom post types
			$args = array(
				'public'   => true,
				'_builtin' => false
			);
			$output		= 'names';
			$operator	= 'and';
	
			$customs	= get_post_types( $args, $output, $operator );
			$builtin	= array('post' => 'post', 'page' => 'page');
	
			$types		= $customs !== false ? array_merge( $customs, $builtin ) : $builtin;
	
			if ( in_array( $page,  $types ) )
				add_meta_box( 'schema-post-box', __( 'Schema Display Options', 'schema' ), array( $this, 'schema_post_box' ), $page, $context, 'high' );
		}
	
		/**
		 * Display checkboxes for disabling the itemprop and itemscope
		 */
		public function schema_post_box( ) {
			global $post;
			
			// Add downwards compatability
			$disable_body = get_post_meta($post->ID, '_schema_disable_body', true);
			$disable_body = $disable_body === true || $disable_body == 'true' || $disable_body == '1';
			$disable_post = get_post_meta($post->ID, '_schema_disable_post', true);
			$disable_post = $disable_post === true || $disable_post == 'true' || $disable_post == '1';
	
			// use nonce for security
			wp_nonce_field( DJ_SCHEMACREATOR_BASE, 'schema_nonce' );
			?>
			
			<p class="schema-post-option">
				<input type="checkbox" name="schema_disable_body" id="schema_disable_body" value="true" <?php echo checked( $disable_body, true, false ); ?>>
				<label for="schema_disable_body"><?php _e( 'Disable body itemscopes on this post.' , 'schema' ); ?></label>
			</p>
	
			<p class="schema-post-option">
				<input type="checkbox" name="schema_disable_post" id="schema_disable_post" value="true" <?php echo checked( $disable_post, true, false ); ?>>
				<label for="schema_disable_post"><?php _e( 'Disable content itemscopes on this post.' , 'schema' ); ?></label>
			</p>
			<?php
			
			do_action( 'dj_sc_metabox' );
		}
	
		/**
		 * Save the data
		 *
		 * @param int $post_id the current post id
		 * @return int|void the post id or void
		 */
		public function save_metabox( $post_id = 0 )
		{	
			$post_id = (int)$post_id;
			$post_status = get_post_status( $post_id );
	
			if ( "auto-draft" == $post_status ) 
				return $post_id;
	
			if ( isset( $_POST['schema_nonce'] ) && !wp_verify_nonce( $_POST['schema_nonce'], DJ_SCHEMACREATOR_BASE ) )
				return;
	
			if ( !current_user_can( 'edit_post', $post_id ) )
				return;
	
			// OK, we're authenticated: we need to find and save the data
			$db_check = isset( $_POST[ 'schema_disable_body' ] );
			$dp_check = isset( $_POST[ 'schema_disable_post' ] );
	
			update_post_meta( $post_id, '_schema_disable_body', $db_check );
			update_post_meta( $post_id, '_schema_disable_post', $dp_check );
			delete_post_meta( $post_id, '_dj_schema_load' );
			
			do_action( 'dj_sc_save_metabox' );
		}
		
		/**
		 * Gets the options value for a key
		 * 
		 * @param string $key the option key
		 * @return mixed the option value
		 */
		function get_option( $key ) {
			$dj_schema_options = $this->get_options();
			return isset( $dj_schema_options[$key] ) ? $dj_schema_options[$key] : NULL;
		}
		
		/**
		 * Gets the options
		 * @return mixed[] the options;
		 **/
		function get_options() {
			$schema_options	= get_option( 'dj_schema_options' ) ?: array();	
			$schema_options = array_merge( get_option( 'schema_options' ) ?: array(), $schema_options );
			return $schema_options;
		}
		
		/**
		 * Gets the tooltip value for a key
		 *
		 * @param string $key the tooltip key
		 * @return string the tooltip value
		 */
		function get_tooltip( $key ) {
			$tooltips = apply_filters( 'dj_sc_admin_tooltip', array() );
			return isset($tooltips[ $key ]) ? htmlentities( $tooltips[ $key ] ) : NULL;
		}
	
		/**
		 * Build settings page
		 */
		public function add_pages() {
			
			add_submenu_page( 'options-general.php',
				 __('Schema Creator', 'schema'),
				 __('Schema Creator', 'schema'), 
				'manage_options', 
				$this->get_page_slug(), 

				array( $this, 'do_page' )
			);
		}
		
		/**
		 * Gets the page slug name
		 *
		 * @returns the page slug
		 */
		public function get_page_slug() {
			return 'dj-schema-creator';
		}
	
		/**
		 * Register settings
		 */
		public function register_settings() {
			register_setting( 'dj_schema_options', 'dj_schema_options', array($this, 'options_validate' ) );
			
			// Information
			add_settings_section('info_section', __('Information', 'schema'), array($this, 'options_info_section'), 'dj_schema_options');
			add_settings_field( 'info_version', __('Plugin Version', 'schema'), array($this, 'options_info_version'), 'dj_schema_options', 'info_section');
			
			// CSS output
			add_settings_section( 'display_section', __('Display', 'schema'), array( $this, 'options_display_section' ), 'dj_schema_options' );
			add_settings_field( 'css', __( 'CSS output', 'schema' ), array( $this, 'options_display_css' ), 'dj_schema_options', 'display_section' );
			
			// HTML data applying
			add_settings_section( 'data_section', __('Data', 'schema'), array( $this, 'options_data_section' ), 'dj_schema_options' );
			add_settings_field( 'body', __( 'Body Tag', 'schema' ), array( $this, 'options_data_body' ), 'dj_schema_options', 'data_section' );
			add_settings_field( 'post', __( 'Content Wrapper', 'schema' ), array( $this, 'options_data_post' ), 'dj_schema_options', 'data_section' );

			do_action( 'dj_sc_register_settings' );
		}
		
		/**
		 * Outputs the info section HTML 
		 */
		function options_info_section() { 
		?>
            <div id='info_section'>
                <p>
				<?php 
					printf(
						__( 'By default, the %s plugin by %s and %s includes unique CSS IDs and classes. You can reference the CSS to control the style of the HTML that the Schema Creator plugin outputs.' , 'schema' ).'<br>',
						
						// the plugin 
						'<a target="_blank" 
							href="'. esc_url( _x( 'http://schema-creator.org/?utm_source=wp&utm_medium=plugin&utm_campaign=schema', 'plugin uri', 'schema' ) ) .'" 
							title="' . esc_attr( _x( 'Schema Creator', 'plugin name', 'schema' ) ) . '">'. _x( 'Schema Creator' , 'plugin name', 'schema') . '</a>', 
						
						// the author
						'<a target="_blank" 
							href="' . esc_url( _x( 'http://derk-jan.com', 'author uri', 'schema' ) ) . '" 
							title="' . esc_attr( _x('Derk-Jan.com | Derk-Jan Karrenbeld', 'author', 'schema' ) ) . '"> ' . _x( 'Derk-Jan Karrenbeld' , 'author', 'schema') . '</a>',
							
						'<a target="_blank" 
							href="' . esc_url( _x( 'http://raventools.com/?utm_source=wp&utm_medium=plugin&utm_campaign=schema', 'author uri', 'schema' ) ) . '" 
							title="' . esc_attr( _x('Raven Internet Marketing Tools', 'author', 'schema' ) ) . '"> ' . _x( 'Raven Internet Marketing Tools' , 'author', 'schema') . '</a>'
					); 
					_e( 'The plugin can also automatically include <code>http://schema.org/Blog</code> and <code>http://schema.org/BlogPosting</code> schemas to your pages and posts.', 'schema'); echo "<br>";			
					printf(
						__( 'Google also offers a %s to review and test the schemas in your pages and posts.', 'schema'),
						
						// Rich Snippet Testing Tool link
						'<a target="_blank" 
							href="' . esc_url( __( 'http://www.google.com/webmasters/tools/richsnippets/', 'schema' ) ) . '" 
							title="' . esc_attr__( 'Rich Snippet Testing tool', 'schema' ) . '"> '. __( 'Rich Snippet Testing tool' , 'schema'). '</a>'
					)
				?>
                </p>
	
			</div> <!-- end #info_section -->
            <?php
		}
		
		/**
		 * Outputs the info version field
		 */
		function options_info_version() 
		{ 
			echo "<code id='info_version'>".DJ_SCHEMACREATOR_VERSION."</code>";
		}
					
		/**
		 * Outputs the display section HTML
		 */
		function options_display_section() { }
		
		/**
		 * Outputs the display css field
		 */
		function options_display_css() { 
			$css_hide = $this->get_option( 'css' );
			$css_hide = isset( $css_hide ) && ($css_hide === true || $css_hide == 'true');

			echo '<label for="schema_css">
					<input type="checkbox" id="schema_css" name="dj_schema_options[css]" class="schema_checkbox" value="true" '.checked($css_hide, true, false).'/>
					 '.__('Exclude default CSS for schema output', 'schema').'
				</label>
				<span class="ap_tooltip" tooltip="'.$this->get_tooltip( 'default_css' ).'">'._x('(?)', 'tooltip button', 'schema').'</span>
			';
		}
		
		/**
		 * Outputs the data section HTML
		 */
		function options_data_section() { }
		
		/**
		 * Outputs data body field
		 */
		function options_data_body() { 
			$body_tag = $this->get_option( 'body' );
			$body_tag = isset( $body_tag ) && ($body_tag === true || $body_tag == 'true');
			
			echo '<label for="schema_body">
					<input type="checkbox" id="schema_body" name="dj_schema_options[body]" class="schema_checkbox" value="true" '.checked($body_tag, true, false).'/>
					 '.__('Apply itemprop &amp; itemtype to main body tag', 'schema').'
				</label>
				<span class="ap_tooltip" tooltip="'.$this->get_tooltip( 'body_class' ).'">'._x('(?)', 'tooltip button', 'schema').'</span>
			';
			
		}
		
		/** 
		 * Outputs data post field
		 */
		function options_data_post() { 
			$post_tag = $this->get_option( 'post' );
			$post_tag = isset( $post_tag ) && ($post_tag === true || $post_tag == 'true');
			
			echo '<label for="schema_post">
					<input type="checkbox" id="schema_post" name="dj_schema_options[post]" class="schema_checkbox" value="true" '.checked($post_tag, true, false).'/>
					 '.__('Apply itemscope &amp; itemtype to content wrapper', 'schema').'
				</label>
				<span class="ap_tooltip" tooltip="'.$this->get_tooltip( 'post_class' ).'">'._x('(?)', 'tooltip button', 'schema').'</span>
			';
		}
		
		/**
		 * Validates input
		 *
		 * @param mixed[] $input the to be processed new values
		 * @return mixed the processed new values
		 */
		function options_validate( $input ) {
			
			do_action_ref_array( 'dj_sc_options_validate', array( &$input ) );
			
			
			/* example: 
			 * $input['some_value'] =  wp_filter_nohtml_kses($input['some_value']);	
			 * $input['maps_zoom'] = min(21, max(0, intval($input['maps_zoom'])));
			 * */

			$input['css'] = isset( $input['css'] );
			$input['body'] = isset( $input['body'] );
			$input['post'] = isset( $input['post'] );
			
			return $input; // return validated input
		}
	
		/**
		 * Set default settings
		 */
		public function default_settings( ) 
		{
			$options_check	= get_option( 'dj_schema_options' );
			$default = apply_filters( 'dj_sc_default_settings', array() );

			// Existing options will override defaults
			update_option( 'dj_schema_options', array_merge( $default, $options_check ) );
		}
		
		/**
		 * Gets the default settings
		 *
		 * @param mixed[] $default current defaults
		 * @return mixed[] new defaults
		 */
		public function get_default_settings( $default = array() ) 
		{
			$default['css']	= false;
			$default['body'] = true;
			$default['post'] = true;
			
			return $default;
		}	
	
		/**
		 * Content for pop-up tooltips
		 *
		 * @param string[] $tooltip current tooltips
		 * @return string[] new tooltips
		 */
		public function get_tooltips( $tooltip = array() ) 
		{
			$tooltip = array_merge( $tooltip, array(
				'default_css'	=> __('Check to remove Schema Creator CSS from the microdata HTML output.', 'schema'),
				'body_class'	=> __('Check to add the <code>http://schema.org/Blog</code> schema itemtype to the BODY element on your pages and posts. Your theme must have the <code>body_class</code> template tag for this to work.', 'schema'),
				'post_class'	=> __('Check to add the <code>http://schema.org/BlogPosting</code> schema itemtype to the content wrapper on your pages and posts.', 'schema'),
	
				// end tooltip content
			) );
	
			return $tooltip;
		}

		
		/**
		 * Adds ajax headers
		 */
		public function do_ajax() {
			header( 'Cache-Control: no-cache, must-revalidate' );
			header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
			header( 'Content-type: application/json' );	
		}
	
		/**
		 * Display main options page structure
		 */
		public function do_page() {
	
			if (!current_user_can( 'manage_options' ) )
				return;
			?> 	
			<div class="wrap">
				<div class="icon32" id="icon-schema"><br></div>
				<h2><?php _e('Schema Creator Settings', 'schema'); ?></h2>
                <div class="schema_options">
                	<form action="options.php" method="post">
						<?php 
							settings_fields( 'dj_schema_options' );	
	 						do_settings_sections( 'dj_schema_options' );
                        	do_action( 'dj_sc_options_form' );
                        ?>
	                    
	                    <p class="submit">
                        	<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
                    	</p>	
                	</form>
				</div> <!-- end .schema_options -->
			</div> <!-- end .wrap -->
		<?php }
	
	
		/**
		 * Load scripts and style for admin settings page
		 * 
		 * @param string $hook the current page hook
		 */
		public function admin_scripts( $hook ) {
			
			$post_screen = $hook == 'post-new.php' || $hook == 'post.php';
			$settings_screen = 'settings_page_' . $this->get_page_slug() == $hook;
			
			if ( $post_screen || $settings_screen ) :
				
				// Style
				wp_enqueue_style( 'dj-schema-admin', plugins_url('/lib/css/schema-admin.css', __FILE__), array(), DJ_SCHEMACREATOR_VERSION, 'all' );
				
				// Tooltip
				wp_enqueue_script( 'jquery-qtip', plugins_url('/lib/js/jquery.qtip.min.js', __FILE__) , array('jquery'), DJ_SCHEMACREATOR_VERSION, true );
				wp_enqueue_script( 'schema-admin', plugins_url('/lib/js/schema.admin.js', __FILE__) , array('jquery'), DJ_SCHEMACREATOR_VERSION, true );
	
				if ( $post_screen ) :
				
					// Create form
					wp_enqueue_script( 'jquery-ui-core' );
					wp_enqueue_script( 'jquery-ui-datepicker');
					wp_enqueue_script( 'jquery-ui-slider');
					wp_enqueue_script( 'jquery-timepicker', plugins_url( '/lib/js/jquery.timepicker.js', __FILE__) , array( 'jquery' ), DJ_SCHEMACREATOR_VERSION, true );
					wp_enqueue_script( 'format-currency', plugins_url( '/lib/js/jquery.currency.min.js', __FILE__) , array( 'jquery' ), DJ_SCHEMACREATOR_VERSION, true );
					wp_enqueue_script( 'dj-schema-admin-form', plugins_url( '/lib/js/schema.admin.form.js', __FILE__) , array( 'jquery' ), DJ_SCHEMACREATOR_VERSION, true );
					wp_enqueue_script( 'dj-schema-admin-ajax', plugins_url( '/lib/js/schema.admin.ajax.js', __FILE__) , array( 'jquery', 'dj-schema-admin-form' ), DJ_SCHEMACREATOR_VERSION, true );
					
					add_filter( 'mce_external_plugins', array( $this, 'mce_plugin' ) );

					wp_localize_script( 'dj-schema-admin-form', 'schema_i18n', array( 
						'numeric_only' => __( 'No non-numeric characters allowed', 'schema' )
					) );
					wp_localize_script( 'dj-schema-admin-ajax', 'schema_ajax', array( 'nonce' => wp_create_nonce( 'dj_schema_ajax_nonce' ) ) );
					
				endif;
			endif;
		}
	
		/**
		*/
		function mce_plugin( $plugins_array ) {
			$plugins_array['schemaadmineditor'] = plugins_url( '/lib/js/schema-admin-editor/editor_plugin.js', __FILE__);
			return $plugins_array;
		}
	
		/**
		 * Add attribution link to settings page
		 *
		 * @param string $text the current footer text
		 * @return string the new footer text
		 */
		public function admin_footer_attribution( $text ) {
			$current_screen = get_current_screen();
	
			if ( 'settings_page_schema-creator' !== $current_screen->base )
				return $text;
	
			$text = '<span id="footer-thankyou">' . 
				sprintf( __('This plugin brought to you by the fine folks at %s and was based on %s.', 'schema'),
					'<a target="_blank" 
							href="' . esc_url( _x( 'http://derk-jan.com', 'plugin url', 'schema' ) ).'" 
							title="' . esc_attr__( 'Derk-Jan.com | Derk-Jan Karrenbeld', 'schema' )  . '"> '. 
							_x('Derk-Jan Karrenbeld', 'author', 'schema') . '
					</a>',
					sprintf( __( 'Schema Creator by %s', 'schema' ),
						'<a target="_blank" 
							href="' . esc_url( _x( 'http://raventools.com/?utm_source=wp&utm_medium=plugin&utm_campaign=schema', 'plugin url', 'schema' ) ).'" 
							title="' . esc_attr__( 'Internet Marketing Tools for SEO and Social Media', 'schema' )  . '"> '. 
							_x('Raven', 'author', 'schema') . '
						</a>'
					)
				) . 
			'</span>';
	
			return $text;
		}
	
		/**
		 * Load body classes
		 *
		 * Outputs itemtype and itemscope when body classes are generated.
		 *
		 * @param string[] $classes current body classes
		 * @return string[] new body classes
		 */
		public function body_class( $classes ) {
	
			if (is_search() || is_404() )
				return $classes;
	
			$schema_option = $this->get_option( 'body' );
			$bodytag = !isset( $schema_option ) || ( $schema_option === true || $schema_option == 'true' );
	
			// user disabled the tag. so bail.
			if ( $bodytag === false )
				return $classes;
	
			// check for single post disable
			global $post;
			if ( empty($post) )
				return $classes;
	
			$disable_body = get_post_meta( $post->ID, '_schema_disable_body', true );
			if ( $disable_body === true || $disable_body == 'true' || $disable_body == '1' )
				return $classes;
	
			$backtrace = debug_backtrace();
			if ( $backtrace[4]['function'] === 'body_class' )
				echo 'itemtype="http://schema.org/Blog" ';
				echo 'itemscope="" ';
	
			return $classes;
		}
	
		/**
		 * Load front-end CSS if shortcode is present
		 *
		 * @param object[] $posts the posts to display
		 * @return object[] the posts to display
		 */
		public function schema_loader( $posts ) {
	
			// No posts present. nothing more to do here
			if ( empty( $posts ) )
				return $posts;
	
			// they said they didn't want the CSS. their loss.
			$schema_option = $this->get_option( 'css' );
			if( isset( $schema_option ) && ( $schema_option === true || $schema_option == 'true' ) )
				return $posts;
	
	
			// false because we have to search through the posts first
			$found = false;
	
			// search through each post
			foreach ( $posts as $post ) :
				$meta_check	= get_post_meta( $post->ID, '_dj_schema_load', array() );
				//printf( "%d is %s, %s, %s<br>", $post->ID, var_export( $meta_check, true ), var_export( !count( $meta_check ), true ), var_export( !empty( $meta_check[0] ), true ) );
				if ( !count( $meta_check ) ) :
				
					// Check the post content for the short code
					$content = $post->post_content;
					$local_found = false;
					if ( strpos( $content, '[schema' ) !== false ) {
						$found |= true;
						$local_found |= true;
					} 
					update_post_meta( $post->ID, '_dj_schema_load', $local_found );
					
					if ( $local_found ) :
						break;
					endif;
					
				else :
					$found |= !empty( $meta_check[0] );
					if ( $found )
						break;
				endif;
			endforeach;

			// A post has one
			if ( $found == true ) : 
				wp_enqueue_style( 'schema-style', plugins_url( '/lib/css/schema-style.css' , __FILE__ ), array(), DJ_SCHEMACREATOR_VERSION, 'all' );
				
				do_action( 'dj_sc_enqueue_schemapost' );
			endif;

			return $posts;
		}
	
		/**
		 * wrap content in markup
		 *
		 * @param string $content the post content
		 * @return string the proccesed post content
		 */
		public function schema_wrapper( $content ) {
	
			$schema_options = $this->get_options();
			$wrapper = !isset( $dj_schema_options['post']) || ( $dj_schema_options['post'] === true || $dj_schema_options['post'] == 'true' );
	
			// user disabled content wrapper. just return the content as usual
			if ($wrapper === false)
				return $content;
	
			// check for single post disable
			global $post;
			$disable_post = get_post_meta( $post->ID, '_schema_disable_post', true );
			if( $disable_post === true || $disable_post == 'true' || $disable_post == '1' )
				return $content;
	
			// updated content filter to wrap the itemscope
			$content = '<div itemscope itemtype="http://schema.org/BlogPosting">'.$content.'</div>';
	
		// Returns the content.
		return $content;
	
		}

		/**
		 * Gets the i18n translation of the string
		 */
		public function get_i18n( $string ) {
			
			global $schema_scraper_i18n_strings;
			if ( !empty( $schema_scraper_i18n_strings[ addslashes( $string ) ] ) )
				return stripslashes( $schema_scraper_i18n_strings[ addslashes( $string ) ] );
			
			return $string;
		}
		
		/**
		 * Gets the countries and their translated counterparts
		 */
		public function get_countries() {
			$countries = array (
				'US' => __('United States', 'schema'),
				'CA' => __('Canada', 'schema'),
				'MX' => __('Mexico', 'schema'),
				'GB' => __('United Kingdom', 'schema'),
			
				'AF' => __('Afghanistan', 'schema'),
				'AX' => __('Aland Islands', 'schema'),
				'AL' => __('Albania', 'schema'),
				'DZ' => __('Algeria', 'schema'),
				'AS' => __('American Samoa', 'schema'),
				'AD' => __('Andorra', 'schema'),
				'AO' => __('Angola', 'schema'),
				'AI' => __('Anguilla', 'schema'),
				'AQ' => __('Antarctica', 'schema'),
				'AG' => __('Antigua And Barbuda', 'schema'),
				'AR' => __('Argentina', 'schema'),
				'AM' => __('Armenia', 'schema'),
				'AW' => __('Aruba', 'schema'),
				'AU' => __('Australia', 'schema'),
				'AT' => __('Austria', 'schema'),
				'AZ' => __('Azerbaijan', 'schema'),
				'BS' => __('Bahamas', 'schema'),
				'BH' => __('Bahrain', 'schema'),
				'BD' => __('Bangladesh', 'schema'),
				'BB' => __('Barbados', 'schema'),
				'BY' => __('Belarus', 'schema'),
				'BE' => __('Belgium', 'schema'),
				'BZ' => __('Belize', 'schema'),
				'BJ' => __('Benin', 'schema'),
				'BM' => __('Bermuda', 'schema'),
				'BT' => __('Bhutan', 'schema'),
				'BO' => __('Bolivia, Plurinational State Of', 'schema'),
				'BQ' => __('Bonaire, Sint Eustatius And Saba', 'schema'),
				'BA' => __('Bosnia And Herzegovina', 'schema'),
				'BW' => __('Botswana', 'schema'),
				'BV' => __('Bouvet Island', 'schema'),
				'BR' => __('Brazil', 'schema'),
				'IO' => __('British Indian Ocean Territory', 'schema'),
				'BN' => __('Brunei Darussalam', 'schema'),
				'BG' => __('Bulgaria', 'schema'),
				'BF' => __('Burkina Faso', 'schema'),
				'BI' => __('Burundi', 'schema'),
				'KH' => __('Cambodia', 'schema'),
				'CM' => __('Cameroon', 'schema'),
				'CV' => __('Cape Verde', 'schema'),
				'KY' => __('Cayman Islands', 'schema'),
				'CF' => __('Central African Republic', 'schema'),
				'TD' => __('Chad', 'schema'),
				'CL' => __('Chile', 'schema'),
				'CN' => __('China', 'schema'),
				'CX' => __('Christmas Island', 'schema'),
				'CC' => __('Cocos (Keeling) Islands', 'schema'),
				'CO' => __('Colombia', 'schema'),
				'KM' => __('Comoros', 'schema'),
				'CG' => __('Congo', 'schema'),
				'CD' => __('Congo, The Democratic Republic Of The', 'schema'),
				'CK' => __('Cook Islands', 'schema'),
				'CR' => __('Costa Rica', 'schema'),
				'CI' => __('Cote D\'Ivoire', 'schema'),
				'HR' => __('Croatia', 'schema'),
				'CU' => __('Cuba', 'schema'),
				'CW' => __('Curacao', 'schema'),
				'CY' => __('Cyprus', 'schema'),
				'CZ' => __('Czech Republic', 'schema'),
				'DK' => __('Denmark', 'schema'),
				'DJ' => __('Djibouti', 'schema'),
				'DM' => __('Dominica', 'schema'),
				'DO' => __('Dominican Republic', 'schema'),
				'EC' => __('Ecuador', 'schema'),
				'EG' => __('Egypt', 'schema'),
				'SV' => __('El Salvador', 'schema'),
				'GQ' => __('Equatorial Guinea', 'schema'),
				'ER' => __('Eritrea', 'schema'),
				'EE' => __('Estonia', 'schema'),
				'ET' => __('Ethiopia', 'schema'),
				'FK' => __('Falkland Islands (Malvinas)', 'schema'),
				'FO' => __('Faroe Islands', 'schema'),
				'FJ' => __('Fiji', 'schema'),
				'FI' => __('Finland', 'schema'),
				'FR' => __('France', 'schema'),
				'GF' => __('French Guiana', 'schema'),
				'PF' => __('French Polynesia', 'schema'),
				'TF' => __('French Southern Territories', 'schema'),
				'GA' => __('Gabon', 'schema'),
				'GM' => __('Gambia', 'schema'),
				'GE' => __('Georgia', 'schema'),
				'DE' => __('Germany', 'schema'),
				'GH' => __('Ghana', 'schema'),
				'GI' => __('Gibraltar', 'schema'),
				'GR' => __('Greece', 'schema'),
				'GL' => __('Greenland', 'schema'),
				'GD' => __('Grenada', 'schema'),
				'GP' => __('Guadeloupe', 'schema'),
				'GU' => __('Guam', 'schema'),
				'GT' => __('Guatemala', 'schema'),
				'GG' => __('Guernsey', 'schema'),
				'GN' => __('Guinea', 'schema'),
				'GW' => __('Guinea-Bissau', 'schema'),
				'GY' => __('Guyana', 'schema'),
				'HT' => __('Haiti', 'schema'),
				'HM' => __('Heard Island And Mcdonald Islands', 'schema'),
				'VA' => __('Vatican City', 'schema'),
				'HN' => __('Honduras', 'schema'),
				'HK' => __('Hong Kong', 'schema'),
				'HU' => __('Hungary', 'schema'),
				'IS' => __('Iceland', 'schema'),
				'IN' => __('India', 'schema'),
				'ID' => __('Indonesia', 'schema'),
				'IR' => __('Iran', 'schema'),
				'IQ' => __('Iraq', 'schema'),
				'IE' => __('Ireland', 'schema'),
				'IM' => __('Isle Of Man', 'schema'),
				'IL' => __('Israel', 'schema'),
				'IT' => __('Italy', 'schema'),
				'JM' => __('Jamaica', 'schema'),
				'JP' => __('Japan', 'schema'),
				'JE' => __('Jersey', 'schema'),
				'JO' => __('Jordan', 'schema'),
				'KZ' => __('Kazakhstan', 'schema'),
				'KE' => __('Kenya', 'schema'),
				'KI' => __('Kiribati', 'schema'),
				'KP' => __('North Korea', 'schema'),
				'KR' => __('South Korea', 'schema'),
				'KW' => __('Kuwait', 'schema'),
				'KG' => __('Kyrgyzstan', 'schema'),
				'LA' => __('Laos', 'schema'),
				'LV' => __('Latvia', 'schema'),
				'LB' => __('Lebanon', 'schema'),
				'LS' => __('Lesotho', 'schema'),
				'LR' => __('Liberia', 'schema'),
				'LY' => __('Libya', 'schema'),
				'LI' => __('Liechtenstein', 'schema'),
				'LT' => __('Lithuania', 'schema'),
				'LU' => __('Luxembourg', 'schema'),
				'MO' => __('Macao', 'schema'),
				'MK' => __('Macedonia', 'schema'),
				'MG' => __('Madagascar', 'schema'),
				'MW' => __('Malawi', 'schema'),
				'MY' => __('Malaysia', 'schema'),
				'MV' => __('Maldives', 'schema'),
				'ML' => __('Mali', 'schema'),
				'MT' => __('Malta', 'schema'),
				'MH' => __('Marshall Islands', 'schema'),
				'MQ' => __('Martinique', 'schema'),
				'MR' => __('Mauritania', 'schema'),
				'MU' => __('Mauritius', 'schema'),
				'YT' => __('Mayotte', 'schema'),
				'FM' => __('Micronesia', 'schema'),
				'MD' => __('Moldova', 'schema'),
				'MC' => __('Monaco', 'schema'),
				'MN' => __('Mongolia', 'schema'),
				'ME' => __('Montenegro', 'schema'),
				'MS' => __('Montserrat', 'schema'),
				'MA' => __('Morocco', 'schema'),
				'MZ' => __('Mozambique', 'schema'),
				'MM' => __('Myanmar', 'schema'),
				'NA' => __('Namibia', 'schema'),
				'NR' => __('Nauru', 'schema'),
				'NP' => __('Nepal', 'schema'),
				'NL' => __('Netherlands', 'schema'),
				'NC' => __('New Caledonia', 'schema'),
				'NZ' => __('New Zealand', 'schema'),
				'NI' => __('Nicaragua', 'schema'),
				'NE' => __('Niger', 'schema'),
				'NG' => __('Nigeria', 'schema'),
				'NU' => __('Niue', 'schema'),
				'NF' => __('Norfolk Island', 'schema'),
				'MP' => __('Northern Mariana Islands', 'schema'),
				'NO' => __('Norway', 'schema'),
				'OM' => __('Oman', 'schema'),
				'PK' => __('Pakistan', 'schema'),
				'PW' => __('Palau', 'schema'),
				'PS' => __('Palestine', 'schema'),
				'PA' => __('Panama', 'schema'),
				'PG' => __('Papua New Guinea', 'schema'),
				'PY' => __('Paraguay', 'schema'),
				'PE' => __('Peru', 'schema'),
				'PH' => __('Philippines', 'schema'),
				'PN' => __('Pitcairn', 'schema'),
				'PL' => __('Poland', 'schema'),
				'PT' => __('Portugal', 'schema'),
				'PR' => __('Puerto Rico', 'schema'),
				'QA' => __('Qatar', 'schema'),
				'RE' => __('Reunion', 'schema'),
				'RO' => __('Romania', 'schema'),
				'RU' => __('Russian Federation', 'schema'),
				'RW' => __('Rwanda', 'schema'),
				'BL' => __('St. Barthelemy', 'schema'),
				'SH' => __('St. Helena', 'schema'),
				'KN' => __('St. Kitts And Nevis', 'schema'),
				'LC' => __('St. Lucia', 'schema'),
				'MF' => __('St. Martin (French Part)', 'schema'),
				'PM' => __('St. Pierre And Miquelon', 'schema'),
				'VC' => __('St. Vincent And The Grenadines', 'schema'),
				'WS' => __('Samoa', 'schema'),
				'SM' => __('San Marino', 'schema'),
				'ST' => __('Sao Tome And Principe', 'schema'),
				'SA' => __('Saudi Arabia', 'schema'),
				'SN' => __('Senegal', 'schema'),
				'RS' => __('Serbia', 'schema'),
				'SC' => __('Seychelles', 'schema'),
				'SL' => __('Sierra Leone', 'schema'),
				'SG' => __('Singapore', 'schema'),
				'SX' => __('Sint Maarten (Dutch Part)', 'schema'),
				'SK' => __('Slovakia', 'schema'),
				'SI' => __('Slovenia', 'schema'),
				'SB' => __('Solomon Islands', 'schema'),
				'SO' => __('Somalia', 'schema'),
				'ZA' => __('South Africa', 'schema'),
				'GS' => __('South Georgia', 'schema'),
				'SS' => __('South Sudan', 'schema'),
				'ES' => __('Spain', 'schema'),
				'LK' => __('Sri Lanka', 'schema'),
				'SD' => __('Sudan', 'schema'),
				'SR' => __('Suriname', 'schema'),
				'SJ' => __('Svalbard', 'schema'),
				'SZ' => __('Swaziland', 'schema'),
				'SE' => __('Sweden', 'schema'),
				'CH' => __('Switzerland', 'schema'),
				'SY' => __('Syria', 'schema'),
				'TW' => __('Taiwan', 'schema'),
				'TJ' => __('Tajikistan', 'schema'),
				'TZ' => __('Tanzania', 'schema'),
				'TH' => __('Thailand', 'schema'),
				'TL' => __('Timor-Leste', 'schema'),
				'TG' => __('Togo', 'schema'),
				'TK' => __('Tokelau', 'schema'),
				'TO' => __('Tonga', 'schema'),
				'TT' => __('Trinidad And Tobago', 'schema'),
				'TN' => __('Tunisia', 'schema'),
				'TR' => __('Turkey', 'schema'),
				'TM' => __('Turkmenistan', 'schema'),
				'TC' => __('Turks And Caicos Islands', 'schema'),
				'TV' => __('Tuvalu', 'schema'),
				'UG' => __('Uganda', 'schema'),
				'UA' => __('Ukraine', 'schema'),
				'AE' => __('United Arab Emirates', 'schema'),
				'UM' => __('United States Minor Outlying Islands', 'schema'),
				'UY' => __('Uruguay', 'schema'),
				'UZ' => __('Uzbekistan', 'schema'),
				'VU' => __('Vanuatu', 'schema'),
				'VE' => __('Venezuela', 'schema'),
				'VN' => __('Vietnam', 'schema'),
				'VG' => __('British Virgin Islands ', 'schema'),
				'VI' => __('U.S. Virgin Islands ', 'schema'),
				'WF' => __('Wallis And Futuna', 'schema'),
				'EH' => __('Western Sahara', 'schema'),
				'YE' => __('Yemen', 'schema'),
				'ZM' => __('Zambia', 'schema'),
				'ZW' => __('Zimbabwe', 'schema')
			);
			// sort alphabetical with translated names
			asort($countries);
			return $coutries;
		}
		
		/*public function get_countries_input( $_argument = '', $ajax = true ) {
			<label for="schema_country"><?php _e('Country', 'schema'); ?></label>
            <select name="schema_country" id="schema_country" class="schema_drop schema_thindrop">
                <option class="holder" value="none">(<?php _e('Select a Country', 'schema'); ?>)</option>
                <option value="US"><?php _e('United States', 'schema'); ?></option>
                <option value="CA"><?php _e('Canada', 'schema'); ?></option>
                <option value="MX"><?php _e('Mexico', 'schema'); ?></option>
                <option value="GB"><?php _e('United Kingdom', 'schema'); ?></option>
                <?php
                $countries = $this->get_countries();
                unset($countries["US"]);
                unset($countries["CA"]);
                unset($countries["MX"]);
                unset($countries["GB"]);
                // set array of each item
                foreach ($countries as $country_key => $country_name) {
                    echo "\n\t<option value='{$country_key}'>{$country_name}</option>";
                }
                ?>
            </select>
         }*/
		

		/**
		 * Gets the schema datatypes

		 *
		 * @returns JSON encoded array datatypes



		 */
		public function get_schema_datatypes( $_argument = '', $ajax = true ) {
			
			if ( $ajax ) :
				$this->do_ajax();
				check_ajax_referer( 'dj_schema_ajax_nonce', 'security' );
			endif;
			
			$prefix = isset( $_REQUEST['prefix'] ) && !empty( $_REQUEST['prefix'] ) ? $_REQUEST['prefix'] : '';
			$scraper = $this->get_scraper();
		
			// Get datatypes
			$datatypes = array();
			foreach( $scraper->get_datatypes() as $datatype ) {
				$datatypes[ $prefix . $scraper->get_datatype_id( $datatype ) ] = array( 
					'id' => $scraper->get_datatype_id( $datatype ), 
					'label' => $scraper->get_datatype_label( $datatype ), 
					'subtypes' => $scraper->get_datatype_descendants( $datatype, true, true ), 
					'desc' => $this->get_i18n( htmlentities( $scraper->get_datatype_comment( $datatype ) ) ),
					'button' => $this->get_i18n( $scraper->get_datatype_label( $datatype ) ),
				);
			}
			
			$results = array( 'datatypes' => $datatypes );
			
			if ( $ajax ) :
				echo json_encode( $results );
				exit;
			endif;
			
			return $results;
		}
	
			/**
		 * Gets the schema types
		 *
		 * @returns JSON encoded array of siblings, parents, children and select type of a type
		 */
		public function get_schema_types( $_argument = '', $ajax = true ) {
			
			if ( $ajax ) :
				$this->do_ajax();
				check_ajax_referer( 'dj_schema_ajax_nonce', 'security' );
			endif;
			
			$scraper = $this->get_scraper();
			$children = array();
			$siblings = array();
			$parents = array();
			$starred = $this->get_option( 'starred_schemas' ) ?: 
				array( 'Person', 'Product', 'Event', 'Organization', 'Movie', 'Book', 'Review', 'Recipe' );
			
			// Get selected schema
			$top_level = $scraper->get_top_level_schemas();
			$type = isset( $_REQUEST[ 'type' ] ) ? $_REQUEST[ 'type' ] : array_shift( $top_level );
			$schema = $scraper->get_schema( $type );
			
			$allow_parents = isset( $_REQUEST[ 'parents' ] ) ? $_REQUEST[ 'parents' ] : true;
			$allow_siblings = isset( $_REQUEST[ 'siblings' ] ) ? $_REQUEST[ 'siblings' ] : true;
			$allow_children = isset( $_REQUEST[ 'children' ] ) ? $_REQUEST[ 'children' ] : true;
			$allow_starred = isset( $_REQUEST[ 'starred'] )  ? $_REQUEST[ 'starred' ] : true; 
			
			if ( empty( $schema ) ) $type = array_shift( $top_level );
			
			// Get descendants
			if ( $allow_children ) :
				foreach( $scraper->get_schema_descendants( $type, false ) as $child )
					$children[]= array( 'id' => $scraper->get_schema_id( $child ) );
			endif;
				
			// Get siblings
			if ( $allow_siblings ) :
				foreach( $scraper->get_schema_siblings( $type ) as $sibling )
					$siblings[]= array( 'id' => $scraper->get_schema_id( $sibling ) );
			endif;
				
			// Get ancestors
			if ( $allow_parents ) :
				foreach( $scraper->get_schema_ancestors( $type, false) as $parent )
					$parents[]= array( 'id' => $scraper->get_schema_id( $parent ) ); 
			endif;
			// Get starred
			if ( $allow_starred ) :
				foreach( $starred as &$star )
					$star = array( 'id' => $scraper->get_schema_id( $star ) );
			else :
				$starred = '';
			endif;
			
			$results = array( 
				'types' => array(
					'' => array( 
						array( 
							'id' => $type, 
							'desc' => $this->get_i18n( htmlentities( $scraper->get_schema_comment( $type ) ) ) 
						) 
					),
					esc_attr__( 'Children', 'schema' ) => $children,
					esc_attr__( 'Siblings', 'schema' ) => $siblings,
					esc_attr__( 'Parents', 'schema' ) => $parents,
					esc_attr__( 'Starred', 'schema' ) => $starred,
				),
			) ;
					
			if ( $ajax ) :
				echo json_encode( $results );
				exit;
			endif;
			
			return $results;
		}
		
		/**
		 * Gets the properties of a schema
		 *
		 * @ajax set to false to return insteaf of encode
		 * @returns encoded json or array 
		 */
		function get_schema_properties( $_argument = '', $ajax = true ) {
			if ( $ajax ) :
				$this->do_ajax();
				check_ajax_referer( 'dj_schema_ajax_nonce', 'security' );
			endif;
			
			$properties = array();
			
			$scraper = $this->get_scraper();
			$schema = $scraper->get_schema( $_REQUEST['type'] );
						
			foreach( $scraper->get_schema_properties( $schema, true ) as $type => $t_properties )  :
				$type_id = $scraper->get_schema_id( $type );
				$properties[ $type_id ] = array();
				foreach( $t_properties as $t_property ) :
					$t_comment = htmlentities( $scraper->get_property_comment( $t_property ) );
					if ( strpos( $t_comment, 'legacy spelling' ) !== false )
						continue;

					array_push( $properties[ $type_id ], 
						array(
							'id' => $scraper->get_property_id( $t_property ),
							'label' => $scraper->get_property_label( $t_property ),
							'desc' => $this->get_i18n( $t_comment ),
							'ranges' => $scraper->get_property_ranges( $t_property ),
						)
					);
				endforeach;
			endforeach;
			
			$results = array( 
				'properties' => $properties,
			);
			
			if ( $ajax ) :
				echo json_encode( $results );
				exit;
			endif;
			
			return $results;
		}
		
		// 
		
		/** 
		 * Gets the scraper class
		 *
		 * @return self the scraper singleton instance
		 */
		public function get_scraper() {
			return DJ_SchemaScraper::singleton();	
		}
		
		/**
		 * Build out shortcode with variable array of options
		 *
		 * @return string the replacement
		 */
		public function shortcode( $atts, $content = NULL ) 
		{
			$attributes = shortcode_atts( 
				array( 
					'type' => '' ,
					'class' => 'schema',
					'embed_class' => 'schema',
					'id' => '',
					'style' => '',
				), $atts );
				
			extract( $attributes );
				
			$scraper = $this->get_scraper();
			$schema = $scraper->get_schema( $type );
			$class = array_merge( explode(' ', $class ), array( strtolower( 'schema-'.$type ) ) );
			$id = !empty( $id ) ? 'id="' . esc_attr( $id ) . '" ' : '';
			$style = !empty( $style ) ? 'style="'. esc_attr( $style ) . '" ' : '';
			
			// wrap schema build out
			$sc_build = '<div ' . $id . $style . 'class="'. esc_attr( implode( ' ', $class ) ) . '" itemscope itemtype="' . esc_attr( esc_url( $scraper->get_schema_url( $schema ) ) ) . '">';
			$sc_build .= $this->shortcode_recursive( $content ?: '', $embed_class );
			$sc_build .= '</div>';

			// Remove all empty paragraphs ( WordPress adds these in filters after WYSIWYG )
			//$sc_build = preg_replace('{(<p([^>]*)?(/\>|\></p>))+}i', '', $sc_build);
			
			// return entire build array
			return $sc_build;
		}
		
		/**
		 * Outputs shortcode recursively
		 *
		 * @param string $content content to process
		 * @param string $embed_class class to style embeds with
		 * @return string processed content
		 */
		public function shortcode_recursive( $content, $embed_class = '' ) {
			
			$matches = array();
			$sc_build = '';

			// Remove all breaks (sorry, they cause problems!)
			$content = preg_replace('{(<br([^>]*/)?\>|&nbsp;)+}i', '', $content);
			
			// Matches 
			$pattern = "/\[(?P<type>scprop|scmbed|scmeta|schtml)(?P<props>[^\]]*?)((?P<tagclose>[\s]*\/\])|".
			"(](?P<inner>(([^\[]*?|\[\!\-\-.*?\-\-\])|(?R))*)\[\/\\1[\s]*\]))/sm";
			if ( !preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) )
				return $content;
				
			if ( $this->debug )
				printf( '<h2>Entered shortcode_recursive</h2>' );

			$elements = array();
			foreach ( $matches[0] as $key => $match ) {
				array_push( $elements, (object)array(
					'node' => $match[0],
					'type' => $matches['type'][$key][0],
					'attributes_raw' => !empty( $matches['props'][$key][0] ) ? $matches['props'][$key][0] : '',
					'attributes' => array(),
					'no_inner' => (boolean)($matches['tagclose'][$key][1] > -1),
					'inner_content' => !empty( $matches['inner'][$key][0] ) ? $matches['inner'][$key][0] : ''
				) );

				if ( $this->debug )
					printf( '<br>Processing element: <code>%s</code><br>', $match[0] );

				// Remove the match from the contents
				foreach(  array( $match[0], trim( $match[0] ) ) as $needle) :
					if ( empty( $needle ) )
						break;
					$pos = strpos( $content, $needle );
					
					if ( $this->debug ) :
						printf( 'Looking for [elem]: <code>%s</code> and <b>pos is %s</b><br>', 
							var_export( $needle, true ), 
							var_export( $pos, true ) 
						);
					endif;
					
					if ( $pos !== false ) :
						$content = substr_replace( $content, '', $pos, strlen( $needle ) );
						
						if ( $this->debug )
							printf( 'After replacement: <code>%s</code><br>', $content );
						break;
					endif;
				endforeach;
				
				// Is there non schema data after this element? Output!
				$nosc_matches = array();
				if ( preg_match( '/^(?P<inner>[^\[]*)/sm', $content, $nosc_matches ) ) :
					array_push( $elements, (object)array(	
						'node' => $nosc_matches[0],
						'type' => 'nosc',
						'inner_content' => $nosc_matches['inner'],
						'no_inner' => false
					) );
					
					// Remove the match from the contents
					foreach(  array( $nosc_mathces[0], trim( $nosc_matches[0] ) ) as $needle) :
						if ( empty( $needle ) )
							break;
						$pos = strpos( $content, $needle );
						
						if ( $this->debug ) :
							printf( 'Looking for [non]: %s and pos is %s<br>', 
								var_export( $needle, true ), 
								var_export( $pos, true ) 
							);
						endif;
						
						if ( $pos !== false ) :
							$content = substr_replace( $content, '', $pos, strlen( $needle ) );
							break;
						endif;
					endforeach;
				endif;
			}

			// The content left was not in this element. But we are doing this recursively
			// so it will be outputted by the overlaying element.
			if ( $this->debug ) :
				if ( !empty( $content ) ) :
					printf( 'Content left is: %s<br>', var_export( $content, true ) );
				endif;
			endif;

			// Get defaults
			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );
			$datetime_format = $date_format . ' ' . $time_format;
			
			// Get scraper
			$scraper = $this->get_scraper();
			
			while( ( $element = array_shift( $elements ) ) ) :
			
				// Early bail if not schema content
				if ( $element->type == 'nosc' ) :
					$sc_build .= $element->inner_content;
					continue;
				endif;
			
				///////////////////////// ///////////////////////// /////////////////////////
				// Readout properties or bail early
				///////////////////////// ///////////////////////// /////////////////////////
				$pattern = '/\s*(?P<key>[^\s=\'"]+)(?:=(?:([\'"])(?P<value>[^\'"]+)[\'"])|\s|$)\s*/sm';
				if ( !preg_match_all( $pattern, $element->attributes_raw, $matches, PREG_OFFSET_CAPTURE ) ) :
					$sc_build .= $element->inner_content;
					continue;
				endif;
					
				// Save attributes
				foreach ( $matches[0] as $key => $match )
					$element->attributes[ $matches['key'][$key][0] ] = $matches['value'][$key][0];
				
				// These attributes are always allowed as html attributes
				$insulate = array();
				foreach( array ( 'id', 'class', 'title', 'alt', 'style', 'onClick' ) as $attr )
					if ( !empty( $element->attributes[ $attr ] ) )
						$insulate[ $attr ] = $element->attributes[ $attr ];
						
				// These tags can encapsulate the property
				$encapsulate = "%s";
				$encapsulate_allowed = array( 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'div', 'ul', 
					'fieldset', 'legend', 'nav', 'header', 'footer', 'section', 'aside', 'ol', 'dt', 'dl',
					'p', 'em', 'strong', 'blockquote', 'form' );
				foreach( array_keys( $element->attributes ) as $tag )
					if ( in_array( $tag, $encapsulate_allowed ) )
						$encapsulate = sprintf( '<%1$s>%2$s</%1$s>', $tag,  $encapsulate );
				
				///////////////////////// ///////////////////////// /////////////////////////
				// Recursive proccessing
				///////////////////////// ///////////////////////// /////////////////////////
				if ( !$element->no_inner ) :

					if ( $element->type == 'scprop' ) :
						// No value? The inner content must be the value
						if ( !isset( $element->attributes[ 'value' ] ) ) :
							$element->attributes[ 'value' ] = $element->inner_content;
							$element->inner_content = '';
							$element->no_inner = true;
						endif;
					endif;
					
					// Parse inner contents (TODO: tail-recursive?)
					if ( !empty( $element->inner_content ) )
						$element->inner_content = $this->shortcode_recursive( $element->inner_content );
				endif;
				
				///////////////////////// ///////////////////////// /////////////////////////
				// HTML processing
				///////////////////////// ///////////////////////// /////////////////////////
				if ( $element->type == 'schtml' ) :
					
					if ( empty( $element->attributes ) ) :
						
						// Just inner content
						$sc_build .= $element->inner_content;
						
					else :
					
						// Inner content in tags
						$encapsulate = preg_replace( '/^\<([^\>]+)\>(.*)$/', '<${1} %s>${2}', $encapsulate );
						$sc_build .= sprintf( $encapsulate, 
							$this->shortcode_build_attributes( $insulate ),
							$element->inner_content
						);
						
					endif;
					continue;
									
				endif;

				///////////////////////// ///////////////////////// /////////////////////////
				//  Fallbacks for faulty use of properties
				///////////////////////// ///////////////////////// /////////////////////////
				if ( $element->type != 'scmbed' ) :
					
					// Default range please. That's text!
					if ( empty( $element->attributes[ 'range' ] ) )
						$element->attributes[ 'range' ] = 'sc_Text';	
						
					// No property defined? We can't process this, so just do inner				
					if ( empty( $element->attributes[ 'prop' ] ) ) :
						$sc_build .= $element->inner_content;
						continue;
					endif;
					
					if ( $element->type == 'scmeta' ) :
						// No content defined? Maybe you typed it as value
						if ( !isset( $element->attributes[ 'content' ] ) ) :
							$element->attributes[ 'content' ] = $element->attributes[ 'value' ];
							$element->attributes[ 'value' ] = '';
						endif;
					endif;
					
				else :
				
					// No embed property? Maybe you typed it as prop
					if ( !isset( $element->attributes[ 'embed' ] ) ) :
						$element->attributes[ 'embed' ] = $element->attributes[ 'prop' ];
						$element->attributes[ 'prop' ] = '';
					endif;
					
					// No embed type? Maybe you typed it as type
					if ( !isset( $element->attributes[ 'value' ] ) ) :
						$element->attributes[ 'value' ] = $element->attributes[ 'type' ];
						$element->attributes[ 'value' ] = '';
					endif;
					
				endif;

				///////////////////////// ///////////////////////// /////////////////////////
				// Property type override
				///////////////////////// ///////////////////////// /////////////////////////
				
				// The tagtype will tell what kind of element we are dealing with
				// The insulate array contains all the properties
				
				// Switch by property type (name)
				switch( $element->attributes[ 'prop' ] ) :
					
					// Images
					case 'image' :
					case 'photo' :
						$tagtype = 'img';
						$insulate[ 'src' ] = esc_url( $element->attributes[ 'value' ] );
						$element->attributes[ 'range' ] = 'sc_Image';
					break;
					
					// Url ( always in conjunction with name or family/given name )
					case 'url' :
						if ( !$element->no_inner ) :
							$format = '<span itemprop="name" class="name name-sc_link">%s</span>';
							$element->inner_content = sprintf( $format, $element->inner_content );
						endif;								
						break;
					break;
					
					// Paragraphs
					case 'description' :
						$tagtype = 'p';
						$element->attributes[ 'range' ] = 'sc_Paragraph';
					break;
					
					
				endswitch;

				// Switch by property value type (range)
				switch( $element->attributes[ 'range' ] ) :
				
					// Displays as <a>
					case 'sc_Link':
					case 'sc_URL':
						$tagtype = 'a';
						$insulate[ 'href' ] = esc_url( $element->attributes[ 'value' ] );
						break;
					
					// Displays as <img>	
					case 'sc_Image':
						break;
						
					// Displays as <time>. Uses date format if no inner content.	
					case 'sc_Date' :
						$tagtype = 'time';
						$insulate[ 'datetime' ] = esc_attr( $element->attributes[ 'value' ] );
						if ( $element->no_inner )
							$element->inner_content = date_i18n( !empty( $element->attributes[ 'format' ] ) ?
								$element->attributes[ 'format' ]  : $datetime_format, 
								strtotime( $element->attributes[ 'value' ] )
							);
						break;
						
					// For text and paragraphs, the inner content is the value
					case 'sc_Text' :
						$tagtype = 'span';
						
					case 'sc_Paragraph':
						$element->inner_content = $element->attributes[ 'value' ];
						break;
						
						
					// We don't know what todo. So default
					default: 
						$tagtype = 'div';
						$element->inner_content = '[' .
							$element->attributes[ 'prop' ] . ': ' .
							$element->attributes[ 'value' ] . '] ' .
							$element->inner_content . '[/' . $element->attributes[ 'prop' ] . ']
						';
						break;
				endswitch;
						
				///////////////////////// ///////////////////////// /////////////////////////
				// Start output
				///////////////////////// ///////////////////////// /////////////////////////
				switch( $element->type ) :
					
					// Actual output of properties
					case 'scprop' :
					
						// Build class for this element
						$prop_class = strtolower(  $element->attributes[ 'prop' ] );
						$range_class = strtolower( $element->attributes[ 'range' ] );
						$insulate[ 'class' ] = trim( 
							implode( ' ', 
								array_merge( 
									explode( ' ' , $prop_class ),
									explode( ' ' , $range_class ),  
									explode( ' ' , $prop_class . '-' . $range_class ),  
									( empty( $insulate[ 'class' ] ) ? array() : 
										explode( ' ', $insulate[ 'class' ] )
									)
								)
							)
						);

						// Output
						$format = '<%1$s %2$sitemprop="%3$s">%4$s</%1$s>';
						$sc_build .= sprintf( $encapsulate, 
							sprintf( '%s%s%s' , 
								$element->attributes[ 'before' ], 
								sprintf( $format,
									!empty( $element->attributes[ 'as' ] ) ? $element->attributes[ 'as' ] : $tagtype, 
									$this->shortcode_build_attributes( $insulate ), 
									$element->attributes[ 'prop' ],
									$element->inner_content 
								),
								$element->attributes[ 'after' ]
							)
						);
						
					break;
					
					// Actual output of meta properties
					case 'scmeta' :
						
						// Output
						$format = '<%1$s %2$sitemprop="%3$s" content="%4$s"/>%5$s';
						$sc_build .= sprintf( $encapsulate, 
							sprintf( '%s%s%s', 
								$element->attributes[ 'before' ], 
								sprintf( $format,
									!empty( $element->attributes[ 'as' ] ) ? $element->attributes[ 'as' ] : 'meta', 
									$this->shortcode_build_attributes( $insulate ), 
									$element->attributes[ 'prop' ],
									$element->attributes[ 'content' ],
									$element->inner_content 
								), 
								$element->attributes[ 'after' ]
							)
						);
					break;		
					
					// Actual output of embedded items	
					case 'scmbed' :
						
						// Fetch schema data
						$embed_type  = $element->attributes[ 'value' ];
						$embed_schema = $scraper->get_schema( $embed_type );
						$insulate[ 'class' ] = trim( 
							implode( ' ', 
								array_merge( 
									explode( ' ' , $embed_class ),
									array( 'schema-embed' ),
									array( strtolower( 'schema-'.$embed_type ) ),
									( empty( $insulate[ 'class' ] ) ? array() : 
										explode( ' ', $insulate[ 'class' ] )
									)
								)
							)
						);
												
						// Output
						$format = '<%1$s %2$sitemprop="%3$s" itemscope itemtype="%5$s">%4$s</%1$s>';
						$sc_build .= sprintf( $encapsulate, 
							sprintf( '%s%s%s' , 
								$element->attributes[ 'before' ], 
								sprintf( $format,
									!empty( $element->attributes[ 'as' ] ) ? $element->attributes[ 'as' ] : 'div', 
									$this->shortcode_build_attributes( $insulate ), 
									$element->attributes[ 'embed' ],
									$element->inner_content,
									esc_attr( esc_url( $scraper->get_schema_url( $embed_schema ) ) )
								) ,
								$element->attributes[ 'after' ]
							)
						);
					break;		
					
				endswitch;
							
			endwhile;
			
			return $sc_build;
		}
	
		/**
		 * Builds attributes for the shortcode processing
		 *
		 * @param string[] $insulate key value array with attributes
		 * @return string attributes string
		 */
		public function shortcode_build_attributes( $insulate ) {
			foreach ( $insulate as $key => &$value )
				$value = sprintf( '%s="%s" ', $key, esc_attr( $value ) );
			return implode( '', $insulate );
		}
	
		/**
		 * Add button to top level media row
		 */
		public function schema_media_button() {
	
			// don't show on dashboard (QuickPress)
			$current_screen = get_current_screen();
			if ( 'dashboard' == $current_screen->base )
				return;

	
			// don't display button for users who don't have access
			if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
				return;
	
			// do a version check for the new 3.5 UI
			$version	= get_bloginfo('version');
	
			if ($version < 3.5) {
				// show button for v 3.4 and below
				echo '<a href="#TB_inline?width=650&inlineId=schema_build_form" class="thickbox schema_clear schema_one" id="add_schema" title="' . __('Schema Creator Form') . '">' .
					__( 'Schema Creator Form', 'schema' ) .
				'</a>';
			} else {
				// display button matching new UI
				$img = '<span class="schema-media-icon"></span> ';
				echo '<a href="#TB_inline?width=650&inlineId=schema_build_form" class="thickbox schema_clear schema_two button" id="add_schema" title="' . esc_attr__( 'Add Schema' ) . '">' .
					$img . __( 'Add Schema', 'schema' ) . 
				'</a>';
			}
	
		}
	
		/**
		 * Build form and add into footer
		 */
		public function schema_media_form() {
	
			// don't load form on non-editing pages
			$current_screen = get_current_screen();
			if ( 'post' !== $current_screen->base )
				return;
	
			// don't display form for users who don't have access
			if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
				return;
			
			// Get the scraper, schema and its properties
			$scraper = $this->get_scraper();
			?>
	
			<div id="schema_build_form" style="display:none;">
				<div id="schema_builder" class="schema_wrap">
                	<ul id="sc_breadcrumbs"><li id="sc_bc_root">root</li></ul>
					<!-- schema type dropdown -->
					<div id="sc_type">						
						<label for="schema_type"><?php _e('Schema Type', 'schema'); ?></label>
						<select name="schema_type" id="schema_type" class="schema_drop schema_thindrop">
							<option class="holder" value="">(<?php _e('Select a Type', 'schema'); ?>)</option>    
						</select>
                        <input type="button" id="schema_type_use" class="button button-primary" value="<?php _e( 'Use selected', 'schema'); ?>"/>
                    </div>
                    <div id="sc_type_description" class="sc_desc">
                        <label><?php _e('Schema Descripton', 'schema'); ?></label>
                        <span id="schema_type_description" class="fullwidth"></span>
					</div>
					<!-- end schema type dropdown -->
                    
                    <input type="hidden" id="schema_display" value="sc_properties" />
                    <div id="sc_properties">
          			
                    </div>
                    
                    <div id="sc_embeds">
                    
                    </div>
                    
					<!-- button for inserting -->
					<div class="insert_button">
						<input id="sc_insert_button" class="schema_insert button button-primary" type="button" value="<?php _e('Insert'); ?>"/>
						<input id="sc_cancel_button" class="schema_cancel button" type="button" value="<?php _e('Cancel'); ?>"/>
					</div>
			
					<!-- various messages -->
					<div id="sc_messages">
						<p class="start"><?php _e( 'Select a schema type above to get started', 'schema' ); ?></p>
                        <p class="loading loading_types" style="display:none"><?php _e( 'Retrieving schema subtree and information...', 'schema' ); ?></p>
                        <p class="loading loading_properties" style="display:none"><?php _e( 'Retrieving schema properties...', 'schema' ); ?></p>
					</div>
			
				</div>
			</div>
		<?php 
		}
		
		// "This will intercept the version check and increment the current version number by 3.
		// It's the quick and dirty way to do it without messing with the settings directly..."
		function my_refresh_mce($ver) {
		  $ver += 3;
		  return $ver;
		}

	/// end class
	}
	
	// Instantiate our class
	$DJ_SchemaCreator = DJ_SchemaCreator::singleton();
endif;
// Include modules
foreach ( glob( plugin_dir_path(__FILE__) . "/lib/*.php" ) as $filename )
    include_once $filename;