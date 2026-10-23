<?php

/**
 * Extraction script to build locales/ticketfilter.pot from PHP and Twig sources.
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$files = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $rel = substr($path, strlen($baseDir) + 1);

    if (
        str_starts_with($rel, 'vendor/') ||
        str_starts_with($rel, 'tests/') ||
        str_starts_with($rel, 'tools/') ||
        str_starts_with($rel, '.git/') ||
        str_starts_with($rel, 'release/')
    ) {
        continue;
    }

    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if ($ext === 'php' || $ext === 'twig') {
        $files[] = $rel;
    }
}

sort($files);

$messages = []; // msgid => ['files' => [rel:line], 'plural' => null]

foreach ($files as $rel) {
    $content = file_get_contents($baseDir . '/' . $rel);
    $lines = explode("\n", $content);

    // 1. Match __("...", "ticketfilter") or __('...', 'ticketfilter')
    // In Twig or PHP
    $patternSingle = '/__\(\s*([\'"])((?:\\\\\\1|.)*?)\1\s*(?:,\s*[\'"]ticketfilter[\'"])?\s*\)/s';
    if (preg_match_all($patternSingle, $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[2] as $idx => $m) {
            $rawMsg = $m[0];
            $offset = $m[1];
            $lineNum = substr_count(substr($content, 0, $offset), "\n") + 1;

            $delimiter = $matches[1][$idx][0];
            $unescaped = ($delimiter === "'") ? str_replace(["\\'", "\\\\"], ["'", "\\"], $rawMsg) : stripcslashes($rawMsg);

            if (!isset($messages[$unescaped])) {
                $messages[$unescaped] = ['files' => [], 'plural' => null];
            }
            $messages[$unescaped]['files'][] = "$rel:$lineNum";
        }
    }

    // 2. Match _n("single", "plural", $n, "ticketfilter")
    $patternPlural = '/_n\(\s*([\'"])((?:\\\\\\1|.)*?)\1\s*,\s*([\'"])((?:\\\\\\3|.)*?)\3\s*,\s*[^,]+,\s*[\'"]ticketfilter[\'"]\s*\)/s';
    if (preg_match_all($patternPlural, $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[2] as $idx => $m) {
            $rawSingle = $m[0];
            $rawPlural = $matches[4][$idx][0];
            $offset    = $m[1];
            $lineNum   = substr_count(substr($content, 0, $offset), "\n") + 1;

            $d1 = $matches[1][$idx][0];
            $d2 = $matches[3][$idx][0];
            $single = ($d1 === "'") ? str_replace(["\\'", "\\\\"], ["'", "\\"], $rawSingle) : stripcslashes($rawSingle);
            $plural = ($d2 === "'") ? str_replace(["\\'", "\\\\"], ["'", "\\"], $rawPlural) : stripcslashes($rawPlural);

            if (!isset($messages[$single])) {
                $messages[$single] = ['files' => [], 'plural' => $plural];
            } else {
                $messages[$single]['plural'] = $plural;
            }
            $messages[$single]['files'][] = "$rel:$lineNum";
        }
    }
}

$date = date('Y-m-d H:iO');

$pot = <<<POT
# TicketFilter Plugin for GLPI
# Copyright (C) 2023-2026 Chris Gralike
# This file is distributed under the same license as the TicketFilter package.
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: TicketFilter 2.0.0\\n"
"Report-Msgid-Bugs-To: https://github.com/DonutsNL/ticketfilter/issues\\n"
"POT-Creation-Date: {$date}\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: Chris Gralike\\n"
"Language-Team: Dutch <https://github.com/DonutsNL/ticketfilter>\\n"
"Language: \\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"


POT;

function escapePo(string $str): string
{
    return str_replace(
        ["\\", '"', "\n", "\r", "\t"],
        ["\\\\", '\"', "\\n\"\n\"", "\\r", "\\t"],
        $str
    );
}

ksort($messages);

foreach ($messages as $msgid => $meta) {
    if (trim($msgid) === '') {
        continue;
    }
    $refs = implode(' ', array_unique($meta['files']));
    $pot .= "#: $refs\n";
    if (str_contains($msgid, '%s') || str_contains($msgid, '%d')) {
        $pot .= "#, php-format\n";
    }

    $pot .= 'msgid "' . escapePo($msgid) . "\"\n";
    if ($meta['plural'] !== null) {
        $pot .= 'msgid_plural "' . escapePo($meta['plural']) . "\"\n";
        $pot .= "msgstr[0] \"\"\n";
        $pot .= "msgstr[1] \"\"\n\n";
    } else {
        $pot .= "msgstr \"\"\n\n";
    }
}

@mkdir($baseDir . '/locales', 0755, true);
file_put_contents($baseDir . '/locales/ticketfilter.pot', $pot);

echo "Successfully wrote " . count($messages) . " strings to locales/ticketfilter.pot\n";
