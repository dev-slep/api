# Crypto/

Argon2id `PasswordHasher`, libsodium `SecretEncrypter` (key from `AUTH_ENCRYPTION_KEY`, used for TOTP secrets), `RandomTokenGenerator`, `Sha256TokenHasher` (stored tokens are only hashes). Never log or return raw tokens.
