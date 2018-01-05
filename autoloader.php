<?php
/**
 * Project autoloader
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(function ($class) {
    $prefix = 'rjacobs\\NestAutoHumidity\\';
    $len = strlen($prefix);
    $lib_map = array(
      'Nest' => '/src/Libs/Nest/nest.class',
      'Spyc' => '/src/Libs/Spyc/Spyc'
      );
    // Project-prefixed classes use the root src dir.
    if (strncmp($prefix, $class, $len) === 0) {
      $relative_class = substr($class, $len);
      $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
    }
    // Non-project-pefixed classes may be mapped to specific directories.
    elseif (array_key_exists($class, $lib_map)) {
      $file = __DIR__ . $lib_map[$class] . '.php';
    }
    // Otherwise move to next registered autoloader.
    else {
      return;
    }
    if (file_exists($file)) {
      require $file;
    }
});
