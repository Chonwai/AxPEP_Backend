<?php

namespace App\Utils;

use App\Exports\AmPEPResultExport;
use App\Imports\AmPEPResultImport;
use App\Imports\OutImport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class FileUtils
{
    public static function createResultFile($path, $methods)
    {
        $methodString = self::prepareMethodString($methods);
        Storage::put($path.'classification.csv', 'id,'.$methodString."number_of_positives,sequence\n");
        Storage::put($path.'score.csv', 'id,'.$methodString."product_of_probability,sequence\n");
    }

    public static function createAcPEPResultFile($path, $methods)
    {
        $methodString = self::prepareMethodString($methods);
        Storage::put($path.'classification.csv', 'id,'.$methodString."sequence\n");
        Storage::put($path.'score.csv', "id,classification,score,sequence\n");
    }

    public static function createSSLBESToxResultFile($path, $methods)
    {
        $methodString = self::prepareMethodString($methods);
        Storage::put($path.'classification.csv', 'id,'.$methodString."smiles\n");
    }

    public static function createEcotoxicologyResultFile($path, $methods)
    {
        $methodString = self::prepareMethodString($methods);
        Storage::put($path.'classification.csv', 'id,'.$methodString."smiles\n");
    }

    public static function insertSequencesAndHeaderOnResult($path, $methods, $function = 'AmPEP')
    {
        [$fInput, $fClassification, $fScore, $methodArray] = self::prepareSequencesAndHeaderFile($path, $methods, $function);
        self::prepareSequencesAndHeaderContent($fInput, $fClassification, $fScore, $methodArray, $function);
    }

    public static function loadResultFile($id, $methods, $application = 'AmPEP')
    {
        $classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
        if ($application === 'SSL-GCN' || $application === 'Ecotoxicology') {
            return [$classifications];
        } elseif ($application === 'AmPEP') {
            $scores = Excel::toArray(new AmPEPResultImport, "Tasks/$id/score.csv", null, \Maatwebsite\Excel\Excel::CSV);
            $ampActivityPrediction = Excel::toArray(new AmPEPResultImport, "Tasks/$id/amp_activity_prediction.csv", null, \Maatwebsite\Excel\Excel::CSV);

            return [$classifications, $scores, $ampActivityPrediction];
        } else {
            $scores = Excel::toArray(new AmPEPResultImport, "Tasks/$id/score.csv", null, \Maatwebsite\Excel\Excel::CSV);

            return [$classifications, $scores];
        }
    }

    public static function loadClassificationsFile($id, $methods)
    {
        $classifications = Excel::toArray(new AmPEPResultImport, "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);

        return $classifications;
    }

    public static function writeAmPEPResultFile($id, $methods)
    {
        [$classifications, $scores, $ampActivityPrediction] = self::loadResultFile($id, $methods, 'AmPEP');

        foreach ($methods as $key => $value) {
            [$classifications, $scores] = self::matchingAmPEP($id, $value->method, $classifications, $scores, $ampActivityPrediction);
        }

        [$classifications, $scores] = self::calculateResultFile($classifications, $scores, $methods);

        Excel::store(new AmPEPResultExport($classifications[0]), "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
        Excel::store(new AmPEPResultExport($scores[0]), "Tasks/$id/score.csv", null, \Maatwebsite\Excel\Excel::CSV);
    }

    public static function writeAcPEPResultFile($id, $methods)
    {
        [$classifications, $scores] = self::loadResultFile($id, $methods, 'AcPEP');

        foreach ($methods as $key => $value) {
            $classifications = self::matchingAcPEPClassification($id, $value->method, $classifications);
        }

        $scores = self::matchingAcPEPScore($id, $scores);

        Excel::store(new AmPEPResultExport($classifications[0]), "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
        Excel::store(new AmPEPResultExport($scores[0]), "Tasks/$id/score.csv", null, \Maatwebsite\Excel\Excel::CSV);
    }

    public static function writeSSLBESToxResultFile($id, $methods)
    {
        [$classifications] = self::loadResultFile($id, $methods, 'SSL-GCN');

        foreach ($methods as $key => $value) {
            $classifications = self::matchingSslGcnAndEcotoxicologyClassification($id, $value->method, $classifications);
        }

        Excel::store(new AmPEPResultExport($classifications[0]), "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
    }

    public static function writeEcotoxicologyResultFile($id, $methods)
    {
        [$classifications] = self::loadResultFile($id, $methods, 'Ecotoxicology');

        foreach ($methods as $key => $value) {
            $classifications = self::matchingSslGcnAndEcotoxicologyClassification($id, $value->method, $classifications);
        }

        Excel::store(new AmPEPResultExport($classifications[0]), "Tasks/$id/classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * 寫入HemoPep結果文件
     *
     * @param  string  $taskID  任務ID
     * @param  array  $methods  任務方法
     * @return void
     */
    public static function writeHemoPepResultFile($taskID, $methods)
    {
        try {
            // 使用 Storage 門面獲取絕對路徑
            $detailedCsvPath = storage_path("app/Tasks/$taskID/hemopep60_detailed.csv");

            if (file_exists($detailedCsvPath)) {
                // 讀取詳細結果數據
                $csvContent = file_get_contents($detailedCsvPath);
                $lines = explode("\n", $csvContent);

                // 創建分類文件（classification.csv）
                $classificationContent = "id,sequence\n";

                // 創建得分文件（score.csv）
                $scoreContent = "id,hc50_score\n";

                // 跳過標題行，處理每一行數據
                for ($i = 1; $i < count($lines); $i++) {
                    if (empty(trim($lines[$i]))) {
                        continue;
                    }

                    $fields = str_getcsv($lines[$i]);
                    if (count($fields) >= 5) { // 確保有足夠的欄位
                        $seqId = $fields[0];
                        $sequence = $fields[1];
                        $hc50 = $fields[4]; // HC50值

                        // 添加到分類文件
                        $classificationContent .= "$seqId,$sequence\n";

                        // 添加到得分文件
                        $scoreContent .= "$seqId,$hc50\n";
                    }
                }

                // 寫入分類文件
                file_put_contents(storage_path("app/Tasks/$taskID/classification.csv"), $classificationContent);

                // 寫入得分文件
                file_put_contents(storage_path("app/Tasks/$taskID/score.csv"), $scoreContent);

                // 如果需要，可以從詳細文件直接複製到各方法的結果文件
                foreach ($methods as $method) {
                    if ($method->method === 'hemopep60') {
                        $methodResultFile = storage_path("app/Tasks/$taskID/{$method->method}_result.csv");
                        copy($detailedCsvPath, $methodResultFile);
                    }
                }
            } else {
                throw new \Exception("找不到HemoPep詳細結果文件: $detailedCsvPath");
            }
        } catch (\Exception $e) {
            \Log::error('寫入HemoPep結果文件錯誤: '.$e->getMessage());
            throw $e;
        }
    }

    public static function matchingAmPEP($id, $method, $classifications, $scores, $ampActivityPrediction)
    {
        $fResult = Excel::toArray(new OutImport, "Tasks/$id/$method.out", null, \Maatwebsite\Excel\Excel::TSV);
        $symbol = ' ';
        foreach ($fResult[0] as $key => $value) {
            $result = explode($symbol, $value[0]);
            $classifications[0] = array_map(function ($value) use ($result, $method) {
                if ($value['id'] == $result[0]) {
                    $value["$method"] = $result[1];
                }

                return $value;
            }, $classifications[0]);
            $scores[0] = array_map(function ($value) use ($result, $method) {
                if ($value['id'] == $result[0]) {
                    $value["$method"] = $result[2];
                }

                return $value;
            }, $scores[0]);
        }

        return [$classifications, $scores];
    }

    public static function matchingAcPEPClassification($id, $method, $classifications)
    {
        $fResult = Excel::toArray(new OutImport, "Tasks/$id/$method.out", null, \Maatwebsite\Excel\Excel::CSV);
        array_shift($fResult[0]);
        foreach ($fResult[0] as $key => $value) {
            $classifications[0] = array_map(function ($val) use ($value, $method) {
                if ($val['id'] === $value[0]) {
                    $val["$method"] = $value[1];
                }

                return $val;
            }, $classifications[0]);
        }

        return $classifications;
    }

    public static function matchingAcPEPScore($id, $scores)
    {
        $fResult = Excel::toArray(new OutImport, "Tasks/$id/xDeep-AcPEP-Classification.csv", null, \Maatwebsite\Excel\Excel::CSV);
        foreach ($fResult[0] as $key => $value) {
            $scores[0] = array_map(function ($val) use ($value) {
                if ($val['id'] === $value[0]) {
                    $val['score'] = $value[2];
                    if ($value[1] != '') {
                        $val['classification'] = $value[1];
                    } else {
                        $val['classification'] = '0';
                    }
                }

                return $val;
            }, $scores[0]);
        }

        return $scores;
    }

    public static function matchingSslGcnAndEcotoxicologyClassification($id, $method, $classifications)
    {
        $fResult = Excel::toArray(new OutImport, "Tasks/$id/$method.result.csv", null, \Maatwebsite\Excel\Excel::CSV);
        array_shift($fResult[0]);
        foreach ($fResult[0] as $key => $value) {
            $classifications[0] = array_map(function ($val) use ($value, $method) {
                if ($val['id'] === $value[0]) {
                    $val["$method"] = strval($value[2]);
                }

                return $val;
            }, $classifications[0]);
        }

        return $classifications;
    }

    public static function matchingEcotoxicologyClassification($id, $method, $classifications)
    {
        $resultPath = "Tasks/$id/$method.result.csv";
        if (! Storage::exists($resultPath)) {
            throw new \Illuminate\Contracts\Filesystem\FileNotFoundException("File [$resultPath] does not exist and can therefore not be imported.");
        }

        $results = array_map('str_getcsv', file(Storage::path($resultPath)));
        array_shift($results); // Remove header

        foreach ($results as $result) {
            $classifications[] = [
                'id' => $result[0],
                'smiles' => $result[1],
                'pre' => $result[2],
            ];
        }

        return $classifications;
    }

    public static function calculateResultFile($classifications, $scores, $methods)
    {
        $classifications[0] = array_map(function ($value) use ($methods) {
            $numberOfPositives = '0';
            foreach ($methods as $key => $method) {
                if ($value["$method->method"] == '1') {
                    $numberOfPositives++;
                }
            }
            $value['number_of_positives'] = $numberOfPositives;

            return $value;
        }, $classifications[0]);

        $scores[0] = array_map(function ($value) use ($methods) {
            $productOfProbability = 1;
            foreach ($methods as $key => $method) {
                $productOfProbability = $productOfProbability * $value["$method->method"];
            }
            $value['product_of_probability'] = $productOfProbability;

            return $value;
        }, $scores[0]);

        return [$classifications, $scores];
    }

    private static function prepareMethodString($methods)
    {
        $methodString = '';
        foreach ($methods as $key => $value) {
            $methodString = $methodString."$value,";
        }

        return $methodString;
    }

    private static function prepareSequencesAndHeaderFile($path, $methods, $function = 'AmPEP')
    {
        $methodArray = [];
        foreach ($methods as $key => $value) {
            array_push($methodArray, '');
        }
        $fInput = fopen($path.'input.fasta', 'r');
        $fClassification = fopen($path.'classification.csv', 'a+');

        switch ($function) {
            case 'AmPEP':
                $fScore = fopen($path.'score.csv', 'a+');
                break;
            case 'AcPEP':
                $fScore = fopen($path.'score.csv', 'a+');
                break;
            case 'BESTox':
                $fScore = null;
                break;
            case 'SSL-GCN':
                $fScore = null;
                break;
            case 'Ecotoxicology':
                $fScore = null;
                break;
            default:
                $fScore = fopen($path.'score.csv', 'a+');
                break;
        }

        return [$fInput, $fClassification, $fScore, $methodArray];
    }

    private static function prepareSequencesAndHeaderContent($fInput, $fClassification, $fScore, $methodArray, $function = 'AmPEP')
    {
        $i = 0;
        $id = '';
        while ($line = fgets($fInput)) {
            $i++;
            if ($i % 2 == 1) {
                $id = ltrim($line, '>');
                $id = ltrim(str_replace("\r\n", '', $id));
                $id = ltrim(str_replace("\n", '', $id));
                $id = ltrim(str_replace(PHP_EOL, '', $id));
            } else {
                $sequence = ltrim($line);
                $sequence = ltrim(str_replace("\r\n", '', $sequence));
                $sequence = ltrim(str_replace("\n", '', $sequence));
                $sequence = ltrim(str_replace(PHP_EOL, '', $sequence));
                if ($function === 'AmPEP') {
                    fputcsv($fClassification, array_merge(['id' => $id], $methodArray, ['number_of_positives' => ''], ['sequence' => $sequence]));
                    fputcsv($fScore, array_merge(['id' => $id], $methodArray, ['product_of_probability' => ''], ['sequence' => $sequence]));
                } elseif ($function === 'AcPEP') {
                    fputcsv($fClassification, array_merge(['id' => $id], $methodArray, ['sequence' => $sequence]));
                    fputcsv($fScore, array_merge(['id' => $id], ['classification' => ''], ['score' => ''], ['sequence' => $sequence]));
                } elseif ($function === 'SSL-GCN' || $function === 'Ecotoxicology') {
                    fputcsv($fClassification, array_merge(['id' => $id], $methodArray, ['smiles' => $sequence]));
                }
            }
        }
    }
}
