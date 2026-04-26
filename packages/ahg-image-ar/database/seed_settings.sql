-- ahg-image-ar — default settings
INSERT IGNORE INTO `image_ar_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('ar_enabled',           '1',         'boolean', 'Master toggle for the image-animation feature'),
('ar_user_button',       '1',         'boolean', 'Show the Animate image button on IO show pages'),
('ar_default_motion',    'zoom_in',   'string',  'Default MP4 motion: zoom_in, zoom_out, pan_lr, pan_rl, ken_burns_diagonal'),
('ar_duration_secs',     '5',         'decimal', 'Clip length in seconds'),
('ar_fps',               '25',        'integer', 'Output framerate'),
('ar_width',             '1280',      'integer', 'Output width in pixels'),
('ar_height',            '720',       'integer', 'Output height in pixels'),
('ar_zoom_strength',     '1.30',      'decimal', 'Final zoom factor for zoom_in / zoom_out');
