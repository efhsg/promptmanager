<?php

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\View;

class HighlightAsset extends AssetBundle
{
    public $basePath = '@webroot/quill/2.0.3';
    public $baseUrl = '@web/quill/2.0.3';
    public $css = [
        'highlight/default.min.css',
    ];
    public $js = [
        'highlight/highlight.min.js',
    ];
    public $jsOptions = [
        'position' => View::POS_HEAD,
    ];
}
