<?php

namespace Ozi\AutoContent\Support;

class Logger
{
    public function error($message, array $context = [])
    {
        error_log('[ozi-acwp][error] ' . $message . ' ' . wp_json_encode($context));
    }

    public function info($message, array $context = [])
    {
        error_log('[ozi-acwp][info] ' . $message . ' ' . wp_json_encode($context));
    }
}
