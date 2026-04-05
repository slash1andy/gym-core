const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'blocks/targeted-content/index': path.resolve(
			process.cwd(),
			'src/blocks/targeted-content/index.js'
		),
	},
};
