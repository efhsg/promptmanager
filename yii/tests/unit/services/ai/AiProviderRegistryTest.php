<?php

namespace tests\unit\services\ai;

use app\services\ai\AiProviderInterface;
use app\services\ai\AiProviderRegistry;
use Codeception\Test\Unit;
use InvalidArgumentException;

class AiProviderRegistryTest extends Unit
{
    public function testGetReturnsRegisteredProvider(): void
    {
        $provider = $this->mockProvider('claude', 'Claude');
        $registry = new AiProviderRegistry([$provider]);

        verify($registry->get('claude'))->same($provider);
    }

    public function testGetThrowsForUnknownProvider(): void
    {
        $provider = $this->mockProvider('claude', 'Claude');
        $registry = new AiProviderRegistry([$provider]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown provider: nonexistent');
        $registry->get('nonexistent');
    }

    public function testHasReturnsTrueForRegisteredProvider(): void
    {
        $provider = $this->mockProvider('claude', 'Claude');
        $registry = new AiProviderRegistry([$provider]);

        verify($registry->has('claude'))->true();
    }

    public function testHasReturnsFalseForUnknownProvider(): void
    {
        $provider = $this->mockProvider('claude', 'Claude');
        $registry = new AiProviderRegistry([$provider]);

        verify($registry->has('nonexistent'))->false();
    }

    public function testAllReturnsAllProvidersIndexedByIdentifier(): void
    {
        $alpha = $this->mockProvider('alpha', 'Alpha');
        $beta = $this->mockProvider('beta', 'Beta');
        $registry = new AiProviderRegistry([$alpha, $beta]);

        $all = $registry->all();
        verify(array_keys($all))->equals(['alpha', 'beta']);
        verify($all['alpha'])->same($alpha);
        verify($all['beta'])->same($beta);
    }

    public function testAllPreservesInsertionOrder(): void
    {
        $alpha = $this->mockProvider('alpha', 'Alpha');
        $beta = $this->mockProvider('beta', 'Beta');
        $gamma = $this->mockProvider('gamma', 'Gamma');
        $registry = new AiProviderRegistry([$alpha, $beta, $gamma]);

        verify(array_keys($registry->all()))->equals(['alpha', 'beta', 'gamma']);
    }

    public function testGetDefaultReturnsFirstProvider(): void
    {
        $alpha = $this->mockProvider('alpha', 'Alpha');
        $beta = $this->mockProvider('beta', 'Beta');
        $registry = new AiProviderRegistry([$alpha, $beta]);

        verify($registry->getDefault())->same($alpha);
    }

    public function testGetDefaultIdentifierReturnsFirstIdentifier(): void
    {
        $alpha = $this->mockProvider('alpha', 'Alpha');
        $beta = $this->mockProvider('beta', 'Beta');
        $registry = new AiProviderRegistry([$alpha, $beta]);

        verify($registry->getDefaultIdentifier())->equals('alpha');
    }

    public function testThrowsOnDuplicateIdentifier(): void
    {
        $provider1 = $this->mockProvider('claude', 'Claude 1');
        $provider2 = $this->mockProvider('claude', 'Claude 2');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate provider: claude');
        new AiProviderRegistry([$provider1, $provider2]);
    }

    public function testThrowsOnEmptyProviders(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one provider required');
        new AiProviderRegistry([]);
    }

    public function testMultipleProvidersResolveCorrectly(): void
    {
        $alpha = $this->mockProvider('alpha', 'Alpha');
        $beta = $this->mockProvider('beta', 'Beta');
        $gamma = $this->mockProvider('gamma', 'Gamma');
        $registry = new AiProviderRegistry([$alpha, $beta, $gamma]);

        verify($registry->get('alpha'))->same($alpha);
        verify($registry->get('beta'))->same($beta);
        verify($registry->get('gamma'))->same($gamma);
    }

    private function mockProvider(string $identifier, string $name): AiProviderInterface
    {
        $mock = $this->createMock(AiProviderInterface::class);
        $mock->method('getIdentifier')->willReturn($identifier);
        $mock->method('getName')->willReturn($name);
        return $mock;
    }
}
