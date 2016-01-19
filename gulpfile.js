var gulp = require( 'gulp' ),
	gp_uglify = require( 'gulp-uglify' ),
	gp_rename = require( 'gulp-rename' );

var scripts = [
	'assets/js/jquery.woo.js'
];

gulp.task( 'scripts', function() {
	gulp.src( 'assets/js/jquery.woo.js' )
		.pipe( gp_uglify() )
		.pipe( gp_rename( 'jquery.woo.min.js' ) )
		.pipe( gulp.dest( 'assets/js' ) );
} );

gulp.task( 'watch', function() {
	gulp.watch( 'assets/js/*.js', ['scripts'] );
} );

gulp.task( 'default', ['scripts'] );
