<?php

namespace app\controllers;

use app\services\CopyFormatConverter;
use app\services\copyformat\MarkdownParser;
use app\services\copyformat\QuillDeltaWriter;
use common\enums\CopyType;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;

class ScratchPadController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['create', 'import-markdown', 'convert-format'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionCreate(): string
    {
        return $this->render('create');
    }

    public function actionImportMarkdown(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $file = UploadedFile::getInstanceByName('mdFile');

        if ($file === null) {
            return [
                'success' => false,
                'errors' => ['mdFile' => ['Please select a file.']],
            ];
        }

        if ($file->size > 1048576) {
            return [
                'success' => false,
                'errors' => ['mdFile' => ['File size must not exceed 1MB.']],
            ];
        }

        $allowedExtensions = ['md', 'markdown', 'txt'];
        if (!in_array(strtolower($file->extension), $allowedExtensions, true)) {
            return [
                'success' => false,
                'errors' => ['mdFile' => ['Invalid file type. Accepted: .md, .markdown, .txt']],
            ];
        }

        $markdownContent = @file_get_contents($file->tempName);
        if ($markdownContent === false) {
            return ['success' => false, 'message' => 'Failed to read uploaded file.'];
        }

        $parser = new MarkdownParser();
        $blocks = $parser->parse($markdownContent);
        $deltaWriter = new QuillDeltaWriter();
        $deltaJson = $deltaWriter->writeFromBlocks($blocks);

        return [
            'success' => true,
            'importData' => [
                'content' => $deltaJson,
            ],
        ];
    }

    public function actionConvertFormat(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'message' => 'Invalid request method.'];
        }

        $rawBody = Yii::$app->request->rawBody;
        $data = json_decode($rawBody, true);

        if ($data === null) {
            return ['success' => false, 'message' => 'Invalid JSON data.'];
        }

        $content = $data['content'] ?? '';
        $format = $data['format'] ?? 'text';

        $copyType = CopyType::tryFrom($format) ?? CopyType::TEXT;
        $converter = new CopyFormatConverter();
        $convertedContent = $converter->convertFromQuillDelta($content, $copyType);

        return [
            'success' => true,
            'content' => $convertedContent,
        ];
    }
}
