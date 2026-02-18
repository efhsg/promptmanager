<?php

namespace app\controllers;

use app\services\FileExportService;
use common\enums\CopyType;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

class ExportController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly FileExportService $exportService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if ($action->id === 'to-file') {
            Yii::$app->session->close();
        }

        return true;
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'to-file' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['to-file'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionToFile(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $rawBody = Yii::$app->request->rawBody;
        if ($rawBody === '') {
            return ['success' => false, 'message' => 'Empty request body.'];
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid JSON data.'];
        }

        $content = $data['content'] ?? '';
        $format = CopyType::tryFrom($data['format'] ?? '') ?? CopyType::MD;
        $filename = trim($data['filename'] ?? '');
        $directory = $data['directory'] ?? '/';
        $projectId = (int) ($data['project_id'] ?? 0);
        $overwrite = (bool) ($data['overwrite'] ?? false);

        if ($filename === '') {
            return ['success' => false, 'message' => 'Filename is required.'];
        }

        if ($projectId <= 0) {
            return ['success' => false, 'message' => 'Invalid project ID.'];
        }

        return $this->exportService->exportToFile(
            $content,
            $format,
            $filename,
            $directory,
            $projectId,
            Yii::$app->user->id,
            $overwrite
        );
    }
}
