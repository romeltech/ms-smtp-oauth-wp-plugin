<?php

// Run from the plugin project root: php build.php.
$source = __DIR__.'/grandauto-microsoft-smtp';
$target = __DIR__.'/dist';
if (! is_dir($target)) {
    mkdir($target, 0755, true);
}
$zip = new ZipArchive;
if ($zip->open($target.'/gah-microsoft-oauth-smtp.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Could not create the plugin archive.');
}
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'md'], true)) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
    $zip->addFile($file->getPathname(), 'grandauto-microsoft-smtp/'.$relative);
}
$zip->close();
echo "Built dist/gah-microsoft-oauth-smtp.zip\n";
