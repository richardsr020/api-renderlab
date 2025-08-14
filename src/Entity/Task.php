<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tasks')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $taskId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type; // 'prompts', 'images', 'audio'

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending'; // 'pending', 'in_progress', 'completed', 'failed'

    #[ORM\Column(type: 'integer')]
    private int $scriptId;

    #[ORM\Column(type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $result = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getTaskId(): string { return $this->taskId; }
    public function getType(): string { return $this->type; }
    public function getStatus(): string { return $this->status; }
    public function getScriptId(): int { return $this->scriptId; }
    public function getUserId(): int { return $this->userId; }
    public function getResult(): ?array { return $this->result; }
    public function getError(): ?string { return $this->error; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    // Setters
    public function setTaskId(string $taskId): self { $this->taskId = $taskId; return $this; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function setStatus(string $status): self { $this->status = $status; $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function setScriptId(int $scriptId): self { $this->scriptId = $scriptId; return $this; }
    public function setUserId(int $userId): self { $this->userId = $userId; return $this; }
    public function setResult(?array $result): self { $this->result = $result; return $this; }
    public function setError(?string $error): self { $this->error = $error; return $this; }
} 