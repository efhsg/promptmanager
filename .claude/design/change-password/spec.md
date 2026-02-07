# Change Password — Functional Specification

## Overview

Add a "Change password" feature to the existing identity module. The menu item in `main.php` already links to `/identity/auth/change-password` — we need to build the backend and frontend to support it.

## Existing Infrastructure

| Component | Path | Relevance |
|-----------|------|-----------|
| Menu link | `views/layouts/main.php:91` | Already points to `/identity/auth/change-password` |
| User model | `modules/identity/models/User.php` | Has `setPassword()` and `validatePassword()` |
| UserService | `modules/identity/services/UserService.php` | Has `updateUserAttribute()` (private) |
| AuthController | `modules/identity/controllers/AuthController.php` | Needs new `actionChangePassword()` |
| LoginForm | `modules/identity/models/LoginForm.php` | Pattern reference for form model |
| SignupForm | `modules/identity/models/SignupForm.php` | Pattern reference (DI via constructor) |
| Login view | `modules/identity/views/auth/login.php` | Visual pattern reference |

## Form Design

### ChangePasswordForm (Model)

**Properties:**

| Property | Type | Rules |
|----------|------|-------|
| `currentPassword` | `string` | Required. Validated against DB hash. |
| `newPassword` | `string` | Required. Min 3 chars (matches SignupForm). Max 255. |
| `confirmPassword` | `string` | Required. Must match `newPassword`. |

**Validation rules:**

```php
public function rules(): array
{
    return [
        [['currentPassword', 'newPassword', 'confirmPassword'], 'required'],
        [['newPassword'], 'string', 'min' => 3, 'max' => 255],
        ['confirmPassword', 'compare', 'compareAttribute' => 'newPassword', 'message' => 'Passwords do not match.'],
        ['currentPassword', 'validateCurrentPassword'],
    ];
}
```

**Custom validator:**

```php
public function validateCurrentPassword(string $attribute): void
{
    if (!$this->hasErrors()) {
        $user = Yii::$app->user->identity;
        if (!$user || !$user->validatePassword($this->currentPassword)) {
            $this->addError($attribute, 'Current password is incorrect.');
        }
    }
}
```

**Business method:**

```php
public function changePassword(): bool
{
    if (!$this->validate()) {
        return false;
    }

    return $this->userService->changePassword(
        Yii::$app->user->identity,
        $this->newPassword
    );
}
```

**Constructor:** Follows `SignupForm` pattern — `UserService` injected via constructor.

### UserService Addition

Add a `changePassword()` method:

```php
public function changePassword(User $user, string $newPassword): bool
{
    $user->setPassword($newPassword);
    return $this->updateUserAttribute($user, 'password_hash', $user->password_hash);
}
```

Note: `updateUserAttribute()` is already private in UserService. The new method calls it internally — no visibility change needed. However, `setPassword()` sets `password_hash` internally, so we call `setPassword()` first, then save via the existing `updateUserAttribute()` pattern.

Alternative (cleaner): since `setPassword()` already sets `password_hash`, we can just save that column directly:

```php
public function changePassword(User $user, string $newPassword): bool
{
    $user->setPassword($newPassword);
    try {
        return $user->save(false, ['password_hash']);
    } catch (Exception $e) {
        Yii::error("Error changing password for user ID $user->id: " . $e->getMessage(), __METHOD__);
        return false;
    }
}
```

## UX Design

### View: `change-password.php`

Follows the exact same visual pattern as `login.php` and `signup.php`:

- Centered card (`col-md-6 col-lg-4`)
- White background with border, rounded corners, shadow
- Title "Change password"
- Three password fields stacked vertically
- Single submit button full-width

```
┌──────────────────────────────────┐
│                                  │
│        Change password           │
│                                  │
│  Please enter your current       │
│  password and choose a new one.  │
│                                  │
│  Current password                │
│  ┌────────────────────────────┐  │
│  │ ●●●●●●●●                  │  │
│  └────────────────────────────┘  │
│                                  │
│  New password                    │
│  ┌────────────────────────────┐  │
│  │ ●●●●●●●●                  │  │
│  └────────────────────────────┘  │
│                                  │
│  Confirm new password            │
│  ┌────────────────────────────┐  │
│  │ ●●●●●●●●                  │  │
│  └────────────────────────────┘  │
│                                  │
│  ┌────────────────────────────┐  │
│  │      Change password       │  │
│  └────────────────────────────┘  │
│                                  │
└──────────────────────────────────┘
```

### User Flow

```
User clicks "Change password" in dropdown menu
  │
  ▼
GET /identity/auth/change-password
  │
  ▼
Display form (3 password fields)
  │
  ▼
User fills in fields and submits
  │
  ▼
POST /identity/auth/change-password
  │
  ├─ Validation fails → re-render form with errors
  │
  └─ Validation passes → changePassword() succeeds
       │
       ▼
     Flash message: "Password changed successfully."
     Redirect to home page (goHome)
```

### Success & Error States

| Scenario | Behavior |
|----------|----------|
| Current password wrong | Inline error on `currentPassword` field |
| New password too short | Inline error: "New password should contain at least 3 characters." |
| Confirm doesn't match | Inline error: "Passwords do not match." |
| All valid | Flash success + redirect to home |
| DB save fails | Flash error "Could not change password. Please try again." + re-render form |

### Access Control

- Only authenticated users can access this action
- The controller already inherits from `Controller`; add `behaviors()` access rules or check `Yii::$app->user->isGuest` at action level
- Since login/signup are guest-only and change-password is auth-only, the cleanest approach is to check in the action and redirect guests to login

## Files to Create/Modify

| Action | File |
|--------|------|
| **Create** | `modules/identity/models/ChangePasswordForm.php` |
| **Create** | `modules/identity/views/auth/change-password.php` |
| **Modify** | `modules/identity/controllers/AuthController.php` — add `actionChangePassword()` |
| **Modify** | `modules/identity/services/UserService.php` — add `changePassword()` |
| **Create** | `tests/unit/modules/identity/models/ChangePasswordFormTest.php` |

## Controller Action

```php
public function actionChangePassword(): Response|string
{
    if (Yii::$app->user->isGuest) {
        return $this->redirect(['/identity/auth/login']);
    }

    $model = new ChangePasswordForm($this->userService);

    if ($model->load(Yii::$app->request->post()) && $model->changePassword()) {
        Yii::$app->session->setFlash('success', 'Password changed successfully.');
        return $this->goHome();
    }

    return $this->render('change-password', ['model' => $model]);
}
```

## Test Plan

### Unit: ChangePasswordFormTest

| Test | Scenario |
|------|----------|
| `testValidationFailsWhenFieldsEmpty` | All fields required |
| `testValidationFailsWhenCurrentPasswordWrong` | Wrong current password |
| `testValidationFailsWhenNewPasswordTooShort` | Min 3 chars |
| `testValidationFailsWhenConfirmDoesNotMatch` | Compare validator |
| `testChangePasswordSuccess` | Happy path — password actually updated |

### Functional (optional, lower priority)

- Guest gets redirected to login
- Authenticated user sees the form
- Successful change shows flash + redirects
