<?php

/**
 * Poller utilities and tools
 */

namespace rjacobs\NestAutoHumidity;

class Poller {

  /**
   * Nest object.
   *
   * @var Nest
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
    $nest = new Nest($nest_settings['username'], $nest_settings['password']);
    return new static($nest, $settings);
  }

  /**
   * Constructor
   */
  public function __construct(Nest $nest, array $settings) {
    $this->nest = $nest;
    $this->settings = $settings;
  }

  /**
   * Poll base structure and device conf.
   */
  public function poll() {
    $structures = $this->getStructures();
    $thermostats = $this->getThermostats();
    foreach ($structures as $structure) {
      $benchmark_temp = $this->getBenchmarkTemp($structure);
      foreach ($structure->thermostats as $thermostat_id) {
        if (isset($thermostats[$thermostat_id])) {
          // Calculate and set target humidity.
          $target = $this->getTargetHumidity($thermostat_id, $benchmark_temp);
          $this->nest->setHumidity($target, $thermostat_id);
        }
      }
    }
  }
  
  /**
   * Get current state info.
   */
  public function info() {
    // Add global info.
    $info = array(
      'default_steps' => $this->settings['steps']['default'],
      'latency_days' => $this->settings['latency_days'],
    );
    $structures = $this->getStructures();
    $thermostats = $this->getThermostats();
    // Add structure-specific info.
    foreach ($structures as $structure_key => $structure) {
      $benchmark_temp = $this->getBenchmarkTemp($structure);
      $info['structures'][$structure_key] = array(
        'name' => $structure->name,
        'benchmark_temp' => $benchmark_temp,
      );
      // Add device-specific info.
      foreach ($structure->thermostats as $thermostat_id) {
        if (isset($thermostats[$thermostat_id])) {
          $info['structures'][$structure_key]['thermostats'][$thermostat_id] = array(
            'where' => $thermostats[$thermostat_id]->where,
            'current_humidity' => $thermostats[$thermostat_id]->current_state->humidity,
            'current_set_target_humidity' => $thermostats[$thermostat_id]->target->humidity,
            'calculated_target_humidity' => $this->getTargetHumidity($thermostat_id, $benchmark_temp),
          );
          if (isset($this->settings['steps'][$thermostat_id])) {
            $info['structures'][$structure_key]['thermostats'][$thermostat_id]['steps'] = $this->settings['steps'][$thermostat_id];
          }
        }
      }
    }
    return $info;
  }

  /**
   * Utility to get bencmark temperature for a structure.
   *
   * @param stdObject $structure
   *   Structure data as returned from the Nest API.
   * @return float
   *   The calculated benchmark temp for the structure.
   */
  protected function getBenchmarkTemp($structure) {
    // Calaculate the lowest temp predicted between now and the number of
    // latency days configured.
    $benchmark = $structure->outside_temperature;
    if (!empty($this->settings['latency_days'])) {
      // Check hourly lows as they are not always accuratly reflected in the
      // daily low temp values)
      foreach ($structure->outside_forecast->hourly as $key => $hour) {
        if ($hour->temp < $benchmark) {
          $benchmark = $hour->temp;
        }
      }
      // Check daily forcast.
      foreach ($structure->outside_forecast->daily as $key => $day) {
        if ($key + 1 > $this->settings['latency_days']) {
          break;
        }
        if ($day->low_temperature < $benchmark) {
          $benchmark = $day->low_temperature;
        }
      }
    }
    return $benchmark;
  }

  /**
   * Utility to get target humidity based on benchmark temp.
   *
   * @param string $thermostat_id
   *   The UUID of a thermostat.
   * @param float $benchmark_temp
   *   The benchmark temp to use when calculating correct humidity step.
   * @return float
   *   The calculated target humidity for the thermostat.
   */
  protected function getTargetHumidity($thermostat_id, $benchmark_temp) {
    $target_humidity = 0;
    $steps = isset($this->settings['steps'][$thermostat_id]) ? $this->settings['steps'][$thermostat_id] : $this->settings['steps']['default'];
    ksort($steps);
    foreach ($steps as $temp => $target_step) {
      if ($benchmark_temp > $temp) {
        $target_humidity = $target_step;
      }
      else {
        break;
      }
    }
    return $target_humidity;
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

}
