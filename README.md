<p align="center">
  <img src="https://github.com/efhsg/promptmanager/blob/main/yii/web/images/prompt-manager-logo.png" alt="MoneyMonkey logo" width="200">
</p>


# PromtpManager: Automate Your AI Prompts

Welcome to **PromtpManager**, a new software project dedicated to streamlining your work with AI prompt engineering. We recognized a common challenge in the development process—managing and assembling various parts of prompts stored in loose text files through manual copy-and-paste. PromtpManager is here to change that.

## What Is PromtpManager?

PromtpManager is designed to automate the creation, management, and integration of prompts used in large language model (LLM) software development. Our tool leverages a structured approach using a combination of projects, contexts, and prompt templates with customizable fields, ensuring that your prompts are both organized and dynamically assembled as needed.

## Why PromptManager?

- **Efficiency:** Eliminate the tedious process of manually piecing together prompt components.
- **Automation:** Seamlessly manage and update your prompts with an automated system.
- **Organization:** Keep your projects, contexts, and templates in one centralized repository.

Explore the complete source code and join the development at our [GitHub repository](https://github.com/efhsg/promptmanager).

Dive in and start automating your AI prompts today!

## Quick start

- **Check out the repository**
  ```
  git clone <repository-url>
  ```
- **Navigate to your local project directory**
  ```
  cd <local project directory>
  ```  
- **Copy the environment variables example file**
  ```
  cp .env.example .env
  ```
- **Set your API keys in the `.env` file**

### NPM
  ```
  docker compose run --entrypoint bash pma_npm -c "npm run setup"
  docker compose run --entrypoint bash pma_npm -c "npm run prebuild"
  docker compose run --entrypoint bash pma_npm -c "npm run build"
  ```

### Setup using Docker:
  ```
  docker-compose up -d
  ```

### Composer install
  ```
   docker exec -it pma_yii bash -c "composer install"
  ```
### Migrations
  ```
  docker exec pma_yii yii migrate --migrationPath=@app/modules/identity/migrations --interactive=0 && docker exec pma_yii yii_test migrate --migrationPath=@app/modules/identity/migrations --interactive=0
  docker exec pma_yii yii migrate --interactive=0 && docker exec pma_yii yii_test migrate --interactive=0 && docker exec pma_yii yii migrate --migrationPath=@yii/log/migrations/ --interactive=0
  docker exec pma_yii yii migrate --migrationPath=@yii/rbac/migrations --interactive=0 && docker exec pma_yii yii rbac/init && docker exec pma_yii yii_test migrate --migrationPath=@yii/rbac/migrations --interactive=0
  ```

### Default user (or create one with Signup in the app)

docker exec -it pma_yii bash -c "yii identity/user/create user user@user.com user"

### To view the app:
- Open your web browser and navigate to `http://localhost:8503/`

Enjoy!