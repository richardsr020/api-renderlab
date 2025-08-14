<?php

namespace App\Message;

/**
 * Message pour la génération d'audio à partir d'un script
 * 
 * Ce message est envoyé dans la queue pour déclencher la génération
 * d'audio à partir du contenu d'un script. Il inclut la voix
 * sélectionnée par l'utilisateur pour la synthèse vocale.
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class GenerateAudioMessage
{
    /**
     * Constructeur du message
     * 
     * @param int $scriptId L'ID du script à convertir en audio
     * @param int $userId L'ID de l'utilisateur propriétaire
     * @param string $voice La voix à utiliser pour la synthèse vocale
     * @param string|null $taskId L'ID unique de la tâche (généré automatiquement si null)
     */
    public function __construct(
        public int $scriptId,
        public int $userId,
        public string $voice,
        public ?string $taskId = null
    ) {
        // Générer un ID unique si non fourni
        if ($this->taskId === null) {
            $this->taskId = uniqid('audio_', true);
        }
    }
} 