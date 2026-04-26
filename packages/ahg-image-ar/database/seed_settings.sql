-- ahg-image-ar — default settings (AI image-to-video pipeline)
INSERT IGNORE INTO `image_ar_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('ar_enabled',           '1',                                'boolean', 'Master toggle for the image-animation feature'),
('ar_user_button',       '1',                                'boolean', 'Show the Animate image button on IO show pages'),
('ar_server_url',        'http://192.168.0.78:5052',         'string',  'Base URL of the video-server FastAPI on the AI host'),
('ar_model',             'svd',                              'string',  'svd | svd-xt | cogvideox-2b | wan-2.1 (must be loaded on the server)'),
('ar_num_frames',        '14',                               'integer', 'Frames per clip (SVD: 14 or 25; CogVideoX: 49)'),
('ar_fps',               '7',                                'integer', 'Output framerate (SVD canonical = 7)'),
('ar_motion_bucket_id',  '127',                              'integer', 'SVD motion strength 1–255 (higher = more movement, more artifacts)'),
('ar_default_prompt',    '',                                 'string',  'Default text prompt (ignored by SVD; used by CogVideoX/WAN)'),
('ar_seed',              '0',                                'integer', '0 = random per-call; >0 = deterministic'),
('ar_request_timeout',   '900',                              'integer', 'Max seconds for one AI generation (allow generous headroom on 8 GB CPU-offload)');
