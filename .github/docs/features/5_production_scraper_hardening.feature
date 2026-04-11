Feature: Production Ad Scraper Hardening (amaterky.sk)
  In order to verify the identity of a worker attempting to use the Shield
  As the Laravel verification backend
  I need to observe rotating-proxy health and reliably extract E.164 phone numbers from real amaterky.sk profiles

  Scenario: Recording rotating-proxy telemetry before parser rollout
    Given the backend is configured to use the Webshare rotating proxy endpoint
    And the operator runs the scraper probe command against an amaterky.sk URL
    When the probe issues repeated requests through the rotating endpoint
    Then each attempt should be recorded with an outcome such as success, blocked, timeout, transport_error, or server_error
    And the recorded telemetry should help separate proxy-pool quality problems from parser regressions

  Scenario: Extracting a phone number from the preferred tel link
    Given a saved amaterky.sk HTML page contains a contact link with href "tel:+421944493008"
    When the amaterky.sk extractor parses the page
    Then it should return the exact E.164 number "+421944493008"

  Scenario: Falling back when the preferred contact node is missing
    Given a saved amaterky.sk HTML page has no tel link
    And the page still contains either an SMS link or a contact heading with the same number
    When the amaterky.sk extractor parses the page
    Then it should fall back in this order: SMS link first, then contact heading
    And it should still return the normalized E.164 phone number

  Scenario: Normalizing Slovak phone-number formats into E.164
    Given the extractor sees a raw number like "0944 493 008" or "Tel: 00421 903-123-456 (WhatsApp only)"
    When the normalizer processes the value
    Then it should strip non-numeric noise
    And it should convert local "0" or international "00421" prefixes into "+421"
    And the final output must be valid E.164

  Scenario: Returning no usable number for missing or suspended ads
    Given the fetched amaterky.sk page is suspended or contains no usable phone number
    When the extraction job processes the page
    Then the extractor should return no phone number
    And the surrounding Phase 1 initiate flow should preserve the existing hard-stop behavior for non-usable ads

  Scenario: Classifying an ad that is temporarily disabled by its owner
    Given the fetched amaterky.sk page contains the heading "Vypnutý zadávateľom"
    When the extraction job processes the page
    Then the backend should classify the ad as temporarily disabled
    And the auth initiate API should block access with a distinct hard-stop error instead of a generic extraction failure
    And phone-number extraction should not start from that ad state
