<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    }

    /**
     * Génère une image à partir d'un prompt via Gemini
     * @param string $prompt
     * @return string|null Chemin du fichier image généré (ou null si erreur)
     */
    public function generateImage(string $prompt, string $saveDir, string $filename): ?string
    {
        // Appel Gemini (à adapter selon l'API réelle)
        $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [ ['text' => $prompt] ]
                    ]
                ],
                'config' => [
                    'response_modalities' => ['TEXT', 'IMAGE']
                ]
            ],
        ]);
        $data = $response->toArray(false);
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $base64 = null;
        foreach ($parts as $part) {
            if (isset($part['inline_data']['data'])) {
                $base64 = $part['inline_data']['data'];
                break;
            }
        }
        if (!$base64) return null;
        if (!is_dir($saveDir)) mkdir($saveDir, 0777, true);
        $path = $saveDir . '/' . preg_replace('/\.[a-zA-Z0-9]+$/', '', $filename) . '.jpg';
        $tmpPath = $saveDir . '/tmp_img';
        file_put_contents($tmpPath, base64_decode($base64));
        $image = @imagecreatefromstring(file_get_contents($tmpPath));
        if ($image === false) {
            unlink($tmpPath);
            return null;
        }
        imagejpeg($image, $path, 90); // Qualité 90/100
        imagedestroy($image);
        unlink($tmpPath);
        return $path;
    }
}
