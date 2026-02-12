<?php

namespace app\migrations;

use RuntimeException;
use yii\db\Migration;
use yii\db\Query;

class m260211_000002_migrate_response_to_child_notes extends Migration
{
    public function safeUp(): void
    {
        $rows = (new Query())
            ->select(['id', 'user_id', 'project_id', 'name', 'response', 'created_at', 'updated_at'])
            ->from('{{%note}}')
            ->where(['NOT', ['response' => null]])
            ->andWhere(['NOT', ['response' => '']])
            ->all();

        $migrated = 0;
        foreach ($rows as $row) {
            if ($this->isEmptyDelta($row['response'])) {
                continue;
            }
            $this->insert('{{%note}}', [
                'user_id' => $row['user_id'],
                'project_id' => $row['project_id'],
                'name' => 'Response: ' . $row['name'],
                'content' => $row['response'],
                'type' => 'summation',
                'parent_id' => $row['id'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
            $migrated++;
        }

        $childCount = (new Query())
            ->from('{{%note}}')
            ->where(['type' => 'summation'])
            ->count();

        echo "    > Migrated {$migrated} responses to child notes. Total response children: {$childCount}\n";

        // Rename legacy type value 'response' → 'summation'
        $renamed = $this->db->createCommand()
            ->update('{{%note}}', ['type' => 'summation'], ['type' => 'response'])
            ->execute();
        echo "    > Renamed {$renamed} note type(s) from 'response' to 'summation'\n";
    }

    public function safeDown(): void
    {
        $schema = $this->db->getTableSchema('{{%note}}');
        if ($schema->getColumn('response') === null) {
            throw new RuntimeException(
                'Cannot reverse: response column does not exist. Run down for migration 2b first.'
            );
        }

        // Copy content from response children back to parent response column
        $children = (new Query())
            ->select(['id', 'parent_id', 'content'])
            ->from('{{%note}}')
            ->where(['type' => 'summation'])
            ->andWhere(['IS NOT', 'parent_id', null])
            ->all();

        foreach ($children as $child) {
            $this->update('{{%note}}', ['response' => $child['content']], ['id' => $child['parent_id']]);
        }

        // Delete child notes that were created by safeUp
        $this->delete('{{%note}}', ['and',
            ['type' => 'summation'],
            ['IS NOT', 'parent_id', null],
        ]);

        // Restore legacy type value 'summation' → 'response'
        $this->update('{{%note}}', ['type' => 'response'], ['type' => 'summation']);
    }

    private function isEmptyDelta(string $value): bool
    {
        $decoded = json_decode($value, true);
        if (!is_array($decoded) || !isset($decoded['ops'])) {
            return false;
        }
        $ops = $decoded['ops'];

        return count($ops) === 1
            && isset($ops[0]['insert'])
            && $ops[0]['insert'] === "\n";
    }
}
