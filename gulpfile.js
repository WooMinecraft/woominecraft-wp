var gulp = require( 'gulp' ),
	gp_uglify = require( 'gulp-uglify' ),
	gp_rename = require( 'gulp-rename' );

gulp.task( 'scripts', function() {
	return gulp.src( 'assets/js/jquery.woo.js' )
		.pipe( gp_uglify() )
		.pipe( gp_rename( 'jquery.woo.min.js' ) )
		.pipe( gulp.dest( 'assets/js' ) );
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

gulp.task( 'default', gulp.series( 'scripts' ) );
