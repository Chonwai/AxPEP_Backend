<?php

namespace App\Jobs;

use App\Services\BERTHemoPep60MicroserviceClient;
use App\Services\TasksServices;
use App\Services\HemoPepServices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HemoPepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $task;
    private $request;

    /**
     * 任務可以運行的秒數上限
     *
     * @var int
     */
    public $timeout = 7200;

    /**
     * 創建新的任務實例
     *
     * @param $task
     * @param $request
     * @return void
     */
    public function __construct($task, $request)
    {
        $this->task = $task;
        $this->request = $request;
    }

    /**
     * 執行任務
     *
     * @return void
     */
    public function handle()
    {
        try {
            foreach ($this->request['methods'] as $key => $value) {
                if ($value == true) {
                    if ($key === 'hemopep60') {
                        $this->processHemoPep60();
                    }
                    // 其他方法可在此添加
                } else {
                    continue;
                }
            }

            HemoPepServices::getInstance()->finishedTask($this->task->id);
        } catch (\Exception $e) {
            Log::error("HemoPep任務失敗: " . $e->getMessage());
            HemoPepServices::getInstance()->failedTask($this->task->id);
            throw $e;
        }
    }

    /**
     * 處理HemoPep60分析
     */
    private function processHemoPep60()
    {
        // 讀取FASTA文件內容
        $fastaContent = Storage::disk('local')->get("Tasks/{$this->task->id}/input.fasta");

        // 調用微服務
        $client = new BERTHemoPep60MicroserviceClient();
        $result = $client->predict($fastaContent);

        // 將結果寫入文件
        $resultPath = "Tasks/{$this->task->id}/hemopep60_result.json";
        Storage::disk('local')->put($resultPath, json_encode($result, JSON_PRETTY_PRINT));

        // 創建CSV結果文件
        $this->createCsvResultFile($result);
    }

    /**
     * 創建CSV結果文件
     */
    private function createCsvResultFile($result)
    {
        if (isset($result['data']['detailed_predictions'])) {
            $csvData = "Sequence ID,Sequence,HC5,HC10,HC50\n";

            foreach ($result['data']['detailed_predictions'] as $pred) {
                $csvData .= "\"{$pred['sequence_id']}\",\"{$pred['sequence']}\",{$pred['HC5']},{$pred['HC10']},{$pred['HC50']}\n";
            }

            $resultPath = "Tasks/{$this->task->id}/hemopep60_detailed.csv";
            Storage::disk('local')->put($resultPath, $csvData);
        }
    }

    /**
     * 任務失敗處理
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception = null)
    {
        Log::error("HemoPep失敗狀態: " . $exception->getMessage());
        TasksServices::getInstance()->failedTask($this->task->id);
    }
}
