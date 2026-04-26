-- ahg-image-animate — default settings
INSERT IGNORE INTO `image_animate_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('animate_enabled',         '1',         'boolean', 'Master toggle for the image-animation feature'),
('animate_user_button',     '1',         'boolean', 'Show the Animate Image button on IO show pages'),
('animate_default_motion',  'zoom_in',   'string',  'Default motion preset: zoom_in, zoom_out, pan_lr, pan_rl, ken_burns_diagonal'),
('animate_duration_secs',   '5',         'decimal', 'Clip length in seconds'),
('animate_fps',             '25',        'integer', 'Output framerate'),
('animate_width',           '1280',      'integer', 'Output width in pixels'),
('animate_height',          '720',       'integer', 'Output height in pixels'),
('animate_zoom_strength',   '1.30',      'decimal', 'Final zoom factor for zoom_in / zoom_out (1.0 = no zoom)');
