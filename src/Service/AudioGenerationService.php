<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de génération d'audio à partir de texte
 * 
 * Ce service convertit du texte en audio de haute qualité en utilisant
 * l'API Gemini TTS. Il inclut :
 * - Synthèse vocale avancée avec 30+ voix disponibles
 * - Conversion en MP3 via ffmpeg
 * - Validation des voix utilisateur
 * - Optimisation de la qualité audio
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class AudioGenerationService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;
    
    /**
     * Liste des voix disponibles dans l'API Gemini TTS
     * 
     * @var array
     */
    private const AVAILABLE_VOICES = [
        'Zephyr',
        'Puck',
        'Charon',
        'Kore',
        'Fenrir',
        'Leda',
        'Orus',
        'Aoede',
        'Callirrhoe',
        'Autonoe',
        'Enceladus',
        'Iapetus',
        'Umbriel',
        'Algieba',
        'Despina',
        'Erinome',
        'Algenib',
        'Rasalgethi',
        'Laomedeia',
        'Achernar',
        'Alnilam',
        'Schedar',
        'Gacrux',
        'Pulcherrima',
        'Achird',
        'Zubenelgenubi',
        'Vindemiatrix',
        'Sadachbia',
        'Sadaltager',
        'Sulafat'
    ];
    
    /**
     * Voix par défaut si aucune voix n'est spécifiée
     * 
     * @var string
     */
    private const DEFAULT_VOICE = 'Kore';

    /**
     * Constructeur du service
     * 
     * @param HttpClientInterface $httpClient Client HTTP pour les appels API
     */
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('GEMINI_API_KEY environment variable is required');
        }
    }

    /**
     * Génère un fichier audio MP3 à partir d'un texte
     * 
     * Cette méthode convertit le texte en audio via l'API Gemini TTS,
     * puis utilise ffmpeg pour convertir en MP3 haute qualité.
     * 
     * @param string $text Le texte à convertir en audio
     * @param string $voice La voix à utiliser (sera normalisée si invalide)
     * @param string $saveDir Le répertoire de sauvegarde
     * @param string $filename Le nom du fichier à créer
     * @return string|null Le chemin du fichier MP3 généré ou null si échec
     * @throws \Exception Si la génération échoue
     */
    public function generateAudio(string $text, string $voice, string $saveDir, string $filename): ?string
    {
        try {
            // Normaliser la voix (utilise la voix par défaut si invalide)
            $normalizedVoice = $this->normalizeVoice($voice);
            
            // Valider les paramètres d'entrée
            $this->validateInput($text, $normalizedVoice);
            
            // Appeler l'API Gemini TTS
            $audioData = $this->callGeminiTTS($text, $normalizedVoice);
            
            // Sauvegarder et convertir l'audio
            return $this->saveAndConvertAudio($audioData, $saveDir, $filename);
            
        } catch (\Exception $e) {
            error_log("Audio generation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Valide les paramètres d'entrée
     * 
     * @param string $text Le texte à valider
     * @param string $voice La voix à valider
     * @throws \Exception Si les paramètres sont invalides
     */
    private function validateInput(string $text, string $voice): void
    {
        if (empty(trim($text))) {
            throw new \Exception('Text cannot be empty');
        }
        
        if (strlen($text) > 5000) {
            throw new \Exception('Text too long (max 5000 characters)');
        }
        
        if (empty($voice)) {
            throw new \Exception('Voice parameter is required');
        }
        
        if (!$this->isValidVoice($voice)) {
            throw new \Exception('Invalid voice: ' . $voice . '. Use getAvailableVoices() to see valid options.');
        }
    }

    /**
     * Vérifie si une voix est valide
     * 
     * @param string $voice Le nom de la voix à vérifier
     * @return bool True si la voix est valide
     */
    public function isValidVoice(string $voice): bool
    {
        return in_array($voice, self::AVAILABLE_VOICES);
    }

    /**
     * Récupère la liste des voix disponibles
     * 
     * @return array Liste des voix disponibles
     */
    public function getAvailableVoices(): array
    {
        return self::AVAILABLE_VOICES;
    }

    /**
     * Normalise une voix (utilise la voix par défaut si invalide)
     * 
     * @param string $voice La voix à normaliser
     * @return string La voix normalisée
     */
    public function normalizeVoice(string $voice): string
    {
        if (empty($voice) || !$this->isValidVoice($voice)) {
            return self::DEFAULT_VOICE;
        }
        
        return $voice;
    }

    /**
     * Récupère la description d'une voix
     * 
     * @param string $voice Le nom de la voix
     * @return string|null La description de la voix ou null si invalide
     */
    public function getVoiceDescription(string $voice): ?string
    {
        // Les descriptions ne sont plus stockées dans le tableau
        // Cette méthode peut être étendue pour retourner des descriptions
        // depuis une base de données ou un fichier de configuration
        return null;
    }

    /**
     * Appelle l'API Gemini TTS pour la synthèse vocale
     * 
     * @param string $text Le texte à synthétiser
     * @param string $voice La voix à utiliser
     * @return string Les données audio en base64
     * @throws \Exception Si l'appel API échoue
     */
    private function callGeminiTTS(string $text, string $voice): string
    {
        $response = $this->httpClient->request('POST', 
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent', [
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
            'timeout' => 120, // Timeout long pour la génération audio
        ]);
        
        // Vérifier le code de statut
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Gemini TTS API error: HTTP ' . $response->getStatusCode());
        }
        
        $data = $response->toArray(false);
        
        // Extraire les données audio
        $base64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;
        if (!$base64) {
            throw new \Exception('No audio data in response');
        }
        
        return $base64;
    }

    /**
     * Sauvegarde et convertit l'audio en MP3
     * 
     * @param string $base64Data Les données audio en base64
     * @param string $saveDir Le répertoire de sauvegarde
     * @param string $filename Le nom du fichier
     * @return string Le chemin du fichier MP3 final
     * @throws \Exception Si la conversion échoue
     */
    private function saveAndConvertAudio(string $base64Data, string $saveDir, string $filename): string
    {
        // Créer le répertoire si nécessaire
        if (!is_dir($saveDir)) {
            if (!mkdir($saveDir, 0755, true)) {
                throw new \Exception('Failed to create directory: ' . $saveDir);
            }
        }
        
        // Vérifier que ffmpeg est disponible
        if (!$this->isFfmpegAvailable()) {
            throw new \Exception('ffmpeg is not available on the system');
        }
        
        // Sauvegarder temporairement en PCM
        $tmpPcm = $saveDir . '/' . uniqid('audio_', true) . '.pcm';
        file_put_contents($tmpPcm, base64_decode($base64Data));
        
        // Convertir en WAV
        $tmpWav = $saveDir . '/' . uniqid('audio_', true) . '.wav';
        $this->convertPcmToWav($tmpPcm, $tmpWav);
        
        // Convertir en MP3
        $mp3Path = $saveDir . '/' . preg_replace('/\.[a-zA-Z0-9]+$/', '', $filename) . '.mp3';
        $this->convertWavToMp3($tmpWav, $mp3Path);
        
        // Nettoyer les fichiers temporaires
        $this->cleanupTempFiles([$tmpPcm, $tmpWav]);
        
        // Valider le fichier final
        if (!$this->validateGeneratedFile($mp3Path, 'audio')) {
            unlink($mp3Path);
            throw new \Exception('Generated audio file validation failed');
        }
        
        return $mp3Path;
    }

    /**
     * Vérifie si ffmpeg est disponible sur le système
     * 
     * @return bool True si ffmpeg est disponible
     */
    private function isFfmpegAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        
        exec('which ffmpeg 2>/dev/null', $output, $returnCode);
        
        return $returnCode === 0;
    }

    /**
     * Convertit un fichier PCM en WAV
     * 
     * @param string $pcmPath Le chemin du fichier PCM
     * @param string $wavPath Le chemin du fichier WAV de sortie
     * @throws \Exception Si la conversion échoue
     */
    private function convertPcmToWav(string $pcmPath, string $wavPath): void
    {
        $cmd = sprintf(
            'ffmpeg -y -f s16le -ar 24000 -ac 1 -i %s %s',
            escapeshellarg($pcmPath),
            escapeshellarg($wavPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($wavPath)) {
            throw new \Exception('Failed to convert PCM to WAV');
        }
    }

    /**
     * Convertit un fichier WAV en MP3
     * 
     * @param string $wavPath Le chemin du fichier WAV
     * @param string $mp3Path Le chemin du fichier MP3 de sortie
     * @throws \Exception Si la conversion échoue
     */
    private function convertWavToMp3(string $wavPath, string $mp3Path): void
    {
        $cmd = sprintf(
            'ffmpeg -y -i %s -codec:a libmp3lame -qscale:a 2 %s',
            escapeshellarg($wavPath),
            escapeshellarg($mp3Path)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($mp3Path)) {
            throw new \Exception('Failed to convert WAV to MP3');
        }
    }

    /**
     * Nettoie les fichiers temporaires
     * 
     * @param array $files Liste des fichiers à supprimer
     */
    private function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Valide un fichier généré
     * 
     * Vérifie que le fichier existe et a le bon type MIME.
     * 
     * @param string $path Le chemin du fichier
     * @param string $type Le type attendu ('image' ou 'audio')
     * @return bool True si le fichier est valide
     */
    private function validateGeneratedFile(string $path, string $type): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        
        $mimeType = mime_content_type($path);
        $allowedTypes = [
            'image' => ['image/jpeg', 'image/png', 'image/jpg'],
            'audio' => ['audio/mpeg', 'audio/mp3']
        ];
        
        return in_array($mimeType, $allowedTypes[$type] ?? []);
    }
} 