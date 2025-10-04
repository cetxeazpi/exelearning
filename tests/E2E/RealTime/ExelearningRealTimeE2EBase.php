<?php

declare(strict_types=1);

namespace App\Tests\E2E\RealTime;

use App\Tests\E2E\ExelearningE2EBase;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Facebook\WebDriver\WebDriverBy;

/**
 * Base class for real-time (Mercure/WebSocket/etc.) end-to-end tests.
 * It provides two separate browser sessions: "main" and "secondary".
 */
abstract class ExelearningRealTimeE2EBase extends ExelearningE2EBase
{
    protected ?Client $secondaryClient  = null;

    /**
     * Creates two logged-in browser clients, each possibly with distinct credentials.
     *
     * @return void
     */
    protected function createRealTimeClients(): void
    {
        // 1) Main client, default user
        $this->mainClient = $this->login(); // uses $this->createTestClient()

        // 2) Create and log in the secondary client
        //    This is an *isolated* browser that can interact with the main client in real time.
        $this->secondaryClient = static::createAdditionalPantherClient();

        // By default createAdditionalPantherClient() reuses the same "base URI" as the first.
        // If needed, confirm you have the same environment or you can override the trait code.
        $this->login($this->secondaryClient);
    }

    /**
     * Retrieves the current page URL after saving the document.
     *
     * The URL returned by this method is the same as the current one,
     * but the document must be saved first to ensure that all related
     * data (such as the session or project state) has been persisted.
     * 
     * This method simulates a real user action by clicking the "Save"
     * button (#head-top-save-button) before returning the current URL.
     *
     * @return string
     */
    protected function getMainShareUrl(): string
    {
        if (null === $this->mainClient) {
            return '';
        }

        $this->waitForWorkareaBoot($this->mainClient);

        // Wait until the save button is visible and clickable
        $this->mainClient->waitFor('#head-top-save-button');

        // Click the save button (simulating a real user action)
        $button = $this->mainClient->findElement(WebDriverBy::cssSelector('#head-top-save-button'));
        $button->click();

        // Return the full URL currently in the browser
        return (string) $this->mainClient->getCurrentURL();

    }

    /**
     * Wait until the workarea removes the loading overlay.
     */
    protected function waitForWorkareaBoot(Client $client, int $timeoutSeconds = 60): void
    {
        $client->waitFor('#head-top-share-button');
        $client->wait($timeoutSeconds)->until(
            function () use ($client) {
                $isLoading = $client->executeScript(
                    "const el = document.querySelector('#load-screen-main'); return el ? el.classList.contains('loading') : false;"
                );

                return !$isLoading;
            },
            'Workarea never finished loading.'
        );
    }

    /**
     * Poll until the concurrent user widget reflects the expected amount.
     */
    protected function waitForConcurrentUserCount(Client $client, int $expected, int $timeoutSeconds = 45): void
    {
        $client->waitFor('#exe-concurrent-users');
        $client->wait($timeoutSeconds)->until(
            function () use ($client, $expected) {
                $value = $this->getConcurrentUserCount($client);

                return $value === $expected;
            },
            sprintf('Expected %d concurrent users but timed out.', $expected)
        );
    }

    protected function getConcurrentUserCount(Client $client): int
    {
        $raw = $client->executeScript(
            "const el = document.querySelector('#exe-concurrent-users'); return el ? el.getAttribute('num') : null;"
        );

        if (null === $raw || '' === $raw) {
            return 0;
        }

        $numeric = (int) $raw;

        return max($numeric, 0);
    }


    /**
     * Helper method to assert the existence of a selector in a given client.
     */
    protected function assertSelectorExistsIn(Client $client, string $selector, string $message = ''): void
    {
        $client->waitFor($selector);
        $this->assertGreaterThan(
            0,
            $client->getCrawler()->filter($selector)->count(),
            $message ?: sprintf('Expected selector "%s" not found for the given client.', $selector)
        );
    }


    /**
     * Helper method to assert the existence of a selector in a given client.
     */
    protected function assertSelectorTextContainsIn(Client $client, string $selector, string $message = ''): void
    {
        // Wait for the selector to be present in the DOM
        $crawler = $client->waitFor($selector);

        // Get the text of the selected element
        $elementText = $crawler->filter($selector)->text();

        // Verify that the element's text contains the expected message
        $this->assertStringContainsString($message, $elementText, sprintf(
            'Failed asserting that selector "%s" contains the text "%s".',
            $selector,
            $message
        ));
    }

    /**
     * Called automatically when a test fails or throws an exception.
     */
    protected function onNotSuccessfulTest(\Throwable $t): never
    {
        if ($this->secondaryClient instanceof Client) {
            try {
                $this->captureAllWindowsScreenshots($this->secondaryClient, 'secondary_fail');
            } catch (\Throwable $e) {
                // Avoid masking the original error if screenshot fails
                fwrite(STDERR, "[Screenshot failed]: " . $e->getMessage() . "\n");
            }
        }

        // Re-throw so PHPUnit marks the test as failed
        parent::onNotSuccessfulTest($t);
    }


}
