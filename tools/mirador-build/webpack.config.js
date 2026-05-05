const path = require('path');

module.exports = {
  entry: './src/index.js',
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: 'mirador.min.js',
    library: {
      name: 'Mirador',
      type: 'window',
      export: 'default',
    },
  },
  module: {
    rules: [
      {
        test: /\.m?jsx?$/,
        exclude: /node_modules\/(?!(mirador|mirador-image-tools|mirador-dl-plugin)\/)/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', { targets: '>0.25%, not dead' }],
              ['@babel/preset-react', { runtime: 'automatic' }],
            ],
          },
        },
      },
    ],
  },
  resolve: {
    extensions: ['.js', '.jsx', '.mjs'],
  },
  performance: {
    hints: false,
  },
};
