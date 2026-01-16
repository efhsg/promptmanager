<?php

namespace app\services;

use app\modules\identity\services\UserDataSeederInterface;
use Yii;
use yii\db\Exception;

class UserDataSeeder implements UserDataSeederInterface
{
    /**
     * @throws Exception
     */
    public function seed(int $userId): void
    {
        $data = [
            'codeBlock' => [
                'label' => null, // Set label to null
                'type' => 'text',
                'options' => null,
            ],
            'codeType' => [
                'label' => null, // Set label to null
                'type' => 'select',
                'options' => ['class', 'test', 'function', 'interface', 'migration'],
            ],
            'extraCriteria' => [
                'label' => null, // Set label to null
                'type' => 'multi-select',
                'options' => [
                    "don't write any comment",
                    "use SOLID, DRY, YAGNI principles",
                ],
            ],
        ];

        foreach ($data as $fieldName => $fieldData) {
            Yii::$app->db->createCommand()->insert('{{%field}}', [
                'user_id' => $userId,
                'project_id' => null,
                'name' => $fieldName,
                'type' => $fieldData['type'],
                'label' => $fieldData['label'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->execute();

            $fieldId = Yii::$app->db->getLastInsertID();

            if (!empty($fieldData['options'])) {
                $order = 0;
                foreach ($fieldData['options'] as $option) {
                    Yii::$app->db->createCommand()->insert('{{%field_option}}', [
                        'field_id' => $fieldId,
                        'value' => $option,
                        'label' => null,
                        'selected_by_default' => in_array(
                            $option,
                            ["don't write any comment", "use SOLID, DRY, YAGNI principles"]
                        ),
                        'order' => $order += 10,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ])->execute();
                }
            }
        }
    }
}
