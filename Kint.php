<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Kint extends BaseConfig
{
    public $plugins = [];

    public $maxDepth = 6;

    public $displayCalledFrom = true;

    public $expanded = false;

    public $richTheme = 'kint.css';

    public $plainTheme = 'kint.css';

    public $richFolder = false;

    public $cliColors = true;

    public $cliTheme = '';
}
