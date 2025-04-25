<?php

namespace app\assets;

use yii\web\AssetBundle;
use yii\web\View;

class HighlightAsset extends AssetBundle
{
    public $basePath = '@webroot/quill';
    public $baseUrl = '@web/quill';
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