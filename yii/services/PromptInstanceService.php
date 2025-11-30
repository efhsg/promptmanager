<?php


namespace app\services;

use app\models\PromptInstance;
use app\models\PromptTemplate;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\web\NotFoundHttpException;

class PromptInstanceService
{
    /**
     * @throws Exception
     */
    public function saveModel(PromptInstance $model, array $postData): bool
    {
        return $model->load($postData) && $model->save();
    }

    /**
     * Finds the PromptInstance model based on its primary key value and verifies that it belongs
     * to the provided user.
     *
     * @param int $id
     * @param int $userId
     * @return PromptInstance
     * @throws NotFoundHttpException if the model is not found or not owned by the user.
     */
    public function findModelWithOwner(int $id, int $userId): ActiveRecord
    {
        $model = PromptInstance::find()
            ->where(['prompt_instance.id' => $id])
            ->andWhere([
                'exists',
                PromptTemplate::find()
                    ->innerJoin('project', 'prompt_template.project_id = project.id')
                    ->where('prompt_template.id = prompt_instance.template_id')
                    ->andWhere(['project.user_id' => $userId])
            ])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('The requested prompt instance does not exist or is not yours.');
        }

        return $model;
    }

    public function parseRawFieldValues(array $fieldValues): array
    {
        $rawPost = file_get_contents('php://input');
        if ($rawPost === false || $rawPost === '') {
            return $fieldValues;
        }

        parse_str($rawPost, $rawData);
        if (!isset($rawData['PromptInstanceForm']['fields']) || !is_array($rawData['PromptInstanceForm']['fields'])) {
            return $fieldValues;
        }

        $params = [];
        foreach (explode('&', $rawPost) as $param) {
            if (!str_starts_with($param, 'PromptInstanceForm%5Bfields%5D')) {
                continue;
            }
            $parts = explode('=', $param, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = urldecode($parts[0]);
            $value = urldecode($parts[1]);
            if (preg_match('/PromptInstanceForm\[fields]\[(\d+)](\[])?/', $key, $matches)) {
                $fieldId = $matches[1];
                $isArrayField = isset($matches[2]) && $matches[2] === '[]';
                if ($isArrayField) {
                    $params[$fieldId][] = $value;
                } elseif (!isset($params[$fieldId])) {
                    $params[$fieldId] = $value;
                }
            }
        }

        foreach ($params as $fid => $val) {
            if (isset($fieldValues[$fid]) && is_array($fieldValues[$fid])) {
                continue;
            }
            $fieldValues[$fid] = $val;
        }

        return $fieldValues;
    }
}
