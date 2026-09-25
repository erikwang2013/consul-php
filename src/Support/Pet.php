<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Support;

/**
 * 项目宠物 Consu 的终端版。
 *
 * 与 docs/images/pet.svg 同一套设定：
 * 天线 = 健康检查心跳，护目镜 = 服务发现，胸前脉线 = 服务状态，腰牌 = ACL Token。
 *
 * ```php
 * echo Pet::say('consul-php 已就绪');  // 气泡 + 宠物，自动判断是否上色
 * echo Pet::art(false);                // 纯文本
 * ```
 */
final class Pet
{
    public const NAME = 'Consu';

    /** 宠物本体：每个字符占 1 列，● 位于第 7 列（供 say() 对齐气泡尾巴）。 */
    private const ART = [
        '       ●',
        '       │',
        '   ╭───┴───╮',
        '   │ ◕   ◕ │',
        '   │  ╰─╯  │',
        '   ├───────┤',
        '   │╱╲╱╲╱╲ │',
        '   ╰┬─────┬╯',
        '    ╰─┬─┬─╯',
    ];

    private const BODY = "\033[38;5;141m";   // 车身 PHP 紫
    private const PULSE = "\033[38;5;42m";  // 天线 / 眼睛：passing 绿
    private const PINK = "\033[38;5;205m";  // 胸前脉线：Consul 品红
    private const RESET = "\033[39m";

    /** 天线所在列，气泡尾巴与它对齐。 */
    private const ANTENNA_COL = 7;

    /**
     * 返回宠物图案；$color 为 null 时按终端能力自动判断（尊重 NO_COLOR）。
     */
    public static function art(?bool $color = null): string
    {
        $art = implode("\n", self::ART);

        return (self::useColor($color) ? self::paint($art) : $art) . "\n";
    }

    /**
     * 头顶气泡说一句话，尾巴对准天线。
     */
    public static function say(string $message, ?bool $color = null): string
    {
        $message = str_replace(["\r", "\n"], ' ', $message);
        $width = self::width(' ' . $message . ' ');

        // 让尾巴落在天线那一列上：气泡窄了就往右推，宽了就贴左边
        $left = max(0, self::ANTENNA_COL - intdiv($width, 2));
        $tail = self::ANTENNA_COL - $left - 1;

        $bubble = [
            str_repeat(' ', $left) . '╭' . str_repeat('─', $width) . '╮',
            str_repeat(' ', $left) . '│ ' . $message . ' │',
            str_repeat(' ', $left) . '╰' . str_repeat('─', $tail) . '┬' . str_repeat('─', $width - $tail - 1) . '╯',
            str_repeat(' ', $left + 1 + $tail) . '│',
        ];

        $text = implode("\n", $bubble) . "\n" . implode("\n", self::ART);

        return (self::useColor($color) ? self::paint($text) : $text) . "\n";
    }

    private static function paint(string $text): string
    {
        // 先点高光，再整体刷车身色，避免高光把后续字符的颜色吃掉
        $text = strtr($text, [
            '●' => self::PULSE . '●' . self::BODY,
            '◕' => self::PULSE . '◕' . self::BODY,
            '╱╲╱╲╱╲' => self::PINK . '╱╲╱╲╱╲' . self::BODY,
        ]);

        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $lines[$i] = self::BODY . $line . self::RESET;
        }

        return implode("\n", $lines);
    }

    private static function useColor(?bool $color): bool
    {
        if ($color !== null) {
            return $color;
        }

        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        return \defined('STDOUT') && @stream_isatty(\STDOUT);
    }

    private static function width(string $text): int
    {
        return \function_exists('mb_strwidth') ? mb_strwidth($text) : \strlen($text);
    }
}
