<?php

/**
 * Forward the request to the Laravel front controller when the web server
 * document root is the project folder (common in XAMPP) instead of /public.
 */
require __DIR__.'/public/index.php';
