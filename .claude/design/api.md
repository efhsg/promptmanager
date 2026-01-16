# PromptManager: ScratchPad API

## Overview
Add token-authenticated REST API for external tools to create scratch_pads.

---

## Prerequisites (Already in Place)

| Requirement | Status | Location |
|-------------|--------|----------|
| `access_token` column on user table | ✓ Exists | `m241207_200942_create_user_table.php:24` |
| `findIdentityByAccessToken()` method | ✓ Exists | `User.php:49-52` |

---

## 1. Migration: Add Token Hash and Expiry

**File:** `yii/migrations/m260116_000001_add_access_token_security.php`

The existing `access_token` column stores plaintext. Add secure token storage:

```php
<?php

namespace app\migrations;

use yii\db\Migration;

class m260116_000001_add_access_token_security extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%user}}', 'access_token_hash', $this->string(255)->null());
        $this->addColumn('{{%user}}', 'access_token_expires_at', $this->integer()->null());

        // Invalidate all existing plaintext tokens (users must regenerate)
        $this->update('{{%user}}', ['access_token' => null]);
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%user}}', 'access_token_hash');
        $this->dropColumn('{{%user}}', 'access_token_expires_at');
    }
}
```

---

## 2. Update User Model

**File:** `yii/modules/identity/models/User.php`

Update `findIdentityByAccessToken()` to use hashed token with expiry check:

```php
public static function findIdentityByAccessToken($token, $type = null): ?self
{
    $hash = hash('sha256', $token);
    return static::find()
        ->active()
        ->andWhere(['access_token_hash' => $hash])
        ->andWhere(['or',
            ['access_token_expires_at' => null],
            ['>', 'access_token_expires_at', time()]
        ])
        ->one();
}
```

---

## 3. Add Token Methods to UserService

**File:** `yii/modules/identity/services/UserService.php`

```php
private const TOKEN_EXPIRY_DAYS = 90;

/**
 * Generates a new API access token.
 * Returns plaintext token (shown once), stores hash in DB.
 *
 * @throws \yii\base\Exception
 * @throws \RuntimeException if token save fails
 */
public function generateAccessToken(User $user, ?int $expiryDays = null): string
{
    $token = Yii::$app->security->generateRandomString(64);
    $hash = hash('sha256', $token);
    $expiryDays = $expiryDays ?? self::TOKEN_EXPIRY_DAYS;

    $user->access_token_hash = $hash;
    $user->access_token_expires_at = time() + ($expiryDays * 86400);

    if (!$user->save(false, ['access_token_hash', 'access_token_expires_at'])) {
        throw new \RuntimeException('Failed to save access token');
    }

    return $token;
}

/**
 * Rotates the access token (generates new, invalidates old).
 *
 * @throws \yii\base\Exception
 * @throws \RuntimeException if token save fails
 */
public function rotateAccessToken(User $user, ?int $expiryDays = null): string
{
    return $this->generateAccessToken($user, $expiryDays);
}

/**
 * @throws \RuntimeException if token revoke fails
 */
public function revokeAccessToken(User $user): void
{
    $user->access_token_hash = null;
    $user->access_token_expires_at = null;

    if (!$user->save(false, ['access_token_hash', 'access_token_expires_at'])) {
        throw new \RuntimeException('Failed to revoke access token');
    }
}

public function isAccessTokenExpired(User $user): bool
{
    if ($user->access_token_expires_at === null) {
        return false;
    }
    return $user->access_token_expires_at < time();
}
```

---

## 4. Add Console Commands

**File:** `yii/commands/UserController.php`

Add constructor and actions:

```php
public function __construct(
    $id,
    $module,
    private readonly UserService $userService = new UserService(),
    $config = []
) {
    parent::__construct($id, $module, $config);
}

/**
 * Generates an API access token for a user.
 *
 * Usage: yii user/generate-token <user_id> [expiry_days]
 */
public function actionGenerateToken(int $userId, int $expiryDays = 90): int
{
    $user = User::findOne($userId);
    if (!$user) {
        $this->stderr("User with ID $userId not found.\n");
        return ExitCode::DATAERR;
    }

    $token = $this->userService->generateAccessToken($user, $expiryDays);
    $expiresAt = date('Y-m-d H:i:s', $user->access_token_expires_at);

    $this->stdout("Access token for user '{$user->username}':\n");
    $this->stdout("$token\n\n");
    $this->stdout("Expires: $expiresAt\n");
    $this->stdout("IMPORTANT: Store this token securely. It cannot be retrieved again.\n");
    return ExitCode::OK;
}

/**
 * Rotates the API access token for a user (invalidates old, creates new).
 *
 * Usage: yii user/rotate-token <user_id> [expiry_days]
 */
public function actionRotateToken(int $userId, int $expiryDays = 90): int
{
    $user = User::findOne($userId);
    if (!$user) {
        $this->stderr("User with ID $userId not found.\n");
        return ExitCode::DATAERR;
    }

    $token = $this->userService->rotateAccessToken($user, $expiryDays);
    $expiresAt = date('Y-m-d H:i:s', $user->access_token_expires_at);

    $this->stdout("New access token for user '{$user->username}':\n");
    $this->stdout("$token\n\n");
    $this->stdout("Expires: $expiresAt\n");
    $this->stdout("Previous token has been invalidated.\n");
    return ExitCode::OK;
}

/**
 * Revokes the API access token for a user.
 *
 * Usage: yii user/revoke-token <user_id>
 */
public function actionRevokeToken(int $userId): int
{
    $user = User::findOne($userId);
    if (!$user) {
        $this->stderr("User with ID $userId not found.\n");
        return ExitCode::DATAERR;
    }

    try {
        $this->userService->revokeAccessToken($user);
        $this->stdout("Access token revoked for user '{$user->username}'.\n");
        return ExitCode::OK;
    } catch (\RuntimeException $e) {
        $this->stderr("Failed to revoke access token: {$e->getMessage()}\n");
        return ExitCode::UNSPECIFIED_ERROR;
    }
}
```

Required imports:
```php
use app\modules\identity\models\User;
use app\modules\identity\services\UserService;
use yii\console\ExitCode;
```

---

## 5. Create API Controller

**File:** `yii/controllers/api/ScratchPadController.php`

```php
<?php

namespace app\controllers\api;

use app\models\Project;
use app\models\ScratchPad;
use app\services\copyformat\DeltaParser;
use Yii;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\rest\Controller;
use yii\web\Response;

class ScratchPadController extends Controller
{
    private DeltaParser $deltaParser;

    public function __construct(
        $id,
        $module,
        DeltaParser $deltaParser = new DeltaParser(),
        $config = []
    ) {
        $this->deltaParser = $deltaParser;
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];
        return $behaviors;
    }

    public function actionCreate(): array
    {
        $request = Yii::$app->request;
        $user = Yii::$app->user->identity;

        $name = $request->post('name');
        $content = $request->post('content');
        $projectName = $request->post('project_name');
        $format = $request->post('format', 'text'); // 'text' or 'delta'

        // Validate format parameter
        if (!in_array($format, ['text', 'delta'], true)) {
            Yii::$app->response->statusCode = 400;
            return ['success' => false, 'errors' => ['format' => ['Format must be "text" or "delta".']]];
        }

        // Validate content type matches format
        if ($format === 'text' && $content !== null && !is_string($content)) {
            Yii::$app->response->statusCode = 400;
            return ['success' => false, 'errors' => ['content' => ['Content must be a string when format is "text".']]];
        }

        // Convert content based on format
        $deltaContent = $this->convertContent($content, $format);
        if ($deltaContent === false) {
            Yii::$app->response->statusCode = 400;
            return ['success' => false, 'errors' => ['content' => ['Invalid Delta JSON format.']]];
        }

        // Find or create project by name
        $projectId = $this->resolveProjectId($user->id, $projectName);

        $scratchPad = new ScratchPad([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'name' => $name,
            'content' => $deltaContent,
        ]);

        if ($scratchPad->save()) {
            Yii::$app->response->statusCode = 201;
            return ['success' => true, 'id' => $scratchPad->id];
        }

        Yii::$app->response->statusCode = 422;
        return ['success' => false, 'errors' => $scratchPad->getErrors()];
    }

    /**
     * Finds existing project or creates new one.
     */
    private function resolveProjectId(int $userId, ?string $projectName): ?int
    {
        if ($projectName === null || $projectName === '') {
            return null;
        }

        $project = Project::find()
            ->byUser($userId)
            ->andWhere(['name' => $projectName])
            ->one();

        if ($project) {
            return $project->id;
        }

        // Auto-create project
        $project = new Project([
            'user_id' => $userId,
            'name' => $projectName,
        ]);

        if ($project->save()) {
            return $project->id;
        }

        // If creation fails, proceed without project
        Yii::warning("Failed to auto-create project '$projectName': " . json_encode($project->getErrors()), __METHOD__);
        return null;
    }

    /**
     * Converts content to Quill Delta JSON format.
     *
     * @param string|array|null $content Content as text, Delta string, or Delta object
     * @param string $format 'text' or 'delta'
     * @return string|null|false Delta JSON string, null for empty, false for invalid delta
     */
    private function convertContent(string|array|null $content, string $format): string|null|false
    {
        if ($content === null || $content === '' || $content === []) {
            return null;
        }

        if ($format === 'delta') {
            // Content can be array (JSON object) or string (JSON string)
            if (is_array($content)) {
                // Validate structure
                if (!isset($content['ops']) || !is_array($content['ops'])) {
                    return false;
                }
                return $this->deltaParser->encode($content);
            }

            // String: validate it's proper Delta JSON
            $decoded = $this->deltaParser->decode($content);
            if ($decoded === null) {
                return false;
            }
            return $this->deltaParser->encode($decoded);
        }

        // Convert plain text to Delta format
        $ops = [['insert' => $content . "\n"]];
        return $this->deltaParser->encode(['ops' => $ops]);
    }
}
```

---

## 6. Add Route Configuration

**File:** `yii/config/web.php`

Add JSON parser to request component:

```php
$config = [
    'id' => 'basic',
    'components' => [
        'db' => $db,
        'request' => [
            'cookieValidationKey' => 'IwE5i3d_0AhHc5a7gnVMSk38YDzgqBYi',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        // ... existing components
    ],
];
```

**File:** `yii/config/main.php`

Add to `urlManager.rules`:

```php
'urlManager' => [
    'enablePrettyUrl' => true,
    'showScriptName' => false,
    'rules' => [
        'POST api/scratch-pad' => 'api/scratch-pad/create',
    ],
],
```

---

## API Specification

**Endpoint:** `POST /api/scratch-pad`

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

### Request Examples

**Plain text content:**
```json
{
  "name": "Video Title - Transcript",
  "content": "Transcript text here...",
  "format": "text",
  "project_name": "YouTube transcripts"
}
```

**Delta as JSON object (recommended for format=delta):**
```json
{
  "name": "Rich Content Note",
  "content": {
    "ops": [
      {"insert": "Hello "},
      {"insert": "World", "attributes": {"bold": true}},
      {"insert": "\n"}
    ]
  },
  "format": "delta",
  "project_name": "Notes"
}
```

**Delta as JSON string (also supported):**
```json
{
  "name": "Rich Content Note",
  "content": "{\"ops\":[{\"insert\":\"Hello \"},{\"insert\":\"World\",\"attributes\":{\"bold\":true}},{\"insert\":\"\\n\"}]}",
  "format": "delta"
}
```

### Response Examples

**Success (201 Created):**
```json
{"success": true, "id": 123}
```

**Validation error (422):**
```json
{"success": false, "errors": {"name": ["Name cannot be blank."]}}
```

**Invalid input (400):**
```json
{"success": false, "errors": {"content": ["Invalid Delta JSON format."]}}
```

**Type mismatch (400):**
```json
{"success": false, "errors": {"content": ["Content must be a string when format is \"text\"."]}}
```

**Unauthorized (401):**
```json
{"name": "Unauthorized", "message": "Your request was made with invalid credentials.", "code": 0, "status": 401}
```

### Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | ScratchPad name |
| `content` | string\|object | No | Plain text (string only when format=text), Delta JSON string, or Delta object |
| `format` | string | No | `"text"` (default) or `"delta"` |
| `project_name` | string | No | Project name (auto-created if not found) |

### Edge Case Behaviors

| Scenario | Behavior |
|----------|----------|
| Project auto-create fails | Proceeds without project (logs warning), scratch pad created with `project_id = null` |
| Token save fails | Throws `RuntimeException` |
| format=text with non-string content | Returns 400 error |
| Existing plaintext tokens after migration | Invalidated; users must regenerate |

---

## Files to Create/Modify

| File | Action |
|------|--------|
| `yii/migrations/m260116_000001_add_access_token_security.php` | Create |
| `yii/modules/identity/models/User.php` | Update `findIdentityByAccessToken()` |
| `yii/modules/identity/services/UserService.php` | Add token methods |
| `yii/commands/UserController.php` | Add token commands |
| `yii/controllers/api/ScratchPadController.php` | Create |
| `yii/config/main.php` | Add API route |
| `yii/config/web.php` | Add JSON parser |

---

## Verification

```bash
# Run migration
docker exec pma_yii yii migrate --interactive=0
docker exec pma_yii yii_test migrate --interactive=0

# Generate token (90-day expiry by default)
docker exec pma_yii yii user/generate-token 1

# Generate token with custom expiry (30 days)
docker exec pma_yii yii user/generate-token 1 30

# Test API with plain text
curl -X POST http://localhost:8503/api/scratch-pad \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test", "content": "Hello world", "format": "text"}'

# Test API with Delta object
curl -X POST http://localhost:8503/api/scratch-pad \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name": "Rich", "content": {"ops": [{"insert": "Bold", "attributes": {"bold": true}}, {"insert": "\n"}]}, "format": "delta"}'

# Test auto-create project
curl -X POST http://localhost:8503/api/scratch-pad \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name": "Note", "content": "Test", "project_name": "New Project"}'

# Rotate token
docker exec pma_yii yii user/rotate-token 1

# Revoke token
docker exec pma_yii yii user/revoke-token 1
```

---

## Security Notes

- **Token storage:** Only SHA-256 hash stored in DB; plaintext shown once at generation
- **Token expiry:** Default 90 days, configurable per token
- **Token rotation:** `rotate-token` command invalidates old token immediately
- **DB leak impact:** Attacker gets hashes only; cannot derive plaintext tokens
