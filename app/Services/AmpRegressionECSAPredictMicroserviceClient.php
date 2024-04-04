<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AmpRegressionECSAPredictMicroserviceClient
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => env('AMP_REGRESSION_EC_SA_PREDICT_BASE_URL', 'http://127.0.0.1:8889')
        ]);
    }

    public function predict($fastaPath, $taskID)
    {
        try {
            $response = $this->client->post('/predict', [
                'form_params' => [
                    'task_id' => $taskID
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data;
        } catch (\Exception $e) {
            Log::error("AMP Regression Microservice call failed: " . $e->getMessage());
            return null;
        }
    }
}
