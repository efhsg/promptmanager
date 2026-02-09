# YouTube Extractor Workflow — Gedetailleerd Implementatieplan

Dit document beschrijft de volledige implementatie van de YouTube Extractor workflow, inclusief alle code, bestandsstructuur en stap-voor-stap instructies. Het is gebaseerd op de goedgekeurde `spec.md` en het technische `plan.md`.

---

## Inhoudsopgave

1. [Overzicht](#1-overzicht)
2. [Migraties](#2-migraties)
3. [Enums](#3-enums)
4. [Modellen & Query Classes](#4-modellen--query-classes)
5. [RBAC Rules](#5-rbac-rules)
6. [Services](#6-services)
7. [Step Handlers](#7-step-handlers)
8. [Controller](#8-controller)
9. [Views](#9-views)
10. [Configuratie-wijzigingen](#10-configuratie-wijzigingen)
11. [Seed Data](#11-seed-data)
12. [Tests](#12-tests)
13. [Implementatievolgorde](#13-implementatievolgorde)

---

## 1. Overzicht

### YouTube Extractor recept

| Stap | Type | Label | Approval | Config |
|---|---|---|---|---|
| 1 | `youtube_transcript` | Transcript ophalen | `true` | `{}` |
| 2 | `save_scratch_pad` | Opslaan als ScratchPad | `false` | `{ "content_source": "step:1", "name_source": "auto" }` |
| 3 | `ai_transform` | Vertalen of samenvatten | `true` | `{ "content_source": "step:1", "instruction_source": "user" }` |
| 4 | `save_scratch_pad` | Resultaat opslaan | `false` | `{ "content_source": "step:3", "name_source": "auto" }` |

### Bestanden overzicht

| # | Bestand | Type | Nieuw/Wijzig |
|---|---|---|---|
| M1 | `yii/migrations/m260208_000001_create_workflow_recipe_table.php` | Migratie | Nieuw |
| M2 | `yii/migrations/m260208_000002_create_workflow_step_table.php` | Migratie | Nieuw |
| M3 | `yii/migrations/m260208_000003_create_workflow_run_table.php` | Migratie | Nieuw |
| M4 | `yii/migrations/m260208_000004_create_workflow_step_result_table.php` | Migratie | Nieuw |
| M5 | `yii/migrations/m260208_000005_seed_system_workflow_recipes.php` | Migratie | Nieuw |
| E1 | `yii/common/enums/WorkflowRecipeType.php` | Enum | Nieuw |
| E2 | `yii/common/enums/WorkflowStepType.php` | Enum | Nieuw |
| MD1 | `yii/models/WorkflowRecipe.php` | Model | Nieuw |
| MD2 | `yii/models/query/WorkflowRecipeQuery.php` | Query | Nieuw |
| MD3 | `yii/models/WorkflowStep.php` | Model | Nieuw |
| MD4 | `yii/models/WorkflowRun.php` | Model | Nieuw |
| MD5 | `yii/models/query/WorkflowRunQuery.php` | Query | Nieuw |
| MD6 | `yii/models/WorkflowStepResult.php` | Model | Nieuw |
| R1 | `yii/rbac/WorkflowRecipeOwnerRule.php` | RBAC | Nieuw |
| R2 | `yii/rbac/WorkflowRunOwnerRule.php` | RBAC | Nieuw |
| H0 | `yii/services/workflowsteps/StepHandlerInterface.php` | Interface | Nieuw |
| H1 | `yii/services/workflowsteps/YoutubeTranscriptHandler.php` | Handler | Nieuw |
| H2 | `yii/services/workflowsteps/AiTransformHandler.php` | Handler | Nieuw |
| H4 | `yii/services/workflowsteps/SaveScratchPadHandler.php` | Handler | Nieuw |
| H5 | `yii/services/workflowsteps/UserInputHandler.php` | Handler | Nieuw |
| S1 | `yii/services/WorkflowRecipeService.php` | Service | Nieuw |
| S2 | `yii/services/WorkflowRunService.php` | Service | Nieuw |
| S3 | `yii/services/WorkflowStepExecutor.php` | Service | Nieuw |
| C1 | `yii/controllers/WorkflowController.php` | Controller | Nieuw |
| V1 | `yii/views/workflow/index.php` | View | Nieuw |
| V2 | `yii/views/workflow/run.php` | View | Nieuw |
| V3 | `yii/views/workflow/_step_youtube_transcript.php` | Partial | Nieuw |
| V4 | `yii/views/workflow/_step_ai_transform.php` | Partial | Nieuw |
| V5 | `yii/views/workflow/_step_save_result.php` | Partial | Nieuw |
| V6 | `yii/views/workflow/_start_modal.php` | Partial | Nieuw |
| W1 | `yii/config/rbac.php` | Config | Wijzig |
| W2 | `yii/views/layouts/main.php` | Layout | Wijzig |
| W3 | `yii/config/main.php` | Config | Wijzig |
| W4 | `yii/services/EntityPermissionService.php` | Service | Wijzig |
| T1 | `yii/tests/unit/models/WorkflowRecipeTest.php` | Test | Nieuw |
| T2 | `yii/tests/unit/models/WorkflowRunTest.php` | Test | Nieuw |
| T3 | `yii/tests/unit/services/WorkflowRunServiceTest.php` | Test | Nieuw |
| T4 | `yii/tests/unit/services/workflowsteps/YoutubeTranscriptHandlerTest.php` | Test | Nieuw |

---

## 2. Migraties

### M1: `yii/migrations/m260208_000001_create_workflow_recipe_table.php`

```php
<?php

namespace app\migrations;

use yii\db\Migration;

class m260208_000001_create_workflow_recipe_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%workflow_recipe}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->null(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'type' => $this->string(64)->notNull(),
            'is_system' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->string(19)->notNull(),
            'updated_at' => $this->string(19)->notNull(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addForeignKey(
            'fk_workflow_recipe_user',
            '{{%workflow_recipe}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('idx_workflow_recipe_user', '{{%workflow_recipe}}', 'user_id');
        $this->createIndex('idx_workflow_recipe_type', '{{%workflow_recipe}}', 'type');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%workflow_recipe}}');
    }
}
```

### M2: `yii/migrations/m260208_000002_create_workflow_step_table.php`

```php
<?php

namespace app\migrations;

use yii\db\Migration;

class m260208_000002_create_workflow_step_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%workflow_step}}', [
            'id' => $this->primaryKey(),
            'recipe_id' => $this->integer()->notNull(),
            'order' => $this->integer()->notNull()->defaultValue(0),
            'step_type' => $this->string(64)->notNull(),
            'label' => $this->string(255)->notNull(),
            'config' => $this->text()->null(),
            'requires_approval' => $this->boolean()->notNull()->defaultValue(true),
            'is_skippable' => $this->boolean()->notNull()->defaultValue(false),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addForeignKey(
            'fk_workflow_step_recipe',
            '{{%workflow_step}}',
            'recipe_id',
            '{{%workflow_recipe}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('idx_workflow_step_recipe_order', '{{%workflow_step}}', ['recipe_id', 'order']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%workflow_step}}');
    }
}
```

### M3: `yii/migrations/m260208_000003_create_workflow_run_table.php`

```php
<?php

namespace app\migrations;

use yii\db\Migration;

class m260208_000003_create_workflow_run_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%workflow_run}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'recipe_id' => $this->integer()->notNull(),
            'project_id' => $this->integer()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('pending'),
            'current_step' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->string(19)->notNull(),
            'updated_at' => $this->string(19)->notNull(),
            'completed_at' => $this->string(19)->null(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addForeignKey(
            'fk_workflow_run_user',
            '{{%workflow_run}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_workflow_run_recipe',
            '{{%workflow_run}}',
            'recipe_id',
            '{{%workflow_recipe}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_workflow_run_project',
            '{{%workflow_run}}',
            'project_id',
            '{{%project}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createIndex('idx_workflow_run_user_status', '{{%workflow_run}}', ['user_id', 'status']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%workflow_run}}');
    }
}
```

### M4: `yii/migrations/m260208_000004_create_workflow_step_result_table.php`

```php
<?php

namespace app\migrations;

use yii\db\Migration;

class m260208_000004_create_workflow_step_result_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%workflow_step_result}}', [
            'id' => $this->primaryKey(),
            'run_id' => $this->integer()->notNull(),
            'step_id' => $this->integer()->notNull(),
            'step_order' => $this->integer()->notNull(),
            'status' => $this->string(32)->notNull()->defaultValue('pending'),
            'input_data' => 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
            'output_data' => 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL',
            'error_message' => $this->text()->null(),
            'scratch_pad_id' => $this->integer()->null(),
            'started_at' => $this->string(19)->null(),
            'completed_at' => $this->string(19)->null(),
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addForeignKey(
            'fk_wf_step_result_run',
            '{{%workflow_step_result}}',
            'run_id',
            '{{%workflow_run}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_wf_step_result_step',
            '{{%workflow_step_result}}',
            'step_id',
            '{{%workflow_step}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_wf_step_result_scratch_pad',
            '{{%workflow_step_result}}',
            'scratch_pad_id',
            '{{%scratch_pad}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createIndex('idx_wf_step_result_run_order', '{{%workflow_step_result}}', ['run_id', 'step_order']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%workflow_step_result}}');
    }
}
```

### M5: `yii/migrations/m260208_000005_seed_system_workflow_recipes.php`

```php
<?php

namespace app\migrations;

use yii\db\Migration;

class m260208_000005_seed_system_workflow_recipes extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        // YouTube Extractor recept
        $this->insert('{{%workflow_recipe}}', [
            'user_id' => null,
            'name' => 'YouTube Extractor',
            'description' => 'Haal een transcript op van een YouTube-video, sla het op als ScratchPad, en vertaal of vat het samen met AI.',
            'type' => 'youtube_extractor',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $youtubeRecipeId = $this->db->getLastInsertID();

        // Stap 1: Transcript ophalen
        $this->insert('{{%workflow_step}}', [
            'recipe_id' => $youtubeRecipeId,
            'order' => 1,
            'step_type' => 'youtube_transcript',
            'label' => 'Transcript ophalen',
            'config' => json_encode([], JSON_THROW_ON_ERROR),
            'requires_approval' => true,
            'is_skippable' => false,
        ]);

        // Stap 2: Opslaan als ScratchPad
        $this->insert('{{%workflow_step}}', [
            'recipe_id' => $youtubeRecipeId,
            'order' => 2,
            'step_type' => 'save_scratch_pad',
            'label' => 'Opslaan als ScratchPad',
            'config' => json_encode([
                'content_source' => 'step:1',
                'name_source' => 'auto',
            ], JSON_THROW_ON_ERROR),
            'requires_approval' => false,
            'is_skippable' => false,
        ]);

        // Stap 3: Vertalen of samenvatten
        $this->insert('{{%workflow_step}}', [
            'recipe_id' => $youtubeRecipeId,
            'order' => 3,
            'step_type' => 'ai_transform',
            'label' => 'Vertalen of samenvatten',
            'config' => json_encode([
                'content_source' => 'step:1',
                'instruction_source' => 'user',
                'ui' => [
                    'fields' => [
                        [
                            'name' => 'action',
                            'type' => 'select',
                            'label' => 'Actie',
                            'options' => [
                                ['value' => 'translate', 'label' => 'Vertalen'],
                                ['value' => 'summarize', 'label' => 'Samenvatten'],
                                ['value' => 'custom', 'label' => 'Vrije instructie'],
                            ],
                            'default' => 'translate',
                        ],
                        [
                            'name' => 'language',
                            'type' => 'select',
                            'label' => 'Taal',
                            'options' => [
                                ['value' => 'nl', 'label' => 'Nederlands'],
                                ['value' => 'en', 'label' => 'Engels'],
                                ['value' => 'de', 'label' => 'Duits'],
                                ['value' => 'fr', 'label' => 'Frans'],
                                ['value' => 'es', 'label' => 'Spaans'],
                            ],
                            'default' => 'nl',
                            'allowCustom' => true,
                        ],
                        [
                            'name' => 'purpose',
                            'type' => 'text',
                            'label' => 'Doel / doelgroep',
                            'placeholder' => 'Bijv. "voor een blogpost" of "voor een technisch team"',
                            'showWhen' => ['action' => 'summarize'],
                        ],
                        [
                            'name' => 'instruction',
                            'type' => 'textarea',
                            'label' => 'Instructie',
                            'placeholder' => 'Beschrijf wat je wilt...',
                            'showWhen' => ['action' => 'custom'],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'requires_approval' => true,
            'is_skippable' => true,
        ]);

        // Stap 4: Resultaat opslaan
        $this->insert('{{%workflow_step}}', [
            'recipe_id' => $youtubeRecipeId,
            'order' => 4,
            'step_type' => 'save_scratch_pad',
            'label' => 'Resultaat opslaan',
            'config' => json_encode([
                'content_source' => 'step:3',
                'name_source' => 'auto',
            ], JSON_THROW_ON_ERROR),
            'requires_approval' => false,
            'is_skippable' => false,
        ]);
    }

    public function safeDown(): void
    {
        // Verwijder stappen via cascade
        $this->delete('{{%workflow_recipe}}', ['type' => 'youtube_extractor', 'is_system' => true]);
    }
}
```

---

## 3. Enums

### E1: `yii/common/enums/WorkflowRecipeType.php`

```php
<?php

namespace common\enums;

enum WorkflowRecipeType: string
{
    case YOUTUBE_EXTRACTOR = 'youtube_extractor';
    case WEBSITE_MANAGER = 'website_manager';

    public static function values(): array
    {
        return array_map(static fn(self $type): string => $type->value, self::cases());
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $type) {
            $labels[$type->value] = $type->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return match ($this) {
            self::YOUTUBE_EXTRACTOR => 'YouTube Extractor',
            self::WEBSITE_MANAGER => 'Website Manager',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::YOUTUBE_EXTRACTOR => 'Haal een transcript op van een YouTube-video, sla het op, en vertaal of vat het samen.',
            self::WEBSITE_MANAGER => 'Haal een webpagina op, herschrijf de content vanuit geselecteerde rollen, en sla suggesties op.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::YOUTUBE_EXTRACTOR => 'bi-youtube',
            self::WEBSITE_MANAGER => 'bi-globe',
        };
    }
}
```

### E2: `yii/common/enums/WorkflowStepType.php`

```php
<?php

namespace common\enums;

enum WorkflowStepType: string
{
    case YOUTUBE_TRANSCRIPT = 'youtube_transcript';
    case AI_TRANSFORM = 'ai_transform';
    case PROMPT_GENERATE = 'prompt_generate';
    case SAVE_SCRATCH_PAD = 'save_scratch_pad';
    case USER_INPUT = 'user_input';
    case URL_FETCH = 'url_fetch';

    public static function values(): array
    {
        return array_map(static fn(self $type): string => $type->value, self::cases());
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $type) {
            $labels[$type->value] = $type->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return match ($this) {
            self::YOUTUBE_TRANSCRIPT => 'YouTube Transcript',
            self::AI_TRANSFORM => 'AI Transform',
            self::PROMPT_GENERATE => 'Prompt Generate',
            self::SAVE_SCRATCH_PAD => 'Save to Scratch Pad',
            self::USER_INPUT => 'User Input',
            self::URL_FETCH => 'Fetch URL',
        };
    }
}
```

---

## 4. Modellen & Query Classes

### MD1: `yii/models/WorkflowRecipe.php`

```php
<?php

namespace app\models;

use app\models\query\WorkflowRecipeQuery;
use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use common\enums\WorkflowRecipeType;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property bool $is_system
 * @property string $created_at
 * @property string $updated_at
 *
 * @property User|null $user
 * @property WorkflowStep[] $steps
 * @property WorkflowRun[] $runs
 */
class WorkflowRecipe extends ActiveRecord
{
    use TimestampTrait;

    public static function tableName(): string
    {
        return 'workflow_recipe';
    }

    public static function find(): WorkflowRecipeQuery
    {
        return new WorkflowRecipeQuery(static::class);
    }

    public function rules(): array
    {
        return [
            [['name', 'type'], 'required'],
            [['user_id'], 'integer'],
            [['description'], 'string'],
            [['is_system'], 'boolean'],
            [['created_at', 'updated_at'], 'string'],
            [['name'], 'string', 'max' => 255],
            [['type'], 'string', 'max' => 64],
            [['type'], 'in', 'range' => WorkflowRecipeType::values()],
            [
                ['user_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => User::class,
                'targetAttribute' => ['user_id' => 'id'],
                'when' => fn($model) => $model->user_id !== null,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'name' => 'Name',
            'description' => 'Description',
            'type' => 'Type',
            'is_system' => 'System Recipe',
            'created_at' => 'Created',
            'updated_at' => 'Updated',
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getSteps(): ActiveQuery
    {
        return $this->hasMany(WorkflowStep::class, ['recipe_id' => 'id'])
            ->orderBy(['order' => SORT_ASC]);
    }

    public function getRuns(): ActiveQuery
    {
        return $this->hasMany(WorkflowRun::class, ['recipe_id' => 'id']);
    }

    public function getRecipeType(): WorkflowRecipeType
    {
        return WorkflowRecipeType::from($this->type);
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord && $this->user_id === null && !$this->is_system) {
            $this->user_id = Yii::$app->user->id ?? null;
        }
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);

        return true;
    }
}
```

### MD2: `yii/models/query/WorkflowRecipeQuery.php`

```php
<?php

namespace app\models\query;

use app\models\WorkflowRecipe;
use yii\db\ActiveQuery;

/**
 * @extends ActiveQuery<WorkflowRecipe>
 */
class WorkflowRecipeQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this->andWhere(['or',
            [WorkflowRecipe::tableName() . '.user_id' => $userId],
            [WorkflowRecipe::tableName() . '.is_system' => true],
        ]);
    }

    public function onlySystem(): self
    {
        return $this->andWhere([WorkflowRecipe::tableName() . '.is_system' => true]);
    }

    public function onlyCustom(int $userId): self
    {
        return $this->andWhere([
            WorkflowRecipe::tableName() . '.is_system' => false,
            WorkflowRecipe::tableName() . '.user_id' => $userId,
        ]);
    }

    public function withType(string $type): self
    {
        return $this->andWhere([WorkflowRecipe::tableName() . '.type' => $type]);
    }

    public function orderedByName(): self
    {
        return $this->orderBy([WorkflowRecipe::tableName() . '.name' => SORT_ASC]);
    }
}
```

### MD3: `yii/models/WorkflowStep.php`

```php
<?php

namespace app\models;

use common\enums\WorkflowStepType;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $recipe_id
 * @property int $order
 * @property string $step_type
 * @property string $label
 * @property string|null $config
 * @property bool $requires_approval
 * @property bool $is_skippable
 *
 * @property WorkflowRecipe $recipe
 */
class WorkflowStep extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'workflow_step';
    }

    public function rules(): array
    {
        return [
            [['recipe_id', 'order', 'step_type', 'label'], 'required'],
            [['recipe_id', 'order'], 'integer'],
            [['config'], 'string'],
            [['requires_approval', 'is_skippable'], 'boolean'],
            [['step_type'], 'string', 'max' => 64],
            [['label'], 'string', 'max' => 255],
            [['step_type'], 'in', 'range' => WorkflowStepType::values()],
            [
                ['recipe_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => WorkflowRecipe::class,
                'targetAttribute' => ['recipe_id' => 'id'],
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'recipe_id' => 'Recipe',
            'order' => 'Order',
            'step_type' => 'Step Type',
            'label' => 'Label',
            'config' => 'Configuration',
            'requires_approval' => 'Requires Approval',
            'is_skippable' => 'Skippable',
        ];
    }

    public function getRecipe(): ActiveQuery
    {
        return $this->hasOne(WorkflowRecipe::class, ['id' => 'recipe_id']);
    }

    public function getStepType(): WorkflowStepType
    {
        return WorkflowStepType::from($this->step_type);
    }

    public function getDecodedConfig(): array
    {
        if ($this->config === null || $this->config === '') {
            return [];
        }

        return json_decode($this->config, true) ?? [];
    }
}
```

### MD4: `yii/models/WorkflowRun.php`

```php
<?php

namespace app\models;

use app\models\query\WorkflowRunQuery;
use app\models\traits\TimestampTrait;
use app\modules\identity\models\User;
use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $user_id
 * @property int $recipe_id
 * @property int|null $project_id
 * @property string $status
 * @property int $current_step
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $completed_at
 *
 * @property User $user
 * @property WorkflowRecipe $recipe
 * @property Project|null $project
 * @property WorkflowStepResult[] $stepResults
 */
class WorkflowRun extends ActiveRecord
{
    use TimestampTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    public static function tableName(): string
    {
        return 'workflow_run';
    }

    public static function find(): WorkflowRunQuery
    {
        return new WorkflowRunQuery(static::class);
    }

    public function rules(): array
    {
        return [
            [['user_id', 'recipe_id'], 'required'],
            [['user_id', 'recipe_id', 'project_id', 'current_step'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['status'], 'in', 'range' => self::STATUSES],
            [['created_at', 'updated_at', 'completed_at'], 'string'],
            [
                ['user_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => User::class,
                'targetAttribute' => ['user_id' => 'id'],
            ],
            [
                ['recipe_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => WorkflowRecipe::class,
                'targetAttribute' => ['recipe_id' => 'id'],
            ],
            [
                ['project_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Project::class,
                'targetAttribute' => ['project_id' => 'id'],
                'when' => fn($model) => $model->project_id !== null,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_id' => 'User',
            'recipe_id' => 'Recipe',
            'project_id' => 'Project',
            'status' => 'Status',
            'current_step' => 'Current Step',
            'created_at' => 'Started',
            'updated_at' => 'Updated',
            'completed_at' => 'Completed',
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getRecipe(): ActiveQuery
    {
        return $this->hasOne(WorkflowRecipe::class, ['id' => 'recipe_id']);
    }

    public function getProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getStepResults(): ActiveQuery
    {
        return $this->hasMany(WorkflowStepResult::class, ['run_id' => 'id'])
            ->orderBy(['step_order' => SORT_ASC]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_PAUSED]);
    }

    public function getCurrentStepResult(): ?WorkflowStepResult
    {
        return WorkflowStepResult::find()
            ->andWhere(['run_id' => $this->id, 'step_order' => $this->current_step])
            ->one();
    }

    public function init(): void
    {
        parent::init();

        if ($this->isNewRecord && $this->user_id === null) {
            $this->user_id = Yii::$app->user->id;
        }
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->handleTimestamps($insert);

        return true;
    }
}
```

### MD5: `yii/models/query/WorkflowRunQuery.php`

```php
<?php

namespace app\models\query;

use app\models\WorkflowRun;
use yii\db\ActiveQuery;

/**
 * @extends ActiveQuery<WorkflowRun>
 */
class WorkflowRunQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this->andWhere([WorkflowRun::tableName() . '.user_id' => $userId]);
    }

    public function forProject(?int $projectId): self
    {
        if ($projectId === null) {
            return $this->andWhere([WorkflowRun::tableName() . '.project_id' => null]);
        }

        return $this->andWhere([WorkflowRun::tableName() . '.project_id' => $projectId]);
    }

    public function active(): self
    {
        return $this->andWhere([WorkflowRun::tableName() . '.status' => [
            WorkflowRun::STATUS_PENDING,
            WorkflowRun::STATUS_RUNNING,
            WorkflowRun::STATUS_PAUSED,
        ]]);
    }

    public function completed(): self
    {
        return $this->andWhere([WorkflowRun::tableName() . '.status' => [
            WorkflowRun::STATUS_COMPLETED,
            WorkflowRun::STATUS_FAILED,
            WorkflowRun::STATUS_CANCELLED,
        ]]);
    }

    public function forRecipe(int $recipeId): self
    {
        return $this->andWhere([WorkflowRun::tableName() . '.recipe_id' => $recipeId]);
    }

    public function orderedByUpdated(): self
    {
        return $this->orderBy([WorkflowRun::tableName() . '.updated_at' => SORT_DESC]);
    }
}
```

### MD6: `yii/models/WorkflowStepResult.php`

```php
<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $run_id
 * @property int $step_id
 * @property int $step_order
 * @property string $status
 * @property string|null $input_data
 * @property string|null $output_data
 * @property string|null $error_message
 * @property int|null $scratch_pad_id
 * @property string|null $started_at
 * @property string|null $completed_at
 *
 * @property WorkflowRun $run
 * @property WorkflowStep $step
 * @property ScratchPad|null $scratchPad
 */
class WorkflowStepResult extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
    ];

    public static function tableName(): string
    {
        return 'workflow_step_result';
    }

    public function rules(): array
    {
        return [
            [['run_id', 'step_id', 'step_order'], 'required'],
            [['run_id', 'step_id', 'step_order', 'scratch_pad_id'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['status'], 'in', 'range' => self::STATUSES],
            [['input_data', 'output_data'], 'string'],
            [['error_message'], 'string'],
            [['started_at', 'completed_at'], 'string'],
            [
                ['run_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => WorkflowRun::class,
                'targetAttribute' => ['run_id' => 'id'],
            ],
            [
                ['step_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => WorkflowStep::class,
                'targetAttribute' => ['step_id' => 'id'],
            ],
            [
                ['scratch_pad_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => ScratchPad::class,
                'targetAttribute' => ['scratch_pad_id' => 'id'],
                'when' => fn($model) => $model->scratch_pad_id !== null,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'run_id' => 'Run',
            'step_id' => 'Step',
            'step_order' => 'Step Order',
            'status' => 'Status',
            'input_data' => 'Input',
            'output_data' => 'Output',
            'error_message' => 'Error',
            'scratch_pad_id' => 'Scratch Pad',
            'started_at' => 'Started',
            'completed_at' => 'Completed',
        ];
    }

    public function getRun(): ActiveQuery
    {
        return $this->hasOne(WorkflowRun::class, ['id' => 'run_id']);
    }

    public function getStep(): ActiveQuery
    {
        return $this->hasOne(WorkflowStep::class, ['id' => 'step_id']);
    }

    public function getScratchPad(): ActiveQuery
    {
        return $this->hasOne(ScratchPad::class, ['id' => 'scratch_pad_id']);
    }

    public function getDecodedInputData(): array
    {
        if ($this->input_data === null || $this->input_data === '') {
            return [];
        }

        return json_decode($this->input_data, true) ?? [];
    }

    public function getDecodedOutputData(): array
    {
        if ($this->output_data === null || $this->output_data === '') {
            return [];
        }

        return json_decode($this->output_data, true) ?? [];
    }
}
```

---

## 5. RBAC Rules

### R1: `yii/rbac/WorkflowRecipeOwnerRule.php`

```php
<?php

namespace app\rbac;

use yii\rbac\Rule;

/**
 * Checks ownership of workflow recipes. System recipes are accessible to all users.
 */
class WorkflowRecipeOwnerRule extends Rule
{
    public $name = 'isWorkflowRecipeOwner';

    public function execute($user, $item, $params): bool
    {
        if (isset($params['model']->is_system) && $params['model']->is_system) {
            return true;
        }
        if (isset($params['model']->user_id)) {
            return $params['model']->user_id == $user;
        }

        return false;
    }
}
```

### R2: `yii/rbac/WorkflowRunOwnerRule.php`

```php
<?php

namespace app\rbac;

use yii\rbac\Rule;

/**
 * Checks if the user ID matches the user_id attribute of the workflow run.
 */
class WorkflowRunOwnerRule extends Rule
{
    public $name = 'isWorkflowRunOwner';

    public function execute($user, $item, $params): bool
    {
        if (isset($params['model']->user_id)) {
            return $params['model']->user_id == $user;
        }

        return false;
    }
}
```

---

## 6. Services

### S1: `yii/services/WorkflowRecipeService.php`

```php
<?php

namespace app\services;

use app\models\WorkflowRecipe;
use yii\web\NotFoundHttpException;

/**
 * Manages workflow recipe retrieval and access.
 */
readonly class WorkflowRecipeService
{
    /**
     * Fetch all recipes available to a user (system + own).
     *
     * @return WorkflowRecipe[]
     */
    public function fetchRecipesForUser(int $userId): array
    {
        return WorkflowRecipe::find()
            ->forUser($userId)
            ->orderedByName()
            ->all();
    }

    /**
     * Find a recipe with its steps, verifying user access.
     *
     * @throws NotFoundHttpException
     */
    public function findRecipeWithSteps(int $recipeId, int $userId): WorkflowRecipe
    {
        $recipe = WorkflowRecipe::find()
            ->forUser($userId)
            ->andWhere(['workflow_recipe.id' => $recipeId])
            ->with('steps')
            ->one();

        if ($recipe === null) {
            throw new NotFoundHttpException('Workflow recipe not found.');
        }

        return $recipe;
    }
}
```

### S2: `yii/services/WorkflowRunService.php`

```php
<?php

namespace app\services;

use app\models\WorkflowRun;
use app\models\WorkflowStepResult;
use RuntimeException;
use Throwable;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Manages workflow run lifecycle: start, execute, advance, pause, cancel.
 */
readonly class WorkflowRunService
{
    public function __construct(
        private WorkflowRecipeService $recipeService,
        private WorkflowStepExecutor $stepExecutor,
    ) {}

    /**
     * Start a new workflow run for a recipe.
     *
     * @throws RuntimeException
     */
    public function startRun(int $recipeId, int $userId, ?int $projectId): WorkflowRun
    {
        $recipe = $this->recipeService->findRecipeWithSteps($recipeId, $userId);

        $run = new WorkflowRun();
        $run->user_id = $userId;
        $run->recipe_id = $recipe->id;
        $run->project_id = $projectId;
        $run->status = WorkflowRun::STATUS_PENDING;
        $run->current_step = 1;

        if (!$run->save()) {
            throw new RuntimeException('Failed to create workflow run: ' . implode(', ', $run->getFirstErrors()));
        }

        $this->initializeStepResults($run, $recipe->steps);

        return $run;
    }

    /**
     * Find a run with ownership check.
     *
     * @throws NotFoundHttpException
     */
    public function findRunWithOwner(int $runId, int $userId): WorkflowRun
    {
        $run = WorkflowRun::find()
            ->forUser($userId)
            ->andWhere(['workflow_run.id' => $runId])
            ->with(['recipe', 'recipe.steps', 'stepResults'])
            ->one();

        if ($run === null) {
            throw new NotFoundHttpException('Workflow run not found.');
        }

        return $run;
    }

    /**
     * Execute the current step with optional user input.
     *
     * @throws RuntimeException
     */
    public function executeCurrentStep(WorkflowRun $run, array $userInput = []): WorkflowStepResult
    {
        $stepResult = $run->getCurrentStepResult();

        if ($stepResult === null) {
            throw new RuntimeException('No step result found for current step.');
        }

        if ($stepResult->status === WorkflowStepResult::STATUS_RUNNING) {
            throw new RuntimeException('Step is already running.');
        }

        $run->status = WorkflowRun::STATUS_RUNNING;
        $run->save(false);

        $stepResult = $this->stepExecutor->execute($stepResult, $run, $userInput);

        // Als de stap gelukt is en geen goedkeuring nodig: auto-advance
        if ($stepResult->status === WorkflowStepResult::STATUS_COMPLETED && !$stepResult->step->requires_approval) {
            $this->doAdvance($run);
        } elseif ($stepResult->status === WorkflowStepResult::STATUS_COMPLETED && $stepResult->step->requires_approval) {
            $run->status = WorkflowRun::STATUS_PAUSED;
            $run->save(false);
        } elseif ($stepResult->status === WorkflowStepResult::STATUS_FAILED) {
            $run->status = WorkflowRun::STATUS_PAUSED;
            $run->save(false);
        }

        return $stepResult;
    }

    /**
     * Advance to the next step after user approval.
     * Optionally accepts edited output data from the user.
     */
    public function advanceToNextStep(WorkflowRun $run, ?string $editedOutputData = null): WorkflowRun
    {
        $currentStepResult = $run->getCurrentStepResult();

        if ($currentStepResult !== null && $editedOutputData !== null) {
            $currentStepResult->output_data = $editedOutputData;
            $currentStepResult->save(false);
        }

        $this->doAdvance($run);

        return $run;
    }

    /**
     * Skip the current step.
     */
    public function skipCurrentStep(WorkflowRun $run): WorkflowRun
    {
        $stepResult = $run->getCurrentStepResult();

        if ($stepResult === null) {
            throw new RuntimeException('No step result found for current step.');
        }

        if (!$stepResult->step->is_skippable) {
            throw new RuntimeException('This step cannot be skipped.');
        }

        $stepResult->status = WorkflowStepResult::STATUS_SKIPPED;
        $stepResult->completed_at = date('Y-m-d H:i:s');
        $stepResult->save(false);

        $this->doAdvance($run);

        return $run;
    }

    /**
     * Cancel the run.
     */
    public function cancelRun(WorkflowRun $run): WorkflowRun
    {
        $run->status = WorkflowRun::STATUS_CANCELLED;
        $run->completed_at = date('Y-m-d H:i:s');
        $run->save(false);

        return $run;
    }

    /**
     * Fetch runs for a user, optionally filtered by status group.
     *
     * @return WorkflowRun[]
     */
    public function fetchRunsForUser(int $userId, ?string $statusGroup = null): array
    {
        $query = WorkflowRun::find()
            ->forUser($userId)
            ->with(['recipe', 'project'])
            ->orderedByUpdated();

        if ($statusGroup === 'active') {
            $query->active();
        } elseif ($statusGroup === 'completed') {
            $query->completed();
        }

        return $query->all();
    }

    /**
     * Internal: advance current_step and update run status.
     */
    private function doAdvance(WorkflowRun $run): void
    {
        $steps = $run->recipe->steps;
        $maxStep = count($steps);

        if ($run->current_step >= $maxStep) {
            $run->status = WorkflowRun::STATUS_COMPLETED;
            $run->completed_at = date('Y-m-d H:i:s');
            $run->save(false);
            return;
        }

        $run->current_step = $run->current_step + 1;
        $run->status = WorkflowRun::STATUS_RUNNING;
        $run->save(false);

        // Auto-execute als de volgende stap geen goedkeuring nodig heeft
        $nextStepResult = $run->getCurrentStepResult();
        if ($nextStepResult !== null && !$nextStepResult->step->requires_approval) {
            try {
                $this->executeCurrentStep($run);
            } catch (Throwable $e) {
                Yii::error("Auto-execute failed for step {$run->current_step}: {$e->getMessage()}", 'workflow');
            }
        } else {
            $run->status = WorkflowRun::STATUS_PAUSED;
            $run->save(false);
        }
    }

    /**
     * Create WorkflowStepResult records for each step in the recipe.
     */
    private function initializeStepResults(WorkflowRun $run, array $steps): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            foreach ($steps as $step) {
                $result = new WorkflowStepResult();
                $result->run_id = $run->id;
                $result->step_id = $step->id;
                $result->step_order = $step->order;
                $result->status = WorkflowStepResult::STATUS_PENDING;

                if (!$result->save()) {
                    throw new RuntimeException(
                        'Failed to create step result: ' . implode(', ', $result->getFirstErrors())
                    );
                }
            }
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
```

### S3: `yii/services/WorkflowStepExecutor.php`

```php
<?php

namespace app\services;

use app\models\WorkflowRun;
use app\models\WorkflowStepResult;
use app\services\workflowsteps\StepHandlerInterface;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Delegates step execution to the appropriate handler based on step_type.
 */
readonly class WorkflowStepExecutor
{
    /**
     * @param StepHandlerInterface[] $handlers Keyed by step_type string
     */
    public function __construct(
        private array $handlers,
    ) {}

    /**
     * Execute a workflow step.
     *
     * @throws RuntimeException if no handler found
     */
    public function execute(WorkflowStepResult $stepResult, WorkflowRun $run, array $userInput = []): WorkflowStepResult
    {
        $stepType = $stepResult->step->step_type;
        $handler = $this->handlers[$stepType]
            ?? throw new RuntimeException("No handler for step type: {$stepType}");

        $stepResult->status = WorkflowStepResult::STATUS_RUNNING;
        $stepResult->started_at = date('Y-m-d H:i:s');
        $stepResult->save(false);

        try {
            $inputData = $this->resolveInputData($stepResult, $run, $userInput);
            $stepResult->input_data = json_encode($inputData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $outputData = $handler->execute($inputData, $stepResult->step->getDecodedConfig(), $run);

            $stepResult->output_data = json_encode($outputData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $stepResult->status = WorkflowStepResult::STATUS_COMPLETED;
            $stepResult->completed_at = date('Y-m-d H:i:s');
        } catch (Throwable $e) {
            $stepResult->status = WorkflowStepResult::STATUS_FAILED;
            $stepResult->error_message = $e->getMessage();
            $stepResult->completed_at = date('Y-m-d H:i:s');
            Yii::error("Workflow step failed: {$e->getMessage()}", 'workflow');
        }

        $stepResult->save(false);

        return $stepResult;
    }

    /**
     * Resolve input data for a step by checking content_source config
     * and merging with user input.
     */
    private function resolveInputData(WorkflowStepResult $stepResult, WorkflowRun $run, array $userInput): array
    {
        $config = $stepResult->step->getDecodedConfig();
        $inputData = $userInput;

        // Resolve content_source: "step:N" referentie
        $contentSource = $config['content_source'] ?? null;
        if ($contentSource !== null && str_starts_with($contentSource, 'step:')) {
            $sourceStepOrder = (int) substr($contentSource, 5);
            $sourceResult = WorkflowStepResult::find()
                ->andWhere(['run_id' => $run->id, 'step_order' => $sourceStepOrder])
                ->one();

            if ($sourceResult !== null && $sourceResult->output_data !== null) {
                $sourceOutput = $sourceResult->getDecodedOutputData();
                // Merge source output into input, user input takes precedence
                $inputData = array_merge($sourceOutput, $inputData);
            }
        }

        return $inputData;
    }
}
```

---

## 7. Step Handlers

### H0: `yii/services/workflowsteps/StepHandlerInterface.php`

```php
<?php

namespace app\services\workflowsteps;

use app\models\WorkflowRun;

interface StepHandlerInterface
{
    /**
     * Execute a workflow step.
     *
     * @param array $inputData Resolved input for this step
     * @param array $config Step configuration from WorkflowStep.config
     * @param WorkflowRun $run The current workflow run
     * @return array Output data (will be JSON-encoded and stored)
     * @throws \RuntimeException on failure
     */
    public function execute(array $inputData, array $config, WorkflowRun $run): array;
}
```

### H1: `yii/services/workflowsteps/YoutubeTranscriptHandler.php`

```php
<?php

namespace app\services\workflowsteps;

use app\models\WorkflowRun;
use app\services\YouTubeTranscriptService;
use RuntimeException;

/**
 * Fetches a YouTube transcript and returns it as Quill Delta JSON.
 */
readonly class YoutubeTranscriptHandler implements StepHandlerInterface
{
    public function __construct(
        private YouTubeTranscriptService $youtubeService,
    ) {}

    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        $videoUrl = $inputData['video_url'] ?? '';
        if ($videoUrl === '') {
            throw new RuntimeException('YouTube video URL is required.');
        }

        $videoId = $this->youtubeService->extractVideoId($videoUrl);
        $transcriptData = $this->youtubeService->fetchTranscript($videoId);
        $deltaContent = $this->youtubeService->convertToQuillDelta($transcriptData);
        $title = $this->youtubeService->getTitle($transcriptData);

        return [
            'content' => $deltaContent,
            'title' => $title,
            'video_id' => $transcriptData['video_id'] ?? $videoId,
            'channel' => $transcriptData['channel'] ?? '',
            'url' => $transcriptData['url'] ?? '',
        ];
    }
}
```

### H2: `yii/services/workflowsteps/AiTransformHandler.php`

```php
<?php

namespace app\services\workflowsteps;

use app\models\WorkflowRun;
use app\services\ClaudeCliService;
use app\services\CopyFormatConverter;
use app\services\copyformat\MarkdownParser;
use app\services\copyformat\QuillDeltaWriter;
use common\enums\CopyType;
use RuntimeException;

/**
 * Sends content to Claude CLI with an instruction and returns the AI response.
 */
readonly class AiTransformHandler implements StepHandlerInterface
{
    public function __construct(
        private ClaudeCliService $claudeCliService,
        private CopyFormatConverter $copyFormatConverter,
        private ?MarkdownParser $markdownParser = null,
        private ?QuillDeltaWriter $quillDeltaWriter = null,
    ) {}

    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        $content = $inputData['content'] ?? '';
        if ($content === '') {
            throw new RuntimeException('No content provided for AI transformation.');
        }

        $markdown = $this->copyFormatConverter->convertFromQuillDelta($content, CopyType::MD);
        $prompt = $this->buildPrompt($inputData, $config, $markdown);

        $result = $this->claudeCliService->execute(
            prompt: $prompt,
            workingDirectory: $run->project->root_directory ?? '',
            timeout: (int) ($config['timeout'] ?? 300),
            options: ['model' => $config['model'] ?? 'sonnet'],
            project: $run->project,
        );

        if (!$result['success']) {
            throw new RuntimeException($result['error'] ?: 'AI processing failed.');
        }

        $outputDelta = $this->convertOutputToDelta($result['output']);

        return [
            'content' => $outputDelta,
            'raw_output' => $result['output'],
            'model' => $result['model'] ?? '',
            'duration_ms' => $result['duration_ms'] ?? 0,
        ];
    }

    private function buildPrompt(array $inputData, array $config, string $markdown): string
    {
        $action = $inputData['action'] ?? 'translate';
        $language = $inputData['language'] ?? 'nl';
        $languageLabel = $this->resolveLanguageLabel($language);

        $instruction = match ($action) {
            'translate' => "Translate the following content to {$languageLabel}. Maintain the original formatting and structure.",
            'summarize' => $this->buildSummarizeInstruction($inputData, $languageLabel),
            'custom' => $inputData['instruction'] ?? 'Process the following content.',
            default => "Translate the following content to {$languageLabel}.",
        };

        return $instruction . "\n\n---\n\n" . $markdown;
    }

    private function buildSummarizeInstruction(array $inputData, string $languageLabel): string
    {
        $purpose = $inputData['purpose'] ?? '';
        $instruction = "Write a comprehensive summary of the following content in {$languageLabel}.";

        if ($purpose !== '') {
            $instruction .= " The summary is intended {$purpose}.";
        }

        $instruction .= ' Use clear headings and bullet points where appropriate.';

        return $instruction;
    }

    private function resolveLanguageLabel(string $language): string
    {
        return match ($language) {
            'nl' => 'Dutch',
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            default => $language,
        };
    }

    private function convertOutputToDelta(string $markdown): string
    {
        $parser = $this->markdownParser ?? new MarkdownParser();
        $writer = $this->quillDeltaWriter ?? new QuillDeltaWriter();

        $blocks = $parser->parse($markdown);

        return $writer->writeFromBlocks($blocks);
    }
}
```

### H4: `yii/services/workflowsteps/SaveScratchPadHandler.php`

```php
<?php

namespace app\services\workflowsteps;

use app\models\ScratchPad;
use app\models\WorkflowRun;
use app\models\WorkflowStepResult;
use RuntimeException;

/**
 * Saves step output as a new ScratchPad.
 */
readonly class SaveScratchPadHandler implements StepHandlerInterface
{
    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        $content = $inputData['content'] ?? '';
        if ($content === '') {
            throw new RuntimeException('No content to save to Scratch Pad.');
        }

        $name = $inputData['title']
            ?? $inputData['name']
            ?? $config['name']
            ?? 'Workflow result - ' . date('Y-m-d H:i');

        $scratchPad = new ScratchPad([
            'user_id' => $run->user_id,
            'project_id' => $run->project_id,
            'name' => mb_substr($name, 0, 255),
            'content' => $content,
        ]);

        if (!$scratchPad->save()) {
            throw new RuntimeException(
                'Failed to save Scratch Pad: ' . implode(', ', $scratchPad->getFirstErrors())
            );
        }

        // Koppel de ScratchPad aan het step result
        $stepResult = WorkflowStepResult::find()
            ->andWhere(['run_id' => $run->id, 'step_order' => $run->current_step])
            ->one();

        if ($stepResult !== null) {
            $stepResult->scratch_pad_id = $scratchPad->id;
            $stepResult->save(false);
        }

        return [
            'scratch_pad_id' => $scratchPad->id,
            'name' => $scratchPad->name,
        ];
    }
}
```

### H5: `yii/services/workflowsteps/UserInputHandler.php`

```php
<?php

namespace app\services\workflowsteps;

use app\models\WorkflowRun;

/**
 * Passes user input through as step output.
 * The controller pauses the run and waits for user input before calling this handler.
 */
readonly class UserInputHandler implements StepHandlerInterface
{
    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        return $inputData;
    }
}
```

---

## 8. Controller

### C1: `yii/controllers/WorkflowController.php`

```php
<?php

/** @noinspection PhpUnused */

namespace app\controllers;

use app\models\Project;
use app\models\WorkflowRun;
use app\models\WorkflowStepResult;
use app\services\EntityPermissionService;
use app\services\WorkflowRecipeService;
use app\services\WorkflowRunService;
use RuntimeException;
use Throwable;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class WorkflowController extends Controller
{
    private array $actionPermissionMap;
    private readonly EntityPermissionService $permissionService;
    private readonly WorkflowRecipeService $recipeService;
    private readonly WorkflowRunService $runService;

    public function __construct(
        $id,
        $module,
        EntityPermissionService $permissionService,
        WorkflowRecipeService $recipeService,
        WorkflowRunService $runService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
        $this->permissionService = $permissionService;
        $this->recipeService = $recipeService;
        $this->runService = $runService;
        $this->actionPermissionMap = $this->permissionService->getActionPermissionMap('workflowRun');
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'start' => ['POST'],
                    'execute-step' => ['POST'],
                    'advance-step' => ['POST'],
                    'skip-step' => ['POST'],
                    'cancel' => ['POST'],
                    'delete' => ['POST'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['index', 'start'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'actions' => array_keys($this->actionPermissionMap),
                        'matchCallback' => function ($rule, $action) {
                            $callback = $this->permissionService->isModelBasedAction($action->id)
                                ? fn() => $this->findRunModel((int) Yii::$app->request->get('id'))
                                : null;

                            return $this->permissionService->hasActionPermission(
                                'workflowRun',
                                $action->id,
                                $callback
                            );
                        },
                    ],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $userId = Yii::$app->user->id;
        $recipes = $this->recipeService->fetchRecipesForUser($userId);
        $activeRuns = $this->runService->fetchRunsForUser($userId, 'active');
        $completedRuns = $this->runService->fetchRunsForUser($userId, 'completed');
        $projects = Project::find()->forUser($userId)->orderedByName()->all();

        return $this->render('index', [
            'recipes' => $recipes,
            'activeRuns' => $activeRuns,
            'completedRuns' => $completedRuns,
            'projects' => $projects,
        ]);
    }

    public function actionStart(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $data = json_decode(Yii::$app->request->rawBody, true);
        $recipeId = (int) ($data['recipe_id'] ?? 0);
        $projectId = !empty($data['project_id']) ? (int) $data['project_id'] : null;
        $userId = Yii::$app->user->id;

        // Valideer project eigenaarschap
        if ($projectId !== null) {
            $project = Project::find()->findUserProject($projectId, $userId);
            if ($project === null) {
                return ['success' => false, 'message' => 'Invalid project selected.'];
            }
        }

        try {
            $run = $this->runService->startRun($recipeId, $userId, $projectId);

            return [
                'success' => true,
                'run_id' => $run->id,
                'redirect' => '/workflow/run?id=' . $run->id,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionRun(int $id): string
    {
        $run = $this->runService->findRunWithOwner($id, Yii::$app->user->id);

        return $this->render('run', [
            'run' => $run,
            'recipe' => $run->recipe,
            'steps' => $run->recipe->steps,
            'stepResults' => $run->stepResults,
        ]);
    }

    public function actionExecuteStep(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = $this->findRunModel($id);
        $data = json_decode(Yii::$app->request->rawBody, true);
        $userInput = $data['input'] ?? [];

        try {
            $stepResult = $this->runService->executeCurrentStep($run, $userInput);
            $run->refresh();

            return [
                'success' => $stepResult->status === WorkflowStepResult::STATUS_COMPLETED,
                'stepResult' => [
                    'id' => $stepResult->id,
                    'status' => $stepResult->status,
                    'output_data' => $stepResult->getDecodedOutputData(),
                    'error_message' => $stepResult->error_message,
                ],
                'run' => [
                    'status' => $run->status,
                    'current_step' => $run->current_step,
                ],
            ];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function actionAdvanceStep(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = $this->findRunModel($id);
        $data = json_decode(Yii::$app->request->rawBody, true);
        $editedOutput = $data['edited_output'] ?? null;

        try {
            $this->runService->advanceToNextStep($run, $editedOutput);
            $run->refresh();

            return [
                'success' => true,
                'run' => [
                    'status' => $run->status,
                    'current_step' => $run->current_step,
                ],
            ];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function actionSkipStep(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = $this->findRunModel($id);

        try {
            $this->runService->skipCurrentStep($run);
            $run->refresh();

            return [
                'success' => true,
                'run' => [
                    'status' => $run->status,
                    'current_step' => $run->current_step,
                ],
            ];
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function actionCancel(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $run = $this->findRunModel($id);
        $this->runService->cancelRun($run);

        return ['success' => true];
    }

    /**
     * @throws NotFoundHttpException
     */
    public function actionDelete(int $id): Response
    {
        $run = $this->findRunModel($id);
        $run->delete();

        return $this->redirect(['index']);
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findRunModel(int $id): WorkflowRun
    {
        $model = WorkflowRun::find()
            ->forUser(Yii::$app->user->id)
            ->andWhere(['workflow_run.id' => $id])
            ->with(['recipe', 'recipe.steps', 'stepResults', 'stepResults.step'])
            ->one();

        if ($model === null) {
            throw new NotFoundHttpException('Workflow run not found.');
        }

        return $model;
    }
}
```

---

## 9. Views

Views zijn beschreven als structurele wireframes met PHP/HTML patronen. De exacte implementatie volgt de bestaande conventies uit `yii/views/scratch-pad/`.

### V1: `yii/views/workflow/index.php`

Beschrijving: Overzichtspagina met recept-cards en run-lijsten. Volgt het patroon van `scratch-pad/index.php`.

**Structuur:**
- Header: `h1` "Workflows"
- Sectie "Beschikbare Workflows": `row` met `col-md-4` cards per recept
  - Card bevat: icon (`<i class="bi {$recipe->getRecipeType()->icon()}"></i>`), naam, beschrijving, stappen-count
  - "Start" knop opent `_start_modal.php` per recept
- Sectie "Lopende Workflows": conditieel, als `$activeRuns` niet leeg
  - Tabel met kolommen: Recipe, Project, Status (badge), Stap, Bijgewerkt, Actie (Doorgaan link)
- Sectie "Afgeronde Workflows": conditieel
  - Tabel met kolommen: Recipe, Project, Status (badge), Datum, Actie (Bekijken/Verwijderen)

### V2: `yii/views/workflow/run.php`

Beschrijving: Workflow runner met stepper-accordion. Volgt het accordion-patroon van `prompt-instance/_form.php`.

**Structuur:**
- Header: recept-naam, project, status badge, annuleer-knop
- Bootstrap accordion `#workflow-stepper` met `data-bs-parent`
- Per stap (`$stepResults` loop):
  - Accordion-item met status-indicator in header
  - Body: stap-partial laden via `$this->render('_step_' . $step->step_type, [...])`
  - Fallback voor onbekende types: generieke output viewer
- JavaScript: AJAX handlers voor execute/advance/skip/cancel

### V3: `yii/views/workflow/_step_youtube_transcript.php`

**Voor uitvoering:**
- Label: "YouTube Video URL"
- Input veld voor URL
- Help tekst: "Ondersteund: youtube.com/watch?v=..., youtu.be/..., of video ID"
- Knoppen: "Uitvoeren" (primary), "Overslaan" (secondary, als `is_skippable`)

**Na uitvoering (completed):**
- Succes-indicator met video titel en kanaal
- `QuillViewerWidget` met transcript preview (beperkte hoogte, scrollbaar)
- Knoppen: "Doorgaan" (primary), "Stop" (secondary)

### V4: `yii/views/workflow/_step_ai_transform.php`

**Voor uitvoering:**
- Preview van input content via `QuillViewerWidget`
- Dynamische formuliervelden op basis van `step.config.ui.fields`:
  - "Actie" select: Vertalen / Samenvatten / Vrije instructie
  - "Taal" select: NL/EN/DE/FR/ES + vrij tekstveld
  - "Doel" tekstveld (conditioneel zichtbaar bij "Samenvatten")
  - "Instructie" textarea (conditioneel zichtbaar bij "Vrije instructie")
- Knoppen: "Uitvoeren" (primary), "Overslaan" (secondary)

**Na uitvoering (completed):**
- `QuillViewerWidget` met AI-output
- Meta: model, duration
- Knoppen: "Doorgaan" (primary), "Stop" (secondary)

### V5: `yii/views/workflow/_step_save_result.php`

**Automatische stap** — toont alleen status:
- Pending: spinner + "Wordt opgeslagen..."
- Completed: "Opgeslagen als ScratchPad: {naam}" met link naar `/scratch-pad/view?id={id}`
- Failed: foutmelding met retry-knop

### V6: `yii/views/workflow/_start_modal.php`

Volgt patroon van `yii/views/scratch-pad/_youtube-import-modal.php`.

**Modal structuur:**
- Titel: "Start Workflow: {recept.naam}"
- Hidden input: `recipe_id`
- Dropdown: Project selectie (optioneel)
- Knoppen: "Annuleer" (secondary), "Start" (primary)
- AJAX submit naar `/workflow/start`, redirect op succes

---

## 10. Configuratie-wijzigingen

### W1: `yii/config/rbac.php`

Toevoegen aan `entities` array (na `scratchPad`):

```php
'workflowRun' => [
    'actionPermissionMap' => [
        'run' => 'viewWorkflowRun',
        'execute-step' => 'viewWorkflowRun',
        'advance-step' => 'viewWorkflowRun',
        'skip-step' => 'viewWorkflowRun',
        'cancel' => 'updateWorkflowRun',
        'delete' => 'deleteWorkflowRun',
    ],
    'permissions' => [
        'createWorkflowRun' => [
            'description' => 'Create a Workflow Run',
            'rule' => null,
        ],
        'viewWorkflowRun' => [
            'description' => 'View a Workflow Run',
            'rule' => 'app\rbac\WorkflowRunOwnerRule',
        ],
        'updateWorkflowRun' => [
            'description' => 'Update a Workflow Run',
            'rule' => 'app\rbac\WorkflowRunOwnerRule',
        ],
        'deleteWorkflowRun' => [
            'description' => 'Delete a Workflow Run',
            'rule' => 'app\rbac\WorkflowRunOwnerRule',
        ],
    ],
],
```

Toevoegen aan `roles.user.permissions`:

```php
'createWorkflowRun',
'viewWorkflowRun',
'updateWorkflowRun',
'deleteWorkflowRun',
```

### W2: `yii/views/layouts/main.php`

Na het Scratch Pads nav-item (regel ~61), toevoegen:

```php
[
    'label' => 'Workflows',
    'url' => ['/workflow/index'],
    'options' => ['id' => 'nav-workflows'],
],
```

### W3: `yii/config/main.php`

Toevoegen aan `container.definitions` (na bestaande definities):

```php
\app\services\WorkflowStepExecutor::class => function ($container) {
    return new \app\services\WorkflowStepExecutor([
        'youtube_transcript' => $container->get(\app\services\workflowsteps\YoutubeTranscriptHandler::class),
        'ai_transform' => $container->get(\app\services\workflowsteps\AiTransformHandler::class),
        'save_scratch_pad' => $container->get(\app\services\workflowsteps\SaveScratchPadHandler::class),
        'user_input' => $container->get(\app\services\workflowsteps\UserInputHandler::class),
    ]);
},
```

**Let op:** Alleen de handlers registreren die voor de YouTube Extractor nodig zijn. `PromptGenerateHandler` en `UrlFetchHandler` worden later toegevoegd bij de Website Manager workflow.

### W4: `yii/services/EntityPermissionService.php`

Toevoegen aan `MODEL_BASED_ACTIONS` constante:

```php
'run', 'execute-step', 'advance-step', 'skip-step', 'cancel'
```

**Let op:** `delete` staat al in de array.

---

## 11. Seed Data

De seed data voor het YouTube Extractor recept is opgenomen in migratie M5 (`m260208_000005_seed_system_workflow_recipes.php`). Dit maakt de recept-data beschikbaar op beide database-schemas na het draaien van migraties.

---

## 12. Tests

### T1: `yii/tests/unit/models/WorkflowRecipeTest.php`

```php
public function testValidationRequiresNameAndType(): void
// Verifieer dat name en type verplicht zijn

public function testValidationRejectsInvalidType(): void
// type = 'invalid_type' → validation error

public function testSystemRecipeAllowsNullUserId(): void
// is_system = true, user_id = null → valid

public function testGetStepsReturnsOrderedSteps(): void
// Maak recept met 3 stappen in willekeurige volgorde, verify ordering
```

### T2: `yii/tests/unit/models/WorkflowRunTest.php`

```php
public function testValidationRequiresUserAndRecipe(): void
// user_id en recipe_id zijn verplicht

public function testIsActiveReturnsTrueForRunningStatus(): void
// status = 'running' → isActive() = true

public function testIsActiveReturnsFalseForCompletedStatus(): void
// status = 'completed' → isActive() = false

public function testStatusMustBeInAllowedValues(): void
// status = 'invalid' → validation error
```

### T3: `yii/tests/unit/services/WorkflowRunServiceTest.php`

```php
public function testStartRunCreatesRunWithCorrectStepResults(): void
// Start een run, verifieer dat WorkflowRun + 4 WorkflowStepResult records bestaan

public function testExecuteCurrentStepCallsHandler(): void
// Mock handler, verifieer dat execute() wordt aangeroepen met juiste params

public function testAdvanceToNextStepIncreasesCurrentStep(): void
// current_step gaat van 1 naar 2

public function testSkipStepSetsStatusToSkipped(): void
// Skippable stap → status wordt 'skipped'

public function testSkipStepThrowsWhenNotSkippable(): void
// Niet-skippable stap → RuntimeException

public function testCancelRunSetsStatusToCancelled(): void
// Run status wordt 'cancelled' met completed_at
```

### T4: `yii/tests/unit/services/workflowsteps/YoutubeTranscriptHandlerTest.php`

```php
public function testExecuteReturnsContentAndMetadata(): void
// Mock YouTubeTranscriptService, verifieer output structuur

public function testExecuteThrowsWhenVideoUrlEmpty(): void
// Lege video_url → RuntimeException

public function testExecuteThrowsWhenServiceFails(): void
// YouTubeTranscriptService gooit RuntimeException → wordt doorgestuurd
```

---

## 13. Implementatievolgorde

### Fase 1: Fundament (database + modellen)

```
1. E1, E2                      — Enums aanmaken
2. M1, M2, M3, M4              — Migraties aanmaken
3. Migraties draaien            — Op yii EN yii_test schema
4. MD1, MD2, MD3, MD4, MD5, MD6 — Modellen + query classes
5. R1, R2                      — RBAC rules
6. W1                          — rbac.php wijzigen
7. RBAC re-init                — Commando: yii rbac/init
```

### Fase 2: Businesslogica (services + handlers)

```
8. H0                          — StepHandlerInterface
9. H1, H2, H4, H5             — Handlers voor YouTube workflow
10. S3                         — WorkflowStepExecutor
11. S1                         — WorkflowRecipeService
12. S2                         — WorkflowRunService
13. W3                         — DI configuratie in main.php
14. M5                         — Seed migratie (systeemrecepten)
15. Migratie M5 draaien        — Op yii EN yii_test schema
```

### Fase 3: Presentatie (controller + views)

```
16. W4                         — EntityPermissionService wijzigen
17. C1                         — WorkflowController
18. V1                         — Index view
19. V6                         — Start modal
20. V2                         — Run view (stepper)
21. V3, V4, V5                 — Stap-partials
22. W2                         — Navigatie wijzigen
```

### Fase 4: Kwaliteit (tests + validatie)

```
23. T1, T2                     — Model tests
24. T3                         — Service tests
25. T4                         — Handler tests
26. Linter draaien             — ./linter.sh fix
27. Alle tests draaien         — docker exec pma_yii vendor/bin/codecept run unit
```

### Commando's

```bash
# Migraties draaien (na stap 3 en 15)
docker exec pma_yii yii migrate --migrationNamespaces=app\\migrations --interactive=0
docker exec pma_yii yii_test migrate --migrationNamespaces=app\\migrations --interactive=0

# RBAC re-init (na stap 7)
docker exec pma_yii yii rbac/init

# Tests draaien (na stap 27)
docker exec pma_yii vendor/bin/codecept run unit

# Linter (na stap 26)
./linter.sh fix
```
