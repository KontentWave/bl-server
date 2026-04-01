Feature: SMS-Verified Hardware Binding
  In order to securely bind a real worker's device to the network
  As an unverified Android client
  I need to provide an active ad URL and prove ownership of the scraped phone number via SMS

  Scenario: Successfully initiating the SMS verification flow
    Given an unverified client provides the active ad URL "https://www.eurogirlsescort.com/escort/miriam/..."
    When the system scrapes the URL
    Then the system should successfully extract the phone number "+421900123456"
    And the system should generate a secure 6-digit OTP
    And the database should store a hashed version of the OTP with a 15-minute expiration
    And the system should dispatch an SMS containing the OTP to "+421900123456"
    And the system should return a challenge identifier and masked phone metadata to the Android client

  Scenario: Validating the OTP within the time window
    Given an OTP was sent to "+421900123456" exactly 10 minutes ago
    And the Android client has generated a hardware-backed public key pair
    When the Android client submits the challenge identifier, correct OTP, public key, and valid signature
    Then the system should verify the OTP
    And the system should authorize the hardware key binding

  Scenario: Rejecting an expired OTP during hardware binding
    Given an OTP was sent to "+421900123456" exactly 16 minutes ago
    And the Android client has generated a hardware-backed public key pair
    When the Android client submits the challenge identifier, expired OTP, public key, and valid signature
    Then the system should reject the verification attempt
    And the system should report that the OTP is invalid or expired

  Scenario: Rejecting an invalid device signature after OTP submission
    Given an OTP was sent to "+421900123456" exactly 10 minutes ago
    When the Android client submits the challenge identifier, correct OTP, public key, and invalid signature
    Then the system should reject the verification attempt
    And the system should report that the device signature is invalid

  Scenario: Handling invalid or missing ads
    Given an unverified client provides a broken or inactive ad URL
    When the system attempts to scrape the URL
    Then the system should fail to extract a valid phone number
    And the system should return a 400 Bad Request error
    And no SMS should be dispatched
