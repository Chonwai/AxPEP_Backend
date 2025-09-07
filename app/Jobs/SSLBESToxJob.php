<?php

namespace App\Jobs;

use App\Services\SSLBESToxServices;
use App\Utils\TaskUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SSLBESToxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $task;

    private $request;

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
    public function __construct($task, $request)
    {
        $this->task = $task;
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo 'Running '.$this->task->id." SSL-GCN Task!\n";

        // 可配置的 SSL-GCN 實現：微服務優先，本地 Python 腳本作為後備
        $useSSLGCNMicroservice = (bool) env('USE_SSL_GCN_MICROSERVICE', true);

        Log::info("SSL-GCN 微服務使用狀態: {$useSSLGCNMicroservice}", [
            'task_id' => $this->task->id,
            'methods' => array_keys(array_filter($this->request['methods'])),
        ]);

        foreach ($this->request['methods'] as $key => $value) {
            if ($value == true) {
                echo "Running {$this->task->id} SSL-GCN's $key Task!\n";

                if ($useSSLGCNMicroservice) {
                    try {
                        // 嘗試使用微服務
                        Log::info('嘗試使用 SSL-GCN 微服務', [
                            'task_id' => $this->task->id,
                            'method' => $key,
                        ]);
                        TaskUtils::runSSLBESToxTaskMicroservice($this->task, $key);
                        Log::info('SSL-GCN 微服務調用成功', [
                            'task_id' => $this->task->id,
                            'method' => $key,
                        ]);
                    } catch (\Exception $e) {
                        // 微服務失敗時，回退到本地 Python 腳本
                        Log::error('SSL-GCN 微服務調用失敗，回退到本地 Python 腳本', [
                            'task_id' => $this->task->id,
                            'method' => $key,
                            'error' => $e->getMessage(),
                            'microservice_url' => env('SSL_GCN_MICROSERVICE_BASE_URL', 'not_set'),
                        ]);

                        // 回退到本地腳本
                        TaskUtils::runSSLBESToxTask($this->task, $key);
                        Log::info('SSL-GCN 本地腳本執行完成', [
                            'task_id' => $this->task->id,
                            'method' => $key,
                        ]);
                    }
                } else {
                    // 直接使用本地 Python 腳本
                    Log::info('使用本地 SSL-GCN Python 腳本', [
                        'task_id' => $this->task->id,
                        'method' => $key,
                    ]);
                    TaskUtils::runSSLBESToxTask($this->task, $key);
                }
            } else {
                continue;
            }
        }

        SSLBESToxServices::getInstance()->finishedTask($this->task->id);
    }

    public function failed(?\Exception $e = null)
    {
        echo 'Fail Status:'.$e;
        SSLBESToxServices::getInstance()->failedTask($this->task->id);
    }
}
