<?php

use Eccube2\Console\Application;

if (class_exists('\Eccube2\Console\Application')) {
    Application::appendConfigPath(__DIR__.'/config');
}
