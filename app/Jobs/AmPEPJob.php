<?php

namespace App\Jobs;

use App\Services\TasksServices;
use App\Utils\TaskUtils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AmPEPJob implements ShouldQueue
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
        foreach ($this->request['methods'] as $key => $value) {
            if ($value == true) {
                if ($key === 'ampep') {
                    // 可配置的AmPEP實現：微服務優先，本地R腳本作為後備
                    $useAmPEPMicroservice = env('USE_AMPEP_MICROSERVICE', true);

                    if ($useAmPEPMicroservice) {
                        try {
                            // 嘗試使用微服務
                            Log::info("嘗試使用AmPEP微服務，TaskID: {$this->task->id}");
                            TaskUtils::runAmPEPMicroservice($this->task);
                            Log::info("AmPEP微服務調用成功，TaskID: {$this->task->id}");
                        } catch (\Exception $e) {
                            // 微服務失敗時，回退到本地R腳本
                            Log::error("AmPEP微服務調用失敗，回退到本地R腳本，TaskID: {$this->task->id}, 錯誤: {$e->getMessage()}");
                            Log::error('微服務URL: '.env('AMPEP_MICROSERVICE_BASE_URL', 'not_set'));
                            TaskUtils::runAmPEPTask($this->task);
                        }
                    } else {
                        // 使用本地R腳本
                        TaskUtils::runAmPEPTask($this->task);
                    }

                    // AMP回歸預測保持不變
                    TaskUtils::runAmpRegressionEcSaPredictMicroservice($this->task);
                }
                if ($key === 'deepampep30') {
                    $useDeepMicroservice = env('USE_DEEPAMPEP30_MICROSERVICE', true);

                    if ($useDeepMicroservice) {
                        try {
                            Log::info("嘗試使用DeepAmPEP30微服務，TaskID: {$this->task->id}");
                            TaskUtils::runDeepAmPEP30Microservice($this->task);
                            Log::info("DeepAmPEP30微服務調用成功，TaskID: {$this->task->id}");
                        } catch (\Exception $e) {
                            Log::error("DeepAmPEP30微服務調用失敗，回退到本地R腳本，TaskID: {$this->task->id}, 錯誤: {$e->getMessage()}");
                            Log::error('微服務URL: '.env('DEEPAMPEP30_MICROSERVICE_BASE_URL', 'not_set'));
                            TaskUtils::runDeepAmPEP30Task($this->task);
                        }
                    } else {
                        TaskUtils::runDeepAmPEP30Task($this->task);
                    }
                }
                if ($key === 'rfampep30') {
                    $useRFMicroservice = env('USE_RFAMPEP30_MICROSERVICE', true);

                    if ($useRFMicroservice) {
                        try {
                            Log::info("嘗試使用RFAmPEP30微服務，TaskID: {$this->task->id}");
                            TaskUtils::runRFAmPEP30Microservice($this->task);
                            Log::info("RFAmPEP30微服務調用成功，TaskID: {$this->task->id}");
                        } catch (\Exception $e) {
                            Log::error("RFAmPEP30微服務調用失敗，回退到本地R腳本，TaskID: {$this->task->id}, 錯誤: {$e->getMessage()}");
                            Log::error('微服務URL: '.env('DEEPAMPEP30_MICROSERVICE_BASE_URL', 'not_set'));
                            TaskUtils::runRFAmPEP30Task($this->task);
                        }
                    } else {
                        TaskUtils::runRFAmPEP30Task($this->task);
                    }
                }
            } else {
                continue;
            }
        }

        TasksServices::getInstance()->finishedTask($this->task->id);
    }

    public function failed(?\Throwable $exception = null)
    {
        echo 'Fail Status: '.$exception->getMessage();
        TasksServices::getInstance()->failedTask($this->task->id);
    }
}
