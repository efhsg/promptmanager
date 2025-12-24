<?php

namespace app\models;

use yii\base\Model;
use yii\web\UploadedFile;

class MarkdownImportForm extends Model
{
    public ?int $project_id = null;
    public ?string $name = null;
    public ?UploadedFile $mdFile = null;

    public function rules(): array
    {
        return [
            [['project_id'], 'required'],
            ['project_id', 'integer'],
            ['name', 'string', 'max' => 255],
            ['name', 'trim'],
            ['name', 'default', 'value' => null],
            [
                'mdFile',
                'file',
                'extensions' => ['md', 'markdown', 'txt'],
                'maxSize' => 1024 * 1024,
                'checkExtensionByMimeType' => false,
                'skipOnEmpty' => false,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'project_id' => 'Project',
            'name' => 'Template Name',
            'mdFile' => 'Markdown File',
        ];
    }
}
