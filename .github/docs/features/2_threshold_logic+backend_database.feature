Feature: Reporting Threshold and Zero-Knowledge Database
  In order to protect workers without compromising privacy or allowing spam
  As a verified backend system
  I need to process hashed reports, prevent duplicates, and enforce a 3-strike promotion rule

  Scenario: Submitting a new report (Level 1 Buffer)
    Given a verified worker submits a report for "client_hash_A" with the feature "No-Show"
    When the system processes the report
    Then the database should store the report using only 64-character SHA-256 strings
    And the database must not contain any plain-text phone numbers
    And the unique reporter count for "No-Show" on "client_hash_A" should be 1
    And the client's status should remain "Level 1" (hidden from sync)

  Scenario: Preventing duplicate reports (Anti-Spam)
    Given "worker_hash_X" has already reported "client_hash_B" for "Aggressive"
    When "worker_hash_X" submits another "Aggressive" report for "client_hash_B"
    Then the system should ignore the duplicate submission
    And the system should return a 422 Unprocessable Entity with a duplicate error
    And the unique reporter count for "Aggressive" on "client_hash_B" should remain 1

  Scenario: Promoting a client to Level 2 (The 3-Strike Rule)
    Given "client_hash_C" already has 2 unique reports for "Non-Payment"
    When a 3rd distinct worker submits a "Non-Payment" report for "client_hash_C"
    Then the unique reporter count should increase to 3
    And the system should promote "client_hash_C" to "Level 2" for "Non-Payment"
    And the system should flag this client as ready for the next Firebase sync

  Scenario: Independent feature counting
    Given "client_hash_D" has 2 reports for "No-Show"
    When a worker submits a new report for "client_hash_D" with the feature "Refused Protection"
    Then the "No-Show" count should remain at 2 (Level 1)
    And the "Refused Protection" count should become 1 (Level 1)
    And the client should not be promoted to Level 2