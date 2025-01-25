<?php

namespace app\components;

use app\models\Project;
use app\services\UserPreferenceService;
use InvalidArgumentException;
use Throwable;
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
        Session               $session,
        UserPreferenceService $userPreference,
        WebUser               $webUser,
                              $config = []
    )
    {
        $this->session = $session;
        $this->userPreference = $userPreference;
        $this->webUser = $webUser;
        parent::__construct($config);
    }

    /**
     * Retrieve the current project from session or user preferences, and cache it in session.
     *
     * @return array|ActiveRecord|null
     */
    public function getCurrentProject(): array|ActiveRecord|null
    {
        $projectId = $this->getSessionProjectId();

        if (!$projectId && $this->isUserLoggedIn()) {
            $projectId = $this->getUserPreferenceProjectId();
            $this->setSessionProjectId($projectId);
        }

        return $projectId ? $this->getValidatedProject($projectId) : null;
    }

    /**
     * Set the current project in session and persist it to user preferences.
     *
     * @throws StaleObjectException
     * @throws Throwable
     */
    public function setCurrentProject(int $projectId): void
    {
        // Handle "no project" scenario
        if ($projectId === 0) {
            $this->clearCurrentProject();
            if ($this->isUserLoggedIn()) {
                $this->removeUserPreferenceProjectId();
            }
            return;
        }

        // Validate the project belongs to the current user
        if (!$this->isProjectValidForUser($projectId)) {
            throw new InvalidArgumentException('Invalid project ID for the current user.');
        }

        // Cache in session
        $this->setSessionProjectId($projectId);

        // Persist to user preferences
        if ($this->isUserLoggedIn()) {
            $this->saveProjectIdToUserPreferences($projectId);
        }
    }

    /**
     * Clear the current project from session (useful on logout).
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
        return is_numeric($val) ? (int)$val : null;
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
        return is_numeric($projectId) ? (int)$projectId : null;
    }

    /**
     * @throws Exception
     */
    private function saveProjectIdToUserPreferences(int $projectId): void
    {
        $userId = $this->webUser->id;
        if ($userId) {
            $this->userPreference->setValue($userId, self::PREF_KEY, (string)$projectId);
        }
    }

    private function isProjectValidForUser(int $projectId): bool
    {
        return Project::find()
            ->where(['id' => $projectId, 'user_id' => $this->webUser->id])
            ->exists();
    }

    private function getValidatedProject(int $projectId): array|ActiveRecord
    {
        return Project::find()
            ->where(['id' => $projectId, 'user_id' => $this->webUser->id])
            ->one();
    }
}
