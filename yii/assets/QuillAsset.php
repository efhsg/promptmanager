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
    ];
    public $depends = [
        'yii\web\YiiAsset',
    ];
}
