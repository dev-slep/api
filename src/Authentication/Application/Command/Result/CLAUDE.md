# Command/Result/

Typed results returned by handlers (`AuthenticationResult`, `TokenPair`, `RecoveryCodes`, `TwoFactorEnrolment`, `CreatedAdmin`). A refusal that must leave an audit trail is a failed result, because throwing would roll the transaction back.
