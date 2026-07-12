const defaultPublicPath = process.env.NODE_ENV === 'production' ? '/momars/' : '/';

module.exports = {
  publicPath: process.env.VUE_APP_PUBLIC_PATH || defaultPublicPath,
  devServer: {
    historyApiFallback: true,
  },
  transpileDependencies: ['vuetify'],
};
