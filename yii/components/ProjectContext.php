<?php

namespace app\components;

use app\models\Project;
use app\services\UserPreferenceService;
use InvalidArgumentException;
use Throwable;
use Yii;
use yii\base\BaseObject;
use yii\db\ActiveRecord;
use yii\db\Exception;
use yii\db\StaleObjectException;
use yii\web\Session;
use yii\web\User as WebUser;

/**
 * Manages the "current project" context in session and user preferences.
 */
class ProjectContext extends BaseObject
{
    public const ALL_PROJECTS_ID = -1;
    public const NO_PROJECT_ID = 0;
    private const PREF_KEY = 'default_project_id';
    private const SESSION_KEY = 'currentProjectId';

    private Session $session;
    private UserPreferenceService $userPreference;
    private WebUser $webUser;

    /**
     * You can inject the Session, the custom UserPreferenceService,
     * and the Yii WebUser (for user checks) via the constructor.
     */
    public function __construct(
        Session $session,
        UserPreferenceService $userPreference,
        WebUser $webUser,
        $config = []
    ) {
        $this->session = $session;
        $this->userPreference = $userPreference;
        $this->webUser = $webUser;
        parent::__construct($config);
    }

    /**
     * Retrieve the current project from session or user preferences and cache it in session.
     *
     * @return array|ActiveRecord|null
     */
    public function getCurrentProject(): array|ActiveRecord|null
    {
        $projectId = $this->getSessionProjectId();

        if ($projectId === self::ALL_PROJECTS_ID || $projectId === self::NO_PROJECT_ID) {
            return null;
        }

        if ($projectId !== null) {
            $project = $this->getValidatedProject($projectId);
            if ($project !== null) {
                return $project;
            }
            $this->clearCurrentProject();
        }

        if (!$this->isUserLoggedIn()) {
            return null;
        }

        $projectId = $this->getUserPreferenceProjectId();
        if ($projectId === null) {
            return null;
        }

        if ($projectId === self::ALL_PROJECTS_ID || $projectId === self::NO_PROJECT_ID) {
            $this->setSessionProjectId($projectId);
            return null;
        }

        $project = $this->getValidatedProject($projectId);
        if ($project !== null) {
            $this->setSessionProjectId($projectId);
            return $project;
        }

        try {
            $this->removeUserPreferenceProjectId();
        } catch (Throwable $e) {
            Yii::warning($e->getMessage(), __METHOD__);
        }

        return null;
    }

    /**
     * Set the current project in session and persist it to user preferences.
     *
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function setCurrentProject(int $projectId): void
    {
        if ($projectId === self::NO_PROJECT_ID) {
            $this->setSessionProjectId($projectId);
            if ($this->isUserLoggedIn()) {
                $this->saveProjectIdToUserPreferences($projectId);
            }
            return;
        }

        if ($projectId === self::ALL_PROJECTS_ID) {
            $this->setSessionProjectId($projectId);
            if ($this->isUserLoggedIn()) {
                $this->saveProjectIdToUserPreferences($projectId);
            }
            return;
        }

        if (!$this->isProjectValidForUser($projectId)) {
            throw new InvalidArgumentException('Invalid project ID for the current user.');
        }

        $this->setSessionProjectId($projectId);

        if ($this->isUserLoggedIn()) {
            $this->saveProjectIdToUserPreferences($projectId);
        }
    }

    /**
     * Check if the current context is "All Projects".
     */
    public function isAllProjectsContext(): bool
    {
        return $this->getSessionProjectId() === self::ALL_PROJECTS_ID;
    }

    /**
     * Check if the current context is "No Project".
     */
    public function isNoProjectContext(): bool
    {
        return $this->getSessionProjectId() === self::NO_PROJECT_ID;
    }

    /**
     * Clear the current project from the session (useful on logout).
     */
    public function clearCurrentProject(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    /**
     * -----------------------------------
     * Private/Protected helper methods
     * -----------------------------------
     */

    /**
     * @throws Throwable
     * @throws StaleObjectException
     */
    private function removeUserPreferenceProjectId(): void
    {
        $this->userPreference->removeValue($this->webUser->id, self::PREF_KEY);
    }

    private function getSessionProjectId(): ?int
    {
        $val = $this->session->get(self::SESSION_KEY);
        return is_numeric($val) ? (int) $val : null;
    }

    private function setSessionProjectId(?int $projectId): void
    {
        if ($projectId !== null) {
            $this->session->set(self::SESSION_KEY, $projectId);
        }
    }

    private function isUserLoggedIn(): bool
    {
        return !$this->webUser->isGuest;
    }

    private function getUserPreferenceProjectId(): ?int
    {
        $userId = $this->webUser->id;
        if (!$userId) {
            return null;
        }
        $projectId = $this->userPreference->getValue($userId, self::PREF_KEY);
        return is_numeric($projectId) ? (int) $projectId : null;
    }

    /**
     * @throws Exception
     */
    private function saveProjectIdToUserPreferences(int $projectId): void
    {
        $userId = $this->webUser->id;
        if ($userId) {
            $this->userPreference->setValue($userId, self::PREF_KEY, (string) $projectId);
        }
    }

    private function isProjectValidForUser(int $projectId): bool
    {
        return Project::find()
            ->where(['id' => $projectId, 'user_id' => $this->webUser->id])
            ->exists();
    }

    private function getValidatedProject(int $projectId): array|ActiveRecord|null
    {
        return Project::find()
            ->where(['id' => $projectId, 'user_id' => $this->webUser->id])
            ->one();
    }
}
