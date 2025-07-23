<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiAudioService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
    }

    /**
     * Génère un audio mp3 à partir d'un texte via Gemini
     * @param string $text
     * @param string $voice
     * @param string $saveDir
     * @param string $filename
     * @return string|null Chemin du fichier mp3 généré (ou null si erreur)
     */
    public function generateAudio(string $text, string $voice, string $saveDir, string $filename): ?string
    {
        // Appel Gemini via HttpClient Symfony (déjà fait)
        $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent', [
            'headers' => [
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'contents' => [[
                    'parts' => [[ 'text' => $text ]]
                ]],
                'generationConfig' => [
                    'responseModalities' => ['AUDIO'],
                    'speechConfig' => [
                        'voiceConfig' => [
                            'prebuiltVoiceConfig' => [
                                'voiceName' => $voice ?: 'Kore'
                            ]
                        ]
                    ]
                ],
                'model' => 'gemini-2.5-flash-preview-tts',
            ],
        ]);
        $data = $response->toArray(false);
        $base64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
        if (!$base64) return null;
        if (!is_dir($saveDir)) mkdir($saveDir, 0777, true);
        // On enregistre d'abord en .pcm
        $tmpPcm = $saveDir . '/' . uniqid('audio_', true) . '.pcm';
        file_put_contents($tmpPcm, base64_decode($base64));
        // Conversion en wav puis mp3 via ffmpeg
        $tmpWav = $saveDir . '/' . uniqid('audio_', true) . '.wav';
        $mp3Path = $saveDir . '/' . preg_replace('/\.[a-zA-Z0-9]+$/', '', $filename) . '.mp3';
        $cmdWav = sprintf('ffmpeg -y -f s16le -ar 24000 -ac 1 -i %s %s', escapeshellarg($tmpPcm), escapeshellarg($tmpWav));
        exec($cmdWav, $out1, $code1);
        if ($code1 !== 0 || !file_exists($tmpWav)) { unlink($tmpPcm); return null; }
        $cmdMp3 = sprintf('ffmpeg -y -i %s -codec:a libmp3lame -qscale:a 2 %s', escapeshellarg($tmpWav), escapeshellarg($mp3Path));
        exec($cmdMp3, $out2, $code2);
        unlink($tmpPcm); unlink($tmpWav);
        if ($code2 !== 0 || !file_exists($mp3Path)) return null;
        return $mp3Path;
    }
}
