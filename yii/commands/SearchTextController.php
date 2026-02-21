<?php

namespace app\commands;

use app\services\SearchTextExtractor;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Console command to backfill search_text columns from Quill Delta JSON content.
 */
class SearchTextController extends Controller
{
    private const TABLES = [
        'context' => 'content',
        'prompt_template' => 'template_body',
        'prompt_instance' => 'final_prompt',
        'note' => 'content',
        'field' => 'content',
    ];

    public function actionBackfill(): int
    {
        $totalUpdated = 0;

        foreach (self::TABLES as $table => $contentColumn) {
            $this->stdout("Processing {$table}...\n");

            $rows = Yii::$app->db->createCommand(
                "SELECT id, {$contentColumn} FROM {{%{$table}}} WHERE search_text IS NULL"
            )->queryAll();

            $updated = 0;
            foreach ($rows as $row) {
                $searchText = SearchTextExtractor::extract($row[$contentColumn]);
                Yii::$app->db->createCommand()->update(
                    "{{%{$table}}}",
                    ['search_text' => $searchText ?: null],
                    ['id' => $row['id']]
                )->execute();
                $updated++;
            }

            $this->stdout("  Updated {$updated} rows.\n");
            $totalUpdated += $updated;
        }

        $this->stdout("\nDone. Total rows updated: {$totalUpdated}\n");

        return ExitCode::OK;
    }
}
