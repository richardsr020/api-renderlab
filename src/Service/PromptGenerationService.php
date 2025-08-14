<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service de génération de prompts pour la création d'images
 * 
 * Ce service analyse les scripts et génère automatiquement des prompts
 * détaillés pour la génération d'images via l'API Groq. Il inclut :
 * - Analyse intelligente des scènes
 * - Génération de prompts multiples par scène
 * - Validation et sanitisation du contenu
 * - Gestion robuste des erreurs
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class PromptGenerationService
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
        $this->apiKey = $_ENV['GROQ_API_KEY'] ?? '';
        
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('GROQ_API_KEY environment variable is required');
        }
    }

    /**
     * Génère des prompts d'images à partir du contenu d'un script
     * 
     * Cette méthode analyse le script, le divise en scènes, et génère
     * pour chaque scène plusieurs prompts détaillés pour différentes
     * vues (face, dos, profil, etc.).
     * 
     * @param string $scriptText Le contenu du script à analyser
     * @return array Structure : ['scene_1' => [prompt1, prompt2, ...], ...]
     * @throws \Exception Si la génération échoue
     */
    public function generatePrompts(string $scriptText): array
    {
        // Diviser le script en scènes distinctes
        $scenes = $this->splitScriptIntoScenes($scriptText);
        $result = [];
        
        // Traiter chaque scène individuellement
        foreach ($scenes as $sceneIdx => $sceneText) {
            $prompts = $this->generatePromptsForScene($sceneText);
            $result['scene_' . ($sceneIdx + 1)] = $prompts;
        }
        
        return $result;
    }

    /**
     * Divise un script en scènes distinctes
     * 
     * Utilise des paragraphes vides comme séparateurs de scènes.
     * 
     * @param string $scriptText Le script complet
     * @return array Liste des scènes
     */
    private function splitScriptIntoScenes(string $scriptText): array
    {
        // Diviser par paragraphes vides et nettoyer
        $scenes = preg_split('/\n\n+/', $scriptText);
        return array_filter(array_map('trim', $scenes));
    }

    /**
     * Génère des prompts pour une scène spécifique
     * 
     * Appelle l'API Groq pour analyser la scène et générer des prompts
     * détaillés pour différentes vues et angles de caméra.
     * 
     * @param string $sceneText Le texte de la scène
     * @return array Liste des prompts générés
     * @throws \Exception Si l'appel API échoue
     */
    private function generatePromptsForScene(string $sceneText): array
    {
        try {
            // Préparer le prompt pour l'API
            $prompt = $this->buildPromptForScene($sceneText);
            
            // Appeler l'API Groq avec gestion d'erreur
            $response = $this->callGroqAPI($prompt);
            
            // Traiter la réponse streamée
            $content = $this->processStreamedResponse($response);
            
            // Valider et nettoyer les prompts
            return $this->validateAndCleanPrompts($content);
            
        } catch (\Exception $e) {
            // Log détaillé de l'erreur
            error_log("Prompt generation error for scene: " . $e->getMessage());
            throw new \Exception('Failed to generate prompts: ' . $e->getMessage());
        }
    }

    /**
     * Construit le prompt pour l'API Groq
     * 
     * @param string $sceneText Le texte de la scène
     * @return string Le prompt formaté
     */
    private function buildPromptForScene(string $sceneText): string
    {
        $sanitizedScene = $this->sanitizePrompt($sceneText);
        
        return "Pour la scène suivante, génère au moins trois prompts de génération d'image " .
               "pour différentes vues (face, dos, profil, angle de caméra, etc.). " .
               "Chaque prompt doit être détaillé et visuellement descriptif.\n\n" . 
               $sanitizedScene;
    }

    /**
     * Appelle l'API Groq avec gestion d'erreur
     * 
     * @param string $prompt Le prompt à envoyer
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     * @throws \Exception Si l'appel échoue
     */
    private function callGroqAPI(string $prompt): \Symfony\Contracts\HttpClient\ResponseInterface
    {
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
            'timeout' => 30, // Timeout explicite
        ]);
        
        // Vérifier le code de statut
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Groq API error: HTTP ' . $response->getStatusCode());
        }
        
        return $response;
    }

    /**
     * Traite la réponse streamée de l'API Groq
     * 
     * @param \Symfony\Contracts\HttpClient\ResponseInterface $response
     * @return string Le contenu complet
     */
    private function processStreamedResponse(\Symfony\Contracts\HttpClient\ResponseInterface $response): string
    {
        $content = '';
        
        foreach ($response->getContent(false, true) as $chunk) {
            // Chaque chunk est une ligne JSON commençant par 'data: '
            if (str_starts_with($chunk, 'data: ')) {
                $json = substr($chunk, 6);
                if (trim($json) === '[DONE]') continue;
                
                $data = json_decode($json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue; // Ignorer les chunks JSON invalides
                }
                
                $delta = $data['choices'][0]['delta']['content'] ?? '';
                $content .= $delta;
            }
        }
        
        if (empty(trim($content))) {
            throw new \Exception('Empty response from Groq API');
        }
        
        return $content;
    }

    /**
     * Valide et nettoie les prompts générés
     * 
     * @param string $content Le contenu brut de l'API
     * @return array Liste des prompts validés
     */
    private function validateAndCleanPrompts(string $content): array
    {
        // Diviser en lignes et nettoyer
        $prompts = preg_split('/\n+/', trim($content));
        $prompts = array_filter(array_map('trim', $prompts));
        $prompts = array_values($prompts);
        
        // Valider chaque prompt
        $validPrompts = [];
        foreach ($prompts as $prompt) {
            if ($this->validatePrompt($prompt)) {
                $validPrompts[] = $prompt;
            }
        }
        
        if (empty($validPrompts)) {
            throw new \Exception('No valid prompts generated');
        }
        
        return $validPrompts;
    }

    /**
     * Sanitise un prompt pour la sécurité
     * 
     * Supprime les caractères dangereux et limite la longueur.
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
        return substr($prompt, 0, 2000);
    }

    /**
     * Valide un prompt généré
     * 
     * Vérifie la longueur et filtre le contenu inapproprié.
     * 
     * @param string $prompt Le prompt à valider
     * @return bool True si le prompt est valide
     */
    private function validatePrompt(string $prompt): bool
    {
        // Vérifier la longueur
        if (strlen($prompt) < 10 || strlen($prompt) > 500) {
            return false;
        }
        
        // Vérifier le contenu inapproprié
        $inappropriateWords = ['nude', 'naked', 'violence', 'hate', 'blood', 'gore'];
        foreach ($inappropriateWords as $word) {
            if (stripos($prompt, $word) !== false) {
                return false;
            }
        }
        
        return true;
    }
} 