<?php

namespace app\assets;

use yii\web\AssetBundle;

class QuillAsset extends AssetBundle
{
    public $basePath = '@webroot/quill';
    public $baseUrl = '@web/quill';
    public $css = [
        'quill.snow.css',
    ];
    public $js = [
        'quill.js',
        'quill-delta-to-html/quill-delta-to-html.min.js',
    ];
    public $depends = [
        'yii\web\YiiAsset',
    ];
}
