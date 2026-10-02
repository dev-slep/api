# config/jwt/

RS256 key pair used to sign and verify access tokens (`private.pem`, `public.pem`). Generate with `make jwt-keys` (skipped if present). Dev keys only: production keys are mounted as secrets and are never committed. Paths and the key id come from `JWT_*` variables in `.env`.
