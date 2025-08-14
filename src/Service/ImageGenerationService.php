<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de génération d'images à partir de prompts
 * 
 * Ce service génère des images haute qualité à partir de prompts textuels
 * en utilisant l'API Gemini. Il inclut :
 * - Validation et sanitisation des prompts
 * - Gestion sécurisée des fichiers
 * - Compression et optimisation des images
 * - Gestion robuste des erreurs
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class ImageGenerationService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;

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
     * Génère une image à partir d'un prompt
     * 
     * Cette méthode prend un prompt textuel, l'envoie à l'API Gemini,
     * récupère l'image générée, la valide et la sauvegarde localement.
     * 
     * @param string $prompt Le prompt de génération d'image
     * @param string $saveDir Le répertoire de sauvegarde
     * @param string $filename Le nom du fichier à créer
     * @return string|null Le chemin du fichier généré ou null si échec
     * @throws \Exception Si la génération échoue
     */
    public function generateImage(string $prompt, string $saveDir, string $filename): ?string
    {
        try {
            // Valider et sanitiser le prompt
            if (!$this->validatePrompt($prompt)) {
                throw new \Exception('Invalid prompt content');
            }
            
            $sanitizedPrompt = $this->sanitizePrompt($prompt);
            
            // Appeler l'API Gemini avec timeout et gestion d'erreur
            $response = $this->callGeminiAPI($sanitizedPrompt);
            
            // Extraire les données d'image de la réponse
            $imageData = $this->extractImageData($response);
            
            // Sauvegarder l'image
            return $this->saveImage($imageData, $saveDir, $filename);
            
        } catch (\Exception $e) {
            error_log("Image generation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Appelle l'API Gemini pour la génération d'image
     * 
     * @param string $prompt Le prompt sanitisé
     * @return array La réponse de l'API
     * @throws \Exception Si l'appel échoue
     */
    private function callGeminiAPI(string $prompt): array
    {
        $response = $this->httpClient->request('POST', 
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent', [
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
            'timeout' => 60, // Timeout plus long pour la génération d'images
        ]);
        
        // Vérifier le code de statut
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Gemini API error: HTTP ' . $response->getStatusCode());
        }
        
        $data = $response->toArray(false);
        
        // Vérifier la structure de la réponse
        if (!isset($data['candidates'][0]['content']['parts'])) {
            throw new \Exception('Invalid response structure from Gemini API');
        }
        
        return $data;
    }

    /**
     * Extrait les données d'image de la réponse API
     * 
     * @param array $data La réponse de l'API
     * @return string Les données d'image en base64
     * @throws \Exception Si aucune image n'est trouvée
     */
    private function extractImageData(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'];
        $base64 = null;
        
        foreach ($parts as $part) {
            if (isset($part['inline_data']['data'])) {
                $base64 = $part['inline_data']['data'];
                break;
            }
        }
        
        if (!$base64) {
            throw new \Exception('No image data in response');
        }
        
        return $base64;
    }

    /**
     * Sauvegarde l'image générée
     * 
     * @param string $base64Data Les données d'image en base64
     * @param string $saveDir Le répertoire de sauvegarde
     * @param string $filename Le nom du fichier
     * @return string Le chemin du fichier sauvegardé
     * @throws \Exception Si la sauvegarde échoue
     */
    private function saveImage(string $base64Data, string $saveDir, string $filename): string
    {
        // Créer le répertoire si nécessaire
        if (!is_dir($saveDir)) {
            if (!mkdir($saveDir, 0755, true)) {
                throw new \Exception('Failed to create directory: ' . $saveDir);
            }
        }
        
        $path = $saveDir . '/' . preg_replace('/\.[a-zA-Z0-9]+$/', '', $filename) . '.jpg';
        $tmpPath = $saveDir . '/tmp_img_' . uniqid();
        
        // Décoder et valider l'image
        $imageData = base64_decode($base64Data);
        if ($imageData === false) {
            throw new \Exception('Invalid base64 image data');
        }
        
        file_put_contents($tmpPath, $imageData);
        
        // Valider que c'est bien une image
        $image = @imagecreatefromstring(file_get_contents($tmpPath));
        if ($image === false) {
            unlink($tmpPath);
            throw new \Exception('Invalid image format');
        }
        
        // Sauvegarder l'image avec compression
        if (!imagejpeg($image, $path, 90)) {
            imagedestroy($image);
            unlink($tmpPath);
            throw new \Exception('Failed to save image');
        }
        
        imagedestroy($image);
        unlink($tmpPath);
        
        // Valider le fichier final
        if (!$this->validateGeneratedFile($path, 'image')) {
            unlink($path);
            throw new \Exception('Generated file validation failed');
        }
        
        return $path;
    }

    /**
     * Valide un prompt de génération d'image
     * 
     * Vérifie la longueur et filtre le contenu inapproprié.
     * 
     * @param string $prompt Le prompt à valider
     * @return bool True si le prompt est valide
     */
    private function validatePrompt(string $prompt): bool
    {
        // Vérifier la longueur
        if (strlen($prompt) < 10 || strlen($prompt) > 1000) {
            return false;
        }
        
        // Vérifier le contenu inapproprié
        $inappropriateWords = ['nude', 'naked', 'violence', 'hate', 'blood', 'gore', 'explicit'];
        foreach ($inappropriateWords as $word) {
            if (stripos($prompt, $word) !== false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Sanitise un prompt pour la sécurité
     * 
     * @param string $prompt Le prompt à sanitiser
     * @return string Le prompt nettoyé
     */
    private function sanitizePrompt(string $prompt): string
    {
        // Supprimer les caractères dangereux
        $prompt = strip_tags($prompt);
        $prompt = htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8');
        
        // Limiter la longueur
        return substr($prompt, 0, 1000);
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