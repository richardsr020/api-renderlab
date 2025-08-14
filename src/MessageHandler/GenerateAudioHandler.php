<?php

namespace App\MessageHandler;

use App\Entity\Script;
use App\Entity\Audio;
use App\Entity\Task;
use App\Message\GenerateAudioMessage;
use App\Service\AudioGenerationService;
use App\Service\MercurePublisherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class GenerateAudioHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AudioGenerationService $audioGenerationService,
        private MercurePublisherService $mercurePublisher
    ) {}

    public function __invoke(GenerateAudioMessage $message): void
    {
        try {
            // Créer ou récupérer la tâche
            $task = $this->entityManager->getRepository(Task::class)->findOneBy(['taskId' => $message->taskId]);
            if (!$task) {
                $task = new Task();
                $task->setTaskId($message->taskId)
                     ->setType('audio')
                     ->setScriptId($message->scriptId)
                     ->setUserId($message->userId)
                     ->setStatus('pending');
                $this->entityManager->persist($task);
                $this->entityManager->flush();
            }

            // Notifier le début du traitement
            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'in_progress',
                'message' => 'Generating audio from script...'
            ]);

            $task->setStatus('in_progress');
            $this->entityManager->flush();

            // Récupérer le script
            $script = $this->entityManager->getRepository(Script::class)->find($message->scriptId);
            if (!$script) {
                throw new UnrecoverableMessageHandlingException('Script not found');
            }

            // Vérifier si déjà généré
            $existing = $this->entityManager->getRepository(Audio::class)->findOneBy(['script' => $script]);
            if ($existing) {
                $task->setStatus('completed')
                     ->setResult(['already_generated' => true, 'audio_url' => json_decode($existing->getUrl(), true)]);
                $this->entityManager->flush();

                $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                    'status' => 'completed',
                    'result' => $task->getResult(),
                    'message' => 'Audio already generated'
                ]);

                return;
            }

            // Notifier la conversion
            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'in_progress',
                'message' => 'Converting text to speech and processing audio...'
            ]);

            // Générer l'audio
            $saveDir = __DIR__ . '/../../public/audio/' . $script->getId();
            $filename = 'audio_' . $script->getId() . '.mp3';
            
            $path = $this->audioGenerationService->generateAudio($script->getContent(), $message->voice, $saveDir, $filename);
            
            if (!$path) {
                throw new \Exception('Audio generation failed');
            }

            // Stocker en base
            $audioEntity = new Audio();
            $audioEntity->setUrl(json_encode(['/audio/' . $script->getId() . '/' . $filename]));
            $audioEntity->setScript($script);
            $this->entityManager->persist($audioEntity);
            $this->entityManager->flush();

            // Marquer comme terminé
            $task->setStatus('completed')
                 ->setResult([
                     'audio_url' => '/audio/' . $script->getId() . '/' . $filename,
                     'voice' => $message->voice,
                     'duration' => 'Generated successfully'
                 ]);
            $this->entityManager->flush();

            // Notifier la fin
            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'completed',
                'result' => $task->getResult(),
                'message' => 'Audio generated successfully with voice: ' . $message->voice
            ]);

            // Notifier la mise à jour du script
            $this->mercurePublisher->publishScriptUpdate($message->scriptId, $message->userId, [
                'type' => 'audio_generated',
                'data' => $task->getResult()
            ]);

        } catch (\Exception $e) {
            // Gérer l'erreur
            if (isset($task)) {
                $task->setStatus('failed')
                     ->setError($e->getMessage());
                $this->entityManager->flush();
            }

            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
} 