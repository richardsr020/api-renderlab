<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $_ENV['GROQ_API_KEY'] ?? '';
    }

    /**
     * Analyse un script et génère des prompts par scène via Groq
     * @param string $scriptText
     * @return array [ scene => [prompt1, prompt2, prompt3, ...], ... ]
     */
    public function generatePrompts(string $scriptText): array
    {
        $scenes = $this->splitScriptIntoScenes($scriptText);
        $result = [];
        foreach ($scenes as $sceneIdx => $sceneText) {
            $prompts = $this->callGroqForScene($sceneText);
            $result['scene_' . ($sceneIdx+1)] = $prompts;
        }
        return $result;
    }

    private function splitScriptIntoScenes(string $scriptText): array
    {
        return array_filter(array_map('trim', preg_split('/\n\n+/', $scriptText)));
    }

    private function callGroqForScene(string $sceneText): array
    {
        $prompt = "Pour la scène suivante, génère au moins trois prompts de génération d'image pour différentes vues (face, dos, profil, etc.).\n\n" . $sceneText;
        $url = 'https://api.groq.com/v1/chat/completions';
        $body = [
            'model' => 'deepseek-r1-distill-llama-70b',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.6,
            'max_completion_tokens' => 4096,
            'top_p' => 0.95,
            'stream' => true,
            'stop' => null
        ];
        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);
        $content = '';
        foreach ($response->getContent(false, true) as $chunk) {
            // Chaque chunk est une ligne JSON commençant par 'data: '
            if (str_starts_with($chunk, 'data: ')) {
                $json = substr($chunk, 6);
                if (trim($json) === '[DONE]') continue;
                $data = json_decode($json, true);
                $delta = $data['choices'][0]['delta']['content'] ?? '';
                $content .= $delta;
            }
        }
        $prompts = preg_split('/\n+/', trim($content));
        $prompts = array_filter(array_map('trim', $prompts));
        return array_values($prompts);
    }
}
