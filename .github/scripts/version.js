const {version} = require( '../../package.json' );
const composerJson = require( '../../composer.json' );
const fs = require( 'fs' );

// Replace in .txt file.
fs.readFile( "readme.txt", 'utf8', function (err,data) {
    if (err) {
        throw err;
    }

    const result = data.replace(/Stable tag: ([0-9\.]+)/g, 'Stable tag: ' + version );
    fs.writeFile( "readme.txt", result, 'utf8', function (err) {
        if (err) throw err;
    });
});

// Replace in md file.
fs.readFile( "readme.md", 'utf8', function (err,data) {
    if (err) {
        throw err;
    }

    // Has issues with double asterisks so sticking with \W{2} instead
    const result = data.replace(/Stable tag:\W{2} ([0-9\.])+/gi, 'Stable tag:** ' + version );
    fs.writeFile( "readme.md", result, 'utf8', function (err) {
        if (err) throw err;
    });
});

// Replace in plugin file.
fs.readFile( "plugin.php", 'utf8', function (err,data) {
    if (err) {
        throw err;
    }

    // Has issues with double asterisks so sticking with \W{2} instead
    const result = data.replace(/Version: ([0-9\.])+/gi, 'Version: ' + version );
    fs.writeFile( "plugin.php", result, 'utf8', function (err) {
        if (err) throw err;
    });
});

// Update composer.json now.
composerJson.version = version;
fs.writeFile( 'composer.json', JSON.stringify( composerJson, null, 2 ), 'utf8', function( err ) {
    if( err ) throw err;
} )
