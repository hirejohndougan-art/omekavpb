// ESLint settings for the editor scripts and the tests.
// Run: npm run lint
const js = require('@eslint/js');
const globals = require('globals');

module.exports = [
  js.configs.recommended,
  {
    // Editor: plain browser scripts sharing the global VPB namespace (no build step).
    files: ['module/VisualPageBuilder/asset/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2017,
      sourceType: 'script',
      globals: { ...globals.browser, VPB: 'readonly' }
    },
    rules: {
      'no-unused-vars': ['error', { args: 'none', caughtErrors: 'none' }],
      'no-shadow': 'error',
      'eqeqeq': ['error', 'smart'],
      'no-var': 'off',
      'strict': ['error', 'function'],
      'no-implicit-globals': 'error'
    }
  },
  {
    // Special Blocks: small standalone browser scripts (public slider, admin form helper).
    files: ['module/VisualPageBuilder/asset/special-blocks/js/**/*.js', 'module/VisualPageBuilder/asset/menu-builder/**/*.js', 'module/VisualPageBuilder/asset/map/**/*.js', 'theme/visual-page-builder-companion/asset/js/**/*.js'],
    languageOptions: { ecmaVersion: 2017, sourceType: 'script', globals: { ...globals.browser } },
    rules: {
      'no-unused-vars': ['error', { args: 'none', caughtErrors: 'none' }],
      'eqeqeq': ['error', 'smart'],
      'strict': ['error', 'function'],
      'no-implicit-globals': 'error'
    }
  },
  {
    files: ['tests/**/*.js', 'eslint.config.js'],
    languageOptions: { ecmaVersion: 2022, sourceType: 'commonjs', globals: { ...globals.node } },
    rules: { 'no-unused-vars': ['error', { args: 'none' }] }
  }
];
