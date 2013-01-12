<?php
/*
Plugin Name: Schema Creator by Raven
Plugin URI: http://schema-creator.org/?utm_source=wp&utm_medium=plugin&utm_campaign=schema
Description: Insert schema.org microdata into posts and pages
Version: 1.042
Author: Raven Internet Marketing Tools
Author URI: http://raventools.com/?utm_source=wp&utm_medium=plugin&utm_campaign=schema
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
	raven_sc_register_settings	: runs when the settings are registered
	raven_sc_default_settings	: runs when plugin is activated
	raven_sc_options_validate	: runs when the settings are saved ( &array )
	raven_sc_options_form		: runs when the settings form is outputted
	raven_sc_metabox			: runs when the metabox is outputted
	raven_sc_save_metabox		: runs when the metabox is saved
	raven_sc_enqueue_schemapost	: runs when showing a post with a schema
	
Filters:
	raven_sc_default_settings	: gets default settings values
	raven_sc_admin_tooltip		: gets the tooltips for admin pages
*/

if ( !class_exists( "RavenSchema" ) ) :

	define('SC_BASE', plugin_basename(__FILE__) );
	define('SC_VER', '1.042');

	class RavenSchema
	{
		/**
		 * This is our constructor
		 *
		 * @return ravenSchema
		 */
		public function __construct() {		
			// Text domain
			add_action( 'plugins_loaded', array( &$this, 'plugin_textdomain' ) );
			
			// Edit Post Page ( Metabox/Media button )
			add_action( 'the_posts', array( &$this, 'schema_loader' ) );
			add_action( 'do_meta_boxes', array( &$this, 'metabox_schema' ), 10, 2 );
			add_action( 'save_post', array( &$this, 'save_metabox' ) );
			add_filter( 'media_buttons', array( &$this, 'schema_media_button' ), 31 );
			add_action( 'admin_footer',	array( &$this, 'schema_media_form'	) );

			// Plugins page
			add_filter( 'plugin_action_links', array( &$this, 'quick_link' ), 10, 2 );
			
			// Settings Page
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_scripts' ) );
			add_action( 'admin_menu', array( &$this, 'add_pages' )	);
			add_action( 'admin_init', array( &$this, 'register_settings' ) );
			add_filter( 'raven_sc_default_settings', array( &$this, 'get_default_settings' ) );
			add_filter( 'raven_sc_admin_tooltip', array( &$this, 'get_tooltips' ) );
			add_filter( 'admin_footer_text', array( &$this, 'admin_footer_attribution' ) );
			register_activation_hook( __FILE__, array( &$this, 'default_settings' ) );
			
			// Admin bar
			add_action( 'admin_bar_menu', array( &$this, 'admin_bar_schema_test' ), 9999 );
			
			// Content
			add_filter( 'body_class', array( &$this, 'body_class' ) );
			add_filter( 'the_content', array( &$this, 'schema_wrapper' ) );
			
			add_shortcode( 'schema', array( &$this, 'shortcode' ) );
			
			// Ajax actions
			add_action( 'wp_ajax_get_schema_types', array( &$this, 'get_schema_types' ) );
		}
		
	
		/**
		 * Load textdomain for international goodness
		 */
		public function plugin_textdomain() {
			load_plugin_textdomain( 'schema', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	
		/**
		 * Show settings link on plugins page
		 *
		 * @return modified links
		 */
		public function quick_link( $links, $file ) {
	
			// Check to make sure we are on the correct plugin
			if ($file === plugin_basename(__FILE__)) {
				$settings_link	= '<a href="' . menu_page_url( 'schema-creator', 0 ) . '">' . _x( 'Settings', 'link to page', 'schema' ) . '</a>';
				array_unshift($links, $settings_link);
			}
	
			return $links;
		}
	
		/**
		 * Add link to admin toolbar for testing
		 */
		public function admin_bar_schema_test( $wp_admin_bar ) {
			// No link on admin panel, only load on singles
			if ( is_admin() || !is_singular() )
				return;
	
			//get some variables
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
		 * Display metabox
		 */
		public function metabox_schema( $page, $context ) {
			// only add on side
			if ('side' != $context)
				return;
				
			// check to see if they have options first
			$schema_options	= get_option('schema_options');
	
			// they haven't enabled this? THEN YOU LEAVE NOW
			if( empty( $schema_options['body'] ) && empty( $schema_options['post'] ) )
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
				add_meta_box( 'schema-post-box', __( 'Schema Display Options', 'schema' ), array( &$this, 'schema_post_box' ), $page, $context, 'high' );
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
			wp_nonce_field( SC_BASE, 'schema_nonce' );
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
			
			do_action( 'raven_sc_metabox' );
		}
	
		/**
		 * Save the data
		 */
		public function save_metabox( $post_id = 0 )
		{	
			$post_id = (int)$post_id;
			$post_status = get_post_status( $post_id );
	
			if ( "auto-draft" == $post_status ) 
				return $post_id;
	
			if ( isset( $_POST['schema_nonce'] ) && !wp_verify_nonce( $_POST['schema_nonce'], SC_BASE ) )
				return;
	
			if ( !current_user_can( 'edit_post', $post_id ) )
				return;
	
			// OK, we're authenticated: we need to find and save the data
			$db_check = isset( $_POST[ 'schema_disable_body' ] );
			$dp_check = isset( $_POST[ 'schema_disable_post' ] );
	
			update_post_meta( $post_id, '_schema_disable_body', $db_check );
			update_post_meta( $post_id, '_schema_disable_post', $dp_check );
			delete_post_meta( $post_id, '_raven_schema_load' );
					
			
			do_action( 'raven_sc_save_metabox' );
		}
		
		/**
		 * Gets the options value for a key
		 *
		 * @key option key
		 * @returns option value
		 */
		function get_option( $key ) {
			$schema_options	= get_option( 'schema_options' );	
			return isset($schema_options[$key]) ? $schema_options[$key] : NULL;
		}
		
		/**
		 * Gets the tooltip value for a key
		 *
		 * @key tooltip key
		 * @returns tooltip value
		 */
		function get_tooltip( $key ) {
			$tooltips = apply_filters( 'raven_sc_admin_tooltip', array() );
			return isset($tooltips[ $key ]) ? $tooltips[ $key ] : NULL;
		}
	
		/**
		 * Register settings page
		 */
		public function add_pages() {
			
			add_submenu_page( 'options-general.php',
				 __('Schema Creator', 'schema'),
				 __('Schema Creator', 'schema'), 
				'manage_options', 
				$this->get_page_slug(), 
				array( &$this, 'do_page' )
			);
			
		}
		
		/**
		 * Gets the page slug name
		 *
		 * @returns the page slug
		 */
		public function get_page_slug() {
			return 'schema-creator';
		}
	
		/**
		 * Register settings
		 */
		public function register_settings() {
			register_setting( 'schema_options', 'schema_options', array(&$this, 'options_validate' ) );
			
			// Information
			add_settings_section('info_section', __('Information', 'schema'), array(&$this, 'options_info_section'), 'schema_options');
			add_settings_field( 'info_version', __('Plugin Version', 'schema'), array(&$this, 'options_info_version'), 'schema_options', 'info_section');
			
			// CSS output
			add_settings_section( 'display_section', __('Display', 'schema'), array( &$this, 'options_display_section' ), 'schema_options' );
			add_settings_field( 'css', __( 'CSS output', 'schema' ), array( &$this, 'options_display_css' ), 'schema_options', 'display_section' );
			
			// HTML data applying
			add_settings_section( 'data_section', __('Data', 'schema'), array( &$this, 'options_data_section' ), 'schema_options' );
			add_settings_field( 'body', __( 'Body Tag', 'schema' ), array( &$this, 'options_data_body' ), 'schema_options', 'data_section' );
			add_settings_field( 'post', __( 'Content Wrapper', 'schema' ), array( &$this, 'options_data_post' ), 'schema_options', 'data_section' );

			do_action( 'raven_sc_register_settings' );
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
						__( 'By default, the %s plugin by %s includes unique CSS IDs and classes. You can reference the CSS to control the style of the HTML that the Schema Creator plugin outputs.' , 'schema' ).'<br>',
						
						// the plugin 
						'<a target="_blank" 
							href="'. esc_url( _x( 'http://schema-creator.org/?utm_source=wp&utm_medium=plugin&utm_campaign=schema', 'plugin uri', 'schema' ) ) .'" 
							title="' . esc_attr( _x( 'Schema Creator', 'plugin name', 'schema' ) ) . '">'. _x( 'Schema Creator' , 'plugin name', 'schema') . '</a>', 
						
						// the author
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
			echo "<code id='info_version'>".SC_VER."</code>";
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
					<input type="checkbox" id="schema_css" name="schema_options[css]" class="schema_checkbox" value="true" '.checked($css_hide, true, false).'/>
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
					<input type="checkbox" id="schema_body" name="schema_options[body]" class="schema_checkbox" value="true" '.checked($body_tag, true, false).'/>
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
					<input type="checkbox" id="schema_post" name="schema_options[post]" class="schema_checkbox" value="true" '.checked($post_tag, true, false).'/>
					 '.__('Apply itemscope &amp; itemtype to content wrapper', 'schema').'
				</label>
				<span class="ap_tooltip" tooltip="'.$this->get_tooltip( 'post_class' ).'">'._x('(?)', 'tooltip button', 'schema').'</span>
			';
		}
		
		/**
		 * Validates input
		 */
		function options_validate( $input ) {
			do_action_ref_array( 'raven_sc_options_validate', array( &$input ) );
			
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
		 * @return ravenSchema
		 */
		public function default_settings( ) 
		{
			$options_check	= get_option('schema_options');
			if(empty( $options_check ))
				$options_check = array();
	
			// Fetch defaults.
			$default = array();
			$default = apply_filters( 'raven_sc_default_settings', &$default );
			
			// Existing optons will override defaults
			update_option('schema_options', $default + $options_check);
			
			do_action( 'raven_sc_default_settings' );
		}
		
		/**
		 * Gets the default settings
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
		 */
		public function get_tooltips( $tooltip = array() ) 
		{
			$tooltip = $tooltip + array(
				'default_css'	=> __('Check to remove Schema Creator CSS from the microdata HTML output.', 'schema'),
				'body_class'	=> __('Check to add the <code>http://schema.org/Blog</code> schema itemtype to the BODY element on your pages and posts. Your theme must have the <code>body_class</code> template tag for this to work.', 'schema'),
				'post_class'	=> __('Check to add the <code>http://schema.org/BlogPosting</code> schema itemtype to the content wrapper on your pages and posts.', 'schema'),
	
				// end tooltip content
			);
	
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
						<?php settings_fields( 'schema_options' ); ?>		
	 					<?php do_settings_sections( 'schema_options' ); ?>
                        <?php do_action( 'raven_sc_options_form' ); ?>
	                    <p class="submit">
                        	<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
                    	</p>	
                	</form>
				</div> <!-- end .schema_options -->
			</div> <!-- end .wrap -->
		<?php }
	
	
		/**
		 * Load scripts and style for admin settings page
		 */
		public function admin_scripts( $hook ) {
			
			$post_screen = $hook == 'post-new.php' || $hook == 'post.php';
			$settings_screen = 'settings_page_' . $this->get_page_slug() == $hook;
			
			if ( $post_screen || $settings_screen ) :
				
				// Style
				wp_enqueue_style( 'schema-admin', plugins_url('/lib/css/schema-admin.css', __FILE__), array(), SC_VER, 'all' );
				
				// Tooltip
				wp_enqueue_script( 'jquery-qtip', plugins_url('/lib/js/jquery.qtip.min.js', __FILE__) , array('jquery'), SC_VER, true );
				wp_enqueue_script( 'schema-admin', plugins_url('/lib/js/schema.admin.js', __FILE__) , array('jquery'), SC_VER, true );
	
				if ( $post_screen ) :
				
					// Create form
					wp_enqueue_script( 'jquery-ui-core' );
					wp_enqueue_script( 'jquery-ui-datepicker');
					wp_enqueue_script( 'jquery-ui-slider');
					wp_enqueue_script( 'jquery-timepicker', plugins_url( '/lib/js/jquery.timepicker.js', __FILE__) , array( 'jquery' ), SC_VER, true );
					wp_enqueue_script( 'format-currency', plugins_url( '/lib/js/jquery.currency.min.js', __FILE__) , array( 'jquery' ), SC_VER, true );
					wp_enqueue_script( 'schema-admin-form', plugins_url( '/lib/js/schema.admin.form.js', __FILE__) , array( 'jquery' ), SC_VER, true );
					wp_enqueue_script( 'schema-admin-ajax', plugins_url( '/lib/js/schema.admin.ajax.js', __FILE__) , array( 'jquery' ), SC_VER, true );
					
					wp_localize_script( 'schema-admin-ajax', 'schema_ajax', array( 'nonce' => wp_create_nonce( 'schema_ajax_nonce' ) ) );
					
				endif;
			endif;
		}
	
	
		/**
		 * Add attribution link to settings page
		 */
		public function admin_footer_attribution( $text ) {
			$current_screen = get_current_screen();
	
			if ( 'settings_page_schema-creator' !== $current_screen->base )
				return $text;
	
			$text = '<span id="footer-thankyou">' . 
				sprintf( __('This plugin brought to you by the fine folks at %s', 'schema'), 
					'<a target="_blank" 
						href="' . esc_url( _x( 'http://raventools.com/?utm_source=wp&utm_medium=plugin&utm_campaign=schema', 'plugin url', 'schema' ) ).'" 
						title="' . esc_attr__( 'Internet Marketing Tools for SEO and Social Media', 'schema' )  . '"> '. 
						_x('Raven Internet Marketing Tools', 'author', 'schema') . '
					</a>'
				) . 
			'</span>';
	
			return $text;
		}
	
		/**
		 * Load body classes
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
				$meta_check	= get_post_meta( $post->ID, '_raven_schema_load', array() );
				//printf( "%d is %s, %s, %s<br>", $post->ID, var_export( $meta_check, true ), var_export( !count( $meta_check ), true ), var_export( !empty( $meta_check[0] ), true ) );
				if ( !count( $meta_check ) ) :
				
					// Check the post content for the short code
					$content = $post->post_content;
					$local_found = false;
					if ( strpos( $content, '[schema' ) !== false ) {
						$found |= true;
						$local_found |= true;
					} 
					update_post_meta( $post->ID, '_raven_schema_load', $local_found );
					
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
				wp_enqueue_style( 'schema-style', plugins_url( '/lib/css/schema-style.css' , __FILE__ ), array(), SC_VER, 'all' );
				do_action( 'raven_sc_enqueue_schemapost' );
			endif;
				
			return $posts;
		}
	
		/**
		 * wrap content in markup
		 *
		 * @return ravenSchema
		 */
		public function schema_wrapper( $content ) {
	
			$schema_options = get_option( 'schema_options' );
			$wrapper = !isset($schema_options['post']) || ( $schema_options['post'] === true || $schema_options['post'] == 'true' );
	
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
		
		/**
		 * Gets the schema types
		 *
		 */
		public function get_schema_types( $ajax = true ) {
			$this->do_ajax();
			check_ajax_referer( 'schema_ajax_nonce', 'security' );
			
			$scraper = $this->get_scraper();
			$results = array();
			
			// Get selected schema
			$top_level = $scraper->get_top_level_schemas();
			$type = isset( $_POST[ 'type' ] ) ? $_POST[ 'type' ] : array_shift( $top_level );
			$schema = $scraper->get_schema( $type );
			if ( empty( $schema ) ) $type = array_shift( $top_level );
			
			// Get descendants
			foreach( $scraper->get_schema_descendants( $type, false ) as $schema )
				$results[]= $scraper->get_schema_id( $schema );
			
			echo json_encode( array( 'types' => $results ) );
			exit;
		}
		
		/** 
		 * Gets the scraper class
		 *
		 * @returns the scraper singleton instance
		 */
		public function get_scraper() {
			return DJ_SchemaScraper::singleton();	
		}
		
		/**
		 * Gets an array of shortcode properties
		 *
		 * @returns an array of available shortcode properties, all set with default to NULL
		 */
		public function get_shortcode_properties() {
			$scraper = $this->get_scraper();
			$properties = $scraper->get_property_keys();
			
			$results = array();
			foreach($properties as $property)
				$results[ $scraper->get_property_id( $property ) ] = NULL;
			return $results;
		}
	
		/**
		 * Gets the output for a schema
		 *
		 * @returns schema output for that type and data
		 */
		public function get_schema_output( $type, $data ) 
		{
			// Get the scraper, schema and its properties
			$scraper = $this->get_scraper();
			$schema = $scraper->get_schema( $type );
			$properties = $scraper->get_schema_properties( $type, true, true ) ?: array();
			
			$sc_build = '<div class="'.esc_attr( strtolower( 'schema-'.$type ) ).'" itemscope itemtype="'.esc_url( $scraper->get_schema_url( $schema ) ).'">';
			while ( $property_key = array_shift( $properties ) ) :
				// go through props
				$property = $scraper->get_property( $property_key );
				$property_id = $scraper->get_property_id( $property );
				if ( !isset( $data[$property_id] ) || empty( $data[$property_id] ) )
					continue;
					
				// TODO: per property formatting
				$sc_build .= sprintf("%s: %s<br>", $property_id, $data[$property_id]);
			endwhile;
			
			$sc_build .= '</div>';
			return $sc_build;
		}
		
		/**
		 * Build out shortcode with variable array of options
		 *
		 * @return the replacement
		 */
		public function shortcode( $atts, $content = NULL ) 
		{
			$attributes = shortcode_atts( $this->get_shortcode_properties() + array( 'type' => '' ), $atts );
				
			// wrap schema build out
			$sc_build  = '<div id="schema_block">';
			$sc_build .= $this->get_schema_output( $attributes["type"], $attributes );
			$sc_build .= '</div>';
	
			// return entire build array
			return $sc_build;
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
					__('Schema Creator Form', 'schema' ) .
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
	
			<script type="text/javascript">
				function InsertSchema() {
					//select field options
					var type			= jQuery('#schema_builder select#schema_type').val();
					
					// output setups
					output = '[schema ';
					output += 'type="' + type + '" ';
					output += ']';
	
					window.send_to_editor(output);
				}
			</script>
	
			<div id="schema_build_form" style="display:none;">
				<div id="schema_builder" class="schema_wrap">
					<!-- schema type dropdown -->
					<div id="sc_type">
                    	<?php 
							$current_type = "Thing";
							$chain_tracker = array();
							$need_optgroup  = false;
						?>
						
						<label for="schema_type"><?php _e('Schema Type', 'schema'); ?></label>
						<select name="schema_type" id="schema_type" class="schema_drop schema_thindrop">
							<option class="holder" value="">(<?php _e('Select a Type', 'schema'); ?>)</option>    
                            
						</select>
					</div>
					<!-- end schema type dropdown -->
                    
					<div id="sc_country" class="sc_option" style="display:none">
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
					</div>
			
					<div id="sc_email" class="sc_option" style="display:none">
						<label for="schema_email"><?php _e('Email Address', 'schema'); ?></label>
						<input type="text" name="schema_email" class="form_full" value="" id="schema_email" />
					</div>
			
					<div id="sc_phone" class="sc_option" style="display:none">
						<label for="schema_phone"><?php _e('Telephone', 'schema'); ?></label>
						<input type="text" name="schema_phone" class="form_half" value="" id="schema_phone" />
					</div>
			
					<div id="sc_fax" class="sc_option" style="display:none">
						<label for="schema_fax"><?php _e('Fax', 'schema'); ?></label>
						<input type="text" name="schema_fax" class="form_half" value="" id="schema_fax" />
					</div>
			
					<div id="sc_brand" class="sc_option" style="display:none">
						<label for="schema_brand"><?php _e('Brand', 'schema'); ?></label>
						<input type="text" name="schema_brand" class="form_full" value="" id="schema_brand" />
					</div>
			
					<div id="sc_manfu" class="sc_option" style="display:none">
						<label for="schema_manfu"><?php _e('Manufacturer', 'schema'); ?></label>
						<input type="text" name="schema_manfu" class="form_full" value="" id="schema_manfu" />
					</div>
			
					<div id="sc_model" class="sc_option" style="display:none">
						<label for="schema_model"><?php _e('Model', 'schema'); ?></label>
						<input type="text" name="schema_model" class="form_full" value="" id="schema_model" />
					</div>
			
					<div id="sc_prod_id" class="sc_option" style="display:none">
						<label for="schema_prod_id"><?php _e('Product ID', 'schema'); ?></label>
						<input type="text" name="schema_prod_id" class="form_full" value="" id="schema_prod_id" />
					</div>
			
					<div id="sc_ratings" class="sc_option" style="display:none">
						<label for="sc_ratings"><?php _e('Aggregate Rating', 'schema'); ?></label>
						<div class="labels_inline">
						<label for="schema_single_rating"><?php _e('Avg Rating', 'schema'); ?></label>
						<input type="text" name="schema_single_rating" class="form_eighth schema_numeric" value="" id="schema_single_rating" />
						<label for="schema_agg_rating"><?php _e('based on', 'schema'); ?> </label>
						<input type="text" name="schema_agg_rating" class="form_eighth schema_numeric" value="" id="schema_agg_rating" />
						<label><?php _e('reviews', 'schema'); ?></label>
						</div>
					</div>
			
					<div id="sc_reviews" class="sc_option" style="display:none">
						<label for="sc_reviews"><?php _e('Rating', 'schema'); ?></label>
						<div class="labels_inline">
						<label for="schema_user_review"><?php _e('Rating', 'schema'); ?></label>
						<input type="text" name="schema_user_review" class="form_eighth schema_numeric" value="" id="schema_user_review" />
						<label for="schema_min_review"><?php _e('Minimum', 'schema'); ?></label>
						<input type="text" name="schema_min_review" class="form_eighth schema_numeric" value="" id="schema_min_review" />
						<label for="schema_max_review"><?php _e('Minimum', 'schema'); ?></label>
						<input type="text" name="schema_max_review" class="form_eighth schema_numeric" value="" id="schema_max_review" />
						</div>
					</div>
			
			
					<div id="sc_price" class="sc_option" style="display:none">
						<label for="schema_price"><?php _e('Price', 'schema'); ?></label>
						<input type="text" name="schema_price" class="form_third sc_currency" value="" id="schema_price" />
					</div>
			
					<div id="sc_condition" class="sc_option" style="display:none">
						<label for="schema_condition"><?php _ex('Condition', 'product', 'schema'); ?></label>
						<select name="schema_condition" id="schema_condition" class="schema_drop">
							<option class="holder" value="none">(<?php _e('Select', 'schema'); ?>)</option>
							<option value="New"><?php _e('New', 'schema'); ?></option>
							<option value="Used"><?php _e('Used', 'schema'); ?></option>
							<option value="Refurbished"><?php _e('Refurbished', 'schema'); ?></option>
							<option value="Damaged"><?php _e('Damaged', 'schema'); ?></option>
						</select>
					</div>
			
					<div id="sc_author" class="sc_option" style="display:none">
						<label for="schema_author"><?php _e('Author', 'schema'); ?></label>
						<input type="text" name="schema_author" class="form_full" value="" id="schema_author" />
					</div>
			
					<div id="sc_publisher" class="sc_option" style="display:none">
						<label for="schema_publisher"><?php _e('Publisher', 'schema'); ?></label>
						<input type="text" name="schema_publisher" class="form_full" value="" id="schema_publisher" />
					</div>
			
					<div id="sc_pubdate" class="sc_option" style="display:none">
						<label for="schema_pubdate"><?php _e('Published Date', 'schema'); ?></label>
						<input type="text" id="schema_pubdate" name="schema_pubdate" class="schema_datepicker form_third" value="" />
						<input type="hidden" id="schema_pubdate-format" class="schema_datepicker-format" value="" />
					</div>
			
					<div id="sc_edition" class="sc_option" style="display:none">
						<label for="schema_edition"><?php _e('Edition', 'schema'); ?></label>
						<input type="text" name="schema_edition" class="form_full" value="" id="schema_edition" />
					</div>
			
					<div id="sc_isbn" class="sc_option" style="display:none">
						<label for="schema_isbn"><?php _e('ISBN', 'schema'); ?></label>
						<input type="text" name="schema_isbn" class="form_full" value="" id="schema_isbn" />
					</div>
			
					<div id="sc_formats" class="sc_option" style="display:none">
						<label class="list_label"><?php _e('Formats', 'schema'); ?></label>
						<div class="form_list">
							<span>
								<input type="checkbox" class="schema_check" id="schema_ebook" name="schema_ebook" value="ebook" />
								<label for="schema_ebook" rel="checker"><?php _e('Ebook', 'schema'); ?></label>
							</span>
							<span>
								<input type="checkbox" class="schema_check" id="schema_paperback" name="schema_paperback" value="paperback" />
								<label for="schema_paperback" rel="checker"><?php _e('Paperback', 'schema'); ?></label>
							</span>
							<span>
								<input type="checkbox" class="schema_check" id="schema_hardcover" name="schema_hardcover" value="hardcover" />
								<label for="schema_hardcover" rel="checker"><?php _e('Hardcover', 'schema'); ?></label>
						   </span>
						</div>
					</div>
			
					<div id="sc_revdate" class="sc_option" style="display:none">
						<label for="schema_revdate"><?php _e('Review Date', 'schema'); ?></label>
						<input type="text" id="schema_revdate" name="schema_revdate" class="schema_datepicker form_third" value="" />
						<input type="hidden" id="schema_revdate-format" class="schema_datepicker-format" value="" />
					</div>
			
					<div id="sc_preptime" class="sc_option" style="display:none">
						<label for="sc_preptime"><?php _e('Prep Time', 'schema'); ?></label>
						<div class="labels_inline">
							<label for="schema_prep_hours"><?php _e('Hours', 'schema'); ?></label>
							<input type="text" name="schema_prep_hours" class="form_eighth schema_numeric" value="" id="schema_prep_hours" />
							<label for="schema_prep_mins"><?php _e('Minutes', 'schema'); ?></label>
							<input type="text" name="schema_prep_mins" class="form_eighth schema_numeric" value="" id="schema_prep_mins" />
						</div>
					</div>
			
					<div id="sc_cooktime" class="sc_option" style="display:none">
						<label for="sc_cooktime"><?php _e('Cook Time', 'schema'); ?></label>
						<div class="labels_inline">
							<label for="schema_cook_hours"><?php _e('Hours', 'schema'); ?></label>
							<input type="text" name="schema_cook_hours" class="form_eighth schema_numeric" value="" id="schema_cook_hours" />
							<label for="schema_cook_mins"><?php _e('Minutes', 'schema'); ?></label>
							<input type="text" name="schema_cook_mins" class="form_eighth schema_numeric" value="" id="schema_cook_mins" />
						</div>
					</div>
			
					<div id="sc_yield" class="sc_option" style="display:none">
						<label for="schema_yield"><?php _e('Yield', 'schema'); ?></label>
						<input type="text" name="schema_yield" class="form_third" value="" id="schema_yield" />
						<label class="additional">(<?php _e('serving size', 'schema'); ?>)</label>
					</div>
			
					<div id="sc_calories" class="sc_option" style="display:none">
						<label for="schema_calories"><?php _e('Calories', 'schema'); ?></label>
						<input type="text" name="schema_calories" class="form_third schema_numeric" value="" id="schema_calories" />
					</div>
			
					<div id="sc_fatcount" class="sc_option" style="display:none">
						<label for="schema_fatcount"><?php _e('Fat', 'schema'); ?></label>
						<input type="text" name="schema_fatcount" class="form_third schema_numeric" value="" id="schema_fatcount" />
						<label class="additional">(<?php _e('in grams', 'schema'); ?>)</label>
					</div>
			
					<div id="sc_sugarcount" class="sc_option" style="display:none">
						<label for="schema_sugarcount"><?php _e('Sugar', 'schema'); ?></label>
						<input type="text" name="schema_sugarcount" class="form_third schema_numeric" value="" id="schema_sugarcount" />
						<label class="additional">(<?php _e('in grams', 'schema'); ?>)</label>
					</div>
			
					<div id="sc_saltcount" class="sc_option" style="display:none">
						<label for="schema_saltcount"><?php _e('Sodium', 'schema'); ?></label>
						<input type="text" name="schema_saltcount" class="form_third schema_numeric" value="" id="schema_saltcount" />
						<label class="additional">(<?php _e('in milligrams', 'schema'); ?>)</label>
					</div>
			
					<div id="sc_ingrt_1" class="sc_option sc_ingrt sc_repeater ig_repeat" style="display:none">
						<label for="schema_ingrt_1"><?php _e('Ingredient', 'schema'); ?></label>
						<input type="text" name="schema_ingrt_1" class="form_half ingrt_input" value="" id="schema_ingrt_1" />
						<label class="additional">(<?php _e('include both type and amount', 'schema'); ?>)</label>
					</div>
			
					<input type="button" class="clone_button" id="clone_ingrt" value="<?php _e('Add Another Ingredient', 'schema'); ?>" style="display:none;" />
			
					<div id="sc_instructions" class="sc_option" style="display:none">
						<label for="schema_instructions"><?php _e('Instructions', 'schema'); ?></label>
						<textarea name="schema_instructions" id="schema_instructions"></textarea>
					</div>
			
					<!-- button for inserting -->
					<div class="insert_button" style="display:none">
						<input class="schema_insert schema_button" type="button" value="<?php _e('Insert'); ?>" onclick="InsertSchema();"/>
						<input class="schema_cancel schema_clear schema_button" type="button" value="<?php _e('Cancel'); ?>" onclick="tb_remove(); return false;"/>
					</div>
			
					<!-- various messages -->
					<div id="sc_messages">
						<p class="start"><?php _e('Select a schema type above to get started', 'schema'); ?></p>
						<p class="pending" style="display:none;"><?php _e('This schema type is currently being constructed.', 'schema'); ?></p>
					</div>
			
				</div>
			</div>
		<?php 
		}
		
	/// end class
	}
	
	// Instantiate our class
	$ravenSchema = new RavenSchema();
endif;
// Include modules
foreach ( glob( plugin_dir_path(__FILE__) . "/lib/*.php" ) as $filename )
    include_once $filename;

