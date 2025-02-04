<?php


namespace app\rbac;

use app\models\PromptTemplate;
use yii\rbac\Rule;

class PromptTemplateOwnerRule extends Rule
{
    public $name = 'isPromptTemplateOwner';


    public function execute($user, $item, $params): bool
    {
        if (!isset($params['model']) || !($params['model'] instanceof PromptTemplate)) {
            return false;
        }
        return $params['model']->project->user_id === (int)$user;
    }
}
