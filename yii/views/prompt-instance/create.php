<?php
/**
 * @var View $this
 * @var PromptInstanceForm $model
 * @var array $templates // [id => name]
 * @var array $templatesDescription // [id => description]
 * @var array $contexts // [id => name]
 * @var array $contextsContent // [id => content]
 * @var array $defaultContextIds
 */

use app\models\PromptInstanceForm;
use yii\web\View;

$this->title = 'Create Prompt Instance';
echo $this->render('_breadcrumbs', [
    'model' => null,
    'actionLabel' => $this->title,
]);
?>
<div class="prompt-instance-create container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-11 col-lg-10">
            <div class="card">
                <div class="card-body">
                    <?= $this->render('_form', [
                        'model' => $model,
                        'templates' => $templates,
                        'templatesDescription' => $templatesDescription,
                        'contexts' => $contexts,
                        'contextsContent' => $contextsContent,
                        'defaultContextIds' => $defaultContextIds,
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>
