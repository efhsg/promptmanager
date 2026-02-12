<?php

namespace app\controllers\api;

use app\models\Note;
use app\services\copyformat\DeltaParser;
use app\services\copyformat\MarkdownParser;
use app\services\copyformat\QuillDeltaWriter;
use app\services\ProjectService;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\rest\Controller;
use yii\web\Response;

class NoteController extends Controller
{
    private DeltaParser $deltaParser;
    private ProjectService $projectService;

    public function __construct(
        $id,
        $module,
        DeltaParser $deltaParser = new DeltaParser(),
        ProjectService $projectService = new ProjectService(),
        $config = []
    ) {
        $this->deltaParser = $deltaParser;
        $this->projectService = $projectService;
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];
        return $behaviors;
    }

    public function actionCreate(): array
    {
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;

        $name = $request->post('name');
        $content = $request->post('content');
        $projectName = $request->post('project_name');
        $format = $request->post('format', 'text');

        // Validate format parameter
        if (!in_array($format, ['text', 'delta', 'md'], true)) {
            Yii::$app->response->statusCode = 400;
            return ['success' => false, 'errors' => ['format' => ['Format must be "text", "delta", or "md".']]];
        }

        // Validate content type matches format
        if (in_array($format, ['text', 'md'], true) && $content !== null && !is_string($content)) {
            Yii::$app->response->statusCode = 400;
            return ['success' => false, 'errors' => ['content' => ['Content must be a string when format is "text" or "md".']]];
        }

        // Convert content based on format
        $deltaContent = $this->convertContent($content, $format);
        if ($deltaContent === false) {
            Yii::$app->response->statusCode = 400;
            return ['success' => false, 'errors' => ['content' => ['Invalid Delta JSON format.']]];
        }

        // Find or create project by name
        $projectResult = $this->projectService->findOrCreateByName($user->id, $projectName);
        if (is_array($projectResult)) {
            Yii::$app->response->statusCode = 422;
            return ['success' => false, 'errors' => ['project_name' => $projectResult]];
        }

        $note = new Note([
            'user_id' => $user->id,
            'project_id' => $projectResult,
            'name' => $name,
            'content' => $deltaContent,
        ]);

        if ($note->save()) {
            Yii::$app->response->statusCode = 201;
            return ['success' => true, 'id' => $note->id];
        }

        Yii::$app->response->statusCode = 422;
        return ['success' => false, 'errors' => $note->getErrors()];
    }

    /**
     * Converts content to Quill Delta JSON format.
     *
     * @param string|array|null $content Content as text, markdown, Delta string, or Delta object
     * @param string $format 'text', 'md', or 'delta'
     * @return string|null|false Delta JSON string, null for empty, false for invalid delta
     */
    private function convertContent(string|array|null $content, string $format): string|false|null
    {
        if ($content === null || $content === '' || $content === []) {
            return null;
        }

        if ($format === 'delta') {
            if (is_array($content)) {
                if (!isset($content['ops']) || !is_array($content['ops'])) {
                    return false;
                }
                return $this->deltaParser->encode($content);
            }

            $decoded = $this->deltaParser->decode($content);
            if ($decoded === null) {
                return false;
            }
            return $this->deltaParser->encode($decoded);
        }

        if ($format === 'md') {
            $parser = new MarkdownParser();
            $blocks = $parser->parse($content);
            $deltaWriter = new QuillDeltaWriter();
            return $deltaWriter->writeFromBlocks($blocks);
        }

        // Convert plain text to Delta format
        $ops = [['insert' => $content . "\n"]];
        return $this->deltaParser->encode(['ops' => $ops]);
    }
}
