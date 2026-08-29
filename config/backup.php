<?php

return [

    'path' => env('DB_BACKUP_PATH', '/var/www/backups'),

    'retention_days' => (int) env('DB_BACKUP_RETENTION_DAYS', 14),

];
