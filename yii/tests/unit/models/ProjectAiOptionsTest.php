<?php

namespace tests\unit\models;

use app\models\Project;
use Codeception\Test\Unit;

class ProjectAiOptionsTest extends Unit
{
    private function createProject(array|string|null $aiOptions = null): Project
    {
        $project = new Project();
        $project->ai_options = is_array($aiOptions) ? json_encode($aiOptions) : $aiOptions;
        return $project;
    }

    // ── getDefaultProvider ──────────────────────────────────────

    public function testGetDefaultProviderFallsBackToHardcodedDefault(): void
    {
        $project = $this->createProject();
        verify($project->getDefaultProvider())->equals('claude');
    }

    public function testGetDefaultProviderReturnsStoredDefault(): void
    {
        $project = $this->createProject(['_default' => 'codex', 'codex' => ['model' => 'codex-mini']]);
        verify($project->getDefaultProvider())->equals('codex');
    }

    public function testGetDefaultProviderFallsBackWhenNoDefaultKey(): void
    {
        $project = $this->createProject(['claude' => ['model' => 'sonnet']]);
        verify($project->getDefaultProvider())->equals('claude');
    }

    // ── isNamespacedOptions (tested via getAiOptions behavior) ──

    public function testGetAiOptionsReturnsFlatOptionsDirectly(): void
    {
        $project = $this->createProject(['model' => 'sonnet', 'permissionMode' => 'plan']);
        $options = $project->getAiOptions();
        verify($options)->equals(['model' => 'sonnet', 'permissionMode' => 'plan']);
    }

    public function testGetAiOptionsReturnsDefaultProviderFromNamespaced(): void
    {
        $project = $this->createProject([
            'claude' => ['model' => 'sonnet'],
            'codex' => ['model' => 'codex-mini'],
            '_default' => 'claude',
        ]);
        $options = $project->getAiOptions();
        verify($options)->equals(['model' => 'sonnet']);
    }

    // ── getAiOptionsForProvider ─────────────────────────────────

    public function testGetAiOptionsForProviderReturnsNamespaced(): void
    {
        $project = $this->createProject(['claude' => ['model' => 'sonnet']]);
        verify($project->getAiOptionsForProvider('claude'))->equals(['model' => 'sonnet']);
    }

    public function testGetAiOptionsForProviderReturnsEmptyForUnknown(): void
    {
        $project = $this->createProject(['claude' => ['model' => 'sonnet']]);
        verify($project->getAiOptionsForProvider('codex'))->equals([]);
    }

    public function testGetAiOptionsForProviderHandlesLegacyFlat(): void
    {
        $project = $this->createProject(['model' => 'sonnet', 'permissionMode' => 'plan']);
        // Default provider is 'claude', so legacy flat returns for 'claude'
        verify($project->getAiOptionsForProvider('claude'))->equals(['model' => 'sonnet', 'permissionMode' => 'plan']);
        // Other providers get empty
        verify($project->getAiOptionsForProvider('codex'))->equals([]);
    }

    public function testGetAiOptionsForProviderReturnsEmptyWhenNull(): void
    {
        $project = $this->createProject();
        verify($project->getAiOptionsForProvider('claude'))->equals([]);
    }

    // ── setAiOptionsForProvider ─────────────────────────────────

    public function testSetAiOptionsForProviderNamespaces(): void
    {
        $project = $this->createProject();
        $project->setAiOptionsForProvider('codex', ['model' => 'codex-mini']);
        verify($project->getAiOptionsForProvider('codex'))->equals(['model' => 'codex-mini']);
    }

    public function testSetAiOptionsForProviderPreservesOtherProviders(): void
    {
        $project = $this->createProject(['claude' => ['model' => 'sonnet']]);
        $project->setAiOptionsForProvider('codex', ['model' => 'codex-mini']);
        verify($project->getAiOptionsForProvider('claude'))->equals(['model' => 'sonnet']);
        verify($project->getAiOptionsForProvider('codex'))->equals(['model' => 'codex-mini']);
    }

    public function testSetAiOptionsForProviderRemovesEmptyValues(): void
    {
        $project = $this->createProject();
        $project->setAiOptionsForProvider('claude', ['model' => '', 'permissionMode' => 'plan']);
        $options = $project->getAiOptionsForProvider('claude');
        verify($options)->equals(['permissionMode' => 'plan']);
        verify(array_key_exists('model', $options))->false();
    }

    public function testSetAiOptionsForProviderMigratesLegacyFlat(): void
    {
        $project = $this->createProject(['model' => 'sonnet', 'permissionMode' => 'plan']);
        $project->setAiOptionsForProvider('codex', ['model' => 'codex-mini']);
        // Legacy flat options should now be under 'claude' namespace
        verify($project->getAiOptionsForProvider('claude'))->equals(['model' => 'sonnet', 'permissionMode' => 'plan']);
        verify($project->getAiOptionsForProvider('codex'))->equals(['model' => 'codex-mini']);
    }

    public function testSetAiOptionsForProviderDecodesJsonStrings(): void
    {
        $project = $this->createProject();
        $project->setAiOptionsForProvider('claude', [
            'commandBlacklist' => '["cmd1","cmd2"]',
            'model' => 'sonnet',
        ]);
        $options = $project->getAiOptionsForProvider('claude');
        verify($options['commandBlacklist'])->equals(['cmd1', 'cmd2']);
        verify($options['model'])->equals('sonnet');
    }

    public function testSetAiOptionsForProviderRemovesProviderWhenAllEmpty(): void
    {
        $project = $this->createProject(['claude' => ['model' => 'sonnet'], 'codex' => ['model' => 'codex-mini']]);
        $project->setAiOptionsForProvider('codex', ['model' => '']);
        verify($project->getAiOptionsForProvider('codex'))->equals([]);
        verify($project->getAiOptionsForProvider('claude'))->equals(['model' => 'sonnet']);
    }

    // ── getAiCommandBlacklist / getAiCommandGroups ──────────────

    public function testGetAiCommandBlacklistWithProviderParameter(): void
    {
        $project = $this->createProject([
            'claude' => ['commandBlacklist' => ['cmd1', 'cmd2']],
            'codex' => ['commandBlacklist' => ['cmd3']],
        ]);
        verify($project->getAiCommandBlacklist('codex'))->equals(['cmd3']);
        verify($project->getAiCommandBlacklist('claude'))->equals(['cmd1', 'cmd2']);
    }

    public function testGetAiCommandBlacklistDefaultsToDefaultProvider(): void
    {
        $project = $this->createProject([
            'claude' => ['commandBlacklist' => ['cmd1']],
            '_default' => 'claude',
        ]);
        // Without provider parameter, uses getAiOptions() which returns default provider's options
        verify($project->getAiCommandBlacklist())->equals(['cmd1']);
    }

    public function testGetAiCommandGroupsWithProviderParameter(): void
    {
        $project = $this->createProject([
            'claude' => ['commandGroups' => ['group1' => ['cmd1', 'cmd2']]],
            'codex' => ['commandGroups' => ['group2' => ['cmd3']]],
        ]);
        verify($project->getAiCommandGroups('codex'))->equals(['group2' => ['cmd3']]);
    }

    public function testGetAiCommandGroupsDefaultsToDefaultProvider(): void
    {
        $project = $this->createProject([
            'claude' => ['commandGroups' => ['group1' => ['cmd1']]],
        ]);
        verify($project->getAiCommandGroups())->equals(['group1' => ['cmd1']]);
    }

    // ── getAiOptions backward compatibility ─────────────────────

    public function testGetAiOptionsBackwardCompatibleWithLegacy(): void
    {
        $project = $this->createProject(['model' => 'sonnet', 'permissionMode' => 'plan']);
        $options = $project->getAiOptions();
        // Should return flat options directly
        verify($options['model'])->equals('sonnet');
        verify($options['permissionMode'])->equals('plan');
    }

    public function testGetAiOptionsReturnsEmptyForNull(): void
    {
        $project = $this->createProject();
        verify($project->getAiOptions())->equals([]);
    }
}
