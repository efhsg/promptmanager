<?php

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Asset bundle for PathSelectorField JavaScript functionality
 *
 * Provides client-side logic for file path selection and preview in prompt forms.
 * Handles modal interactions, path selection, and file preview with syntax highlighting.
 */
class PathSelectorFieldAsset extends AssetBundle
{
    public $sourcePath = '@app/assets/path-selector-field';

    public $js = [
        'path-selector-field.js',
    ];

    public $depends = [
        'yii\bootstrap5\BootstrapAsset',
        'app\assets\HighlightAsset',
    ];
}
