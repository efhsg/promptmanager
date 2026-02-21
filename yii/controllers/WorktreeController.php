<?php

namespace app\controllers;

use app\models\Project;
use app\models\ProjectWorktree;
use app\services\EntityPermissionService;
use app\services\worktree\WorktreeService;
use common\enums\LogCategory;
use common\enums\WorktreePurpose;
use RuntimeException;
use Throwable;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * AJAX controller for worktree management operations.
 */
class WorktreeController extends Controller
{
    private readonly WorktreeService $worktreeService;
    private readonly EntityPermissionService $permissionService;

    public function __construct(
        $id,
        $module,
        WorktreeService $worktreeService,
        EntityPermissionService $permissionService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->worktreeService = $worktreeService;
        $this->permissionService = $permissionService;
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'status' => ['GET'],
                    'create' => ['POST'],
                    'sync' => ['POST'],
                    'remove' => ['POST'],
                    'recreate' => ['POST'],
                    'cleanup' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['status', 'create'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            $projectId = (int) (Yii::$app->request->get('projectId')
                                ?: Yii::$app->request->post('projectId'));
                            return $this->permissionService->checkPermission(
                                'viewProject',
                                $this->findProject($projectId)
                            );
                        },
                    ],
                    [
                        'actions' => ['sync', 'remove', 'recreate', 'cleanup'],
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => function () {
                            $worktree = $this->findWorktree(
                                (int) Yii::$app->request->post('worktreeId')
                            );
                            return $this->permissionService->checkPermission(
                                'viewProject',
                                $worktree->project
                            );
                        },
                    ],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        Yii::$app->response->format = Response::FORMAT_JSON;

        return true;
    }

    public function actionStatus(int $projectId): array
    {
        try {
            $project = $this->findProject($projectId);
            $isGitRepo = $this->worktreeService->isGitRepo($project);

            if (!$isGitRepo) {
                return [
                    'success' => true,
                    'message' => 'Root directory is not a git repository.',
                    'data' => ['isGitRepo' => false, 'worktrees' => []],
                ];
            }

            $statuses = $this->worktreeService->getStatusForProject($project);
            $data = array_map(fn($s) => [
                'id' => $s->worktreeId,
                'directoryExists' => $s->directoryExists,
                'hostPath' => $s->hostPath,
                'branch' => $s->branch,
                'sourceBranch' => $s->sourceBranch,
                'purpose' => $s->purpose->value,
                'purposeLabel' => $s->purpose->label(),
                'purposeBadgeClass' => $s->purpose->badgeClass(),
                'behindSourceCount' => $s->behindSourceCount,
            ], $statuses);

            return [
                'success' => true,
                'message' => '',
                'data' => ['isGitRepo' => true, 'worktrees' => $data],
            ];
        } catch (Throwable $e) {
            Yii::error("Worktree status failed: {$e->getMessage()}", LogCategory::WORKTREE->value);

            return ['success' => false, 'message' => 'Failed to load worktree status.', 'data' => null];
        }
    }

    public function actionCreate(): array
    {
        try {
            $request = Yii::$app->request;
            $projectId = (int) $request->post('projectId');
            $branch = (string) $request->post('branch', '');
            $suffix = (string) $request->post('suffix', '');
            $purposeValue = (string) $request->post('purpose', '');
            $sourceBranch = (string) ($request->post('sourceBranch') ?: 'main');

            $purpose = WorktreePurpose::tryFrom($purposeValue);
            if ($purpose === null) {
                return ['success' => false, 'message' => 'Invalid purpose value.', 'data' => null];
            }

            $project = $this->findProject($projectId);
            $worktree = $this->worktreeService->create($project, $branch, $suffix, $purpose, $sourceBranch);

            return [
                'success' => true,
                'message' => 'Worktree created.',
                'data' => ['id' => $worktree->id],
            ];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        } catch (Throwable $e) {
            Yii::error("Worktree create failed: {$e->getMessage()}", LogCategory::WORKTREE->value);

            return ['success' => false, 'message' => 'Failed to create worktree. Branch or path may already exist.', 'data' => null];
        }
    }

    public function actionSync(): array
    {
        try {
            $worktreeId = (int) Yii::$app->request->post('worktreeId');
            $worktree = $this->findWorktree($worktreeId);
            $result = $this->worktreeService->sync($worktree);

            if (!$result->success) {
                return ['success' => false, 'message' => $result->errorMessage, 'data' => null];
            }

            return [
                'success' => true,
                'message' => "Synced: {$result->commitsMerged} commits merged.",
                'data' => ['commitsMerged' => $result->commitsMerged],
            ];
        } catch (Throwable $e) {
            Yii::error("Worktree sync failed: {$e->getMessage()}", LogCategory::WORKTREE->value);

            return ['success' => false, 'message' => 'Failed to sync worktree.', 'data' => null];
        }
    }

    public function actionRemove(): array
    {
        try {
            $worktreeId = (int) Yii::$app->request->post('worktreeId');
            $worktree = $this->findWorktree($worktreeId);
            $this->worktreeService->remove($worktree);

            return ['success' => true, 'message' => 'Worktree removed.', 'data' => null];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        } catch (Throwable $e) {
            Yii::error("Worktree remove failed: {$e->getMessage()}", LogCategory::WORKTREE->value);

            return ['success' => false, 'message' => 'Failed to remove worktree. Check if it is locked.', 'data' => null];
        }
    }

    public function actionRecreate(): array
    {
        try {
            $worktreeId = (int) Yii::$app->request->post('worktreeId');
            $worktree = $this->findWorktree($worktreeId);
            $this->worktreeService->recreate($worktree);

            return ['success' => true, 'message' => 'Worktree re-created.', 'data' => null];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        } catch (Throwable $e) {
            Yii::error("Worktree recreate failed: {$e->getMessage()}", LogCategory::WORKTREE->value);

            return ['success' => false, 'message' => 'Failed to re-create worktree.', 'data' => null];
        }
    }

    public function actionCleanup(): array
    {
        try {
            $worktreeId = (int) Yii::$app->request->post('worktreeId');
            $worktree = $this->findWorktree($worktreeId);
            $this->worktreeService->cleanup($worktree);

            return ['success' => true, 'message' => 'Record cleaned up.', 'data' => null];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        } catch (Throwable $e) {
            Yii::error("Worktree cleanup failed: {$e->getMessage()}", LogCategory::WORKTREE->value);

            return ['success' => false, 'message' => 'Failed to clean up record.', 'data' => null];
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findProject(int $id): Project
    {
        return Project::find()->where([
            'id' => $id,
            'user_id' => Yii::$app->user->id,
        ])->one() ?? throw new NotFoundHttpException('The requested Project does not exist or is not yours.');
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findWorktree(int $id): ProjectWorktree
    {
        return ProjectWorktree::find()
            ->forUser((int) Yii::$app->user->id)
            ->andWhere(['{{%project_worktree}}.id' => $id])
            ->one() ?? throw new NotFoundHttpException('The requested Worktree does not exist or is not yours.');
    }
}
