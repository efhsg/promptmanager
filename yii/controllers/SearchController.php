<?php

namespace app\controllers;

use app\services\AdvancedSearchService;
use app\services\QuickSearchService;
use common\enums\SearchMode;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;

/**
 * Controller for search operations, providing AJAX search endpoints.
 */
class SearchController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly QuickSearchService $searchService,
        private readonly AdvancedSearchService $advancedSearchService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['quick', 'advanced'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'quick' => ['get'],
                    'advanced' => ['get'],
                ],
            ],
        ];
    }

    /**
     * Quick search endpoint for header search input.
     * Returns JSON with grouped results from all entity types.
     *
     * @throws BadRequestHttpException
     */
    public function actionQuick(): Response
    {
        if (!Yii::$app->request->isAjax) {
            throw new BadRequestHttpException('This endpoint only accepts AJAX requests.');
        }

        $query = Yii::$app->request->get('q', '');
        $userId = Yii::$app->user->id;

        $results = $this->searchService->search($query, $userId);

        return $this->asJson([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Advanced search endpoint with entity type filtering and search modes.
     * Returns JSON with grouped results from selected entity types.
     *
     * @throws BadRequestHttpException
     */
    public function actionAdvanced(): Response
    {
        if (!Yii::$app->request->isAjax) {
            throw new BadRequestHttpException('This endpoint only accepts AJAX requests.');
        }

        $query = Yii::$app->request->get('q', '');
        $types = Yii::$app->request->get('types', []);
        $modeValue = Yii::$app->request->get('mode', SearchMode::PHRASE->value);
        $userId = Yii::$app->user->id;

        $mode = is_string($modeValue) ? (SearchMode::tryFrom($modeValue) ?? SearchMode::PHRASE) : SearchMode::PHRASE;

        $results = $this->advancedSearchService->search($query, $userId, $types, $mode);

        return $this->asJson([
            'success' => true,
            'data' => $results,
        ]);
    }
}
