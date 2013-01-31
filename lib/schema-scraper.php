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
	define("DJ_SCHEMASCRAPE_BASE", plugin_basename(__FILE__));
	define("DJ_SCHEMASCRAPE_VERSION", '1.0');
	
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
		 * @return DJ_SchemaScraper the singleton instance
		 */
		public static function singleton() {
			if ( empty( DJ_SchemaScraper::$singleton ) )
				DJ_SchemaScraper::$singleton = new DJ_SchemaScraper();
			return DJ_SchemaScraper::$singleton;
		}
		
		/**
		 * Creates a new instance of DJ_SchemaScraper
		 *
		 * @link DJ_SchemaScraper::singleton() use outside the class hieracrchy
		 */
		protected function __construct() 
		{	
			$this->get_schema_data();	
		
			add_filter( 'dj_schemascraper_scrapeurl', array( $this, 'get_scrapeurl' ) );
			add_filter( 'dj_schemascraper_cachepath', array( $this, 'get_cachepath' ) );			
			add_filter( 'dj_scraper_default_settings', array( $this, 'get_default_settings' ) );
			
			add_action( 'dj_sc_onactivate', array( $this, 'default_settings' ) );
			add_action( 'dj_sc_register_settings', array( $this, 'register_settings' ) );
			add_action( 'dj_sc_options_form', create_function( '', 'settings_fields(\'dj_schemascraper\'); do_settings_sections(\'dj_schemascraper\');' ) );
		}
		
		/**
		 * Gets an option value by key
		 *
		 * @param string $key the option key
		 * @return mixed the option value
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
			$fetch_disabled = false;

			// Nope, we need to set options first
			if ( empty( $url ) || empty( $path ) || $path == WP_CONTENT_DIR ) :
				$file = 'all.json';
				$path = plugin_dir_path( __FILE__ ) . '';
				$fetch_disabled = true;
			else:
				$file = basename( $url );
			endif;
			
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
							date_i18n("d M Y, H:i:s", strtotime( $this->get_validation_date(). " + 2 day") + get_option( 'gmt_offset' ) * 3600 ),
							gmdate("H:i:s", (strtotime( $this->get_validation_date(). " + 2 days" ) - $timestamp_now) ),
							date_i18n("d M Y, H:i:s", $timestamp_now + get_option( 'gmt_offset' ) * 3600 ) 
						);
						
						add_action( 'admin_notices' , array( $this, 'notice_fetch' ) );
					}
				endif;
			endif;
			
			if ( $fetch_disabled || empty( $url ) )
				return;
			
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
				
				add_action( 'admin_notices' , array( $this, 'notice_fetch' ) );
			}
		}
		
		/**
		 * Fetches notices
		 */
		public function notice_fetch( ) {
			echo $this->last_error;
			$this->last_error = '';
		}
		
		/**
		 * Gets a document over an HTTP request
		 *
		 * @param string $url the url to the scrapped database
		 * @return string|object the body of the document or http error
		 */
		public function get_document( $url ) {
			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) 
				return json_encode( $response );
			return $response["body"];
		}
		
		/**
		 * Gets date till witch this schema is valid
		 *
		 * @return string strtotime valid timestamp string
		 */
		public function get_validation_date() {
			return isset($this->schema_data->valid) ? $this->schema_data->valid : "today";	
		}
		
		/**
		 * Get all the schema types
		 *
		 * @return object[] array of objects by type name
		 */
		public function get_schemas() {
			return $this->schema_data->types;	
		}
				
		/**
		 * Get the schema for a type
		 *
		 * @param string|object $type either string or type object
		 * @return object the type object
		 */
		public function get_schema( $type ) {
			if ( is_object( $type ) )
				return $type;
			return isset( $this->get_schemas()->$type ) ? $this->get_schemas()->$type : NULL;
		}
		
		/**
		 * Gets the schema's id
		 *
		 * @param string|object $type either string or type object
		 * @return object the type id	
		 */
		public function get_schema_id( $type ) {
			$type = is_object( $type ) ? $type : $this->get_schema( $type );
			return $type->id;
		}
		
		/**
		 * Gets the schemas with no parents
		 * 
		 * @return string[] the top level schemas
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
		 * Get all the ancestors of a type
		 *
		 * @param string|object $type either string or type object
		 * @param boolean $recursive grap recursively flag
		 * @return string[] type names of the ancestors
		 */
		public function get_schema_ancestors( $type, $recursive = true ) {
			$schema = is_object( $type ) ? $type : $this->get_schema( $type );
			return $recursive ? $schema ->ancestors : $schema ->supertypes;
		}
		
		/**
		 * Get schema descendants
		 *
		 * @param string|object $type either string or type object
		 * @param boolean $recursive grap recursively flag
		 * @return string[]|string[][] type names of the descendants
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
		 *
		 * @param string|object $type either string or type object
		 * @return string[] type names of the siblings
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
		 *
		 * @param string|object $type either string or type object
		 * @param boolean $recursive to grab all the properties of all the ancestors flag
		 * @param boolean $flat to flatten the results array or per type
		 * @return string[]|string[][] property names
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
		 *
		 * @param string|object $type either string or type object
		 * @param boolean $html to output in html flag
		 * @return the type comment
		 */
		public function get_schema_comment( $type, $html = true ) {
			$schema = is_object( $type ) ? $type : $this->get_schema( $type );
			return $html ? $schema ->comment : $schema ->comment_plain;
		}
		
		/**
		 * Get the URL of the schema
		 * 
		 * @param string|object $type either string or type object
		 * @return schema url
		 */
		public function get_schema_url( $type ) {
			if ( is_object( $type ) )
				return $type->url;
			return 	$this->get_schema( $type )->url;
		}
		
		/**
		 * Gets the properties data
		 *
		 * @return object[] the properties data
		 */
		public function get_properties( ) {
			return $this->schema_data->properties;	
		}
		
		/**
		 * Gets the property keys
		 * 
		 * @return string[] the property names
		 */
		public function get_property_keys( ) {
			$results = array();
			foreach( $this->get_properties() as $property => $data)
				array_push( $results, $property );
			return $results;
		}
		
		/**
		 * Gets a property object
		 *
		 * @param string|object $property the property id or object
		 * @return object that property
		 */
		public function get_property( $property ) {
			return isset($this->get_properties()->$property) ? $this->get_properties()->$property : NULL;	
		}
		
		/**
		 * Gets the property id
		 *
		 * @param string|object $property the property id or object
		 * @return string the property's id
		 */
		public function get_property_id( $property ) {
			if ( is_object( $property ) )
				return $property->id;
			return $this->get_property( $property )->id;	
		}
		
		/**
		 * Gets a property english display label
		 *
		 * @param string|object $property the property id or object
		 * @return string the property's label
		 */
		public function get_property_label( $property ) {
			if ( is_object( $property ) )
				return $property->label;
			return $this->get_property( $property )->label;	
		}
		
		/**
		 * Gets a property description
		 *
		 * @param string|object $property the property id or object
		 * @param boolean $html to output as html flag
		 * @return string the property's comment
		 */
		public function get_property_comment( $property, $html = true ) {
			$property = is_object( $property ) ? $property : $this->get_property( $property );
			return $html ? $property->comment : $property->comment_plain;	
		}
		
		/**
		 * Get property ranges (what is valid contents)
		 *
		 * @param string|object $property the property id or object
		 * @return string[] the types that are valid input
		 */
		public function get_property_ranges( $property ) {
			if ( is_object( $property ) )
				return $property->ranges;
			return $this->get_property( $property )->ranges;	
		}
		
		/**
		 * Get property domains (where is this used)
		 *
		 * @param string|object $property the property id or object
		 * @return string[] the types that have this property
		 */
		public function get_property_domains( $property ) {
			if ( is_object( $property ) )
				return $property->domains;
			return $this->get_property( $property )->domains;	
		}
		
		/**
		 * Returns true if property is defined
		 *
		 * @param string|object $property the property id or object
		 * @return boolean exists
		 */
		public function is_property( $property ) {
			return !is_null( $this->get_property( $property ) );	
		}
		
		/**
		 * Get datatypes
		 *
		 * @return object[] the datatypes
		 */
		public function get_datatypes() {
			return $this->schema_data->datatypes;	
		}
		
		/**
		 * Gets a single datatype
		 *
		 * @param string|object $type the datatype id or object
		 * @return object that datatype
		 */
		public function get_datatype( $type ) {
			return isset($this->get_datatypes()->$type) ? $this->get_datatypes()->$type : NULL;
		}
		
		/**
		 * Gets a datatype's id
		 *		 
		 * @param string|object $type the datatype id or object
		 * @return string the datatype id
		 */
		public function get_datatype_id( $type ) {
			if ( is_object( $type ) )
				return $type->id;
			return $this->get_datatype( $type )->id;
		}
		
		/**
		 * Gets a datatype's label
		 * 
		 * @param string|object $type the datatype id or object
		 * @return string the label of the datatype
		 */
		public function get_datatype_label( $type ) {
			if ( is_object( $type ) )
				return $type->label;
			return $this->get_datatype( $type )->label;
		}
		
		/**
		 * Gets the datatypes with no parents
		 * 
		 * @return string[] toplevel datatypes
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
		 * Get all the ancestors of a datatype
		 *
		 * @param string|object $type the datatype id or object
		 * @param boolean $recursive grab all the ancestors of datatype
		 * @return string[] datatype names
		 */
		public function get_datatype_ancestors( $type, $recursive = true ) {
			$datatype = is_object( $type ) ? $type : $this->get_datatype( $type );
			return $recursive ? $datatype->ancestors : $datatype->supertypes;
		}
		
		/**
		 * Get datatype descendants
		 * @param string|object $type the datatype id or object
		 * @param boolean $recursive to grab all the descendant datatypes of all the descendants flag
		 * @param boolean $flat to flatten the results array or per type
		 * @return string[]|string[][] datatype names
		 */
		public function get_datatype_descendants( $type, $recursive = true, $flat = false ) {
			$datatype = is_object( $type ) ? $type : $this->get_datatype( $type );
			$result = $datatype->subtypes;
			
			if ($recursive) :
				$results = array();
				
				foreach( $result as $descendant )
					if ( $flat ) :
						array_push( $results, $this->get_datatype_id( $descendant ) );
						$results = array_merge( $results, $this->get_datatype_descendants( $descendant, $recursive, $flat ) );
					else :
						$results[ $descendant ] = $this->get_datatype_descendants( $descendant, $recursive, $flat );
					endif;
				
				return $results;
			endif;
			
			return $result;
		}
		
		/**
		 * Gets datatype siblings
		 * 
		 * @param string|object $type the datatype id or object
		 * @return string[] the siblings
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
		 *
		 * @param string|object $type either string or datatype object
		 * @param boolean $recursive to grab all the properties of all the ancestors flag
		 * @param boolean $flat to flatten the results array or per type
		 * @return string[]|string[][] property names
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
		 * 
		 * @param string|object $type the datatype id or object
		 * @param boolean $html output as html flag
		 * @return string the comment
		 */
		public function get_datatype_comment( $type, $html = true ) {
			$datatype = is_object( $type ) ? $type : $this->get_datatype( $type );
			return $html ? $datatype ->comment : $datatype ->comment_plain;
		}
		
		/**
		 * Gets the datatype url
		 * 
		 * @param string|object $type the datatype id or object
		 * @return string the url to the datatype
		 */
		public function get_datatype_url( $type ) {
			if ( is_object( $type ) )
				return $type->url;
			return $this->get_datatype( $type )->url;
		}
		
		/**
		 * Returns true if type is defined
		 * 
		 * @return boolean datatype exists
		 */
		public function is_datatype( $type ) {
			return !is_null( $this->get_datatype( $type ) );
		}
		
		/**
		 * Registers new option group
		 */
		function register_settings() {
			register_setting( 'dj_schemascraper', 'dj_schemascraper', array($this, 'options_validate' ) );	
			
			// Scraper section
			add_settings_section( 'scraper_section', __('Schema Scraper', 'schema'), array( $this, 'options_scraper_section' ), 'dj_schemascraper' );
			add_settings_field( 'scrape_url', __( 'Scrape URL', 'schema' ), array( $this, 'options_scraper_scrapeurl' ), 'dj_schemascraper', 'scraper_section' );
			add_settings_field( 'cache_path', __( 'Cache Path', 'schema' ), array( $this, 'options_scraper_cachepath' ), 'dj_schemascraper', 'scraper_section' );
			add_settings_field( 'cache_time', __( 'Cache Time', 'schema' ), array( $this, 'options_scraper_cachetime' ), 'dj_schemascraper', 'scraper_section' );
			add_settings_field( 'current_timestamp', __( 'Current Cache', 'schema' ), array( $this, 'options_scraper_current_timestamp' ), 'dj_schemascraper', 'scraper_section' );
			
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
		
		function options_scraper_current_timestamp() {
			
			$currtime = microtime( true );
			
			$cache_time = ( $this->get_option( 'cache_time' ) ?: 60 * 24 ) * 60;
			$filetime = date_i18n("d M Y, H:i:s", $this->timestamp + get_option( 'gmt_offset' ) * 3600 );
			$fileexpi = date_i18n("d M Y, H:i:s", ($this->timestamp + get_option( 'gmt_offset' ) * 3600 + $cache_time) );
			$dataexpi = date_i18n("d M Y, H:i:s", strtotime( $this->get_validation_date(). " + 2 days") );
			
			echo 'data timestamp: <code>' . $filetime . '</code>.<br>';
			echo 'data expiration date: <code>' . $dataexpi . '</code><br>';
			echo 'file expiration date: <code>' . $fileexpi  . '</code>.';
		}
		
		/**
		 * Validates the options
		 *
		 * @param mixed[] $input the to be processed option values
		 * @return mixed[] the processed option values
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
			$default = apply_filters( 'dj_scraper_default_settings', $default );
			
			// Existing optons will override defaults
			update_option('dj_schemascraper', $default + $options_check);
		}
		
		/**
		 * Gets the default settings
		 *
		 * @param mixed[] $default the current defaults
		 * @return mixed[] the defaults
		 */
		public function get_default_settings( $default = array() ) {
			
			$default["scrape_url"] = "http://schema.rdfs.org/all.json";
			$default["cache_path"] = '/cache/';
			return $default;	
		}
			
	}
	
	$DJ_SchemaScraper = DJ_SchemaScraper::singleton();
}