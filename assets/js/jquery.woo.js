window.WooMinecraft = ( function( window, document, $ ) {

	var app = {};
		app.l10n = woominecraft || {};

	app.cache = function() {
		app.$body = $( 'body' );
		app.$nonce = app.$body.find( '#woo_minecraft_nonce' );
	};

	app.init = function() {

		app.cache();

		app.$body.on( 'click', '.woo_minecraft_add', app.add_command );
		app.$body.on( 'click', '.remove_row', app.remove_command );
		app.$body.on( 'click', '.woo_minecraft_reset', app.reset_form );

	};

	/**
	 * Adds more rows for the commands.
	 * @param evt
	 */
	app.add_command = function( evt ) {
		evt.preventDefault();
		var current_block = $( this ).closest( '.form-fields' );  // Update to grab the parent
		var cloned = current_block.find( '.woo_minecraft_copyme' ).clone().removeClass( 'woo_minecraft_copyme' ).removeAttr( 'style' );
		current_block.append( cloned );
	};

	/**
	 * Removes commands from post data.
	 * @param evt
	 */
	app.remove_command = function( evt ) {
		evt.preventDefault();

		$(this).parent('span').fadeOut(200, function(e){
			$(this).remove();
		});
	};

	/**
	 * Removes ALL commands from the current selection.
	 * @param evt
	 */
	app.reset_form = function( evt ){
		evt.preventDefault();

		var confirmation = confirm( app.l10n.confirm );
		if ( confirmation ) {
			$( this ).parent().find( 'span' ).not( '.woo_minecraft_copyme' ).fadeOut( 200, function() {
				$( this ).remove();
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