<?php

namespace app\models;

use app\models\query\ProjectQuery;
use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use app\services\CopyFormatConverter;
use common\enums\CopyType;
use app\services\ai\AiProviderInterface;
use app\services\ai\AiWorkspaceProviderInterface;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use Throwable;

/**
 * Class Project
 *
 * Represents a project entity with attributes such as name, description, and timestamps.
 * Provides relationships and validation rules for the project model.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $root_directory
 * @property string|null $allowed_file_extensions
 * @property string|null $blacklisted_directories
 * @property string $prompt_instance_copy_format
 * @property string|null $ai_options
 * @property string|null $ai_context
 * @property string|null $label
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 *
 * @property User $user
 * @property Project[] $linkedProjects
 * @property array $linkedProjectIds
 */
class Project extends ActiveRecord
{
    use TimestampTrait;

    private array $_linkedProjectIds = [];

    public function getLinkedProjectIds(): array
    {
        return $this->_linkedProjectIds;
    }

    public function setLinkedProjectIds(mixed $value): void
    {
        if (is_array($value)) {
            $this->_linkedProjectIds = $value;
        } elseif ($value === '' || $value === null) {
            $this->_linkedProjectIds = [];
        } else {
            $this->_linkedProjectIds = (array) $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'project';
    }

    public static function find(): ProjectQuery
    {
        return new ProjectQuery(static::class);
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord && $this->prompt_instance_copy_format === null) {
            $this->prompt_instance_copy_format = CopyType::MD->value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['name', 'user_id'], 'required'],
            [['user_id'], 'integer'],
            [['created_at', 'updated_at', 'deleted_at'], 'string'],
            [['description'], 'string'],
            [['root_directory'], 'string', 'max' => 1024],
            [['prompt_instance_copy_format'], 'default', 'value' => CopyType::MD->value],
            [['prompt_instance_copy_format'], 'required'],
            [['prompt_instance_copy_format'], 'string', 'max' => 32],
            [['prompt_instance_copy_format'], 'in', 'range' => CopyType::values()],
            [['ai_options'], 'safe'],
            [['ai_context'], 'string'],
            [['label'], 'string', 'max' => 64],
            [
                ['label'],
                'unique',
                'targetAttribute' => ['user_id', 'label'],
                'filter' => ['not', ['label' => null]],
                'message' => 'Label must be unique per user.',
            ],
            [
                ['root_directory'],
                'match',
                'pattern' => '~^(?:(?:[A-Za-z]:\\\\|\\\\\\\\[\w.\- $]+\\\\|/)(?:[\w.\- $]+(?:[\/\\\\][\w.\- $]+)*)?[\/\\\\]?|[\w.\- $]+(?:[\/\\\\][\w.\- $]+)*[\/\\\\]?)$~',
                'message' => 'Root directory must be a valid path.',
            ],
            [['allowed_file_extensions'], 'string', 'max' => 255],
            [['allowed_file_extensions'], 'validateAllowedFileExtensions'],
            [['blacklisted_directories'], 'string'],
            [['blacklisted_directories'], 'validateBlacklistedDirectories'],
            [['name'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            [['linkedProjectIds'], 'safe'],
            [['linkedProjectIds'], 'each', 'rule' => ['integer']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User ID',
            'name' => 'Name',
            'description' => 'Description',
            'root_directory' => 'Root Directory',
            'allowed_file_extensions' => 'Allowed File Extensions',
            'blacklisted_directories' => 'Blacklisted Directories',
            'prompt_instance_copy_format' => 'Prompt Instance Copy Format',
            'ai_options' => 'AI CLI Options',
            'ai_context' => 'AI Project Context',
            'label' => 'Label',
            'linkedProjectIds' => 'Linked Projects',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
        ];
    }

    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        $this->normalizeAllowedFileExtensionsField();

        return true;
    }

    public function validateAllowedFileExtensions(string $attribute): void
    {
        if ($this->$attribute === null || $this->$attribute === '') {
            return;
        }

        foreach (explode(',', $this->$attribute) as $extension) {
            $normalized = ltrim(strtolower(trim($extension)), '.');
            if ($normalized === '') {
                $this->addError($attribute, 'Provide at least one file extension or leave this field blank.');
                return;
            }

            if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $normalized)) {
                $this->addError(
                    $attribute,
                    'File extensions may only contain letters, numbers, dots, underscores or hyphens.'
                );
                return;
            }
        }
    }

    public function getAllowedFileExtensions(): array
    {
        if (empty($this->allowed_file_extensions)) {
            return [];
        }

        $parts = explode(',', $this->allowed_file_extensions);
        $normalized = array_map(
            static fn(string $extension): string => ltrim(strtolower(trim($extension)), '.'),
            $parts
        );

        return array_values(array_unique(array_filter(
            $normalized,
            static fn(string $value): bool => $value !== ''
        )));
    }

    public function isFileExtensionAllowed(?string $extension): bool
    {
        $whitelist = $this->getAllowedFileExtensions();
        if ($whitelist === []) {
            return true;
        }

        $normalized = ltrim(strtolower((string) $extension), '.');
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $whitelist, true);
    }

    private function parseBlacklistDirectories(string $input): array
    {
        $parts = [];
        $current = '';
        $inBrackets = false;

        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];

            if ($char === '[') {
                $inBrackets = true;
                $current .= $char;
            } elseif ($char === ']') {
                $inBrackets = false;
                $current .= $char;
            } elseif ($char === ',' && !$inBrackets) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    public function getBlacklistedDirectories(): array
    {
        if (empty($this->blacklisted_directories)) {
            return [];
        }

        $parts = $this->parseBlacklistDirectories($this->blacklisted_directories);
        $result = [];

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('~^(.+?)/?(\[([^]]+)])$~', $trimmed, $matches)) {
                $basePath = trim(str_replace('\\', '/', $matches[1]), " 	\n\r\0\x0B/");
                $exceptionsStr = $matches[3];
                $exceptionParts = array_map('trim', explode(',', $exceptionsStr));
                $filtered = array_filter($exceptionParts, static fn(string $v): bool => $v !== '');
                $exceptions = array_values(array_unique($filtered));

                if ($basePath !== '') {
                    $result[] = [
                        'path' => $basePath,
                        'exceptions' => $exceptions,
                    ];
                }
            } else {
                $normalized = trim(str_replace('\\', '/', $trimmed), " 	\n\r\0\x0B/");
                if ($normalized !== '') {
                    $result[] = [
                        'path' => $normalized,
                        'exceptions' => [],
                    ];
                }
            }
        }

        $uniqueKeys = [];
        $uniqueResult = [];
        foreach ($result as $rule) {
            $key = $rule['path'];
            if (!isset($uniqueKeys[$key])) {
                $uniqueKeys[$key] = true;
                $uniqueResult[] = $rule;
            }
        }

        return $uniqueResult;
    }

    public function getPromptInstanceCopyFormat(): string
    {
        return $this->getPromptInstanceCopyFormatEnum()->value;
    }

    public static function getPromptInstanceCopyFormatOptions(): array
    {
        return CopyType::labels();
    }

    public function getAiOptions(): array
    {
        if (empty($this->ai_options)) {
            return [];
        }
        if (is_string($this->ai_options)) {
            return json_decode($this->ai_options, true) ?? [];
        }
        return $this->ai_options;
    }

    public function setAiOptions(array|string|null $value): void
    {
        if (is_array($value)) {
            $value = array_filter($value, fn($v) => $v !== null && $v !== '');
            // Decode JSON string values submitted by hidden form fields (e.g. commandBlacklist, commandGroups)
            foreach ($value as $k => $v) {
                if (is_string($v) && ($v[0] === '[' || $v[0] === '{')) {
                    $decoded = json_decode($v, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value[$k] = $decoded;
                    }
                }
            }
            $this->ai_options = empty($value) ? null : json_encode($value);
        } else {
            $this->ai_options = $value;
        }
    }

    public function getAiOption(string $key, mixed $default = null): mixed
    {
        return $this->getAiOptions()[$key] ?? $default;
    }

    public function getAiCommandBlacklist(): array
    {
        $raw = $this->getAiOption('commandBlacklist');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (is_array($raw)) {
            return array_values(array_filter($raw, static fn($v): bool => is_string($v) && $v !== ''));
        }
        return [];
    }

    public function getAiCommandGroups(): array
    {
        $raw = $this->getAiOption('commandGroups');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (is_array($raw)) {
            return array_filter(
                $raw,
                static fn($v, $k): bool => is_string($k) && $k !== '' && is_array($v),
                ARRAY_FILTER_USE_BOTH
            );
        }
        return [];
    }

    public function getAiContext(): ?string
    {
        return $this->ai_context;
    }

    public function setAiContext(?string $value): void
    {
        $this->ai_context = $value === '' ? null : $value;
    }

    public function hasAiContext(): bool
    {
        return $this->ai_context !== null && trim($this->ai_context) !== '';
    }

    /**
     * Returns ai_context converted to markdown.
     *
     * Handles both Delta JSON (new format) and plain text (legacy).
     */
    public function getAiContextAsMarkdown(): string
    {
        if (!$this->hasAiContext()) {
            return '';
        }

        $raw = $this->ai_context;
        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['ops'])) {
            /** @var CopyFormatConverter $converter */
            $converter = Yii::createObject(CopyFormatConverter::class);
            return $converter->convertFromQuillDelta($raw, CopyType::MD);
        }

        // Legacy plain-text content
        return $raw;
    }

    public function getPromptInstanceCopyFormatEnum(): CopyType
    {
        return CopyType::tryFrom((string) $this->prompt_instance_copy_format) ?? CopyType::MD;
    }

    private function normalizeAllowedFileExtensionsField(): void
    {
        $extensions = $this->getAllowedFileExtensions();
        $this->allowed_file_extensions = $extensions === [] ? null : implode(',', $extensions);
    }

    private function normalizeBlacklistedDirectoriesField(): void
    {
        $rules = $this->getBlacklistedDirectories();
        if ($rules === []) {
            $this->blacklisted_directories = null;
            return;
        }

        $normalized = [];
        foreach ($rules as $rule) {
            if ($rule['exceptions'] === []) {
                $normalized[] = $rule['path'];
            } else {
                $normalized[] = $rule['path'] . '/[' . implode(',', $rule['exceptions']) . ']';
            }
        }

        $this->blacklisted_directories = implode(',', $normalized);
    }

    public function validateBlacklistedDirectories(string $attribute): void
    {
        if ($this->$attribute === null || $this->$attribute === '') {
            return;
        }

        $parts = $this->parseBlacklistDirectories((string) $this->$attribute);

        foreach ($parts as $directory) {
            $trimmed = trim($directory);
            if ($trimmed === '') {
                $this->addError($attribute, 'Provide at least one directory or leave this field blank.');
                return;
            }

            if (preg_match('~^(.+?)/?(\[([^]]+)])$~', $trimmed, $matches)) {
                $basePath = trim(str_replace('\\', '/', $matches[1]), " 	\n\r\0\x0B/");
                $exceptionsStr = $matches[3];

                if ($basePath === '') {
                    $this->addError($attribute, 'Blacklisted directory path cannot be empty.');
                    return;
                }

                if (str_contains($basePath, '..')) {
                    $this->addError($attribute, 'Blacklisted directories must be relative to the project root.');
                    return;
                }

                if (!preg_match('~^[\w. -]+(?:/[\w. -]+)*$~', $basePath)) {
                    $this->addError(
                        $attribute,
                        'Directory names may include letters, numbers, dots, underscores, hyphens, spaces, and slashes only.'
                    );
                    return;
                }

                $rawExceptionParts = explode(',', $exceptionsStr);
                $exceptionParts = array_map('trim', $rawExceptionParts);

                foreach ($rawExceptionParts as $i => $rawException) {
                    $exception = $exceptionParts[$i];

                    if ($exception === '') {
                        $this->addError($attribute, 'Whitelist exceptions cannot be empty.');
                        return;
                    }

                    if (str_contains($exception, '/') || str_contains($exception, '\\')) {
                        $this->addError($attribute, 'Whitelist exceptions must be direct subdirectories (no slashes).');
                        return;
                    }

                    if (!preg_match('~^[\w. -]+$~', $exception)) {
                        $this->addError(
                            $attribute,
                            'Exception names may include letters, numbers, dots, underscores, hyphens, and spaces only.'
                        );
                        return;
                    }
                }
            } else {
                $normalized = trim(str_replace('\\', '/', $trimmed), " 	\n\r\0\x0B/");

                if ($normalized === '') {
                    $this->addError($attribute, 'Provide at least one directory or leave this field blank.');
                    return;
                }

                if (str_contains($normalized, '..')) {
                    $this->addError($attribute, 'Blacklisted directories must be relative to the project root.');
                    return;
                }

                if (!preg_match('~^[\w. -]+(?:/[\w. -]+)*$~', $normalized)) {
                    $this->addError(
                        $attribute,
                        'Directory names may include letters, numbers, dots, underscores, hyphens, spaces, and slashes only.'
                    );
                    return;
                }
            }
        }
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getLinkedProjects(): ActiveQuery
    {
        return $this->hasMany(Project::class, ['id' => 'linked_project_id'])
            ->viaTable('project_linked_project', ['project_id' => 'id']);
    }

    public static function findAvailableForLinking(?int $excludeProjectId, int $userId): ProjectQuery
    {
        return static::find()->availableForLinking($excludeProjectId, $userId);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->label !== null) {
            $this->label = trim($this->label) === '' ? null : trim($this->label);
        }

        $this->normalizeAllowedFileExtensionsField();
        $this->normalizeBlacklistedDirectoriesField();
        $this->handleTimestamps($insert);

        return true;
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        // Fields that affect AI workspace configuration
        $relevantFields = [
            'ai_context',
            'ai_options',
            'name',
            'allowed_file_extensions',
            'blacklisted_directories',
        ];

        $shouldSync = $insert || array_intersect_key($changedAttributes, array_flip($relevantFields)) !== [];

        if ($shouldSync) {
            try {
                $provider = Yii::$container->get(AiProviderInterface::class);
                if ($provider instanceof AiWorkspaceProviderInterface) {
                    $provider->syncConfig($this);
                }
            } catch (Throwable $e) {
                Yii::error("Failed to sync AI workspace for project {$this->id}: {$e->getMessage()}", 'application');
            }
        }
    }

    public function afterDelete(): void
    {
        parent::afterDelete();

        try {
            $provider = Yii::$container->get(AiProviderInterface::class);
            if ($provider instanceof AiWorkspaceProviderInterface) {
                $provider->deleteWorkspace($this);
            }
        } catch (Throwable $e) {
            Yii::error("Failed to delete AI workspace for project {$this->id}: {$e->getMessage()}", 'application');
        }
    }
}
