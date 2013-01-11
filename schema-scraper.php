<?php 
/*
  Scrapes the schemas from schema.org and processes them into php code.

  Version: 1.0
  Author: Derk-Jan Karrenbeld
  Author URI: http://derk-jan.com
  
  Hooks:
  
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
			add_filter( 'dj_schemascraper_scrapeurl', array( &$this, 'get_scrapeurl' ) );
			add_filter( 'dj_schemascraper_cachepath', array( &$this, 'get_cachepath' ) );			
			//add_filter( 'raven_sc_admin_tooltip', array( &$this, 'get_tooltips' ) );
			add_filter( 'dj_scraper_default_settings', array( &$this, 'get_default_settings' ) );
			
			add_action( 'raven_sc_default_settings', array( &$this, 'default_settings' ) );
			add_action( 'raven_sc_register_settings', array( &$this, 'register_settings' ) );
			add_action( 'raven_sc_options_form', create_function( '', 'settings_fields(\'dj_schemascraper\'); do_settings_sections(\'dj_schemascraper\');' ) );
			add_action( 'admin_init', array( &$this, 'retrieve_schema_data' ) );
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
		public function retrieve_schema_data() 
		{
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
					$cache_time = $this->get_option( 'cache_time' ) ?: 3600;
					$this->timestamp = filemtime( $path . $file );
														
					$timestamp_now = microtime( true );
					if (strtotime( $this->get_validation_date(). " + 1 day") <= $timestamp_now)
						if ($this->timestamp && ($timestamp_now - $this->timestamp) <= $cache_time)
							return;
				endif;
			endif;
			
			// Nope, we still need to fetch it
			$this->schema_data = @json_decode( $this->get_document( $url ) );
			if( is_object( $this->schema_data ) ) :
				 $this->timestamp = microtime( true );
				 
				// We got it, so try to write it
				if ( !is_wp_error( $this->schema_data ) )
					@file_put_contents( $path . $file, json_encode( $this->schema_data ) );	
			endif;
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
			return isset($this->get_schemas()->$type) ? $this->get_schemas()->$type : NULL;
		}
		
		/**
		 *	Get all the ancestors of a type
		 */
		public function get_schema_ancestors( $type, $recursive = true ) {
			return $recursive ? $this->get_schema( $type )->ancestors : $this->get_schema( $type )->supertypes;
		}
		
		/**
		 * Get schema descendants
		 */
		public function get_schema_descendants( $type, $recursive = true ) {
			$result = $this->get_schema( $type )->subtypes;
			
			if ($recursive) :
				$results = array();
				foreach( $result as $descendant )
					 $results[$descendant] = $this->get_schema_descendants( $descendant, $recursive );
				return $results;
			endif;
			
			return $result;
		}
		
		/**
		 * Get all the properties of a type
		 */
		public function get_schema_properties( $type, $recursive = true, $flat = false ) {
			$result = $this->get_schema( $type )->specific_properties;
			
			// Parent properties
			if ($recursive) :
				$result = array( $type => $result );
				
				if ($flat) :
					return $this->get_schema( $type )->properties;
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
			return $html ? $this->get_schema( $type )->comment : $this->get_schema( $type )->comment_plain;
		}
		
		/**
		 * Get the URL of the schema
		 */
		public function get_schema_url( $type ) {
			return 	$this->get_schema( $type )->url;
		}
		
		/**
		 * Gets the properties data
		 */
		public function get_properties() {
			return $this->schema_data->properties;	
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
			return $this->get_property( $property )->id;	
		}
		
		/**
		 * Gets a property english display label
		 */
		public function get_property_label( $property ) {
			return $this->get_property( $property )->label;	
		}
		
		/**
		 * Gets a property description
		 */
		public function get_property_comment( $property, $html = true ) {
			return $html ? $this->get_property( $property )->comment :
				 $this->get_property( $property )->comment_plain;	
		}
		
		/**
		 * Get property ranges (what is valid contents)
		 */
		public function get_property_ranges( $property ) {
			return $this->get_property( $property )->ranges;	
		}
		
		/**
		 * Get property domains (where is this used)
		 */
		public function get_property_domains( $property ) {
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
			return $this->get_datatype( $type )->id;
		}
		
		/**
		 *
		 */
		public function get_datatype_label( $type ) {
			return $this->get_datatype( $type )->label;
		}
		
		/**
		 *	Get all the ancestors of a datatype
		 */
		public function get_datatype_ancestors( $type, $recursive = true ) {
			return $recursive ? $this->get_datatype( $type )->ancestors : $this->get_datatype( $type )->supertypes;
		}
		
		/**
		 * Get datatype descendants
		 */
		public function get_datatype_descendants( $type, $recursive = true ) {
			$result = $this->get_datatype( $type )->subtypes;
			
			if ($recursive) :
				$results = array();
				foreach( $result as $descendant )
					 $results[$descendant] = $this->get_datatype_descendants( $descendant, $recursive );
				return $results;
			endif;
			
			return $result;
		}
		
		/**
		 * Gets a datatype comment
		 */
		public function get_datatype_comment( $type, $html = true ) {
			return $html ? $this->get_datatype( $type )->comment : $this->get_datatype( $type )->comment_plain;
		}
		
		/**
		 *
		 */
		public function get_datatype_url( $type ) {
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
			_e( 'The scraper module tries to retrieve any arbitrary schema in JSON format. In the end, the schema creator should be able to parse this data and provide functionality for any schema on the scrape url.', 'schema' );
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
				value="'.$this->get_option('cache_time').'"/> <label for="scraper_cache_time">'._x( 'seconds', 'cache time', 'schema' ).'</label>';
		}
		
		/**
		 * Validates the options
		 */
		function options_validate( $input ) {
			//$input["scrape_url"]
			//$input["cache_path"] 
			$input["cache_time"] = max( array(0, intval( $input["cache_time"] ) ) );
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