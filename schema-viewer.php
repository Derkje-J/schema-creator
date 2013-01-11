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
		
		}			
	}
	
	$DJ_SchemaViewer = DJ_SchemaViewer::singleton();
}