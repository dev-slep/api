# config/secrets/

Symfony secrets vault. `prod/` holds the encrypted production secrets and the public encryption key. Manage with `bin/console secrets:set NAME --env=prod`. Never put the decrypt private key in the repository outside CI/deploy secrets.
