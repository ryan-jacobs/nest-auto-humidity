# Nest Auto Humidity (Condensation/Ice Blocker)

For a smart device, a Nest thermostat is not very "smart" at managing humidity levels relative to exterior conditions in cold and dry weather. Nest allows users to set a static target humidity level but homes in very cold or irregular climates will benefit from a more variable humidity level that is relative to exterior temperature.<sup>[1](https://www.hvac.com/faq/recommended-humidity-level-home/),[2](http://www.startribune.com/fixit-what-is-the-ideal-winter-indoor-humidity-level/11468916/),[3](https://www.hunker.com/13416128/recommended-humidity-based-on-the-temperature-in-the-house)</sup>

This PHP project leverages the Nest API to track exterior temperatures and uses this information to automatically adjust interior humidity based on configurable steps. The goal is to regulate interior humidity appropriately as exterior temperatures fall to prevent condensation near exterior surfaces such as windows. The process takes into account the weather forecast to predict needed changes in advance and allow adequate time for humidity adjustments to take effect.

## Dependencies

 * 2nd generation or later Nest thermostat wired to a whole-house humidifier
 * Active Nest account
 * PHP >= 5.3
 * A way to trigger a php script as a scheduled task (e.g. cron)

## Installation and Configuration

### Initialize settings using settings.yml.example as a guide

```
cp /path/to/project/conf/settings.yml.example /path/to/project/conf/settings.yml
vi /path/to/project/conf/settings.yml
```

Set values as follows:

 * In the "nest" section enter your username and password. These values are used by the [Nest API](https://github.com/gboudreau/nest-api) to establish a connection to your Nest account.
 * Set the "latency_days" value to match the number of days, on average, that you expect it takes for minor changes in relative humidity settings (+/- 10%) to stabilize. This will vary depending on the size of your home, how tightly-sealed it is, the output of your humidifier, etc. This value is used to determine how many days into the future to check the weather forecast. Within this range the **lowest** temperature value found will be the **reference temperature** used to set the current target humidity level.
 * Adjust the "steps" value to match the relative humidity levels that you prefer at various exterior temperatures. Note that:
     * Each step is in the form `exterior temperature: indoor relative humidity`, where the temperature scale (celsius vs fahrenheit) is based on whatever scale your thermostat(s) are configured to use. When the reference temperature (see above) moves from the range of one step to another the associated relative humidity target value for that step will be set on the thermostat.
     * Steps can be declared globally or on a thermostat-by-thermostat basis. To differentiate the step values used for a specific thermostat simply group the steps by that thermostat's Nest ID. Any thermostats without custom steps defined will use the "default" steps.

Below is an example `settings.yml` file the declares conservative steps for one thermostat (ID: 123456789) and more liberal default steps for all others (with fahrenheit scale):

```
nest:
  username: ****
  password: ****
latency_days: 2
steps:
  123456789:
    -20: 10
    0: 15
    20: 30
    30: 35
  default:
    -20: 10
    -10: 20
    0: 25
    10: 35
    20: 40
    50: 50
```

### Manually verify that everything is working

Call `info.php` to check the status of your setup:

```
/path/to/php /path/to/project/info.php
```

If everything is working correctly you will see your base settings (latency_days, default steps) and the status output for each thermostat on your account (detailing current/target humidity settings, etc.) printed to the screen. If any Nest API connection errors are detected they will be printed to the screen instead.

After verifying your settings, run the poller once and re-check the status:

```
/path/to/php /path/to/project/poll.php
/path/to/php /path/to/project/info.php
```

If polling was successful the `current_set_target_humidity` will match the `calculated_target_humidity` values reported for each thermostat.

### Setup auto-polling

Configure polling to happen automatically on a specific interval. This interval does not have to be very frequent. For example, if using cron an entry similar to the following could be added to your crontab to trigger polling every 3 hours:

```
0 */3 * * * /path/to/php /path/to/project/poll.php > /tmp/cron.out 2>&1
```

## Built With

* [Nest API](https://github.com/gboudreau/nest-api) - To communicate with Nest API
* [Spyc](https://github.com/mustangostang/spyc) - For parsing yml configuration

## Authors

* **Ryan Jacobs** - *Initial work* - [ryan-jacobs.net](http://www.ryan-jacobs.net)