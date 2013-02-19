<?php 	

/**
 * Basic tests for Schema Creator by Raven plugin.
 *
 * Runs on PHPUnit and Wordpress-Tests.
 * @author Derk-Jan Karrenbeld <derk-jan+github@karrenbeld.info>
 * @version 1.0
 * @package WordPress\SchemaCreator\Tests
 */


/**
 * Stub for get_current_screen();
 *
 * @returns object stub
 */
function get_current_screen() {
	global $getcurrentscreenname;
	return (object)array( 'base' => $getcurrentscreenname );
}

/**
 * Stub method for adding meta boxes
 *
 * @param mixed id the metabox id
 */
function add_meta_box( $id ) {
	global $metaboxes;
	$metaboxes[]=$id;
}

/**
 * Basic Test class for Schema Creator Plugin.
 *
 * @author Derk-Jan Karrenbeld <derk-jan+github@karrenbeld.info>
 * @version 1.0
 * @link http://plugins.svn.wordpress.org/advanced-excerpt/tests/wp-test/lib/testcase.php uses this testcase
 * @remarks plugin path has to be [...]/$plugin_slug/$plugin_slug.php
 *
 * @package WordPress\SchemaCreator\Tests
 */
class BasicTest extends WP_UnitTestCase {
	
	/**
	 * This is the name of the folder and file.
	 */
	public $plugin_slug = 'schema-creator';

	/**
	 * Setups this test
	 */
	public function setUp() {
		parent::setUp();
		$this->my_plugin = new \ravenSchema();
		
		global $getcurrentscreenname, $metaboxes;
		$getcurrentscreenname = '';
		$metaboxes = array();
	}

	/**
	 * Tests if a quick link is added to the links array.
	 */
	public function testQuickLink() 
	{
		$links = $this->my_plugin->quick_link( array(), SC_BASE );
		$this->assertCount( 1, $links, 'Expected 1 link and got ' . count( $links ) );
		$this->assertStringStartsWith( '<a ', $links[0], "Expected link but didn't find it." );
	}
	
	/**
	 * Tests if a schema test link is added to the admin bar
	 */
	public function testSchemaTest() {

		// This will simulate running WordPress' main query.
		// We want to be on a singular, non-admin page!
		$this->go_to( 'http://example.org/?p=1' );
		
		// Since the global will be loaded, mock it.
		global $wp_admin_bar;
		$wp_admin_bar = $this->getMock( 'WP_Admin_Bar', array('add_node') );
		
		// We would like to know if the test fails because of a faulty go_to or
		// a faulty schema_creator implementation. Just a pre-caution. This is 
		// not a Unit Test standard, since that should be tested in a WP_UnitTestCase
		// Unit Test. Since there is none...
		$this->assertFalse( is_admin(), 'Go-to needs to simulate a non-admin page' );
		$this->assertTrue( is_singular(), 'Go-to needs to simulate a singular page' );
		
		// We expect the add_node to be called once( to add the add_node )
		$wp_admin_bar->expects( $this->once() )->method('add_node');
		$this->my_plugin->schema_test( $wp_admin_bar );
	}
	
	/**
	 * Tests if the metabox is conditionally hidden.
	 * 
	 * Tests if the metabox is only hidden when both post and body
	 * are empty values in the option variable.
	 */
	public function testMetaBox() {
		$this->assertTrue( $this->metaBoxTests( true, true ), 'metabox is hidden when it should not be'  );
		$this->assertTrue( $this->metaBoxTests( true, NULL ), 'metabox is hidden when it should not be'  );
		$this->assertTrue( $this->metaBoxTests( NULL, true ), 'metabox is hidden when it should not be' );
		$this->assertFalse( $this->metaBoxTests( NULL, NULL ) , 'metabox is visible when it should not be' );
	}
	
	/**
	 * Tests the metabox adding with certain option values.
	 *
	 * @assert (true, true) == false
	 * @assert (NULL, true) == true
	 * @assert (true, NULL) == true
	 * @assert (NULL, NULL) == false
	 *
	 * @param mixed $a body option value
	 * @param mixed $b post option value
	 * @returns boolean true if metabox was added
	 */
	public function metaBoxTests( $a, $b ) {
		global $metaboxes;
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['body'] = $a;
		$current['post'] = $b;
		update_option( 'schema_options', $current );
		
		$this->my_plugin->metabox_schema( 'post', 'side' );
		$result = in_array( 'schema-post-box', $metaboxes );
		$metaboxes = array();
		
		update_option( 'schema_options', $previous );
		return $result;
	}
	
	/**
	 * Tests if the metabox is conditionally hidden.
	 * 
	 * Tests if the metabox is only shown when context is side
	 * are empty values in the option variable.
	 *
	 * @depends testMetaBox
	 */
	public function metaBoxContextTests() {
		global $metaboxes;
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['body'] = true;
		$current['post'] = true;
		update_option( 'schema_options', $current );
		
		$this->my_plugin->metabox_schema( 'post', 'advanced' );
		$this->assertEmpty( $metaboxes, 'meta box was added to wrong context' );
		$metaboxes = array();
		
		$this->my_plugin->metabox_schema( 'post', 'high' );
		$this->assertEmpty( $metaboxes, 'meta box was added to wrong context' );
		$metaboxes = array();
		
		update_option( 'schema_options', $previous );
	}
	
	/**
	 * Tests the default options
	 */
	public function testStoreSettings() {
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		update_option( 'schema_options', array() );
		
		$this->my_plugin->store_settings();		
		
		$schema_options = get_option( 'schema_options' );
		$this->assertEquals( 'false', $schema_options['css'], 'default css option is not false but ' . var_export( $schema_options['css'], true ) );
		$this->assertEquals( 'true', $schema_options['body'], 'default body option is not true but ' . var_export( $schema_options['body'], true )  );
		$this->assertEquals( 'true', $schema_options['post'], 'default post option is not true but ' . var_export( $schema_options['post'], true )  );
		
		update_option( 'schema_options', $previous );
	
	}
	
	/**
	 * Tests if style and script are loaded when editing a post.
	 */
	public function testAdminScriptsPost() {
		
		global $getcurrentscreenname;
		$getcurrentscreenname = '';
		$this->my_plugin->admin_scripts( 'post.php' );
		
		$this->assertTrue( wp_style_is( 'schema-admin' ), 'Admin schema style is not enqueued.' );
		$this->assertTrue( wp_script_is( 'schema-form' ), 'Schema script is not enqueued.' );
		
		wp_dequeue_style( 'schema-admin' );
		wp_dequeue_script( 'schema-form' );
	}
	
	/**
	 * Tests if style and script are loaded when creating a post.
	 */
	public function testAdminScriptsPostNew() {
		
		global $getcurrentscreenname;
		$getcurrentscreenname = '';
		$this->my_plugin->admin_scripts( 'post-new.php' );
		
		$this->assertTrue( wp_style_is( 'schema-admin' ), 'Admin schema style is not enqueued.' );
		$this->assertTrue( wp_script_is( 'schema-form' ), 'Schema script is not enqueued.' );
		
		wp_dequeue_style( 'schema-admin' );
		wp_dequeue_script( 'schema-form' );
	}
	
	/**
	 * Tests if style and script are loaded when on the settings page.
	 */
	public function testAdminScriptsSettingsPage() {
		
		global $getcurrentscreenname;
		$getcurrentscreenname = 'settings_page_schema-creator';
		
		$this->my_plugin->admin_scripts( 'settings.php' );
		
		$this->assertTrue( wp_style_is( 'schema-admin' ), 'Admin schema style is not enqueued.' );
		$this->assertTrue( wp_script_is( 'schema-admin' ), 'Admin Schema script is not enqueued.' );
		
		wp_dequeue_style( 'schema-admin' );
		wp_dequeue_script( 'schema-admin' );
	}
	
	/**
	 * Tests the attribution link
	 */
	public function testSchemaFooter() {
		
		global $getcurrentscreenname;
		$getcurrentscreenname = 'settings_page_schema-creator';
		
		$this->assertNotEmpty( $this->my_plugin->schema_footer( '' ), 'footer is not altered when on settings page' );
		
		$getcurrentscreenname = '';
		$this->assertEmpty( $this->my_plugin->schema_footer( '' ), 'footer is altered when not on settings page' );
		
	}
	
	/**
	 * Tests if the body class is added if set to true
	 */
	public function testBodyClass() {
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['body'] = 'true';
		update_option( 'schema_options', $current );
		
		// Got to a post
		$this->go_to( 'http://example.org/?p=1' );
		
		ob_start();
		$this->my_plugin->body_class( '' );
		$this->assertContains( 'itemscope', ob_get_contents(), 'Itemscope not inserted by default.');
		ob_clean();
		
		update_option( 'schema_options', $previous );
	}
	
	/**
	 * Tests if the body class is not added if set to false
	 */
	public function testBodyClassNot() {
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['body'] = 'false';
		update_option( 'schema_options', $current );
		
		// Got to a post
		$this->go_to( 'http://example.org/?p=1' );
		
		ob_start();
		$this->my_plugin->body_class( '' );
		$this->assertEmpty( ob_get_contents(), 'Itemscope was inserted when it should not.');
		ob_clean();
		
		update_option( 'schema_options', $previous );
	}
				
	/**
	 * Tests if the schema css is loaded for schema posts
	 */
	public function testSchemaLoader() {
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['css'] = false;
		update_option( 'schema_options', $current );
		
		$this->go_to( 'http://example.org/?p=1' );
		
		global $wp_query;
        $post = $wp_query->get_queried_object();
		$post->post_content = '[schema ]';
		$posts = array( $post );
		
		$this->my_plugin->schema_loader( $posts );
		$this->assertTrue( wp_style_is( 'schema-style' ) );
		$this->assertNotEmpty( get_post_meta( $post->ID, '_raven_schema_load', true ), 'Raven Schema should be loaded for this post' );	
	
		wp_dequeue_style( 'schema-style' );
		update_option( 'schema_options', $previous );
	}
	
	/**
	 * Tests if the schema css is not loaded for non schema posts
	 */
	public function testSchemaLoaderNot() {
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['css'] = false;
		update_option( 'schema_options', $current );
		
		$this->go_to( 'http://example.org/?p=1' );
		
		global $wp_query;
        $post = $wp_query->get_queried_object();
		$post->post_content = '';
		$posts = array( $post );
		
		$this->my_plugin->schema_loader( $posts );
		$this->assertFalse( wp_style_is( 'schema-style' ) );
		$this->assertEmpty( get_post_meta( $post->ID, '_raven_schema_load', true ), 'Raven Schema should not be loaded for this post' );
		
		wp_dequeue_style( 'schema-style' );
		update_option( 'schema_options', $previous );
	}
	
	/**
	 * Tests if the schema css is not loaded for schema posts but option set
	 * @depends testSchemaLoaderNot
	 * @depends testSchemaLoader
	 */
	public function testSchemaLoaderNotOptions() {
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['css'] = 'true';
		update_option( 'schema_options', $current );
		
		$this->go_to( 'http://example.org/?p=1' );
		
		global $wp_query;
        $post = $wp_query->get_queried_object();
		$post->post_content = '[schema ]';
		$posts = array( $post );
		
		$this->my_plugin->schema_loader( $posts );
		$this->assertFalse( wp_style_is( 'schema-style' ) );
		$this->assertEmpty( get_post_meta( $post->ID, '_raven_schema_load', true ), 'Raven Schema should not be loaded for this post' );
		
		wp_dequeue_style( 'schema-style' );
		update_option( 'schema_options', $previous );
	}
	
	/**
	 * Tests if the post is wrapped
	 * @depends testSchemaLoader
	 */
	public function testSchemaWrapper() {
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['css'] = false;
		$current['post'] = 'true';
		update_option( 'schema_options', $current );
		
		// First load that schema valid post
		global $wp_query;
        $post = $wp_query->get_queried_object();
		$post->post_content = '[schema ]';
		$posts = array( $post );
		$this->my_plugin->schema_loader( $posts );
		
		// Now lets see it its wrapped
		$this->assertContains( 'itemscope', $this->my_plugin->schema_wrapper( '' ), 'post should be wrapped' );
		
		update_option( 'schema_options', $previous );
		
	}
	
	/**
	 * Tests if the post is not wrapped when option indicates that
	 * @depends testSchemaLoader
	 */
	public function testSchemaWrapperNot() {
		
		$current = get_option( 'schema_options' );
		$previous = $current;
		$current['css'] = false;
		$current['post'] = 'false';
		update_option( 'schema_options', $current );
		
		// First load that schema valid post
		global $wp_query;
        $post = $wp_query->get_queried_object();
		$post->post_content = '[schema ]';
		$posts = array( $post );
		$this->my_plugin->schema_loader( $posts );
		
		// Now lets see it its wrapped
		$this->assertEmpty( $this->my_plugin->schema_wrapper( '' ), 'post should not be wrapped' );
		
		update_option( 'schema_options', $previous );
		
	}
	
	
}