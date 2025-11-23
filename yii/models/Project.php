<?php

namespace app\models;

use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use common\enums\CopyType;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

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
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $deleted_at
 *
 * @property User $user
 */
class Project extends ActiveRecord
{
    use TimestampTrait;

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return 'project';
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
            [['user_id', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['description'], 'string'],
            [['root_directory'], 'string', 'max' => 1024],
            [['prompt_instance_copy_format'], 'default', 'value' => CopyType::MD->value],
            [['prompt_instance_copy_format'], 'required'],
            [['prompt_instance_copy_format'], 'string', 'max' => 32],
            [['prompt_instance_copy_format'], 'in', 'range' => CopyType::values()],
            [
                ['root_directory'],
                'match',
                'pattern' => '~^(?:(?:[A-Za-z]:\\\\|\\\\\\\\[\w.\- \$]+\\\\|/)(?:[\w.\- \$]+(?:[\/\\\\][\w.\- \$]+)*)?[\/\\\\]?|[\w.\- \$]+(?:[\/\\\\][\w.\- \$]+)*[\/\\\\]?)$~',
                'message' => 'Root directory must be a valid path.',
            ],
            [['allowed_file_extensions'], 'string', 'max' => 255],
            [['allowed_file_extensions'], 'validateAllowedFileExtensions'],
            [['blacklisted_directories'], 'string'],
            [['blacklisted_directories'], 'validateBlacklistedDirectories'],
            [['name'], 'string', 'max' => 255],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
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
        $this->normalizeBlacklistedDirectoriesField();

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

        $normalized = ltrim(strtolower((string)$extension), '.');
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $whitelist, true);
    }

    public function getBlacklistedDirectories(): array
    {
        if (empty($this->blacklisted_directories)) {
            return [];
        }

        $parts = explode(',', $this->blacklisted_directories);
        $normalized = array_map(
            static fn(string $directory): string => trim(str_replace('\\', '/', $directory), " \t\n\r\0\x0B/"),
            $parts
        );

        $filtered = array_filter(
            $normalized,
            static fn(string $value): bool => $value !== ''
        );

        return array_values(array_unique($filtered));
    }

    public function getPromptInstanceCopyFormat(): string
    {
        return $this->getPromptInstanceCopyFormatEnum()->value;
    }

    public static function getPromptInstanceCopyFormatOptions(): array
    {
        return CopyType::labels();
    }

    public function getPromptInstanceCopyFormatEnum(): CopyType
    {
        return CopyType::tryFrom((string)$this->prompt_instance_copy_format) ?? CopyType::MD;
    }

    private function normalizeAllowedFileExtensionsField(): void
    {
        $extensions = $this->getAllowedFileExtensions();
        $this->allowed_file_extensions = $extensions === [] ? null : implode(',', $extensions);
    }

    private function normalizeBlacklistedDirectoriesField(): void
    {
        $directories = $this->getBlacklistedDirectories();
        $this->blacklisted_directories = $directories === [] ? null : implode(',', $directories);
    }

    public function validateBlacklistedDirectories(string $attribute): void
    {
        if ($this->$attribute === null || $this->$attribute === '') {
            return;
        }

        foreach (explode(',', (string)$this->$attribute) as $directory) {
            $normalized = trim(str_replace('\\', '/', $directory), " \t\n\r\0\x0B/");

            if ($normalized === '') {
                $this->addError($attribute, 'Provide at least one directory or leave this field blank.');
                return;
            }

            if (str_contains($normalized, '..')) {
                $this->addError($attribute, 'Blacklisted directories must be relative to the project root.');
                return;
            }

            if (!preg_match('~^[\\w.\\- ]+(?:/[\\w.\\- ]+)*$~', $normalized)) {
                $this->addError(
                    $attribute,
                    'Directory names may include letters, numbers, dots, underscores, hyphens, spaces, and slashes only.'
                );
                return;
            }
        }
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->normalizeAllowedFileExtensionsField();
        $this->normalizeBlacklistedDirectoriesField();
        $this->handleTimestamps($insert);

        return true;
    }
}
