jQuery( document ).ready( function( $ ) {

	var schema_datatypes = { };
	
	// Retrieves the schema types
	var ajax_schema_datatypes = function( ) {
		
		var caller = $( this );
		var usebutton = $('#schema_type_use');
		var messages = $( '#sc_messages' );
		
		var data = {
			action: 'get_schema_datatypes',
			security: schema_ajax.nonce,
		};
	
		usebutton.attr( 'disabled', 'disabled' );
		
		var loader = $( '#sc_messages p.loading_types' );
		loader.fadeIn();
	
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post( ajaxurl, data )
			.success( 				
				function( result ) { 
					console.info( result );
					schema_datatypes = result.datatypes;
				} 
			)
			.error( function( result ) { console.error( result ); } )
			.complete( function( result ) { usebutton.removeAttr( 'disabled' ); loader.fadeOut(); } );
	};
	
	ajax_schema_datatypes();

	// Retrieves the schema types
	var ajax_schema_types = function( ) {
		
		var caller = $( this );
		var parenttype = caller.val();
		var description = $( '#schema_type_description' );
		var messages = $( '#sc_messages' );
		var usebutton = $('#schema_type_use');
		
		var data = {
			action: 'get_schema_types',
			security: schema_ajax.nonce,
			type: parenttype
		};
	
		caller.attr( 'disabled', 'disabled' );
		usebutton.attr( 'disabled', 'disabled' );
		
		var loader = $( '#sc_messages p.loading_types' );
		loader.fadeIn();
	
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post( ajaxurl, data )
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
		
	};
	
	$( '#schema_type' ).change( ajax_schema_types );
	$( '#schema_type' ).change();
	
//////////////////////////////////////////////////////////////////
//
//////////////////////////////////////////////////////////////////

	var ajax_schema_use_type = function() {
		
		var type = $( '#schema_type' ).val();	// get the selected value to trigger form changes
		var caller = $( this );
		var messages = $( '#sc_messages' );
		var properties = $( '#sc_properties' );
		var usebutton = $('#schema_type_use');
		
		var data = {
			action: 'get_schema_properties',
			security: schema_ajax.nonce,
			type: type
		};
	
		usebutton.attr( 'disabled', 'disabled' );
		
		var loader = $( '#sc_messages p.loading_properties' );
		loader.fadeIn();
	
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post( ajaxurl, data )
			.success( 
				function( result ) { 

					$('#sc_messages p.start' ).hide();
					$('#schema_builder div.insert_button' ).show();
			
					console.info( result );
					
					var html = '';
					for ( var inherited_type in result.properties ) {
						html += schema_build_type_header( inherited_type );
						for ( var i in result.properties[ inherited_type ] )
							html += schema_build_property( inherited_type, 0, result.properties[ inherited_type ][i] );
					}
					properties.html( html );
					
					// process single option fields
					
					$('span.ap_tooltip').each(function() {
						ap_apply_tooltip(this);
					});

				} 
			)
			.error( function( result ) {  console.error( result ); } )
			.complete( function( result ) { usebutton.removeAttr( 'disabled' ); loader.fadeOut(); } );
	
		$('span.warning').remove(); // clear any warning messages from fields
		
		// message displays
		$('div#sc_messages p.start').hide();
		$('div#sc_messages p.pending').hide();
		
		resizeInnerWindow();

	};
	
	$( '#schema_type_use' ).click( ajax_schema_use_type );
	
	var resizeInnerWindow = function() {
		var formHeight	= $('div#TB_window').height() * 0.9;
		var formWidth	= $('div#TB_window').width() * 0.9;

		$("#TB_ajaxContent").animate({
			height:	formHeight,
			width:	formWidth
		}, {
			duration: 100
		});
	}

//////////////////////////////////////////////////////////
// builds a property
//////////////////////////////////////////////////////////
	
	var schema_build_type_header = function( type ) {
		return '<div class="sc_type_header colspan2 sc_type_' + type +' sc_option">Properties from ' + type + '</div>';
	}
	
	var schema_build_property = function( schema_type, schema_type_i, json ) {
		
		var property_id = schema_type + '_' + schema_type_i + '_' + json.id;
		var property_name = 'schema[' + schema_type_i + '][' + json.id + ']';
		var ranges = json.ranges;
		
		var extendRanges = true;
		var isDataType = false;
		
		for( var i in schema_datatypes ) {
			
			if ( $.inArray( schema_datatypes[i].id , ranges ) !== -1 ) {
				extendRanges = false;
				if ( schema_datatypes[i].id != "Text" && schema_datatypes[i].id != "URL" ) {
					isDataType = true;
					for( j in schema_datatypes[i].subtypes )
						ranges.push( schema_datatypes[i].subtypes[j] );
				}
				break;
			}
		}
		
		if ( extendRanges ) {
			ranges.push( "_URL" );
			ranges.push( "Text" );
		} 
		ranges = ranges.sort();

		var property_options = { };
		for( var i in ranges )
			property_options[ ranges[i] ] = schema_build_property_option( property_id, property_name, ranges[i] );

		var property_fields = { };
		for( var i in property_options )
			property_fields = $.extend(property_fields, schema_build_property_field( property_id, property_name, i ));
			
		var html = '';
		for ( var opt in property_options )
			html += property_options[opt];
		for ( var opt in property_fields )
			html += property_fields[opt];
		
		// cancel button
		html += schema_build_property_option( property_id, property_name, 'X' );
		
		return '<div class="sc_prop_' + json.id + ' sc_option">' +
			'<label>' + json.label + '</label>' +
			'<input id="' + property_id + '__choice" type="hidden" name="' + property_name + '[_choice]" value="' + schema_get_current( property_id + '__choice' ) + '"/>' +
			html +
			//'<input id="' + property_id + '" name="' + property_name + '" value="' + '' + '" />' +
			'<span class="ap_tooltip" tooltip="' + json.desc + '">(?)</span>' +
		'</div>';	
	}
	
	//
	var schema_build_property_option = function( hidden_id, hidden_name, option_range ) {
		var type = schema_get_internal_type( option_range );
		var choice = schema_get_current( hidden_id + '__choice' ).split(',');
		if ( choice.length == 1 && choice[0] === "" ) choice = [];
		var style = 'display:' + ( choice.length == 0 ? (type == 'X' ? 'none' : 'visible') : ( type == 'X' ? 'visble' : 'none' ) );
		
		
		switch( type ) {
			case 'Text':
				return '<button class="button" id="' + hidden_id +'_btn_Text" style="' + style + '" value="Text"  onClick="schema_option_enable( \'' + hidden_id + '\', [\'Text\'], \'Text\' );">Text</button>';
			case 'URL':
				return '<button class="button" id="' + hidden_id +'_btn_URL" style="' + style + '" value="URL" onClick="schema_option_enable( \'' + hidden_id + '\', [\'URL\'], \'URL\' );">URL</button>';
			case '_URL':
				return '<button class="button" id="' + hidden_id +'_btn_URL"  style="' + style + '" value="URL" onClick="schema_option_enable( \'' + hidden_id + '\', [\'Text\', \'URL\'], \'URL\' );">Link</button>';
			case 'Embed':
				return '<button class="button" id="' + hidden_id +'_btn_' + option_range + '" style="' + style + '" value="URL" onClick="schema_option_enable( \'' + hidden_id + '\', [], \'\' );">' + option_range + '</button>';
			case 'X':
				return '<button class="button" id="' + hidden_id +'_btn_X" style="' + style + '" value="X" onClick="schema_option_reset( \'' + hidden_id + '\' );">X</button>';
		}
		
		return 'Nothing found for: ' + type;
	}
	
	//
	var schema_build_property_field = function( hidden_id, hidden_name, option_range, classes ) {
		var type = schema_get_internal_type( option_range );
		classes = (classes === undefined ? '' : classes );
		var choice = schema_get_current( hidden_id + '__choice' ).split(',');
		if ( choice.length == 1 && choice[0] === "" ) choice = [];
		var style = 'display:' + ( choice.length == 0 ? 'none' : ( $.inArray( type, choice ) === -1 ? 'none' : 'visible' ) );
		if ( choice.length > 1 )
			classes += ' form_half';
		
		switch( type ) {
			case 'Text':
				// Textfield
				return { "Text": '<input type="text" style="' + style + '" id="' + hidden_id + '_' + type + '" ' + 
					'name="' + hidden_name + '[' + type + ']" placeholder="' + type +'" ' +
					'value="' + schema_get_current( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes  + '"/>' 
				};
			case 'URL':
				return { "URL": '<input type="text" style="' + style + '"  id="' + hidden_id + '_' + type + '" ' +
					'name="' + hidden_name + '[' + type + ']" placeholder="' + type +'"  ' + 
					'value="' + schema_get_current( hidden_id + '_' + type ) + '" style="display:none;" class="' + classes  + '" onBlur="schema_check_url();"/>'
				};
			case '_URL':
				// Text + url field
				return $.extend( schema_build_property_field( hidden_id, hidden_name, 'Text' ), 
					schema_build_property_field( hidden_id, hidden_name, 'URL' )
				);
			case 'DataType':
			case 'Embed':
				break;
		}
		
		return { };
	}
	
	//
	var schema_get_internal_type = function( option_range ) {
		switch( option_range ) {
			case 'Text':
			case 'URL':
			case '_URL':
			case 'X':
				type = option_range;
			break;
			
			// TODO check datatypes
			
			// ELSE embed
			default:
				type = 'Embed';
			break;
		}
		return type;
	}
	
	//
	var schema_get_current = function( some_id ) {
		var current = $( '#' + some_id  );
		return current == null ? '' : ( current.val() !== undefined ? current.val() : '' );	
	}
	
	var schema_current_exists = function( some_id ) {
		var current = $( '#' + some_id  );
		return current == null ? false : ( current.val() !== undefined );	
	} 
	
		
});	// end schema ajax

//
var schema_option_enable = function( hidden_id, fields, option_range ) {
	
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

//
var schema_option_reset = function( hidden_id ) {
	
	// Hide all
	jQuery( "[id^=" + hidden_id + '_]' ).hide();
	
	// Show the buttons
	jQuery( "[id^=" + hidden_id + '_btn_]' ).show();
	
	// Hide the X button
	jQuery( "#" + hidden_id + '_btn_X' ).hide();
	
	// Set the hidden
	jQuery( "#" + hidden_id + '__choice').val( '' );
	
}
	
var schema_check_url = function() {
	
}
