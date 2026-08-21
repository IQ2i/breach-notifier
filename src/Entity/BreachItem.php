<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BreachItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A persisted entry from an RSS/Atom feed (a data breach alert).
 */
#[ORM\Entity(repositoryClass: BreachItemRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_breach_item_guid', columns: ['guid'])]
class BreachItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $feedUrl;

    /**
     * Stable identifier of the item within its feed (RSS guid, link, or fallback hash).
     */
    #[ORM\Column(length: 512)]
    private string $guid;

    #[ORM\Column(length: 500)]
    private string $title;

    #[ORM\Column(length: 1000)]
    private string $link;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    private array $categories = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $firstSeenAt;

    /**
     * Fingerprint of the title + content + categories, used to detect that an article was
     * edited (the feed sometimes adds "Updated on ..." blocks) and re-run matching on it.
     */
    #[ORM\Column(length: 64)]
    private string $contentHash;

    /** @var Collection<int, BreachMatch> */
    #[ORM\OneToMany(targetEntity: BreachMatch::class, mappedBy: 'breachItem', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $matches;

    public function __construct(string $feedUrl, string $guid, string $title, string $link, string $content, array $categories, ?\DateTimeImmutable $publishedAt, string $contentHash, \DateTimeImmutable $firstSeenAt)
    {
        $this->feedUrl = $feedUrl;
        $this->guid = $guid;
        $this->title = $title;
        $this->link = $link;
        $this->content = $content;
        $this->categories = $categories;
        $this->publishedAt = $publishedAt;
        $this->contentHash = $contentHash;
        $this->firstSeenAt = $firstSeenAt;
        $this->matches = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeedUrl(): string
    {
        return $this->feedUrl;
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return string[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    /**
     * Updates the item's content if the feed has changed. Returns true if an update occurred.
     *
     * @param string[] $categories
     */
    public function updateFrom(string $title, string $link, string $content, array $categories, ?\DateTimeImmutable $publishedAt, string $contentHash): bool
    {
        if ($contentHash === $this->contentHash) {
            return false;
        }

        $this->title = $title;
        $this->link = $link;
        $this->content = $content;
        $this->categories = $categories;
        $this->publishedAt = $publishedAt;
        $this->contentHash = $contentHash;

        return true;
    }
}
