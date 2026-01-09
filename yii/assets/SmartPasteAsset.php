<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Asset bundle for Smart Paste functionality.
 *
 * Provides clipboard-to-Quill paste with markdown detection.
 */
class SmartPasteAsset extends AssetBundle
{
    public $sourcePath = '@app/assets/smart-paste';

    public $js = [
        'smart-paste.js',
    ];

    public $depends = [
        'yii\bootstrap5\BootstrapPluginAsset',
        'app\assets\QuillAsset',
    ];
}
