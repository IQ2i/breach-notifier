<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Entity\BreachItem;
use App\Entity\BreachMatch;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Real rendering of the email templates: BreachNotifier's unit tests mock the
 * mailer and never run the Twig engine, which would let a syntax error or an
 * unknown variable in the templates slip through.
 */
final class BreachEmailTemplatesTest extends KernelTestCase
{
    public function testHtmlAndTextTemplatesRenderWithoutError(): void
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        $item = new BreachItem('https://example.com/feed.xml', 'guid-1', 'SFR', 'https://example.com/sfr', 'content', ['email', 'name'], new \DateTimeImmutable('2026-08-20T10:00:00+00:00'), 'hash', new \DateTimeImmutable());
        $matches = [new BreachMatch($item, 'SFR', 'Société Française du Radiotéléphone', 'content', '… sfr targeted by a data breach …', new \DateTimeImmutable())];

        $html = $twig->render('email/breaches.html.twig', ['matches' => $matches]);
        $text = $twig->render('email/breaches.txt.twig', ['matches' => $matches]);

        self::assertStringContainsString('SFR', $html);
        self::assertStringContainsString('https://example.com/sfr', $html);
        self::assertStringContainsString('SFR', $text);
        self::assertStringContainsString('https://example.com/sfr', $text);
    }

    public function testShortTemplateRendersWithoutError(): void
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        $item = new BreachItem('https://example.com/feed.xml', 'guid-1', 'SFR', 'https://example.com/sfr', 'content', ['email', 'name'], new \DateTimeImmutable('2026-08-20T10:00:00+00:00'), 'hash', new \DateTimeImmutable());
        $matches = [new BreachMatch($item, 'SFR', 'Société Française du Radiotéléphone', 'content', '… sfr targeted by a data breach …', new \DateTimeImmutable())];

        $short = $twig->render('notification/short.txt.twig', ['matches' => $matches]);

        self::assertStringContainsString('SFR', $short);
        self::assertStringContainsString('https://example.com/sfr', $short);
    }
}
