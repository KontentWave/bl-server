Feature: Hardware-Bound Authentication Initiation
  In order to securely bind a new device to the network
  As an unverified Android client
  I need to generate a temporary, 1-hour password tied to a phone number

  Scenario: Successfully requesting a new installation password
    Given an unverified client with the phone number "+421900123456"
    When the client requests an installation password via the "/api/auth/initiate" endpoint
    Then the system should respond with a 201 Created status
    And the response should contain a securely generated password
    And the database should store a hashed version of the password
    And the password expiration time should be set to exactly 60 minutes from now

  Scenario: Validating a password strictly within the 1-hour window
    Given an installation password was generated for "+421900123456" exactly 59 minutes ago
    When the system checks the validity of the password
    Then the system should report the password as valid

  Scenario: Rejecting an expired password
    Given an installation password was generated for "+421900123456" exactly 61 minutes ago
    When the system checks the validity of the password
    Then the system should report the password as expired
    And the system should reject any hardware-binding attempts for this number

  Scenario: Handling multiple password requests (Anti-Spam / Override)
    Given an active installation password already exists for "+421900123456"
    When the client requests a new installation password
    Then the system should invalidate the previously generated password
    And the system should generate a new password with a fresh 60-minute expiration

  Scenario: Successfully binding a device after ad verification
    Given an installation password exists for "+421900123456"
    And an active advertisement fixture can be verified for that number
    When the client submits the phone number, password, public key, and valid signature to "/api/auth/verify"
    Then the system should bind the public key to the phone number
    And the installation password should be invalidated

  Scenario: Rejecting hardware binding when no active advertisement is found
    Given an installation password exists for "+421900123456"
    And the advertisement fixture is suspended or missing for that number
    When the client submits the phone number, password, public key, and valid signature to "/api/auth/verify"
    Then the system should reject the hardware-binding attempt
    And the system should report that no active advertisement was verified

  Scenario: Rejecting hardware binding when the device signature is invalid
    Given an installation password exists for "+421900123456"
    And an active advertisement fixture can be verified for that number
    When the client submits the phone number, password, public key, and invalid signature to "/api/auth/verify"
    Then the system should reject the hardware-binding attempt
    And the system should report that the signature is invalid
