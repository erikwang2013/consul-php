<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Support;

/**
 * 开关型选项的取值归一。
 *
 * 为什么需要它：Consul 的 `stale` / `consistent` 等参数是"有值即为真"的开关，而调用方常常
 * 从环境变量或配置里拿到**字符串**——`['stale' => 'false']` 在 `!empty()` 下是真值，
 * 于是本该走一致性读的请求被发成了陈旧读，而且不报错。这里统一按 PHP 的布尔字面量语义解析：
 * `'false'` / `'0'` / `'no'` / `'off'` / `''` → false，`'true'` / `'1'` / `'yes'` / `'on'` → true。
 */
final class Boolean
{
    /** @param mixed $value */
    public static function isTrue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }
}
