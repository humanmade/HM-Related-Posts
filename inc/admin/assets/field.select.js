var hmrp_SelectInit = function() {

	jQuery( '.hm_rp_select' ).each( function() {

		var el = jQuery(this);
 		var fieldID = el.attr( 'data-field-id'); // JS Friendly ID

 		// If fieldID is set
 		// If fieldID options exist
 		// If Element is not hidden template field.
 		// If elemnt has not already been initialized.
 		if ( fieldID && fieldID in window.hm_rp_select_fields && el.is( ':visible' ) && ! el.hasClass( 'select2-added' ) ) {

 			// Get options for this field.
 			options = window.hm_rp_select_fields[fieldID];
			el.addClass( 'select2-added' ).select2( options );

 		}

	})

};

// Hook this in for all the required fields.
HMRP.addCallbackForInit( hmrp_SelectInit );
HMRP.addCallbackForClonedField( 'HMRP_Select', hmrp_SelectInit );
HMRP.addCallbackForClonedField( 'HMRP_Post_Select', hmrp_SelectInit );
HMRP.addCallbackForClonedField( 'HMRP_Taxonomy_Select', hmrp_SelectInit );