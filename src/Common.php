<?php
/**
 * Common utilities and tools
 */

namespace rjacobs\NestHistory;

class Common {

  /**
   * Loaded settings used for static caching purposes.
   *
   * @var array
   */
  static private $settings = array();

  /**
   * Get global settings.
   *
   * @param string $group
   *   Optional. Define a specific group of settings.
   * @return array
   *   If group is defined, the group-speific settings are returned, otherwise
   *   all settings are returned.
   */
  public static function settings($group = NULL) {
    // Only parse settings from yaml once per request.
    if (!static::$settings) {
      static::$settings = \Spyc::YAMLLoad(realpath(__DIR__ . '/../conf/settings.yml'));
    }
    if ($group) {
      return isset(static::$settings[$group]) ? static::$settings[$group] : NULL;
    }
    return static::$settings;
  }

  /**
   * Get database object.
   *
   * @return \MysqliDb
   *   The master database object initiated with global connection settings.
   */
  public static function db() {
    $db_settings = self::settings('db') + array(
      'port' => 3306,
      'prefix' => '',
      'charset' => 'utf8');
    return new \MysqliDb($db_settings);
 }

  /**
   * Get nest object.
   *
   * @return \Nest
   *   The master nest object initiated with credentials.
   */
  public static function nest() {
    $nest_settings = self::settings('nest');
    return new \Nest($nest_settings['username'], $nest_settings['password']);
  }

  /**
   * Get poller object.
   *
   * @return Poller
   *   The poller utility.
   */
   public static function poller() {
     return new Poller(self::db(), self::nest());
   }
}
