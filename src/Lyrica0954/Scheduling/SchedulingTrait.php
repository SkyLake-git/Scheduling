<?php

declare(strict_types=1);

namespace Lyrica0954\Scheduling;

use pocketmine\scheduler\TaskHandler;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\Server;
use ReflectionClass;

trait SchedulingTrait {

	protected Scheduling $scheduling;

	private TaskScheduler $taskScheduler;

	private TaskHandler $schedulerHeartbeatTask;

	/**
	 * @return Scheduling
	 */
	public function getScheduling(): Scheduling {
		return $this->scheduling;
	}

	protected function initSchedulingTrait(): void {
		$this->taskScheduler = new TaskScheduler("Scheduling Trait: " . (new ReflectionClass($this))->getName());
		$this->schedulerHeartbeatTask = Scheduling::global()->repeating(function(): void {
			$this->taskScheduler->mainThreadHeartbeat(Server::getInstance()->getTick());
		}, 1);

		$this->scheduling = Scheduling::get($this->taskScheduler);
	}

	protected function disposeSchedulingTrait(): void {
		$this->taskScheduler->cancelAllTasks();
		$this->schedulerHeartbeatTask->cancel();
	}
}
