<?php

    try {
        
        $link = new PDO(

            "mysql:host=" . DB['HOST'] . ";dbname=" . DB['NAME'] . ";charset=utf8mb4",
            DB['USER'],
            DB['PASS'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
            
        );

    } catch (PDOException $e) {

        error_log("Database connection failed: {$e->getMessage()}");
        http_response_code(500);

        exit('Database unavailable');

    } 

?>