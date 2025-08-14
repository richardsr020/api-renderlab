<?php

namespace App\Message;

/**
 * Message pour la génération d'images à partir de prompts
 * 
 * Ce message est envoyé dans la queue pour déclencher la génération
 * d'images à partir des prompts existants d'un script. Il gère
 * la génération de toutes les images pour toutes les scènes.
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class GenerateImagesMessage
{
    /**
     * Constructeur du message
     * 
     * @param int $scriptId L'ID du script contenant les prompts
     * @param int $userId L'ID de l'utilisateur propriétaire
     * @param string|null $taskId L'ID unique de la tâche (généré automatiquement si null)
     */
    public function __construct(
        public int $scriptId,
        public int $userId,
        public ?string $taskId = null
    ) {
        // Générer un ID unique si non fourni
        if ($this->taskId === null) {
            $this->taskId = uniqid('images_', true);
        }
    }
} 