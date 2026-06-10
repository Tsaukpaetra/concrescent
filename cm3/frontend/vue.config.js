const path = require('path'); 
const webpack = require('webpack');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
  productionSourceMap: false,
  publicPath: '',

  devServer: {
    historyApiFallback: true,
    static: {
        directory: path.join(__dirname, 'customization'),
        publicPath: '/', 
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
    ]
  },
  chainWebpack: (config) => {
    config
      .plugin('html')
      .tap((args) => {
        args[0].template = './src/index.html';
        args[0].title = 'ConCrescent';
        return args;
      });
    config
      .plugin('define')
      .tap((args) => args);
  },
  transpileDependencies: [
    // can be string or regex
  ],

  pluginOptions: {
    vuetify: {},
  },
};
