# cron-time

> Cron time helping work with group of crons and get information like is executable or duration between opened crons

**Build status**

master: [![Build Status](https://travis-ci.com/Yakubko/cron-time.svg?branch=master)](https://travis-ci.com/Yakubko/cron-time)
[![Coverage Status](https://coveralls.io/repos/github/Yakubko/cron-time/badge.svg?branch=master)](https://coveralls.io/github/Yakubko/cron-time?branch=master)

dev: [![Build Status](https://travis-ci.com/Yakubko/cron-time.svg?branch=dev)](https://travis-ci.com/Yakubko/cron-time)
[![Coverage Status](https://coveralls.io/repos/github/Yakubko/cron-time/badge.svg?branch=dev)](https://coveralls.io/github/Yakubko/cron-time?branch=dev)

## Install

The recommended way to install is via Composer:

```
composer require yakub/cron-time
```

# Main

## Examples

### isExecutable(\$time = 'now')

How to use isExecutable

```php
<?php
$cronTime = \Yakub\CronTime\Main::create([
    ['raw' => '* 8-14 * * mon-fri'],

    ['raw' => '* 12 * * mon-fri', 'open' => false],
    ['raw' => '* 8 * * wed', 'open' => false],
]);

$cronTime->isExecutable('mon 08:00:00');    // Return true
$cronTime->isExecutable('mon 07:59:59');    // Return false

$cronTime->isExecutable('tue 8:00:00');     // Return false
$cronTime->isExecutable('tue 14:59:59');    // Return false

$cronTime->isExecutable('wed 09:00:00');    // Return true
$cronTime->isExecutable('wed 08:59:59');    // Return false
```

### getOpenDuration($from, $to = 'now')

How to get duration between two dates

```php
$cronTime = \Yakub\CronTime\Main::create([
    ['raw' => '* 9-16 * * mon-fri'],

    ['raw' => '* 12 * * thu-fri', 'open' => false]
]);

$cronTime->getOpenDuration('mon 00:00', 'mon 23:59');       // Return 28800 (8h in seconds)
$cronTime->getOpenDuration('fri 00:00', 'fri 23:59');       // Return 25200 (7h in seconds)
$cronTime->getOpenDuration('fri 10:30', 'fri 14:49:12');    // Return 11952 (3h 19m 12s in seconds)
```

### getFutureOpenDateTime($forSeconds, $from = 'now')

How to get future open date time

```php
$cronTime = \Yakub\CronTime\Main::create([
    ['raw' => '* 9-16 * * mon-fri'],

    ['raw' => '* 12 * * thu-fri', 'open' => false]
]);

$cronTime->getFutureOpenDateTime(20, 'mon 9:20:12')->format('H:i:s');               // Return 09:20:32
$cronTime->getFutureOpenDateTime(60 * 60 * 3, 'mon 9:20:12')->format('H:i:s');      // Return 12:20:12

// Over days
$cronTime = \Yakub\CronTime\Main::create([
    ['raw' => '* 9-16 * * mon-fri'],

    ['raw' => '* 10,12,14 * * mon-fri', 'open' => false],
    ['raw' => '* * 12-14 4 ', 'open' => false]
]);

// Return 2020-06-24 11:20:17
$cronTime->getFutureOpenDateTime(60 * 60 * 3 + 5, '2020-06-23 15:20:12')->format('Y-m-d H:i:s');
// Return 2023-10-27 15:20:12
$cronTime->getFutureOpenDateTime(60 * 60 * 24 * 30 * 6, '2020-06-23 15:20:12')->format('Y-m-d H:i:s');
```

### getScheduleForOpenedDays($closestOpenDays = 5, $from = 'now')

How to get schedule for closest opened days

```php
$cronTime = \Yakub\CronTime\Main::create([
    ['raw' => '* 9-16 * * mon-fri'],

    ['raw' => '* * 12,13 mar', 'open' => false],
    ['raw' => '* 12-23 16 mar', 'open' => false],
    ['raw' => '* 12 20 mar', 'open' => false]
]);

/**
 * Return array
 * [
 *     '2020-06-30' => [['from' => 32400, 'to' => 61199]],
 *     '2020-07-01' => [['from' => 32400, 'to' => 61199]],
 *     '2020-07-02' => [['from' => 32400, 'to' => 61199]],
 *     '2020-07-03' => [['from' => 32400, 'to' => 61199]],
 *     '2020-07-06' => [['from' => 32400, 'to' => 61199]]
 * ]
 */
$cronTime->getScheduleForOpenedDays(5, '2020-06-30');

/**
 * Return array
 * [
 *     '2020-03-09' => [['from' => 32400, 'to' => 61199]],
 *     '2020-03-10' => [['from' => 32400, 'to' => 61199]],
 *     '2020-03-11' => [['from' => 32400, 'to' => 61199]],
 *     '2020-03-16' => [
 *         ['from' => 32400, 'to' => 43199]
 *     ],
 *     '2020-03-17' => [['from' => 32400, 'to' => 61199]],
 *     '2020-03-18' => [['from' => 32400, 'to' => 61199]],
 *     '2020-03-19' => [['from' => 32400, 'to' => 61199]],
 *     '2020-03-20' => [
 *         ['from' => 32400, 'to' => 43199],
 *         ['from' => 46800, 'to' => 61199]
 *     ],
 *     '2020-03-23' => [['from' => 32400, 'to' => 61199]],
 * ]
 */
$cronTime->getScheduleForOpenedDays(9, '2020-03-09')
```

## Cron

## Definition

Definition order. When you want set seconds you must use full definitions.

```
  * * * * * * *
  | | | | | | |
  | | | | | | +-- Year                 (range: 1900-3000)
  | | | | | +---- Day of the Week      (range: 1-7 (1 standing for Monday), mon-sun)
  | | | | +------ Month of the Year    (range: 1-12, jan-dec)
  | | | +-------- Day of the Month     (range: 1-31)
  | | +---------- Hour                 (range: 0-23)
  | +------------ Minute               (range: 0-59)
  +-------------- Second (Optional)    (range: 0-59)
```

## Syntax

You can use:

-   \*
-   numbers separated with ,
-   number range separated with -
-   \*/15 modulo range
-   Aliases for months and day of week

More info: http://www.nncron.ru/help/EN/working/cron-format.htm

## Examples

### Definition types

```php
$cron = new Yakub\CronTime\Model\Cron();
// Same as
$cron = new Yakub\CronTime\Model\Cron(['raw' => '*']);
// Same as
$cron = new Yakub\CronTime\Model\Cron(['raw' => '* * * * * * *']);

// Definition without seconds
// Each minute between 8 and 17 hour
$cron = new Yakub\CronTime\Model\Cron(['raw' => '* 8-17']);

// Definition with seconds
// Every 0,15,30,45 second each minute between 8 and 17 hour
$cron = new Yakub\CronTime\Model\Cron(['raw' => '*/15 * 8-17 * * * *']);

// Definition with aliases
$cron = new Yakub\CronTime\Model\Cron(['raw' => '* 8-17 * mar-jun mon,wed-fri']);

// Alternative definition when raw is not setted
$cron = new Yakub\CronTime\Model\Cron([
//    'second' => '*',
//    'minute' => '*',
      'hour' => '8-17',
//    'day' => '*',
//    'month' => '*',
      'dayOfWeek' => 'mon-fri'
//    'year' => '*'
]);
```

### getters

```php
<?php
$cron = new Yakub\CronTime\Model\Cron();

$cron->isOpen();           // Return true
$cron->getSecond();        // Return [['from' => 0, 'to' => 59]]
$cron->getMinute();        // Return [['from' => 0, 'to' => 59]]
$cron->getHour();          // Return [['from' => 0, 'to' => 23]]
$cron->getDay();           // Return [['from' => 1, 'to' => 31]]
$cron->getMonth();         // Return [['from' => 1, 'to' => 12]]
$cron->getDayOfWeek();     // Return [['from' => 1, 'to' => 7]]
$cron->getYear();          // Return [['from' => 1900, 'to' => 3000]]
```

### getFirstRunDateTime(), getLastRunDateTime()

How to get first/last run dateTime

```php
<?php
$cron = new Cron(['raw' => '* 8-14 * * mon-fri']);

$cron->getFirstRunDateTime()->format('Y-m-d H:i:s');    // Return 1900-01-01 08:00:00
$cron->getLastRunDateTime()->format('Y-m-d H:i:s');     // Return 3000-12-26 14:59:59
```

### isExecutable(\$time = 'now')

How to use isExecutable

```php
$cron = new Cron(['raw' => '* 8-14 * * mon-fri']);

$ret = $cron->isExecutable('mon 9:31');     // Return true
$ret = $cron->isExecutable('wed 15:00');    // Return false
```
