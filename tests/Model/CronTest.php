<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Yakub\CronTime\Model\Cron;

final class CronTest extends TestCase {

    public function testCreateWithoutData(): void {
        $cron = new Cron();

        $this->assertTrue($cron->isOpen());
        $this->assertEquals([['from' => 0, 'to' => 59]], $cron->getSecond());
        $this->assertEquals([['from' => 0, 'to' => 59]], $cron->getMinute());
        $this->assertEquals([['from' => 0, 'to' => 23]], $cron->getHour());
        $this->assertEquals([['from' => 1, 'to' => 31]], $cron->getDay());
        $this->assertEquals([['from' => 1, 'to' => 12]], $cron->getMonth());
        $this->assertEquals([['from' => 1, 'to' => 7]], $cron->getDayOfWeek());
        $this->assertEquals([['from' => 1900, 'to' => 3000]], $cron->getYear());
    }

    public function testCreateWithIncompleteRawData(): void {
        $cron = new Cron(['raw' => '*']);

        $this->assertTrue($cron->isOpen());
        $this->assertEquals([['from' => 0, 'to' => 59]], $cron->getSecond());
        $this->assertEquals([['from' => 0, 'to' => 59]], $cron->getMinute());
        $this->assertEquals([['from' => 0, 'to' => 23]], $cron->getHour());
        $this->assertEquals([['from' => 1, 'to' => 31]], $cron->getDay());
        $this->assertEquals([['from' => 1, 'to' => 12]], $cron->getMonth());
        $this->assertEquals([['from' => 1, 'to' => 7]], $cron->getDayOfWeek());
        $this->assertEquals([['from' => 1900, 'to' => 3000]], $cron->getYear());
    }

    public function testCreateWithTooLongRawData(): void {
        $cron = new Cron(['raw' => '* *    * * * * * *']);

        $this->assertTrue($cron->isOpen());
        $this->assertEquals([['from' => 0, 'to' => 59]], $cron->getSecond());
        $this->assertEquals([['from' => 0, 'to' => 59]], $cron->getMinute());
        $this->assertEquals([['from' => 0, 'to' => 23]], $cron->getHour());
        $this->assertEquals([['from' => 1, 'to' => 31]], $cron->getDay());
        $this->assertEquals([['from' => 1, 'to' => 12]], $cron->getMonth());
        $this->assertEquals([['from' => 1, 'to' => 7]], $cron->getDayOfWeek());
        $this->assertEquals([['from' => 1900, 'to' => 3000]], $cron->getYear());
    }

    public function testCreateWithWrongDefinitions(): void {
        $types = [
            'minute' => '',
            'hour' => '* ',
            'day' => '* * ',
            'month' => '* * * ',
            'dayOfWeek' => '* * * * ',
            'year' => '* * * * * '
        ];
        $wrongValues = ['wrong', '-4', '2-', '2-wrong', '70', '1ds-dec', '15-74', '**/15', '*/c', '*/59', '*/0'];

        foreach ($types as $typeName => $prefix) {
            foreach ($wrongValues as $value) {
                $isError = false;
                try {
                    $cron = new Cron(['raw' => $prefix.$value]);
                } catch (\InvalidArgumentException $e) {
                    $isError = true;
                }
                $this->assertTrue($isError);

                $isError = false;
                try {
                    $cronData = [];
                    $cronData[$typeName] = $value;
                    $cron = new Cron($cronData);
                } catch (\InvalidArgumentException $e) {
                    $isError = true;
                }
                $this->assertTrue($isError);
            }
        }
    }

    public function testCreateWithMonthAliases(): void {
        $cron = new Cron(['raw' => '* * * jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,dec']);

        $this->assertEquals([['from' => 1, 'to' => 12]], $cron->getMonth());
    }

    public function testCreateWithDayOfWeekAliases(): void {
        $cron = new Cron(['raw' => '* * * * mon,tue,wed,thu,fri,sat,sun']);

        $this->assertTrue($cron->isOpen());
        $this->assertEquals([['from' => 1, 'to' => 7]], $cron->getDayOfWeek());
    }

    public function testCreateWithCombinedConfiguration(): void {
        $cron = new Cron(['raw' => '1,5,10-20,*/15,55-59']);
        $this->assertEquals(
            [
                ['from' => 0, 'to' => 1],
                ['from' => 5, 'to' => 5],
                ['from' => 10, 'to' => 20],
                ['from' => 30, 'to' => 30],
                ['from' => 45, 'to' => 45],
                ['from' => 55, 'to' => 59]
            ],
            $cron->getMinute()
        );

        $cron = new Cron(['raw' => '* 1,5,19-21,*/5']);
        $this->assertEquals(
            [
                ['from' => 0, 'to' => 1],
                ['from' => 5, 'to' => 5],
                ['from' => 10, 'to' => 10],
                ['from' => 15, 'to' => 15],
                ['from' => 19, 'to' => 21]
            ],
            $cron->getHour()
        );

        $cron = new Cron(['raw' => '* * 1,5,19-31,*/5']);
        $this->assertEquals(
            [
                ['from' => 1, 'to' => 1],
                ['from' => 5, 'to' => 5],
                ['from' => 10, 'to' => 10],
                ['from' => 15, 'to' => 15],
                ['from' => 19, 'to' => 31]
            ],
            $cron->getDay()
        );

        $cron = new Cron(['raw' => '* * * jan,5,10-dec,*/5']);
        $this->assertEquals(
            [
                ['from' => 1, 'to' => 1],
                ['from' => 5, 'to' => 5],
                ['from' => 10, 'to' => 12]
            ],
            $cron->getMonth()
        );

        $cron = new Cron(['raw' => '* * * * mon,3,sat-7,*/5']);
        $this->assertEquals(
            [
                ['from' => 1, 'to' => 1],
                ['from' => 3, 'to' => 3],
                ['from' => 5, 'to' => 7]
            ],
            $cron->getDayOfWeek()
        );

        $cron = new Cron(['raw' => '* * * * * 2020,2022,2030-2040']);
        $this->assertEquals(
            [
                ['from' => 2020, 'to' => 2020],
                ['from' => 2022, 'to' => 2022],
                ['from' => 2030, 'to' => 2040]
            ],
            $cron->getYear()
        );

        $cron = new Cron(['raw' => '1,7,12-15,*/30 * * 1,5,19-31,*/5 * * *']);
        $this->assertEquals(
            [
                ['from' => 0, 'to' => 1],
                ['from' => 7, 'to' => 7],
                ['from' => 12, 'to' => 15],
                ['from' => 30, 'to' => 30]
            ],
            $cron->getSecond()
        );
        $this->assertEquals(
            [
                ['from' => 1, 'to' => 1],
                ['from' => 5, 'to' => 5],
                ['from' => 10, 'to' => 10],
                ['from' => 15, 'to' => 15],
                ['from' => 19, 'to' => 31]
            ],
            $cron->getDay()
        );
    }

    public function testCreateClosedCron(): void {
        $cron = new Cron(['open' => false]);
        $this->assertFalse($cron->isOpen());

        $cron = new Cron(['open' => 'no']);
        $this->assertFalse($cron->isOpen());

        $cron = new Cron(['open' => 'wrong']);
        $this->assertFalse($cron->isOpen());
    }

    public function testCronIsExecutable() {
        $cron = new Cron(['raw' => '* 8-14 * * mon-fri']);

        $ret = $cron->isExecutable('mon 9:31');
        $this->assertTrue($ret);

        $ret = $cron->isExecutable('mon 14:59');
        $this->assertTrue($ret);

        $ret = $cron->isExecutable('mon 15:00', Cron::EXECUTABLE_LEVEL_DAY);
        $this->assertTrue($ret);

        $ret = $cron->isExecutable('mon 15:00');
        $this->assertFalse($ret);

        $ret = $cron->isExecutable('sun 15:00');
        $this->assertFalse($ret);

        $ret = $cron->isExecutable('sun 15:00', Cron::EXECUTABLE_LEVEL_DAY);
        $this->assertFalse($ret);

        $isError = false;
        try {
            $cron->isExecutable('wrong value');
        } catch (\InvalidArgumentException $e) {
            $isError = true;
        }
        $this->assertTrue($isError);

        $isError = false;
        try {
            $cron->isExecutable('now', 0);
        } catch (\InvalidArgumentException $e) {
            $isError = true;
        }
        $this->assertTrue($isError);
    }

    public function testGetFirstLastCronDay() {
        $cron = new Cron(['raw' => '* 8-14 * * mon-fri']);

        $this->assertEquals(
            '1900-01-01 08:00:00',
            $cron->getFirstRunDateTime()->format('Y-m-d H:i:s')
        );

        $this->assertEquals(
            '3000-12-26 14:59:59',
            $cron->getLastRunDateTime()->format('Y-m-d H:i:s')
        );
    }

    public function testGetHisTimestamp() {
        $cron = new Cron(['raw' => '* 8-14']);

        $this->assertEquals(
            // 08:00:00  ->  14:59:59
            [['from' => 28800, 'to' => 53999]],
            $cron->getHisTimestamp()
        );

        $cron = new Cron(['raw' => '0-10,55-59 8-10']);

        $this->assertEquals(
            [
                // 08:00:00  ->  08:10:59
                ['from' => 28800, 'to' => 29459],
                // 08:55:00  ->  09:10:59
                ['from' => 32100, 'to' => 33059],
                // 09:55:00  ->  10:10:59
                ['from' => 35700, 'to' => 36659],
                // 10:55:00  ->  10:59:59
                ['from' => 39300, 'to' => 39599]
            ],
            $cron->getHisTimestamp()
        );

        $cron = new Cron(['raw' => '0-30,45-46 */15 12 * * * *']);

        $this->assertEquals(
            [
                // 12:00:00  ->  12:30:00
                ['from' => 43200, 'to' => 43230],
                // 12:00:45  ->  12:30:46
                ['from' => 43245, 'to' => 43246],

                // 12:15:00  ->  12:15:30
                ['from' => 44100, 'to' => 44130],
                // 12:15:45  ->  12:15:46
                ['from' => 44145, 'to' => 44146],

                // 12:30:00  ->  10:30:30
                ['from' => 45000, 'to' => 45030],
                // 12:30:45  ->  10:30:46
                ['from' => 45045, 'to' => 45046],

                // 12:45:00  ->  12:45:30
                ['from' => 45900, 'to' => 45930],
                // 12:45:45  ->  12:45:46
                ['from' => 45945, 'to' => 45946]
            ],
            $cron->getHisTimestamp()
        );
    }
}
