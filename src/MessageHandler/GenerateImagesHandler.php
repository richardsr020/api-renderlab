<?php

namespace App\MessageHandler;

use App\Entity\Script;
use App\Entity\Prompt;
use App\Entity\Image;
use App\Entity\Task;
use App\Message\GenerateImagesMessage;
use App\Service\ImageGenerationService;
use App\Service\MercurePublisherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
class GenerateImagesHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImageGenerationService $imageGenerationService,
        private MercurePublisherService $mercurePublisher
    ) {}

    public function __invoke(GenerateImagesMessage $message): void
    {
        try {
            // Créer ou récupérer la tâche
            $task = $this->entityManager->getRepository(Task::class)->findOneBy(['taskId' => $message->taskId]);
            if (!$task) {
                $task = new Task();
                $task->setTaskId($message->taskId)
                     ->setType('images')
                     ->setScriptId($message->scriptId)
                     ->setUserId($message->userId)
                     ->setStatus('pending');
                $this->entityManager->persist($task);
                $this->entityManager->flush();
            }

            // Notifier le début du traitement
            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'in_progress',
                'message' => 'Generating images from prompts...'
            ]);

            $task->setStatus('in_progress');
            $this->entityManager->flush();

            // Récupérer le script et ses prompts
            $script = $this->entityManager->getRepository(Script::class)->find($message->scriptId);
            if (!$script) {
                throw new UnrecoverableMessageHandlingException('Script not found');
            }

            $prompts = $this->entityManager->getRepository(Prompt::class)->findBy(['script' => $script]);
            if (!$prompts) {
                throw new UnrecoverableMessageHandlingException('No prompts found for this script');
            }

            $imagesResult = [];
            $totalImages = 0;
            $generatedImages = 0;

            foreach ($prompts as $promptEntity) {
                $promptList = json_decode($promptEntity->getContent(), true);
                $sceneImages = [];
                
                foreach ($promptList as $idx => $promptText) {
                    $totalImages++;
                    
                    // Notifier la progression
                    $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                        'status' => 'in_progress',
                        'progress' => [
                            'current' => $generatedImages + 1,
                            'total' => $totalImages,
                            'message' => "Generating image " . ($generatedImages + 1) . "/{$totalImages}..."
                        ]
                    ]);

                    $saveDir = __DIR__ . '/../../public/images/' . $script->getId();
                    $filename = 'scene_' . $promptEntity->getId() . '_img_' . ($idx+1) . '.png';
                    
                    $path = $this->imageGenerationService->generateImage($promptText, $saveDir, $filename);
                    if ($path) {
                        $sceneImages[] = '/images/' . $script->getId() . '/' . $filename;
                        $generatedImages++;
                    }
                }

                // Stocker dans la table Image
                $imageEntity = new Image();
                $imageEntity->setUrl(json_encode($sceneImages));
                $imageEntity->setScript($script);
                $this->entityManager->persist($imageEntity);
                $imagesResult[$promptEntity->getId()] = $sceneImages;
            }

            $this->entityManager->flush();

            // Marquer comme terminé
            $task->setStatus('completed')
                 ->setResult([
                     'total_images' => $totalImages,
                     'generated_images' => $generatedImages,
                     'scenes' => count($imagesResult)
                 ]);
            $this->entityManager->flush();

            // Notifier la fin
            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'completed',
                'result' => $task->getResult(),
                'message' => "Generated {$generatedImages} images successfully"
            ]);

            // Notifier la mise à jour du script
            $this->mercurePublisher->publishScriptUpdate($message->scriptId, $message->userId, [
                'type' => 'images_generated',
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