<?php

    define('MAIL', [
        'FROM' => 'noreply@example.com',
        'NAME' => 'Project Mailer',
        'HOST' => 'smtp.example.com',
        'PORT' => 587, // TLS
        'USER' => 'smtp-user@example.com',
        'PASS' => 'yourStrongPassword!123'
    ]);

    define('AUTHENTICATION', [
        'DIFFICULTY' => 'medium',
        'LENGTH' => 12,
        'ALGORITHM' => PASSWORD_BCRYPT,
        'PEPPER' => [
            'ALGORITHM' => 'sha512',
            'VALUE' => '7NQEG-q5~#y5V?/x2-_wH1s,@?AIr[|9.3!Vgu?SqC+X-sm+-&lS6481<&[.>V,'
        ],
        'COST' => 10
    ]);

    define('DB', [
        'HOST' => 'localhost',
        'NAME' => 'fundamental',
        'USER' => 'root',
        'PASS' => '',
        'PREFIX' => '', // optioneel, bijv. 'mw_'
        'CHARSET' => 'utf8mb4' // veiliger dan UTF8
    ]);