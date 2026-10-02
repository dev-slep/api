# Authentication/Domain/

Framework-free model. Aggregates `UserAccount`, `RefreshToken` (a token family), `OneTimeToken` (email verification, password reset) and `TwoFactorSecret`; value objects (`Email`, `PlainPassword`, `PasswordHash`, ids, `SocialIdentity`); repository interfaces; policies/ports (`PasswordHasher`, `TokenGenerator`, `TokenHasher`, `SecretEncrypter`, `PasswordPolicy`); `AuthenticationProblem` (every domain refusal, mapped to an HTTP problem). Change behaviour here first and cover every public method with a unit test.
