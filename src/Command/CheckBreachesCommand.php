<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\BreachItem;
use App\Entity\BreachMatch;
use App\Feed\FeedFetchException;
use App\Feed\FeedReader;
use App\Feed\FeedRegistry;
use App\Matching\CompanyMatcher;
use App\Notification\BreachNotifier;
use App\Notification\ChannelRegistry;
use App\Notification\NotificationReport;
use App\Repository\BreachItemRepository;
use App\Repository\BreachMatchRepository;
use App\Watchlist\WatchlistProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads the configured RSS/Atom feeds, searches for watchlist companies
 * in new breaches, and reports new matches.
 */
#[AsCommand(
    name: 'app:breach:check',
    description: 'Searches for watched companies in the RSS feeds of data breaches',
)]
final class CheckBreachesCommand extends Command
{
    /** Exit code used when new matches have been detected. */
    private const int MATCHES_FOUND = 2;

    public function __construct(
        private readonly FeedRegistry $feedRegistry,
        private readonly FeedReader $feedReader,
        private readonly WatchlistProvider $watchlistProvider,
        private readonly CompanyMatcher $companyMatcher,
        private readonly BreachNotifier $notifier,
        private readonly ChannelRegistry $channelRegistry,
        private readonly BreachItemRepository $breachItemRepository,
        private readonly BreachMatchRepository $breachMatchRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Shows all known matches, not just the ones from this run')]
        bool $all = false,
        #[Option('JSON output instead of the console table')]
        bool $json = false,
        #[Option("Doesn't persist anything to the database and doesn't send any notification")]
        bool $dryRun = false,
        #[Option("Doesn't download any feed, only re-matches the existing database")]
        bool $noFetch = false,
        #[Option("Doesn't send any notification for this run")]
        bool $noNotify = false,
        #[Option('Restricts processing to a single feed (see feeds.yaml)')]
        ?string $feed = null,
        #[Option('Restricts notifications to a single channel (see notifications.yaml)')]
        ?string $channel = null,
    ): int {
        $now = new \DateTimeImmutable();

        $sources = $this->feedRegistry->all();
        if (null !== $feed) {
            $source = $this->feedRegistry->findByName($feed);
            if (null === $source) {
                $message = \sprintf('Unknown feed: "%s". Available feeds: %s', $feed, implode(', ', array_map(static fn ($f) => $f->name, $sources)));
                $json ? $io->writeln(json_encode(['error' => $message])) : $io->error($message);

                return Command::FAILURE;
            }
            $sources = [$source];
        }

        if (null !== $channel && null === $this->channelRegistry->findById($channel)) {
            $message = \sprintf('Unknown channel: "%s". Available channels: %s', $channel, implode(', ', array_map(static fn ($c) => $c->id, $this->channelRegistry->all())));
            $json ? $io->writeln(json_encode(['error' => $message])) : $io->error($message);

            return Command::FAILURE;
        }

        /** @var array<string, BreachItem> $itemsByGuid indexed by guid, DB + new ones from this run */
        $itemsByGuid = [];
        foreach ($this->breachItemRepository->findAll() as $existingItem) {
            $itemsByGuid[$existingItem->getGuid()] = $existingItem;
        }

        $feedErrors = [];
        if (!$noFetch) {
            foreach ($sources as $source) {
                try {
                    $feedItems = $this->feedReader->read($source);
                } catch (FeedFetchException $e) {
                    $feedErrors[] = $e->getMessage();
                    continue;
                }

                foreach ($feedItems as $feedItem) {
                    $existing = $itemsByGuid[$feedItem->guid] ?? null;
                    if (null !== $existing) {
                        $existing->updateFrom($feedItem->title, $feedItem->link, $feedItem->content, $feedItem->categories, $feedItem->publishedAt, $feedItem->contentHash());
                        continue;
                    }

                    $newItem = new BreachItem($feedItem->feedUrl, $feedItem->guid, $feedItem->title, $feedItem->link, $feedItem->content, $feedItem->categories, $feedItem->publishedAt, $feedItem->contentHash(), $now);
                    if (!$dryRun) {
                        $this->entityManager->persist($newItem);
                    }
                    $itemsByGuid[$feedItem->guid] = $newItem;
                }
            }
        }

        if (!$json) {
            foreach ($feedErrors as $error) {
                $io->warning($error);
            }
        }

        $knownKeys = [];
        foreach ($this->breachMatchRepository->findAllWithItem() as $existingMatch) {
            $knownKeys[$this->matchKey($existingMatch->getBreachItem()->getGuid(), $existingMatch->getCompanyName())] = true;
        }

        $companies = $this->watchlistProvider->all();
        $newMatches = [];

        foreach ($itemsByGuid as $item) {
            foreach ($this->companyMatcher->match($companies, $item->getTitle(), $item->getContent()) as $result) {
                $key = $this->matchKey($item->getGuid(), $result->company->name);
                if (isset($knownKeys[$key])) {
                    continue;
                }
                $knownKeys[$key] = true;

                $match = new BreachMatch($item, $result->company->name, $result->matchedTerm, $result->matchedField, $result->snippet, $now);
                if (!$dryRun) {
                    $this->entityManager->persist($match);
                }
                $newMatches[] = $match;
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $report = new NotificationReport();
        if (!$dryRun && !$noNotify && [] !== $newMatches && $this->notifier->isConfigured()) {
            $report = $this->notifier->notify($newMatches, $channel);

            if ($report->hasAnySuccess()) {
                foreach ($newMatches as $match) {
                    $match->markNotified($now);
                }
                $this->entityManager->flush();
            }
        }

        if (!$json) {
            foreach ($report->errors() as $channelId => $channelErrors) {
                foreach ($channelErrors as $error) {
                    $io->warning(\sprintf('Notification failed on channel "%s": %s', $channelId, $error));
                }
            }
        }

        $matchesToDisplay = $all ? array_values($this->breachMatchRepository->findAllWithItem()) : $newMatches;
        // In --all + --dry-run mode, matches found during this run are not in the database:
        // add them explicitly so they still show up.
        if ($all && $dryRun) {
            $matchesToDisplay = [...$matchesToDisplay, ...$newMatches];
        }

        if ($json) {
            $io->writeln(json_encode([
                'newMatchesCount' => \count($newMatches),
                'notifications' => $report->toArray(),
                'errors' => $feedErrors,
                'matches' => array_map($this->matchToArray(...), $matchesToDisplay),
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($io, $matchesToDisplay);
            $io->newLine();
            if ([] === $newMatches) {
                $io->success('No new match.');
            } else {
                $notifiedVia = $report->successfulChannelIds();
                $io->success(\sprintf('%d new match(es) detected%s.', \count($newMatches), [] === $notifiedVia ? '' : ' — notified via '.implode(', ', $notifiedVia)));
            }
        }

        return [] === $newMatches ? Command::SUCCESS : self::MATCHES_FOUND;
    }

    private function matchKey(string $guid, string $companyName): string
    {
        return $guid.'|'.$companyName;
    }

    /**
     * @param BreachMatch[] $matches
     */
    private function renderTable(SymfonyStyle $io, array $matches): void
    {
        if ([] === $matches) {
            return;
        }

        $io->table(
            ['Company', 'Breach', 'Published on', 'Field', 'Notified'],
            array_map(static fn (BreachMatch $m) => [
                $m->getCompanyName(),
                $m->getBreachItem()->getTitle(),
                $m->getBreachItem()->getPublishedAt()?->format('d/m/Y H:i') ?? '—',
                $m->getMatchedField(),
                $m->isNotified() ? 'yes' : 'no',
            ], $matches),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function matchToArray(BreachMatch $match): array
    {
        $item = $match->getBreachItem();

        return [
            'company' => $match->getCompanyName(),
            'matchedTerm' => $match->getMatchedTerm(),
            'matchedField' => $match->getMatchedField(),
            'snippet' => $match->getSnippet(),
            'title' => $item->getTitle(),
            'link' => $item->getLink(),
            'publishedAt' => $item->getPublishedAt()?->format(\DATE_ATOM),
            'categories' => $item->getCategories(),
            'detectedAt' => $match->getDetectedAt()->format(\DATE_ATOM),
            'notifiedAt' => $match->getNotifiedAt()?->format(\DATE_ATOM),
        ];
    }
}
