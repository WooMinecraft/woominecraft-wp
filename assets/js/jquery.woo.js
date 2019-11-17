window.WooMinecraft = ( function( window, document, $ ) {

	var app = {};
		app.l10n = woominecraft || {};

	app.cache = function() {
		app.$body = $( 'body' );
		app.$nonce = app.$body.find( '#woo_minecraft_nonce' );
		app.$resend_donations = app.$body.find( '#resendDonations' );
	};

	app.init = function() {

		app.cache();

		app.$body.on( 'click', '.woo_minecraft_reset', app.resetForm );
		app.$body.on( 'click', '.wmc_add_server', app.addRow );
		app.$body.on( 'click', '.wmc_delete_server', app.removeRow );

		if ( app.l10n.player_id ) {
			app.$resend_donations.on( 'click', app.resend_donations );
		} else {
			app.$resend_donations.prop( 'disabled', true );
		}
	};

	/**
	 * Helper function to grab the current parent of a row.
	 *
	 * @param {object} selector
	 * @returns {*}
	 */
	app.curParent = function( selector ) {
		var curElement = $( selector );
		var curParent = curElement.closest( 'table.woominecraft, div.wrap.woocommerce tr.woominecraft' );
		if ( ! curParent.length ) {
			return false;
		}

		return curParent;
	};

	/**
	 * Adds a toggleable row which you can remove in the future, relative to the current container.
	 * @since 1.0.7
	 * @param evt
	 */
	app.addRow = function( evt ) {
		evt.preventDefault();

		var curParent = app.curParent( this );
		window.console.trace( curParent);
		if ( ! curParent ) {
			return false;
		}

		var $row = curParent.find( 'tbody tr:first' );
		window.console.trace($row );
		if ( ! $row ) {
			return false;
		}

		var $new_row = $row.clone();

		// Clear out all values
		$new_row.find( ':text' ).val( '' );
		$row.parent( 'tbody' ).append( $new_row );
		app.reindexRows( curParent );
	};

	/**
	 * Removes a row from the current list of rows, and reindex them.
	 * @since 1.0.7
	 * @param evt
	 */
	app.removeRow = function( evt ) {
		evt.preventDefault();

		var curParent = app.curParent( this );
		if ( ! curParent ) {
			window.console.trace( curParent );
			return false;
		}

		var rowLength = curParent.find( 'tbody tr' ).length;

		if ( 0 == ( rowLength - 1 ) ) {
			alert( app.l10n.must_have_single );
			return false;
		}

		$( this ).closest( 'tr.row' ).fadeOut( 200, function() {
			$( this ).remove();
			app.reindexRows( curParent );
		} );
	};

	/**
	 * Re-indexes rows for array processing
	 * @since 1.0.7
	 */
	app.reindexRows = function( curParent ) {

		var $rows = curParent.find( 'tbody tr' );
		if ( ! $rows ) {
			return false;
		}

		$rows.each( function( index ) {
			$( this ).find( ':text' ).each( function(){
				var $name = $( this ).attr( 'name' );
				$name = $name.replace( /\[([0-9]+)\]/, '['+ index +']' );

				$( this ).attr( 'name', $name );
			} );

			$( this ).find( 'select' ).each( function(){
				var $name = $( this ).attr( 'name' );
				$name = $name.replace( /\[([0-9]+)\]/, '['+ index +']' );

				$( this ).attr( 'name', $name );
			} );
		} );
	};

	app.resend_donations = function( evt ) {
		evt.preventDefault();

		var serverSelect = $( 'select.wmc-server-select' );
		if ( ! serverSelect || '' == serverSelect.val() ) {
			return false;
		}

		app.$resend_donations.prop( 'disabled', true );
		$.ajax( {
			url:      ajaxurl,
			data:     {
				action:    'wmc_resend_donations',
				order_id:  app.l10n.order_id,
				player_id: app.l10n.player_id,
				server:    serverSelect.val(),
			},
			dataType: 'json',
			method:   'POST'
		} ).done( app.xhrDone );

	};

	app.xhrDone = function( data, textStatus, jqXHR ) {
		if ( data.success ) {
			// TODO: Make a prettier dialog, instead of this crap.
			alert( app.l10n.donations_resent );
		}

		app.$resend_donations.prop( 'disabled', false );
	};


	/**
	 * Removes ALL commands from the current selection.
	 * @param evt
	 */
	app.resetForm = function( evt ){
		evt.preventDefault();

		var confirmation = confirm( app.l10n.confirm );
		if ( confirmation ) {
			// Finds the closest parent and removes all commands from within
			$( this ).closest('.form-fields').find( 'span' ).not( '.woo_minecraft_copyme, .woocommerce-help-tip' ).fadeOut( 200, function() {
				$( this ).remove();

				// This is very inefficient, but WooCommerce does this, so why not?
				//noinspection JSJQueryEfficiency
				$( '#variable_product_options .woocommerce_variations :input' ).trigger( 'change' );
			} );
		}
	};

	/**
	 * Logging helper, will ONLY log if SCRIPT_DEBUG is enabled
	 * in the wp-config file.
	 */
	app.log = function() {
		if ( app.l10n.script_debug && window.console ) {
			window.console.log( Array.prototype.slice.call( arguments ) );
		}
	};

	$( document ).ready( app.init );

	return app;

} )( window, document, jQuery );