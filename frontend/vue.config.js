module.exports = {
  publicPath: process.env.VUE_APP_PUBLIC_PATH || '/',
  devServer: {
    historyApiFallback: true,
  },
  transpileDependencies: ['vuetify'],
};
