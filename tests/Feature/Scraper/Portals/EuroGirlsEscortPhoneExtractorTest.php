<?php

namespace Tests\Feature\Scraper\Portals;

use App\Scraper\Portals\EuroGirlsEscortPhoneExtractor;
use Tests\TestCase;

class EuroGirlsEscortPhoneExtractorTest extends TestCase
{
    public function test_it_extracts_the_number_from_the_telegram_attribute(): void
    {
        $html = <<<'HTML'
<div id="js-phone">
  <div class="js-phone-item">
    <strong>
      <span class="flag-icon flag-icon-cz"></span>
      <span class="opacity-horizontal">+420&nbsp;792</span>
      <a class="js-phone js-stt-click btn small" href="#" data-id="651611" data-type="phone_girl" data-phone="AXC-|QLC|XDC|IDI">Show phone</a>
      <span class="social-icons">
        <i class="icon-whatsapp js-tooltip"><span class="tooltip">WhatsApp</span></i>
        <i class="icon-viber js-tooltip"><span class="tooltip">Viber</span></i>
        <i class="icon-telegram js-tooltip" data-telegram="+420792412818"><span class="tooltip">Telegram</span></i>
      </span>
    </strong>
  </div>
</div>
HTML;

        $result = app(EuroGirlsEscortPhoneExtractor::class)->extract($html);

        $this->assertSame('+420792412818', $result);
    }

    public function test_it_returns_null_when_only_a_visible_prefix_is_present(): void
    {
        $html = <<<'HTML'
<div id="js-phone">
  <div class="js-phone-item">
    <strong>
      <span class="opacity-horizontal">+420&nbsp;792</span>
      <a class="js-phone js-stt-click btn small" href="#" data-id="651611" data-type="phone_girl">Show phone</a>
    </strong>
  </div>
</div>
HTML;

        $result = app(EuroGirlsEscortPhoneExtractor::class)->extract($html);

        $this->assertNull($result);
    }

    public function test_it_decodes_the_obfuscated_data_phone_value_when_no_social_number_is_present(): void
    {
        $html = <<<'HTML'
<div id="js-phone">
  <div class="js-phone-item">
    <strong>
      <span class="opacity-horizontal">+421&nbsp;944</span>
      <a class="js-phone js-stt-click btn small" href="#" data-id="454" data-type="phone_girl" data-phone="AXCD|LXX|_CX|IIL">Show phone</a>
      <span class="social-icons">
        <i class="icon-whatsapp js-tooltip"><span class="tooltip">WhatsApp</span></i>
        <i class="icon-viber js-tooltip"><span class="tooltip">Viber</span></i>
      </span>
    </strong>
  </div>
</div>
HTML;

        $result = app(EuroGirlsEscortPhoneExtractor::class)->extract($html);

    $this->assertSame('+421944524889', $result);
    }

    public function test_it_decodes_a_greek_number_from_the_obfuscated_data_phone_value(): void
    {
        $html = <<<'HTML'
<div id="js-phone">
  <div class="js-phone-item">
    <strong>
      <span class="flag-icon flag-icon-gr"></span>
      <span class="opacity-horizontal">+30&nbsp;697&nbsp;</span>
      <a class="js-phone js-stt-click btn small" href="#" data-id="405324" data-type="phone_girl" data-phone="Ab-|lLQ|blX|bXb-">Show phone</a>
    </strong>
  </div>
</div>
HTML;

        $result = app(EuroGirlsEscortPhoneExtractor::class)->extract($html);

        $this->assertSame('+306973643430', $result);
    }

    public function test_it_decodes_a_philippines_number_from_the_obfuscated_data_phone_value(): void
    {
        $html = <<<'HTML'
<div id="js-phone">
  <div class="js-phone-item">
    <strong>
      <span class="flag-icon flag-icon-ph"></span>
      <span class="opacity-horizontal">+63&nbsp;907&nbsp;</span>
      <a class="js-phone js-stt-click btn small" href="#" data-id="1013278" data-type="phone_girl" data-phone="Alb|L-Q|LCI|QLC_">Show phone</a>
    </strong>
  </div>
</div>
HTML;

        $result = app(EuroGirlsEscortPhoneExtractor::class)->extract($html);

        $this->assertSame('+639079287925', $result);
    }

    public function test_it_extracts_all_candidate_numbers_from_a_multi_number_page_in_dom_order(): void
    {
        $html = <<<'HTML'
<div id="js-phone">
  <div class="js-phone-item">
    <div class="row">
      <span><i class="icon-phone"></i> Cell phone:</span>
      <strong>
        <span class="flag-icon flag-icon-tr"></span>
        <span class="opacity-horizontal">+90&nbsp;501&nbsp;</span>
        <a class="js-phone js-stt-click btn small" href="#" data-id="1050583" data-type="phone_girl" data-phone="AL-|_-D|bCX|-QXL">Show phone</a>
        <span class="social-icons">
          <i class="icon-telegram js-tooltip" data-telegram="LT_999"><span class="tooltip">Telegram</span></i>
        </span>
      </strong>
    </div>
  </div>
  <div class="js-phone-item">
    <div class="row">
      <span><i class="icon-phone"></i> Cell phone 2:</span>
      <strong>
        <span class="flag-icon flag-icon-ru"></span>
        <span class="opacity-horizontal">+7&nbsp;965&nbsp;9</span>
        <a class="js-phone js-stt-click btn small" href="#" data-id="1050583" data-type="phone_girl" data-phone="AQ|Ll_|LQ_|QDLI">Show phone</a>
        <span class="social-icons">
          <i class="icon-telegram js-tooltip" data-telegram="+79659757198"><span class="tooltip">Telegram</span></i>
        </span>
      </strong>
    </div>
  </div>
</div>
HTML;

        $result = app(EuroGirlsEscortPhoneExtractor::class)->extractAll($html);

        $this->assertSame([
            '+905013240749',
            '+79659757198',
        ], $result);
    }
}
