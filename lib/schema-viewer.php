<?php 
/*
  Lists schemas scraped with the scraper

  Version: 1.0
  Author: Derk-Jan Karrenbeld
  Author URI: http://derk-jan.com
  
*/

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
			return 'dj-schema-viewer';
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
		public function get_link( $type, $format = '%s', $classes = 'schema' ) {
			return '<a class="' . $classes . '" href="' . esc_attr( esc_url( $this->get_url( $type ) ) ) . '" title="' . esc_attr( sprintf( __( 'See the schema for %s', 'schema') , $type ) ) . '">' . 
					sprintf( $format, $type ) . 
					'</a>';
		}
		
		/**
		 *
		 */
		public function get_starring_link( $type, $format = '%s %s', $linkdo_text = NULL, $linkdont_text = NULL, 
			$is_text = NULL, $isnt_text = NULL, $title_format = NULL, $classes = 'action' ) {
				
			$do = $this->is_starred( $type ) ? 'off' : 'on';
			
			return sprintf( $format, 
				sprintf( 
					__( '%s is %s.', 'schema'), 
					$type, 
					$do == 'on' ? 
						( $isnt_text ?: __( 'not starred', 'schema') ) : 
						( $is_text ?: __( 'starred', 'schema' ) )
				),
				'<a class="' . $classes . '" href="' . 
					esc_attr( esc_url( $this->get_url( $type ) . '&action=star&do=' . $do ) ) . '" ' .
					'title="' . esc_attr( 
						sprintf( 
							$title_format ?: __( 'Set starred value of %s to %s', 'schema') , 
							$type, 
							$do 
						) 
					) . '">' . 
					( $do == 'on' ? 
						$linkdo_text ?: __( 'Star', 'schema' ) :
						$linkdont_text ?: __( 'Unstar', 'schema' ) 
					) . 
				'</a>'
			);
		}
		
		/**
		 *
		 */
		public function is_starred( $type ) {
			$schema_creator = DJ_SchemaCreator::singleton();
			$starred = $schema_creator->get_option( 'starred_schemas' );
			return array_search( $type, $starred ) !== false;
		}
		
		/**
		 *
		 */
		public function get_property_root_toggle( $type, $prop ) {
			
			$disabled = $this->is_root_disabled( $type, $prop );
			$do = $disabled ? 'on' : 'off';
			return '<a class="action root" href="' . 
					esc_attr( esc_url( $this->get_url( $type ) . '&action=root&do=' . $do . '&prop=' . $prop ) ) . '" ' .
					'title="' . esc_attr( 
						sprintf( 
							$title_format ?: __( 'Set property %s:%s when root to %s', 'schema') , 
							$type, 
							$prop,
							$do 
						) 
					) . '">' . 
					( $do == 'on' ? '&#9744' : '&#9745'  ) . 
				'</a>';
		}
		
		/**
		 *
		 */
		public function get_property_embed_toggle( $type, $prop ) {
			
			$disabled = $this->is_embed_disabled( $type, $prop );
			$do = $disabled ? 'on' : 'off';
			return '<a class="action embed" href="' . 
					esc_attr( esc_url( $this->get_url( $type ) . '&action=embed&do=' . $do . '&prop=' . $prop ) ) . '" ' .
					'title="' . esc_attr( 
						sprintf( 
							$title_format ?: __( 'Set property %s:%s when embedded to %s', 'schema') , 
							$type, 
							$prop,
							$do 
						) 
					) . '">' . 
					( $do == 'on' ? '&#9744' : '&#9745'  ) . 
				'</a>';
		}
		
		/**
		 *
		 */
		public function is_root_disabled( $type, $property ) {
			$schema_creator = DJ_SchemaCreator::singleton();
			return 	$schema_creator->is_root_disabled( $type, $property );
		}
		
		/**
		 *
		 */
		public function is_embed_disabled( $type, $property ) {
			$schema_creator = DJ_SchemaCreator::singleton();
			return 	$schema_creator->is_embed_disabled( $type, $property );
		}
		
		/**
		 *
		 */
		public function admin_scripts( $hook ) {
			
			if ( $hook == 'settings_page_' . $this->page_slug() ) :
				wp_enqueue_style( 'schema-viewer', plugins_url( 'css/schema-viewer.css' , __FILE__ ), array(), DJ_SCHEMAVIEWER_VERSION, 'all' );
			endif;
		}
		
		/**
		 * build out settings page
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
		public function do_actions() {
			if ( empty( $_REQUEST[ 'action' ] ) || empty( $_REQUEST[ 'schema' ] ) )
				return;
				
			$action = $_REQUEST[ 'action' ];
			$schema_type = $_REQUEST[ 'schema' ];
			
			$schema_creator = DJ_SchemaCreator::singleton();
			$options = $schema_creator->get_options();
			
			switch( $action ) :
			
				case 'star':
					$starred = $options[ 'starred_schemas' ];
					if ( $_REQUEST[ 'do' ] == 'on' ) :
						$starred = array_unique( array_merge( $starred, array( $schema_type ) ) );
					elseif ( $_REQUEST[ 'do' ] == 'off' ) :
						$key = array_search( $schema_type , $starred );
						if ( $key !== false ) :
							unset(  $starred[ $key ] );
							 $starred = array_values(  $starred );
						endif;
					endif;
					$options[ 'starred_schemas' ] = $starred;
				break;
				
				case 'root':
				case 'embed':	
					$schema_prop = $_REQUEST[ 'prop' ];
					$properties = $options[ 'schema_properties' ] ?: array();

					if ( !isset( $properties[ $schema_type ] ) )
						$properties[ $schema_type ] = array();
					if ( !isset( $properties[ $schema_type ][ $schema_prop ] ) )
						$properties[ $schema_type ][ $schema_prop ] = 0;
						
					$mask = $action == 'root' ? DJ_SchemaCreator::OptionRootDisabled : DJ_SchemaCreator::OptionEmbedDisabled;

					if ( $_REQUEST[ 'do' ] == 'on' )
						$properties[ $schema_type ][ $schema_prop ] &= (~$mask);
					else if ( $_REQUEST[ 'do' ] == 'off') 
						$properties[ $schema_type ][ $schema_prop ] |= $mask;	
									
					$options[ 'schema_properties' ] = $properties;
				break;
			endswitch;
			
			$schema_creator->set_options( $options );
		}

		/**
		 *
		 */
		public function do_page()
		{	
			if (!current_user_can( 'manage_options' ) )
				return;
				
			$this->do_actions();
			
			// Get the scraper
			$schema_scraper = DJ_SchemaScraper::singleton();
			$schema_scraper->get_schema_data(); // make sure fetch is done
			
			// Get the displayed type, default to first top level
			$top_level_schemas = $schema_scraper->get_top_level_schemas();
			$schema_type = isset( $_REQUEST[ 'schema' ] ) ? urldecode( $_REQUEST[ 'schema' ] ) : array_shift( $top_level_schemas );
			$is_datatype = $schema_scraper->is_datatype( $schema_type );
			$schema = $is_datatype ? $schema_scraper->get_datatype( $schema_type ) : $schema_scraper->get_schema( $schema_type );
			
			// Replace toplevel if datatype
			if ( $is_datatype ) :
				$top_level_schemas = $schema_scraper->get_top_level_datatypes();
			endif;
			
			// Fallback for not found schema
			if ( empty( $schema ) ) :
				$schema_type = !empty( $top_level_schemas ) ? array_shift( $top_level_schemas ) : NULL;
				$schema = $schema_scraper->get_schema( $schema_type );
			endif;
			
			// Property time
			$schema_properties = $is_datatype ? array() : $schema_scraper->get_schema_properties( $schema, true, false ); 
			// $schema_scraper->get_datatype_properties( $schema, true, false ) is available, but datatypes don't have props
			
			// Find the Family
			$schema_parents = $schema_scraper->get_datatype_ancestors( $schema, true );
			$schema_children = $schema_scraper->get_schema_descendants( $schema, false );
			$schema_siblings = !in_array( $schema_type, $top_level_schemas ) ? 
				( $is_datatype ? $schema_scraper->get_datatype_siblings( $schema ) : $schema_scraper->get_schema_siblings( $schema ) ) : 
				array();
				
			// Base type
			$base_types = array( 'URL', 'Text' );
			
			// Action time
			$schema_actions = $this->get_starring_link( $schema_type );
							
			// Start page display
			?> 	
			<div class="wrap">
				<div class="icon32" id="icon-schema"><br></div>
                <h2><?php _e('Schema Viewer', 'schema'); ?></h2>
                <div class="schema-listing">
                	<h2 class="page-title">
					<?php 
						$title = array();
						$star = $this->get_starring_link( $schema_type, ' %2$s', '&#9734;', '&#9733', '', '', NULL, 'action star');
						foreach( $schema_parents as $parent ) 
							$title[] = $this->get_link( $parent, '%s', $is_datatype ? 'datatype' : 'schema' );
						$title[] = $this->get_link( $schema_type, '%s', $is_datatype ? 'datatype' : 'schema' ) . $star; 
						echo implode( ' > ', $title );
					?>
                    </h2>
                    <p class="page-description"><?php echo $schema_scraper->get_schema_comment( $schema ); ?></p>
                    
                    <?php if ( !$is_datatype ) : ?>
                        <table class="definition-table" cellspacing="3">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Property', 'schema' ); ?></th>
                                    <th><?php _e( 'Expected Type', 'schema' );?></th>
                                    <th><?php _e( 'Description', 'schema' ); ?></th>
                                    <th><?php _e( 'Root', 'schema' ); ?></th>
                                    <th><?php _e( 'Embed', 'schema' ); ?></th>
                                </tr>
                            </thead>
                            <?php foreach( $schema_properties as $type => $properties ): ?>
                                <thead class="<?php if ( $type !== $schema_type ) echo "supertype "; ?>type">
                                    <tr>
                                        <th class="type-name" colspan="5">
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
                                                        if ( !empty( $range_schema ) )
                                                            $range = $this->get_link( $range );
														else if ( !in_array( $range, $base_types ) && $schema_scraper->is_datatype( $range ) )
															$range = $this->get_link( $range, '%s', 'datatype' );
                                                    }
                                                    array_splice( $ranges, -2, 2, implode( _x( ' or ', 'listing: last 2 items glue', 'schema' ), array_slice( $ranges, -2, 2 ) ) );
                                                    echo implode( _x( ', ', 'listing: glue, space after comma', 'schema' ) , $ranges );
                                                ?>
                                            </td>
                                            <td class="prop-desc">
                                                <?php echo $schema_scraper->get_property_comment( $property ); ?>
                                            </td>
                                            <td class="prop-root">
                                            	<?php echo $this->get_property_root_toggle( $schema_type, $property ); ?>
                                            </td>
                                            <td class="prop-embed">
                                            	<?php echo $this->get_property_embed_toggle( $schema_type, $property ); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                    
                    <?php if ( !empty( $schema_children ) ) : ?>
                        <h3><?php _e( 'More specific types', 'schema' ); ?></h3>
                        <ul class="children">
                        <?php foreach( $schema_children as $child ) : ?>
                            <li class="subtype">
                                <?php echo $this->get_link( $child, '%s', $is_datatype ? 'datatype' : 'schema' ); ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if ( !empty( $schema_siblings ) ) : ?> 
                        <h3><?php 
							printf(
								__( 'Types that are also a %s', 'schema' ), 
								$this->get_link( $schema_parents[ count( $schema_parents ) - 1 ], '%s', $is_datatype ? 'datatype' : 'schema' )
							); 
						?></h3>
                        <ul class="siblings">
                        <?php foreach( $schema_siblings as $sibling ) : ?>
                            <li class="siblingtype">
                                <?php echo $this->get_link( $sibling, '%s', $is_datatype ? 'datatype' : 'schema' ); ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    	<h3><?php _e( 'Different types', 'schema' ); ?></h3>
                    <?php 
						// Branch to other lists
						$switch_links = $is_datatype ? 
							$schema_scraper->get_top_level_schemas() :
							$schema_scraper->get_top_level_datatypes();
						echo $this->get_link( array_shift( $switch_links ), 
							$is_datatype ? __( 'See schema types' , 'schema' ) : __( 'See data types' , 'schema' ), 
							!$is_datatype ? 'datatype' : 'schema' ); 
					?>
                    
                    <?php if ( !empty( $schema_actions ) ) : ?>
                    	<h3><?php _e( 'Actions', 'schema' ); ?></h3>
                        <span class="schema-actions"><?php echo $schema_actions; ?></span>
                    
                    <?php endif; ?>
				</div> <!-- end .schema_listing -->
			</div> <!-- end .wrap -->
            <?php
		}
	}
	
	$DJ_SchemaViewer = DJ_SchemaViewer::singleton();
}