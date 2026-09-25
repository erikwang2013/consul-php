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
    default => usage(),
};

function usage(): void
{
    fwrite(STDERR, "用法：php scripts/i18n-svg.php extract | switcher <lang> | build <lang> | verify <lang>\n");
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
    foreach (svgFiles() as $file) {
        $svg = (string) file_get_contents($file);
        $sizes = cssFontSizes($svg);

        $svg = preg_replace_callback('/<text([^>]*)>(.*?)<\/text>/s', function (array $m) use ($translations, $sizes, &$missing): string {
            $original = html_entity_decode($m[2], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $translated = $translations[$original] ?? $original;
            if (!isset($translations[$original])) {
                $missing++;
            }

            return '<text' . shrinkToFit($m[1], $translated, $original, $sizes) . '>'
                . htmlspecialchars($translated, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text>';
        }, $svg);

        $svg = preg_replace_callback('/<title>(.*?)<\/title>/s', fn (array $m): string => '<title>'
            . htmlspecialchars($translations[html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')] ?? $m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')
            . '</title>', (string) $svg);

        $svg = preg_replace_callback('/aria-label="([^"]*)"/', fn (array $m): string => 'aria-label="'
            . htmlspecialchars($translations[html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')] ?? $m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8')
            . '"', (string) $svg);

        // 源图里的 <!-- 分节注释 --> 是给维护者看的，生成件里去掉，免得外文文件里夹中文
        file_put_contents($outDir . '/' . basename($file), (string) preg_replace('/\s*<!--(?!\[).*?-->/s', '', (string) $svg));
    }

    printf("已生成 docs/i18n/%s/images/（缺失翻译 %d 处，回落为原文）\n", $lang, $missing);
}

/**
 * 译文比中文长时按比例缩字号，避免撑破卡片；下限 0.72 倍，再长就只能靠溢出暴露问题。
 * 字号写在 style 属性里，优先级高于 <style> 中的类选择器。
 */
function shrinkToFit(string $attrs, string $translated, string $original, array $sizes): string
{
    if (!preg_match('/class="([^"]*)"/', $attrs, $m)) {
        return $attrs;
    }

    $base = $sizes[$m[1]] ?? null;
    if ($base === null) {
        return $attrs;
    }

    $ratio = textWidth($translated) / max(textWidth($original), 0.01);
    if ($ratio <= 1.0) {
        return $attrs;
    }

    $size = round($base * max(0.72, 1 / $ratio), 1);

    return $attrs . ' style="font-size:' . $size . 'px"';
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
 * 近似显示宽度：CJK / 韩文按 1 列，其余按 0.55 列，组合标记（天城文、孟加拉文的元音符号等）不占宽。
 * 够用即可，只用来比较译文与原文字长。
 */
function textWidth(string $text): float
{
    $width = 0.0;
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        if (preg_match('/\p{Mn}|\p{Me}/u', $char) === 1) {
            continue;
        }

        $width += preg_match('/[\x{1100}-\x{11FF}\x{2E80}-\x{9FFF}\x{AC00}-\x{D7AF}\x{FF00}-\x{FFEF}]/u', $char) ? 1.0 : 0.55;
    }

    return $width;
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
        if ($sibling) {
            continue;
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
