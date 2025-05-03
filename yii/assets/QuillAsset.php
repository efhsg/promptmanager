<?php
namespace app\assets;

use yii\web\AssetBundle;

class QuillAsset extends AssetBundle
{
    public $basePath = '@webroot/quill/1.3.7';
    public $baseUrl = '@web/quill/1.3.7';

    public $css = ['quill.snow.css'];
    public $js = ['quill.min.js', 'editor-init.min.js'];

    public $jsOptions = ['defer' => true];

    public $depends = ['yii\web\YiiAsset'];
}