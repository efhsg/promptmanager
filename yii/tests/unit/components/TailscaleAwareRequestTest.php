<?php

namespace tests\unit\components;

use app\components\TailscaleAwareRequest;
use Codeception\Test\Unit;
use yii\web\HeaderCollection;

class TailscaleAwareRequestTest extends Unit
{
    /**
     * @dataProvider secureConnectionDataProvider
     */
    public function testGetIsSecureConnection(array $headers, bool $expected, ?array $customPorts = null): void
    {
        $request = $this->createMockRequest($headers);

        if ($customPorts !== null) {
            $request->httpsIndicatorPorts = $customPorts;
        }

        $this->assertSame($expected, $request->getIsSecureConnection());
    }

    public static function secureConnectionDataProvider(): array
    {
        return [
            'HTTPS indicator port 8443 in Host' => [
                ['Host' => '100.107.169.66:8443'],
                true,
            ],
            'HTTPS port 443 in Host' => [
                ['Host' => '100.107.169.66:443'],
                true,
            ],
            'HTTP port 8080 in Host' => [
                ['Host' => '100.107.169.66:8080'],
                false,
            ],
            'HTTP port 80 in Host' => [
                ['Host' => '100.107.169.66:80'],
                false,
            ],
            'HTTPS indicator port in X-Forwarded-Port' => [
                ['Host' => 'localhost', 'X-Forwarded-Port' => '8443'],
                true,
            ],
            'X-Forwarded-Port takes precedence over Host' => [
                ['Host' => '100.107.169.66:8080', 'X-Forwarded-Port' => '8443'],
                true,
            ],
            'Host without port' => [
                ['Host' => 'localhost'],
                false,
            ],
            'HTTPS indicator port in X-Forwarded-Host' => [
                ['Host' => 'localhost', 'X-Forwarded-Host' => '100.107.169.66:8443'],
                true,
            ],
            'Custom HTTPS indicator port' => [
                ['Host' => '100.107.169.66:9443'],
                true,
                [443, 8443, 9443],
            ],
        ];
    }

    private function createMockRequest(array $headers): TailscaleAwareRequest
    {
        $headerCollection = new HeaderCollection();
        foreach ($headers as $name => $value) {
            $headerCollection->add($name, $value);
        }

        $request = $this->getMockBuilder(TailscaleAwareRequest::class)
            ->onlyMethods(['getHeaders'])
            ->getMock();

        $request->method('getHeaders')->willReturn($headerCollection);

        $request->trustedHosts = [
            '.*' => ['X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-For', 'X-Forwarded-Host'],
        ];

        return $request;
    }
}
