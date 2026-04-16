<?php

if (!defined('ABSPATH')) {
    exit;
}

function ozi_acwp_array_get(array $data, $key, $default = null) {
    return array_key_exists($key, $data) ? $data[$key] : $default;
}
