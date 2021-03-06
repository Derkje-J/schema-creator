(function() {
	tinymce.create('tinymce.plugins.SchemaAdminEditor', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			
			console.info( 'maybe' );
			
			ed.onExecCommand.add(function(ed, cmd, ui, val) {
				console.info('Command was executed: ' + cmd);
			});
			
			ed.onSetContent.add(function(ed, o) {
				console.info('Setting');
				console.info( ed );
				console.info( o );
				
			});
			
			ed.onGetContent.add(function(ed, o) {
				console.info('Getting');
				console.info( ed );
				console.info( o );
		  	});
			
			ed.onEvent.add(function(ed, e) {
				console.debug('Editor event occured: ' + e.target.nodeName);
				console.debug( e );
			});
			
			ed.onLoadContent.add(function(ed, o) {
				// Output the element name
				console.info('Load content occured: ' + o.element.nodeName);
				console.info( o );
				var oldcontent = String(o.content).valueOf();
				//o.content = o.content.replace(/\<p[^\>]*\>(\[schema.*?\[\/schema\])\<\/p\>/g, '<p><button>schema<span style="display: none">$1</span></button></p>');
				if ( o.content.valueOf() !== oldcontent ) {
					console.info('changed');
				}
				
				ed.setContent( o.content );
			});
		},
		
		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : "Schema Admin Editor",
				author : 'Derk-Jan Karrenbeld',
				authorurl : 'http://derk-jan.com/',
				infourl : 'http://github.com/Derkje-J/schema-creator',
				version : "1.0"
			};
		}
	});
	tinymce.PluginManager.add( 'schemaadmineditor', tinymce.plugins.SchemaAdminEditor );
})();