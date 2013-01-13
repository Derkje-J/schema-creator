jQuery(document).ready(function($) {

//********************************************************
// catch-all resize function after form is loaded
//********************************************************

	$(window).resize(function() {
		var formHeight	= $('div#TB_window').height() * 0.9;
		var formWidth	= $('div#TB_window').width() * 0.9;

		$("#TB_ajaxContent").animate({
			height:	formHeight,
			width:	formWidth
		}, {
			duration: 100
		});

	});

//********************************************************
// reset form on media row button click or cancel
//********************************************************

	$('.schema_clear').click( function() {
		$('div#schema_builder select#schema_type').find('option:first').attr('selected', 'selected');
		$('div#schema_builder div.insert_button' ).hide();
		$('div#schema_builder div.sc_option' ).hide();
		$('div#schema_builder input.clone_button' ).hide();
		$('div#schema_builder div#sc_messages p.pending' ).hide();
		$('div#schema_builder div#sc_messages p.start' ).show();
		$('div#schema_builder div.sc_option input' ).val();
		
		$('#schema_properties').empty();
	});

//********************************************************
// change values based on schema type
//********************************************************

	$( '#schema_type' ).focus( function() {
			var caller = $( this );
			caller.attr( 'size', '15' );
			caller.css( 'height', 'auto' );
		}
	);
	
	$('#schema_type_use').click( function() {
			var type_select = $( '#schema_type' )
			type_select.removeAttr( 'size' );
			type_select.css( 'height', '' );
		}
	);
	
// end schema check

//////////////////////////////////////////////////////////
// builds a property
//////////////////////////////////////////////////////////
	
	var schema_build_property = function( key, value ) {
		return key + ': ' + value + '<br>';	
	}

//********************************************************
// jquery datepicker(s)
//********************************************************

	// get current year, futureproof that bitch
	var currentyear = new Date().getFullYear();
	
	// datepicker for birthday, offset the starting date by 15 years
	$( 'input#schema_bday' ).datepicker({
		onSelect: function( selectedDate ) {
			$('input#schema_bday').datepicker( 'option', 'maxDate', selectedDate );
		},
		dateFormat:		'mm/dd/yy',
		defaultDate:	'-15y',
		changeMonth:	true,
		changeYear:		true,
		yearRange:		'1800:' + currentyear + '',
		onClose: function() {
			$('input#schema_bday').trigger('change');
		},
		altField:		'input#schema_bday-format',
		altFormat:		'yy-mm-dd'

	}); // end datepicker for birthday

	$( 'input#schema_sdate' ).datepicker({
		dateFormat:		'mm/dd/yy',
		defaultDate:	null,
		changeMonth:	true,
		changeYear:		true,
		onClose: function() {
			$('input#schema_sdate').trigger('change');
		},
		altField:		'input#schema_sdate-format',
		altFormat:		'yy-mm-dd'

	}); // end datepicker for start date

	$( 'input#schema_edate' ).datepicker({
		dateFormat:		'mm/dd/yy',
		defaultDate:	null,
		changeMonth:	true,
		changeYear:		true,
		onClose: function() {
			$('input#schema_edate').trigger('change');
		},
		altField:		'input#schema_edate-format',
		altFormat:		'yy-mm-dd'

	}); // end datepicker for end date

	$( 'input#schema_pubdate' ).datepicker({
		dateFormat:		'mm/dd/yy',
		defaultDate:	null,
		changeMonth:	true,
		changeYear:		true,
		onClose: function() {
			$('input#schema_pubdate').trigger('change');
		},
		altField:		'input#schema_pubdate-format',
		altFormat:		'yy-mm-dd'

	}); // end datepicker for publish date

	$( 'input#schema_revdate' ).datepicker({
		dateFormat:		'mm/dd/yy',
		defaultDate:	null,
		changeMonth:	true,
		changeYear:		true,
		onClose: function() {
			$('input#schema_revdate').trigger('change');
		},
		altField:		'input#schema_revdate-format',
		altFormat:		'yy-mm-dd'

	}); // end datepicker for publish date

//********************************************************
// timepicker add-ons
//********************************************************

	$('input#schema_stime').timepicker({
		ampm:	true,
		hour:	12,
		minute: 30
	});

	$('input#schema_duration').timepicker({
		ampm: false
	});

	
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
// cloning for actors
//********************************************************

	function ac_block_counter() {

		var count = 0;
		$('div.sc_actor').attr('id', function() {
			count++;
			return 'sc_actor_' + count;
		});
	}

	function ac_label_counter() {
		
		var count = 0;
		$('div.sc_actor label').attr('for', function() {
			count++;
			return 'schema_actor_' + count;
		});
	}

	function ac_input_counter() {
		
		var count = 0;
		$('div.sc_actor input').attr('id', function() {
			count++;
			return 'schema_actor_' + count;
		});
	}

	function ac_names_counter() {
		
		var count = 0;
		$('div.sc_actor input').attr('name', function() {
			count++;
			return 'schema_actor_' + count;
		});
	}
	

    $('input#clone_actor').click(function(){
		$('div.ac_repeat:last').clone(true).insertAfter('div.ac_repeat:last');
		$('div.ac_repeat:last input').val('');
		ac_block_counter();
		ac_label_counter();
		ac_input_counter();
		ac_names_counter();
    });


//********************************************************
// cloning for ingredients
//********************************************************

	function ig_block_counter() {

		var count = 0;
		$('div.sc_ingrt').attr('id', function() {
			count++;
			return 'sc_ingrt_' + count;
		});
	}

	function ig_label_counter() {
		
		var count = 0;
		$('div.sc_ingrt label').attr('for', function() {
			count++;
			return 'schema_ingrt_' + count;
		});
	}

	function ig_input_counter() {
		
		var count = 0;
		$('div.sc_ingrt input').attr('id', function() {
			count++;
			return 'schema_ingrt_' + count;
		});
	}

	function ig_names_counter() {
		
		var count = 0;
		$('div.sc_ingrt input').attr('name', function() {
			count++;
			return 'schema_ingrt_' + count;
		});
	}
	

    $('input#clone_ingrt').click(function(){
		$('div.ig_repeat:last').clone(true).insertAfter('div.ig_repeat:last');
		$('div.ig_repeat:last input').val('');
		ig_block_counter();
		ig_label_counter();
		ig_input_counter();
		ig_names_counter();
    });

//********************************************************
// trigger checkbox on label
//********************************************************


	$('div.sc_option label[rel="checker"]').each(function() {
		$(this).click(function() {

			var check_me = $(this).prev('input.schema_check');
			var is_check = $(check_me).is(':checked');

			if (is_check === false) {
				$(check_me).prop('checked', true);
				$(check_me).trigger('change');
			}

			if (is_check === true) {
				$(check_me).prop('checked', false);
				$(check_me).trigger('change');
			}

		});
	});

//********************************************************
// remove non-numeric characters
//********************************************************

	$('input.schema_numeric').keyup(function() {
			
			var numcheck = $.isNumeric($(this).val() );

			if(this.value.length > 0 && numcheck === false) {
				this.value = this.value.replace(/[^0-9\.]/g,'');
				$('span.warning').remove();
				$(this).parents('div.sc_option').append('<span class="warning">No non-numeric characters allowed</span>');
			}

			if(numcheck === true)
				$('span.warning').remove();

	});

//********************************************************
// remove numeric warning when other fields entered
//********************************************************

	$('div.sc_option input').not('.schema_numeric').keyup(function() {
		$('span.warning').remove();
	});

//********************************************************
// You're still here? It's over. Go home.
//********************************************************
	

});	// end schema form init
