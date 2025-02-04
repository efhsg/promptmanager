<?php

namespace app\services;

use app\models\Context;
use Throwable;
use yii\base\Component;
use yii\db\Exception;

class ContextService extends Component
{
    /**
     * Persists the given Context model.
     *
     * @param Context $model
     * @return bool
     * @throws Exception
     */
    public function saveContext(Context $model): bool
    {
        return $model->save();
    }

    /**
     * Deletes the given Context model.
     *
     * @param Context $model
     * @return bool
     * @throws Exception|Throwable
     */
    public function deleteContext(Context $model): bool
    {
        return $model->delete() !== false;
    }
}
