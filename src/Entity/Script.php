<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
class Script
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'scripts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'script', targetEntity: Prompt::class)]
    private $prompts;

    #[ORM\OneToMany(mappedBy: 'script', targetEntity: Image::class)]
    private $images;

    #[ORM\OneToMany(mappedBy: 'script', targetEntity: Audio::class)]
    private $audios;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->prompts = new \Doctrine\Common\Collections\ArrayCollection();
        $this->images = new \Doctrine\Common\Collections\ArrayCollection();
        $this->audios = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getPrompts() { return $this->prompts; }
    public function addPrompt(Prompt $prompt): self { if (!$this->prompts->contains($prompt)) { $this->prompts[] = $prompt; $prompt->setScript($this); } return $this; }
    public function removePrompt(Prompt $prompt): self { if ($this->prompts->removeElement($prompt)) { if ($prompt->getScript() === $this) { $prompt->setScript(null); } } return $this; }
    public function getImages() { return $this->images; }
    public function addImage(Image $image): self { if (!$this->images->contains($image)) { $this->images[] = $image; $image->setScript($this); } return $this; }
    public function removeImage(Image $image): self { if ($this->images->removeElement($image)) { if ($image->getScript() === $this) { $image->setScript(null); } } return $this; }
    public function getAudios() { return $this->audios; }
    public function addAudio(Audio $audio): self { if (!$this->audios->contains($audio)) { $this->audios[] = $audio; $audio->setScript($this); } return $this; }
    public function removeAudio(Audio $audio): self { if ($this->audios->removeElement($audio)) { if ($audio->getScript() === $this) { $audio->setScript(null); } } return $this; }
}
