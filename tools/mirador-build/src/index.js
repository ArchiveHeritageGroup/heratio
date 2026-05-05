import Mirador from 'mirador';
import { miradorImageToolsPlugin } from 'mirador-image-tools';
import miradorDlPlugin from 'mirador-dl-plugin';

const plugins = [
  ...miradorImageToolsPlugin,
  ...miradorDlPlugin,
];

const wrappedViewer = (config, extraPlugins) => Mirador.viewer(
  config,
  Array.isArray(extraPlugins) ? plugins.concat(extraPlugins) : plugins,
);

export default {
  ...Mirador,
  viewer: wrappedViewer,
  plugins,
};
