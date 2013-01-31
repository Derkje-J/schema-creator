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
		}
		
		/**
		 *
		 */
		public function on_activated() {
			if ( !class_exists( 'DJ_SchemaScraper' ) )
				return;
			
			$this->on_fetch( DJ_SchemaScraper::singleton()->get_schema_data() );
		}
		
		/**
		 *
		 */
		public function on_fetch( $schema_data ) {
			
			$path = WP_CONTENT_DIR . DJ_SchemaScraper::singleton()->get_option( 'cache_path' );
			$file = 'schema-scraper-i18n-strings.php';
			
			$stream = @fopen( $path . $file, 'w' );
			
			if ( !empty( $stream ) ) :
				fwrite( $stream, '<?php' . "\n" . '$schema_scraper_i18n_strings = array(' . "\n" );
				$this->walk_object_for_strings( $schema_data, $stream );
				fwrite( $stream, ');' . "\n" . '?>' );
				fclose( $stream );
			endif;
		}
		
		/**
		 *
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

	}
	
	$DJ_SchemaScraperI18n = DJ_SchemaScraperI18n::singleton();
}
?>