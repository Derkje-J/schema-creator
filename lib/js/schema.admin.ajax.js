var schema_embeds = 0;
var schema_datatypes = {};

//////////////////////////////////////////////////////////////////
//  Retrieves the datatypes
//////////////////////////////////////////////////////////////////
SchemaForm.prototype.ajaxDataTypes = function( element ) {
	
	var schemaForm = this;
	var caller = jQuery( element );
	var usebutton = jQuery('#schema_type_use');
	var messages = jQuery('#sc_messages');
	
	var data = {
		action: 'get_schema_datatypes',
		security: schema_ajax.nonce,
		prefix: 'sc_',
	};

	usebutton.attr('disabled', 'disabled');
	
	var loader = jQuery('#sc_messages p.loading_types');
	loader.fadeIn();

	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jQuery.get( ajaxurl, data )
		.success( function( result ) { schema_datatypes = result.datatypes; } )
		.error( function( result ) { console.error( result ); } )
		.complete( function( result ) { usebutton.removeAttr('disabled'); loader.fadeOut(); } );
};

//////////////////////////////////////////////////////////////////
// Retrieves the schema types
//////////////////////////////////////////////////////////////////

SchemaForm.prototype.ajaxSchemaTypes = function( element, overridetype ) {
	var caller = jQuery( element );
	var parenttype = overridetype === undefined ? caller.val() : overridetype;
	var description = jQuery('#schema_type_description');
	var usebutton = jQuery('#schema_type_use');
	var is_embed = jQuery('#schema_display').val() !== 'sc_properties';
	
	usebutton.val( is_embed ? 'Embed selected' : 'Use selected');
	
	// hack, because right now we want to allow all embed types
	is_embed = false;
	
	this.ajaxSchemaTypesGeneric(  parenttype, caller, usebutton, description, !is_embed, !is_embed, !is_embed, !is_embed );
};

SchemaForm.prototype.ajaxSchemaTypesGeneric = function( parenttype, caller, usebutton, description, 
	allow_parents, allow_siblings, allow_children, allow_starred ) {
		
	var data = {
		
		action: 'get_schema_types',
		security: schema_ajax.nonce,
		type: parenttype,
		
		parents: ( allow_parents === undefined ? true : allow_parents ),
		siblings: ( allow_siblings === undefined ? true : allow_siblings ),
		children: ( allow_children === undefined ? true : allow_children ),
		starred: ( allow_starred === undefined ? true : allow_starred )
		
	};
	
	caller.attr('disabled', 'disabled');
	usebutton.attr('disabled', 'disabled');
		
	var loader = jQuery('#sc_messages p.loading_types');
	loader.fadeIn();
	
	jQuery.get( ajaxurl, data )
		.success( 				
			function( result ) { 
				console.info( result );
				
				html = '';
				for ( var i in result.types ) {
					
					// Sort by group please, but don't show empty
					if ( result.types[i].length > 0 ) {
						html += '<optgroup label="' + i + '">';
						for ( var j in result.types[i] ) {
							html += '<option value="' + result.types[i][j].id + '"' + ( ( i == '') ? ' selected' : '') + '>' + 
								result.types[i][j].id + '</option>';
							if ( i == '')
								description.html( result.types[i][j].desc );
						}
						html += '</optgroup>';
					}
					
				}
				caller.html(html);
			} 
		)
		.error( function( result ) { console.error( result ); } )
		.complete( function( result ) { caller.removeAttr('disabled'); usebutton.removeAttr('disabled'); loader.fadeOut(); } );
}

//////////////////////////////////////////////////////////////////
// Uses a schema type
//////////////////////////////////////////////////////////////////
	
SchemaForm.prototype.ajaxUseType = function() {
		
	var type = jQuery('#schema_type').val();	// get the selected value to trigger form changes
	var properties = jQuery('#' + jQuery('#schema_display').val() );
	var embed_id = properties.attr('id') == 'sc_properties' ? 0 : properties.attr('id').match(/embed_([0-9]+)/)[1];
	
	this.ajaxUseTypeGeneric( type, properties, embed_id );
};

SchemaForm.prototype.ajaxUseTypeGeneric = function( type, properties, embed_num ) {
	
	var schemaForm = this;
	var usebutton = jQuery('#schema_type_use');
	
	var data = {
		action: 'get_schema_properties',
		security: schema_ajax.nonce,
		type: type
	};

	usebutton.attr('disabled', 'disabled');

	properties.slideUp('slow', function() { 
	
		jQuery('span.warning').remove(); // clear any warning messages from fields
		
		// message displays
		jQuery('div#sc_messages p.start').hide();
		jQuery('div#sc_messages p.pending').hide();
	
		var loader = jQuery('#sc_messages p.loading_properties');
		loader.fadeIn();
	
		jQuery.get( ajaxurl, data )
			.success( 
				function( result ) { 

					jQuery('#sc_messages p.start').hide();
					jQuery('#schema_builder div.insert_button').show();
			
					console.info( result );
					
					// Clear properties but make the empty div visible
					//properties.show(); 
					console.info( properties );
					var html = '<input type="hidden" class="schema_section_type" id="' + properties.attr('id') + '_type" value="' + type + '"/>';
					for ( var inherited_type in result.properties ) {
						html += schemaForm.buildHeader( inherited_type );
						for ( var i in result.properties[ inherited_type ] )
							html += schemaForm.buildProperty( inherited_type, embed_num, result.properties[ inherited_type ][i], 0 );
							
						// Fade in section
						//var section = jQuery( html );
						//section.hide();							
						//section.appendTo( properties );
						//section.fadeIn('slow');
						
						//html = '';	
					}	
					
					properties.empty();
					var section = jQuery( html );	
					section.appendTo( properties );	
					section.fadeIn('slow');
					// process single option fields
					
					jQuery('span.ap_tooltip').each(function() {
						ap_apply_tooltip(this);
					});

				} 
			)
			.error( function( result ) {  console.error( result ); } )
			.success( function( ) { properties.fadeIn('slow');  } )
			.complete( function( result ) { 
					usebutton.removeAttr('disabled'); 
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
	jQuery('[id^="' + hidden_id + '_"]').hide();
	
	// Show some fields
	for( var i in fields ) {
		var field = jQuery( "#" + hidden_id + '_' + fields[i] );
		field.show();
		
		if ( fields.length > 1 ) field.addClass('form_half'); else field.removeClass('form_half');	
	}
	
	// Show the X button
	jQuery( "#" + hidden_id + '_btn_X').show();
	
	// Set the hidden
	jQuery( "#" + hidden_id + '__choice').val( fields );
}

//////////////////////////////////////////////////////////
// Resets certain option
//////////////////////////////////////////////////////////
SchemaForm.prototype.optionReset = function( hidden_id ) {
	
	// Hide all
	jQuery( "[id^='" + hidden_id + "_']" ).hide();
	
	// Show the buttons
	jQuery( "[id^='" + hidden_id + "_btn_']" ).show();
	
	// Hide the X button
	jQuery( "#" + hidden_id + '_btn_X').hide();
	
	// Set the hidden
	jQuery( "#" + hidden_id + '__choice').val('');
	
}

SchemaForm.prototype.optionClone = function( option ) {
	
	// Get the data
	var option = jQuery( option );
	var option_data = option.children('[id$="_btn_X"]').first().attr('id').match(/^(.*)_([0-9]+)_btn_X$/)
	var base_id = option_data[1];
	var clone_id = option_data[2]; // clicked clone
	
	// Find the maximum clone number
	var maximum = clone_id;
	var field_elements = jQuery('[id^="' + base_id + '_"]');

	for ( var i in field_elements ) {
		if ( jQuery.isNumeric( i ) === false )
			continue;
		var field_element = jQuery( field_elements[i] );
		var clone_num = field_element.attr('id').replace( base_id , '').match(/^_([0-9]+)_/)[1];
		maximum = Math.max( clone_num, maximum );
	}
	
	// Find the base elements (clone clone_id )
	var base_elements = jQuery('[id^="' + base_id + '_' + clone_id + '"]');
	maximum++;
	
	var cloned_option = jQuery('<div class="' + option.attr('class') + '"></div>');
	cloned_option.append( option.children('label').first().clone( true, true ) );
	
	// Add remove button
	cloned_option.children('label').first().children('.remove_field').css('display', '');
	
	// Remove tooltip
	cloned_option.children('label').first().children('.ap_tooltip').remove();
	
	
	// Start cloning
	for( var i in base_elements ) {
		if ( jQuery.isNumeric( i ) === false )
			continue;

		var	base_element = jQuery( base_elements[i] );
		var base_clone = base_element.clone(true, true);
		
		// id
		if ( base_element.attr('id') !== undefined )
			base_clone.attr('id', base_element.attr('id').replace( base_id + '_' + clone_id, base_id + '_' + maximum ) );
		
		// name
		if ( base_element.attr('name') !== undefined )
			base_clone.attr('name', base_element.attr('name').replace('[' + clone_id + ']', '[' + maximum + ']') );
			
		// onclick
		if ( base_element.attr('onclick') !== undefined )
			base_clone.attr('onclick', base_element.attr('onclick').replace( base_id + '_' + clone_id, base_id + '_' + maximum ) );
			
		// remove embeds
		if ( base_element.val() !== undefined && base_element.val() !== null && base_element.val().indexOf('embed_') === 0 )
			base_clone.val('');
			
		// remove embed name
		if ( base_element.hasClass( 'embed-link' ) )
			base_clone.html('Embed');
			
		cloned_option.append( base_clone );
	}
	
	// Add the clone
	cloned_option.hide();
	cloned_option.insertAfter( option );
	cloned_option.slideDown();
}

SchemaForm.prototype.optionRemoveClone = function( option ) {
	var option = jQuery( option );
	var option_data = option.children('[id$="_btn_X"]').first().attr('id').match(/^(.*)_([0-9]+)_btn_X$/)
	var base_id = option_data[1];
	var clone_id = option_data[2]; // clicked clone
	
	if ( 0 === parseInt( clone_id, 10 ) )
		return; 
	
	option.slideUp( null, null, function() { option.remove(); } );
}

SchemaForm.prototype.optionEmbed = function( hidden_id, type ) {

	// identifier for this embed
	var schema_embed_id = -1;
	if ( jQuery('#' + hidden_id + '_' + type ).val() === '' || jQuery('#' + hidden_id + '_' + type ).val() === undefined ) {
		schema_embed_id = 'embed_' + (++schema_embeds);
		jQuery('#' + hidden_id + '_' + type ).val( schema_embed_id );
	} else {
		schema_embed_id = jQuery('#' + hidden_id + '_' + type ).val();
	}
	
	var embed_field = hidden_id.match(/^(.+_)?([^_]*?)_([^_]*?_[0-9]+)$/);
	jQuery('#sc_breadcrumbs').append('<li id="sc_bc_' + schema_embed_id + '"> &gt; <abbr title="' + type + '">' + embed_field[2] + ':' + embed_field[3] + '</abbr></li>');
	
	// Hide current display and set displayed id
	jQuery('#' + jQuery('#schema_display').val() ).fadeOut();
	jQuery('#schema_display').val( "schema_" + schema_embed_id );
	
	// Create if needed
	var properties = jQuery('#' + jQuery('#schema_display').val() );
	if ( properties === undefined || properties.length === 0 ) { 
		jQuery('#sc_embeds').append('<div id="schema_' + schema_embed_id + '"></div>');
		this.ajaxSchemaTypes( jQuery('#schema_type'), type );
		return;
	}
	
	// Fill type selection
	this.ajaxSchemaTypes( jQuery('#schema_type'), type );
	jQuery('#' + jQuery('#schema_display').val() ).fadeIn();
	
	// Buttons
	
}
SchemaForm.prototype.closeEmbed = function( cancelled ) {
	// Hide current display
	var display = jQuery('#schema_display');
	jQuery('#' + display.val() ).fadeOut();
	
	if ( cancelled ) jQuery('#' + display.val() ).empty();
	
	// Try to fetch embed id
	var embed_id = display.val().match(/schema_embed_([0-9]+)/);
	jQuery('#sc_bc_embed_' + embed_id[1] ).remove(); // remove the bread crumb
	
	if ( embed_id === undefined || embed_id === null || embed_id.length === 0 ) {
		this.resetDisplay( display ); // already root
		return;
	}
	embed_id = embed_id[1];
	
	// Try to find parent
	var parent = jQuery('input[value="embed_' + embed_id + '"]');
	if ( parent === undefined || parent === null || parent.length === 0 ) {
		this.resetDisplay( display ); // no parent
		return;
	}
	
	if ( cancelled ) 
	{ 
		parent.val('');
		parent.siblings('.button[id$="_btn_X"]').click();
		parent.siblings('.embed-link').html('Embed');
		
	} else {
		var embed = jQuery('#' + display.val() );
		var embed_type = embed.children('.schema_section_type').first().val();
		var not_set = true;
		
		// Try to get the name
		var embed_name = embed.children('.sc_prop_name').first();
		
		if ( embed_name !== null && embed_name !== undefined ) {
			var embed_name_choice = embed_name.children('.choice').val();
			if ( embed_name_choice !== null && embed_name_choice !== undefined && embed_name_choice !== '') {
				var the_name = embed_name.children('.schema_' + embed_name_choice ).val();
				if ( the_name !== null && the_name !== undefined ) {
					parent.siblings('.embed-link').html( the_name + ' (' + embed_type + ')');
					not_set = false;
				}
			}
		}
		
		// If any fails, set type name
		if ( not_set ) {
			if ( embed_type === undefined )
				embed_type = 'generic embed';
				
			parent.siblings('.embed-link').html('A ' + ("AEIOU".indexOf( embed_type[0] ) != -1 ? 'n' : '') + embed_type );
		}
	}
	
	var is_embed = parent.attr('id').match(/embed_([0-9]+)/);
	if ( is_embed === undefined || is_embed === null || is_embed.length === 0 ) {
		this.resetDisplay( display ); // parent is root
		return;
	}
	is_embed = is_embed[1];
	
	display.val('schema_embed_' + is_embed );
	var type = jQuery('#schema_embed_' + is_embed + '_type').val();
	this.ajaxSchemaTypes( jQuery('#schema_type'), type );
	jQuery('#' + display.val() ).fadeIn();
}

SchemaForm.prototype.resetDisplay = function( display ) {
	// Reset display
	display.val('sc_properties');
	var type = jQuery('#sc_properties_type').val();
	this.ajaxSchemaTypes( jQuery('#schema_type'), type );
	jQuery('#' + display.val() ).fadeIn();
}

	
SchemaForm.prototype.validate = function( type, element ) {
	
	if ( type == "sc_Number" || type == "sc_Float" || type == "sc_Integer"
	 ) 
		schemaFormInstance.removeNonNumeric( element );

}

//////////////////////////////////////////////////////////////////
//  Builds property
//////////////////////////////////////////////////////////////////
SchemaForm.prototype.buildProperty = function( schema_type, schema_type_i, json_type, num ) {
		
	// Build generic id/name
	var property_generic_id = schema_type + '_' + json_type.id;
	var property_generic_name = 'schema[' + json_type.id + ']';
	if ( schema_type_i !== 0 ) {
		property_generic_id = 'embed_' + schema_type_i + '_' + property_generic_id;
		property_generic_name = 'embed_' + schema_type_i + '_' + property_generic_name;
	}
		
	// Append clone number
	var property_id =  property_generic_id + '_' + num;
	var property_name = property_generic_name + '[' + num +']';
	
	// Determine options
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
	
	// Arbitrary options accept urls or plain text
	if ( extendRanges ) {
		ranges.push( "Link" );
		ranges.push( "Text" );
	} 
	ranges = ranges.sort();

	// Build all the options ( choice buttons )
	var property_options = { };
	for( var i in ranges )
		property_options[ ranges[i] ] = this.buildPropertyOption( property_id, property_name, ranges[i] );

	// Build all the field ( chosen fields )
	var property_fields = { };
	for( var i in property_options ) {
		property_fields = jQuery.extend( property_fields, this.buildPropertyField( property_id, property_name, i ));
	}
		
	var html = '';
	for ( var opt in property_options )
		html += property_options[opt];
	for ( var opt in property_fields )
		html += property_fields[opt];
	
	// Build the cancel option
	html += this.buildPropertyOption( property_id, property_name, 'X');
	
	return '<div class="sc_prop_' + json_type.id + ' sc_option">' +
		'<label>' + json_type.label + 
			'<span class="ap_tooltip" tooltip="' + json_type.desc + '">(?)</span>' + 
			'<a class="clone_field" onClick="schemaFormInstance.optionClone( jQuery( this ).parent().parent( \'.sc_option\') ); return false;">[+]</a>' +
			'<a class="remove_field" style="display:none" onClick="schemaFormInstance.optionRemoveClone( jQuery( this ).parent().parent( \'.sc_option\') ); return false;">[-]</a>' +
		'</label>' +
		'<input id="' + property_id + '__choice" type="hidden" name="' + property_name + '[_choice]" value="' + this.getCurrent( property_id + '__choice') + '" class="choice" />' +
		html +
	'</div>';	
}

//////////////////////////////////////////////////////////
// Builds a property option button
//////////////////////////////////////////////////////////

SchemaForm.prototype.buildPropertyOption = function( hidden_id, hidden_name, option_range ) {
	var type = this.getInternalType( option_range );
	var choice = this.getCurrent( hidden_id + '__choice').split(',');
	if ( choice.length == 1 && choice[0] === "" ) choice = [];
	var style = 'display:' + ( choice.length == 0 ? (type == 'X' ? 'none' : 'visible') : ( type == 'X' ? 'visble' : 'none') );
	
	switch( type ) {
		case 'sc_Text':
			return '<button class="button" id="' + hidden_id +'_btn_Text" style="' + style + '" value="Text"  onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'sc_Text\'], \'sc_Text\');">'  + schema_datatypes[type]['button'] + '</button>';
			
		case 'sc_URL':
			return '<button class="button" id="' + hidden_id +'_btn_URL" style="' + style + '" value="URL" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'sc_URL\'], \'sc_URL\');">' + schema_datatypes[type]['button'] + '</button>';
			
		case 'sc_Link':
			return '<button class="button" id="' + hidden_id +'_btn_URL"  style="' + style + '" value="URL" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'sc_Text\', \'sc_URL\'], \'sc_URL\');">Link</button>';
			
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
		
			return '<button class="button" id="' + hidden_id +'_btn_' + type +'"  style="' + style + '" value="' + type + '" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'' + type + '\'], \'' + type +'\');">' + schema_datatypes[type]['button'] +'</button>';
			
		case 'sc_meta_' + option_range:
			return '<button class="button" id="' + hidden_id +'_btn_' + option_range + '"  style="' + style + '" value="' + option_range + '" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'sc_meta_' + option_range +'_display\', \'sc_meta_' + option_range + '\'], \'' + option_range +'\');">' + option_range +'</button>';
			
		case 'sc_enum_' + option_range:
			return '<button class="button" id="' + hidden_id +'_btn_' + option_range + '"  style="' + style + '" value="' + option_range + '" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'sc_enum_' + option_range +'_display\', \'sc_enum_' + option_range + '\'], \'' + option_range +'\');">' + option_range +'</button>';
		
		
		case 'sc_Embed_' + option_range:
			return '<button class="button" id="' + hidden_id +'_btn_' + option_range + '" style="' + style + '" value="' + option_range + '" onClick="schemaFormInstance.optionEnable( \'' + hidden_id + '\', [\'' + option_range +'\', \'' + option_range + '_sc_Embed\'], \'' + option_range + '\');">' + option_range + '</button>';
		
		case 'X':
			return '<button class="button" id="' + hidden_id +'_btn_X" style="' + style + '" value="X" onClick="schemaFormInstance.optionReset( \'' + hidden_id + '\');">X</button>';
	}
	
	return 'Nothing found for: ' + type;
}

//////////////////////////////////////////////////////////
// Builds a property field 
//////////////////////////////////////////////////////////

SchemaForm.prototype.buildPropertyField = function( hidden_id, hidden_name, option_range, classes ) {
	var type = this.getInternalType( option_range );
	classes = (classes === undefined ? '' : classes );
	var choice = this.getCurrent( hidden_id + '__choice').split(',');
	if ( choice.length == 1 && choice[0] === "" ) choice = [];
	var style = 'display:' + ( choice.length == 0 ? 'none' : ( jQuery.inArray( type, choice ) === -1 ? 'none' : 'visible') );
	if ( choice.length > 1 )
		classes += (classes.length > 0 ? ' form_half ' : 'form_half ');
		
	classes += (classes.length > 0 ? ' ' :  '') + 'schema_' + type;
	
	if ( type === 'sc_Text') 
		// Textfield
		return { "sc_Text": '<input type="text" style="' + style + '" id="' + hidden_id + '_' + type + '" ' + 
			'name="' + hidden_name + '[' + type + ']" placeholder="' + schema_datatypes[type]['button'] +'" ' +
			'value="' + this.getCurrent( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes  + '"/>' 
		};
	
	if ( type ===  'sc_URL')
			return { "sc_URL": '<input type="text" style="' + style + '"  id="' + hidden_id + '_' + type + '" ' +
				'name="' + hidden_name + '[' + type + ']" placeholder="' + schema_datatypes[type]['button'] +'"  ' + 
				'value="' + this.getCurrent( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes  + '" onBlur="schemaFormInstance.validate(\'' + type + '\', this);"/>'
			};
			
	if ( type === 'sc_Link') 
			// Text + url field
			return jQuery.extend( this.buildPropertyField( hidden_id, hidden_name, 'Text'), 
				this.buildPropertyField( hidden_id, hidden_name, 'URL')
			);
			
		// boolean
	if ( type === 'sc_Boolean')
			return { 'sc_Boolean': '<input type="checkbox" style="' + style + '"  id="' + hidden_id + '_' + type + '" ' +
				'name="' + hidden_name + '[' + type + ']" ' + 
				'value="' + this.getCurrent( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes + ' form_eighth' + '"/>'
			};
	
	// Meta value		
	if ( type === 'sc_meta_' + option_range ) {
		var result = {};
		result[type] = '' + 
			'<input type="text" style="' + style + '"  id="' + hidden_id + '_sc_meta_' + option_range + '" ' +
				'name="' + hidden_name + '[sc_meta_' + option_range + ']" placeholder="' + option_range +'"  ' + 
				'value="' + this.getCurrent( hidden_id + '_sc_meta_' + option_range ) + '" style="display:none;" class="' + classes  + '" onBlur="schemaFormInstance.validate(\'' + type + '\', this);"/>' + 
			'<input type="text" style="' + style + '"  id="' + hidden_id + '_sc_meta_' + option_range + '_display" ' +
				'name="' + hidden_name + '[sc_meta_' + option_range + '_display]" placeholder="Display"  ' + 
				'value="' + this.getCurrent( hidden_id + '_sc_meta_' + option_range + '_display') + '" style="display:none;" class="' + classes  + '" onBlur="schemaFormInstance.validate(\'sc_Text\', this);"/>';
		return result;
			
	}
	
	if ( type === 'sc_enum_' + option_range ) {
		var result = {};
		result[type] = '' + 
			'<input type="text" style="' + style + '"  id="' + hidden_id + '_sc_enum_' + option_range + '" ' +
				'name="' + hidden_name + '[sc_enum_' + option_range + ']" placeholder="' + option_range +'"  ' + 
				'value="' + this.getCurrent( hidden_id + '_sc_enum_' + option_range ) + '" style="display:none;" class="' + classes  + '" onBlur="schemaFormInstance.validate(\'' + type + '\', this);"/>' + 
			'<input type="text" style="' + style + '"  id="' + hidden_id + '_sc_enum_' + option_range + '_display" ' +
				'name="' + hidden_name + '[sc_enum_' + option_range + '_display]" placeholder="Display"  ' + 
				'value="' + this.getCurrent( hidden_id + '_sc_enum_' + option_range + '_display') + '" style="display:none;" class="' + classes  + '" onBlur="schemaFormInstance.validate(\'sc_Text\', this);"/>';
		return result;
	}
	
	
	if ( type === 'sc_Embed_' + option_range ) {
		var result = {};
		result[type] = '' + 
			'<input type="hidden" id="' + hidden_id + '_' + option_range + '" ' +
				'name="' + hidden_name + '[' + option_range + ']" ' + 
				'value="' + this.getCurrent( hidden_id + '_' + option_range ) + '"/>' +
			'<a class="embed-link" id="' + hidden_id + '_' + option_range + '_sc_Embed" style="display:none;" ' + 
				'onClick="schemaFormInstance.optionEmbed(\'' + hidden_id + '\',\'' + option_range + '\');">' +
				'Embed</a> ';
		
		return result;
	}
		
	classes += ' form_third';
		
	// Create it for this type
	var result = {};
	result[type] = '<input type="text" style="' + style + '"  id="' + hidden_id + '_' + type + '" ' +
				'name="' + hidden_name + '[' + type + ']" placeholder="' + schema_datatypes[type]['button'] +'"  ' + 
				'value="' + this.getCurrent( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes  + '" onBlur="schemaFormInstance.validate(\'' + type + '\', this);"/>'
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
		
		// Intangible
		// TODO: can we derive these dynamically?
		case 'Enumeration':
		case 'BookFormatType':
		case 'BusinessEntityType':
		case 'BusinessFunction':
		case 'DayOfWeek':
		case 'DeliveryMethod':
		case 'ItemAvailability':
		case 'OfferItemCondition':
		case 'PaymentMethod':
		case 'Specialty':
		case 'WarrantyScope':
		// TODO: All the enumeration subtypes should derive
		// possible values from THEIR subtypes or predef types.
			type = 'sc_enum_' + option_range;
		break;
		
		// These are meta values
		case 'Language': 
		case 'Quantity':
		case 'Distance':
		case 'Duration':
		case 'Energy':
		case 'Mass':
			type = 'sc_meta_' + option_range; 
		break;
		
		// Other intangibles are embeds
		case 'StructuredValue':

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
	var current = jQuery('#' + some_id  );
	return current == null ? '' : ( current.val() !== undefined ? current.val() : '');	
}

//////////////////////////////////////////////////////////////////
//  Checks if current value exists
//////////////////////////////////////////////////////////////////
SchemaForm.prototype.currentExists = function( some_id ) {
	var current = jQuery('#' + some_id  );
	return current == null ? false : ( current.val() !== undefined );	
} 

//////////////////////////////////////////////////////////////////
//  Hook to document
//////////////////////////////////////////////////////////////////
jQuery( document ).ready( function( jQuery ) {
	
	// Get datatypes
	schemaFormInstance.ajaxDataTypes( this );
	
	// Refresh current type subtypes
	jQuery('#schema_type').change( function() { schemaFormInstance.ajaxSchemaTypes( this ); } );
	jQuery('#schema_type').change();

	// Bind type use
	jQuery('#schema_type_use').click( function() { schemaFormInstance.ajaxUseType( this ); } );
		
});	// end schema ajax
