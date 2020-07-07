<?php
namespace Yakub\CronTime;

/**
 * Main class
 *
 * @author yakub
 */
final class Main {

	private $crons = [];

	public static function create(array $crons = []): Main {
		return new static($crons);
	}

	protected function __construct(array $crons = []) {
		$this->setCrons($crons);
	}

	public function setCrons(array $crons = []): void {
		$this->crons = [];
		$autoOpenInterval = true;

		foreach ($crons as $cron) {
			$cronObject = new Model\Cron($cron);
			$autoOpenInterval = ! (! $autoOpenInterval || $cronObject->isOpen());

			$this->crons[] = $cronObject;
		}

		if ($autoOpenInterval) {
			array_unshift($this->crons, new Model\Cron());
		}
	}

	public function isExecutable(string $time = 'now'): bool {
		try {
			$date = new \DateTime($time);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid time value: '.$time);
        }

		$ret = false;
		$dateTime = $this->timeToSec($date->format('H:i:s'));

		$this->walkThroughDays($date, $date, function ($currentYMD, $currentTimes) use (& $ret, $dateTime) {
			foreach ($currentTimes as $time) {
				if ($dateTime <= $time['to'] && $dateTime >= $time['from']) {
					$ret = true;
					return true;
				}
			}
		});

		return $ret;
	}

	public function getOpenDuration(string $from, string $to = 'now'): int {
		try {
			$toDate = new \DateTime($to);
			$fromDate = new \DateTime($from);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid from or to value: '.$from.' '.$to);
        }

		$toYMD = $toDate->format('Y-m-d');
		$fromYMD = $fromDate->format('Y-m-d');
		$ret = 0;

		$this->walkThroughDays($fromDate, $toDate,  function ($currentYMD, $currentTimes) use (& $ret, $fromYMD, $toYMD, $fromDate, $toDate) {
			$fromTime = 0;
			if ($currentYMD == $fromYMD) {
				$fromTime = $this->timeToSec($fromDate->format('H:i:s'));
			}
			$toTime = 86399;
			if ($currentYMD == $toYMD) {
				$tmpToTime = $this->timeToSec($toDate->format('H:i:s'));
				if ($tmpToTime > 0) { $toTime = $tmpToTime; }
			}

			foreach ($currentTimes as $time) {
				if ($fromTime <= $time['to'] && $toTime >= $time['from']) {
					$ret+= min($toTime, $time['to'] + 1) - max($fromTime, $time['from']);
				}
			}
		});

		return $ret;
	}

	public function getFutureOpenDateTime(int $forSeconds, string $from = 'now'): ?\DateTime {
		try {
			$fromDate = new \DateTime($from);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid from value: '.$from);
		}

		$ret = null;
		$mySeconds = 0;
		$fromYMD = $fromDate->format('Y-m-d');
		$safeBreak = 0;

		do {
			$toDate = clone $fromDate;
			$toDate->add(new \DateInterval('P7D'));
			$this->walkThroughDays($fromDate, $toDate,  function ($dateYMD, $times) use (& $ret, & $mySeconds, $forSeconds, $fromYMD, $fromDate) {

				$fromTime = 0;
				if ($dateYMD == $fromYMD) {
					$fromTime = $this->timeToSec($fromDate->format('H:i:s'));
				}

				foreach ($times as $time) {
					if ($fromTime) {
						if ($time['to'] <= $fromTime) {
							continue;
						}
						else if ($fromTime > $time['from'] && $fromTime < $time['to']) {
							$time['from'] = $fromTime;
						}
					}

					$timeDuration = $time['to'] - $time['from'];
					if ($timeDuration + $mySeconds < $forSeconds) {
						$mySeconds+= $timeDuration + 1;
					} else {
						$ret = new \DateTime($dateYMD.' +'.($time['from']+($forSeconds-$mySeconds)).' seconds');
						return true;
					}
				}
			});
			$fromDate->add(new \DateInterval('P8D'));
		} while (is_null($ret) && ++$safeBreak < 1000);

		return $ret;
	}

	public function getScheduleForOpenedDays(int $closestOpenDays = 5, string $from = 'now'): array {
		try {
			$fromDate = new \DateTime($from);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid from value: '.$from);
		}

		$ret = [];
		$safeBreak = 0;

		do {
			$toDate = clone $fromDate;
			$toDate->add(new \DateInterval('P7D'));
			$this->walkThroughDays($fromDate, $toDate, function ($dateYMD, $times) use (& $ret, $closestOpenDays) {
				if (count($times)) {
					$ret[$dateYMD] = $times;
				}

				if (count($ret) == $closestOpenDays) {
					return true;
				}
			});
			$fromDate->add(new \DateInterval('P8D'));
		} while (count($ret) < $closestOpenDays && ++$safeBreak < 1000);

		return $ret;
	}

	private function walkThroughDays(\DateTime $from, \DateTime $to, callable $callback): void {
		$toYMD = $to->format('Y-m-d');
		$current = clone $from;
		$currentYMD = $current->format('Y-m-d');
		$currentIntervals = [];

		while ($currentYMD <= $toYMD) {
			$currentIntervals = [];
			foreach ($this->crons as $cron) {
				if ($cron->isExecutable($currentYMD, Model\Cron::EXECUTABLE_LEVEL_DAY)) {
					foreach ($cron->getHisTimestamp() as $hmsInterval) {
						$currentIntervals = $this->addNewInterval($currentIntervals, $hmsInterval, $cron->isOpen());
					}
				}
			}

			if (! is_null($callback($currentYMD, $currentIntervals))) {
				break;
			}

			$current->add(new \DateInterval('P1D'));
			$currentYMD = $current->format('Y-m-d');
		}
	}

	private function addNewInterval(array $times, array $addTime, bool $open): array {
		foreach ($times as $key => $time) {
			if ($addTime['from'] <= ($time['to'] + 1) && ($addTime['to'] + 1) >= $time['from']) {
				if ($open) {
					$addTime['from'] = ($addTime['from'] < $time['from']) ? $addTime['from'] : $time['from'];
					$addTime['to'] = ($addTime['to'] > $time['to']) ? $addTime['to'] : $time['to'];
					unset($times[$key]);
				} else {
					// out
					if ($addTime['from'] >= $time['to'] || $addTime['to'] <= $time['from']) {
						continue;
					}
					// over
					else if ($addTime['from'] <= $time['from'] && $addTime['to'] >= $time['to']) {
						unset($times[$key]);
					}
					// in
					else if ($addTime['from'] > $time['from'] && $addTime['to'] < $time['to']) {
						$originTime = $time;
						$originTime['from'] = $addTime['to'] + 1;

						$times[$key]['to'] = $addTime['from'] - 1;
						$times[] = $originTime;
						break;
					}
					// cross
					else {
						$times[$key]['from'] = ($addTime['to'] < $time['to']) ? $addTime['to'] + 1 : $time['from'];
						$times[$key]['to'] = ($addTime['from'] > $time['from']) ? $addTime['from'] - 1 : $time['to'];
					}
				}

				return $this->addNewInterval($times, $addTime, $open);
			}
		}

		if ($open) { $times[] = $addTime; }
		return $times;
	}

	private function timeToSec(string $string): int {
		$hours = $minutes = $seconds = 0;
		sscanf($string, "%d:%d:%d", $hours, $minutes, $seconds);
		return $hours * 3600 + $minutes * 60 + $seconds;
	}
}