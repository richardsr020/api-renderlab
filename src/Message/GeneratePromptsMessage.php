<?php

namespace App\Message;

/**
 * Message pour la génération de prompts d'images
 * 
 * Ce message est envoyé dans la queue pour déclencher la génération
 * automatique de prompts à partir d'un script. Il contient toutes
 * les informations nécessaires pour identifier le script et l'utilisateur.
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class GeneratePromptsMessage
{
    /**
     * Constructeur du message
     * 
     * @param int $scriptId L'ID du script à analyser
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
            $this->taskId = uniqid('prompts_', true);
        }
    }
} 