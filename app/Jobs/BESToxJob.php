<?php

namespace App\Jobs;

use App\Services\BESToxServices;
use App\Utils\TaskUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BESToxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $task;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 7200;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($task)
    {
        $this->task = $task;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo 'Running '.$this->task->id." BESTox Task!\n";

        // 可配置的 BESTox 實現：微服務優先，本地 Python 腳本作為後備
        $useBESToxMicroservice = (bool) env('USE_BESTOX_MICROSERVICE', true);

        Log::info("BESTox 微服務使用狀態: {$useBESToxMicroservice}");

        if ($useBESToxMicroservice) {
            try {
                // 嘗試使用微服務
                Log::info("嘗試使用 BESTox 微服務，TaskID: {$this->task->id}");
                TaskUtils::runBESToxMicroservice($this->task);
                Log::info("BESTox 微服務調用成功，TaskID: {$this->task->id}");
            } catch (\Exception $e) {
                // 微服務失敗時，回退到本地 Python 腳本
                Log::error("BESTox 微服務調用失敗，回退到本地 Python 腳本，TaskID: {$this->task->id}, 錯誤: {$e->getMessage()}");
                Log::error('微服務 URL: '.env('BESTOX_MICROSERVICE_BASE_URL', 'not_set'));
                TaskUtils::runBESToxTask($this->task);
            }
        } else {
            // 直接使用本地 Python 腳本
            Log::info("使用本地 BESTox Python 腳本，TaskID: {$this->task->id}");
            TaskUtils::runBESToxTask($this->task);
        }

        BESToxServices::getInstance()->finishedTask($this->task->id);
    }

    public function failed(?\Exception $e = null)
    {
        echo 'Fail Status:'.$e;
        BESToxServices::getInstance()->failedTask($this->task->id);
    }
}
