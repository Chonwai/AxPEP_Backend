<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EcotoxicologyMicroserviceClient
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => env('ECOTOXICOLOGY_MICROSERVICE_BASE_URL', 'http://127.0.0.1:8890')
        ]);
    }

    public function predict($fastaPath, $modelType)
    {
        try {
            $relativePath = str_replace('storage/app/', '', $fastaPath);
            if (!Storage::exists($relativePath)) {
                throw new \Exception("File not found: $relativePath");
            }
            $fastaContent = Storage::get($relativePath);
            $response = $this->client->post('/api/predict', [
                'json' => [
                    'fasta' => $fastaContent,
                    'model_type' => $modelType
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data;
        } catch (\Exception $e) {
            Log::error("Ecotoxicology Microservice call failed: " . $e->getMessage());
            return null;
        }
    }
}
