<?php

    // Get all query params except 'url'
    $params = array_diff_key($_GET ?? [], ['url' => '']);

?>