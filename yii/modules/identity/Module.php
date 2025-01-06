<?php

namespace app\modules\identity;

use Yii;
use yii\console\Application;

/**
 * identity module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\identity\controllers';

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        if (Yii::$app instanceof Application) {
            $this->controllerNamespace = 'app\modules\identity\commands';
        }

        $config = require __DIR__ . '/config/main.php';
        Yii::configure($this, $config);
    }
}
