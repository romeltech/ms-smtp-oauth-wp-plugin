<?php

// Run from the plugin project root: php build.php.
// Legacy package: php build.php --wp602.
$legacy = ($argv[1] ?? '') === '--wp602';
$archive = $legacy ? 'gah-microsoft-oauth-smtp-wp-6.0.2-php-8.4.zip' : 'gah-microsoft-oauth-smtp.zip';
$source = __DIR__.'/gah-microsoft-oauth-smtp';
$target = __DIR__.'/dist';
if (! is_dir($target)) {
    mkdir($target, 0755, true);
}
$zip = new ZipArchive;
if ($zip->open($target.'/'.$archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Could not create the plugin archive.');
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'md'], true)) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
    if ($legacy && $relative === 'gah-microsoft-oauth-smtp.php') {
        $contents = str_replace(
            [' * Version: 1.0.2', ' * Requires at least: 6.5', ' * Requires PHP: 8.1'],
            [' * Version: 1.0.2-wp602', ' * Requires at least: 6.0.2', ' * Requires PHP: 8.4'],
            file_get_contents($file->getPathname())
        );
        $zip->addFromString('gah-microsoft-oauth-smtp/'.$relative, $contents);
    } elseif ($legacy && $relative === 'includes/Plugin.php') {
        $contents = str_replace(
            "require_once ABSPATH.WPINC.'/PHPMailer/OAuthTokenProvider.php';",
            "require_once __DIR__.'/OAuthTokenProvider.php';",
            file_get_contents($file->getPathname())
        );
        $zip->addFromString('gah-microsoft-oauth-smtp/'.$relative, $contents);
    } elseif ($legacy && $relative === 'MICROSOFT-SMTP.md') {
        $contents = str_replace(
            ['Version 1.0.2.', 'WordPress 6.5 or later, PHP 8.1 or later', '`gah-microsoft-oauth-smtp.zip`'],
            ['Version 1.0.2-wp602.', 'WordPress 6.0.2, PHP 8.4', '`'.$archive.'`'],
            file_get_contents($file->getPathname())
        );
        $zip->addFromString('gah-microsoft-oauth-smtp/'.$relative, $contents);
    } else {
        $zip->addFile($file->getPathname(), 'gah-microsoft-oauth-smtp/'.$relative);
    }
}
if ($legacy) {
    $zip->addFile(__DIR__.'/compatibility/wp602/OAuthTokenProvider.php', 'gah-microsoft-oauth-smtp/includes/OAuthTokenProvider.php');
    $zip->addFile(__DIR__.'/docs/wordpress-6.0.2-compatibility.md', 'gah-microsoft-oauth-smtp/COMPATIBILITY.md');
}
$zip->close();
echo 'Built dist/'.$archive."\n";
