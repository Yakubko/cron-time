<?php
namespace Yakub\CronTime\Model;

/**
 * Link: http://www.nncron.ru/help/EN/working/cron-format.htm
 *     * * * * * * *
 *     | | | | | | |
 *     | | | | | | +-- Year                 (range: 1900-3000)
 *     | | | | | +---- Day of the Week      (range: 1-7 (1 standing for Monday), mon-sun)
 *     | | | | +------ Month of the Year    (range: 1-12, jan-dec)
 *     | | | +-------- Day of the Month     (range: 1-31)
 *     | | +---------- Hour                 (range: 0-23)
 *     | +------------ Minute               (range: 0-59)
 *     +-------------- Second (Optional)    (range: 0-59)
 */
final class Cron {

    const EXECUTABLE_LEVEL_ALL = 1;
    const EXECUTABLE_LEVEL_DAY = 2;

    private $open;
    private $second;
    private $minute;
    private $hour;
    private $day;
    private $month;
    private $dayOfWeek;
    private $year;
    private $hisTimestamp;

    private $firstDay = null;
    private $lastDay = null;

    private static $types = ['second', 'minute', 'hour', 'day', 'month', 'dayOfWeek', 'year'];
    private static $aliases = [
        'month' => [1 => 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'],
        'dayOfWeek' => [1 => 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']
    ];

    public function __construct(array $data = []) {
        $this->parseData($data);
    }

    public function isOpen() { return $this->open; }
    public function getSecond() { return $this->second; }
    public function getMinute() { return $this->minute; }
    public function getHour() { return $this->hour; }
    public function getDay() { return $this->day; }
    public function getMonth() { return $this->month; }
    public function getDayOfWeek() { return $this->dayOfWeek; }
    public function getYear() { return $this->year; }

    public function getHisTimestamp(): array {
        if (is_null($this->hisTimestamp)) {
            $hisTimestamp = [];
            $this->hisTimestamp = [];

            $hours = $this->getHour();
            $minutes = $this->getMinute();
            $seconds = $this->getSecond();

            $fullSeconds = false;
            if (count($seconds) == 1 && $seconds[0]['from'] == 0 && $seconds[0]['to'] == 59) {
                $fullSeconds = true;
            }

            $fullMinutes = false;
            if (count($minutes) == 1 && $minutes[0]['from'] == 0 && $minutes[0]['to'] == 59) {
                $fullMinutes = true;
            }

            $fnCreateTimestampTime = function ($hour, $minute, $second) {
                return $hour * 3600 + $minute * 60 + $second;
            };

            foreach ($hours as $hour) {
                if ($fullMinutes && $fullMinutes) {
                    $hisTimestamp[] = [
                        'from' => $fnCreateTimestampTime($hour['from'], $minutes[0]['from'], $seconds[0]['from']),
                        'to' => $fnCreateTimestampTime($hour['to'], $minutes[0]['to'], $seconds[0]['to'])
                    ];
                } else {
                    for ($h = $hour['from']; $h <= $hour['to']; $h++) {
                        foreach ($minutes as $minute) {
                            if ($fullSeconds) {
                                $hisTimestamp[] = [
                                    'from' => $fnCreateTimestampTime($h, $minute['from'], $seconds[0]['from']),
                                    'to' => $fnCreateTimestampTime($h, $minute['to'], $seconds[0]['to'])
                                ];
                            } else {
                                for ($m = $minute['from']; $m <= $minute['to']; $m++) {
                                    foreach ($seconds as $second) {
                                        $hisTimestamp[] = [
                                            'from' => $fnCreateTimestampTime($h, $m, $second['from']),
                                            'to' => $fnCreateTimestampTime($h, $m, $second['to'])
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Connect neighborhood intervals
            $this->hisTimestamp = array_reduce($hisTimestamp, function ($carry, $item) {
                $lastIndex = count($carry) - 1;
                if (array_key_exists($lastIndex, $carry) && ($carry[$lastIndex]['to'] + 1) == $item['from']) {
                    $carry[$lastIndex]['to'] = $item['to'];
                } else {
                    $carry[] = $item;
                }

                return $carry;
            }, []);
        }

        return $this->hisTimestamp;
    }

    public function getFirstRunDateTime(): \DateTime {
        if (is_null($this->firstDay)) {
            $firstDay =
                reset($this->year)['from'].'-'.
                reset($this->month)['from'].'-'.
                reset($this->day)['from'].' '.
                static::$aliases['dayOfWeek'][reset($this->dayOfWeek)['from']].' '.
                reset($this->hour)['from'].':'.
                reset($this->minute)['from'].':'.
                reset($this->second)['from'];
            $this->firstDay = new \DateTime($firstDay);
        }

        return $this->firstDay;
    }
    public function getLastRunDateTime(): \DateTime {
        if (is_null($this->lastDay)) {
            $lastDay =
                end($this->year)['to'].'-'.
                end($this->month)['to'].'-'.
                end($this->day)['to'].' '.
                'last '.static::$aliases['dayOfWeek'][end($this->dayOfWeek)['to']].' '.
                end($this->hour)['to'].':'.
                end($this->minute)['to'].':'.
                end($this->second)['to'];
            $this->lastDay = new \DateTime($lastDay);
        }

        return $this->lastDay;
    }

    public function isExecutable(string $onTime = null, int $level = Cron::EXECUTABLE_LEVEL_ALL): bool {
        try {
            $date = new \DateTime(is_null($onTime) ? 'now' : $onTime);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid onTime value: '.$onTime);
        }
        if ($level < 1 || $level > 2) {
            throw new \InvalidArgumentException('Invalid level value: '.$level);
        }

        $data = array_combine(static::$types, explode(' ', $date->format('s i H j n N Y')));
        $data = array_map(function ($item) { return (int) $item; }, $data);

        $types = [];
        switch ($level) {
            case self::EXECUTABLE_LEVEL_ALL:
                $types = static::$types;
                break;

            case self::EXECUTABLE_LEVEL_DAY:
                $types = ['day', 'month', 'dayOfWeek', 'year'];
                break;
        }

        foreach ($types as $type) {
            foreach ($this->{$type} as $cronData) {
                if ($data[$type] < $cronData['from'] || $data[$type] > $cronData['to']) {
                    return false;
                }
            }
        }

        return true;
    }

    private function normalizeData(array $data = []): array {
        $data['open'] = (array_key_exists('open', $data)) ? filter_var($data['open'], FILTER_VALIDATE_BOOLEAN) : true;
        if (array_key_exists('raw', $data) && $data['raw']) {
            $data['raw'] = preg_replace('/\s+/', ' ', $data['raw']);
            $data = array_diff_key($data, array_flip(static::$types));

            $rawData = explode(' ', trim($data['raw']));
            // Add optional second parameter
            if (count($rawData) < 7) { array_unshift($rawData, '*'); }
            $rawData = array_slice($rawData + array_fill(0, count(static::$types), '*'), 0, count(static::$types));

            $data+= array_combine(static::$types, $rawData);
        }

        foreach(static::$types as $settingName) {
            if (array_key_exists($settingName, $data) && $data[$settingName]) {
                if (! \is_string($data[$settingName])) {
                    throw new \InvalidArgumentException('Invalid '.$settingName.' range');
                }
            } else {
                $data[$settingName] = '*';
            }
        }

        return $data;
    }

    private function parseData(array $data = []): void {
        $data = $this->normalizeData($data);

        $this->open = $data['open'];

        // TODO: Add ? logic, but probably can by solved by alias as months or day of weeks
        $configs = [
            'second' => ['min' => 0, 'max' => 59],
            'minute' => ['min' => 0, 'max' => 59],
            'hour' => ['min' => 0, 'max' => 23],
            'day' => ['min' => 1, 'max' => 31],
            'month' => [
                'min' => 1,
                'max' => 12,
                'aliases' => array_flip(static::$aliases['month'])
            ],
            'dayOfWeek' => [
                'min' => 1,
                'max' => 7,
                'aliases' => array_flip(static::$aliases['dayOfWeek'])
            ],
            'year' => ['min' => 1900, 'max' => 3000]
        ];

        foreach ($configs as $name => $config) {
            $this->{$name} = [];
            if ($data[$name] == '*') {
                $this->{$name} = [['from' => $config['min'], 'to' => $config['max']]];
            } else if ($data[$name] != '*') {
                $tmpMinute = [];
                $intervals = explode(',', $data[$name]);

                foreach ($intervals as $interval) {
                    if (strpos($interval, '-') !== false) {
                        list($rangeFrom, $rangeTo) = explode('-', $interval);

                        if (array_key_exists('aliases', $config)) {
                            if (array_key_exists(\strtolower($rangeFrom), $config['aliases'])) {
                                $rangeFrom = (string) $config['aliases'][\strtolower($rangeFrom)];
                            }
                            if (array_key_exists(\strtolower($rangeTo), $config['aliases'])) {
                                $rangeTo = (string) $config['aliases'][\strtolower($rangeTo)];
                            }
                        }

                        if (! ctype_digit($rangeFrom) || ! ctype_digit($rangeTo)
                            || $rangeFrom > $rangeTo
                            || $rangeFrom < $config['min'] || $rangeFrom > $config['max'] || $rangeTo < $config['min'] || $rangeTo > $config['max']
                        )  {
                            throw new \InvalidArgumentException('Invalid '.$name.' interval: '.$interval);
                        }

                        $tmpMinute = array_merge($tmpMinute, range($rangeFrom, $rangeTo));
                    } else if (strpos($interval, '*/') === 0) {
                        $modulo = \substr($interval, 2);
                        if (! ctype_digit($modulo) || $modulo == '0' || $modulo < $config['min'] || $modulo >= $config['max']) {
                            throw new \InvalidArgumentException('Invalid '.$name.' interval: '.$interval);
                        }

                        for ($i = $config['min']; $i <= $config['max']; $i++) {
                            if ($i % $modulo === 0) { $tmpMinute[] = $i; }
                        }
                    } else {
                        if (array_key_exists('aliases', $config) && array_key_exists(\strtolower($interval), $config['aliases'])) {
                            $interval = (string) $config['aliases'][\strtolower($interval)];
                        }

                        if (ctype_digit($interval) && $interval >= $config['min'] && $interval <= $config['max']) {
                            $tmpMinute[] = (int) $interval;
                        } else {
                            throw new \InvalidArgumentException('Invalid '.$name.' interval: '.$interval);
                        }
                    }
                }

                $tmpMinute = array_unique($tmpMinute);
                sort($tmpMinute);
                for ($i = 0; $i < count($tmpMinute); $i++) {
                    $increment = 1;
                    $interval = ['from' => $tmpMinute[$i], 'to' => null];

                    while (array_key_exists($i+$increment, $tmpMinute) && $tmpMinute[$i+$increment] == $interval['from']+$increment) {
                        $increment++;
                    }
                    $i+= --$increment;
                    $interval['to'] = $tmpMinute[$i];

                    $this->{$name}[] = $interval;
                }
            }
        }
    }
}