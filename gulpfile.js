var gulp = require( 'gulp' ),
	gp_uglify = require( 'gulp-uglify' ),
	gp_rename = require( 'gulp-rename' ),
	gp_sass = require( 'gulp-sass' ),
	gp_cssmin = require( 'gulp-cssmin' );

var scripts = [
	'assets/js/jquery.woo.js'
];

var sass_options = {
	outputStyle: 'expanded'
};

gulp.task( 'scripts', function() {
	return gulp.src( 'assets/js/jquery.woo.js' )
		.pipe( gp_uglify() )
		.pipe( gp_rename( 'jquery.woo.min.js' ) )
		.pipe( gulp.dest( 'assets/js' ) );
} );

gulp.task( 'sass', function() {
	return gulp.src( 'assets/sass/*.scss' )
		.pipe( gp_sass( sass_options ) )
		.pipe( gp_rename( 'style.css' ) )
		.pipe( gulp.dest( './' ) );
} );

gulp.task( 'cssmin', function() {
	return gulp.src( 'style.css' )
		.pipe( gp_cssmin() )
		.pipe( gp_rename( 'style.min.css' ) )
		.pipe( gulp.dest( './' ) );
} );

gulp.task( 'watch', function() {
	gulp.watch( 'assets/js/*.js', ['scripts'] );
	gulp.watch( 'assets/sass/*.scss', [ 'sass', 'cssmin' ] );
} );

gulp.task( 'build', function() {
	return gulp.src([
		'*.php',
		'*.png',
		'*.css',
		'LICENSE',
		'readme.txt',
		'assets/**/*',
		'includes/**/*',
		'languages/**/*',
	], { base: "." } ).pipe( gulp.dest( 'woominecraft' ) );
} );

gulp.task( 'default', gulp.series( 'scripts', 'sass', 'cssmin' ) );
