<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Service de publication de notifications Mercure
 * 
 * Ce service gère l'envoi de notifications temps réel via le hub Mercure.
 * Il permet de notifier les clients frontend des changements d'état
 * des tâches en cours (prompts, images, audio).
 * 
 * Les notifications sont envoyées sur des topics spécifiques :
 * - task/{taskId} : Mises à jour d'une tâche spécifique
 * - script/{scriptId}/user/{userId} : Mises à jour d'un script
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class MercurePublisherService
{
    public function __construct(
        private HubInterface $hub
    ) {}

    /**
     * Publie une mise à jour de tâche
     * 
     * Envoie une notification sur le topic task/{taskId} pour informer
     * le frontend des changements d'état d'une tâche spécifique.
     * 
     * @param string $taskId L'ID unique de la tâche
     * @param array $data Les données à envoyer (statut, progression, résultat, etc.)
     */
    public function publishTaskUpdate(string $taskId, array $data): void
    {
        $update = new Update(
            "task/{$taskId}",
            json_encode($data),
            true
        );

        $this->hub->publish($update);
    }

    /**
     * Publie une mise à jour de script
     * 
     * Envoie une notification sur le topic script/{scriptId}/user/{userId}
     * pour informer le frontend des changements d'un script spécifique.
     * 
     * @param int $scriptId L'ID du script
     * @param int $userId L'ID de l'utilisateur propriétaire
     * @param array $data Les données à envoyer (type de mise à jour, données, etc.)
     */
    public function publishScriptUpdate(int $scriptId, int $userId, array $data): void
    {
        $update = new Update(
            "script/{$scriptId}/user/{$userId}",
            json_encode($data),
            true
        );

        $this->hub->publish($update);
    }
} 