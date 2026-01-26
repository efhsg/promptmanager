<?php

namespace app\models;

use yii\base\Model;

class YouTubeImportForm extends Model
{
    public ?string $videoId = null;
    public ?int $project_id = null;

    public function rules(): array
    {
        return [
            [['videoId'], 'required', 'message' => 'Please enter a YouTube video ID or URL.'],
            ['videoId', 'string', 'max' => 255],
            ['videoId', 'trim'],
            ['project_id', 'integer'],
            ['project_id', 'default', 'value' => null],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'videoId' => 'YouTube Video ID or URL',
            'project_id' => 'Project',
        ];
    }
}
