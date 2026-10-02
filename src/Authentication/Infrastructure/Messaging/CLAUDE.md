# Messaging/

`RevokeSessionsOnPenaltySubscriber` consumes Penalty's `UserBannedV1`/`UserSuspendedV1` and revokes the account's refresh tokens, idempotently (`AuthenticationProcessedEvents`).
