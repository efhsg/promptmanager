<?php

namespace tests\unit\services\projectload;

use app\services\projectload\LoadReport;
use Codeception\Test\Unit;

class LoadReportTest extends Unit
{
    private LoadReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new LoadReport();
    }

    public function testInitProjectCreatesEntry(): void
    {
        $this->report->initProject(5, 'MyApp');

        $projects = $this->report->getProjects();
        $this->assertArrayHasKey(5, $projects);
        $this->assertEquals('MyApp', $projects[5]['name']);
        $this->assertEquals('pending', $projects[5]['status']);
    }

    public function testSetProjectStatusUpdatesStatus(): void
    {
        $this->report->initProject(5, 'MyApp');
        $this->report->setProjectStatus(5, 'success');

        $this->assertEquals('success', $this->report->getProjects()[5]['status']);
    }

    public function testSetProjectErrorSetsStatusAndMessage(): void
    {
        $this->report->initProject(5, 'MyApp');
        $this->report->setProjectError(5, 'Insert failed');

        $project = $this->report->getProjects()[5];
        $this->assertEquals('error', $project['status']);
        $this->assertEquals('Insert failed', $project['error']);
    }

    public function testAddInsertedAccumulatesCounts(): void
    {
        $this->report->initProject(5, 'MyApp');
        $this->report->addInserted(5, 'field', 3);
        $this->report->addInserted(5, 'field', 2);

        $this->assertEquals(5, $this->report->getProjects()[5]['inserted']['field']);
    }

    public function testAddDeletedAccumulatesCounts(): void
    {
        $this->report->initProject(5, 'MyApp');
        $this->report->addDeleted(5, 'context', 4);

        $this->assertEquals(4, $this->report->getProjects()[5]['deleted']['context']);
    }

    public function testIdMappingStoresAndRetrieves(): void
    {
        $this->report->addIdMapping('field', 10, 20);
        $this->report->addIdMapping('field', 11, 21);

        $this->assertEquals(20, $this->report->getMappedId('field', 10));
        $this->assertEquals(21, $this->report->getMappedId('field', 11));
        $this->assertNull($this->report->getMappedId('field', 99));
        $this->assertNull($this->report->getMappedId('context', 10));
    }

    public function testHasErrorsReturnsTrueWhenProjectHasError(): void
    {
        $this->report->initProject(5, 'MyApp');
        $this->report->setProjectStatus(5, 'success');
        $this->assertFalse($this->report->hasErrors());

        $this->report->initProject(8, 'Other');
        $this->report->setProjectError(8, 'Failed');
        $this->assertTrue($this->report->hasErrors());
    }

    public function testCountsReflectProjectStatuses(): void
    {
        $this->report->initProject(1, 'A');
        $this->report->setProjectStatus(1, 'success');

        $this->report->initProject(2, 'B');
        $this->report->setProjectStatus(2, 'success');
        $this->report->setProjectLocalMatch(2, 10, true);

        $this->report->initProject(3, 'C');
        $this->report->setProjectError(3, 'Failed');

        $this->report->initProject(4, 'D');
        $this->report->setProjectStatus(4, 'skipped');

        $this->assertEquals(2, $this->report->getSuccessCount());
        $this->assertEquals(1, $this->report->getErrorCount());
        $this->assertEquals(1, $this->report->getSkippedCount());
    }

    public function testReplacementAndNewCounts(): void
    {
        // New project (success)
        $this->report->initProject(1, 'New');
        $this->report->setProjectLocalMatch(1, null, false);
        $this->report->setProjectStatus(1, 'success');

        // Replacement project (success)
        $this->report->initProject(2, 'Replace');
        $this->report->setProjectLocalMatch(2, 10, true);
        $this->report->setProjectStatus(2, 'success');

        // Replacement project (error — should not count)
        $this->report->initProject(3, 'FailReplace');
        $this->report->setProjectLocalMatch(3, 20, true);
        $this->report->setProjectError(3, 'Failed');

        $this->assertEquals(1, $this->report->getNewCount());
        $this->assertEquals(1, $this->report->getReplacementCount());
    }

    public function testWarningsTrackedPerProject(): void
    {
        $this->report->initProject(5, 'MyApp');
        $this->report->addWarning(5, 'Warning 1');
        $this->report->addWarning(5, 'Warning 2');

        $this->assertCount(2, $this->report->getProjects()[5]['warnings']);
    }

    public function testGlobalWarningsTrackedSeparately(): void
    {
        $this->report->addGlobalWarning('Schema warning');

        $this->assertCount(1, $this->report->getGlobalWarnings());
        $this->assertEquals('Schema warning', $this->report->getGlobalWarnings()[0]);
    }
}
