jQuery( document ).ready( function( $ ) {

	var ajax_schema_types = function( caller ) {
		
		var data = {
			action: 'get_schema_types',
			security: schema_ajax.nonce,
			type: caller.val()
		};
	
		caller.attr( 'disabled', 'disabled' );
	
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post( ajaxurl, data )
			.success( function( result ) { 
					var html = '';
					for ( var i in result.types )
						html += '<option value="' + result.types[i] + '">' + result.types[i] + '</option>';
					caller.html(html);
				} )
			.error( function( result ) { console.info( result ); } )
			.complete( function( result ) { caller.removeAttr( 'disabled' ); } );
		
	};
	
	$( '#schema_type' ).on( 'change', ajax_schema_types( $( '#schema_type' ) ) );

});	// end schema ajax
