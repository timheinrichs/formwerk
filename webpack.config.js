const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// PHP owns src/ (PSR-4), so the JS sources live in assets/src and build to build/.
module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'assets/src/admin/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
