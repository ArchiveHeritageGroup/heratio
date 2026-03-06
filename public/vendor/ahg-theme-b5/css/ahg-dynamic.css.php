<?php
header('Content-Type: text/css');

// Get settings from database
require_once dirname(__FILE__) . '/../../../config/ProjectConfiguration.class.php';
$configuration = ProjectConfiguration::getApplicationConfiguration('qubit', 'prod', false);
sfContext::createInstance($configuration);

use Illuminate\Database\Capsule\Manager as DB;

$settings = [];
$rows = DB::table('ahg_settings')->where('setting_group', 'general')->get();
foreach ($rows as $row) {
    $settings[$row->setting_key] = $row->setting_value;
}

$cardHeaderBg = $settings['ahg_card_header_bg'] ?? '#1a5f2a';
$cardHeaderText = $settings['ahg_card_header_text'] ?? '#ffffff';
$buttonBg = $settings['ahg_button_bg'] ?? '#1a5f2a';
$buttonText = $settings['ahg_button_text'] ?? '#ffffff';
$linkColor = $settings['ahg_link_color'] ?? '#1a5f2a';
$sidebarBg = $settings['ahg_sidebar_bg'] ?? '#f8f9fa';
$sidebarText = $settings['ahg_sidebar_text'] ?? '#333333';
?>
/* AHG Dynamic Theme Styles */
/* Card Headers */
.card-header {
    background-color: <?php echo $cardHeaderBg; ?> !important;
    color: <?php echo $cardHeaderText; ?> !important;
}
.card-header h1, .card-header h2, .card-header h3,
.card-header h4, .card-header h5, .card-header h6,
.card-header i, .card-header span, .card-header a {
    color: <?php echo $cardHeaderText; ?> !important;
}

/* Buttons */
.btn-primary {
    background-color: <?php echo $buttonBg; ?> !important;
    border-color: <?php echo $buttonBg; ?> !important;
    color: <?php echo $buttonText; ?> !important;
}
.btn-primary:hover {
    background-color: <?php echo adjustBrightness($buttonBg, -20); ?> !important;
    border-color: <?php echo adjustBrightness($buttonBg, -20); ?> !important;
}

/* Links */
a:not(.btn):not(.nav-link):not(.dropdown-item) {
    color: <?php echo $linkColor; ?>;
}
a:not(.btn):not(.nav-link):not(.dropdown-item):hover {
    color: <?php echo adjustBrightness($linkColor, -30); ?>;
}

/* Sidebar */
.sidebar, #sidebar-content {
    background-color: <?php echo $sidebarBg; ?> !important;
    color: <?php echo $sidebarText; ?> !important;
}

<?php
function adjustBrightness($hex, $steps) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
}
?>

/* Override for native HTML5 video controls */
.ahg-native-video::-webkit-media-controls,
.ahg-native-video::-webkit-media-controls-panel,
.ahg-native-video::-webkit-media-controls-start-playback-button {
    -webkit-appearance: media-controls-container !important;
    display: flex !important;
}
video.ahg-native-video {
    pointer-events: auto !important;
}
