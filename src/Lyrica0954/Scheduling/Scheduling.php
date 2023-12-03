<?php

declare(strict_types=1);

namespace Lyrica0954\Scheduling;

use Closure;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\scheduler\TaskHandler;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Utils;
use RuntimeException;

class Scheduling {

	private static ?TaskScheduler $globalScheduler = null;

	private TaskScheduler $scheduler;

	private bool $isGlobal;

	private function __construct(TaskScheduler $scheduler) {
		$this->scheduler = $scheduler;
		$this->isGlobal = false;
	}

	public static function initGlobal(TaskScheduler $scheduler): void {
		self::$globalScheduler = $scheduler;
	}

	public static function global(): self {
		$i = new self(self::$globalScheduler ?? throw new RuntimeException("Not initialized"));
		$i->isGlobal = true;

		return $i;
	}

	public static function get(TaskScheduler $scheduler): self {
		return new self($scheduler);
	}

	/**
	 * @return bool
	 */
	public function isGlobal(): bool {
		return $this->isGlobal;
	}

	/**
	 * @param Closure(): void $closure
	 * @param int $period
	 * @return TaskHandler
	 */
	public function repeating(Closure $closure, int $period): TaskHandler {
		return $this->scheduler->scheduleRepeatingTask(
			new class($closure) extends Task {
				private int $tick = 0;

				public function __construct(private readonly Closure $closure) {
				}

				public function getName(): string {
					return "Scheduling[repeating] " . Utils::getNiceClosureName($this->closure);
				}

				public function onRun(): void {
					$this->tick++;
					($this->closure)($this->tick);
				}
			}, $period
		);
	}

	public function forEach(Closure $closure, array $arr, int $period, int $count = 1, ?Closure $finally = null): TaskHandler {
		$finally ??= function(): void {
		};

		return $this->scheduler->scheduleRepeatingTask(
			new class($arr, $count, $closure, $finally) extends Task {

				private int $pointer = 0;

				public function __construct(private array $arr, private readonly int $count, private readonly Closure $consumer, private readonly Closure $finally) {
					$this->arr = array_values($arr);
				}

				public function getName(): string {
					return "Scheduling[forEach] " . Utils::getNiceClosureName($this->consumer);
				}

				public function onRun(): void {
					for ($i = 0; $i < $this->count; $i++) {
						if ($this->pointer >= count($this->arr)) {
							$this->getHandler()?->cancel();
							($this->finally)();

							return;
						}

						$v = $this->arr[$this->pointer];
						$result = ($this->consumer)($v);

						if ($result === false) {
							$this->getHandler()?->cancel();
							($this->finally)();

							return;
						}
						$this->pointer++;
					}
				}
			}, $period
		);
	}

	public function setAfter(&$value, int $sleep, mixed $set): TaskHandler {
		return $this->delayed(function() use (&$value, $set): void {
			$value = $set;
		}, $sleep);
	}

	/**
	 * @param Closure(): void $closure
	 * @param int $delay
	 * @return TaskHandler
	 */
	public function delayed(Closure $closure, int $delay): TaskHandler {
		return $this->scheduler->scheduleDelayedTask(new ClosureTask($closure), $delay);
	}

	/**
	 * @param Closure(int $tick): void $closure
	 * @param int $period
	 * @param int $limit
	 * @return TaskHandler
	 */
	public function limitedRepeating(Closure $closure, int $period, int $limit): TaskHandler {

		$task = new class($closure, $limit) extends Task {
			private int $tick = 0;

			public function __construct(private readonly Closure $closure, private readonly int $limit) {
			}

			public function getName(): string {
				return "Scheduling[limitedRepeating] " . Utils::getNiceClosureName($this->closure);
			}

			public function onRun(): void {
				$this->tick++;

				($this->closure)($this->tick);

				if ($this->tick >= $this->limit) {
					$this->getHandler()?->cancel();
				}
			}
		};

		return $this->scheduler->scheduleRepeatingTask($task, $period);
	}

	/**
	 * @param Closure(int $tick): bool $closure
	 * @param int $period
	 * @param Closure(): void|null $onCancel
	 * @return TaskHandler
	 */
	public function conditionallyRepeating(Closure $closure, int $period, ?Closure $onCancel = null): TaskHandler {

		Utils::validateCallableSignature(function(int $tick): bool {
			return false;
		}, $closure);

		$onCancel ??= function(): void {
		};

		$task = new class($closure, $onCancel) extends Task {
			private int $tick = 0;

			public function __construct(private readonly Closure $closure, private readonly Closure $onCancel) {
			}

			public function getName(): string {
				return "Scheduling[conditionallyRepeating] " . Utils::getNiceClosureName($this->closure);
			}

			public function onRun(): void {
				$this->tick++;

				$result = ($this->closure)($this->tick);

				if (!$result) {
					$this->getHandler()?->cancel();

					($this->onCancel)();
				}
			}
		};

		return $this->scheduler->scheduleRepeatingTask($task, $period);
	}

	public function interruptibleRepeating(Closure $closure, int $period, ?Closure $onInterrupt = null): TaskHandler {
		Utils::validateCallableSignature(function(Closure $interrupt): void {
		}, $closure);

		$onInterrupt ??= function(): void {
		};

		$task = new class($closure, $onInterrupt) extends Task {
			private int $tick = 0;

			public function __construct(private readonly Closure $closure, private readonly Closure $onInterrupt) {
			}

			public function getName(): string {
				return "Scheduling[interruptibleRepeating] " . Utils::getNiceClosureName($this->closure);
			}

			public function onRun(): void {
				$this->tick++;

				$interrupted = false;
				$interrupter = function() use (&$interrupted): void {
					$interrupted = true;
				};

				($this->closure)($interrupter);

				if ($interrupted) {
					$this->getHandler()?->cancel();

					($this->onInterrupt)();
				}
			}
		};

		return $this->scheduler->scheduleRepeatingTask($task, $period);
	}
}
