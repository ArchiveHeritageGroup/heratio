/**
 * Heratio Mirador 4 MUI theme.
 *
 * Mirador 4 uses MUI v7. Custom palettes are merged via the
 * miradorConfig.theme key (see https://github.com/ProjectMirador/mirador/wiki/Mirador-Custom-Theming).
 * We read the embedder-provided palette from window.AHG_IIIF.theme so
 * the operator can re-skin Mirador from /admin/ahgSettings/iiif
 * without rebuilding the bundle. Defaults come from the Heratio
 * Bootstrap 5 palette: primary (#2c3e50, slate blue), secondary
 * (#6c757d, BS5 grey), danger (#dc3545, BS5 red), success (#198754,
 * BS5 green).
 */

const DEFAULT_PALETTE = {
  primary:   { main: '#2c3e50', contrastText: '#ffffff' },
  secondary: { main: '#6c757d', contrastText: '#ffffff' },
  error:     { main: '#dc3545', contrastText: '#ffffff' },
  warning:   { main: '#ffc107', contrastText: '#212529' },
  info:      { main: '#0dcaf0', contrastText: '#212529' },
  success:   { main: '#198754', contrastText: '#ffffff' },
  background: { default: '#f8f9fa', paper: '#ffffff' },
};

const DEFAULT_TYPOGRAPHY = {
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
  fontSize: 14,
};

/**
 * Build a Mirador theme override. Mirador deep-merges this into its
 * stock theme, so missing keys fall back to Mirador's defaults rather
 * than nullifying them.
 */
function buildHeratioTheme(overrides) {
  const palette = Object.assign({}, DEFAULT_PALETTE, (overrides && overrides.palette) || {});
  const typography = Object.assign({}, DEFAULT_TYPOGRAPHY, (overrides && overrides.typography) || {});

  return {
    typography,
    palette: {
      mode: (overrides && overrides.mode) || 'light',
      primary:    palette.primary,
      secondary:  palette.secondary,
      error:      palette.error,
      warning:    palette.warning,
      info:       palette.info,
      success:    palette.success,
      background: palette.background,
    },
    shape: { borderRadius: 4 },
    components: {
      MuiButton: {
        styleOverrides: {
          root: { textTransform: 'none' },
        },
      },
      MuiAppBar: {
        styleOverrides: {
          root: { boxShadow: 'none', borderBottom: '1px solid rgba(0,0,0,.08)' },
        },
      },
    },
  };
}

/**
 * Resolve theme from window.AHG_IIIF.theme. The expected shape is
 *
 *   window.AHG_IIIF = {
 *     theme: {
 *       mode: 'light',
 *       palette: { primary: { main: '#2c3e50' }, ... },
 *       typography: { fontSize: 14 }
 *     }
 *   }
 *
 * Missing / wrong-shaped input degrades to defaults.
 */
function resolveHeratioTheme() {
  if (typeof window === 'undefined') return buildHeratioTheme(null);
  const cfg = (window.AHG_IIIF && window.AHG_IIIF.theme && typeof window.AHG_IIIF.theme === 'object')
    ? window.AHG_IIIF.theme
    : null;
  return buildHeratioTheme(cfg);
}

export { buildHeratioTheme, resolveHeratioTheme, DEFAULT_PALETTE };
export default resolveHeratioTheme;
