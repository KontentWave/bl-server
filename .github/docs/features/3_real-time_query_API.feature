Feature: Real-Time Blacklist Query (The Oracle)
  In order to get instant warnings about incoming calls without storing data locally
  As a verified Android client
  I need to query the backend with a hashed phone number and receive its Level 2 status

  Scenario: Successfully querying a known Level 2 target
    Given "target_hash_A" has been promoted to Level 2 for "Aggressive" and "No-Show"
    When a verified worker queries the "/api/blacklist/check" endpoint with "target_hash_A"
    Then the system should respond with a 200 OK status
    And the response should contain the features ["Aggressive", "No-Show"]

  Scenario: Querying a Level 1 target (Privacy Buffer Protection)
    Given "target_hash_B" has 2 reports for "Non-Payment" but is still Level 1
    When a verified worker queries the "/api/blacklist/check" endpoint with "target_hash_B"
    Then the system should respond with a 200 OK status
    And the response should contain an empty array []
    And no buffer data should be leaked to the client

  Scenario: Querying an unknown, clean number
    Given "target_hash_C" does not exist in the database
    When a verified worker queries the "/api/blacklist/check" endpoint with "target_hash_C"
    Then the system should respond with a 200 OK status
    And the response should contain an empty array []

  Scenario: Rejecting unauthorized queries
    Given an unverified client with an invalid or missing hardware signature
    When the client queries the "/api/blacklist/check" endpoint with any target hash
    Then the system should reject the request
    And the system should return a 401 Unauthorized status