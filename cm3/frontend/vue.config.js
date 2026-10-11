const path = require('path'); 
const fs = require('fs');
const webpack = require('webpack');
const CopyWebpackPlugin = require('copy-webpack-plugin');

const currentPublicPath = process.env.VUE_APP_PUBLIC_PATH || '/';
module.exports = {
  productionSourceMap: process.env.SOURCEMAP === 'true',
  publicPath: currentPublicPath,
  outputDir: process.env.OUTPUT_DIR || 'dist',

  devServer: {
    historyApiFallback: true,
    allowedHosts: 'all', 
    static: {
        directory: path.join(__dirname, 'customization'),
        publicPath: currentPublicPath, 
    },
    proxy: {
      '^/api/': {
        target: 'http://localhost:8081',
        changeOrigin: true, // so CORS doesn't bite us.
        pathRewrite: { '^/api': '' },
      },
    },
  },
  configureWebpack: {
    plugins: [
      new CopyWebpackPlugin({
        patterns: [
          // Copies customization template to the build folder in case of post-build overrides
          { from: './customization', to: '.' }
        ],
      }),
    ],
  },
  chainWebpack: (config) => {
    config
      .plugin('html')
      .tap((args) => {
        args[0].template = './src/index.html';
        args[0].title = 'ConCrescent';
        return args;
      });
    if (process.env.NODE_ENV === 'production') {
        const customizationDir = path.join(__dirname, 'customization');
        let excludedJsRegex = null;

        if (fs.existsSync(customizationDir)) {
        // Read all files, filter down to just JavaScript, and escape special characters like dots
        const jsFiles = fs.readdirSync(customizationDir)
            .filter(file => file.endsWith('.js'))
            .map(file => file.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&')); // Clean escape regex

        if (jsFiles.length > 0) {
            // Stitch files together into a pattern matching their output state (e.g., /(config\.js|theme\.js)$/)
            excludedJsRegex = new RegExp(`(${jsFiles.join('|')})$`);
            console.log(`\n Webpack Optimizer: Excluded raw text assets: [${jsFiles.join(', ')}]\n`);
            config.optimization.minimizer('terser').tap((args) => {
                // exclude them via regEx
                args[0].exclude = excludedJsRegex;
                return args;
            });
        }
}
    }
  },
  transpileDependencies: [
    // can be string or regex
  ],

  pluginOptions: {
    vuetify: {},
  },
};
