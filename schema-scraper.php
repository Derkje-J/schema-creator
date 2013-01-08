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
	
	class DJ_SchemaScraper {
		
		private static $singleton;
		private $options;
		private $schema_data;
		
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
		 * Gets an option value by key
		 */
		public function get_option( $key ) {
			if ( empty( $options ) )
				$options = get_option( 'dj_schemascraper' );
			return @$options[ $key ];
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
			
			add_action( 'admin_init', array( &$this, 'retrieve_schema_data' ) );
		}
		
		/**
		 * Runs when the admin initializes
		 */
		public function retrieve_schema_data() 
		{
			$url  =	$this->get_option( 'scrape_url' );
			$path = $this->get_option( 'cache_path' );
			
			// Nope, we need to set options first
			if ( empty( $url ) || empty( $path ) )
				return;
				
			$file = basename( $url );
			
			// Try cached value
			if ( file_exists( $path ) && file_exists ( $path . $file ) ) :
				$this->schema_data = @json_decode( file_get_contents( $path . $file ) );
				
				if ( is_object( $this->schema_data ) ) :
					$cache_time = $this->get_option( 'cache_time' );
					$timestamp  = $this->schema_data->dj_fecth->timestamp;
					
					if ($timestamp && microtime( true ) - $timestamp <= $cache_time)
						return;
				endif;
			endif;
			
			// Nope, we still need to fetch it
			$this->schema_data = @json_decode( file_get_contents( $url ) );
			
			if ( is_object( $this->schema_data ) )
				 $this->schema_data->dj_fecth->timestamp = microtime( true );
				 
			print_r( $this->schema_data );
		}
			
	}
	
	$DJ_SchemaScraper = DJ_SchemaScraper::singleton();
}