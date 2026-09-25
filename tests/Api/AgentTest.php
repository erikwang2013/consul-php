<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Agent;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class AgentTest extends TestCase
{
    private $transport;
    private Agent $agent;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->agent = new Agent($this->transport);
    }

    public function testMembers(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/members', [])
            ->willReturn([['Name' => 'node1']]);

        $this->assertSame('node1', $this->agent->members()[0]['Name']);
    }

    public function testMembersWithWan(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/members', ['wan' => '1'])
            ->willReturn([['Name' => 'node2']]);

        $this->assertSame('node2', $this->agent->members(['wan' => true])[0]['Name']);
    }

    public function testMembersPropagatesTransportError(): void
    {
        $this->transport->method('get')->willThrowException(new ClientException('down'));

        $this->expectException(ClientException::class);
        $this->agent->members();
    }

    public function testSelf(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/self')
            ->willReturn(['Config' => ['NodeName' => 'node1']]);

        $this->assertSame('node1', $this->agent->self()['Config']['NodeName']);
    }

    public function testMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/maintenance', [], ['enable' => 'true', 'reason' => 'upgrade']);

        $this->agent->maintenance(true, 'upgrade');
    }

    public function testMaintenanceWithoutReason(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/maintenance', [], ['enable' => 'true']);

        $this->agent->maintenance(true);
    }

    public function testMaintenanceDisableUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/maintenance', [], ['enable' => 'false']);

        $this->agent->maintenance(false);
    }

    public function testJoin(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/join/10.0.0.1%3A8301', [], []);

        $this->agent->join('10.0.0.1:8301');
    }

    public function testJoinWithWan(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/join/10.0.0.1%3A8302', [], ['wan' => '1']);

        $this->agent->join('10.0.0.1:8302', true);
    }

    public function testJoinEncodesAddress(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/join/a%20b', [], []);

        $this->agent->join('a b');
    }

    public function testForceLeave(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/force-leave/node-9');

        $this->agent->forceLeave('node-9');
    }

    public function testHealthServiceByName(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/health/service/name/web', [])
            ->willReturn([['AggregatedStatus' => 'passing', 'Service' => ['Service' => 'web']]]);

        $result = $this->agent->healthServiceByName('web');

        $this->assertCount(1, $result);
        $this->assertSame('passing', $result[0]['AggregatedStatus']);
    }

    public function testHealthServiceByNamePassesQueryOptions(): void
    {
        $options = ['passing' => 'true', 'filter' => 'Service.Meta.env==prod', 'node-meta' => 'rack:2'];
        $this->transport->method('get')
            ->with('/v1/agent/health/service/name/web', $options)
            ->willReturn([]);

        $this->assertSame([], $this->agent->healthServiceByName('web', $options));
    }

    public function testHealthServiceByNameEncodesName(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/health/service/name/web%20api%2Fv2', [])
            ->willReturn([]);

        $this->assertSame([], $this->agent->healthServiceByName('web api/v2'));
    }

    public function testHealthServiceByNamePropagatesUnhealthyStatus(): void
    {
        // 429/503 会被传输层当成请求异常抛出，方法不会返回数组
        $this->transport->method('get')->willThrowException(new ClientException('critical', 503));

        $this->expectException(ClientException::class);
        $this->agent->healthServiceByName('web');
    }

    public function testHealthServiceById(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/health/service/id/web-1', [])
            ->willReturn([['AggregatedStatus' => 'passing', 'Service' => ['ID' => 'web-1']]]);

        $result = $this->agent->healthServiceById('web-1');

        $this->assertSame('web-1', $result[0]['Service']['ID']);
    }

    public function testHealthServiceByIdEncodesId(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/health/service/id/svc%3Aweb', ['ns' => 'ns1'])
            ->willReturn([]);

        $this->assertSame([], $this->agent->healthServiceById('svc:web', ['ns' => 'ns1']));
    }

    public function testReload(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/reload')
            ->willReturn([]);

        $this->assertSame([], $this->agent->reload());
    }

    public function testLeave(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/leave')
            ->willReturn([]);

        $this->assertSame([], $this->agent->leave());
    }

    public function testForceLeaveWithPruneAndWan(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/force-leave/node-9', [], ['prune' => 'true', 'wan' => '1'])
            ->willReturn([]);

        $this->agent->forceLeave('node-9', ['prune' => 'true', 'wan' => '1']);
    }

    public function testChecksWithFilter(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/checks', ['filter' => 'Status == "critical"'])
            ->willReturn(['check1' => ['Status' => 'critical']]);

        $this->assertArrayHasKey('check1', $this->agent->checks(['filter' => 'Status == "critical"']));
    }

    public function testServicesWithFilter(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/services', ['filter' => 'Service == "web"'])
            ->willReturn(['web' => ['Service' => 'web']]);

        $this->assertArrayHasKey('web', $this->agent->services(['filter' => 'Service == "web"']));
    }

    public function testChecks(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/checks')
            ->willReturn(['check1' => ['Status' => 'passing']]);

        $this->assertArrayHasKey('check1', $this->agent->checks());
    }

    public function testServices(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/services')
            ->willReturn(['web' => ['Service' => 'web', 'Port' => 80]]);

        $this->assertArrayHasKey('web', $this->agent->services());
    }

    public function testRegisterService(): void
    {
        $service = ['Name' => 'web', 'Port' => 80];
        $this->transport->method('put')
            ->with('/v1/agent/service/register', $service)
            ->willReturn([]);

        $this->assertSame([], $this->agent->registerService($service));
    }

    public function testDeregisterService(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/deregister/web-1');

        $this->agent->deregisterService('web-1');
    }

    public function testEnableMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/maintenance/web-1', [], ['enable' => 'true', 'reason' => 'deploy']);

        $this->agent->enableMaintenance('web-1', 'deploy');
    }

    public function testEnableMaintenanceWithoutReason(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/maintenance/web-1', [], ['enable' => 'true']);

        $this->agent->enableMaintenance('web-1');
    }

    public function testDisableMaintenanceUsesQueryParameters(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/maintenance/web-1', [], ['enable' => 'false']);

        $this->agent->disableMaintenance('web-1');
    }

    public function testCheckPassSendsNoteAsQueryParameter(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/pass/check-1', '', ['note' => 'all good']);

        $this->agent->checkPass('check-1', 'all good');
    }

    public function testCheckPassWithoutNote(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/pass/check-1', '', []);

        $this->agent->checkPass('check-1');
    }

    public function testCheckFailSendsNoteAsQueryParameter(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/fail/check-1', '', ['note' => 'boom']);

        $this->agent->checkFail('check-1', 'boom');
    }

    public function testCheckWarnSendsNoteAsQueryParameter(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/warn/check-1', '', ['note' => 'slow']);

        $this->agent->checkWarn('check-1', 'slow');
    }

    public function testCheckPassEncodesCheckId(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/pass/svc%3Aweb', '', []);

        $this->agent->checkPass('svc:web');
    }

    public function testCheckRegister(): void
    {
        $check = ['ID' => 'chk1', 'Name' => 'http', 'TTL' => '10s'];
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/check/register', $check);

        $this->agent->checkRegister($check);
    }

    public function testCheckDeregister(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/check/deregister/chk1');

        $this->agent->checkDeregister('chk1');
    }

    public function testTtlCheckPassDelegates(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/pass/chk1', '', ['note' => 'n']);

        $this->agent->ttlCheckPass('chk1', 'n');
    }

    public function testTtlCheckFailDelegates(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/fail/chk1', '', []);

        $this->agent->ttlCheckFail('chk1');
    }

    public function testTtlCheckWarnDelegates(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/agent/check/warn/chk1', '', []);

        $this->agent->ttlCheckWarn('chk1');
    }

    public function testCheckUpdateSendsStatusAndOutputAsBody(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/check/update/chk1', ['Status' => 'passing', 'Output' => 'ok'])
            ->willReturn([]);

        $this->assertSame([], $this->agent->checkUpdate('chk1', ['Status' => 'passing', 'Output' => 'ok']));
    }

    public function testCheckUpdateWithoutOptionsSendsEmptyBody(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/check/update/chk1', []);

        $this->agent->checkUpdate('chk1');
    }

    public function testCheckUpdateEncodesCheckId(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/check/update/svc%3Aweb', ['Status' => 'critical']);

        $this->agent->checkUpdate('svc:web', ['Status' => 'critical']);
    }

    public function testServiceReturnsSingleRegistration(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/service/web-1', [])
            ->willReturn(['ID' => 'web-1', 'Service' => 'web', 'Port' => 80]);

        $this->assertSame(80, $this->agent->service('web-1')['Port']);
    }

    public function testServicePassesQueryOptionsAndEncodesId(): void
    {
        $options = ['index' => '42', 'wait' => '5m', 'ns' => 'ns1'];
        $this->transport->method('get')
            ->with('/v1/agent/service/svc%3Aweb', $options)
            ->willReturn([]);

        $this->assertSame([], $this->agent->service('svc:web', $options));
    }

    public function testUpdateTokenSendsTokenInBody(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/token/acl_token', ['Token' => 'secret-token'])
            ->willReturn([]);

        $this->assertSame([], $this->agent->updateToken('acl_token', 'secret-token'));
    }

    public function testUpdateTokenKeepsKindAsIs(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/token/acl_agent_token', ['Token' => 't']);

        $this->agent->updateToken('acl_agent_token', 't');
    }

    public function testVersion(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/version')
            ->willReturn(['SHA' => 'abc123', 'HumanVersion' => '1.20.0']);

        $this->assertSame('1.20.0', $this->agent->version()['HumanVersion']);
    }

    public function testHost(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/host')
            ->willReturn(['OS' => 'linux', 'Hostname' => 'node1']);

        $this->assertSame('node1', $this->agent->host()['Hostname']);
    }

    public function testMetricsReturnsJsonWithoutFormat(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/metrics')
            ->willReturn(['Counters' => [['Name' => 'consul.rpc.query']]]);

        $this->assertArrayHasKey('Counters', $this->agent->metrics());
    }

    public function testMetricsWithEmptyFormatStillReturnsJson(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/metrics')
            ->willReturn([]);

        $this->assertSame([], $this->agent->metrics(''));
    }

    public function testMetricsPrometheusUsesRawTransport(): void
    {
        $this->transport->expects($this->once())
            ->method('getRaw')
            ->with('/v1/agent/metrics', ['format' => 'prometheus'])
            ->willReturn("consul_rpc_query_count 3\n");

        $this->assertSame(
            ['format' => 'prometheus', 'body' => "consul_rpc_query_count 3\n"],
            $this->agent->metrics('prometheus')
        );
    }

    public function testConnectAuthorize(): void
    {
        $payload = [
            'Target' => 'db',
            'ClientCertURI' => 'spiffe://abc.consul/ns/default/dc/dc1/svc/web',
            'ClientCertSerial' => 'aa:bb',
        ];
        $this->transport->expects($this->once())
            ->method('post')
            ->with('/v1/agent/connect/authorize', $payload)
            ->willReturn(['Authorized' => true, 'Reason' => 'Matched L4 intention: db => db']);

        $result = $this->agent->connectAuthorize($payload);

        $this->assertTrue($result['Authorized']);
        $this->assertSame('Matched L4 intention: db => db', $result['Reason']);
    }

    public function testConnectCaRoots(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/connect/ca/roots')
            ->willReturn(['ActiveRootID' => 'root-1', 'Roots' => [['ID' => 'root-1']]]);

        $this->assertSame('root-1', $this->agent->connectCaRoots()['ActiveRootID']);
    }

    public function testConnectCaLeaf(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/connect/ca/leaf/web', [])
            ->willReturn(['SerialNumber' => '01:02', 'Service' => 'web']);

        $this->assertSame('web', $this->agent->connectCaLeaf('web')['Service']);
    }

    public function testConnectCaLeafEncodesServiceName(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/connect/ca/leaf/web%20api', [])
            ->willReturn([]);

        $this->assertSame([], $this->agent->connectCaLeaf('web api'));
    }
}
