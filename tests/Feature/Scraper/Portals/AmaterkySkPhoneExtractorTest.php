<?php

namespace Tests\Feature\Scraper\Portals;

use App\Exceptions\AdTemporarilyDisabledException;
use App\Scraper\Portals\AmaterkySkPhoneExtractor;
use Tests\TestCase;

class AmaterkySkPhoneExtractorTest extends TestCase
{
    public function test_it_prefers_the_tel_link_href(): void
    {
        $html = <<<'HTML'
<div class="card card-contact">
  <h2>0944493008</h2>
  <a class="detail-floater-link detail-floater-link-phone" href="tel:+421944493008">Call</a>
  <a class="detail-floater-link detail-floater-link-sms" href="sms:+421111111111?body=Hello">SMS</a>
</div>
HTML;

        $result = app(AmaterkySkPhoneExtractor::class)->extract($html);

        $this->assertSame('+421944493008', $result);
    }

    public function test_it_falls_back_to_the_sms_link_when_the_tel_link_is_missing(): void
    {
        $html = <<<'HTML'
<div class="card card-contact">
  <a class="detail-floater-link detail-floater-link-sms" href="sms:+421944493008?body=Hello">SMS</a>
</div>
HTML;

        $result = app(AmaterkySkPhoneExtractor::class)->extract($html);

        $this->assertSame('+421944493008', $result);
    }

    public function test_it_falls_back_to_the_contact_heading_when_structured_links_are_missing(): void
    {
        $html = <<<'HTML'
<div class="card card-contact">
  <h2 class="mb-7 fw-bold">0944 493 008</h2>
</div>
HTML;

        $result = app(AmaterkySkPhoneExtractor::class)->extract($html);

        $this->assertSame('+421944493008', $result);
    }

  public function test_it_raises_a_distinct_exception_for_a_temporarily_disabled_ad(): void
  {
    $html = <<<'HTML'
<section>
  <h2 class="h4 alert-heading">Vypnutý zadávateľom</h2>
  <p>Inzerát je dočasne vypnutý zadávateľom, skúste to prosím neskôr.</p>
</section>
HTML;

    $this->expectException(AdTemporarilyDisabledException::class);

    app(AmaterkySkPhoneExtractor::class)->extract($html);
  }
}
