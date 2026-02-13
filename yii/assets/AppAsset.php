<?php

namespace app\assets;

use yii\web\AssetBundle;

class AppAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/spacelab.min.css',
        'css/site.css',
        'css/mobile.css',
    ];
    public $js       = [
        'js/directory-selector.js',
        'js/form.js',
        'js/quick-search.js',
        'js/advanced-search.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
        'yii\bootstrap5\BootstrapAsset',
        'yii\bootstrap5\BootstrapPluginAsset',
    ];
}
