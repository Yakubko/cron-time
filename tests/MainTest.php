<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Yakub\WorkingTime\Main;

final class MainTest extends TestCase {

    public function testMergeOpenCronIntervals() {
        $workingTime = Main::create([
            ['raw' => '* 8-14 * * mon-fri'],
            ['raw' => '* 7 * * tue'],
            ['raw' => '* 15 * * tue'],
            ['raw' => '0-29 15 * * wed'],
            ['raw' => '50-59 7 * * fri'],
        ]);

        $this->assertTrue($workingTime->isExecutable('mon 14:59:59'));
        $this->assertFalse($workingTime->isExecutable('mon 15:00:00'));

        $this->assertTrue($workingTime->isExecutable('tue 07:00:00'));
        $this->assertFalse($workingTime->isExecutable('tue 06:59:59'));
        $this->assertTrue($workingTime->isExecutable('tue 15:59:59'));
        $this->assertFalse($workingTime->isExecutable('tue 16:00:00'));

        $this->assertTrue($workingTime->isExecutable('wed 15:29:59'));
        $this->assertFalse($workingTime->isExecutable('wed 15:30:00'));

        $this->assertTrue($workingTime->isExecutable('fri 07:50:00'));
        $this->assertFalse($workingTime->isExecutable('fri 07:49:59'));
    }

    public function testMergeCloseCronIntervals() {
        $workingTime = Main::create([
            ['raw' => '* 8-14 * * mon-fri'],

            ['raw' => '* 7 * * mon-fri', 'open' => false],
            ['raw' => '* 15 * * mon-fri', 'open' => false],
            ['raw' => '* * * * tue', 'open' => false],
            ['raw' => '* 12 * * mon-fri', 'open' => false],
            ['raw' => '* 8 * * wed', 'open' => false],
            ['raw' => '* 14 * * wed', 'open' => false]
        ]);

        $this->assertTrue($workingTime->isExecutable('mon 08:00:00'));
        $this->assertFalse($workingTime->isExecutable('mon 07:59:59'));
        $this->assertTrue($workingTime->isExecutable('mon 14:59:59'));
        $this->assertFalse($workingTime->isExecutable('mon 15:00:00'));

        $this->assertFalse($workingTime->isExecutable('tue 8:00:00'));
        $this->assertFalse($workingTime->isExecutable('tue 14:59:59'));
        $this->assertFalse($workingTime->isExecutable('tue 15:00:00'));

        $this->assertTrue($workingTime->isExecutable('wed 09:00:00'));
        $this->assertFalse($workingTime->isExecutable('wed 08:59:59'));
        $this->assertTrue($workingTime->isExecutable('wed 13:59:59'));
        $this->assertFalse($workingTime->isExecutable('wed 14:00:00'));
    }

    public function testAutoOpenInterval(): void {
        $workingTime = Main::create([
            ['raw' => '* 12 * * mon-fri', 'open' => false]
        ]);

        $this->assertTrue($workingTime->isExecutable('mon 08:00:00'));
        $this->assertFalse($workingTime->isExecutable('mon 12:59:59'));
    }

    public function testWrongAttributes() {
        $workingTime = Main::create([
            ['raw' => '* 12 * * mon-fri', 'open' => false]
        ]);

        $isError = false;
        try {
            $workingTime->isExecutable('wrong value');
        } catch (\InvalidArgumentException $e) {
            $isError = true;
        }
        $this->assertTrue($isError);

        $isError = false;
        try {
            $workingTime->getOpenDuration('wrong value');
        } catch (\InvalidArgumentException $e) {
            $isError = true;
        }
        $this->assertTrue($isError);

        $isError = false;
        try {
            $workingTime->getOpenDuration('now', 'wrong value');
        } catch (\InvalidArgumentException $e) {
            $isError = true;
        }
        $this->assertTrue($isError);

        $isError = false;
        try {
            $workingTime->getFutureOpenDateTime(15, 'wrong value');
        } catch (\InvalidArgumentException $e) {
            $isError = true;
        }
        $this->assertTrue($isError);

        $isError = false;
        try {
            $workingTime->getScheduleForOpenedDays(5, 'wrong value');
        } catch (\InvalidArgumentException $e) {
            $isError = true;
        }
        $this->assertTrue($isError);
    }


    public function testGetOpenDuration(): void {
        $workingTime = Main::create([
            ['raw' => '* 9-16 * * mon-fri'],

            ['raw' => '* 12 * * thu-fri', 'open' => false]
        ]);

        $this->assertEquals(
            28800,  // 8h
            $workingTime->getOpenDuration('mon 00:00', 'mon 23:59')
        );

        $this->assertEquals(
            25200,  // 7h
            $workingTime->getOpenDuration('fri 00:00', 'fri 23:59')
        );

        $this->assertEquals(
            11952,  // 3h 19m 12s
            $workingTime->getOpenDuration('fri 10:30', 'fri 14:49:12')
        );
    }

    public function testGetFutureOpenDateTime(): void {
        $workingTime = Main::create([
            ['raw' => '* 9-16 * * mon-fri'],

            ['raw' => '* 12 * * thu-fri', 'open' => false]
        ]);

        $this->assertEquals(
            '09:20:32',
            $workingTime->getFutureOpenDateTime(20, 'mon 9:20:12')->format('H:i:s')
        );

        $this->assertEquals(
            '12:20:12',
            $workingTime->getFutureOpenDateTime(60 * 60 * 3, 'mon 9:20:12')->format('H:i:s')
        );

        $workingTime = Main::create([
            ['raw' => '* 9-16 * * mon-fri'],

            ['raw' => '* 10,12,14 * * mon-fri', 'open' => false],
            ['raw' => '* * 12-14 4 ', 'open' => false]
        ]);

        $this->assertEquals(
            '15:20:12',
            $workingTime->getFutureOpenDateTime(60 * 60 * 3, 'thu 9:20:12')->format('H:i:s')
        );

        $this->assertEquals(
            '2020-06-24 11:20:17',
            $workingTime->getFutureOpenDateTime(60 * 60 * 3 + 5, '2020-06-23 15:20:12')->format('Y-m-d H:i:s')
        );

        $this->assertEquals(
            '2023-10-27 15:20:12',
            $workingTime->getFutureOpenDateTime(60 * 60 * 24 * 30 * 6, '2020-06-23 15:20:12')->format('Y-m-d H:i:s')
        );
    }

    public function testGetScheduleForOpenedDays(): void {
        $workingTime = Main::create([
            ['raw' => '* 9-16 * * mon-fri'],

            ['raw' => '* * 12,13 mar', 'open' => false],
            ['raw' => '* 12-23 16 mar', 'open' => false],
            ['raw' => '* 12 20 mar', 'open' => false]
        ]);

        $this->assertEquals(
            [
                '2020-06-30' => [['from' => 32400, 'to' => 61199]],
                '2020-07-01' => [['from' => 32400, 'to' => 61199]],
                '2020-07-02' => [['from' => 32400, 'to' => 61199]],
                '2020-07-03' => [['from' => 32400, 'to' => 61199]],
                '2020-07-06' => [['from' => 32400, 'to' => 61199]]
            ],
            $workingTime->getScheduleForOpenedDays(5, '2020-06-30')
        );

        $this->assertEquals(
            [
                '2020-03-09' => [['from' => 32400, 'to' => 61199]],
                '2020-03-10' => [['from' => 32400, 'to' => 61199]],
                '2020-03-11' => [['from' => 32400, 'to' => 61199]],
                '2020-03-16' => [
                    ['from' => 32400, 'to' => 43199]
                ],
                '2020-03-17' => [['from' => 32400, 'to' => 61199]],
                '2020-03-18' => [['from' => 32400, 'to' => 61199]],
                '2020-03-19' => [['from' => 32400, 'to' => 61199]],
                '2020-03-20' => [
                    ['from' => 32400, 'to' => 43199],
                    ['from' => 46800, 'to' => 61199]
                ],
                '2020-03-23' => [['from' => 32400, 'to' => 61199]],
            ],
            $workingTime->getScheduleForOpenedDays(9, '2020-03-09')
        );
    }
}
