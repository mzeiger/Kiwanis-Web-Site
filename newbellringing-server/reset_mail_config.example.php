<?php

/**
 * Copy to reset_mail_config.php and set values for your server.
 * Without reset_mail_config.php, reset emails use PHP mail() with a default From.
 */
return [
    'from' => [
        'name' => 'Monument Hill Kiwanis Bell Ringing',
        'address' => 'noreply-bellringing@monumenthillkiwanis.org',
    ],
    'smtp' => [
        'enabled' => false,
        'host' => 'smtp.monumenthillkiwanis.com',
        'port' => 587,
        'user' => '',
        'pass' => '',
        'secure' => 'tls',
    ],
];
