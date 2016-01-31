window.WooMinecraft = ( function( window, document, $ ) {

	var app = {};

	app.cache = function() {
		app.$body = $( 'body' );
		app.$nonce = app.$body.find( '#woo_minecraft_nonce' );
	};

	app.init = function() {

		app.$body.on( 'click', '.woo_minecraft_add', app.add_command );
		app.$body.on( 'click', '.remove_row', app.remove_command );
		app.$body.on( 'click', '.woo_minecraft_reset', app.reset_form );

	};

	app.add_command = function( evt ) {
		evt.preventDefault();
	};

	app.remove_command = function( evt ) {
		evt.preventDefault();

		$(this).parent('span').fadeOut(200, function(e){
			$(this).remove();
		});
	};

	app.reset_form = function( evt ){
		evt.preventDefault();
	};

	$( document ).ready( app.init );

	return app;

} )( window, document, jQuery );