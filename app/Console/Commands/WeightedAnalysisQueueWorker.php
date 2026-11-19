<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Throwable;

class WeightedAnalysisQueueWorker extends Command
{
    protected $signature = 'analyses:work {--sleep=1 : Seconds to wait when both queues are empty}';

    protected $description = 'Process analysis jobs with weighted Premium/free queue fairness';

    public function handle(Container $container): int
    {
        $worker = $container->make('queue.worker');

        if (! $worker instanceof Worker) {
            throw new RuntimeException('The Laravel queue worker is unavailable.');
        }

        $connectionName = (string) config('queue.default');

        if (config("queue.connections.{$connectionName}.driver") !== 'database') {
            throw new RuntimeException('Weighted analysis processing requires the database queue connection.');
        }

        $workerName = 'weighted-analysis-worker';
        $premiumStreak = 0;
        $sleepSeconds = max(1, (int) $this->option('sleep'));
        $worker->setName($workerName)->setCache(app('cache')->store());

        Worker::popUsing($workerName, function (callable $popJob, string $queue) use (&$premiumStreak, $connectionName): mixed {
            $connection = Queue::connection($connectionName);
            $premiumSize = $connection->size('premium');
            $freeSize = $connection->size('default');

            if ($premiumSize === 0 && $freeSize === 0) {
                return null;
            }

            if ($premiumSize > 0 && ($premiumStreak < 3 || $freeSize === 0)) {
                $job = $popJob('premium');

                if ($job !== null) {
                    $premiumStreak++;

                    return $job;
                }
            }

            if ($freeSize > 0) {
                $job = $popJob('default');

                if ($job !== null) {
                    $premiumStreak = 0;

                    return $job;
                }
            }

            if ($premiumSize > 0) {
                $job = $popJob('premium');

                if ($job !== null) {
                    $premiumStreak++;

                    return $job;
                }
            }

            return null;
        });

        try {
            $this->components->info('Weighted analysis worker berjalan (maksimal 3 Premium untuk 1 gratis). Hentikan dengan Ctrl+C.');

            return $worker->daemon(
                $connectionName,
                'premium,default',
                new WorkerOptions(
                    name: $workerName,
                    sleep: $sleepSeconds,
                    maxTries: 1,
                    timeout: 85,
                )
            );
        } catch (Throwable $exception) {
            Log::error('Weighted analysis worker stopped unexpectedly.', [
                'exception_class' => $exception::class,
            ]);
            throw $exception;
        } finally {
            Worker::popUsing($workerName, null);
        }
    }
}
