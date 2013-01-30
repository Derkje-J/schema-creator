function SchemaForm() {
}
var schemaFormInstance = new SchemaForm();

//********************************************************
// Resize function
//********************************************************
	
SchemaForm.prototype.resizeInnerWindow = function() {
	var formHeight	= jQuery('#TB_window').height() * 0.9;
	var formWidth	= jQuery('#TB_window').width() * 0.9;

	jQuery("#TB_ajaxContent").animate({
		height:	formHeight,
		width:	formWidth
	}, {
		duration: 100
	});
}


//********************************************************
// Remove non numerics
//********************************************************

SchemaForm.prototype.removeNonNumeric = function( element ) {

	var numcheck = jQuery.isNumeric( jQuery(element).val() );

	if(element.value.length > 0 && numcheck === false) {
		element.value = element.value.replace(/[^0-9\.]/g,'');
		this.removeWarnings();
		jQuery(element).parents('div.sc_option').append('<span class="warning">' + schema_i18n.numeric_only + '</span>');
	}

	if(numcheck === true)
		this.removeWarnings();
}

//********************************************************
// Remove warnings
//********************************************************

SchemaForm.prototype.removeWarnings = function() {
	jQuery('span.warning').remove();
}


//********************************************************
// Reset form
//********************************************************
SchemaForm.prototype.resetForm = function() {

	// Completely resets the selection
	var selector = jQuery('#schema_type');
	selector.append( '<option value="_invalid"></option>' );
	selector.val( "_invalid" );
	selector.change();
	
	jQuery('#schema_builder div.insert_button' ).hide();
	jQuery('#schema_builder div.sc_option' ).hide();
	jQuery('#schema_builder input.clone_button' ).hide();
	jQuery('#sc_messages p.pending' ).hide();
	jQuery('#sc_messages p.start' ).show();
	jQuery('#schema_builder div.sc_option input' ).val();
	jQuery('#schema_display').val('sc_properties');
	
	jQuery('#sc_properties').empty();
	jQuery('#sc_embeds').empty();
	jQuery('#sc_breadcrumbs').empty();
	jQuery('#sc_breadcrumbs').append('<li id="sc_bc_root">root</li>');
};


//********************************************************
// Insert values
//********************************************************
SchemaForm.prototype.cancelForm = function() {
	if ( jQuery( '#schema_display' ).val() !== 'sc_properties' ) {
		schemaFormInstance.closeEmbed( true );
		return false;	
	}
	window.send_to_editor('');
	return false;
}

SchemaForm.prototype.insertForm = function() {
	
	if ( jQuery( '#schema_display' ).val() !== 'sc_properties' ) {
		schemaFormInstance.closeEmbed( false );
		return;	
	}
	
	
	var type = jQuery('#sc_properties .schema_section_type').val();
	
	var output = '[schema '
	output += 'type="' + type + '" ';
	output += ']';
	output += schemaFormInstance.insertFormSection( type, '' );
	output += '[/schema]';
	
	// Reset so it's reusable
	schemaFormInstance.resetForm();
	
	window.send_to_editor(output);
}		

SchemaForm.prototype.insertFormSection = function( type, embed ) {
	
	var output = '';
	
	var schema_id = embed + 'schema';
	console.info( 'inserting id: ' + schema_id );
	var schema_fields = jQuery( "[name^='" + schema_id + "'].choice" );
	var schema_pattern = /schema\[(.+?)\]\[([0-9]+)\]\[(.+?)\]/; //\[([0-9]+)\]
	var schema_choice_pattern = ',';
	
	var schema_name = null;
	var schema_url = null;
	
	// For all the fields from this type
	for( var i in schema_fields ) {
		
		var schema_field = schema_fields[i];
		if ( schema_field === undefined )
			continue;
			
		// Only set choices
		if ( schema_field.value === undefined || schema_field.value === "" )
			continue;
	
		// Nope, not a schema field
		var extract_field = schema_field.name.match( schema_pattern );
		if ( extract_field === undefined || extract_field === false || extract_field.length === 0 || extract_field[0] === undefined )
			continue;
			
		// Fetch the data from the name
		var schema_field_property = extract_field[1];
		var schema_field_property_i = extract_field[2];
		var schema_field_property_type = schema_field.value.split( schema_choice_pattern );
		
		console.info( schema_field_property_i );
		// Thing properties are kinda special. Name and url should be bound as link
		if ( schema_field_property == 'name' ) {
			schema_name =  jQuery( "[name='" + schema_id + "\[" + schema_field_property + "\]\[" + schema_field_property_i + "\]\[sc_Text\]']");
			continue;
		} else if ( schema_field_property == 'url' ) {
			schema_url = jQuery( "[name='" + schema_id + "\[" + schema_field_property + "\]\[" + schema_field_property_i + "\]\[sc_URL\]']");
			continue;
		}
		
		console.info( schema_field_property_type );
		// Arbitrary link (todo: post links)
		if ( schema_field_property_type.length == 2 && 
			( 
				( schema_field_property_type[0] == 'sc_URL' && 
					schema_field_property_type[1] == 'sc_Text' ) || 
				( schema_field_property_type[1] == 'sc_URL' &&
					 schema_field_property_type[0] == 'sc_Text' ) 
			)
		) {
			//schema[name][0][sc_Text]
			// Build link
			var field_url = jQuery( "[name='" +schema_id+ "\[" + schema_field_property + "\]\[" + schema_field_property_i + "\]\[sc_URL\]']");
			var field_text = jQuery( "[name='" +schema_id + "\[" + schema_field_property + "\]\[" + schema_field_property_i + "\]\[sc_Text\]']");
			output += '[scprop prop="' + schema_field_property + '" range="sc_Link' ;
			output += '" value="' + field_url.val() + '"]' + field_text.val() + '[/scprop]';
			
		// Arbitrary embed
		} else if ( schema_field_property_type[0].indexOf( 'sc_' ) !== 0 ) {
			
			var embed_type = schema_field_property_type[0];
			output += '[scmbed embed="' + schema_field_property + '"' ;
			output += ' value="' + embed_type + '" ';
			output += ']';
	
			var field_embed = jQuery( "[name='" + schema_id + "\[" + schema_field_property + "\]\[" + schema_field_property_i + "\]\[" + embed_type + "\]']");
			console.info( 'embed field is: ' );
			console.info( field_embed );
			if ( field_embed.val() !== undefined && field_embed.val() !== '' ) {
				output += schemaFormInstance.insertFormSection( embed_type, field_embed.val() + '_' );
				console.info( 'output embed! value: ' + field_embed.val() );
			}
				
			output += '[/scmbed]';
			
		// Meta content
		} else if ( ( schema_field_property_type[0].indexOf( 'sc_meta_' ) === 0 ) || ( schema_field_property_type[0].indexOf( 'sc_enum_' ) === 0 )) {
			
			var display_index = schema_field_property_type[0].indexOf( '_display' ) > 0 ? 0 : 1;
			var real_type = schema_field_property_type[ 1 - display_index ].match( /sc_(meta|enum)_(.*)/ );
			
			var schema_field_meta = jQuery( "[name='" + schema_id + "\[" + schema_field_property + "\]\[" + schema_field_property_i + "\]\[" + schema_field_property_type[ 1 - display_index ] + "\]']");
			var schema_field_display = jQuery( "[name='" + schema_id + "\[" + schema_field_property + "\]\[" + schema_field_property_i + "\]\[" + schema_field_property_type[ display_index ] + "\]']");
			
			output += '[scprop prop="' + schema_field_property + '" range="' + real_type[2] ;
				output += '" content="' + schema_field_meta.val() + '"]' + schema_field_display.val() + '[/scprop]';
			
		// Other props
		} else {
			
			for ( var j in schema_field_property_type ) {
				var schema_field_value = jQuery( "[name='" + schema_id + "\[" + schema_field_property + "\]\[" + schema_field_property_i + "\]\[" + schema_field_property_type[j] + "\]']");
				
				if ( schema_field_value.val() === undefined )
					schema_field_value.val('');
				
				output += '[scprop prop="' + schema_field_property + '" range="' + schema_field_property_type[j] ;
				output += '" value="' + schema_field_value.val() + '"/]';
			}
		}
	}	
	
	// THINGS:
	
	
	console.info( schema_name );
	console.info( schema_url );
	
	// Link
	if ( schema_name !== null && schema_url !== null && schema_name.val() !== undefined && schema_url.val() !== undefined ) {
		output = '[scprop prop="url" range="sc_Link"' +
				 ' value="' + schema_url.val() + '"]' + 
				 schema_name.val() +
				 '[/scprop]' + output;
	// Name only
	} else if ( schema_name !== null && schema_name.val() !== undefined ) {
		output = '[scprop prop="name" range="sc_Text"' +
				 ' value="' + schema_name.val() + '"/]' + output;
	// Url only (should not be possible...)
	} else if ( schema_url !== null && schema_url.val() !== undefined ) {
		output = '[scprop prop="url" range="sc_URL"' +
				 ' value="' + schema_url.val() + '"/]' + output;
	}
	
	return output;
}	

jQuery(document).ready(function($) {

//********************************************************
// catch-all resize function after form is loaded
//********************************************************
	
	$( window ).resize( schemaFormInstance.resizeInnerWindow );

//********************************************************
// reset form on media row button click or cancel
//********************************************************

	$('.schema_insert').click( schemaFormInstance.insertForm );
	$('.schema_cancel').click( schemaFormInstance.cancelForm );
	$('.schema_clear').click( schemaFormInstance.resetForm );
	
//********************************************************
// change values based on schema type
//********************************************************

	$( '#schema_type' ).focus( function() {
			var caller = $( this );
			caller.attr( 'size', '15' );
			caller.css( 'height', 'auto' );		
			
			schemaFormInstance.resizeInnerWindow();
		}
	);
	
	$('#schema_type_use').click( function() {
			var type_select = $( '#schema_type' )
			type_select.removeAttr( 'size' );
			type_select.css( 'height', '' );
			
			schemaFormInstance.resizeInnerWindow();
		}
	);
	
//********************************************************
// currency formatting
//********************************************************


	$('div#schema_builder input.sc_currency').blur(function() {
		$('div#schema_builder input.sc_currency').formatCurrency({
			colorize: true,
			roundToDecimalPlace: 2,
			groupDigits: false
		});
	});

//********************************************************
// You're still here? It's over. Go home.
//********************************************************

});	// end schema form init
