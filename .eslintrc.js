module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: { browser: true },
	overrides: [
		{
			files: [ '**/test/**/*.js', '**/*.test.js' ],
			extends: [ 'plugin:@wordpress/eslint-plugin/test-unit' ],
		},
	],
	rules: {
		// @wordpress/* packages are WordPress externals, not installed.
		'import/no-unresolved': [ 'error', { ignore: [ '^@wordpress/' ] } ],
	},
};
