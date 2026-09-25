<?php

declare(strict_types=1);

/**
 * 多语言资源生成器：把 docs/images/*.svg 里的文案替换成目标语言，输出到 docs/i18n/<lang>/images/。
 *
 * 用法：
 *   php scripts/i18n-svg.php extract          # 生成 docs/i18n/strings.template.json（原文 => 原文）
 *   php scripts/i18n-svg.php switcher <lang>  # 打印语言切换导航（lang = zh 或 12 个语言码之一）
 *   php scripts/i18n-svg.php build <lang>     # 用 docs/i18n/<lang>/strings.json 生成图
 *   php scripts/i18n-svg.php verify <lang>    # 校验产物：XML 合法、无中文残留、README 链接可达
 */

const ROOT = __DIR__ . '/..';
const SRC_SVG_DIR = ROOT . '/docs/images';
const I18N_DIR = ROOT . '/docs/i18n';
const TEMPLATE = I18N_DIR . '/strings.template.json';

const RTL_LANGS = ['ar'];   // 从右到左书写的语言：版面需要镜像
const MIN_SCALE = 0.62;      // 字号缩放下限，触底的条目由 audit 复核是否真的放得下
const TEXT_PADDING = 10.0;  // 文字与容器边缘的呼吸空间（px）

/** 语言码 => [本族名, 排序用顺序] */
const LANGUAGES = [
    'en' => 'English',
    'ja' => '日本語',
    'ko' => '한국어',
    'de' => 'Deutsch',
    'fr' => 'Français',
    'es' => 'Español',
    'pt' => 'Português',
    'ru' => 'Русский',
    'ar' => 'العربية',
    'hi' => 'हिन्दी',
    'bn' => 'বাংলা',
    'id' => 'Bahasa Indonesia',
];

$command = $argv[1] ?? '';
$lang = $argv[2] ?? '';

match ($command) {
    'extract' => extractStrings(),
    'switcher' => print switcher($lang),
    'build' => build($lang),
    'verify' => verify($lang),
    'audit' => audit($lang === '' ? 'zh' : $lang),
    default => usage(),
};

function usage(): void
{
    fwrite(STDERR, "用法：php scripts/i18n-svg.php extract | switcher <lang> | build <lang> | verify <lang> | audit [lang]\n");
    exit(1);
}

function assertLang(string $lang): void
{
    if ($lang !== 'zh' && !isset(LANGUAGES[$lang])) {
        fwrite(STDERR, "未知语言码：$lang\n");
        exit(1);
    }
}

/** 语言切换导航；切换行放在各 README 的 H1 下方。链接按当前文件所在层级生成。 */
function switcher(string $lang): string
{
    assertLang($lang);
    $base = $lang === 'zh' ? 'docs/i18n' : '..';
    $parts = [$lang === 'zh' ? '**中文**' : '[中文](../../../README.md)'];

    foreach (LANGUAGES as $code => $native) {
        $parts[] = $code === $lang ? "**$native**" : "[$native]($base/$code/README.md)";
    }

    return implode(' · ', $parts);
}

/** 取出 SVG 中所有可翻译文案（text / title / aria-label），实体解码后去重排序。 */
function sourceStrings(): array
{
    $strings = [];
    foreach (svgFiles() as $file) {
        $svg = (string) file_get_contents($file);
        preg_match_all('/<text[^>]*>(.*?)<\/text>/s', $svg, $texts);
        preg_match_all('/<title>(.*?)<\/title>/s', $svg, $titles);
        preg_match_all('/aria-label="([^"]*)"/', $svg, $labels);

        foreach ([...$texts[1], ...$titles[1], ...$labels[1]] as $raw) {
            $value = html_entity_decode($raw, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            if ($value !== '') {
                $strings[$value] = true;
            }
        }
    }

    $keys = array_keys($strings);
    sort($keys, SORT_STRING);

    return $keys;
}

function svgFiles(): array
{
    $files = glob(SRC_SVG_DIR . '/*.svg') ?: [];
    sort($files);

    return $files;
}

/** 生成待翻译模板：值先填原文，未翻译的条目原样回落，不会产出空白图。 */
function extractStrings(): void
{
    $template = [];
    foreach (sourceStrings() as $string) {
        $template[$string] = $string;
    }

    @mkdir(dirname(TEMPLATE), 0755, true);
    file_put_contents(
        TEMPLATE,
        json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );

    printf("已生成 %s（%d 条文案）\n", relative(TEMPLATE), count($template));
}

function build(string $lang): void
{
    if ($lang === 'all') {
        foreach (array_keys(LANGUAGES) as $code) {
            build($code);
        }

        return;
    }

    assertLang($lang);
    if ($lang === 'zh') {
        fwrite(STDERR, "zh 是源语言，无需生成。\n");
        exit(1);
    }

    $translations = loadTranslations($lang);
    $outDir = I18N_DIR . "/$lang/images";
    @mkdir($outDir, 0755, true);

    $missing = 0;
    $stats = ['shrunk' => 0, 'floor' => 0];
    foreach (svgFiles() as $file) {
        $svg = (string) file_get_contents($file);
        $sizes = cssFontSizes($svg);
        $layout = parseLayout($svg);
        $siblings = textExtents($svg, $sizes);

        $svg = preg_replace_callback('/<text([^>]*)>(.*?)<\/text>/s', function (array $m) use ($translations, $sizes, $layout, $siblings, &$missing, &$stats): string {
            $original = html_entity_decode($m[2], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $translated = $translations[$original] ?? $original;
            if (!isset($translations[$original])) {
                $missing++;
            }

            return '<text' . fitToWidth($m[1], $translated, $sizes, $layout, $siblings, $stats) . '>'
                . htmlspecialchars($translated, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text>';
        }, $svg);

        $svg = preg_replace_callback('/<title>(.*?)<\/title>/s', fn (array $m): string => '<title>'
            . htmlspecialchars($translations[html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')] ?? $m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')
            . '</title>', (string) $svg);

        $svg = preg_replace_callback('/aria-label="([^"]*)"/', fn (array $m): string => 'aria-label="'
            . htmlspecialchars($translations[html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')] ?? $m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')
            . '"', (string) $svg);

        // 源图里的 <!-- 分节注释 --> 是给维护者看的，生成件里去掉，免得外文文件里夹中文
        $svg = (string) preg_replace('/\s*<!--(?!\[).*?-->/s', '', (string) $svg);

        // RTL 语言：把流程图的版面沿中轴镜像（宠物卡是海报，不参与）
        if (in_array($lang, RTL_LANGS, true) && basename($file) !== 'pet.svg') {
            $svg = mirrorGeometry($svg);
        }

        file_put_contents($outDir . '/' . basename($file), $svg);
    }

    printf(
        "已生成 docs/i18n/%s/images/（缺失翻译 %d 处，缩字号 %d 处，其中触底 %d 处）\n",
        $lang,
        $missing,
        $stats['shrunk'],
        $stats['floor']
    );
}

/**
 * 让译文在版面里放得下：按可用宽度等比缩字号（下限 MIN_SCALE）。
 *
 * 不依赖 textLength：librsvg 完全忽略它（实测带与不带渲染宽度相同），
 * 拿它当"保证不溢出"是假的。所以这里只用确定性的宽度模型 + 字号缩放，
 * 剩余放不下的由 audit 逐条报出来改文案。
 *
 * 字号写在 style 属性里，优先级高于 <style> 中的类选择器。
 */
function fitToWidth(string $attrs, string $translated, array $sizes, array $layout, array $siblings, array &$stats): string
{
    if (!preg_match('/class="([^"]*)"/', $attrs, $m) || !isset($sizes[$m[1]])) {
        return $attrs;
    }

    $base = $sizes[$m[1]];
    $available = availableWidth($attrs, $layout, $base, $siblings);
    $needed = textWidth($translated) * $base;

    if ($available <= 0 || $needed <= $available) {
        return $attrs;                                  // 放得下，原样输出
    }

    // 向下取整到 0.1：四舍五入会向上舍入，导致"缩完正好等于可用宽度"被审计判成溢出
    $size = floor($base * max(MIN_SCALE, $available / $needed) * 10) / 10;
    $stats['shrunk']++;
    if ($size <= $base * MIN_SCALE + 0.05) {
        $stats['floor']++;                              // 已到缩放下限，实际效果由 audit 复核
    }

    return $attrs . ' style="font-size:' . $size . 'px"';
}


/**
 * RTL 版面镜像：把几何元素沿垂直中轴翻转，文字锚点 start <-> end 对调。
 * 只处理绝对坐标命令（M/L/H/C/V）——本项目源图只用这些；相对命令一律不动，避免算错。
 */
function mirrorGeometry(string $svg): string
{
    preg_match('/viewBox="0 0 ([\d.]+)/', $svg, $viewBox);
    $w = (float) ($viewBox[1] ?? 1000);

    $svg = preg_replace_callback('/<(rect|circle|ellipse)(\s[^>]*?)?\s*\/>/s', function (array $m) use ($w): string {
        $attrs = $m[2] ?? '';
        $key = $m[1] === 'rect' ? 'x' : 'cx';
        if (preg_match('/(?:^|\s)' . $key . '="(-?[\d.]+)"/', $attrs, $x) !== 1) {
            return $m[0];
        }

        $mirrored = $m[1] === 'rect'
            ? $w - (float) $x[1] - (float) attr($attrs, 'width')
            : $w - (float) $x[1];

        return '<' . $m[1] . preg_replace(
            '/(?:^|\s)' . $key . '="-?[\d.]+"/',
            ' ' . $key . '="' . round($mirrored, 1) . '"',
            $attrs
        ) . '/>';
    }, $svg);

    $svg = preg_replace_callback('/<line(\s[^>]*?)?\s*\/>/s', function (array $m) use ($w): string {
        $attrs = $m[1] ?? '';
        foreach (['x1', 'x2'] as $key) {
            $attrs = preg_replace_callback('/(?:^|\s)' . $key . '="(-?[\d.]+)"/', fn (array $a): string => ' ' . $key . '="' . round($w - (float) $a[1], 1) . '"', $attrs);
        }

        return '<line' . $attrs . '/>';
    }, $svg);

    $svg = preg_replace_callback('/<path(\s[^>]*?)?\s*\/>/s', function (array $m) use ($w): string {
        $attrs = $m[1] ?? '';
        $attrs = preg_replace_callback('/d="([^"]*)"/', function (array $a) use ($w): string {
            $mirrored = preg_replace_callback('/([A-Z])([^A-Za-z]*)/', function (array $c) use ($w): string {
                $numbers = preg_split('/[\s,]+/', trim($c[2]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($numbers as $i => $number) {
                    $isX = match ($c[1]) {
                        'H' => true,
                        'V' => false,
                        default => $i % 2 === 0,     // M/L 的 x,y 对；C 的 x1,y1,x2,y2,x,y
                    };
                    $numbers[$i] = $isX ? round($w - (float) $number, 1) : (float) $number;
                }

                return $c[1] . ' ' . implode(' ', $numbers);
            }, $a[1]);

            return 'd="' . $mirrored . '"';
        }, $attrs);

        return '<path' . $attrs . '/>';
    }, $svg);

    return (string) preg_replace_callback('/(<text\s[^>]*?)>/s', function (array $m) use ($w): string {
        $attrs = $m[1];
        if (preg_match('/(?:^|\s)x="(-?[\d.]+)"/', $attrs, $x) !== 1) {
            return $m[0];
        }

        $attrs = preg_replace('/(?:^|\s)x="-?[\d.]+"/', ' x="' . round($w - (float) $x[1], 1) . '"', $attrs);
        // 默认锚点是 start，属性可能根本没写——这种情况要补上 end，否则文字会向右捅出画布
        $anchor = attr($attrs, 'text-anchor', 'start');
        if ($anchor === 'start') {
            $attrs = preg_match('/(?:^|\s)text-anchor="start"/', $attrs) === 1
                ? preg_replace('/(?:^|\s)text-anchor="start"/', ' text-anchor="end"', $attrs)
                : $attrs . ' text-anchor="end"';
        } elseif ($anchor === 'end') {
            $attrs = preg_replace('/(?:^|\s)text-anchor="end"/', ' text-anchor="start"', $attrs);
        }

        return $attrs . '>';
    }, $svg);
}

/** 解析版面几何：画布宽度 + 所有矩形（卡片、色条、进度条都算障碍物）。 */
function parseLayout(string $svg): array
{
    preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $svg, $viewBox);
    preg_match_all('/<rect\s+([^>]*?)\s*\/>/s', $svg, $matches, PREG_SET_ORDER);

    $rects = [];
    foreach ($matches as $match) {
        $rects[] = [
            (float) attr($match[1], 'x'),
            (float) attr($match[1], 'y'),
            (float) attr($match[1], 'width'),
            (float) attr($match[1], 'height'),
        ];
    }

    return ['w' => (float) ($viewBox[1] ?? 1000), 'rects' => $rects];
}

/**
 * 推导一段文字能用的宽度：
 * 先找包含它的最小矩形当"容器"（卡片），再被容器内部挡在右侧/左侧的矩形收窄（如进度条、色条）。
 * 找不到容器时退化为整幅画布。
 */
function availableWidth(string $attrs, array $layout, float $fontSize, array $siblings = []): float
{
    $x = (float) attr($attrs, 'x');
    $y = (float) attr($attrs, 'y');
    $top = $y - $fontSize * 0.8;
    $bottom = $y + $fontSize * 0.25;

    $container = null;
    foreach ($layout['rects'] as $rect) {
        [$rx, $ry, $rw, $rh] = $rect;
        if ($rx <= $x && $x <= $rx + $rw && $ry <= $y && $y <= $ry + $rh
            && ($container === null || $rw * $rh < $container[2] * $container[3])) {
            $container = $rect;
        }
    }

    // 文字不在任何卡片里（箭头旁的标签、段落注解、图题）时按整幅画布算：
    // 源图里这类标签本来就是压着卡片边缘排的，用"最近矩形"当硬边界会把中文源图也判成溢出。
    $left = 0.0;
    $right = $layout['w'];

    if ($container !== null) {
        [$cx, $cy, $cw, $ch] = $container;
        $left = $cx;
        $right = $cx + $cw;

        foreach ($layout['rects'] as $rect) {
            [$rx, $ry, $rw, $rh] = $rect;
            if ($rx < $cx || $rx + $rw > $cx + $cw || $ry < $cy || $ry + $rh > $cy + $ch) {
                continue;                                // 只看容器内部的矩形
            }
            if ($ry > $bottom || $ry + $rh < $top) {
                continue;
            }

            if ($rx + $rw <= $x) {
                $left = max($left, $rx + $rw);
            } elseif ($rx >= $x) {
                $right = min($right, $rx);
            }
        }
    }

    foreach ($siblings as [$sl, $sr, $st, $sb, $sx]) {
        if (abs($sx - $x) < 0.01 || $st > $bottom || $sb < $top) {
            continue;                                   // 跳过自己与不同行的文字
        }

        if ($sr <= $x) {
            $left = max($left, $sr);
        } elseif ($sl >= $x) {
            $right = min($right, $sl);
        }
    }

    $space = match (attr($attrs, 'text-anchor', 'start')) {
        'middle' => 2 * min($x - $left, $right - $x),
        'end' => $x - $left,
        default => $right - $x,
    };

    return $space - TEXT_PADDING;
}

/** 从属性里取数值，避免 \bwidth 误匹配 stroke-width 这类复合属性名。 */
function attr(string $attrs, string $name, string $default = '0'): string
{
    return preg_match('/(?:^|\s)' . preg_quote($name, '/') . '="([^"]*)"/', $attrs, $m) === 1 ? $m[1] : $default;
}

/** 版面审计：报出每个文件的压缩情况与仍然溢出的条目（译文长度模型下的估算）。 */
function audit(string $lang): void
{
    if ($lang === 'all') {
        $overflows = 0;
        foreach (['zh', ...array_keys(LANGUAGES)] as $code) {
            $output = [];
            $status = 0;
            exec('php ' . escapeshellarg(__FILE__) . ' audit ' . escapeshellarg($code) . ' 2>&1', $output, $status);
            $overflows += (int) preg_match('/溢出 (\d+) 条/', end($output) ?: '', $m) === 1 ? (int) $m[1] : 0;
            echo end($output) . "\n";
        }

        exit($overflows > 0 ? 1 : 0);
    }

    $dir = $lang === 'zh' ? SRC_SVG_DIR : I18N_DIR . "/$lang/images";
    if (!is_dir($dir)) {
        fwrite(STDERR, "目录不存在：$dir\n");
        exit(1);
    }

    $overflows = 0;
    $guarded = 0;
    foreach (svgFiles() as $file) {
        $svg = (string) @file_get_contents($dir . '/' . basename($file));
        if ($svg === '') {
            continue;
        }

        // 版面几何一律取自中文源图，与被审语言无关——build 也是这么算的，两边必须一致。
        // RTL 语言的生成件是镜像过的，几何也要镜像后再比，否则每条都会被判成溢出。
        $source = (string) file_get_contents(SRC_SVG_DIR . '/' . basename($file));
        if (in_array($lang, RTL_LANGS, true) && basename($file) !== 'pet.svg') {
            $source = mirrorGeometry($source);
        }
        $layout = parseLayout($source);
        $sizes = cssFontSizes($source);
        $siblings = textExtents($source, $sizes);

        preg_match_all('/<text([^>]*)>(.*?)<\/text>/s', $svg, $texts, PREG_SET_ORDER);
        foreach ($texts as $text) {
            $base = $sizes[attr($text[1], 'class', '')] ?? null;
            if ($base === null) {
                continue;
            }

            // 缩到下限的字号：模型认为仍放不下，报出来由人工改文案
            if (preg_match('/style="font-size:([\d.]+)px"/', $text[1], $fm) === 1
                && (float) $fm[1] <= $base * MIN_SCALE + 0.05) {
                $guarded++;
            }

            $scale = preg_match('/style="font-size:([\d.]+)px"/', $text[1], $size) === 1 ? ((float) $size[1]) / $base : 1.0;
            $available = availableWidth($text[1], $layout, $base, $siblings);
            $needed = textWidth(html_entity_decode($text[2], ENT_XML1 | ENT_QUOTES, 'UTF-8')) * $base * $scale;

            // 10% 容差：宽度表是静态参考（DejaVu/Noto 度量），用户浏览器字体不同，本就 ±10% 误差，
            // 只报明确放不下的，避免噪声淹没真正需要改文案的条目
            if ($needed > $available * 1.10) {
                $overflows++;
                printf(
                    "  溢出 %s (需 %.0f / 可用 %.0f, 字号 x%.2f) %s\n",
                    basename($file),
                    $needed,
                    $available,
                    $scale,
                    mb_substr(html_entity_decode($text[2], ENT_XML1 | ENT_QUOTES, 'UTF-8'), 0, 34)
                );
            }
        }
    }

    printf("[%s] 溢出 %d 条，触底 %d 条\n", $lang, $overflows, $guarded);
    exit($overflows > 0 ? 1 : 0);
}

/** 从 <style> 中解析类名 => 字号。 */
function cssFontSizes(string $svg): array
{
    preg_match('/<style>(.*?)<\/style>/s', $svg, $style);
    preg_match_all('/\.([A-Za-z0-9_-]+)\s*\{([^}]*)\}/', $style[1] ?? '', $rules, PREG_SET_ORDER);

    $sizes = [];
    foreach ($rules as $rule) {
        if (preg_match('/font-size:\s*([\d.]+)px/', $rule[2], $size)) {
            $sizes[$rule[1]] = (float) $size[1];
        }
    }

    return $sizes;
}

/**
 * 近似显示宽度（单位：em）。系数用 GD + 本机字体实测校准过：
 * Noto Sans CJK 的汉字 1.00、DejaVu Sans 的拉丁字母 0.50 / 西里尔 0.60、Noto Arabic 0.48。
 * 只用于"该不该缩、缩多少"的判断；真正的兜底是 textLength，与字体无关。
 */
function textWidth(string $text): float
{
    static $table = null;
    $table ??= json_decode((string) @file_get_contents(__DIR__ . '/i18n-widths.json'), true) ?: [];

    $width = 0.0;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        $width += $table[codepoint($char)] ?? fallbackWidth($char);
    }

    return $width;
}

/** UTF-8 码点，不依赖 mbstring。 */
function codepoint(string $char): int
{
    $bytes = array_values(unpack('C*', $char));

    return match (count($bytes)) {
        1 => $bytes[0],
        2 => (($bytes[0] & 0x1F) << 6) | ($bytes[1] & 0x3F),
        3 => (($bytes[0] & 0x0F) << 12) | (($bytes[1] & 0x3F) << 6) | ($bytes[2] & 0x3F),
        4 => (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3F) << 12) | (($bytes[2] & 0x3F) << 6) | ($bytes[3] & 0x3F),
        default => 0,
    };
}

/**
 * 宽度表（scripts/i18n-widths.json）之外的字符——新语言或新文案里没出现过的码点。
 * 表由真实字体量出并提交进仓库，因此 build 结果与机器无关，CI 里 rebuild 不会产生 diff。
 */
function fallbackWidth(string $char): float
{
    return match (true) {
        preg_match('/\p{Mn}|\p{Me}/u', $char) === 1 => 0.0,
        $char === ' ' => 0.318,
        preg_match('/[\x{1100}-\x{11FF}\x{2E80}-\x{9FFF}\x{AC00}-\x{D7AF}\x{F900}-\x{FAFF}\x{FF00}-\x{FFEF}]/u', $char) === 1 => 1.04,
        preg_match('/[\x{0400}-\x{04FF}]/u', $char) === 1 => 0.55,
        preg_match('/[\x{0590}-\x{08FF}]/u', $char) === 1 => 0.51,
        preg_match('/[\x{0900}-\x{0DFF}]/u', $char) === 1 => 0.48,
        default => 0.50,
    };
}

/**
 * 源图上每段文字的占位区间 [左, 右, 上, 下, 锚点x]，用来避免译文撞到相邻文字
 * （如底部横条上并列的两段文案——只靠矩形检测发现不了）。
 * 用源文（中文）算占位而不是译文：版面是按源文设计的，这样结果与语言无关、可复现。
 */
function textExtents(string $svg, array $sizes): array
{
    preg_match_all('/<text([^>]*)>(.*?)<\/text>/s', $svg, $texts, PREG_SET_ORDER);

    $extents = [];
    foreach ($texts as $text) {
        $base = $sizes[attr($text[1], 'class', '')] ?? null;
        if ($base === null) {
            continue;
        }

        $x = (float) attr($text[1], 'x');
        $y = (float) attr($text[1], 'y');
        $width = textWidth(html_entity_decode($text[2], ENT_XML1 | ENT_QUOTES, 'UTF-8')) * $base;
        [$left, $right] = match (attr($text[1], 'text-anchor', 'start')) {
            'middle' => [$x - $width / 2, $x + $width / 2],
            'end' => [$x - $width, $x],
            default => [$x, $x + $width],
        };

        $extents[] = [$left, $right, $y - $base * 0.8, $y + $base * 0.25, $x];
    }

    return $extents;
}

function loadTranslations(string $lang): array
{
    $file = I18N_DIR . "/$lang/strings.json";
    if (!is_file($file)) {
        fwrite(STDERR, "缺少 $file，请先从 docs/i18n/strings.template.json 复制并翻译。\n");
        exit(1);
    }

    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        fwrite(STDERR, "$file 不是合法 JSON：" . json_last_error_msg() . "\n");
        exit(1);
    }

    return $data;
}

/** 校验：JSON、SVG 合法性、中文残留、README 存在性与本地链接可达。 */
function verify(string $lang): void
{
    if ($lang === 'all') {
        $GLOBALS['i18nStrictLinks'] = true;   // 全量校验时兄弟语言链接也必须可达
        foreach (array_keys(LANGUAGES) as $code) {
            verify($code);
        }

        return;
    }

    assertLang($lang);
    if ($lang === 'zh') {
        fwrite(STDERR, "zh 是源语言，无需校验。\n");
        exit(1);
    }

    $errors = [];
    $warnings = [];
    $translations = loadTranslations($lang);
    $template = json_decode((string) @file_get_contents(TEMPLATE), true) ?: [];

    $untranslated = array_diff(array_keys($template), array_keys($translations));
    if ($untranslated !== []) {
        $errors[] = sprintf('strings.json 缺 %d 条：%s', count($untranslated), implode(' / ', array_slice($untranslated, 0, 5)));
    }

    $dir = I18N_DIR . "/$lang/images";
    foreach (svgFiles() as $file) {
        $target = $dir . '/' . basename($file);
        if (!is_file($target)) {
            $errors[] = "缺少图片 " . relative($target);
            continue;
        }

        $svg = (string) file_get_contents($target);
        if (@simplexml_load_string($svg) === false) {
            $errors[] = relative($target) . ' XML 非法';
            continue;
        }

        // 残留判定不能只看 \p{Han}：日文译文里的汉字也会被匹配。
        // build() 对未翻译的键原样输出中文，所以“与中文键完全一致且该键含汉字”才是真残留；
        // Laravel / Acl 这类故意保留原文的专有名词不会命中（键里没有汉字）。
        preg_match_all('/<text[^>]*>(.*?)<\/text>/s', $svg, $texts);
        $leftover = array_values(array_filter(
            $texts[1],
            fn (string $text): bool => ($decoded = html_entity_decode($text, ENT_XML1 | ENT_QUOTES, 'UTF-8')) !== ''
                && isset($template[$decoded])
                && preg_match('/\p{Han}/u', $decoded) === 1
        ));
        // 日文与中文共用汉字，「PSR 抽象」「通知」这类词译文与原文一致属正常，
        // 只有大面积未译才算失败，零星几条降级为告警。
        if (count($leftover) > 5) {
            $errors[] = sprintf('%s 疑似未翻译 %d 条：%s', relative($target), count($leftover), implode(' / ', array_slice($leftover, 0, 5)));
        } elseif ($leftover !== []) {
            $warnings[] = sprintf('%s 有 %d 条与中文原文相同（汉字共用可能属正常）：%s', relative($target), count($leftover), implode(' / ', $leftover));
        }
    }

    $readme = I18N_DIR . "/$lang/README.md";
    if (!is_file($readme)) {
        $errors[] = "缺少 " . relative($readme);
    } else {
        $errors = [...$errors, ...linkErrors($readme, $lang)];
        if (!str_contains((string) file_get_contents($readme), '**' . LANGUAGES[$lang] . '**')) {
            $errors[] = relative($readme) . ' 缺少语言切换导航（当前语言需加粗）';
        }
    }

    if ($errors !== []) {
        fwrite(STDERR, "[" . $lang . "] 校验未通过：\n - " . implode("\n - ", $errors) . "\n");
        exit(1);
    }

    printf("[%s] 校验通过：4 张图 + README + 语言导航\n", $lang);
    foreach ($warnings as $warning) {
        echo "  告警：$warning\n";
    }
}

/**
 * README 里的相对链接与图片路径必须真实存在。
 *
 * 语言切换导航指向另外 11 种语言，那些目录可能还没生成（各语言并行翻译），
 * 所以只校验本语言目录之外的链接一律跳过，避免先完成的语言假失败。
 */
function linkErrors(string $readme, string $lang): array
{
    $errors = [];
    $dir = dirname($readme);
    preg_match_all('/!?\[[^\]]*\]\(([^)#]+)(#[^)]*)?\)/', (string) file_get_contents($readme), $links);

    foreach (array_unique($links[1]) as $target) {
        if (preg_match('#^[a-z]+://#i', $target) === 1 || str_starts_with($target, 'mailto:')) {
            continue;
        }

        $path = $dir . '/' . rawurldecode($target);
        $sibling = str_starts_with($path, I18N_DIR . '/') && !str_starts_with($path, I18N_DIR . "/$lang/");
        if ($sibling && !($GLOBALS['i18nStrictLinks'] ?? false)) {
            continue;   // 单语言校验时兄弟语言可能尚未生成；verify all 会全量检查
        }

        if (!file_exists($path)) {
            $errors[] = relative($readme) . " 链接不可达：$target";
        }
    }

    return $errors;
}

function relative(string $path): string
{
    return ltrim(str_replace(ROOT, '', $path), '/');
}
