<?php
/*
  Scrapes the translatable data from the schema_data. Adds them in a file that is then loaded
  by the text load_plugin.

  Version: 1.0
  Author: Derk-Jan Karrenbeld
  Author URI: http://derk-jan.com
*/

if (!class_exists("DJ_SchemaScraperI18n")) 
{
	define("DJ_SCHEMASCRAPEI18N_BASE", plugin_basename(__FILE__));
	define("DJ_SCHEMASCRAPEI18N_VERSION", '1.0');
	
	class DJ_SchemaScraperI18n {
		
		private static $singleton;
		
		/**
		 * Gets a singleton of this class
		 *
		 * DJ_SchemaScraperI18n::singleton() will always return the same instance during a
		 * PHP processing stack. This way actions will not be queued duplicately and 
		 * caching of processed values is not neccesary.
		 *
		 * @return DJ_SchemaScraperI18n the singleton instance
		 */
		public static function singleton() {
			if ( empty( DJ_SchemaScraperI18n::$singleton ) )
				DJ_SchemaScraperI18n::$singleton = new DJ_SchemaScraperI18n();
			return DJ_SchemaScraperI18n::$singleton;
		}
		
		/**
		 *
		 */
		protected function __construct() 
		{
			add_action( 'dj_schemacraper_fetched', array( $this, 'on_fetch' ) );
			add_action( 'dj_sc_onactivate', array( $this, 'on_activated' ) );
			
			add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		}
		
		/**
		 * Runs when the plugin is activated
		 */
		public function on_activated() {
			
			if ( !class_exists( 'DJ_SchemaScraper' ) )
				return;
			if ( !class_exists( 'DJ_SchemaCreator' ) )
				return;
			if ( !DJ_SchemaCreator::singleton()->debug )
				return;
			
			$this->on_fetch( DJ_SchemaScraper::singleton()->get_schema_data() );
		}
		
		/**
		 *	Get caching path
		 *	@return $string path 
		 */
		public function get_path() {
			return WP_CONTENT_DIR . DJ_SchemaScraper::singleton()->get_option( 'cache_path' );
		}
		
		/**
		 *	Get caching filename
		 *	@return $string filename
		 */
		public function get_filename() {
			return 'schema-scraper-i18n-strings.php';	
		}
		
		/**
		 *	On fetched schema data from scraper
		 *	@param object $schema_data fetched schema
		 */
		public function on_fetch( $schema_data ) {
			
			if ( !class_exists( 'DJ_SchemaCreator' ) )
				return;
			if ( !DJ_SchemaCreator::singleton()->debug )
				return;
				
			$path = $this->get_path();
			$filename = $this->get_filename();
			
			$stream = @fopen( $path . $filename, 'w' );
			
			if ( !empty( $stream ) ) :
				fwrite( $stream, '<?php' . "\n" . '$schema_scraper_i18n_strings = array(' . "\n" );
				$this->walk_object_for_strings( $schema_data, $stream );
				fwrite( $stream, ');' . "\n" . '?>' );
				fclose( $stream );
			endif;
		}
		
		/**
		 *	Walks the object and looks for strings
		 *
		 *	@param object $object the walked object
		 *	@param resource $stream filestream
		 */
		 public function walk_object_for_strings( $object, $stream ) {
			
			$strings = array();
			$objects = array();
			
			foreach( $object as $key => $value ) {
				if ( is_string( $value) && in_array( $key, array( 'comment', 'comment_plain', 'label' ) ) )
					$strings[] = $value;
				else if ( is_object( $value ) )
					$objects[] = $key;
			}			
			
			// Write to file
			$strings = array_unique( $strings );
			foreach( $strings as $string )
				fwrite( $stream, sprintf( "\t'%s' => __( '%s', 'schema' ), \n", addslashes( $string ), addslashes( $string ) ) );
			unset( $strings );
			
			// Recursive loop
			foreach( $objects as $key )
				$this->walk_object_for_strings( $object->$key, $stream );
			unset( $objects );
		}
		
		/**
		 *	Runs when plugins are loaded and loads the strings
		 */
		public function on_plugins_loaded() {
			
			$path = $this->get_path();
			$filename = $this->get_filename();

			global $schema_scraper_i18n_strings;
			if ( file_exists( $path . $filename ) ) :
				include_once( $path . $filename );
				return;
			endif;
			
			$path = plugin_dir_path( __FILE__ ) . 'res/';
			include_once( $path . $filename );
		}

	}
	
	$DJ_SchemaScraperI18n = DJ_SchemaScraperI18n::singleton();
}
?>