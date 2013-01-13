jQuery( document ).ready( function( $ ) {

	// Retrieves the schema types
	var ajax_schema_types = function( ) {
		
		var caller = $( this );
		var parenttype = caller.val();
		var description = $( '#schema_type_description' );
		var messages = $( '#sc_messages' );
		
		var data = {
			action: 'get_schema_types',
			security: schema_ajax.nonce,
			type: parenttype
		};
	
		caller.attr( 'disabled', 'disabled' );
	
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post( ajaxurl, data )
			.success( 
				function( result ) { 
					console.info( result );
					var html = '';
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
			.complete( function( result ) { caller.removeAttr( 'disabled' ); } );
		
	};
	
	$( '#schema_type' ).change( ajax_schema_types );
	$( '#schema_type' ).change();
	
//////////////////////////////////////////////////////////////////
//
//////////////////////////////////////////////////////////////////
	
	$( '#schema_type_use' ).click( function() {
		
		var type = $( '#schema_type' ).val();	// get the selected value to trigger form changes
		var caller = $( this );
		var messages = $( '#sc_messages' );
		var properties = $( '#sc_properties' );
		
		var data = {
			action: 'get_schema_properties',
			security: schema_ajax.nonce,
			type: type
		};
	
		var loader = $( '#sc_messages p.loading' );
		loader.show();
		console.info( loader );
	
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post( ajaxurl, data )
			.success( 
				function( result ) { 
				
					if( type == 'none' || type == '' ) {
						$('div#schema_builder div.insert_button' ).hide();
						$('div#schema_builder div.sc_option' ).hide();
						$('div#schema_builder div#sc_messages p.pending' ).hide();
						$('div#schema_builder div#sc_messages p.start' ).show();
						$('div#schema_builder div.sc_option input' ).val();
						$('div#schema_builder input.clone_button' ).hide();
					}
					
					if(type !== 'none' ) {
						$('div#schema_builder div#sc_messages p.start' ).hide();
						$('div#schema_builder div.insert_button' ).show();
					}
			
					console.info( result );
					
					var html = '';
					for ( var type in result.properties ) {
						html += schema_build_type_header( type );
						for ( var i in result.properties[type] )
							html += schema_build_property( result.properties[type][i] );
					}
					properties.html( html );
					
					$('span.ap_tooltip').each(function() {
						ap_apply_tooltip(this);
					});

				} 
			)
			.error( function( result ) {  console.error( result ); } )
			.complete( function( result ) { loader.hide() } );
	
		$('span.warning').remove(); // clear any warning messages from fields
		
		// message displays
		$('div#sc_messages p.start').hide();
		$('div#sc_messages p.pending').hide();
		
		var formHeight	= $('div#TB_window').height() * 0.9;
		var formWidth	= $('div#TB_window').width() * 0.9;
		
		$("#TB_ajaxContent").animate({
			height:	formHeight,
			width:	formWidth
		}, {
			duration: 800
		});

	});

//////////////////////////////////////////////////////////
// builds a property
//////////////////////////////////////////////////////////
	
	var schema_build_type_header = function( type ) {
		return '<div class="sc_type_header colspan2 sc_type_' + type +' sc_option">Properties from ' + type + '</div>';
	}
	
	var schema_build_property = function( json ) {
		return '<div class="sc_prop_' + json.id + ' sc_option">' +
			'<label>' + json.label + '</label>' +
			'<input/>' +
			'<span class="ap_tooltip" tooltip="' + json.desc + '">(?)</span>' +
		'</div>';	
	}
		
});	// end schema ajax
