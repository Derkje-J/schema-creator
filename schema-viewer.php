<?php 
/*
  Lists schemas scraped with the scraper

  Version: 1.0
  Author: Derk-Jan Karrenbeld
  Author URI: http://derk-jan.com
  
*/

include_once "schema-scraper.php";

if (!class_exists("DJ_SchemaViewer")) 
{
	define("DJ_SCHEMAVIEWER_BASE", plugin_basename(__FILE__));
	define("DJ_SCHEMAVIEWER_VERSION", '1.0');
	
	class DJ_SchemaViewer {
		
		private static $singleton;
		
		/**
		 * Gets a singleton of this class
		 *
		 * DJ_SchemaViewer::singleton() will always return the same instance during a
		 * PHP processing stack. This way actions will not be queued duplicately and 
		 * caching of processed values is not neccesary.
		 *
		 * @returns the singleton instance
		 */
		public static function singleton() {
			if ( empty( DJ_SchemaViewer::$singleton ) )
				DJ_SchemaViewer::$singleton = new DJ_SchemaViewer();
			return DJ_SchemaViewer::$singleton;
		}
		
		/**
		 * Creates a new instance of DJ_SchemaScraper
		 *
		 * @remarks use DJ_SchemaViewer::singleton() outside the class hieracrchy
		 */
		protected function __construct() 
		{	
			add_action( 'admin_menu', array( $this, 'add_pages' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		}	
		
		/**
		 * Gets the page slug name
		 */
		public function page_slug() {
			return 'schema-viewer';
		}	
		
		/**
		 *
		 */
		public function get_url( $type = NULL ) {
			
			// Edit URL
			$url = 'options-general.php?page=' . $this->page_slug();	
	
			// Add type id
			if ( !empty( $type ) || isset( $_REQUEST[ 'schema' ] ) )
				$url .= '&schema=' . ( !empty( $type ) ? urlencode( $type ) : $_REQUEST[ 'schema' ] );
			
			return $url;
		}	
		
		/**
		 *
		 */
		public function get_link( $type, $format = '%s' ) {
			return '<a href="' . esc_attr( esc_url( $this->get_url( $type ) ) ) . '" title="' . esc_attr( sprintf( __( 'See the schema for %s', 'schema') , $type ) ) . '">
                	' . sprintf( $format, $type ) . '
                </a>';
		}
		
		/**
		 *
		 */
		public function admin_scripts( $hook ) {
			
			if ( $hook == 'settings_page_' . $this->page_slug() ) :
				wp_enqueue_style( 'schema-admin', plugins_url('/lib/css/schema-admin.css', __FILE__), array(), SC_VER, 'all' );
				wp_enqueue_style( 'schema-viewer', plugins_url('/lib/css/schema-viewer.css', __FILE__), array(), DJ_SCHEMAVIEWER_VERSION, 'all' );
			endif;
		}
		
		/**
		 * build out settings page
		 *
		 * @return ravenSchema
		 */
		public function add_pages() {
			
			add_submenu_page( 'options-general.php',
				 __('Schema Viewer', 'schema'),
				 __('Schema Viewer', 'schema'), 
				'manage_options', 
				$this->page_slug(), 
				array( $this, 'do_page' )
			);
			
		}

		/**
		 *
		 */
		public function do_page()
		{	
			if (!current_user_can( 'manage_options' ) )
				return;
			
			// Get the scraper
			$schema_scraper = DJ_SchemaScraper::singleton();
			$schema_scraper->get_schema_data(); // make sure fetch is done
			
			// Get the displayed type, default to first top level
			$top_level_schemas = $schema_scraper->get_top_level_schemas();
			$schema_type = isset( $_REQUEST[ 'schema' ] ) ? urldecode( $_REQUEST[ 'schema' ] ) : array_shift( $top_level_schemas );
			
			$is_datatype = $schema_scraper->is_datatype( $schema_type );
			$schema = $is_datatype ? $schema_scraper->get_datatype( $schema_type ) : $schema_scraper->get_schema( $schema_type );
			
			// Fallback for not found schema
			if ( empty( $schema ) ) :
				$schema_type = !empty( $top_level_schemas ) ? array_shift( $top_level_schemas ) : NULL;
				$schema = $schema_scraper->get_schema( $schema_type );
			endif;
			
			// Property time
			$schema_properties = $is_datatype ? $schema_scraper->get_datatype_properties( $schema_type, true, false ) : $schema_scraper->get_schema_properties( $schema_type, true, false );
			
			// Find the Family
			$schema_children = $is_datatype ? $schema_scraper->get_datatype_descendants( $schema_type, false ) : $schema_scraper->get_schema_descendants( $schema_type, false );
			$schema_siblings = !in_array( $schema_type, $top_level_schemas ) ? $schema_scraper->get_schema_siblings( $schema_type ) : array();
							
			// Start page display
			?> 	
			<div class="wrap">
				<div class="icon32" id="icon-schema"><br></div>
                <h2><?php _e('Schema Viewer', 'schema'); ?></h2>
                <div class="schema_listing">
                	<h2 class="page-title"><?php echo $schema_type; ?></h2>
                    <span class="page-description"><?php echo $schema_scraper->get_schema_comment( $schema_type ); ?></span>
                    
                    <table class="definition-table" cellspacing="3">
                    	<thead>
                        	<tr>
                            	<th><?php _e( 'Property', 'schema' ); ?></th>
                                <th><?php _e( 'Expected Type', 'schema' );?></th>
                                <th><?php _e( 'Description', 'schema' ); ?></th>
                            </tr>
                        </thead>
                        <?php foreach( $schema_properties as $type => $properties ): ?>
                        	<thead class="<?php if ( $type !== $schema_type ) echo "supertype "; ?>type">
                            	<tr>
                                	<th class="type-name" colspan="3">
                                    	<?php printf( __( 'Properties from %s', 'schema' ), 
											$this->get_link( $type ) ); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="<?php if ( $type !== $schema_type ) echo "supertype "; ?>type">
                            	<?php foreach( $properties as $property ) : ?>
                                	<tr>
                                        <th class="prop-nam" scope="row">
                                            <code><?php echo $property; ?></code>
                                        </th>
                                        <td class="prop-ect">
                                            <?php  
                                                $ranges = $schema_scraper->get_property_ranges( $property );
                                                
                                                // Create links
                                                foreach ( $ranges as &$range ) {
                                                    $range_schema = $schema_scraper->get_schema( $range );
                                                    if ( !empty( $range_schema ) || $schema_scraper->is_datatype( $range ) )
                                                        $range = $this->get_link( $range );
                                                }
												array_splice( $ranges, -2, 2, implode( _x( 'or', 'listing: last two items glue', 'schema' ), array_slice( $ranges, -2, 2 ) ) );
                                                echo implode( _x( ', ', 'listing: glue, space after comma', 'schema' ) , $ranges );
                                            ?>
                                        </td>
                                        <td class="prop-desc">
                                        	<?php echo $schema_scraper->get_property_comment( $property ); ?>
                                        </td>
                                	</tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endforeach; ?>
                    </table>
                    
                    <?php if ( !empty( $schema_children ) ) : ?>
                        <h3><?php _e( 'More specific types', 'schema' ); ?></h3>
                        <ul class="children">
                        <?php foreach( $schema_children as $child ) : ?>
                            <li class="subtype">
                                <?php echo $this->get_link( $child ); ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if ( !empty( $schema_siblings ) ) : ?> 
                        <h3><?php _e( 'Types with the same ancestor', 'schema' ); ?></h3>
                        <ul class="siblings">
                        <?php foreach( $schema_siblings as $sibling ) : ?>
                            <li class="siblingtype">
                                <?php echo $this->get_link( $sibling ); ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
				</div> <!-- end .schema_listing -->
			</div> <!-- end .wrap -->
            <?php
		}
	}
	
	$DJ_SchemaViewer = DJ_SchemaViewer::singleton();
}