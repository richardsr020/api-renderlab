<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $binanceId = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $walletAddress = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isBlocked = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Script::class)]
    private $scripts;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Payment::class)]
    private $payments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->scripts = new \Doctrine\Common\Collections\ArrayCollection();
        $this->payments = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getRoles(): array { $roles = $this->roles; $roles[] = 'ROLE_USER'; return array_unique($roles); }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }
    public function getBinanceId(): ?string { return $this->binanceId; }
    public function setBinanceId(?string $binanceId): self { $this->binanceId = $binanceId; return $this; }
    public function getWalletAddress(): ?string { return $this->walletAddress; }
    public function setWalletAddress(?string $walletAddress): self { $this->walletAddress = $walletAddress; return $this; }
    public function isBlocked(): bool { return $this->isBlocked; }
    public function setIsBlocked(bool $isBlocked): self { $this->isBlocked = $isBlocked; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUserIdentifier(): string { return $this->email; }
    public function eraseCredentials(): void {}
    public function getScripts() { return $this->scripts; }
    public function addScript(Script $script): self { if (!$this->scripts->contains($script)) { $this->scripts[] = $script; $script->setUser($this); } return $this; }
    public function removeScript(Script $script): self { if ($this->scripts->removeElement($script)) { if ($script->getUser() === $this) { $script->setUser(null); } } return $this; }
    public function getPayments() { return $this->payments; }
    public function addPayment(Payment $payment): self { if (!$this->payments->contains($payment)) { $this->payments[] = $payment; $payment->setUser($this); } return $this; }
    public function removePayment(Payment $payment): self { if ($this->payments->removeElement($payment)) { if ($payment->getUser() === $this) { $payment->setUser(null); } } return $this; }
}
