<?php

declare(strict_types=1);

$root = realpath(dirname(__DIR__));
if ($root === false) {
    fwrite(STDERR, "Nao foi possivel localizar a raiz do projeto.\n");
    exit(2);
}

$scanRoots = [$root . '/modules', $root . '/includes', $root . '/api'];
$rootFiles = [$root . '/index.php', $root . '/login.php', $root . '/logout.php'];
$files = $rootFiles;

foreach ($scanRoots as $scanRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], true)) {
            $files[] = $file->getPathname();
        }
    }
}

$issues = [];
$referencePattern = <<<'REGEX'
~(?:href\s*=|action\s*=|fetch\s*\(|Location:\s*|window\.location(?:\.href)?\s*=)[^\n]*?["'`]([^"'`]*?\.php)(?:[?#][^"'`]*)?["'`]~i
REGEX;

foreach ($files as $source) {
    $contents = file_get_contents($source);
    if ($contents === false || !preg_match_all($referencePattern, $contents, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($matches[1] as [$reference, $offset]) {
        if (str_contains($reference, '?>')) {
            $reference = substr($reference, strrpos($reference, '?>') + 2);
        }
        if ($reference === '' || preg_match('~^(?:https?:)?//~i', $reference)) {
            continue;
        }

        // Fragmentos concatenados a BASE_URL comecam na raiz da aplicacao.
        if ($reference[0] === '/') {
            $target = $root . $reference;
        } else {
            $target = dirname($source) . '/' . $reference;
        }

        $target = preg_replace('~[?#].*$~', '', $target);
        $resolvedDirectory = realpath(dirname($target));
        $normalizedTarget = $resolvedDirectory !== false
            ? $resolvedDirectory . '/' . basename($target)
            : $target;

        if (!is_file($normalizedTarget)) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $issues[] = sprintf(
                '%s:%d -> %s',
                substr($source, strlen($root) + 1),
                $line,
                $reference
            );
        }
    }
}

$issues = array_values(array_unique($issues));
sort($issues);

if ($issues !== []) {
    fwrite(STDERR, "Referencias internas para arquivos inexistentes:\n");
    fwrite(STDERR, implode("\n", $issues) . "\n");
    exit(1);
}

echo "Links internos literais validados: nenhum destino PHP inexistente.\n";
