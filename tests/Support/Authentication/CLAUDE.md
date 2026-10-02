# Support/Authentication/

Authentication test kit: `AuthenticationWorld` (hand-wired handlers over in-memory repos for unit tests), `AuthenticationApplicationTestCase`/`AuthenticationIntegrationTestCase`, `ReadsJsonResponses` (typed JSON accessors), fakes (`FakeAccessTokenIssuer`, `FakeAuthEmailSender`, `FakeSocialIdentityVerifier`, ...), recorders (`RecordingCommandBus`, `RecordingEventBus`, `RecordingMailer`) and crypto helpers (`RsaKey`, `SocialTokens`, `TotpCodes`). `Repository/` holds the repository contract tests.
