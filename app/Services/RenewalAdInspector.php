<?php

namespace App\Services;

use App\Contracts\EscortPortalClient;
use App\Exceptions\EscortPortalTimeoutException;
use App\Exceptions\EscortPortalUnavailableException;
use App\Exceptions\InvalidAdUrlException;
use App\Exceptions\PhoneExtractionFailedException;
use App\Scraper\Portals\AmaterkySkPhoneExtractor;
use App\Scraper\Portals\EuroGirlsEscortPhoneExtractor;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class RenewalAdInspector
{
    public function __construct(
        private readonly EscortPortalClient $portal,
        private readonly AmaterkySkPhoneExtractor $amaterky,
        private readonly EuroGirlsEscortPhoneExtractor $eurogirls,
    ) {}

    public function inspect(string $url, string $phone): array
    {
        try {
            $html = $this->portal->fetchAdHtml($url);
            if ($html === null || $html === '') {
                return ['status' => 'unknown', 'retry_after' => $this->transportDelay()];
            }
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $crawler = new Crawler($html);
            if ($crawler->filter('#challenge-form, #cf-challenge-running, .g-recaptcha, iframe[src*="captcha"]')->count() > 0
                || in_array(trim($crawler->filter('title')->text('')), ['Just a moment...', 'Attention Required! | Cloudflare'], true)) {
                return ['status' => 'unknown', 'retry_after' => $this->transportDelay()];
            }
            if ($this->amaterky->supports($host)) {
                foreach ($crawler->filter('h2.alert-heading') as $node) {
                    $heading = trim($node->textContent);
                    if (in_array($heading, ['Vypnuty zadavatelom', 'Vypnutý zadávateľom'], true)) {
                        return ['status' => 'ad_disabled'];
                    }
                    if ($heading === 'Vypršala platnosť') {
                        return ['status' => 'ad_expired'];
                    }
                }
                $phones = $this->amaterky->extractAll($html);
                foreach ($crawler->filter('a.detail-floater-link-phone, a.detail-floater-link-sms, .card.card-contact h2') as $node) {
                    $fragment = $node->ownerDocument->saveHTML($node);
                    if ($node->tagName === 'h2') {
                        $fragment = '<div class="card card-contact">'.$fragment.'</div>';
                    }
                    if ($this->amaterky->extractAll($fragment) === []) {
                        return ['status' => 'unknown', 'retry_after' => $this->transportDelay()];
                    }
                }
            } elseif ($this->eurogirls->supports($host)) {
                $phones = $this->eurogirls->extractAll($html);
                foreach ($crawler->filter('#js-phone .js-phone-item') as $node) {
                    $fragment = '<div id="js-phone">'.$node->ownerDocument->saveHTML($node).'</div>';
                    if ($this->eurogirls->extractAll($fragment) === []) {
                        return ['status' => 'unknown', 'retry_after' => $this->transportDelay()];
                    }
                }
                foreach ($crawler->filter('#js-phone .js-phone-item .js-phone, #js-phone .js-phone-item [data-phone], #js-phone .js-phone-item [data-telegram], #js-phone .js-phone-item [data-whatsapp], #js-phone .js-phone-item [data-viber]') as $node) {
                    $fragment = '<div id="js-phone"><div class="js-phone-item">'.$node->ownerDocument->saveHTML($node).'</div></div>';
                    if ($this->eurogirls->extractAll($fragment) === []) {
                        return ['status' => 'unknown', 'retry_after' => $this->transportDelay()];
                    }
                }
            } else {
                $phones = [];
            }

            return ['status' => $phones === [] ? 'unknown' : (in_array($phone, $phones, true) ? 'active' : 'contact_changed'),
                'retry_after' => $phones === [] ? $this->transportDelay() : 0];
        } catch (EscortPortalTimeoutException|EscortPortalUnavailableException|InvalidAdUrlException|PhoneExtractionFailedException $exception) {
            $retryAfter = $exception->meta()['retry_after'] ?? 0;
            $delay = max($this->transportDelay(), is_int($retryAfter) ? max(0, $retryAfter) : 0);

            return ['status' => 'unknown', 'retry_after' => $delay];
        } catch (\Throwable $exception) {
            // Exception messages, traces and context may contain fetched URLs or contacts.
            Log::error('renewal.inspection_failed', ['exception_class' => $exception::class]);

            return ['status' => 'unknown', 'retry_after' => $this->transportDelay()];
        }
    }

    private function transportDelay(): int
    {
        return $this->portal instanceof HttpEscortPortalClient ? $this->portal->renewalRetryAfter() : 0;
    }
}
