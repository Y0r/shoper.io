const path = require('path');
const miniCss = require('mini-css-extract-plugin');

module.exports = {
  mode: 'development',
  entry: {
    main: [
      './src/index.js'
    ]
  },

  output: {
    filename: '[name].bundle.js',
    path: path.resolve(__dirname, 'dist')
  },
  module: {
    rules: [
      {
        test: /\.css$/,
        use: [
          miniCss.loader,
          'css-loader',
        ]
      },
      {
        test: /\.s[ac]ss$/,
        use: [
          miniCss.loader,
          'css-loader',
          'sass-loader',
        ]
      },
    ]
  },
  plugins: [
    new miniCss({
      filename: 'global-styling.css',
    })
  ],
}
