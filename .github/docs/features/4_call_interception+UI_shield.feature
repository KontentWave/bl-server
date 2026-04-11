Feature: Call Interception and UI Shield
  In order to be instantly warned about dangerous clients before answering the phone
  As a verified Android client
  I need the app to detect incoming calls, securely query the Oracle, and display a visual/audio warning for Level 2 matches

  Scenario: Detecting and Warning about a Level 2 Caller
    Given the app has been granted "Phone State", "Call Log", and "System Alert Window" permissions
    And the Android device receives an incoming call from "+421900999888"
    When the app's BroadcastReceiver detects the "RINGING" state
    And the app securely queries the Oracle with the SHA-256 hash of the number
    And the Oracle responds with the Level 2 features ["Aggressive", "No-Show"]
    Then the app should instantly draw a high-priority red overlay over the screen
    And the overlay should clearly display the text: "Warning: Aggressive, No-Show"
    And the TalkBack accessibility service should immediately announce the warning aloud

  Scenario: Silent Operation for Clean or Level 1 Callers
    Given the Android device receives an incoming call from a clean or Level 1 number
    When the app's BroadcastReceiver detects the "RINGING" state
    And the app securely queries the Oracle with the SHA-256 hash of the number
    And the Oracle responds with an empty array []
    Then the app should remain completely silent in the background
    And no warning overlay should be drawn on the screen
    And the user's normal phone answering experience should not be interrupted

  Scenario: Requesting Sensitive OS Permissions Gracefully
    Given a verified worker opens the app to enable the Call Shield
    When the app checks its current Android permission status
    Then the app should display a clear, accessible explanation of why it needs to draw over other apps
    And if the user denies the "System Alert Window" permission
    Then the app should display a persistent "Shield Inactive" warning on the home screen

  Scenario: Submitting a new Level 1 report via the App UI
    Given a verified worker navigates to the "Submit Report" screen
    When the worker inputs the phone number "+421900555666"
    And the worker selects "Non-Payment" from the immutable feature dropdown
    And the worker presses the Submit button
    Then the app must convert the number to an E.164 format and hash it via SHA-256 locally
    And the app must NOT send the raw phone number over the network
    And the app must dispatch a signed request to the "/api/reports" endpoint
    And the UI should display a success confirmation message