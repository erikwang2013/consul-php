<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\ExportedService;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class ExportedServiceTest extends TestCase
{
    private $transport;
    private ExportedService $exportedService;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->exportedService = new ExportedService($this->transport);
    }

    public function testExported(): void
    {
        $this->transport->method('get')
            ->with('/v1/exported-services')
            ->willReturn([['Service' => 'web', 'Consumers' => [['Peer' => 'peer-a']]]]);

        $result = $this->exportedService->exported();

        $this->assertCount(1, $result);
        $this->assertSame('web', $result[0]['Service']);
        $this->assertSame('peer-a', $result[0]['Consumers'][0]['Peer']);
    }

    public function testExportedReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/exported-services')
            ->willReturn([]);

        $this->assertSame([], $this->exportedService->exported());
    }

    public function testImported(): void
    {
        $this->transport->method('get')
            ->with('/v1/imported-services')
            ->willReturn([['Service' => 'api', 'Peer' => 'peer-a']]);

        $result = $this->exportedService->imported();

        $this->assertCount(1, $result);
        $this->assertSame('api', $result[0]['Service']);
        $this->assertSame('peer-a', $result[0]['Peer']);
    }

    public function testImportedReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/imported-services')
            ->willReturn([]);

        $this->assertSame([], $this->exportedService->imported());
    }

    public function testExportedPropagatesTransportError(): void
    {
        $this->transport->method('get')->willThrowException(new ClientException('forbidden'));

        $this->expectException(ClientException::class);
        $this->exportedService->exported();
    }

    public function testImportedPropagatesTransportError(): void
    {
        $this->transport->method('get')->willThrowException(new ClientException('forbidden'));

        $this->expectException(ClientException::class);
        $this->exportedService->imported();
    }
}
