<?php 
/*
  Scrapes the schemas from schema.org and processes them into php code.
  This does NOT scrape pages for schema data.

  Version: 1.0
  Author: Derk-Jan Karrenbeld
  Author URI: http://derk-jan.com
  
  Hooks:
  	- dj_schemascraper_fetched		: Runs when the schemas are fetched
  
  Filters:
  	- dj_schemascraper_scrapeurl	: Retrieves the schema scraping url
	- dj_schemascraper_cachepath	: Retrieves the schema cache path
*/

if (!class_exists("DJ_SchemaScraper")) 
{
	define("DJ_SCRAPE_BASE", plugin_basename(__FILE__));
	define("DJ_SCRAPE_VERSION", '1.0');
	
    if( !class_exists( 'WP_Http' ) )
        include_once( ABSPATH . WPINC. '/class-http.php' );
	
	class DJ_SchemaScraper {
		
		private static $singleton;
		private $options;
		private $schema_data;
		private $timestamp;
		private $last_error;
		
		/**
		 * Gets a singleton of this class
		 *
		 * DJ_SchemaScraper::singleton() will always return the same instance during a
		 * PHP processing stack. This way actions will not be queued duplicately and 
		 * caching of processed values is not neccesary.
		 *
		 * @returns the singleton instance
		 */
		public static function singleton() {
			if ( empty( DJ_SchemaScraper::$singleton ) )
				DJ_SchemaScraper::$singleton = new DJ_SchemaScraper();
			return DJ_SchemaScraper::$singleton;
		}
		
		/**
		 * Creates a new instance of DJ_SchemaScraper
		 *
		 * @remarks use DJ_SchemaScraper::singleton() outside the class hieracrchy
		 */
		protected function __construct() 
		{	
			$this->get_schema_data();	
		
			add_filter( 'dj_schemascraper_scrapeurl', array( &$this, 'get_scrapeurl' ) );
			add_filter( 'dj_schemascraper_cachepath', array( &$this, 'get_cachepath' ) );			
			//add_filter( 'raven_sc_admin_tooltip', array( &$this, 'get_tooltips' ) );
			add_filter( 'dj_scraper_default_settings', array( &$this, 'get_default_settings' ) );
			
			add_action( 'raven_sc_default_settings', array( &$this, 'default_settings' ) );
			add_action( 'raven_sc_register_settings', array( &$this, 'register_settings' ) );
			add_action( 'raven_sc_options_form', create_function( '', 'settings_fields(\'dj_schemascraper\'); do_settings_sections(\'dj_schemascraper\');' ) );
		}
		
		/**
		 * Gets an option value by key
		 */
		public function get_option( $key ) {
			if ( empty( $options ) )
				$options = get_option( 'dj_schemascraper' );
			return @$options[ $key ];
		}
		
		/**
		 * Runs when the admin initializes
		 */
		public function get_schema_data() 
		{
			if ( !empty( $this->schema_data ) && is_object( $this->schema_data ) )
				return $this->schema_data;
			
			$this->last_error = '';
			$url  =	$this->get_option( 'scrape_url' );
			$path = WP_CONTENT_DIR . $this->get_option( 'cache_path' );
			
			// Nope, we need to set options first
			if ( empty( $url ) || empty( $path ) )
				return;
				
			$file = basename( $url );
			
			// Try cached value
			if ( file_exists( $path ) && file_exists ( $path . $file ) ) :
				$this->schema_data = @json_decode( file_get_contents( $path . $file ) );
								
				if ( is_object( $this->schema_data ) ) :
					$cache_time = ( $this->get_option( 'cache_time' ) ?: 60 * 24 ) * 60;
					$this->timestamp = filemtime( $path . $file );
												
					$timestamp_now = microtime( true );
					if ( strtotime( $this->get_validation_date(). " + 2 days") > $timestamp_now ) {
						if ( $timestamp_now - $this->timestamp <= $cache_time ) 
							return;
					}
					
					if ( is_admin() ) {
						$this->last_error .= sprintf( "<div class='updated'><p>" . 
								__( 'Schemas invalidated. Cache time: %s, File time: %s (%s), Contents time: %s (%s), Timestamp: %s', 'schema' ) .
							"</p></div>",	
							gmdate("H:i:s", $cache_time),
							date_i18n("d M Y, H:i:s", $this->timestamp + get_option( 'gmt_offset' ) * 3600 ),
							gmdate("H:i:s", ($cache_time - ($timestamp_now - $this->timestamp) ) ),
							date_i18n("d M Y, H:i:s", strtotime( $this->get_validation_date(). " + 1 day") + get_option( 'gmt_offset' ) * 3600 ),
							gmdate("H:i:s", (strtotime( $this->get_validation_date(). " + 2 days" ) - $timestamp_now) ),
							date_i18n("d M Y, H:i:s", $timestamp_now + get_option( 'gmt_offset' ) * 3600 ) 
						);
						
						add_action( 'admin_notices' , array( &$this, 'notice_fetch' ) );
					}
				endif;
			endif;
			
			// Nope, we still need to fetch it
			$fetched_schema = @json_decode( $this->get_document( $url ) );
			if( is_object( $fetched_schema ) ) :
			
				// We got it, so try to write it
				if ( !is_wp_error( $fetched_schema ) ) :
					@file_put_contents( $path . $file, json_encode( $fetched_schema ) );	
					// Don't set it earlier, we might have an outdated
					// but still valid fetch from cache.
					$this->schema_data = $fetched_schema;
					$this->timestamp = microtime( true );
					
					do_action( 'dj_schemascraper_fetched', $this->schema_data );
				 	return;
				 endif;
				 
			endif;
			
			if ( is_admin() ) {
				
				$this->last_error .= sprintf( 
					"<div class='error'><p>" . __( 'Failed to fetch schema: ( %s ) from %s', 'schema' ) . "</p></div>",
					var_export(  $fetched_schema, true ),
					var_export( $url, true )
				);
				
				add_action( 'admin_notices' , array( &$this, 'notice_fetch' ) );
			}
		}
		
		/**
		 *
		 */
		public function notice_fetch( ) {
			echo $this->last_error;
			$this->last_error = '';
		}
		
		/**
		 *	Gets a document over an HTTP request
		 */
		public function get_document( $url ) {
			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) 
				return json_encode( $response );
			return $response["body"];
		}
		
		/**
		 * Gets date till witch this schema is valid
		 */
		public function get_validation_date() {
			return isset($this->schema_data->valid) ? $this->schema_data->valid : "today";	
		}
		
		/**
		 * Get all the schema types
		 */
		public function get_schemas() {
			return $this->schema_data->types;	
		}
				
		/**
		 * Get the schema for a type
		 */
		public function get_schema( $type ) {
			if ( is_object( $type ) )
				return $type;
			return isset( $this->get_schemas()->$type ) ? $this->get_schemas()->$type : NULL;
		}
		
		/**
		 * Gets the schema's id
		 */
		public function get_schema_id( $type ) {
			$type = is_object( $type ) ? $type : $this->get_schema( $type );
			return $type->id;
		}
		
		/**
		 * Gets the schemas with no parents
		 */
		public function get_top_level_schemas() {
			$results = array( );
			foreach( $this->get_schemas() as $type => $schema ) :
				$parents = $this->get_schema_ancestors( $schema );
				if ( empty( $parents ) )
					array_push( $results, $type );
			endforeach;
			return $results;
		}
		
		/**
		 *	Get all the ancestors of a type
		 */
		public function get_schema_ancestors( $type, $recursive = true ) {
			$schema = is_object( $type ) ? $type : $this->get_schema( $type );
			return $recursive ? $schema ->ancestors : $schema ->supertypes;
		}
		
		/**
		 * Get schema descendants
		 */
		public function get_schema_descendants( $type, $recursive = true ) {
			$schema = is_object( $type ) ? $type : $this->get_schema( $type );
			$result = $schema->subtypes;
			
			if ($recursive) :
				$results = array();
				foreach( $result as $descendant )
					 $results[$descendant] = $this->get_schema_descendants( $descendant, $recursive );
				return $results;
			endif;
			
			return $result;
		}
		
		/**
		 * Gets schema siblings
		 */
		public function get_schema_siblings( $type ) 
		{
			$results = array();
			$parents = $this->get_schema_ancestors( $type, false );
			if ( empty( $parents ) )
				$results = $this->get_top_level_schemas();
				
			foreach( $parents as $parent )
				$results += $this->get_schema_descendants( $parent, false);
			return array_diff( $results, array( $this->get_schema_id( $type ) ) );
		}
		
		/**
		 * Get all the properties of a type
		 */
		public function get_schema_properties( $type, $recursive = true, $flat = false ) {
			$type = is_object( $type ) ? $type : $this->get_schema( $type );
			$result = $type->specific_properties;
			
			// Parent properties
			if ($recursive) :
				$result = array( $this->get_schema_id( $type ) => $result );
				
				if ($flat) :
					return $type->properties;
				else :
					foreach( $this->get_schema_ancestors( $type ) as $ancestor )
						$result = $this->get_schema_properties( $ancestor, $recursive ) + $result;
				endif;
			endif;

			return $result;
		}
				
		/**
		 * Get the schema comment for a type
		 */
		public function get_schema_comment( $type, $html = true ) {
			$schema = is_object( $type ) ? $type : $this->get_schema( $type );
			return $html ? $schema ->comment : $schema ->comment_plain;
		}
		
		/**
		 * Get the URL of the schema
		 */
		public function get_schema_url( $type ) {
			if ( is_object( $type ) )
				return $type->url;
			return 	$this->get_schema( $type )->url;
		}
		
		/**
		 * Gets the properties data
		 */
		public function get_properties( ) {
			return $this->schema_data->properties;	
		}
		
		/**
		 * Gets the property keys
		 */
		public function get_property_keys( ) {
			$results = array();
			foreach( $this->get_properties() as $property => $data)
				array_push( $results, $property );
			return $results;
		}
		
		/**
		 * Gets a property object
		 */
		public function get_property( $property ) {
			return isset($this->get_properties()->$property) ? $this->get_properties()->$property : NULL;	
		}
		
		/**
		 * Gets the property id
		 */
		public function get_property_id( $property ) {
			if ( is_object( $property ) )
				return $property->id;
			return $this->get_property( $property )->id;	
		}
		
		/**
		 * Gets a property english display label
		 */
		public function get_property_label( $property ) {
			if ( is_object( $property ) )
				return $property->label;
			return $this->get_property( $property )->label;	
		}
		
		/**
		 * Gets a property description
		 */
		public function get_property_comment( $property, $html = true ) {
			$property = is_object( $property ) ? $property : $this->get_property( $property );
			return $html ? $property->comment : $property->comment_plain;	
		}
		
		/**
		 * Get property ranges (what is valid contents)
		 */
		public function get_property_ranges( $property ) {
			if ( is_object( $property ) )
				return $property->ranges;
			return $this->get_property( $property )->ranges;	
		}
		
		/**
		 * Get property domains (where is this used)
		 */
		public function get_property_domains( $property ) {
			if ( is_object( $property ) )
				return $property->domains;
			return $this->get_property( $property )->domains;	
		}
		
		/**
		 * Returns true if property is defined
		 */
		public function is_property( $property ) {
			return !is_null( $this->get_property( $property ) );	
		}
		
		/**
		 * Get datatypes
		 */
		public function get_datatypes() {
			return $this->schema_data->datatypes;	
		}
		
		/**
		 * Gets a single datatype
		 */
		public function get_datatype( $type ) {
			return isset($this->get_datatypes()->$type) ? $this->get_datatypes()->$type : NULL;
		}
		
		/**
		 *
		 */
		public function get_datatype_id( $type ) {
			if ( is_object( $type ) )
				return $type->id;
			return $this->get_datatype( $type )->id;
		}
		
		/**
		 *
		 */
		public function get_datatype_label( $type ) {
			if ( is_object( $type ) )
				return $type->label;
			return $this->get_datatype( $type )->label;
		}
		
		/**
		 * Gets the datatypes with no parents
		 */
		public function get_top_level_datatypes() {
			$results = array( );
			foreach( $this->get_datatypes() as $type => $datatype ) :
				$parents = $this->get_datatype_ancestors( $datatype );
				if ( empty( $parents ) )
					array_push( $results, $type );
			endforeach;
			return $results;
		}
		
		/**
		 *	Get all the ancestors of a datatype
		 */
		public function get_datatype_ancestors( $type, $recursive = true ) {
			$datatype = is_object( $type ) ? $type : $this->get_datatype( $type );
			return $recursive ? $datatype->ancestors : $datatype->supertypes;
		}
		
		/**
		 * Get datatype descendants
		 */
		public function get_datatype_descendants( $type, $recursive = true, $flat = false ) {
			$datatype = is_object( $type ) ? $type : $this->get_datatype( $type );
			$result = $datatype->subtypes;
			
			if ($recursive) :
				$results = array();
				
				foreach( $result as $descendant )
					if ( $flat ) :
						$results += array( $this->get_datatype_id( $descendant ) ) + $this->get_datatype_descendants( $descendant, $recursive );
					else :
						$results[ $descendant ] = $this->get_datatype_descendants( $descendant, $recursive );
					endif;
				
				return $results;
			endif;
			
			return $result;
		}
		
		/**
		 * Gets datatype siblings
		 */
		public function get_datatype_siblings( $type ) 
		{
			$results = array();
			$parents = $this->get_datatype_ancestors( $type, false );
			if ( empty( $parents ) )
				$results = $this->get_top_level_datatypes();
				
			foreach( $parents as $parent )
				$results += $this->get_datatype_descendants( $parent, false);
			return array_diff( $results, array( $this->get_datatype_id( $type ) ) );
		}
		
		/**
		 * Get all the properties of a datatype
		 */
		public function get_datatype_properties( $type, $recursive = true, $flat = false ) {
			$datatype = is_object( $type ) ? $type : $this->get_datatype( $type );
			$result = $datatype->specific_properties;
			
			// Parent properties
			if ($recursive) :
				$result = array( $this->get_datatype_id( $type ) => $result );
				
				if ($flat) :
					return $datatype->properties;
				else :
					foreach( $this->get_datatype_ancestors( $type ) as $ancestor )
						$result = $this->get_datatype_properties( $ancestor, $recursive ) + $result;
				endif;
			endif;
					
			return $result;
		}
		
		/**
		 * Gets a datatype comment
		 */
		public function get_datatype_comment( $type, $html = true ) {
			$datatype = is_object( $type ) ? $type : $this->get_datatype( $type );
			return $html ? $datatype ->comment : $datatype ->comment_plain;
		}
		
		/**
		 * Gets the datatype url
		 */
		public function get_datatype_url( $type ) {
			if ( is_object( $type ) )
				return $type->url;
			return $this->get_datatype( $type )->url;
		}
		
		/**
		 * Returns true if type is defined
		 */
		public function is_datatype( $type ) {
			return !is_null( $this->get_datatype( $type ) );
		}
		
		/**
		 * Registers new option group
		 */
		function register_settings() {
			register_setting( 'dj_schemascraper', 'dj_schemascraper', array(&$this, 'options_validate' ) );	
			
			// Scraper section
			add_settings_section( 'scraper_section', __('Schema Scraper', 'schema'), array( &$this, 'options_scraper_section' ), 'dj_schemascraper' );
			add_settings_field( 'scrape_url', __( 'Scrape URL', 'schema' ), array( &$this, 'options_scraper_scrapeurl' ), 'dj_schemascraper', 'scraper_section' );
			add_settings_field( 'cache_path', __( 'Cache Path', 'schema' ), array( &$this, 'options_scraper_cachepath' ), 'dj_schemascraper', 'scraper_section' );
			add_settings_field( 'cache_time', __( 'Cache Time', 'schema' ), array( &$this, 'options_scraper_cachetime' ), 'dj_schemascraper', 'scraper_section' );
			
		}
		
		/**
		 * Outputs the scraper section HTML
		 */
		function options_scraper_section() {
			echo '<p id="scraper_section">';
			_e( 'The scraper module tries to retrieve any arbitrary schema in JSON format. In the end, the schema creator should be able to parse this data and provide functionality for any schema on the scrape url. The schemas are fetched from the <code>scrape url</code> and saved at <code>cache_path</code>. The filename used is the same as fetched from the url. If a <code>valid</code> parameter is provided in the data retrieved, that + 1 day or fetch time + <code>cache_time</code>, whichever comes first, will invalidate the cache.', 'schema' );
			echo '</p>';
		}
		
		/**
		 * Outputs the scraper url field
		 */
		function options_scraper_scrapeurl() {
			echo '<input type="textfield" size="60" id="scraper_scrape_url" name="dj_schemascraper[scrape_url]" class="schema_textfield options-big"
				value="'.$this->get_option('scrape_url').'"/>';
		}
		
		/**
		 * Outputs the scraper cache path field
		 */
		function options_scraper_cachepath() {
			echo '<label for="scraper_cache_path">WP_CONTENT_DIR</labe> <input type="textfield" size="60" id="scraper_cache_path" 
				name="dj_schemascraper[cache_path]" class="schema_textfield options-big" 
				value="'.$this->get_option('cache_path').'"/>';
		}
		
		/**
		 *
		 */
		function options_scraper_cachetime() {
			echo '<input type="textfield" size="5" id="scraper_cache_time" name="dj_schemascraper[cache_time]" class="schema_textfield options-big" 
				value="'.$this->get_option('cache_time').'"/> <label for="scraper_cache_time">'._x( 'minutes', 'cache time', 'schema' ).'</label>';
		}
		
		/**
		 * Validates the options
		 */
		function options_validate( $input ) {
			//$input["scrape_url"]
			//$input["cache_path"] 
			$input["cache_time"] = max( array(0, floatval( $input["cache_time"] ) ) );
			return $input;
		}
		
		/**
		 * Sets default settings
		 */
		public function default_settings( ) {
			
			$options_check	= get_option('dj_schemascraper');
			if(empty( $options_check ))
				$options_check = array();
	
			// Fetch defaults.
			$default = array();
			$default = apply_filters( 'dj_scraper_default_settings', &$default );
			
			// Existing optons will override defaults
			update_option('dj_schemascraper', $default + $options_check);
		}
		
		/**
		 * Gets the default settings
		 */
		public function get_default_settings( $default ) {
			
			$default["scrape_url"] = "http://schema.rdfs.org/all.json";
			$default["cache_path"] = '/cache/';
			return $default;	
		}
			
	}
	
	$DJ_SchemaScraper = DJ_SchemaScraper::singleton();
}