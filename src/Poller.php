<?php
/**
 * Poller utilities and tools
 */

namespace rjacobs\NestAutoHumidity;

class Poller {

  /**
   * Nest object.
   *
   * @var \Nest
   */
  private $nest;

  /**
   * Global settings.
   *
   * @var array
   */
  private $settings;

  /**
   * Device data for caching purposes.
   *
   * @var array
   */
  private $thermostats;
  
  /**
   * Structure data for caching purposes.
   *
   * @var array
   */
  private $structures;
  
  
  /**
   * Factory
   */
  public static function create() {
    $settings = \Spyc::YAMLLoad(realpath(__DIR__ . '/../conf/settings.yml'));
    $nest_settings = $settings['nest'];
    $nest = new \Nest($nest_settings['username'], $nest_settings['password']);
    return new static($nest, $settings);
  }

  /**
   * Constructor
   */
  public function __construct(\Nest $nest, $settings) {
    $this->nest = $nest;
    $this->settings = $settings;
  }

  /**
   * Poll base structure and device conf.
   */
  public function poll() {
    // Update thermostat data.
    $devices = $this->getThermostats();
    foreach ($devices as $device_info) {
      print $device_info->serial_number;
    }
  }

  /**
   * Utility to get device data for all thermostats.
   *
   * @return array
   *   Array keyed by thermostat serial number with full device data for each
   *   thermostat.
   */
  private function getThermostats() {
    // @todo, consider if we need this caching. It's possible that this is
    // redundant to the API's own cache. Unlike a getUserLocations() request,
    // these calls do not seem to create redundant REST requests.
    if (!$this->thermostats) {
      $devices = $this->nest->getDevices(DEVICE_TYPE_THERMOSTAT);
      foreach ($devices as $thermostat) {
        $this->thermostats[$thermostat] = $this->nest->getDeviceInfo($thermostat);
      }
    }
    return $this->thermostats;
  }
  
  /**
   * Utility to get and cache structure data including weather.
   *
   * The Nest API may perform some redundant remote REST requests upon each
   * getUserLocations() call, such as weather lookups, so this method wraps that
   * call with some caching.
   *
   * @return array
   *   Indexed array of structure data.
   */
  private function getStructures() {
    if (!$this->structures) {
      $this->structures = $this->nest->getUserLocations();
    }
    return $this->structures;
  }

  /**
   * Utility to get celsius temperature independent of the user's scale.
   *
   * When fetching tempatures the Nest API always returns values in the user's
   * scale. However, we want to store everything in a standard celsius and then
   * convert at dispaly time.
   *
   * @param float $temp
   *   The temperature specified in the user's scale.
   * @param string $serial_number
   *   The serial number of the thermostat that this temperature value came
   *   from. This is needed to determine the user's scale. If NULL the scale of
   *   the first thermostat will be used.
   * @return float
   *   The temperature in celsius.
   */
  private function temp($temp, $serial_number = NULL) {
    $temp_scale = $this->nest->getDeviceTemperatureScale($serial_number);
    if ($temp_scale == 'F') {
      $temp = 5/9 * ($temp - 32);
    }
    return $temp;
  }
  
}
