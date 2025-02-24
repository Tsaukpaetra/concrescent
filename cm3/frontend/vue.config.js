module.exports = {
  productionSourceMap: false,
  publicPath: '',

  devServer: {
    proxy: {
      '^/api/': {
        target: 'http://localhost:8081',
        changeOrigin: true, // so CORS doesn't bite us.
        pathRewrite: { '^/api': '' },
      },
    },
  },

  chainWebpack: (config) => {
    config
      .plugin('html')
      .tap((args) => {
        args[0].template = './src/index.html';
        args[0].title = 'ConCrescent';
        args[0].favicon = './customization/favicon.ico';
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
