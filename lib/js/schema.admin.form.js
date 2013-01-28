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

	jQuery('select#schema_type').find('option:first').attr('selected', 'selected');
	jQuery('#schema_builder div.insert_button' ).hide();
	jQuery('#schema_builder div.sc_option' ).hide();
	jQuery('#schema_builder input.clone_button' ).hide();
	jQuery('#sc_messages p.pending' ).hide();
	jQuery('#sc_messages p.start' ).show();
	jQuery('#schema_builder div.sc_option input' ).val();
	
	jQuery('#schema_properties').empty();
};


//********************************************************
// Insert values
//********************************************************
SchemaForm.prototype.insertForm = function() {
	
	var type = jQuery('#schema_type').val();
	
	var output = '[schema '
	output += 'type="' + type + '" ';
	output += ']';
	
	var schema_fields = jQuery( "[id^='" + type + "_'].choice" );
	var schema_pattern = /schema\[(.+?)\]\[([0-9]+)\]\[(.+?)\]/; //\[([0-9]+)\]
	var schema_choice_pattern = ',';
	console.info( schema_fields );
	for( var i in schema_fields ) {
		var schema_field = schema_fields[i];
		if ( schema_field === undefined )
			continue;
		if ( schema_field.value === undefined || schema_field.value === "" )
			continue;
			
		var extract_field = schema_field.name.match( schema_pattern );
		var schema_field_property = extract_field[1];
		var schema_field_property_i = extract_field[2];
		var schema_field_property_type = schema_field.value.split( schema_choice_pattern );
		
		// Link
		if ( schema_field_property_type.length == 2 && 
			( 
				( schema_field_property_type[0] == 'sc_URL' && 
					schema_field_property_type[1] == 'sc_Text' ) || 
				( schema_field_property_type[1] == 'sc_URL' &&
					 schema_field_property_type[0] == 'sc_Text' ) 
			)
		) {
			
			// Build link
			var field_url = jQuery( "#" + type + "_" + schema_field_property + "_" + schema_field_property_i + "_sc_URL");
			var field_text = jQuery( "#" + type + "_" + schema_field_property + "_" + schema_field_property_i + "_sc_Text" );
			output += '[scp ' + schema_field_property + '="sc_Link' ;
			output += '" value="' + field_url.val() + '"]' + field_text.val() + '[/scp]';
			
		// Other props
		} else {
			
			for ( var j in schema_field_property_type ) {
				var schema_field_value = jQuery( "#" + type + "_" + schema_field_property + "_" + schema_field_property_i + "_" + schema_field_property_type[j] );
				
				output += '[scp ' + schema_field_property + '="' + schema_field_property_type[j] ;
				output += '" value="' + schema_field_value.val() + '"/]';
			}
		}
	}
						
	output += '[/schema]';
	
	window.send_to_editor(output);
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
