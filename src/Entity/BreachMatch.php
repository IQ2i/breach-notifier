<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BreachMatchRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A detected match between a watched company and a BreachItem.
 *
 * The unique constraint (breach_item_id, company_name) guarantees that the same
 * breach is reported only once per company, even if the command is run
 * several times on the same feed.
 */
#[ORM\Entity(repositoryClass: BreachMatchRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_breach_match_item_company', columns: ['breach_item_id', 'company_name'])]
class BreachMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BreachItem::class, inversedBy: 'matches')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BreachItem $breachItem;

    #[ORM\Column(length: 255)]
    private string $companyName;

    #[ORM\Column(length: 255)]
    private string $matchedTerm;

    #[ORM\Column(length: 20)]
    private string $matchedField;

    #[ORM\Column(type: 'text')]
    private string $snippet;

    #[ORM\Column]
    private \DateTimeImmutable $detectedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    public function __construct(BreachItem $breachItem, string $companyName, string $matchedTerm, string $matchedField, string $snippet, \DateTimeImmutable $detectedAt)
    {
        $this->breachItem = $breachItem;
        $this->companyName = $companyName;
        $this->matchedTerm = $matchedTerm;
        $this->matchedField = $matchedField;
        $this->snippet = $snippet;
        $this->detectedAt = $detectedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBreachItem(): BreachItem
    {
        return $this->breachItem;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getMatchedTerm(): string
    {
        return $this->matchedTerm;
    }

    public function getMatchedField(): string
    {
        return $this->matchedField;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }

    public function getDetectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function isNotified(): bool
    {
        return null !== $this->notifiedAt;
    }

    public function markNotified(\DateTimeImmutable $at): void
    {
        $this->notifiedAt = $at;
    }
}
