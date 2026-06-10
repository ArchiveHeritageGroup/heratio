{{-- heratio#1183 Point-cloud viewer: standalone full-screen Potree page for one octree. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>{{ $cloud->title }} - {{ __('Point cloud') }}</title>
  <link rel="stylesheet" type="text/css" href="/vendor/potree/libs/potree/potree.css">
  <link rel="stylesheet" type="text/css" href="/vendor/potree/libs/jquery-ui/jquery-ui.min.css">
  <link rel="stylesheet" type="text/css" href="/vendor/potree/libs/openlayers3/ol.css">
  <link rel="stylesheet" type="text/css" href="/vendor/potree/libs/spectrum/spectrum.css">
  <link rel="stylesheet" type="text/css" href="/vendor/potree/libs/jstree/themes/mixed/style.css">
  <style nonce="{{ $cspNonce ?? '' }}">
    html, body { margin:0; height:100%; width:100%; overflow:hidden; }
    #potree_render_area { position:absolute; inset:0; }
    #pc_bar { position:absolute; top:0; left:0; right:0; z-index:1000; padding:.5rem .75rem;
      background:rgba(20,20,20,.72); color:#fff; font:14px/1.3 system-ui,sans-serif; display:flex; gap:.75rem; align-items:center; }
    #pc_bar a { color:#9ec5ff; text-decoration:none; }
    #pc_bar .t { font-weight:600; }
  </style>
</head>
<body>
  <div id="pc_bar">
    <a href="javascript:history.back()" title="{{ __('Back') }}">&larr; {{ __('Back') }}</a>
    <span class="t">{{ $cloud->title }}</span>
    @if($cloud->point_count)<span style="opacity:.8">{{ number_format($cloud->point_count) }} {{ __('points') }}</span>@endif
  </div>
  <div class="potree_container" style="position:absolute;top:0;width:100%;height:100%;left:0">
    <div id="potree_render_area"></div>
  </div>

  <script src="/vendor/potree/libs/jquery/jquery-3.1.1.min.js"></script>
  <script src="/vendor/potree/libs/spectrum/spectrum.js"></script>
  <script src="/vendor/potree/libs/jquery-ui/jquery-ui.min.js"></script>
  <script src="/vendor/potree/libs/other/BinaryHeap.js"></script>
  <script src="/vendor/potree/libs/tween/tween.min.js"></script>
  <script src="/vendor/potree/libs/d3/d3.js"></script>
  <script src="/vendor/potree/libs/proj4/proj4.js"></script>
  <script src="/vendor/potree/libs/openlayers3/ol.js"></script>
  <script src="/vendor/potree/libs/i18next/i18next.js"></script>
  <script src="/vendor/potree/libs/jstree/jstree.js"></script>
  <script src="/vendor/potree/libs/potree/potree.js"></script>
  <script src="/vendor/potree/libs/plasio/js/laslaz.js"></script>
  <script nonce="{{ $cspNonce ?? '' }}">
    window.viewer = new Potree.Viewer(document.getElementById("potree_render_area"));
    viewer.setEDLEnabled(true);
    viewer.setFOV(60);
    viewer.setPointBudget(2_000_000);
    viewer.loadSettingsFromURL();
    viewer.setBackground("gradient");
    viewer.loadGUI(() => {
      viewer.setLanguage('en');
      viewer.toggleSidebar();
    });
    Potree.loadPointCloud(@json($octreeUrl), @json($cloud->title), e => {
      let pointcloud = e.pointcloud;
      let material = pointcloud.material;
      material.size = 1;
      material.pointSizeType = Potree.PointSizeType.ADAPTIVE;
      material.shape = Potree.PointShape.SQUARE;
      material.activeAttributeName = "rgba";
      viewer.scene.addPointCloud(pointcloud);
      viewer.fitToScreen();
    });
  </script>
</body>
</html>
