#!/usr/bin/env php
<?php

/**
 * Detect test-looking methods that PHP parsed as comments or strings.
 *
 * PHP syntax checks accept an unterminated docblock when a later closing
 * marker finishes it. That once hid a complete PHPUnit method while every
 * syntax check stayed green.
 */

$testRoot = $argv[1] ?? dirname(__DIR__) . '/tests/phpunit';
if (!is_dir($testRoot)) {
  fwrite(STDERR, "check-php-test-discovery: directory not found: $testRoot\n");
  exit(2);
}

$failures = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
  $testRoot,
  FilesystemIterator::SKIP_DOTS
));
foreach ($iterator as $file) {
  if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Test.php')) {
    continue;
  }

  $source = file_get_contents($file->getPathname());
  preg_match_all('/\bpublic\s+function\s+(test[A-Za-z0-9_]+)\s*\(/', $source, $matches);
  $testLookingNames = array_values(array_unique($matches[1]));

  $parsedNames = array();
  $tokens = token_get_all($source);
  $tokenCount = count($tokens);
  for ($i = 0; $i < $tokenCount; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
      continue;
    }
    for ($j = $i + 1; $j < $tokenCount; $j++) {
      if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
        if (str_starts_with($tokens[$j][1], 'test')) {
          $parsedNames[] = $tokens[$j][1];
        }
        break;
      }
    }
  }

  foreach (array_diff($testLookingNames, $parsedNames) as $missingName) {
    $failures[] = $file->getPathname() . "::$missingName";
  }
}

if ($failures) {
  fwrite(STDERR, "Test-looking methods were not parsed by PHP (check surrounding comments):\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, "  $failure\n");
  }
  exit(1);
}

fwrite(STDOUT, "PHP test discovery source check passed.\n");
