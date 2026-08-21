<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\BreachItem;
use App\Repository\BreachMatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckBreachesCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $application = new Application(self::$kernel);
        $command = $application->find('app:breach:check');
        $this->commandTester = new CommandTester($command);
    }

    public function testFirstRunReportsNewMatchAndSecondRunReportsNone(): void
    {
        $this->persistBreachItem('SFR', 'sfr-guid-1', 'SFR: more than 2 million rows affected');

        $exitCode = $this->commandTester->execute(['--no-fetch' => true]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('SFR', $this->commandTester->getDisplay());
        self::assertStringContainsString('1 new match', $this->commandTester->getDisplay());

        /** @var BreachMatchRepository $matchRepository */
        $matchRepository = static::getContainer()->get(BreachMatchRepository::class);
        self::assertCount(1, $matchRepository->findAllWithItem());

        $secondRun = new CommandTester((new Application(self::$kernel))->find('app:breach:check'));
        $secondExitCode = $secondRun->execute(['--no-fetch' => true]);

        self::assertSame(0, $secondExitCode);
        self::assertStringContainsString('No new match', $secondRun->getDisplay());
        self::assertCount(1, $matchRepository->findAllWithItem());
    }

    public function testNoMatchWhenNoCompanyMentioned(): void
    {
        $this->persistBreachItem('Unknown company', 'unknown-guid', 'A company unrelated to the watchlist.');

        $exitCode = $this->commandTester->execute(['--no-fetch' => true]);

        self::assertSame(0, $exitCode);
    }

    public function testDryRunDoesNotPersistAnything(): void
    {
        $this->persistBreachItem('SFR', 'sfr-guid-dry-run', 'SFR targeted by a data breach');

        $exitCode = $this->commandTester->execute(['--no-fetch' => true, '--dry-run' => true]);

        self::assertSame(2, $exitCode);

        /** @var BreachMatchRepository $matchRepository */
        $matchRepository = static::getContainer()->get(BreachMatchRepository::class);
        self::assertCount(0, $matchRepository->findAllWithItem());
    }

    public function testJsonOutputIsValidJson(): void
    {
        $this->persistBreachItem('SFR', 'sfr-guid-json', 'SFR targeted by a data breach');

        $this->commandTester->execute(['--no-fetch' => true, '--json' => true]);

        $payload = json_decode($this->commandTester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['newMatchesCount']);
        self::assertCount(1, $payload['matches']);
        self::assertSame('SFR', $payload['matches'][0]['company']);
    }

    public function testNotificationsAreSentToEveryConfiguredChannel(): void
    {
        // tests/Fixtures/notifications.yaml (see config/services_test.yaml) declares 4 channels
        // on "null://" transports: no network access, but the send genuinely "succeeds".
        $this->persistBreachItem('SFR', 'sfr-guid-notify', 'SFR targeted by a data breach');

        $this->commandTester->execute(['--no-fetch' => true, '--json' => true]);

        $payload = json_decode($this->commandTester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['notifications']['email']['sent']);
        self::assertSame(1, $payload['notifications']['free_mobile']['sent']);
        self::assertSame(1, $payload['notifications']['mattermost']['sent']);
        self::assertSame(1, $payload['notifications']['pushover']['sent']);
        self::assertNotNull($payload['matches'][0]['notifiedAt']);
    }

    public function testNoNotifyOptionSkipsNotifications(): void
    {
        $this->persistBreachItem('SFR', 'sfr-guid-no-notify', 'SFR targeted by a data breach');

        $this->commandTester->execute(['--no-fetch' => true, '--json' => true, '--no-notify' => true]);

        $payload = json_decode($this->commandTester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([], $payload['notifications']);
        self::assertNull($payload['matches'][0]['notifiedAt']);
    }

    public function testChannelOptionRestrictsNotificationsToOneChannel(): void
    {
        $this->persistBreachItem('SFR', 'sfr-guid-channel', 'SFR targeted by a data breach');

        $this->commandTester->execute(['--no-fetch' => true, '--json' => true, '--channel' => 'email']);

        $payload = json_decode($this->commandTester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('email', $payload['notifications']);
        self::assertArrayNotHasKey('free_mobile', $payload['notifications']);
        self::assertArrayNotHasKey('mattermost', $payload['notifications']);
        self::assertArrayNotHasKey('pushover', $payload['notifications']);
    }

    public function testUnknownChannelFails(): void
    {
        $exitCode = $this->commandTester->execute(['--channel' => 'unknown']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Unknown channel', $this->commandTester->getDisplay());
    }

    private function persistBreachItem(string $title, string $guid, string $content): void
    {
        $item = new BreachItem(
            feedUrl: 'https://frenchbreaches.com/feed.xml',
            guid: $guid,
            title: $title,
            link: 'https://frenchbreaches.com/alertes/'.$guid,
            content: $content,
            categories: ['email', 'name'],
            publishedAt: new \DateTimeImmutable('2026-08-20T10:00:00+00:00'),
            contentHash: hash('sha256', $title.$content),
            firstSeenAt: new \DateTimeImmutable(),
        );

        $this->entityManager->persist($item);
        $this->entityManager->flush();
    }
}
