<?php

namespace app\assets;

use yii\web\AssetBundle;

class QuillAsset extends AssetBundle
{
    public $basePath = '@webroot/quill/2.0.3';
    public $baseUrl = '@web/quill/2.0.3';

    public $css = [
        'quill.snow.css',
        'highlight/default.min.css',
    ];

    public $js = [
        'highlight/highlight.min.js',
        'quill.min.js',
        'editor-init.min.js',
    ];

    public $jsOptions = ['defer' => true];

    public $depends = ['yii\web\YiiAsset'];
}
