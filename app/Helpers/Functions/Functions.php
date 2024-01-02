<?php


/*
|--------------------------------------------------------------------------
| Dump and Die with Headers Helper
|--------------------------------------------------------------------------
|
| This helper function is a wrapper for the dd() function that allows
| us to set the headers before calling the dd() function.
|
*/
if (!function_exists('ddh')) {
    function ddh(mixed ...$vars) {
        // Now we can set our access control policy to
        // accept all...
        header('Access-Control-Allow-Origin: http://localhost:8100');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: *');

        // Finally call the original dd method...
        dd(...func_get_args());
    }
}