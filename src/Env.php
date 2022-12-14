<?php

namespace Phutilities;

class Env
{
  static function load(string $path): void
  {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      $line = trim($line);
      if (strpos($line, '#') === 0) {
        continue;
      }
      $line = rtrim(explode("#", $line, 2)[0]);
      [$name, $value] = explode('=', $line, 2);
      $name = rtrim($name);
      $value = ltrim($value);

      if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
      }
    }
  }

  public static function getEnvOrValue(string $env, array|string $value, bool $local_only = true): array|string
  {
    if($var = getenv($env, $local_only))
      return $var;
    return $value;
  }
}
