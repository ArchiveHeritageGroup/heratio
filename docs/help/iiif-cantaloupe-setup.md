> Heratio Help Center article. Category: System Administration / Infrastructure.

# IIIF Image Server (Cantaloupe)

Heratio uses Cantaloupe as its IIIF Image API server for the OpenSeadragon and Mirador viewers. Cantaloupe is required for those viewers to display and deep-zoom supported raster images, including JPEG and PNG, as well as TIFF and JPEG 2000. These formats may display in a browser without Cantaloupe, but the Heratio deep-zoom viewers request them through the IIIF endpoint.

Cantaloupe is optional only if the installation will use the browser-native Image viewer instead of OpenSeadragon or Mirador.

## How It Works

When OpenSeadragon or Mirador displays a supported raster image, Heratio requests its IIIF `info.json` document and image tiles from Cantaloupe. Cantaloupe must be running, and the web server must proxy `/iiif/` requests to it. A missing or misconfigured endpoint can return an HTML error page where the viewer expects IIIF JSON, causing a JSON parse error.

## Architecture

- **Cantaloupe** runs on port 8182 by default and serves IIIF Image API 3.0
- **Nginx** proxies `/iiif/` requests to Cantaloupe; this proxy must be enabled in the Heratio virtual host
- **OpenSeadragon** and **Mirador** viewers consume IIIF tiles
- Path resolution uses hostname-based mapping via delegates.rb

## Supported Formats

- TIFF (.tif, .tiff), JPEG 2000 (.jp2, .jpx), JPEG (.jpg, .jpeg) and PNG are routed through Cantaloupe by the Heratio deep-zoom viewers.
- GIF, BMP and WebP are not routed through the IIIF image service by those viewers and use the browser image path where supported.

## Viewer Modes

- **Deep Zoom** (OpenSeadragon): Tile-based pan/zoom with navigator
- **Mirador**: IIIF-compliant scholarly viewer with sidebar
- **Image**: Simple browser-native display

## URL Format

IIIF identifiers use _SL_ as path separator:

uploads/r/repo/hash/image.jpg becomes uploads_SL_r_SL_repo_SL_hash_SL_image.jpg

## Administration

- Config: /opt/cantaloupe-5.0.6/cantaloupe.properties
- Delegates: /opt/cantaloupe-5.0.6/delegates.rb
- Service: systemctl status cantaloupe
- Logs: /opt/cantaloupe-5.0.6/nohup.out

For full setup instructions see docs/cantaloupe-iiif-setup.md

## Troubleshooting

- If OpenSeadragon or Mirador reports a JSON parse error, inspect the corresponding `/iiif/3/<identifier>/info.json` request. It should return HTTP 200 with a JSON response. An HTML 404 or proxy error usually means Cantaloupe is stopped, the Nginx `/iiif/` proxy is missing or not active, or the identifier does not resolve in `delegates.rb`.
- After installing Cantaloupe, follow the installer output to include `snippets/heratio-iiif.conf` in the Heratio Nginx virtual host, validate the configuration, and reload Nginx. Installing Cantaloupe alone does not activate the proxy.
