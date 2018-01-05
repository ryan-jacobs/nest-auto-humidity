<?php
/**
 * App-specific customizations to the base nest API.
 */

namespace rjacobs\NestAutoHumidity;

class Nest extends \Nest {

    /**
     * Get the outside temperature & humidity, given a location (zip/postal code & optional country code).
     *
     * @param string $postal_code  Zip or postal code
     * @param string $country_code (Optional) Country code
     *
     * @return stdClass
     */
    public function getWeather($postal_code, $country_code = NULL) {
        try {
            $url = "https://home.nest.com/api/0.1/weather/forecast/$postal_code";
            if (!empty($country_code)) {
                $url .= ",$country_code";
            }
            $weather = $this->doGET($url);
        } catch (RuntimeException $ex) {
            // NESTAPI_ERROR_NOT_JSON_RESPONSE is kinda normal. The forecast API will often return a '502 Bad Gateway' response... meh.
            if ($ex->getCode() != NESTAPI_ERROR_NOT_JSON_RESPONSE) {
                throw new RuntimeException("Unexpected issue fetching forecast.", $ex->getCode(), $ex);
            }
        }

        return (object) array(
            'outside_temperature' => isset($weather->now->current_temperature) ? $this->temperatureInUserScale((float) $weather->now->current_temperature) : NULL,
            'outside_humidity'    => isset($weather->now->current_humidity) ? $weather->now->current_humidity : NULL,
            // Add forecast data.
            'forecast' => isset($weather->forecast) ? $weather->forecast : NULL,
        );
    }

    /**
     * Get a list of all the locations configured in the Nest account.
     *
     * @return array
     */
    public function getUserLocations() {
        $this->prepareForGet();
        $structures = (array) $this->last_status->structure;
        $user_structures = array();
        $class_name = get_class($this);
        $topaz = isset($this->last_status->topaz) ? $this->last_status->topaz : array();
        foreach ($structures as $struct_id => $structure) {
            // Nest Protects at this location (structure)
            $protects = array();
            foreach ($topaz as $protect) {
                if ($protect->structure_id == $struct_id) {
                    $protects[] = $protect->serial_number;
                }
            }

            $weather_data = $this->getWeather($structure->postal_code, $structure->country_code);
            $user_structures[] = (object) array(
                'name' => isset($structure->name)?$structure->name:'',
                'address' => !empty($structure->street_address) ? $structure->street_address : NULL,
                'city' => $structure->location,
                'postal_code' => $structure->postal_code,
                'country' => $structure->country_code,
                'outside_temperature' => $weather_data->outside_temperature,
                'outside_humidity' => $weather_data->outside_humidity,
                // Add forecast data.
                'outside_forecast' => $weather_data->forecast,
                'away' => $structure->away,
                'away_last_changed' => date(DATETIME_FORMAT, $structure->away_timestamp),
                'thermostats' => array_map(array($class_name, 'cleanDevices'), $structure->devices),
                'protects' => $protects,
            );
        }
        return $user_structures;
    }
  
}
