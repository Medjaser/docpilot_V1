<?php

return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:'.((int) env('DOCPILOT_MAX_UPLOAD_MB', 10) * 1024)],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],
];

