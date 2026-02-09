# Workflow Recipes — Technisch Plan

Dit plan implementeert de goedgekeurde `spec.md`. Alle bestanden volgen de bestaande patronen uit de codebase.

---

## Overzicht

| Categorie | Aantal |
|---|---|
| Nieuwe bestanden (aan te maken) | 28 |
| Bestaande bestanden (te wijzigen) | 4 |
| Migraties | 4 |
| Modellen + Query classes | 8 |
| Enums | 2 |
| Services | 3 |
| Step handlers | 6 |
| RBAC rules | 2 |
| Controller | 1 |
| Views | 6 |
| Tests | 5 |

---

## Bestanden aan te maken

### Migraties

**[M1]** `yii/migrations/m260208_000001_create_workflow_recipe_table.php`

```php
// safeUp:
$this->createTable('{{%workflow_recipe}}', [
    'id' => $this->primaryKey(),
    'user_id' => $this->integer()->null(),          // null voor systeemrecepten
    'name' => $this->string(255)->notNull(),
    'description' => $this->text()->null(),
    'type' => $this->string(64)->notNull(),          // enum: 'youtube_extractor', 'website_manager'
    'is_system' => $this->boolean()->notNull()->defaultValue(false),
    'created_at' => $this->integer()->notNull(),
    'updated_at' => $this->integer()->notNull(),
], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

$this->addForeignKey(
    'fk_workflow_recipe_user',
    '{{%workflow_recipe}}', 'user_id',
    '{{%user}}', 'id',
    'CASCADE', 'CASCADE'
);
$this->createIndex('idx_workflow_recipe_user', '{{%workflow_recipe}}', 'user_id');
$this->createIndex('idx_workflow_recipe_type', '{{%workflow_recipe}}', 'type');
```

**[M2]** `yii/migrations/m260208_000002_create_workflow_step_table.php`

```php
// safeUp:
$this->createTable('{{%workflow_step}}', [
    'id' => $this->primaryKey(),
    'recipe_id' => $this->integer()->notNull(),
    'order' => $this->integer()->notNull()->defaultValue(0),
    'step_type' => $this->string(64)->notNull(),      // enum: 'youtube_transcript', 'ai_transform', etc.
    'label' => $this->string(255)->notNull(),
    'config' => $this->text()->null(),                 // JSON configuratie
    'requires_approval' => $this->boolean()->notNull()->defaultValue(true),
    'is_skippable' => $this->boolean()->notNull()->defaultValue(false),
], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

$this->addForeignKey(
    'fk_workflow_step_recipe',
    '{{%workflow_step}}', 'recipe_id',
    '{{%workflow_recipe}}', 'id',
    'CASCADE', 'CASCADE'
);
$this->createIndex('idx_workflow_step_recipe_order', '{{%workflow_step}}', ['recipe_id', 'order']);
```

**[M3]** `yii/migrations/m260208_000003_create_workflow_run_table.php`

```php
// safeUp:
$this->createTable('{{%workflow_run}}', [
    'id' => $this->primaryKey(),
    'user_id' => $this->integer()->notNull(),
    'recipe_id' => $this->integer()->notNull(),
    'project_id' => $this->integer()->null(),
    'status' => $this->string(32)->notNull()->defaultValue('pending'),
    'current_step' => $this->integer()->notNull()->defaultValue(0),
    'created_at' => $this->integer()->notNull(),
    'updated_at' => $this->integer()->notNull(),
    'completed_at' => $this->integer()->null(),
], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

$this->addForeignKey('fk_workflow_run_user', '{{%workflow_run}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
$this->addForeignKey('fk_workflow_run_recipe', '{{%workflow_run}}', 'recipe_id', '{{%workflow_recipe}}', 'id', 'CASCADE', 'CASCADE');
$this->addForeignKey('fk_workflow_run_project', '{{%workflow_run}}', 'project_id', '{{%project}}', 'id', 'SET NULL', 'CASCADE');
$this->createIndex('idx_workflow_run_user_status', '{{%workflow_run}}', ['user_id', 'status']);
```

**[M4]** `yii/migrations/m260208_000004_create_workflow_step_result_table.php`

```php
// safeUp:
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
    'started_at' => $this->integer()->null(),
    'completed_at' => $this->integer()->null(),
], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

$this->addForeignKey('fk_wf_step_result_run', '{{%workflow_step_result}}', 'run_id', '{{%workflow_run}}', 'id', 'CASCADE', 'CASCADE');
$this->addForeignKey('fk_wf_step_result_step', '{{%workflow_step_result}}', 'step_id', '{{%workflow_step}}', 'id', 'CASCADE', 'CASCADE');
$this->addForeignKey('fk_wf_step_result_sp', '{{%workflow_step_result}}', 'scratch_pad_id', '{{%scratch_pad}}', 'id', 'SET NULL', 'CASCADE');
$this->createIndex('idx_wf_step_result_run_order', '{{%workflow_step_result}}', ['run_id', 'step_order']);
```

---

### Enums

**[E1]** `yii/common/enums/WorkflowRecipeType.php`

```php
namespace common\enums;

enum WorkflowRecipeType: string
{
    case YOUTUBE_EXTRACTOR = 'youtube_extractor';
    case WEBSITE_MANAGER = 'website_manager';

    public static function values(): array { /* ... */ }
    public static function labels(): array { /* ... */ }

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

    public function stepCount(): int
    {
        return match ($this) {
            self::YOUTUBE_EXTRACTOR => 4,
            self::WEBSITE_MANAGER => 4,
        };
    }
}
```

**[E2]** `yii/common/enums/WorkflowStepType.php`

```php
namespace common\enums;

enum WorkflowStepType: string
{
    case YOUTUBE_TRANSCRIPT = 'youtube_transcript';
    case AI_TRANSFORM = 'ai_transform';
    case PROMPT_GENERATE = 'prompt_generate';
    case SAVE_SCRATCH_PAD = 'save_scratch_pad';
    case USER_INPUT = 'user_input';
    case URL_FETCH = 'url_fetch';

    public static function values(): array { /* ... */ }
    public static function labels(): array { /* ... */ }

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

### Modellen

**[MD1]** `yii/models/WorkflowRecipe.php`

Patroon: `ScratchPad.php`

```php
namespace app\models;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $description
 * @property string $type          // WorkflowRecipeType enum value
 * @property bool $is_system
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User|null $user
 * @property WorkflowStep[] $steps
 * @property WorkflowRun[] $runs
 */
class WorkflowRecipe extends ActiveRecord
{
    use TimestampTrait;

    // tableName: 'workflow_recipe'
    // find(): WorkflowRecipeQuery
    // rules: name required, type required+in(enum values), user_id+is_system validatie
    // relations: getUser(), getSteps() orderedBy order, getRuns()
    // init(): user_id auto-set voor niet-systeem recepten
    // beforeSave(): handleTimestamps
}
```

**[MD2]** `yii/models/query/WorkflowRecipeQuery.php`

```php
namespace app\models\query;

class WorkflowRecipeQuery extends ActiveQuery
{
    public function forUser(int $userId): self           // user_id = $userId OR is_system = true
    public function onlySystem(): self                    // is_system = true
    public function onlyCustom(int $userId): self         // is_system = false AND user_id = $userId
    public function withType(string $type): self          // type = $type
    public function orderedByName(): self                 // ORDER BY name ASC
}
```

**[MD3]** `yii/models/WorkflowStep.php`

```php
namespace app\models;

/**
 * @property int $id
 * @property int $recipe_id
 * @property int $order
 * @property string $step_type      // WorkflowStepType enum value
 * @property string $label
 * @property string|null $config    // JSON
 * @property bool $requires_approval
 * @property bool $is_skippable
 *
 * @property WorkflowRecipe $recipe
 */
class WorkflowStep extends ActiveRecord
{
    // tableName: 'workflow_step'
    // rules: recipe_id+order+step_type+label required, step_type in enum values, config string
    // relations: getRecipe()
    // getDecodedConfig(): array — json_decode van config
}
```

**[MD4]** `yii/models/WorkflowRun.php`

Patroon: `ScratchPad.php` (user_id, project_id nullable, timestamps)

```php
namespace app\models;

/**
 * @property int $id
 * @property int $user_id
 * @property int $recipe_id
 * @property int|null $project_id
 * @property string $status          // pending, running, paused, completed, failed, cancelled
 * @property int $current_step       // huidige stap-order
 * @property int $created_at
 * @property int $updated_at
 * @property int|null $completed_at
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

    // tableName: 'workflow_run'
    // find(): WorkflowRunQuery
    // rules: user_id+recipe_id required, status in STATUSES, project_id FK exist
    // relations: getUser(), getRecipe(), getProject(), getStepResults() orderedBy step_order
    // init(): user_id auto-set
    // beforeSave(): handleTimestamps

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_PAUSED]);
    }

    public function getCurrentStepResult(): ?WorkflowStepResult
    {
        // Haal het stepResult op voor current_step
    }
}
```

**[MD5]** `yii/models/query/WorkflowRunQuery.php`

```php
namespace app\models\query;

class WorkflowRunQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    public function forProject(?int $projectId): self
    public function active(): self                        // status IN (pending, running, paused)
    public function completed(): self                     // status = completed
    public function orderedByUpdated(): self               // ORDER BY updated_at DESC
    public function forRecipe(int $recipeId): self
    public function withStatus(string $status): self
}
```

**[MD6]** `yii/models/WorkflowStepResult.php`

```php
namespace app\models;

/**
 * @property int $id
 * @property int $run_id
 * @property int $step_id
 * @property int $step_order
 * @property string $status           // pending, running, completed, failed, skipped
 * @property string|null $input_data  // JSON
 * @property string|null $output_data // JSON
 * @property string|null $error_message
 * @property int|null $scratch_pad_id
 * @property int|null $started_at
 * @property int|null $completed_at
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

    // tableName: 'workflow_step_result'
    // rules: run_id+step_id+step_order required, status in statuses
    // relations: getRun(), getStep(), getScratchPad()

    public function getDecodedInputData(): array   // json_decode input_data
    public function getDecodedOutputData(): array  // json_decode output_data
}
```

---

### RBAC

**[R1]** `yii/rbac/WorkflowRecipeOwnerRule.php`

Patroon: `ScratchPadOwnerRule.php`

```php
namespace app\rbac;

class WorkflowRecipeOwnerRule extends Rule
{
    public $name = 'isWorkflowRecipeOwner';

    public function execute($user, $item, $params): bool
    {
        // Systeem-recepten (is_system=true) zijn altijd leesbaar
        if (isset($params['model']->is_system) && $params['model']->is_system)
            return true;
        if (isset($params['model']->user_id))
            return $params['model']->user_id == $user;
        return false;
    }
}
```

**[R2]** `yii/rbac/WorkflowRunOwnerRule.php`

```php
namespace app\rbac;

class WorkflowRunOwnerRule extends Rule
{
    public $name = 'isWorkflowRunOwner';

    public function execute($user, $item, $params): bool
    {
        if (isset($params['model']->user_id))
            return $params['model']->user_id == $user;
        return false;
    }
}
```

---

### Services

**[S1]** `yii/services/WorkflowRecipeService.php`

Verantwoordelijkheid: Recepten ophalen, systeemrecepten seeden, recept-configuratie valideren.

```php
namespace app\services;

readonly class WorkflowRecipeService
{
    public function __construct() {}

    /**
     * Haal alle beschikbare recepten op voor een gebruiker (systeem + eigen).
     * @return WorkflowRecipe[]
     */
    public function fetchRecipesForUser(int $userId): array

    /**
     * Haal een recept op met zijn stappen.
     * @throws NotFoundHttpException
     */
    public function findRecipeWithSteps(int $recipeId, int $userId): WorkflowRecipe

    /**
     * Seed systeemrecepten als ze niet bestaan. Idempotent.
     */
    public function seedSystemRecipes(): void
}
```

`seedSystemRecipes()` maakt de twee vaste recepten met hun stappen aan via `WorkflowRecipe::find()->onlySystem()->count()` check. Dit wordt aangeroepen vanuit een migratie of command.

**[S2]** `yii/services/WorkflowRunService.php`

Verantwoordelijkheid: Run-lifecycle beheer (starten, uitvoeren, pauzeren, hervatten, annuleren).

```php
namespace app\services;

readonly class WorkflowRunService
{
    public function __construct(
        private WorkflowStepExecutor $stepExecutor,
    ) {}

    /**
     * Start een nieuwe workflow-run voor een recept.
     * Maakt WorkflowRun + WorkflowStepResult records aan.
     * @throws RuntimeException
     */
    public function startRun(int $recipeId, int $userId, ?int $projectId): WorkflowRun

    /**
     * Haal run op met ownership-check.
     * @throws NotFoundHttpException
     */
    public function findRunWithOwner(int $runId, int $userId): WorkflowRun

    /**
     * Voer de volgende stap uit (of de huidige als die gepauseerd is).
     * Geeft het bijgewerkte WorkflowStepResult terug.
     */
    public function executeCurrentStep(WorkflowRun $run, array $userInput = []): WorkflowStepResult

    /**
     * Verplaats naar de volgende stap na goedkeuring.
     * Update current_step en run-status.
     */
    public function advanceToNextStep(WorkflowRun $run, ?string $editedOutputData = null): WorkflowRun

    /**
     * Sla een stap over.
     */
    public function skipCurrentStep(WorkflowRun $run): WorkflowRun

    /**
     * Pauzeer de run.
     */
    public function pauseRun(WorkflowRun $run): WorkflowRun

    /**
     * Annuleer de run.
     */
    public function cancelRun(WorkflowRun $run): WorkflowRun

    /**
     * Haal alle runs op voor een gebruiker, optioneel gefilterd.
     */
    public function fetchRunsForUser(int $userId, ?string $status = null): array

    /**
     * Interne helper: maak StepResult records aan voor elke stap in het recept.
     */
    private function initializeStepResults(WorkflowRun $run, array $steps): void
}
```

**[S3]** `yii/services/WorkflowStepExecutor.php`

Verantwoordelijkheid: Delegeert stap-uitvoering naar het juiste handler-object op basis van `step_type`.

```php
namespace app\services;

use app\services\workflowsteps\StepHandlerInterface;

readonly class WorkflowStepExecutor
{
    /**
     * @param StepHandlerInterface[] $handlers — geïnjecteerd via DI, key = step_type
     */
    public function __construct(
        private array $handlers,
    ) {}

    /**
     * Voer een stap uit.
     * @throws RuntimeException als handler niet gevonden
     */
    public function execute(WorkflowStepResult $stepResult, WorkflowRun $run, array $userInput = []): WorkflowStepResult
    {
        $stepType = $stepResult->step->step_type;
        $handler = $this->handlers[$stepType]
            ?? throw new RuntimeException("Geen handler voor step type: {$stepType}");

        $stepResult->status = WorkflowStepResult::STATUS_RUNNING;
        $stepResult->started_at = time();
        $stepResult->save(false);

        try {
            $inputData = $this->resolveInputData($stepResult, $run, $userInput);
            $stepResult->input_data = json_encode($inputData, JSON_UNESCAPED_UNICODE);

            $outputData = $handler->execute($inputData, $stepResult->step->getDecodedConfig(), $run);

            $stepResult->output_data = json_encode($outputData, JSON_UNESCAPED_UNICODE);
            $stepResult->status = WorkflowStepResult::STATUS_COMPLETED;
            $stepResult->completed_at = time();
        } catch (Throwable $e) {
            $stepResult->status = WorkflowStepResult::STATUS_FAILED;
            $stepResult->error_message = $e->getMessage();
            $stepResult->completed_at = time();
            Yii::error("Workflow step failed: {$e->getMessage()}", 'workflow');
        }

        $stepResult->save(false);
        return $stepResult;
    }

    /**
     * Bepaal input_data: via content_source referentie of user input.
     */
    private function resolveInputData(WorkflowStepResult $stepResult, WorkflowRun $run, array $userInput): array
}
```

---

### Step Handlers

Alle handlers implementeren één interface en worden in een subdirectory geplaatst.

**[H0]** `yii/services/workflowsteps/StepHandlerInterface.php`

```php
namespace app\services\workflowsteps;

use app\models\WorkflowRun;

interface StepHandlerInterface
{
    /**
     * @param array $inputData   Opgeloste input voor deze stap
     * @param array $config      Stap-configuratie uit WorkflowStep.config
     * @param WorkflowRun $run   De huidige workflow run
     * @return array             Output data (wordt JSON-encoded opgeslagen)
     * @throws RuntimeException  Bij falen
     */
    public function execute(array $inputData, array $config, WorkflowRun $run): array;
}
```

**[H1]** `yii/services/workflowsteps/YoutubeTranscriptHandler.php`

```php
namespace app\services\workflowsteps;

readonly class YoutubeTranscriptHandler implements StepHandlerInterface
{
    public function __construct(
        private YouTubeTranscriptService $youtubeService,
    ) {}

    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        // $inputData['video_url'] komt van user input
        $videoId = $this->youtubeService->extractVideoId($inputData['video_url']);
        $transcriptData = $this->youtubeService->fetchTranscript($videoId);
        $deltaContent = $this->youtubeService->convertToQuillDelta($transcriptData);
        $title = $this->youtubeService->getTitle($transcriptData);

        return [
            'content' => $deltaContent,    // Quill Delta JSON string
            'title' => $title,
            'video_id' => $transcriptData['video_id'] ?? $videoId,
            'channel' => $transcriptData['channel'] ?? '',
        ];
    }
}
```

**[H2]** `yii/services/workflowsteps/AiTransformHandler.php`

```php
namespace app\services\workflowsteps;

readonly class AiTransformHandler implements StepHandlerInterface
{
    public function __construct(
        private ClaudeCliService $claudeCliService,
        private CopyFormatConverter $copyFormatConverter,
    ) {}

    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        // $inputData['content'] = Quill Delta JSON van vorige stap
        // $inputData['instruction'] = gebruikersinstructie (vrij tekstveld)
        // $inputData['language'] = doeltaal (optioneel)
        // $inputData['action'] = 'translate'|'summarize'|'custom' (optioneel)
        // $config['model'] = Claude model (default: sonnet)

        $markdown = $this->copyFormatConverter->convertFromQuillDelta(
            $inputData['content'],
            CopyType::MD
        );

        $systemPrompt = $this->buildSystemPrompt($inputData, $config);
        $prompt = $systemPrompt . "\n\n---\n\n" . $markdown;

        $result = $this->claudeCliService->execute(
            prompt: $prompt,
            workingDirectory: null,
            timeout: $config['timeout'] ?? 300,
            options: ['model' => $config['model'] ?? 'sonnet'],
            project: $run->project,
        );

        if (!$result['success'])
            throw new RuntimeException($result['error'] ?? 'AI verwerking mislukt');

        // Converteer markdown output naar Quill Delta
        $outputDelta = $this->convertOutputToDelta($result['output']);

        return [
            'content' => $outputDelta,
            'raw_output' => $result['output'],
            'model' => $result['model'] ?? '',
            'duration_ms' => $result['duration_ms'] ?? 0,
        ];
    }

    private function buildSystemPrompt(array $inputData, array $config): string
    private function convertOutputToDelta(string $markdown): string
}
```

**[H3]** `yii/services/workflowsteps/PromptGenerateHandler.php`

```php
namespace app\services\workflowsteps;

readonly class PromptGenerateHandler implements StepHandlerInterface
{
    public function __construct(
        private PromptGenerationService $promptGenerationService,
        private ContextService $contextService,
    ) {}

    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        // $config['template_id'], $config['context_ids'], $config['field_values']
        // Of: $inputData bevat deze waarden als ze van een user_input stap komen

        $templateId = $inputData['template_id'] ?? $config['template_id'];
        $contextIds = $inputData['context_ids'] ?? $config['context_ids'] ?? [];
        $fieldValues = $inputData['field_values'] ?? $config['field_values'] ?? [];

        $userId = $run->user_id;
        $contextContents = $this->contextService->fetchContextsContentById($userId, $contextIds);
        $deltaJson = $this->promptGenerationService->generateFinalPrompt(
            $templateId, $contextContents, $fieldValues, $userId
        );

        return ['content' => $deltaJson];
    }
}
```

**[H4]** `yii/services/workflowsteps/SaveScratchPadHandler.php`

```php
namespace app\services\workflowsteps;

readonly class SaveScratchPadHandler implements StepHandlerInterface
{
    public function __construct() {}

    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        // $inputData['content'] = Quill Delta JSON
        // $inputData['title'] of $config['name'] = naam
        // $run->project_id = project scope

        $name = $inputData['title']
            ?? $inputData['name']
            ?? $config['name']
            ?? 'Workflow result - ' . date('Y-m-d H:i');

        $scratchPad = new ScratchPad();
        $scratchPad->user_id = $run->user_id;
        $scratchPad->project_id = $run->project_id;
        $scratchPad->name = mb_substr($name, 0, 255);
        $scratchPad->content = $inputData['content'];

        if (!$scratchPad->save())
            throw new RuntimeException('ScratchPad opslaan mislukt: ' . implode(', ', $scratchPad->getFirstErrors()));

        return [
            'scratch_pad_id' => $scratchPad->id,
            'name' => $scratchPad->name,
        ];
    }
}
```

**[H5]** `yii/services/workflowsteps/UserInputHandler.php`

```php
namespace app\services\workflowsteps;

readonly class UserInputHandler implements StepHandlerInterface
{
    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        // Deze handler retourneert de user input als output.
        // De controller vangt het 'user_input' scenario af en pauzeert de run.
        // Wanneer de gebruiker input levert, wordt die hier doorgesluisd.
        return $inputData;
    }
}
```

**[H6]** `yii/services/workflowsteps/UrlFetchHandler.php`

```php
namespace app\services\workflowsteps;

readonly class UrlFetchHandler implements StepHandlerInterface
{
    public function __construct(
        private CopyFormatConverter $copyFormatConverter,
    ) {}

    public function execute(array $inputData, array $config, WorkflowRun $run): array
    {
        // $inputData['url'] komt van user input
        $url = $inputData['url'];

        // Valideer URL
        if (!filter_var($url, FILTER_VALIDATE_URL))
            throw new RuntimeException('Ongeldige URL: ' . $url);

        $html = $this->fetchUrl($url);
        $textContent = $this->extractTextFromHtml($html);
        $deltaContent = $this->convertToDelta($textContent);

        return [
            'content' => $deltaContent,
            'url' => $url,
            'title' => $this->extractTitle($html),
        ];
    }

    private function fetchUrl(string $url): string
    {
        // Gebruik file_get_contents met stream context (timeout, user-agent)
        // Gooi RuntimeException bij falen
    }

    private function extractTextFromHtml(string $html): string
    private function extractTitle(string $html): string
    private function convertToDelta(string $text): string
}
```

---

### Controller

**[C1]** `yii/controllers/WorkflowController.php`

Patroon: `ScratchPadController.php`

```php
namespace app\controllers;

class WorkflowController extends Controller
{
    private readonly EntityPermissionService $permissionService;
    private readonly WorkflowRecipeService $recipeService;
    private readonly WorkflowRunService $runService;
    private array $actionPermissionMap;

    public function __construct(
        $id, $module,
        EntityPermissionService $permissionService,
        WorkflowRecipeService $recipeService,
        WorkflowRunService $runService,
        $config = []
    ) { /* ... */ }

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
                            return $this->permissionService->hasActionPermission('workflowRun', $action->id, $callback);
                        },
                    ],
                ],
            ],
        ];
    }
```

**Acties:**

| Actie | Method | Beschrijving |
|---|---|---|
| `actionIndex` | GET | Overzicht: recepten + lopende/afgeronde runs |
| `actionStart` | POST/AJAX | Start nieuwe run: `recipe_id`, `project_id` → redirect naar run |
| `actionRun` | GET | Toon run-stepper view |
| `actionExecuteStep` | POST/AJAX | Voer huidige stap uit met user input |
| `actionAdvanceStep` | POST/AJAX | Ga door naar volgende stap (na goedkeuring) |
| `actionSkipStep` | POST/AJAX | Sla huidige stap over |
| `actionCancel` | POST/AJAX | Annuleer run |
| `actionDelete` | POST | Verwijder run |
| `actionStreamStep` | POST/SSE | Stream AI-stap output (voor `ai_transform` stappen) |

**`actionIndex` detail:**

```php
public function actionIndex(): string
{
    $userId = Yii::$app->user->id;
    $recipes = $this->recipeService->fetchRecipesForUser($userId);
    $activeRuns = $this->runService->fetchRunsForUser($userId, 'active');
    $completedRuns = $this->runService->fetchRunsForUser($userId, 'completed');

    return $this->render('index', [
        'recipes' => $recipes,
        'activeRuns' => $activeRuns,
        'completedRuns' => $completedRuns,
    ]);
}
```

**`actionRun` detail:**

```php
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
```

**`actionExecuteStep` detail:**

```php
public function actionExecuteStep(int $id): array
{
    Yii::$app->response->format = Response::FORMAT_JSON;
    $run = $this->findRunModel($id);
    $userInput = Yii::$app->request->post('input', []);

    $stepResult = $this->runService->executeCurrentStep($run, $userInput);

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
}
```

**`actionStreamStep` detail:**

```php
public function actionStreamStep(int $id): void
{
    // Patroon van ScratchPadController::actionStreamClaude
    // SSE headers, stream Claude CLI output, sluit met [DONE]
    // Alleen voor ai_transform stappen
}
```

---

### Views

**[V1]** `yii/views/workflow/index.php` — Overzichtspagina

```
Layout:
┌─────────────────────────────────────────────┐
│ h1: Workflows                               │
├─────────────────────────────────────────────┤
│ Sectie: Beschikbare Workflows               │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐     │
│ │ YouTube  │ │ Website  │ │ (future) │     │
│ │ Extractor│ │ Manager  │ │          │     │
│ │ [icon]   │ │ [icon]   │ │          │     │
│ │ 4 steps  │ │ 4 steps  │ │          │     │
│ │ [Start]  │ │ [Start]  │ │          │     │
│ └──────────┘ └──────────┘ └──────────┘     │
├─────────────────────────────────────────────┤
│ Sectie: Lopende Workflows (als er zijn)     │
│ GridView: naam, recept, status, stap, datum │
├─────────────────────────────────────────────┤
│ Sectie: Afgeronde Workflows                 │
│ GridView: naam, recept, status, datum       │
└─────────────────────────────────────────────┘
```

- Recept-kaarten: `card` met `card-body` met `WorkflowRecipeType->icon()`, naam, beschrijving, stapcount
- Start-knop opent modal met project-selectie, POST naar `/workflow/start`
- GridViews volgen patroon van `scratch-pad/index.php` (table-striped, row-click)

**[V2]** `yii/views/workflow/run.php` — Workflow runner (stepper)

```
Layout:
┌─────────────────────────────────────────────┐
│ h1: {recept.naam}                [Annuleer] │
│ Project: {project.naam of 'Geen'}           │
│ Status: {run.status badge}                  │
├─────────────────────────────────────────────┤
│ Verticale stepper/accordion                 │
│                                             │
│ ● Stap 1: {label}                     [✓]  │
│   accordion-body: output preview            │
│                                             │
│ ◉ Stap 2: {label}                     [🔄] │
│   accordion-body:                           │
│   - Input section (afhankelijk van type)    │
│   - Output preview (na uitvoering)          │
│   - Action buttons                          │
│                                             │
│ ○ Stap 3: {label}                     [⏳] │
│   accordion-body: "Wacht op vorige stap"    │
│                                             │
│ ○ Stap 4: {label}                     [⏳] │
│   accordion-body: "Wacht op vorige stap"    │
└─────────────────────────────────────────────┘
```

- Accordion patroon van `prompt-instance/_form.php`
- Bootstrap accordion met `data-bs-parent` voor single-open
- Status badges: `badge bg-secondary` (pending), `bg-primary` (running), `bg-success` (completed), `bg-danger` (failed), `bg-warning` (skipped)
- Voltooide stappen: ingeklapt, met `QuillViewerWidget` voor output preview (truncated)
- Actieve stap: uitgeklapt, met stap-specifiek formulier

**[V3]** `yii/views/workflow/_step_youtube_transcript.php` — YouTube stap-partial

```
┌───────────────────────────────────┐
│ YouTube Video URL                 │
│ [________________________]        │
│ Ondersteunde formaten: ...        │
│                                   │
│ [Uitvoeren]  [Overslaan]          │
└───────────────────────────────────┘
```

Na uitvoering:
```
┌───────────────────────────────────┐
│ ✓ Transcript opgehaald            │
│ Video: {title}                    │
│ Kanaal: {channel}                 │
│ ┌─────────────────────────────┐  │
│ │ QuillViewerWidget (preview) │  │
│ └─────────────────────────────┘  │
│                                   │
│ [Doorgaan]  [Overslaan]  [Stop]   │
└───────────────────────────────────┘
```

**[V4]** `yii/views/workflow/_step_ai_transform.php` — AI Transform stap-partial

```
┌───────────────────────────────────┐
│ Input van vorige stap:            │
│ ┌─────────────────────────────┐  │
│ │ QuillViewerWidget (preview) │  │
│ └─────────────────────────────┘  │
│                                   │
│ Actie:  [Vertalen ▼]             │
│ Taal:   [Nederlands ▼]           │
│ Instructie: [________________]    │
│                                   │
│ [Uitvoeren]  [Overslaan]          │
└───────────────────────────────────┘
```

Na uitvoering: toont output in QuillViewerWidget, met Doorgaan/Overslaan/Stop knoppen.

**[V5]** `yii/views/workflow/_step_user_input.php` — Gebruikersinvoer stap-partial

Toont configureerbare formuliervelden op basis van `step.config.prompt` en `step.config.options`.

**[V6]** `yii/views/workflow/_start_modal.php` — Start workflow modal

```
Modal:
┌───────────────────────────────────┐
│ Start Workflow: {recept.naam}     │
├───────────────────────────────────┤
│ Project (optioneel):              │
│ [________________________ ▼]      │
│                                   │
│          [Annuleer] [Start]       │
└───────────────────────────────────┘
```

Patroon: `_youtube-import-modal.php` — form in modal, AJAX submit, redirect op succes.

---

## Bestaande bestanden te wijzigen

### [W1] `yii/config/rbac.php`

**Wijziging:** Voeg `workflowRun` entity toe aan `entities` array en permissions aan `roles.user.permissions`.

```php
// Toe te voegen aan entities:
'workflowRun' => [
    'actionPermissionMap' => [
        'run' => 'viewWorkflowRun',
        'execute-step' => 'viewWorkflowRun',
        'advance-step' => 'viewWorkflowRun',
        'skip-step' => 'viewWorkflowRun',
        'stream-step' => 'viewWorkflowRun',
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

// Toe te voegen aan roles.user.permissions:
'createWorkflowRun',
'viewWorkflowRun',
'updateWorkflowRun',
'deleteWorkflowRun',
```

### [W2] `yii/views/layouts/main.php`

**Wijziging:** Voeg "Workflows" toe als nav-item, na "Scratch Pads".

```php
// Na het 'Scratch Pads' item (regel ~58-61):
[
    'label' => 'Workflows',
    'url' => ['/workflow/index'],
    'options' => ['id' => 'nav-workflows'],
],
```

### [W3] `yii/config/web.php`

**Wijziging:** Registreer DI-configuratie voor `WorkflowStepExecutor` met handlers-array.

```php
// In container.definitions:
\app\services\WorkflowStepExecutor::class => function ($container) {
    return new \app\services\WorkflowStepExecutor([
        'youtube_transcript' => $container->get(\app\services\workflowsteps\YoutubeTranscriptHandler::class),
        'ai_transform' => $container->get(\app\services\workflowsteps\AiTransformHandler::class),
        'prompt_generate' => $container->get(\app\services\workflowsteps\PromptGenerateHandler::class),
        'save_scratch_pad' => $container->get(\app\services\workflowsteps\SaveScratchPadHandler::class),
        'user_input' => $container->get(\app\services\workflowsteps\UserInputHandler::class),
        'url_fetch' => $container->get(\app\services\workflowsteps\UrlFetchHandler::class),
    ]);
},
```

### [W4] `yii/services/EntityPermissionService.php`

**Wijziging:** Voeg workflow-acties toe aan `MODEL_BASED_ACTIONS` array.

```php
// Toe te voegen aan MODEL_BASED_ACTIONS:
'run', 'execute-step', 'advance-step', 'skip-step', 'stream-step', 'cancel', 'delete'
```

**Let op:** Alleen toevoegen als deze nog niet in de array staan. 'delete' staat er waarschijnlijk al — controleer.

---

## Wat verandert NIET

| Bestand/onderdeel | Reden |
|---|---|
| `ScratchPad` model | Geen schemawijzigingen. Handlers maken ScratchPads aan via het bestaande model. |
| `ScratchPadController` | YouTube import blijft werken als zelfstandige functie naast workflows. |
| `YouTubeTranscriptService` | Wordt hergebruikt door handler, niet gewijzigd. |
| `ClaudeCliService` | Wordt hergebruikt door handler, niet gewijzigd. |
| `PromptGenerationService` | Wordt hergebruikt door handler, niet gewijzigd. |
| `CopyFormatConverter` | Wordt hergebruikt door handlers, niet gewijzigd. |
| `ContextService` | Wordt hergebruikt, niet gewijzigd. |
| Alle bestaande views | Geen wijzigingen aan bestaande pagina's. |
| Bestaande migraties | Worden niet gewijzigd. |
| RBAC owner rules (bestaande) | Worden niet gewijzigd. |

---

## Implementatiestappen (geordend)

### Fase A: Database & Modellen

| # | Actie | Bestanden | Afhankelijk van |
|---|---|---|---|
| A1 | Maak enums aan | [E1], [E2] | — |
| A2 | Maak migraties aan | [M1], [M2], [M3], [M4] | — |
| A3 | Draai migraties op beide schemas | — | A2 |
| A4 | Maak modellen aan | [MD1], [MD2], [MD3], [MD4], [MD5], [MD6] | A2, A1 |
| A5 | Maak RBAC rules aan | [R1], [R2] | — |
| A6 | Wijzig rbac.php | [W1] | A5 |
| A7 | Draai RBAC seeder (als nodig) | — | A6 |

### Fase B: Services & Handlers

| # | Actie | Bestanden | Afhankelijk van |
|---|---|---|---|
| B1 | Maak StepHandlerInterface aan | [H0] | — |
| B2 | Maak step handlers aan | [H1]-[H6] | B1, A4 |
| B3 | Maak WorkflowStepExecutor aan | [S3] | B1, B2 |
| B4 | Maak WorkflowRecipeService aan | [S1] | A4 |
| B5 | Maak WorkflowRunService aan | [S2] | A4, B3 |
| B6 | Registreer DI configuratie | [W3] | B3 |
| B7 | Seed systeemrecepten (via migration of command) | — | B4, A3 |

### Fase C: Controller & RBAC

| # | Actie | Bestanden | Afhankelijk van |
|---|---|---|---|
| C1 | Wijzig EntityPermissionService | [W4] | — |
| C2 | Maak WorkflowController aan | [C1] | B4, B5 |

### Fase D: Views & Navigatie

| # | Actie | Bestanden | Afhankelijk van |
|---|---|---|---|
| D1 | Maak index view aan | [V1] | C2 |
| D2 | Maak run view aan | [V2] | C2 |
| D3 | Maak stap-partials aan | [V3], [V4], [V5] | D2 |
| D4 | Maak start modal aan | [V6] | D1 |
| D5 | Voeg nav-item toe | [W2] | — |

### Fase E: Tests

| # | Actie | Bestanden | Afhankelijk van |
|---|---|---|---|
| E1 | Model tests | Zie testplan | A4 |
| E2 | Service tests | Zie testplan | B4, B5 |
| E3 | Handler tests | Zie testplan | B2 |

---

## Testplan

### Unit Tests

**[T1]** `yii/tests/unit/models/WorkflowRecipeTest.php`

```php
public function testValidationWithRequiredFields(): void      // name+type required
public function testValidationWithInvalidType(): void         // ongeldige type waarde
public function testSystemRecipeAllowsNullUserId(): void      // is_system=true, user_id=null
public function testGetStepsReturnsOrderedSteps(): void       // relatie met ordering
```

**[T2]** `yii/tests/unit/models/WorkflowRunTest.php`

```php
public function testValidationWithRequiredFields(): void      // user_id+recipe_id required
public function testIsActiveReturnsTrueForPendingStatus(): void
public function testIsActiveReturnsFalseForCompletedStatus(): void
public function testGetCurrentStepResultReturnsCorrectStep(): void
```

**[T3]** `yii/tests/unit/services/WorkflowRecipeServiceTest.php`

```php
public function testFetchRecipesForUserIncludesSystemRecipes(): void
public function testSeedSystemRecipesCreatesRecipesIdempotently(): void
```

**[T4]** `yii/tests/unit/services/WorkflowRunServiceTest.php`

```php
public function testStartRunCreatesRunWithStepResults(): void
public function testExecuteCurrentStepDelegatesToHandler(): void
public function testAdvanceToNextStepUpdatesCurrentStep(): void
public function testSkipCurrentStepSetsStatusToSkipped(): void
public function testCancelRunSetsStatusToCancelled(): void
public function testStartRunThrowsWhenRecipeNotFound(): void
```

**[T5]** `yii/tests/unit/services/workflowsteps/YoutubeTranscriptHandlerTest.php`

```php
public function testExecuteCallsYoutubeServiceAndReturnsContent(): void
public function testExecuteThrowsWhenVideoUrlMissing(): void
```

---

## Dependencies

| Type | Wijziging | Toelichting |
|---|---|---|
| Composer | Geen | Geen nieuwe packages nodig |
| npm | Geen | Geen nieuwe frontend dependencies |
| PHP extensies | Geen | `json`, `mbstring` al beschikbaar |
| Externe tools | Geen nieuwe | `ytx.py` al aanwezig, Claude CLI al geïntegreerd |

---

## Risico's en mitigaties

| Risico | Impact | Mitigatie |
|---|---|---|
| Claude CLI timeout bij lange AI-stappen | Stap faalt, gebruiker verliest wachttijd | Configureerbare timeout per stap (default 300s). Duidelijke foutmelding met retry-optie. |
| URL-fetch geblokkeerd door remote server | `url_fetch` stap faalt | User-agent header, timeout, duidelijke foutmelding. Gebruiker kan alternatieve URL proberen. |
| Grote transcript/pagina content overschrijdt LONGTEXT | Onwaarschijnlijk maar mogelijk | LONGTEXT kan ~4GB. Voeg maxlength-check toe in handlers. |
| Race condition bij gelijktijdige stap-uitvoering | Twee tabs voeren dezelfde stap uit | Check `stepResult.status` vóór uitvoering. Als al `running` → weiger met melding. |
| Systeemrecepten bijwerken na seed | Nieuwe versie conflicteert met bestaande runs | Recepten zijn immutable zodra een run eraan gekoppeld is. Nieuwe versies krijgen een nieuwe record. |
| RBAC-migratie vereist re-seed | Nieuwe permissions niet beschikbaar | RBAC re-init commando documenteren in migratienotes. |
| SSE streaming voor AI-stappen complex | Dupliceert bestaande ScratchPad-logica | Hergebruik het `actionStreamClaude`-patroon zo direct mogelijk. Eventueel extracten naar gedeelde helper. |
