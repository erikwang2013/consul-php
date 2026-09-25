<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Api;

use Erikwang2013\Consul\Transport\TransportInterface;

/**
 * 事务 API（PUT /v1/txn）。
 *
 * 一次请求里按顺序执行多个操作，全部成功或全部不生效（Consul 内部按 Raft 事务提交）。
 * 支持 KV / Node / Service / Check 四类操作，本类只封装最常用的 KV 操作，
 * 其余三类可用 {@see self::raw()} 自行拼装。
 *
 * ## 失败语义（重要）
 *
 * 与大多数端点不同，txn 冲突**不返回 200**：Consul 在存在 `Errors` 时会以
 * **409 Conflict** 回复，body 仍是正常的 `{"Results": [...], "Errors": [...]}` JSON。
 * 传输层（{@see \Erikwang2013\Consul\Transport\Psr18Transport::checkStatus()}）会把
 * 4xx 一律抛成 `ConsulRequestException`，因此 {@see self::apply()} 遇到冲突时抛出的是
 * `ConsulRequestException`，**异常 code 为 409**——调用方据此区分「事务冲突」与其他 4xx：
 *
 * ```php
 * try {
 *     $txn->apply([Txn::checkIndex('k', $idx), Txn::set('k', 'v')]);
 * } catch (ConsulRequestException $e) {
 *     if ($e->getCode() === 409) { // 冲突：索引不匹配 / 写入被拒
 *     }
 * }
 * ```
 *
 * 取舍说明：这里**没有**捕获 409 后返回结构化结果。原因是被抛出的异常 message 里
 * body 已被传输层截断到 200 字节（`MAX_ERROR_BODY_LENGTH`），`Errors` 数组通常远超该长度，
 * 反解 JSON 只会得到残缺数据，静默丢错误比直接抛异常更危险。需要完整 Errors 的调用方
 * 应自行放宽该上限，或干脆用单条 KV 接口配合 CAS 重试。
 */
class Txn
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * 提交事务。
     *
     * @param array $operations Txn::set()/get()/... 等构造出的操作列表，或 raw() 的自定义操作
     * @param array $options    仅 dc / ns / partition（txn 不支持 index/wait 阻塞查询）
     *
     * @throws \Erikwang2013\Consul\Exception\ConsulRequestException 事务冲突时 code 为 409
     */
    public function apply(array $operations, array $options = []): array
    {
        return $this->transport->put(
            '/v1/txn',
            ['Operations' => array_values($operations)],
            $this->optionsQuery($options)
        );
    }

    // ---- KV 操作构造器 ----

    /** 写入 key。 */
    public static function set(string $key, string $value, int $flags = 0): array
    {
        return self::kvWrite('set', $key, $value, self::flags($flags));
    }

    /** 仅当 key 的 ModifyIndex 等于 $index 时写入。 */
    public static function cas(string $key, string $value, int $index, int $flags = 0): array
    {
        return self::kvWrite('cas', $key, $value, self::flags($flags) + ['Index' => $index]);
    }

    /** 用会话获取锁（key 已被他人持有时事务失败）。 */
    public static function lock(string $key, string $value, string $session, int $flags = 0): array
    {
        return self::kvWrite('lock', $key, $value, self::flags($flags) + ['Session' => $session]);
    }

    /** 用会话释放锁（key 必须由该会话持有）。 */
    public static function unlock(string $key, string $value, string $session, int $flags = 0): array
    {
        return self::kvWrite('unlock', $key, $value, self::flags($flags) + ['Session' => $session]);
    }

    /** 读取单个 key。 */
    public static function get(string $key): array
    {
        return self::kvOp('get', $key);
    }

    /** 读取前缀下所有 key。 */
    public static function getTree(string $prefix): array
    {
        return self::kvOp('get-tree', $prefix);
    }

    /** 删除单个 key。 */
    public static function delete(string $key): array
    {
        return self::kvOp('delete', $key);
    }

    /** 删除前缀下所有 key。 */
    public static function deleteTree(string $prefix): array
    {
        return self::kvOp('delete-tree', $prefix);
    }

    /** 仅当 ModifyIndex 等于 $index 时删除。 */
    public static function deleteCas(string $key, int $index): array
    {
        return self::kvOp('delete-cas', $key, ['Index' => $index]);
    }

    /** 守卫：key 的 ModifyIndex 不等于 $index 时整个事务失败。用来做乐观并发。 */
    public static function checkIndex(string $key, int $index): array
    {
        return self::kvOp('check-index', $key, ['Index' => $index]);
    }

    /** 守卫：key 必须被指定会话持有，否则整个事务失败。 */
    public static function checkSession(string $key, string $session): array
    {
        return self::kvOp('check-session', $key, ['Session' => $session]);
    }

    /** 守卫：key 必须不存在，否则整个事务失败。 */
    public static function checkNotExists(string $key): array
    {
        return self::kvOp('check-not-exists', $key);
    }

    /**
     * 拼装任意操作（Node/Service/Check 等本类未封装的部分）。
     * 原样交给 Consul，不做字段校验。
     *
     * @param array $operation 形如 ['Node' => ['Verb' => 'get', 'Node' => 'n1']]
     */
    public static function raw(array $operation): array
    {
        return $operation;
    }

    // ---- 内部 ----

    /**
     * Value 在 Consul 侧是 Go 的 []byte，JSON 传输时按 StdEncoding base64 解码，
     * 所以这里必须编码；与普通 KV 写入（/v1/kv 直接收 raw body，不编码）语义不同，别混用。
     * 编码只在这一处做，各 verb 构造器共用。
     */
    private static function kvWrite(string $verb, string $key, string $value, array $extra = []): array
    {
        return self::kvOp($verb, $key, ['Value' => base64_encode($value)] + $extra);
    }

    private static function kvOp(string $verb, string $key, array $extra = []): array
    {
        return ['KV' => ['Verb' => $verb, 'Key' => $key] + $extra];
    }

    /** Flags 为 0 时不发送，保持 body 精简（Consul 缺省即 0）。 */
    private static function flags(int $flags): array
    {
        return $flags !== 0 ? ['Flags' => $flags] : [];
    }

    private function optionsQuery(array $options): array
    {
        return array_intersect_key($options, array_flip(['dc', 'ns', 'partition']));
    }
}
