var schema_embed = 0;
var schema_datatypes = {};

//////////////////////////////////////////////////////////////////
//  Retrieves the datatypes
//////////////////////////////////////////////////////////////////
SchemaForm.prototype.ajaxDataTypes = function( element ) {
	
	var schemaForm = this;
	var caller = jQuery( element );
	var usebutton = jQuery('#schema_type_use');
	var messages = jQuery( '#sc_messages' );
	
	var data = {
		action: 'get_schema_datatypes',
		security: schema_ajax.nonce,
		prefix: 'sc_',
	};

	usebutton.attr( 'disabled', 'disabled' );
	
	var loader = jQuery( '#sc_messages p.loading_types' );
	loader.fadeIn();

	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jQuery.post( ajaxurl, data )
		.success( function( result ) { schema_datatypes = result.datatypes; } )
		.error( function( result ) { console.error( result ); } )
		.complete( function( result ) { usebutton.removeAttr( 'disabled' ); loader.fadeOut(); } );
};

//////////////////////////////////////////////////////////////////
// Retrieves the schema types
//////////////////////////////////////////////////////////////////

SchemaForm.prototype.ajaxSchemaTypes = function( element ) {
	var caller = jQuery( element );
	var parenttype = caller.val();
	var description = jQuery( '#schema_type_description' );
	var usebutton = jQuery('#schema_type_use');
	
	this.ajaxSchemaTypesGeneric(  parenttype, caller, usebutton, description );
};

SchemaForm.prototype.ajaxSchemaTypesGeneric = function( parenttype, caller, usebutton, description ) {
		
	var data = {
		action: 'get_schema_types',
		security: schema_ajax.nonce,
		type: parenttype
	};
	
	caller.attr( 'disabled', 'disabled' );
	usebutton.attr( 'disabled', 'disabled' );
		
	var loader = jQuery( '#sc_messages p.loading_types' );
	loader.fadeIn();
	
	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jQuery.post( ajaxurl, data )
		.success( 				
			function( result ) { 
				console.info( result );
				
				html = '';
				for ( var i in result.types ) {
					html += '<optgroup label="' + i + '">';
					for ( var j in result.types[i] ) {
						html += '<option value="' + result.types[i][j].id + '"' + ( ( i == '' ) ? ' selected' : '') + '>' + result.types[i][j].id + '</option>';
						if ( i == '' )
							description.html( result.types[i][j].desc );
					}
					html += '</optgroup>';
				}
				caller.html(html);
			} 
		)
		.error( function( result ) { console.error( result ); } )
		.complete( function( result ) { caller.removeAttr( 'disabled' ); usebutton.removeAttr( 'disabled' ); loader.fadeOut(); } );
}

//////////////////////////////////////////////////////////////////
// Uses a schema type
//////////////////////////////////////////////////////////////////
	
SchemaForm.prototype.ajaxUseType = function() {
		
	var type = jQuery( '#schema_type' ).val();	// get the selected value to trigger form changes
	var properties = jQuery( '#sc_properties' );
	
	this.ajaxUseTypeGeneric( type, properties );
};

SchemaForm.prototype.ajaxUseTypeGeneric = function( type, properties ) {
	
	var schemaForm = this;
	var usebutton = jQuery('#schema_type_use');
	
	var data = {
		action: 'get_schema_properties',
		security: schema_ajax.nonce,
		type: type
	};

	usebutton.attr( 'disabled', 'disabled' );
	properties.slideUp( 'slow', function() { 
	
		jQuery('span.warning').remove(); // clear any warning messages from fields
		
		// message displays
		jQuery('div#sc_messages p.start').hide();
		jQuery('div#sc_messages p.pending').hide();
	
		var loader = jQuery( '#sc_messages p.loading_properties' );
		loader.fadeIn();
	
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post( ajaxurl, data )
			.success( 
				function( result ) { 

					jQuery('#sc_messages p.start' ).hide();
					jQuery('#schema_builder div.insert_button' ).show();
			
					console.info( result );
					
					// Clear properties but make the empty div visible
					properties.empty();
					//properties.show(); 
					
					var html = '';
					for ( var inherited_type in result.properties ) {
						html += schemaForm.buildHeader( inherited_type );
						for ( var i in result.properties[ inherited_type ] )
							html += schemaForm.buildProperty( inherited_type, schema_embed, result.properties[ inherited_type ][i], 0 );
							
						// Fade in section
						var section = jQuery( html );
						//section.hide();							
						section.appendTo( properties );
						//section.fadeIn( 'slow' );
						
						html = '';	
					}					
					// process single option fields
					
					jQuery('span.ap_tooltip').each(function() {
						ap_apply_tooltip(this);
					});

				} 
			)
			.error( function( result ) {  console.error( result ); } )
			.success( function( ) { properties.fadeIn( 'slow' );  } )
			.complete( function( result ) { 
					usebutton.removeAttr( 'disabled' ); 
					loader.fadeOut(); 
					schemaForm.resizeInnerWindow();
				} 
			);
	});
}

//////////////////////////////////////////////////////////////////
//  Builds a type header
//////////////////////////////////////////////////////////////////

SchemaForm.prototype.buildHeader = function( type ) {
	return '<div class="sc_type_header colspan2 sc_type_' + type +' sc_option">Properties from ' + type + '</div>';
}

//////////////////////////////////////////////////////////
// Enables certain option
//////////////////////////////////////////////////////////
SchemaForm.prototype.optionEnable = function( hidden_id, fields, option_range ) {
	
	// Hide all
	jQuery( '[id^=' + hidden_id + '_]' ).hide();
	
	// Show some fields
	for( var i in fields ) {
		var field = jQuery( "#" + hidden_id + '_' + fields[i] );
		field.show();
		
		if ( fields.length > 1 ) field.addClass( 'form_half' ); else field.removeClass( 'form_half' );	
	}
	
	// Show the X button
	jQuery( "#" + hidden_id + '_btn_X' ).show();
	
	// Set the hidden
	jQuery( "#" + hidden_id + '__choice').val( fields );
}

//////////////////////////////////////////////////////////
// Resets certain option
//////////////////////////////////////////////////////////
SchemaForm.prototype.optionReset = function( hidden_id ) {
	
	// Hide all
	jQuery( "[id^=" + hidden_id + '_]' ).hide();
	
	// Show the buttons
	jQuery( "[id^=" + hidden_id + '_btn_]' ).show();
	
	// Hide the X button
	jQuery( "#" + hidden_id + '_btn_X' ).hide();
	
	// Set the hidden
	jQuery( "#" + hidden_id + '__choice').val( '' );
	
}

var schema_open_embed = function( hidden_id, type ) {
	
}
	
var schema_validate = function( type ) {
	
}

var schema_clone = function( after_id, num, generic_id, generic_name, json_id ) {
	var after = jQuery( "#" + after_id );
	var properties = jQuery( '[id^="' + generic_id + '_' + num + '"]' );
	var properties_all = jQuery( '[id^="' + generic_id + '_"]' );
	
	var max_num = num + 1;//0;
	//for ( var i in properties_all )
	//	jQuery( properties_all[i] ).attr( 'id' );
	
	var cloned = [];
	/*for( var i in properties ) { 
		var p_clone = jQuery( properties[i] );
		//p_clone.attr( 'id', p_clone.attr( 'id' ).replace( '/' + generic_id + '_' + num + '/', generic_id + '_' + max_num ) ); 
		//p_clone.attr( 'name', p_clone.attr( 'name' ).replace( '/' + generic_name + '\[' + num + '\]/', generic_name + '[' + max_num + ']' ) ); 
		cloned.push( p_clone );
	}*/
	
	console.info( properties );
	
}

//////////////////////////////////////////////////////////////////
//  Builds property
//////////////////////////////////////////////////////////////////
SchemaForm.prototype.buildProperty = function( schema_type, schema_type_i, json_type, num ) {
		
	var property_generic_id = schema_type + '_' + schema_type_i + '_' + json_type.id;
	var property_generic_name =  'schema[' + schema_type_i + '][' + json_type.id + ']';
	var property_id =  property_generic_id + '_' + num;
	var property_name = property_generic_name + '[' + num +']';
	
	var ranges = json_type.ranges;
	
	var extendRanges = true;
	var isDataType = false;
	
	for( var i in schema_datatypes ) {
		
		if ( jQuery.inArray( schema_datatypes[i].id , ranges ) !== -1 ) {
			extendRanges = false;
			if ( schema_datatypes[i].id != "Text" && schema_datatypes[i].id != "URL" ) {
				isDataType = true;
				//for( j in schema_datatypes[i].subtypes )
				//	ranges.push( schema_datatypes[i].subtypes[j] );
			}
			break;
		}
	}
	
	if ( extendRanges ) {
		ranges.push( "Link" );
		ranges.push( "Text" );
	} 
	ranges = ranges.sort();

	var property_options = { };
	for( var i in ranges )
		property_options[ ranges[i] ] = this.buildPropertyOption( property_id, property_name, ranges[i] );

	var property_fields = { };
	for( var i in property_options ) {
		property_fields = jQuery.extend( property_fields, this.buildPropertyField( property_id, property_name, i ));
	}
		
	var html = '';
	for ( var opt in property_options )
		html += property_options[opt];
	for ( var opt in property_fields )
		html += property_fields[opt];
	
	// cancel button
	html += this.buildPropertyOption( property_id, property_name, 'X' );
	
	return '<div class="sc_prop_' + json_type.id + ' sc_option">' +
		'<label>' + json_type.label + 
			'<span class="ap_tooltip" tooltip="' + json_type.desc + '">(?)</span>' + 
		'</label>' +
		'<input id="' + property_id + '__choice" type="hidden" name="' + property_name + '[_choice]" value="' + this.getCurrent( property_id + '__choice' ) + '"/>' +
		html +
		//'<input id="' + property_id + '" name="' + property_name + '" value="' + '' + '" />' +
		//'<a href="#" onClick="schema_clone(\'sc_prop_' + json_type.id + '\', ' + num + ', \'' + property_generic_id + '\', \'' + property_generic_name + '\', \'' + json_type.id + '\'); return false"> + </a>' +
	'</div>';	
}

//////////////////////////////////////////////////////////
// Builds a property option button
//////////////////////////////////////////////////////////

SchemaForm.prototype.buildPropertyOption = function( hidden_id, hidden_name, option_range ) {
	var type = this.getInternalType( option_range );
	var choice = this.getCurrent( hidden_id + '__choice' ).split(',');
	if ( choice.length == 1 && choice[0] === "" ) choice = [];
	var style = 'display:' + ( choice.length == 0 ? (type == 'X' ? 'none' : 'visible') : ( type == 'X' ? 'visble' : 'none' ) );
	
	switch( type ) {
		case 'sc_Text':
			return '<button class="button" id="' + hidden_id +'_btn_Text" style="' + style + '" value="Text"  onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'sc_Text\'], \'sc_Text\' );">'  + schema_datatypes[type]['button'] + '</button>';
		case 'sc_URL':
			return '<button class="button" id="' + hidden_id +'_btn_URL" style="' + style + '" value="URL" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'sc_URL\'], \'sc_URL\' );">' + schema_datatypes[type]['button'] + '</button>';
		case 'sc_Link':
			return '<button class="button" id="' + hidden_id +'_btn_URL"  style="' + style + '" value="URL" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'sc_Text\', \'sc_URL\'], \'sc_URL\' );">Link</button>';
		
		// boolean
		case 'sc_Boolean':
		
		// number
		case 'sc_Number':
		case 'sc_Integer':
		case 'sc_Float':
		
		// datetime
		case 'sc_DateTime':
		case 'sc_Date':
		case 'sc_Time':
		
			return '<button class="button" id="' + hidden_id +'_btn_' + type +'"  style="' + style + '" value="' + type + '" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'' + type + '\'], \'' + type +'\' );">' + schema_datatypes[type]['button'] +'</button>';
		
		case 'sc_Embed_' + option_range:
			return '<button class="button" id="' + hidden_id +'_btn_' + option_range + '" style="' + style + '" value="' + option_range + '" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'' + option_range +'\'], \'' + option_range + '\' );">' + option_range + '</button>';
		
		case 'X':
			return '<button class="button" id="' + hidden_id +'_btn_X" style="' + style + '" value="X" onClick="schemaFormInstance.optionReset( \'' + hidden_id + '\' );">X</button>';
	}
	
	return 'Nothing found for: ' + type;
}

//////////////////////////////////////////////////////////
// Builds a property field 
//////////////////////////////////////////////////////////

SchemaForm.prototype.buildPropertyField = function( hidden_id, hidden_name, option_range, classes ) {
	var type = this.getInternalType( option_range );
	classes = (classes === undefined ? '' : classes );
	var choice = this.getCurrent( hidden_id + '__choice' ).split(',');
	if ( choice.length == 1 && choice[0] === "" ) choice = [];
	var style = 'display:' + ( choice.length == 0 ? 'none' : ( jQuery.inArray( type, choice ) === -1 ? 'none' : 'visible' ) );
	if ( choice.length > 1 )
		classes += ' form_half';
		
	classes += ' schema_' + type;
	
	if ( type === 'sc_Text' ) 
		// Textfield
		return { "sc_Text": '<input type="text" style="' + style + '" id="' + hidden_id + '_' + type + '" ' + 
			'name="' + hidden_name + '[' + type + ']" placeholder="' + schema_datatypes[type]['button'] +'" ' +
			'value="' + this.getCurrent( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes  + '"/>' 
		};
	
	if ( type ===  'sc_URL' )
			return { "sc_URL": '<input type="text" style="' + style + '"  id="' + hidden_id + '_' + type + '" ' +
				'name="' + hidden_name + '[' + type + ']" placeholder="' + schema_datatypes[type]['button'] +'"  ' + 
				'value="' + this.getCurrent( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes  + '" onBlur="schema_validate(\'' + type + '\');"/>'
			};
			
	if ( type === 'sc_Link' ) 
			// Text + url field
			return jQuery.extend( this.buildPropertyField( hidden_id, hidden_name, 'Text' ), 
				this.buildPropertyField( hidden_id, hidden_name, 'URL' )
			);
			
		// boolean
	if ( type === 'sc_Boolean' )
			return { 'sc_Boolean': '<input type="checkbox" style="' + style + '"  id="' + hidden_id + '_' + type + '" ' +
				'name="' + hidden_name + '[' + type + ']" ' + 
				'value="' + this.getCurrent( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes + ' form_eighth' + '"/>'
			};
	
	if ( type === 'sc_Embed_' + option_range ) {
		var result = {};
		result[type] = '<span id="' + hidden_id + '_' + option_range + '" ' +
			'style="display:none;" >Not implemented yet</span>';
		
		return result;
	}
		
	classes += ' form_third';
		
	// Create it for this type
	var result = {};
	result[type] = '<input type="text" style="' + style + '"  id="' + hidden_id + '_' + type + '" ' +
				'name="' + hidden_name + '[' + type + ']" placeholder="' + schema_datatypes[type]['button'] +'"  ' + 
				'value="' + this.getCurrent( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes  + '" onBlur="schema_validate(\'' + type + '\');"/>'
			;
	return result;
}

//////////////////////////////////////////////////////////
// Gets internal type
//////////////////////////////////////////////////////////

SchemaForm.prototype.getInternalType = function( option_range ) {
	
	switch( option_range ) {
		
		// boolean
		case 'Boolean':
		
		// number
		case 'Number':
		case 'Integer':
		case 'Float':
				
		// datetime
		case 'DateTime':
		case 'Date':
		case 'Time':
		
		// text
		case 'Text':
		case 'URL':
		case 'Link':
			type = 'sc_' + option_range;
		break;
		
		// button
		case 'X':
			type = option_range;
		break;
		
		
		// Else embed
		default:
			type = 'sc_Embed_' + option_range;
		break;
	}
	
	return type;
}

//////////////////////////////////////////////////////////////////
//  Gets current value
//////////////////////////////////////////////////////////////////
SchemaForm.prototype.getCurrent = function( some_id ) {
	var current = jQuery( '#' + some_id  );
	return current == null ? '' : ( current.val() !== undefined ? current.val() : '' );	
}

//////////////////////////////////////////////////////////////////
//  Checks if current value exists
//////////////////////////////////////////////////////////////////
SchemaForm.prototype.currentExists = function( some_id ) {
	var current = jQuery( '#' + some_id  );
	return current == null ? false : ( current.val() !== undefined );	
} 

//////////////////////////////////////////////////////////////////
//  Hook to document
//////////////////////////////////////////////////////////////////
jQuery( document ).ready( function( jQuery ) {
	
	// Get datatypes
	schemaFormInstance.ajaxDataTypes( this );
	
	// Refresh current type subtypes
	jQuery( '#schema_type' ).change( function() { schemaFormInstance.ajaxSchemaTypes( this ); } );
	jQuery( '#schema_type' ).change();

	// Bind type use
	jQuery( '#schema_type_use' ).click( function() { schemaFormInstance.ajaxUseType( this ); } );
		
});	// end schema ajax
